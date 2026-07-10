<?php
/**
 * Sesión propia del sistema de cuentas — independiente de la sesión de
 * WordPress (no usa wp_set_auth_cookie ni las cookies de WP).
 *
 * Mecánica:
 *   - Al iniciar sesión se genera un token aleatorio de 256 bits. En la base
 *     de datos se guarda SÓLO su hash SHA-256 (nunca el token en claro), igual
 *     que el patrón de token_hash de las invitaciones del Portal: si alguien
 *     lee la tabla, no puede reconstruir cookies válidas.
 *   - La cookie del navegador lleva "<account_id>:<token_en_claro>", firmada
 *     con HMAC-SHA256 usando las claves de sal de WordPress, de modo que una
 *     cookie manipulada se descarta antes siquiera de tocar la base.
 *   - Cookie HttpOnly + Secure (bajo HTTPS) + SameSite=Lax, con la ruta del
 *     sitio. No accesible desde JavaScript.
 *
 * La cuenta actual se resuelve una sola vez por request y se cachea.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Caaguazu_Cuentas_Sessions {

	private static $instance = null;

	/** Nombre de la cookie de sesión. */
	const COOKIE = 'caaguazu_cuenta';

	/** Duración por defecto de una sesión (segundos). 14 días. */
	const LIFETIME = 1209600;

	/** Duración de una sesión "recordarme". 60 días. */
	const LIFETIME_REMEMBER = 5184000;

	/** Cuenta resuelta para este request (false = aún no calculado). */
	private $current = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Resolvemos temprano para que la cuenta esté lista para otros plugins.
		$this->resolve();
	}

	/**
	 * @return string Nombre de la tabla de sesiones.
	 */
	private static function table() {
		$t = Caaguazu_Cuentas_Install::tables();
		return $t['sessions'];
	}

	/* --------------------------------------------------------------------- */
	/*  Firma de cookie                                                       */
	/* --------------------------------------------------------------------- */

	/**
	 * Clave secreta para firmar la cookie: derivada de las sales de WordPress.
	 * Si alguien las rota, todas las sesiones se invalidan (comportamiento
	 * deseado, igual que las cookies de auth de WP).
	 *
	 * @return string
	 */
	private static function secret() {
		$base = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : '' );
		if ( '' === $base ) {
			$base = get_option( 'caaguazu_cuentas_fallback_secret' );
			if ( ! $base ) {
				$base = wp_generate_password( 64, true, true );
				update_option( 'caaguazu_cuentas_fallback_secret', $base, false );
			}
		}
		return 'caaguazu-cuentas|' . $base;
	}

	/**
	 * Firma un payload de cookie.
	 *
	 * @param string $payload "<account_id>:<token>"
	 * @return string "<payload>:<hmac>"
	 */
	private static function sign( $payload ) {
		$mac = hash_hmac( 'sha256', $payload, self::secret() );
		return $payload . ':' . $mac;
	}

	/**
	 * Verifica y separa una cookie firmada.
	 *
	 * @param string $cookie
	 * @return array|null { account_id:int, token:string }
	 */
	private static function unsign( $cookie ) {
		$parts = explode( ':', (string) $cookie );
		if ( count( $parts ) !== 3 ) {
			return null;
		}
		list( $account_id, $token, $mac ) = $parts;
		$payload  = $account_id . ':' . $token;
		$expected = hash_hmac( 'sha256', $payload, self::secret() );
		if ( ! hash_equals( $expected, (string) $mac ) ) {
			return null;
		}
		if ( ! ctype_digit( (string) $account_id ) || '' === $token ) {
			return null;
		}
		return array( 'account_id' => (int) $account_id, 'token' => $token );
	}

	/**
	 * Hash con el que se guarda/busca un token en la base.
	 *
	 * @param string $token
	 * @return string
	 */
	private static function token_hash( $token ) {
		return hash( 'sha256', $token );
	}

	/* --------------------------------------------------------------------- */
	/*  Ciclo de vida de la sesión                                            */
	/* --------------------------------------------------------------------- */

	/**
	 * Inicia una sesión para una cuenta: crea la fila y setea la cookie.
	 *
	 * @param int  $account_id
	 * @param bool $remember
	 * @return bool
	 */
	public function start( $account_id, $remember = false ) {
		global $wpdb;
		$account_id = (int) $account_id;
		if ( $account_id <= 0 ) {
			return false;
		}

		$token    = wp_generate_password( 43, false, false ); // ~256 bits base62
		$lifetime = $remember ? self::LIFETIME_REMEMBER : self::LIFETIME;
		$now      = time();

		$wpdb->insert( self::table(), array( // phpcs:ignore WordPress.DB
			'account_id'   => $account_id,
			'token_hash'   => self::token_hash( $token ),
			'created_at'   => gmdate( 'Y-m-d H:i:s', $now ),
			'expires_at'   => gmdate( 'Y-m-d H:i:s', $now + $lifetime ),
			'last_seen_at' => gmdate( 'Y-m-d H:i:s', $now ),
			'ip'           => self::client_ip(),
			'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
		) );

		$this->set_cookie( self::sign( $account_id . ':' . $token ), $now + $lifetime );
		$this->current = Caaguazu_Cuentas_Accounts::get( $account_id );
		return true;
	}

	/**
	 * Cierra la sesión actual: borra su fila y expira la cookie.
	 */
	public function destroy() {
		global $wpdb;
		$parsed = $this->parse_cookie();
		if ( $parsed ) {
			$wpdb->delete( self::table(), array( 'token_hash' => self::token_hash( $parsed['token'] ) ) ); // phpcs:ignore WordPress.DB
		}
		$this->set_cookie( '', time() - 3600 );
		$this->current = null;
	}

	/**
	 * Resuelve (una vez por request) la cuenta de la cookie de sesión.
	 *
	 * @return array|null Fila de cuenta activa, o null.
	 */
	public function resolve() {
		if ( false !== $this->current ) {
			return $this->current;
		}
		$this->current = null;

		$parsed = $this->parse_cookie();
		if ( ! $parsed ) {
			return null;
		}

		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT * FROM {$table} WHERE token_hash = %s",
			self::token_hash( $parsed['token'] )
		), ARRAY_A );

		if ( ! $row || (int) $row['account_id'] !== $parsed['account_id'] ) {
			return null;
		}
		if ( strtotime( $row['expires_at'] . ' UTC' ) < time() ) {
			$wpdb->delete( $table, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB
			return null;
		}

		$account = Caaguazu_Cuentas_Accounts::get( (int) $row['account_id'] );
		if ( ! $account || 'active' !== $account['status'] ) {
			return null;
		}

		// Sello de actividad, a lo sumo una vez por hora (evita un write por hit).
		if ( empty( $row['last_seen_at'] ) || strtotime( $row['last_seen_at'] . ' UTC' ) < time() - HOUR_IN_SECONDS ) {
			$wpdb->update( $table, array( 'last_seen_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB
		}

		$this->current = $account;
		return $account;
	}

	/**
	 * Cuenta actual (cacheada).
	 *
	 * @return array|null
	 */
	public function current() {
		return false === $this->current ? $this->resolve() : $this->current;
	}

	/**
	 * Purga sesiones vencidas (para un cron opcional del panel).
	 *
	 * @return int filas borradas
	 */
	public static function purge_expired() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s", gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB
	}

	/* --------------------------------------------------------------------- */
	/*  Cookie helpers                                                        */
	/* --------------------------------------------------------------------- */

	/**
	 * Lee y valida la cookie cruda del request.
	 *
	 * @return array|null { account_id, token }
	 */
	private function parse_cookie() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return null;
		}
		return self::unsign( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
	}

	/**
	 * Setea (o expira) la cookie de sesión.
	 *
	 * @param string $value
	 * @param int    $expires timestamp
	 */
	private function set_cookie( $value, $expires ) {
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
		$secure = is_ssl();

		// setcookie con opciones (PHP 7.3+) para poder fijar SameSite.
		if ( PHP_VERSION_ID >= 70300 ) {
			setcookie( self::COOKIE, $value, array(
				'expires'  => $expires,
				'path'     => $path,
				'domain'   => $domain,
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			) );
		} else {
			setcookie( self::COOKIE, $value, $expires, $path . '; samesite=Lax', $domain, $secure, true );
		}
		// Disponible ya mismo en este request.
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/**
	 * IP del cliente (para trazar sesiones). Best-effort, sin confiar en proxies.
	 *
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return $ip ? mb_substr( sanitize_text_field( $ip ), 0, 64 ) : '';
	}
}
