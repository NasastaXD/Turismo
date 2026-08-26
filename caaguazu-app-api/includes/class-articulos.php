<?php
/**
 * CPT Artículo + endpoints.
 *
 * CPT propio y no las entradas nativas de WordPress: las entradas del portal
 * institucional (Noticias, Agenda) viven en otro plugin y en otro repositorio,
 * y la web va a rehacerse. Un CPT propio deja los artículos de la app fuera de
 * ese trabajo.
 *
 * El autor que se publica NO es post_author — ver czuapi_autor() en
 * helpers.php.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_Articulos {

	private static $instance = null;

	const CPT = 'promotur_articulo';

	const META_PIE_PORTADA = '_articulo_pie_portada';
	const META_RELACIONADOS = '_articulo_relacionados';

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
				'name'          => __( 'Artículos', 'caaguazu-app-api' ),
				'singular_name' => __( 'Artículo', 'caaguazu-app-api' ),
				'menu_name'     => __( 'Artículos (app)', 'caaguazu-app-api' ),
			),
			'public'       => true,
			'show_ui'      => true,
			'show_in_rest' => false,
			'menu_icon'    => 'dashicons-media-document',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'articulo' ),
			'taxonomies'   => array( CZUAPI_Taxonomias::TAX_CATEGORIA ),
		) );

		register_post_meta( self::CPT, self::META_PIE_PORTADA, array(
			'type' => 'string', 'single' => true, 'show_in_rest' => false,
		) );
	}

	public function register_routes() {
		register_rest_route( CZUAPI_NS, '/articulos', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'lista' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'categoria'  => array( 'type' => 'integer' ),
				'pagina'     => array( 'type' => 'integer', 'default' => 1 ),
				'por_pagina' => array( 'type' => 'integer', 'default' => 20 ),
			),
		) );

		register_rest_route( CZUAPI_NS, '/articulos/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'detalle' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function lista( $request ) {
		$pagina     = max( 1, (int) $request->get_param( 'pagina' ) );
		$por_pagina = min( 100, max( 1, (int) $request->get_param( 'por_pagina' ) ) );

		$args = array_merge( czuapi_args_publicado(), array(
			'post_type'      => self::CPT,
			'posts_per_page' => $por_pagina,
			'paged'          => $pagina,
			'orderby'        => 'date',
			'order'          => 'DESC',
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
			$items[] = $this->resumen( $post );
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

		$out = $this->resumen( $post );

		$out['cuerpo_html'] = apply_filters( 'the_content', $post->post_content );
		$out['pie_portada'] = (string) get_post_meta( $post->ID, self::META_PIE_PORTADA, true );
		$out['categoria']   = czuapi_primer_termino( $post->ID, CZUAPI_Taxonomias::TAX_CATEGORIA );
		$out['relacionados'] = $this->relacionados( $post->ID );
		$out['actualizado'] = czuapi_fecha( $post->post_modified_gmt );

		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * @param WP_Post $post
	 * @return array
	 */
	private function resumen( $post ) {
		return array(
			'id'        => (int) $post->ID,
			'titulo'    => get_the_title( $post ),
			'bajada'    => get_the_excerpt( $post ),
			'portada'   => czuapi_imagen(
				(int) get_post_thumbnail_id( $post->ID ),
				(string) get_post_meta( $post->ID, self::META_PIE_PORTADA, true )
			),
			'autor'     => czuapi_autor( $post->ID ),
			'publicado' => czuapi_fecha( $post->post_date_gmt ),
		);
	}

	/**
	 * Relacionados explícitos si los hay; si no, los más recientes de la misma
	 * categoría. Nunca se devuelve a sí mismo.
	 *
	 * @return array[]
	 */
	private function relacionados( $id ) {
		$raw = get_post_meta( $id, self::META_RELACIONADOS, true );
		$ids = is_array( $raw ) ? $raw : array_filter( array_map( 'intval', explode( ',', (string) $raw ) ) );

		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 6,
			'post__not_in'   => array( (int) $id ),
		);

		if ( $ids ) {
			$args['post__in'] = $ids;
			$args['orderby']  = 'post__in';
		} else {
			$cat = czuapi_primer_termino( $id, CZUAPI_Taxonomias::TAX_CATEGORIA );
			if ( ! $cat ) {
				return array();
			}
			$args['tax_query'] = array( array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'taxonomy' => CZUAPI_Taxonomias::TAX_CATEGORIA,
				'field'    => 'term_id',
				'terms'    => $cat['id'],
			) );
		}

		$out = array();
		foreach ( get_posts( $args ) as $p ) {
			$out[] = array(
				'id'      => (int) $p->ID,
				'titulo'  => get_the_title( $p ),
				'portada' => czuapi_imagen( (int) get_post_thumbnail_id( $p->ID ), '', 'medium' ),
			);
		}
		return $out;
	}
}
