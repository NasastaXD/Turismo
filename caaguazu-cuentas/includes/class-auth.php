<?php
/**
 * Servicio de autenticación del sistema de cuentas: login, logout, registro,
 * y el flujo de recuperación/restablecimiento de contraseña.
 *
 * Esta clase es la lógica pura (sin rutas ni templates): los paneles la
 * consumen desde sus propios controladores. El Portal, en la ronda de
 * cutover, reemplaza sus llamadas a wp_signon/wp_insert_user/reset_password
 * por estas.
 *
 * El correo de recuperación se envía con wp_mail (infra de mail del sitio),
 * pero el token de reset es propio (no el de WordPress) y vive en un meta de
 * la cuenta, hasheado.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Caaguazu_Cuentas_Auth {

	private static $instance = null;

	/** Validez del token de reset (segundos). 1 hora. */
	const RESET_TTL = 3600;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Intenta iniciar sesión con email + contraseña.
	 *
	 * @param string $email
	 * @param string $password
	 * @param bool   $remember
	 * @return array|WP_Error Cuenta al iniciar sesión, o WP_Error.
	 */
	public function login( $email, $password, $remember = false ) {
		$account = Caaguazu_Cuentas_Accounts::get_by_email( $email );

		// Mensaje genérico para no revelar si el email existe.
		$generic = new WP_Error( 'invalid_login', __( 'Email o contraseña incorrectos.', 'caaguazu-cuentas' ) );

		if ( ! $account ) {
			return $generic;
		}
		if ( 'suspended' === $account['status'] ) {
			return new WP_Error( 'suspended', __( 'Tu cuenta está suspendida. Contactá al equipo.', 'caaguazu-cuentas' ) );
		}
		if ( 'active' !== $account['status'] ) {
			return $generic;
		}
		if ( ! Caaguazu_Cuentas_Passwords::verify( $password, $account['pass_hash'] ) ) {
			return $generic;
		}

		// Rehash transparente si el hash venía en un formato heredado (phpass).
		if ( Caaguazu_Cuentas_Passwords::needs_rehash( $account['pass_hash'] ) ) {
			Caaguazu_Cuentas_Accounts::set_hash( $account['id'], Caaguazu_Cuentas_Passwords::hash( $password ) );
		}

		Caaguazu_Cuentas_Sessions::instance()->start( (int) $account['id'], $remember );
		Caaguazu_Cuentas_Accounts::touch_login( (int) $account['id'] );

		/**
		 * Una cuenta inició sesión.
		 *
		 * @param array $account
		 */
		do_action( 'caaguazu_cuentas_logged_in', $account );
		return $account;
	}

	/**
	 * Cierra la sesión de la cuenta actual.
	 */
	public function logout() {
		do_action( 'caaguazu_cuentas_logout', caaguazu_account_id() );
		Caaguazu_Cuentas_Sessions::instance()->destroy();
	}

	/**
	 * Registra una cuenta nueva y la deja logueada.
	 *
	 * @param array $data { email, password, display_name?, phone? }
	 * @param bool  $login_after
	 * @return array|WP_Error Cuenta creada, o WP_Error.
	 */
	public function register( array $data, $login_after = true ) {
		$id = Caaguazu_Cuentas_Accounts::create( $data );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$account = Caaguazu_Cuentas_Accounts::get( $id );
		if ( $login_after ) {
			Caaguazu_Cuentas_Sessions::instance()->start( $id, false );
			Caaguazu_Cuentas_Accounts::touch_login( $id );
		}
		return $account;
	}

	/* --------------------------------------------------------------------- */
	/*  Recuperación de contraseña                                            */
	/* --------------------------------------------------------------------- */

	/**
	 * Genera un token de reset y envía el email. No revela si el email existe.
	 *
	 * @param string   $email
	 * @param callable $url_builder función( $email, $token ) → URL de reset
	 * @return true Siempre true (respuesta genérica).
	 */
	public function request_reset( $email, $url_builder ) {
		$account = Caaguazu_Cuentas_Accounts::get_by_email( $email );
		if ( $account && 'active' === $account['status'] ) {
			$token = wp_generate_password( 32, false, false );
			caaguazu_account_meta_set( (int) $account['id'], '_reset', array(
				'hash'    => hash( 'sha256', $token ),
				'expires' => time() + self::RESET_TTL,
			) );

			$url  = is_callable( $url_builder ) ? call_user_func( $url_builder, $account['email'], $token ) : '';
			$body = sprintf(
				/* translators: %s = enlace de restablecimiento */
				__( "Recibimos un pedido para restablecer tu contraseña.\n\nEntrá a este enlace (válido 1 hora):\n%s\n\nSi no fuiste vos, ignorá este mensaje.", 'caaguazu-cuentas' ),
				$url
			);
			wp_mail( $account['email'], __( 'Restablecer tu contraseña', 'caaguazu-cuentas' ), $body );
		}
		return true;
	}

	/**
	 * Valida un token de reset para un email.
	 *
	 * @param string $email
	 * @param string $token
	 * @return array|WP_Error Cuenta si el token es válido, o WP_Error.
	 */
	public function check_reset( $email, $token ) {
		$account = Caaguazu_Cuentas_Accounts::get_by_email( $email );
		$invalid = new WP_Error( 'invalid_token', __( 'El enlace venció o no es válido.', 'caaguazu-cuentas' ) );
		if ( ! $account ) {
			return $invalid;
		}
		$reset = caaguazu_account_meta_get( (int) $account['id'], '_reset' );
		if ( ! is_array( $reset ) || empty( $reset['hash'] ) || empty( $reset['expires'] ) ) {
			return $invalid;
		}
		if ( $reset['expires'] < time() ) {
			return $invalid;
		}
		if ( ! hash_equals( (string) $reset['hash'], hash( 'sha256', (string) $token ) ) ) {
			return $invalid;
		}
		return $account;
	}

	/**
	 * Restablece la contraseña con un token válido y limpia el token.
	 *
	 * @param string $email
	 * @param string $token
	 * @param string $new_password
	 * @return true|WP_Error
	 */
	public function reset( $email, $token, $new_password ) {
		$account = $this->check_reset( $email, $token );
		if ( is_wp_error( $account ) ) {
			return $account;
		}
		$ok = Caaguazu_Cuentas_Accounts::set_password( (int) $account['id'], $new_password );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		caaguazu_account_meta_delete( (int) $account['id'], '_reset' );
		return true;
	}
}
