<?php
/**
 * Autenticación del portal: login, registro (con token de invitación),
 * recuperar/restablecer contraseña y salir.
 *
 * Corre enteramente sobre el sistema de cuentas universal (caaguazu-cuentas,
 * plugin hermano) — ninguna persona del panel tiene ya un usuario de
 * WordPress. Los administradores siguen entrando por wp-login.php/wp-admin
 * como siempre (ver PROMOTUR_Router::maybe_block_wp_login()); esta clase no
 * los toca.
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
		// Invite-only: se desactiva el alta nativa de WordPress (el registro de
		// cuentas ya no pasa por wp_insert_user en absoluto).
		add_filter( 'option_users_can_register', '__return_zero' );

		// Auditoría de login: wp_login/wp_login_failed (WP) ya no se disparan
		// para los promotores, que ahora inician sesión por caaguazu-cuentas.
		add_action( 'caaguazu_cuentas_logged_in', array( $this, 'audit_login_success' ) );
		add_action( 'caaguazu_cuentas_login_failed', array( $this, 'audit_login_failed' ) );
	}

	/**
	 * admin-post: genera un link de invitación (desde el panel de equipo del Promotor).
	 */
	public function handle_create_invite() {
		if ( ! caaguazu_account_can( 'promotor', 'promotur_manage_team' ) || ! check_admin_referer( 'promotur_invite' ) ) {
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
	 * Auditoría: login exitoso de una cuenta (caaguazu-cuentas).
	 *
	 * @param array $account
	 */
	public function audit_login_success( $account ) {
		if ( ! class_exists( 'PROMOTUR_Audit' ) ) { return; }
		$id = (int) $account['id'];
		PROMOTUR_Audit::log( 'login_success', array( 'user_id' => $id, 'entity_type' => 'account', 'entity_id' => $id ) );
	}

	/**
	 * Auditoría: intento de login fallido (email inexistente, contraseña
	 * incorrecta, o cuenta suspendida/inactiva).
	 *
	 * @param string     $email
	 * @param array|null $account
	 */
	public function audit_login_failed( $email, $account = null ) {
		if ( ! class_exists( 'PROMOTUR_Audit' ) ) { return; }
		PROMOTUR_Audit::log( 'login_failed', array(
			'user_id'     => $account ? (int) $account['id'] : 0,
			'entity_type' => 'account',
			'entity_id'   => $account ? (int) $account['id'] : null,
			'payload'     => array( 'email' => substr( (string) $email, 0, 190 ) ),
		) );
	}

	/**
	 * Renderiza (y procesa) una pantalla de auth.
	 *
	 * @param string $route login|registro|recuperar|restablecer
	 */
	public function render( $route ) {
		// Ya logueado: a /czu-login o /registro no tiene sentido entrar.
		if ( caaguazu_is_logged_in() && in_array( $route, array( 'login', 'registro' ), true ) ) {
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
		$email    = sanitize_email( wp_unslash( $_POST['user_login'] ?? '' ) );
		$password = (string) ( $_POST['user_pass'] ?? '' );
		$remember = ! empty( $_POST['remember'] );

		$account = caaguazu_account_login( $email, $password, $remember );
		if ( is_wp_error( $account ) ) {
			$vars['error'] = $account->get_error_message();
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

		$display_name = sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) );
		$email        = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$phone        = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$pass         = (string) ( $_POST['user_pass'] ?? '' );

		if ( ! $display_name || ! is_email( $email ) || '' === $phone || ! Caaguazu_Cuentas_Passwords::is_valid( $pass ) ) {
			$vars['error'] = __( 'Completá usuario, email, teléfono y una contraseña de 6+ caracteres.', 'caaguazu-portal' );
			return $vars;
		}
		if ( Caaguazu_Cuentas_Accounts::email_exists( $email ) ) {
			$vars['error'] = __( 'Ese email ya tiene una cuenta.', 'caaguazu-portal' );
			return $vars;
		}

		$role = array_key_exists( $row['role'], PROMOTUR_Roles::roles() ) ? $row['role'] : 'promotur_visitante';

		$account = Caaguazu_Cuentas_Auth::instance()->register( array(
			'email'        => $email,
			'password'     => $pass,
			'display_name' => $display_name,
			'phone'        => $phone,
		) );
		if ( is_wp_error( $account ) ) {
			$vars['error'] = $account->get_error_message();
			return $vars;
		}
		$account_id = (int) $account['id'];

		caaguazu_account_grant( $account_id, 'promotor', $role, null, null );
		caaguazu_account_meta_set( $account_id, 'invited_via', (int) $row['id'] );
		PROMOTUR_Invitations::mark_used( (int) $row['id'], $account_id );
		PROMOTUR_Audit::log( 'account_registered', array( 'entity_type' => 'account', 'entity_id' => $account_id, 'payload' => array( 'role' => $role ) ) );

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
		$email = sanitize_email( wp_unslash( $_POST['user_login'] ?? '' ) );
		Caaguazu_Cuentas_Auth::instance()->request_reset( $email, function ( $account_email, $token ) {
			// login/key: mismos nombres de campo que espera templates/auth/restablecer.php.
			return add_query_arg(
				array( 'login' => rawurlencode( $account_email ), 'key' => rawurlencode( $token ) ),
				promotur_url( 'recuperar/restablecer' )
			);
		} );
		// No revelamos si el email existe: mensaje siempre genérico.
		$vars['notice'] = __( 'Si la cuenta existe, te enviamos un email con instrucciones.', 'caaguazu-portal' );
		return $vars;
	}

	/* ----- Restablecer (con login+key: mismos nombres que usa el template) ----- */
	private function process_reset( $vars ) {
		// $_REQUEST cubre tanto el GET del link del email como el POST del
		// formulario (que reenvía login/key como campos ocultos).
		$email = sanitize_email( wp_unslash( $_REQUEST['login'] ?? '' ) );
		$token = sanitize_text_field( wp_unslash( $_REQUEST['key'] ?? '' ) );
		$vars['login'] = $email;
		$vars['key']   = $token;

		$check = ( $email && $token ) ? Caaguazu_Cuentas_Auth::instance()->check_reset( $email, $token ) : new WP_Error( 'missing', '' );
		$vars['valid_key'] = ! is_wp_error( $check );

		if ( empty( $_POST['promotur_auth'] ) || 'restablecer' !== $_POST['promotur_auth'] ) {
			if ( $email && $token && is_wp_error( $check ) ) {
				$vars['error'] = __( 'El enlace de restablecimiento venció o no es válido.', 'caaguazu-portal' );
			}
			return $vars;
		}
		if ( ! $this->verify( 'promotur_restablecer' ) ) {
			$vars['error'] = __( 'Sesión expirada. Recargá la página.', 'caaguazu-portal' );
			return $vars;
		}
		$pass1  = (string) ( $_POST['pass1'] ?? '' );
		$result = Caaguazu_Cuentas_Auth::instance()->reset( $email, $token, $pass1 );
		if ( is_wp_error( $result ) ) {
			$vars['error'] = $result->get_error_message();
			return $vars;
		}
		wp_safe_redirect( promotur_url( 'login' ) . '?reset=1' );
		exit;
	}

	/* ----- Salir ----- */
	public function logout() {
		caaguazu_account_logout();
		wp_safe_redirect( promotur_url( 'login' ) );
		exit;
	}

}
