<?php
/**
 * Autenticación propia del portal: login, registro (con token de invitación),
 * recuperar/restablecer contraseña y salir.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Auth {

	private static $instance = null;

	const INVITES_OPTION = 'promotur_invites';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_promotur_invite', array( $this, 'handle_create_invite' ) );
	}

	/**
	 * admin-post: genera un link de invitación (desde el panel de equipo).
	 */
	public function handle_create_invite() {
		if ( ! current_user_can( 'promotur_manage_team' ) || ! check_admin_referer( 'promotur_invite' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'caaguazu-portal' ) );
		}
		$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : 'promotur_mini';
		if ( ! array_key_exists( $role, PROMOTUR_Roles::roles() ) ) {
			$role = 'promotur_mini';
		}
		$token = self::create_invite( $role, 14 );
		$link  = promotur_url( 'i/' . $token );
		/* translators: %s = enlace de invitación */
		promotur_flash( sprintf( __( 'Enlace de invitación creado (válido 14 días): %s', 'caaguazu-portal' ), $link ), 'success' );
		wp_safe_redirect( promotur_url( 'panel/equipo' ) );
		exit;
	}

	/**
	 * Renderiza (y procesa) una pantalla de auth.
	 *
	 * @param string $route login|registro|recuperar|restablecer
	 */
	public function render( $route ) {
		// Ya logueado: a /login o /registro no tiene sentido entrar.
		if ( is_user_logged_in() && in_array( $route, array( 'login', 'registro' ), true ) ) {
			wp_safe_redirect( $this->safe_next() );
			exit;
		}

		$vars = array( 'error' => '', 'notice' => '', 'next' => $this->raw_next(), 'token' => '' );

		switch ( $route ) {
			case 'login':       $vars = $this->process_login( $vars ); break;
			case 'registro':    $vars = $this->process_register( $vars ); break;
			case 'recuperar':   $vars = $this->process_recover( $vars ); break;
			case 'restablecer': $vars = $this->process_reset( $vars ); break;
		}

		promotur_template( 'auth/' . $route, $vars );
	}

	/* --------------------------------------------------------------------- */

	private function raw_next() {
		return isset( $_REQUEST['next'] ) ? esc_url_raw( wp_unslash( $_REQUEST['next'] ) ) : '';
	}

	/**
	 * Destino seguro post-login (whitelist al propio host).
	 */
	private function safe_next() {
		$next = $this->raw_next();
		if ( $next ) {
			$validated = wp_validate_redirect( $next, '' );
			if ( $validated ) {
				return $validated;
			}
		}
		return promotur_url( 'panel' );
	}

	private function verify( $action ) {
		return isset( $_POST['promotur_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['promotur_nonce'] ) ), $action );
	}

	/* ----- Login ----- */
	private function process_login( $vars ) {
		if ( empty( $_POST['promotur_auth'] ) || 'login' !== $_POST['promotur_auth'] ) {
			return $vars;
		}
		if ( ! $this->verify( 'promotur_login' ) ) {
			$vars['error'] = __( 'Sesión expirada. Recargá la página.', 'caaguazu-portal' );
			return $vars;
		}
		$creds = array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) ),
			'user_password' => (string) ( $_POST['user_pass'] ?? '' ),
			'remember'      => ! empty( $_POST['remember'] ),
		);
		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			$vars['error'] = __( 'Usuario o contraseña incorrectos.', 'caaguazu-portal' );
			return $vars;
		}
		wp_safe_redirect( $this->safe_next() );
		exit;
	}

	/* ----- Registro ----- */
	private function process_register( $vars ) {
		// Token de invitación (de la query var o del POST).
		$token = sanitize_text_field( get_query_var( 'promotur_token' ) );
		if ( ! $token && isset( $_REQUEST['token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_REQUEST['token'] ) );
		}
		$vars['token'] = $token;
		$invite        = $this->get_invite( $token );

		if ( empty( $_POST['promotur_auth'] ) || 'registro' !== $_POST['promotur_auth'] ) {
			return $vars;
		}
		if ( ! $this->verify( 'promotur_registro' ) ) {
			$vars['error'] = __( 'Sesión expirada. Recargá la página.', 'caaguazu-portal' );
			return $vars;
		}

		$username = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ) );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$pass     = (string) ( $_POST['user_pass'] ?? '' );

		if ( ! $username || ! is_email( $email ) || strlen( $pass ) < 6 ) {
			$vars['error'] = __( 'Completá usuario, un email válido y una contraseña de 6+ caracteres.', 'caaguazu-portal' );
			return $vars;
		}
		if ( username_exists( $username ) || email_exists( $email ) ) {
			$vars['error'] = __( 'Ese usuario o email ya existe.', 'caaguazu-portal' );
			return $vars;
		}

		// Rol: el de la invitación (si es válida) o visitante por defecto.
		$role = $invite && ! empty( $invite['role'] ) ? $invite['role'] : 'promotur_visitante';
		if ( ! array_key_exists( $role, PROMOTUR_Roles::roles() ) ) {
			$role = 'promotur_visitante';
		}

		$user_id = wp_insert_user( array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $pass,
			'role'       => $role,
		) );
		if ( is_wp_error( $user_id ) ) {
			$vars['error'] = $user_id->get_error_message();
			return $vars;
		}

		if ( $invite ) {
			$this->consume_invite( $token );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );
		wp_safe_redirect( $this->safe_next() );
		exit;
	}

	/* ----- Recuperar (solicitar) ----- */
	private function process_recover( $vars ) {
		if ( empty( $_POST['promotur_auth'] ) || 'recuperar' !== $_POST['promotur_auth'] ) {
			return $vars;
		}
		if ( ! $this->verify( 'promotur_recuperar' ) ) {
			$vars['error'] = __( 'Sesión expirada. Recargá la página.', 'caaguazu-portal' );
			return $vars;
		}
		// retrieve_password lee $_POST['user_login']; aceptamos email o usuario.
		$_POST['user_login'] = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) );
		$result = retrieve_password();
		if ( is_wp_error( $result ) ) {
			// No revelamos si existe o no: mensaje genérico.
			$vars['notice'] = __( 'Si la cuenta existe, te enviamos un email con instrucciones.', 'caaguazu-portal' );
		} else {
			$vars['notice'] = __( 'Si la cuenta existe, te enviamos un email con instrucciones.', 'caaguazu-portal' );
		}
		return $vars;
	}

	/* ----- Restablecer (con key+login) ----- */
	private function process_reset( $vars ) {
		$login = sanitize_text_field( wp_unslash( $_REQUEST['login'] ?? '' ) );
		$key   = sanitize_text_field( wp_unslash( $_REQUEST['key'] ?? '' ) );
		$vars['login'] = $login;
		$vars['key']   = $key;

		$user = ( $login && $key ) ? check_password_reset_key( $key, $login ) : new WP_Error( 'missing', '' );
		$vars['valid_key'] = ! is_wp_error( $user );

		if ( empty( $_POST['promotur_auth'] ) || 'restablecer' !== $_POST['promotur_auth'] ) {
			if ( $login && $key && is_wp_error( $user ) ) {
				$vars['error'] = __( 'El enlace de restablecimiento venció o no es válido.', 'caaguazu-portal' );
			}
			return $vars;
		}
		if ( ! $this->verify( 'promotur_restablecer' ) ) {
			$vars['error'] = __( 'Sesión expirada. Recargá la página.', 'caaguazu-portal' );
			return $vars;
		}
		if ( is_wp_error( $user ) ) {
			$vars['error'] = __( 'El enlace de restablecimiento venció o no es válido.', 'caaguazu-portal' );
			return $vars;
		}
		$pass1 = (string) ( $_POST['pass1'] ?? '' );
		if ( strlen( $pass1 ) < 6 ) {
			$vars['error'] = __( 'La contraseña debe tener 6+ caracteres.', 'caaguazu-portal' );
			return $vars;
		}
		reset_password( $user, $pass1 );
		wp_safe_redirect( promotur_url( 'login' ) . '?reset=1' );
		exit;
	}

	/* ----- Salir ----- */
	public function logout() {
		// Si viene con nonce de wp_logout lo respetamos; igual cerramos sesión.
		wp_logout();
		wp_safe_redirect( promotur_url( 'login' ) );
		exit;
	}

	/* ----- Invitaciones ----- */

	/**
	 * Devuelve la invitación si el token es válido y no venció.
	 *
	 * @param string $token
	 * @return array|null { role, expires }
	 */
	public function get_invite( $token ) {
		if ( ! $token ) { return null; }
		$invites = get_option( self::INVITES_OPTION, array() );
		if ( ! isset( $invites[ $token ] ) ) { return null; }
		$inv = $invites[ $token ];
		if ( ! empty( $inv['expires'] ) && time() > (int) $inv['expires'] ) {
			return null;
		}
		return $inv;
	}

	private function consume_invite( $token ) {
		$invites = get_option( self::INVITES_OPTION, array() );
		if ( isset( $invites[ $token ] ) ) {
			unset( $invites[ $token ] );
			update_option( self::INVITES_OPTION, $invites );
		}
	}

	/**
	 * Crea un token de invitación (lo usa el panel de equipo del Promotor).
	 *
	 * @param string $role
	 * @param int    $ttl_days
	 * @return string token
	 */
	public static function create_invite( $role = 'promotur_mini', $ttl_days = 14 ) {
		$invites = get_option( self::INVITES_OPTION, array() );
		$token   = wp_generate_password( 20, false, false );
		$invites[ $token ] = array(
			'role'    => $role,
			'expires' => time() + ( $ttl_days * DAY_IN_SECONDS ),
			'by'      => get_current_user_id(),
		);
		update_option( self::INVITES_OPTION, $invites );
		return $token;
	}
}
