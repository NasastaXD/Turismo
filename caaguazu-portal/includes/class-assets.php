<?php
/**
 * Assets del portal: un único CSS + un único JS, encolados SOLO en rutas del portal.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Assets {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Rutas que cargan los assets del portal.
	 */
	private function is_portal_route() {
		$route = get_query_var( 'promotur_route' );
		return in_array( $route, array( 'panel', 'login', 'registro', 'recuperar', 'restablecer', 'pwa-offline' ), true );
	}

	public function enqueue() {
		if ( ! $this->is_portal_route() ) {
			return;
		}

		wp_enqueue_style(
			'promotur',
			promotur_asset( 'css/caaguazu-portal.css' ),
			array(),
			PROMOTUR_VERSION
		);

		// El theme ya carga Sora + Lato globalmente; los heredamos vía var(--font-*).
		wp_enqueue_script(
			'promotur',
			promotur_asset( 'js/caaguazu-portal.js' ),
			array(),
			PROMOTUR_VERSION,
			true
		);

		wp_localize_script( 'promotur', 'PROMOTUR', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'adminPost' => admin_url( 'admin-post.php' ),
			'nonce'     => wp_create_nonce( 'promotur' ),
			'swUrl'     => home_url( '/promotur-sw.js' ),
			'manifest'  => home_url( '/promotur-manifest.webmanifest' ),
			'urls'      => array(
				'panel' => promotur_url( 'panel' ),
				'login' => promotur_url( 'login' ),
				'salir' => promotur_url( 'salir' ),
			),
			'i18n'      => array(
				'install'  => __( 'Instalar app', 'caaguazu-portal' ),
				'sending'  => __( 'Enviando…', 'caaguazu-portal' ),
				'error'    => __( 'Ocurrió un error. Probá de nuevo.', 'caaguazu-portal' ),
				'saved'    => __( 'Guardado', 'caaguazu-portal' ),
				'confirm'  => __( '¿Confirmás esta acción?', 'caaguazu-portal' ),
				'missing'  => __( 'Faltan datos obligatorios', 'caaguazu-portal' ),
			),
		) );
	}
}
