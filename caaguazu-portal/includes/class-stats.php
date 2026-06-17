<?php
/**
 * Pulso/estadísticas: vistas por ficha, búsquedas sin resultado, niveles de confianza,
 * producción por autor y salud del contenido.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Stats {

	private static $instance = null;
	const EMPTY_SEARCHES = 'promotur_empty_searches';
	const LEVEL_META     = 'promotur_nivel';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'maybe_count_view' ) );
	}

	/* ----- Vistas ----- */
	public function maybe_count_view() {
		if ( ! is_singular( PROMOTUR_Destinos::CPT ) ) { return; }
		$id = get_queried_object_id();
		// Dedupe simple por cookie para no inflar con recargas.
		$cookie = 'promotur_v_' . $id;
		if ( isset( $_COOKIE[ $cookie ] ) ) { return; }
		$views = (int) get_post_meta( $id, '_promotur_views', true );
		update_post_meta( $id, '_promotur_views', $views + 1 );
		if ( ! headers_sent() ) {
			setcookie( $cookie, '1', time() + 6 * HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/' );
		}
	}

	public static function views( $post_id ) {
		return (int) get_post_meta( $post_id, '_promotur_views', true );
	}

	public static function top_viewed( $limit = 8 ) {
		return get_posts( array(
			'post_type'      => PROMOTUR_Destinos::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'meta_key'       => '_promotur_views',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		) );
	}

	/* ----- Búsquedas sin resultado ----- */
	public static function log_empty_search( $q ) {
		$q = trim( wp_strip_all_tags( (string) $q ) );
		if ( '' === $q || mb_strlen( $q ) > 120 ) { return; }
		$log = get_option( self::EMPTY_SEARCHES, array() );
		if ( ! is_array( $log ) ) { $log = array(); }
		$key = mb_strtolower( $q );
		if ( ! isset( $log[ $key ] ) ) {
			$log[ $key ] = array( 'q' => $q, 'count' => 0, 'last' => '' );
		}
		$log[ $key ]['count']++;
		$log[ $key ]['last'] = current_time( 'mysql' );
		// Cap: las 100 más recientes.
		if ( count( $log ) > 100 ) {
			uasort( $log, function ( $a, $b ) { return strcmp( $b['last'], $a['last'] ); } );
			$log = array_slice( $log, 0, 100, true );
		}
		update_option( self::EMPTY_SEARCHES, $log, false );
	}

	public static function empty_searches() {
		$log = get_option( self::EMPTY_SEARCHES, array() );
		if ( ! is_array( $log ) ) { return array(); }
		uasort( $log, function ( $a, $b ) { return $b['count'] <=> $a['count']; } );
		return $log;
	}

	/* ----- Niveles de confianza ----- */
	public static function levels() {
		return array(
			'aprendiz'  => __( 'Aprendiz', 'caaguazu-portal' ),
			'jr'        => __( 'Promotor Jr', 'caaguazu-portal' ),
			'confianza' => __( 'De confianza', 'caaguazu-portal' ),
		);
	}

	public static function get_level( $user_id ) {
		$l = get_user_meta( $user_id, self::LEVEL_META, true );
		return $l ? $l : 'aprendiz';
	}

	public static function level_label( $user_id ) {
		$levels = self::levels();
		$l = self::get_level( $user_id );
		return isset( $levels[ $l ] ) ? $levels[ $l ] : $levels['aprendiz'];
	}

	public static function set_level( $user_id, $level ) {
		if ( array_key_exists( $level, self::levels() ) ) {
			update_user_meta( $user_id, self::LEVEL_META, $level );
		}
	}

	/* ----- Producción ----- */

	/** Cuenta destinos de un autor por estado de publicación. */
	public static function author_counts( $user_id ) {
		$pub = new WP_Query( array( 'post_type' => PROMOTUR_Destinos::CPT, 'post_status' => 'publish', 'author' => $user_id, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		$all = new WP_Query( array( 'post_type' => PROMOTUR_Destinos::CPT, 'post_status' => array( 'publish', 'draft', 'pending' ), 'author' => $user_id, 'posts_per_page' => 1, 'fields' => 'ids' ) );
		return array( 'publicadas' => (int) $pub->found_posts, 'total' => (int) $all->found_posts );
	}

	/**
	 * Salud del contenido: publicados sin portada y desactualizados (> N meses).
	 *
	 * @return array{ sin_foto:WP_Post[], viejas:WP_Post[] }
	 */
	public static function content_health( $meses = 6 ) {
		$pub = get_posts( array( 'post_type' => PROMOTUR_Destinos::CPT, 'post_status' => 'publish', 'posts_per_page' => 200 ) );
		$sin_foto = array();
		$viejas   = array();
		$limite   = strtotime( "-{$meses} months" );
		foreach ( $pub as $p ) {
			if ( ! get_post_meta( $p->ID, '_promotur_portada', true ) && ! has_post_thumbnail( $p ) ) {
				$sin_foto[] = $p;
			}
			$verif = get_post_meta( $p->ID, '_promotur_verificado_en', true );
			$ref   = $verif ? strtotime( $verif ) : strtotime( $p->post_modified_gmt );
			if ( $ref && $ref < $limite ) { $viejas[] = $p; }
		}
		return array( 'sin_foto' => $sin_foto, 'viejas' => $viejas );
	}
}
