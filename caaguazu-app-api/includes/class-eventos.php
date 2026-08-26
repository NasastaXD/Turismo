<?php
/**
 * CPT Evento + endpoint.
 *
 * Un evento es una categoría general aparte del atractivo: tiene fecha, y esa
 * fecha lo hace aparecer y desaparecer solo. Reusa el mismo flujo editorial y
 * las mismas taxonomías que los destinos.
 *
 * El lugar puede venir de dos formas: referenciando un destino ya cargado (lo
 * habitual — la fiesta es EN tal lugar) o con coordenadas propias, para
 * eventos que ocurren donde no hay ficha. La API resuelve las dos a un mismo
 * bloque `lugar` con lat/lng ya listas, para que el cliente no tenga que
 * ramificar.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_Eventos {

	private static $instance = null;

	const CPT = 'promotur_evento';

	const META_INICIO   = '_evento_inicio';
	const META_FIN      = '_evento_fin';
	const META_LUGAR_ID = '_evento_lugar_ref';  // ID de promotur_destino, o 0
	const META_LAT      = '_evento_lat';
	const META_LNG      = '_evento_lng';
	const META_COSTO    = '_evento_costo';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	public function register_post_type() {
		register_post_type( self::CPT, array(
			'labels' => array(
				'name'          => __( 'Eventos', 'caaguazu-app-api' ),
				'singular_name' => __( 'Evento', 'caaguazu-app-api' ),
				'menu_name'     => __( 'Eventos', 'caaguazu-app-api' ),
			),
			'public'          => true,
			'show_ui'         => true,
			'show_in_rest'    => false, // la app usa /czu-app/v1/eventos, no el REST nativo
			'menu_icon'       => 'dashicons-calendar-alt',
			'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'has_archive'     => false,
			'rewrite'         => array( 'slug' => 'evento' ),
			'taxonomies'      => array( CZUAPI_Taxonomias::TAX_CATEGORIA, CZUAPI_Taxonomias::TAX_ZONA ),
		) );

		foreach ( array( self::META_INICIO, self::META_FIN, self::META_COSTO ) as $key ) {
			register_post_meta( self::CPT, $key, array(
				'type' => 'string', 'single' => true, 'show_in_rest' => false,
			) );
		}
		foreach ( array( self::META_LAT, self::META_LNG ) as $key ) {
			register_post_meta( self::CPT, $key, array(
				'type' => 'number', 'single' => true, 'show_in_rest' => false,
			) );
		}
		register_post_meta( self::CPT, self::META_LUGAR_ID, array(
			'type' => 'integer', 'single' => true, 'show_in_rest' => false,
		) );
	}

	public function register_routes() {
		register_rest_route( CZUAPI_NS, '/eventos', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'lista' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'desde'      => array( 'type' => 'string' ), // ISO 8601
				'hasta'      => array( 'type' => 'string' ),
				'categoria'  => array( 'type' => 'integer' ),
				'pagina'     => array( 'type' => 'integer', 'default' => 1 ),
				'por_pagina' => array( 'type' => 'integer', 'default' => 20 ),
			),
		) );

		register_rest_route( CZUAPI_NS, '/eventos/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'detalle' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function lista( $request ) {
		$pagina     = max( 1, (int) $request->get_param( 'pagina' ) );
		$por_pagina = min( 100, max( 1, (int) $request->get_param( 'por_pagina' ) ) );

		$meta_query = array();

		// Por defecto: lo que todavía no terminó. Un evento pasado no le sirve
		// a un turista, y hacer que el cliente filtre implica bajarlos todos.
		$desde = $request->get_param( 'desde' );
		$hasta = $request->get_param( 'hasta' );

		$meta_query[] = array(
			'key'     => self::META_INICIO,
			'value'   => $desde ? gmdate( 'Y-m-d H:i:s', strtotime( (string) $desde ) ) : gmdate( 'Y-m-d H:i:s' ),
			'compare' => '>=',
			'type'    => 'DATETIME',
		);
		if ( $hasta ) {
			$meta_query[] = array(
				'key'     => self::META_INICIO,
				'value'   => gmdate( 'Y-m-d H:i:s', strtotime( (string) $hasta ) ),
				'compare' => '<=',
				'type'    => 'DATETIME',
			);
		}

		$args = array_merge( czuapi_args_publicado(), array(
			'post_type'      => self::CPT,
			'posts_per_page' => $por_pagina,
			'paged'          => $pagina,
			'meta_key'       => self::META_INICIO, // phpcs:ignore WordPress.DB.SlowDBQuery
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );

		if ( $request->get_param( 'categoria' ) ) {
			$args['tax_query'] = array( array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'taxonomy' => CZUAPI_Taxonomias::TAX_CATEGORIA,
				'field'    => 'term_id',
				'terms'    => (int) $request->get_param( 'categoria' ),
			) );
		}

		$q     = new WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $post ) {
			$items[] = $this->formato( $post, false );
		}

		return new WP_REST_Response(
			CZUAPI_Response::paginado( $items, $q->found_posts, $pagina, $por_pagina ),
			200
		);
	}

	public function detalle( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || self::CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return CZUAPI_Response::no_encontrado();
		}
		return new WP_REST_Response( $this->formato( $post, true ), 200 );
	}

	/**
	 * @param WP_Post $post
	 * @param bool    $completo incluye cuerpo del artículo
	 * @return array
	 */
	private function formato( $post, $completo ) {
		$id = $post->ID;

		$out = array(
			'id'        => (int) $id,
			'tipo'      => 'evento',
			'titulo'    => get_the_title( $post ),
			'inicio'    => czuapi_fecha( (string) get_post_meta( $id, self::META_INICIO, true ) ),
			'fin'       => czuapi_fecha( (string) get_post_meta( $id, self::META_FIN, true ) ),
			'lugar'     => $this->lugar( $id ),
			'costo'     => (string) get_post_meta( $id, self::META_COSTO, true ),
			'categoria' => czuapi_primer_termino( $id, CZUAPI_Taxonomias::TAX_CATEGORIA ),
			// Pedido por el lado de la app: las tarjetas de evento llevan foto
			// y el payload original no la traía.
			'portada'   => czuapi_imagen( (int) get_post_thumbnail_id( $id ) ),
			'resumen'   => get_the_excerpt( $post ),
		);

		if ( $completo ) {
			$out['articulo_html'] = apply_filters( 'the_content', $post->post_content );
			$out['autor']         = czuapi_autor( $id );
			$out['actualizado']   = czuapi_fecha( $post->post_modified_gmt );
		}

		return $out;
	}

	/**
	 * Lugar resuelto: si referencia un destino, hereda sus coordenadas; si no,
	 * usa las propias. El cliente recibe siempre la misma forma.
	 *
	 * @return array|null
	 */
	private function lugar( $id ) {
		$ref = (int) get_post_meta( $id, self::META_LUGAR_ID, true );

		if ( $ref > 0 && 'publish' === get_post_status( $ref ) ) {
			return array(
				'ref_tipo' => 'destino',
				'ref_id'   => $ref,
				'nombre'   => get_the_title( $ref ),
				'lat'      => (float) get_post_meta( $ref, '_promotur_lat', true ),
				'lng'      => (float) get_post_meta( $ref, '_promotur_lng', true ),
			);
		}

		$lat = get_post_meta( $id, self::META_LAT, true );
		$lng = get_post_meta( $id, self::META_LNG, true );
		if ( '' === $lat || '' === $lng ) {
			return null;
		}
		return array(
			'ref_tipo' => 'propio',
			'ref_id'   => null,
			'nombre'   => '',
			'lat'      => (float) $lat,
			'lng'      => (float) $lng,
		);
	}

	/**
	 * Markers de eventos vigentes, para /mapa/markers.
	 *
	 * @return array[]
	 */
	public function markers() {
		$ids = get_posts( array_merge( czuapi_args_publicado(), array(
			'post_type'      => self::CPT,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'key'     => self::META_INICIO,
				'value'   => gmdate( 'Y-m-d H:i:s' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			) ),
		) ) );

		$out = array();
		foreach ( $ids as $id ) {
			$lugar = $this->lugar( $id );
			if ( ! $lugar || ! $lugar['lat'] ) { continue; }
			$cat   = czuapi_primer_termino( $id, CZUAPI_Taxonomias::TAX_CATEGORIA );
			$out[] = array(
				'id'        => (int) $id,
				'tipo'      => 'evento',
				'lat'       => (float) $lugar['lat'],
				'lng'       => (float) $lugar['lng'],
				'categoria' => $cat ? $cat['id'] : null,
			);
		}
		return $out;
	}
}
