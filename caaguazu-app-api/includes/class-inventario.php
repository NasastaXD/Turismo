<?php
/**
 * Inventario de atractivos: lista, detalle y markers del mapa.
 *
 * Lee del CPT `promotur_destino` de caaguazu-portal. No duplica el modelo ni
 * reimplementa la visibilidad: el estado editorial manda, y solo `publicado`
 * llega a `post_status = publish`.
 *
 * Tres endpoints y no uno porque las tres pantallas necesitan cosas muy
 * distintas: el mapa quiere miles de pines mínimos, la lista quiere lo justo
 * para pintar una tarjeta, y el detalle quiere todo. Servir el detalle
 * completo a las tres es lo que hace que una lista de 128 fichas descargue
 * megabytes para mostrar foto, nombre y precio.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_Inventario {

	private static $instance = null;

	/** Meta de rango de precio: entero 0–4 (0 = gratis). Ver nota abajo. */
	const META_RANGO_PRECIO = '_promotur_rango_precio';

	/** Meta con los IDs de artículos relacionados. */
	const META_ARTICULOS = '_promotur_articulos_rel';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_routes() {
		register_rest_route( CZUAPI_NS, '/inventario', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'lista' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'categoria'  => array( 'type' => 'integer' ),
				'zona'       => array( 'type' => 'integer' ),
				'bbox'       => array( 'type' => 'string' ),  // "minLng,minLat,maxLng,maxLat"
				'buscar'     => array( 'type' => 'string' ),
				'pagina'     => array( 'type' => 'integer', 'default' => 1 ),
				'por_pagina' => array( 'type' => 'integer', 'default' => 20 ),
			),
		) );

		register_rest_route( CZUAPI_NS, '/inventario/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'detalle' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( CZUAPI_NS, '/mapa/markers', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'markers' ),
			'permission_callback' => '__return_true',
		) );
	}

	/* --------------------------------------------------------------------- */

	public function lista( $request ) {
		$pagina     = max( 1, (int) $request->get_param( 'pagina' ) );
		$por_pagina = min( 100, max( 1, (int) $request->get_param( 'por_pagina' ) ) );

		$args = array_merge( czuapi_args_publicado(), array(
			'post_type'      => PROMOTUR_Destinos::CPT,
			'posts_per_page' => $por_pagina,
			'paged'          => $pagina,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$tax_query = array();
		if ( $request->get_param( 'categoria' ) ) {
			$tax_query[] = array(
				'taxonomy' => CZUAPI_Taxonomias::TAX_CATEGORIA,
				'field'    => 'term_id',
				'terms'    => (int) $request->get_param( 'categoria' ),
			);
		}
		if ( $request->get_param( 'zona' ) ) {
			$tax_query[] = array(
				'taxonomy' => CZUAPI_Taxonomias::TAX_ZONA,
				'field'    => 'term_id',
				'terms'    => (int) $request->get_param( 'zona' ),
			);
		}
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		if ( $request->get_param( 'buscar' ) ) {
			$args['s'] = sanitize_text_field( (string) $request->get_param( 'buscar' ) );
		}

		$bbox = $this->parse_bbox( (string) $request->get_param( 'bbox' ) );
		if ( $bbox ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'AND',
				array( 'key' => '_promotur_lat', 'value' => array( $bbox['min_lat'], $bbox['max_lat'] ), 'type' => 'DECIMAL(10,6)', 'compare' => 'BETWEEN' ),
				array( 'key' => '_promotur_lng', 'value' => array( $bbox['min_lng'], $bbox['max_lng'] ), 'type' => 'DECIMAL(10,6)', 'compare' => 'BETWEEN' ),
			);
		}

		$q     = new WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $post ) {
			$items[] = $this->item_lista( $post );
		}

		return new WP_REST_Response(
			CZUAPI_Response::paginado( $items, $q->found_posts, $pagina, $por_pagina ),
			200
		);
	}

	/**
	 * Elemento de lista: solo lo que pinta una tarjeta.
	 *
	 * @param WP_Post $post
	 * @return array
	 */
	private function item_lista( $post ) {
		$id = $post->ID;
		return array(
			'id'              => (int) $id,
			'tipo'            => 'destino',
			'titulo'          => get_the_title( $post ),
			'gancho'          => (string) get_post_meta( $id, '_promotur_gancho', true ),
			'categoria'       => czuapi_primer_termino( $id, CZUAPI_Taxonomias::TAX_CATEGORIA ),
			'zona'            => czuapi_primer_termino( $id, CZUAPI_Taxonomias::TAX_ZONA ),
			'coordenadas'     => $this->coordenadas( $id ),
			'portada'         => $this->portada( $id ),
			'rango_precio'    => $this->rango_precio( $id ),
			'horario_resumen' => (string) get_post_meta( $id, '_promotur_horario', true ),
			'actualizado'     => czuapi_fecha( $post->post_modified_gmt ),
		);
	}

	public function detalle( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );

		if ( ! $post || PROMOTUR_Destinos::CPT !== $post->post_type || 'publish' !== $post->post_status ) {
			return CZUAPI_Response::no_encontrado();
		}

		$m = function ( $key ) use ( $id ) {
			return (string) get_post_meta( $id, $key, true );
		};

		return new WP_REST_Response( array(
			'id'          => $id,
			'tipo'        => 'destino',
			'titulo'      => get_the_title( $post ),
			'gancho'      => $m( '_promotur_gancho' ),
			'categoria'   => czuapi_primer_termino( $id, CZUAPI_Taxonomias::TAX_CATEGORIA ),
			'zona'        => czuapi_primer_termino( $id, CZUAPI_Taxonomias::TAX_ZONA ),
			'etiquetas'   => $this->etiquetas( $id ),
			'coordenadas' => $this->coordenadas( $id ),
			'portada'     => $this->portada( $id ),
			'galeria'     => $this->galeria( $id ),
			'video'       => $m( '_promotur_video' ) ? $m( '_promotur_video' ) : null,
			'practicos'   => array(
				'horario'      => $m( '_promotur_horario' ),
				'costo'        => $m( '_promotur_costo' ),
				'rango_precio' => $this->rango_precio( $id ),
				'duracion'     => $m( '_promotur_duracion' ),
				'servicios'    => $m( '_promotur_servicios' ),
				'temporada'    => $m( '_promotur_temporada' ),
				'contacto'     => $m( '_promotur_contacto' ),
			),
			'acceso'      => array(
				'como_llegar'   => $m( '_promotur_como_llegar' ),
				'referencia'    => $m( '_promotur_referencia' ),
				'estado_camino' => $m( '_promotur_estado_camino' ),
				'accesibilidad' => $m( '_promotur_accesibilidad' ),
			),
			'articulo_html'          => apply_filters( 'the_content', $post->post_content ),
			'articulos_relacionados' => $this->articulos_relacionados( $id ),
			'fuentes'                => $m( '_promotur_fuentes' ),
			'autor'                  => czuapi_autor( $id ),
			'actualizado'            => czuapi_fecha( $post->post_modified_gmt ),
		), 200 );
	}

	/**
	 * Markers: payload deliberadamente mínimo.
	 *
	 * Va separado de /inventario porque el mapa carga TODO de una y no
	 * paginado: sumarle un solo campo de texto acá se multiplica por la
	 * cantidad de pines.
	 */
	public function markers( $request ) {
		$out = array();

		// Destinos.
		$destinos = get_posts( array_merge( czuapi_args_publicado(), array(
			'post_type'      => PROMOTUR_Destinos::CPT,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) );
		foreach ( $destinos as $id ) {
			$coord = $this->coordenadas( $id );
			if ( ! $coord ) { continue; }
			$cat     = czuapi_primer_termino( $id, CZUAPI_Taxonomias::TAX_CATEGORIA );
			$out[]   = array(
				'id'        => (int) $id,
				'tipo'      => 'destino',
				'lat'       => $coord['lat'],
				'lng'       => $coord['lng'],
				'categoria' => $cat ? $cat['id'] : null,
			);
		}

		// Eventos con lugar propio o heredado de su destino.
		foreach ( CZUAPI_Eventos::instance()->markers() as $marker ) {
			$out[] = $marker;
		}

		return CZUAPI_Response::with_etag( $out, $request, 120 );
	}

	/* --------------------------------------------------------------------- */
	/*  Piezas                                                                */
	/* --------------------------------------------------------------------- */

	/**
	 * @return array|null { lat, lng }
	 */
	private function coordenadas( $id ) {
		$lat = get_post_meta( $id, '_promotur_lat', true );
		$lng = get_post_meta( $id, '_promotur_lng', true );
		if ( '' === $lat || '' === $lng || null === $lat || null === $lng ) {
			return null;
		}
		return array( 'lat' => (float) $lat, 'lng' => (float) $lng );
	}

	private function portada( $id ) {
		$att = (int) get_post_meta( $id, '_promotur_portada', true );
		if ( ! $att ) {
			$att = (int) get_post_thumbnail_id( $id );
		}
		return czuapi_imagen( $att, (string) get_post_meta( $id, '_promotur_credito_fotos', true ) );
	}

	private function galeria( $id ) {
		$raw = get_post_meta( $id, '_promotur_galeria', true );
		$ids = is_array( $raw ) ? $raw : array_filter( array_map( 'intval', explode( ',', (string) $raw ) ) );
		$out = array();
		foreach ( $ids as $att ) {
			$img = czuapi_imagen( (int) $att );
			if ( $img ) { $out[] = $img; }
		}
		return $out;
	}

	private function etiquetas( $id ) {
		$terms = get_the_terms( $id, 'promotur_etiqueta' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return array();
		}
		return array_map( 'czuapi_termino', $terms );
	}

	/**
	 * Rango de precio: entero 0–4, o null si el promotor no lo cargó.
	 *
	 * Existe además del texto libre `_promotur_costo`, no en su reemplazo: el
	 * número permite filtrar y pintar el indicador de la tarjeta, y el texto
	 * dice cosas que un número no ("entrada libre, estacionamiento 5.000 Gs").
	 * Es una decisión editorial del promotor, no algo calculable.
	 *
	 * @return int|null
	 */
	private function rango_precio( $id ) {
		$v = get_post_meta( $id, self::META_RANGO_PRECIO, true );
		if ( '' === $v || null === $v ) {
			return null;
		}
		return max( 0, min( 4, (int) $v ) );
	}

	/**
	 * Artículos relacionados, con lo mínimo para pintar la tarjeta y navegar.
	 *
	 * @return array[]
	 */
	private function articulos_relacionados( $id ) {
		$raw = get_post_meta( $id, self::META_ARTICULOS, true );
		$ids = is_array( $raw ) ? $raw : array_filter( array_map( 'intval', explode( ',', (string) $raw ) ) );
		if ( ! $ids ) {
			return array();
		}

		$posts = get_posts( array(
			'post_type'      => CZUAPI_Articulos::CPT,
			'post__in'       => $ids,
			'orderby'        => 'post__in',
			'posts_per_page' => 12,
			'post_status'    => 'publish',
		) );

		$out = array();
		foreach ( $posts as $p ) {
			$out[] = array(
				'id'      => (int) $p->ID,
				'titulo'  => get_the_title( $p ),
				'portada' => czuapi_imagen( (int) get_post_thumbnail_id( $p->ID ), '', 'medium' ),
			);
		}
		return $out;
	}

	/**
	 * "minLng,minLat,maxLng,maxLat" → array, o null si no parsea.
	 *
	 * @return array|null
	 */
	private function parse_bbox( $bbox ) {
		if ( ! $bbox ) {
			return null;
		}
		$p = array_map( 'trim', explode( ',', $bbox ) );
		if ( count( $p ) !== 4 ) {
			return null;
		}
		foreach ( $p as $v ) {
			if ( ! is_numeric( $v ) ) { return null; }
		}
		return array(
			'min_lng' => (float) $p[0],
			'min_lat' => (float) $p[1],
			'max_lng' => (float) $p[2],
			'max_lat' => (float) $p[3],
		);
	}
}
