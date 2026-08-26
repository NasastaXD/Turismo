<?php
/**
 * Autenticación por token bearer para la app.
 *
 * No reimplementa identidad: valida contra las cuentas de caaguazu-cuentas y
 * delega TODO chequeo de permiso en caaguazu_account_can(), pasando el ID de
 * cuenta explícito (esa API acepta un account_id, así que no hace falta
 * simular una sesión global).
 *
 * Por qué tabla propia y no la de sesiones de caaguazu-cuentas: una sesión de
 * navegador y un token de teléfono tienen ciclos de vida distintos — cerrar
 * sesión en la web no debe desloguear el celular — y así esta capa no escribe
 * en la tabla de otro plugin. La disciplina es la misma: se guarda solo el
 * hash SHA-256, nunca el token.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_Auth {

	private static $instance = null;

	/** Vigencia de un token de app. */
	const LIFETIME = 2592000; // 30 días

	/** Cuenta resuelta para este request (false = todavía no se calculó). */
	private $current = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	private static function table() {
		$t = CZUAPI_Install::tables();
		return $t['tokens'];
	}

	private static function token_hash( $token ) {
		return hash( 'sha256', $token );
	}

	public function register_routes() {
		register_rest_route( CZUAPI_NS, '/auth/login', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'login' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'email'    => array( 'required' => true, 'type' => 'string' ),
				'password' => array( 'required' => true, 'type' => 'string' ),
			),
		) );

		register_rest_route( CZUAPI_NS, '/auth/logout', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'logout' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( CZUAPI_NS, '/auth/me', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'me' ),
			'permission_callback' => '__return_true',
		) );
	}

	/* --------------------------------------------------------------------- */
	/*  Resolución del token                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * Cuenta autenticada de este request, o null.
	 *
	 * @return array|null fila de cuenta
	 */
	public function current_account() {
		if ( false !== $this->current ) {
			return $this->current;
		}
		$this->current = null;

		$token = $this->bearer_token();
		if ( ! $token ) {
			return null;
		}

		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT * FROM {$table} WHERE token_hash = %s",
			self::token_hash( $token )
		), ARRAY_A );

		if ( ! $row ) {
			return null;
		}
		if ( strtotime( $row['expires_at'] . ' UTC' ) < time() ) {
			$wpdb->delete( $table, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB
			return null;
		}

		$cuenta = Caaguazu_Cuentas_Accounts::get( (int) $row['account_id'] );
		if ( ! $cuenta || 'active' !== $cuenta['status'] ) {
			return null;
		}

		// Sello de actividad, a lo sumo una vez por hora.
		if ( empty( $row['last_seen_at'] ) || strtotime( $row['last_seen_at'] . ' UTC' ) < time() - HOUR_IN_SECONDS ) {
			$wpdb->update( $table, array( 'last_seen_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB
		}

		$this->current = $cuenta;
		return $cuenta;
	}

	/**
	 * @return int 0 si no hay cuenta autenticada
	 */
	public function current_account_id() {
		$cuenta = $this->current_account();
		return $cuenta ? (int) $cuenta['id'] : 0;
	}

	/**
	 * Token del header Authorization.
	 *
	 * @return string
	 */
	private function bearer_token() {
		$header = '';
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$header = wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		} elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			// Apache con CGI no propaga Authorization salvo por esta vía.
			$header = wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}
		if ( ! $header || 0 !== stripos( $header, 'bearer ' ) ) {
			return '';
		}
		return trim( substr( $header, 7 ) );
	}

	/* --------------------------------------------------------------------- */
	/*  Guards reutilizables                                                  */
	/* --------------------------------------------------------------------- */

	/**
	 * Guard: requiere token válido.
	 *
	 * @return true|WP_REST_Response
	 */
	public function require_auth() {
		return $this->current_account_id() > 0 ? true : CZUAPI_Response::no_autenticado();
	}

	/**
	 * Guard: requiere token válido + capability en el panel `promotor`.
	 *
	 * El chequeo lo hace caaguazu-cuentas, no esta capa: los permisos tienen
	 * dos ejes (rol y nivel de confianza) y esa lógica vive allá.
	 *
	 * @param string $cap
	 * @return true|WP_REST_Response
	 */
	public function require_cap( $cap ) {
		$id = $this->current_account_id();
		if ( $id <= 0 ) {
			return CZUAPI_Response::no_autenticado();
		}
		return caaguazu_account_can( 'promotor', $cap, $id ) ? true : CZUAPI_Response::sin_permiso();
	}

	/* --------------------------------------------------------------------- */
	/*  Endpoints                                                             */
	/* --------------------------------------------------------------------- */

	public function login( $request ) {
		$email    = sanitize_email( (string) $request->get_param( 'email' ) );
		$password = (string) $request->get_param( 'password' );

		if ( ! is_email( $email ) || '' === $password ) {
			return CZUAPI_Response::error( 'credenciales_invalidas', __( 'Email o contraseña incorrectos.', 'caaguazu-app-api' ), 401 );
		}

		$cuenta = Caaguazu_Cuentas_Accounts::get_by_email( $email );
		if ( ! $cuenta
			|| 'active' !== $cuenta['status']
			|| ! Caaguazu_Cuentas_Passwords::verify( $password, $cuenta['pass_hash'] ) ) {
			// Mismo mensaje en los tres casos: no revelar si el email existe.
			return CZUAPI_Response::error( 'credenciales_invalidas', __( 'Email o contraseña incorrectos.', 'caaguazu-app-api' ), 401 );
		}

		$account_id = (int) $cuenta['id'];
		$token      = wp_generate_password( 43, false, false ); // ~256 bits base62
		$expires    = time() + self::LIFETIME;

		global $wpdb;
		$wpdb->insert( self::table(), array( // phpcs:ignore WordPress.DB
			'account_id'   => $account_id,
			'token_hash'   => self::token_hash( $token ),
			'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			'expires_at'   => gmdate( 'Y-m-d H:i:s', $expires ),
			'last_seen_at' => gmdate( 'Y-m-d H:i:s' ),
			'device'       => isset( $_SERVER['HTTP_USER_AGENT'] )
				? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 )
				: '',
		) );

		Caaguazu_Cuentas_Accounts::touch_login( $account_id );

		/** Auditoría: el panel ya escucha este hook para su propio log. */
		do_action( 'caaguazu_cuentas_logged_in', $cuenta );

		return new WP_REST_Response( array(
			'token'      => $token,
			'expires_at' => gmdate( 'c', $expires ),
			'cuenta'     => $this->perfil( $account_id, $cuenta ),
		), 200 );
	}

	public function logout( $request ) {
		$token = $this->bearer_token();
		if ( $token ) {
			global $wpdb;
			$wpdb->delete( self::table(), array( 'token_hash' => self::token_hash( $token ) ) ); // phpcs:ignore WordPress.DB
		}
		return new WP_REST_Response( null, 204 );
	}

	public function me( $request ) {
		$cuenta = $this->current_account();
		if ( ! $cuenta ) {
			return CZUAPI_Response::no_autenticado();
		}
		return new WP_REST_Response( $this->perfil( (int) $cuenta['id'], $cuenta ), 200 );
	}

	/**
	 * Perfil que consume la app.
	 *
	 * `permisos` es lo que la app usa para gatear la UI. Va resuelto del lado
	 * servidor a propósito: los permisos dependen de DOS ejes —el rol del panel
	 * y el nivel de confianza de la cuenta— y hacer que el cliente combine eso
	 * es pedirle que reimplemente reglas que van a cambiar.
	 *
	 * @param int   $account_id
	 * @param array $cuenta
	 * @return array
	 */
	private function perfil( $account_id, $cuenta ) {
		$grant = class_exists( 'Caaguazu_Cuentas_Panels' )
			? Caaguazu_Cuentas_Panels::instance()->get_grant( $account_id, 'promotor' )
			: null;
		$rol = ( $grant && 'active' === $grant['status'] ) ? (string) $grant['role'] : '';

		return array(
			'id'       => $account_id,
			'nombre'   => $cuenta['display_name'] ? $cuenta['display_name'] : $cuenta['email'],
			'email'    => $cuenta['email'],
			'rol'      => $rol,
			'nivel'    => class_exists( 'PROMOTUR_Stats' ) ? PROMOTUR_Stats::get_level( $account_id ) : '',
			'permisos' => $this->permisos( $account_id ),
		);
	}

	/**
	 * Capabilities efectivas de la cuenta, ya combinando rol y nivel.
	 *
	 * Los dos que salen del nivel de confianza y no del rol:
	 *   - promotur_edit_published   → nivel Jr o superior
	 *   - promotur_publish_destino  → nivel "De confianza"
	 *
	 * @param int $account_id
	 * @return string[]
	 */
	private function permisos( $account_id ) {
		$caps = array();

		if ( class_exists( 'Caaguazu_Cuentas_Panels' ) ) {
			$efectivas = Caaguazu_Cuentas_Panels::instance()->effective_caps( $account_id, 'promotor' );
			foreach ( $efectivas as $cap => $granted ) {
				if ( $granted && 0 === strpos( $cap, 'promotur_' ) ) {
					$caps[ $cap ] = true;
				}
			}
		}

		if ( class_exists( 'PROMOTUR_Stats' ) ) {
			if ( PROMOTUR_Stats::can_edit_published( $account_id ) ) {
				$caps['promotur_edit_published'] = true;
			}
			if ( PROMOTUR_Stats::can_publish_directly( $account_id ) ) {
				$caps['promotur_publish_destino'] = true;
			}
		}

		return array_values( array_keys( $caps ) );
	}

	/**
	 * Purga de tokens vencidos (para un cron opcional).
	 *
	 * @return int
	 */
	public static function purge_expired() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"DELETE FROM {$table} WHERE expires_at < %s",
			gmdate( 'Y-m-d H:i:s' )
		) );
	}
}
