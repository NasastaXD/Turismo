<?php
/**
 * Enrutador propio: query vars, rewrite rules, dispatch y guards.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Router {

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

		// Bloquear wp-login.php para usuarios del portal (con excepciones).
		add_action( 'login_init', array( $this, 'maybe_block_wp_login' ) );

		// WP no debe "adivinar" URLs y romper nuestras rutas.
		remove_action( 'template_redirect', 'redirect_guess_404_permalink' );
	}

	/**
	 * Query vars propias.
	 */
	public function query_vars( $vars ) {
		$vars[] = 'promotur_route';
		$vars[] = 'promotur_sub';
		$vars[] = 'promotur_size';
		$vars[] = 'promotur_token';
		return $vars;
	}

	/**
	 * Reglas de reescritura (top). Más específicas primero.
	 * Estático para reutilizar en la activación, antes del flush.
	 */
	public static function add_rewrite_rules() {
		// PWA.
		add_rewrite_rule( '^promotur-manifest\.webmanifest$', 'index.php?promotur_route=pwa-manifest', 'top' );
		add_rewrite_rule( '^promotur-sw\.js$',                'index.php?promotur_route=pwa-sw', 'top' );
		add_rewrite_rule( '^promotur-icon-([0-9]+)\.png$',    'index.php?promotur_route=pwa-icon&promotur_size=$matches[1]', 'top' );
		add_rewrite_rule( '^promotur-offline/?$',             'index.php?promotur_route=pwa-offline', 'top' );

		// Auth (restablecer antes que recuperar).
		add_rewrite_rule( '^login/?$',                 'index.php?promotur_route=login', 'top' );
		add_rewrite_rule( '^registro/?$',              'index.php?promotur_route=registro', 'top' );
		add_rewrite_rule( '^recuperar/restablecer/?$', 'index.php?promotur_route=restablecer', 'top' );
		add_rewrite_rule( '^recuperar/?$',             'index.php?promotur_route=recuperar', 'top' );
		add_rewrite_rule( '^salir/?$',                 'index.php?promotur_route=salir', 'top' );

		// Link de invitación → registro con token.
		add_rewrite_rule( '^i/([^/]+)/?$', 'index.php?promotur_route=registro&promotur_token=$matches[1]', 'top' );

		// Panel (bajo /turismo/ para no vivir en la raíz del sitio).
		add_rewrite_rule( '^turismo/panel/?$',       'index.php?promotur_route=panel', 'top' );
		add_rewrite_rule( '^turismo/panel/(.+?)/?$', 'index.php?promotur_route=panel&promotur_sub=$matches[1]', 'top' );
	}

	/**
	 * Despacho en template_redirect.
	 */
	public function dispatch() {
		$route = get_query_var( 'promotur_route' );
		if ( '' === $route || null === $route ) {
			return;
		}

		nocache_headers();

		// El panel es una "app": sin admin bar de WordPress.
		add_filter( 'show_admin_bar', '__return_false' );

		switch ( $route ) {
			case 'pwa-manifest':
				PROMOTUR_PWA::instance()->render_manifest();
				exit;
			case 'pwa-sw':
				PROMOTUR_PWA::instance()->render_sw();
				exit;
			case 'pwa-icon':
				PROMOTUR_PWA::instance()->render_icon( (int) get_query_var( 'promotur_size' ) );
				exit;
			case 'pwa-offline':
				status_header( 200 );
				PROMOTUR_PWA::instance()->render_offline();
				exit;

			case 'login':
			case 'registro':
			case 'recuperar':
			case 'restablecer':
				status_header( 200 );
				PROMOTUR_Auth::instance()->render( $route );
				exit;

			case 'salir':
				PROMOTUR_Auth::instance()->logout();
				exit;

			case 'panel':
				$this->guard_panel();
				status_header( 200 );
				$sub     = (string) get_query_var( 'promotur_sub' );
				$parts   = '' === $sub ? array() : explode( '/', trim( $sub, '/' ) );
				$section = ! empty( $parts[0] ) ? sanitize_key( $parts[0] ) : 'home';
				$id      = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : null;
				PROMOTUR_Shell::instance()->render( $section, $id );
				exit;
		}
	}

	/**
	 * Guard del panel: login + capability base.
	 */
	private function guard_panel() {
		if ( ! is_user_logged_in() ) {
			$current = home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );
			wp_safe_redirect( promotur_url( 'login' ) . '?next=' . rawurlencode( $current ) );
			exit;
		}
		if ( ! current_user_can( 'promotur_view_panel' ) ) {
			wp_die(
				esc_html__( 'No tenés acceso a este panel.', 'caaguazu-portal' ),
				esc_html__( 'Acceso denegado', 'caaguazu-portal' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Redirige wp-login.php al login del portal, salvo admins y acciones de sistema.
	 */
	public function maybe_block_wp_login() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';
		$exempt = array( 'logout', 'resetpass', 'rp', 'postpass', 'lostpassword', 'retrievepassword' );
		if ( in_array( $action, $exempt, true ) ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return; // admins conservan wp-login.php
		}
		if ( ! apply_filters( 'promotur_block_wp_login', true ) ) {
			return;
		}
		wp_safe_redirect( promotur_url( 'login' ) );
		exit;
	}
}
