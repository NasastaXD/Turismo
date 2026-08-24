<?php
/**
 * La única ruta pública de este plugin: `/acceso-cead?code=`.
 *
 * A propósito no acepta ningún otro parámetro (nada de `next`/`redirect_to`):
 * el destino tras un canje exitoso es siempre el panel del Portal. Aceptar un
 * destino desde la URL abriría un open-redirect justo después de abrir
 * sesión, que es de lo peor que se puede regalar (ver README, "Seguridad").
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CEADSSO_Router {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'dispatch' ) );
	}

	public function query_vars( $vars ) {
		$vars[] = 'ceadsso_route';
		return $vars;
	}

	/**
	 * Estático para reutilizar en la activación, antes del flush.
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule( '^acceso-cead/?$', 'index.php?ceadsso_route=acceso', 'top' );
	}

	public function dispatch() {
		if ( 'acceso' !== get_query_var( 'ceadsso_route' ) ) {
			return;
		}
		nocache_headers();

		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		if ( ! ceadsso_deps_active() ) {
			CEADSSO_Log::record( 'error', 'dependencias_faltantes', array() );
			wp_die(
				esc_html__( 'El acceso desde el CEAD no está disponible en este momento.', 'caaguazu-sso-cead' ),
				esc_html__( 'No disponible', 'caaguazu-sso-cead' ),
				array( 'response' => 503 )
			);
		}

		$claims = CEADSSO_Redeem::redeem( $code );
		if ( is_wp_error( $claims ) ) {
			CEADSSO_Log::record( 'error', $claims->get_error_code(), array() );
			$this->fail( $claims->get_error_message() );
		}

		$result = CEADSSO_Link::resolve( $claims );
		if ( is_wp_error( $result ) ) {
			$this->fail( $result->get_error_message() );
		}

		// remember = false a propósito: una sesión de SSO vive del vínculo con
		// el CEAD, no tiene sentido que dure más que su curso (ver README).
		Caaguazu_Cuentas_Sessions::instance()->start( $result['account_id'], false );

		wp_safe_redirect( promotur_url( 'panel' ) );
		exit;
	}

	/**
	 * Pantalla de error sin detalles técnicos (contrato §5.1).
	 *
	 * @param string $mensaje
	 */
	private function fail( $mensaje ) {
		wp_die(
			esc_html( $mensaje ),
			esc_html__( 'No pudimos verificar tu acceso', 'caaguazu-sso-cead' ),
			array( 'response' => 400 )
		);
	}
}

/**
 * ¿Están activos los dos plugins de los que este depende (cuentas + portal)?
 *
 * @return bool
 */
function ceadsso_deps_active() {
	return function_exists( 'caaguazu_account_grant' )
		&& function_exists( 'promotur_url' )
		&& class_exists( 'Caaguazu_Cuentas_Sessions' );
}
