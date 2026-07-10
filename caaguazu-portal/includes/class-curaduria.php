<?php
/**
 * Curaduría de la home: destacados ordenables, banner de temporada con vigencia
 * y colecciones. Todo se guarda en opciones y alimenta el shortcode [promotur_home].
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Curaduria {

	private static $instance = null;
	const OPT_DESTACADOS = 'promotur_destacados';
	const OPT_BANNER     = 'promotur_banner';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_promotur_save_curaduria', array( $this, 'handle_save' ) );
	}

	/** IDs destacados, en orden. */
	public static function destacados() {
		$ids = get_option( self::OPT_DESTACADOS, array() );
		return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
	}

	/** Banner { title, text, url, desde, hasta }. */
	public static function banner() {
		$b = get_option( self::OPT_BANNER, array() );
		return wp_parse_args( is_array( $b ) ? $b : array(), array(
			'title' => '', 'text' => '', 'url' => '', 'desde' => '', 'hasta' => '',
		) );
	}

	/** ¿El banner está vigente hoy? */
	public static function banner_visible() {
		$b = self::banner();
		if ( '' === trim( $b['title'] ) ) { return false; }
		$today = current_time( 'Y-m-d' );
		if ( $b['desde'] && $today < $b['desde'] ) { return false; }
		if ( $b['hasta'] && $today > $b['hasta'] ) { return false; }
		return true;
	}

	/**
	 * Guarda destacados + banner (admin-post, gateado por capability).
	 */
	public function handle_save() {
		if ( ! caaguazu_account_can( 'promotor', 'promotur_curate_featured' ) || ! check_admin_referer( 'promotur_curaduria' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'caaguazu-portal' ) );
		}

		// Destacados: pares id => orden; nos quedamos con los marcados, ordenados.
		$selected = isset( $_POST['destacado'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['destacado'] ) ) : array();
		$orders   = isset( $_POST['orden'] ) ? (array) wp_unslash( $_POST['orden'] ) : array();
		$pairs    = array();
		foreach ( $selected as $id ) {
			$pairs[ $id ] = isset( $orders[ $id ] ) ? (int) $orders[ $id ] : 999;
		}
		asort( $pairs );
		update_option( self::OPT_DESTACADOS, array_keys( $pairs ) );

		update_option( self::OPT_BANNER, array(
			'title' => sanitize_text_field( wp_unslash( $_POST['banner_title'] ?? '' ) ),
			'text'  => sanitize_text_field( wp_unslash( $_POST['banner_text'] ?? '' ) ),
			'url'   => esc_url_raw( wp_unslash( $_POST['banner_url'] ?? '' ) ),
			'desde' => sanitize_text_field( wp_unslash( $_POST['banner_desde'] ?? '' ) ),
			'hasta' => sanitize_text_field( wp_unslash( $_POST['banner_hasta'] ?? '' ) ),
		) );

		promotur_flash( __( 'Curaduría guardada. La home ya refleja los cambios.', 'caaguazu-portal' ), 'success' );
		wp_safe_redirect( promotur_url( 'panel/curaduria' ) );
		exit;
	}
}
