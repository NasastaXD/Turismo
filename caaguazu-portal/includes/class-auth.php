<?php
/**
 * Autenticación propia del portal: login, registro (con token de invitación),
 * recuperar/restablecer contraseña y salir.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Auth {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_promotur_invite', array( $this, 'handle_create_invite' ) );
		// Invite-only: bloquear suspendidos y desactivar el alta nativa de WordPress.
		add_filter( 'authenticate', array( $this, 'block_suspended' ), 30 );
		add_filter( 'option_users_can_register', '__return_zero' );
	}

	/**
	 * Bloquea el login de usuarios suspendidos (filtro authenticate).
	 */
	public function block_suspended( $user ) {
		if ( $user instanceof WP_User && get_user_meta( $user->ID, '_promotur_suspended', true ) ) {
			return new WP_Error( 'promotur_suspended', __( 'Tu cuenta está suspendida. Contactá al equipo.', 'caaguazu-portal' ) );
		}
		return $user;
	}

	/**
	 * admin-post: genera un link de invitación (desde el panel de equipo del Promotor).
	 */
	public function handle_create_invite() {
		if ( ! current_user_can( 'promotur_manage_team' ) || ! check_admin_referer( 'promotur_invite' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'caaguazu-portal' ) );
		}
		$role = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : 'promotur_mini';
		$tokens = PROMOTUR_Invitations::create( array( 'role' => $role, 'expires_days' => 14, 'count' => 1 ) );
		$link   = PROMOTUR_Invitations::registration_url( $tokens[0] );
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
			$vars['error'] = ( 'promotur_suspended' === $user->get_error_code() )
				? __( 'Tu cuenta está suspendida. Contactá al equipo.', 'caaguazu-portal' )
				: __( 'Usuario o contraseña incorrectos.', 'caaguazu-portal' );
			return $vars;
		}
		wp_safe_redirect( $this->safe_next() );
		exit;
	}

	/* ----- Registro (INVITE-ONLY) ----- */
	private function process_register( $vars ) {
		// Token de invitación (de la query var o del POST).
		$token = sanitize_text_field( get_query_var( 'promotur_token' ) );
		if ( ! $token && isset( $_REQUEST['token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_REQUEST['token'] ) );
		}
		$row    = PROMOTUR_Invitations::find_by_token( $token );
		$status = PROMOTUR_Invitations::status( $row );

		$vars['token']         = $token;
		$vars['invite_status'] = $status;            // valid|used|expired|revoked|invalid
		$vars['invite_role']   = $row ? PROMOTUR_Roles::label( $row['role'] ) : '';

		if ( empty( $_POST['promotur_auth'] ) || 'registro' !== $_POST['promotur_auth'] ) {
			return $vars;
		}
		if ( ! $this->verify( 'promotur_registro' ) ) {
			$vars['error'] = __( 'Sesión expirada. Recargá la página.', 'caaguazu-portal' );
			return $vars;
		}
		// Sólo con invitación válida (invite-only).
		if ( 'valid' !== $status ) {
			$vars['error'] = __( 'Necesitás una invitación válida para registrarte.', 'caaguazu-portal' );
			return $vars;
		}

		$username = sanitize_user( wp_unslash( $_POST['user_login'] ?? '' ) );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone    = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$pass     = (string) ( $_POST['user_pass'] ?? '' );

		if ( ! $username || ! is_email( $email ) || '' === $phone || strlen( $pass ) < 6 ) {
			$vars['error'] = __( 'Completá usuario, email, teléfono y una contraseña de 6+ caracteres.', 'caaguazu-portal' );
			return $vars;
		}
		if ( username_exists( $username ) || email_exists( $email ) ) {
			$vars['error'] = __( 'Ese usuario o email ya existe.', 'caaguazu-portal' );
			return $vars;
		}

		$role = array_key_exists( $row['role'], PROMOTUR_Roles::roles() ) ? $row['role'] : 'promotur_visitante';

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

		update_user_meta( $user_id, '_promotur_phone', $phone );
		update_user_meta( $user_id, '_promotur_invited_via', (int) $row['id'] );
		PROMOTUR_Invitations::mark_used( (int) $row['id'], (int) $user_id );
		PROMOTUR_Audit::log( 'user_registered', array( 'user_id' => $user_id, 'entity_type' => 'user', 'entity_id' => $user_id, 'payload' => array( 'role' => $role ) ) );

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

}
