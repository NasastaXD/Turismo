<?php
/**
 * Recorridos: prehechos (del panel) y de usuario (de la app).
 *
 * Mismo CPT y mismo esquema de paradas para los dos, distinguidos por
 * `_recorrido_tipo`. Los prehechos suman el bloque `historia` —curiosidades,
 * personas, por qué ese orden— que es contenido humano y no se genera.
 *
 * Costo total y compatibilidad de fechas NO se guardan: se calculan de las
 * paradas al pedirlos. Guardarlos sería duplicar un dato que cambia solo
 * cuando cambia una ficha, y quedaría desactualizado en silencio.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_Recorridos {

	private static $instance = null;

	const CPT = 'promotur_recorrido';

	const META_TIPO      = '_recorrido_tipo';      // prehecho | usuario
	const META_PARADAS   = '_recorrido_paradas';   // array ordenado
	const META_DURACION  = '_recorrido_duracion';
	const META_HISTORIA  = '_recorrido_historia';  // array
	const META_CUENTA    = '_recorrido_cuenta';    // dueño, para los de usuario

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
				'name'          => __( 'Recorridos', 'caaguazu-app-api' ),
				'singular_name' => __( 'Recorrido', 'caaguazu-app-api' ),
				'menu_name'     => __( 'Recorridos', 'caaguazu-app-api' ),
			),
			'public'       => true,
			'show_ui'      => true,
			'show_in_rest' => false,
			'menu_icon'    => 'dashicons-location-alt',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'has_archive'  => false,
			'rewrite'      => array( 'slug' => 'recorrido' ),
		) );
	}

	public function register_routes() {
		register_rest_route( CZUAPI_NS, '/recorridos', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'lista' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( CZUAPI_NS, '/recorridos/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'detalle' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( CZUAPI_NS, '/mis-recorridos', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'mis_lista' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'mis_crear' ),
				'permission_callback' => '__return_true',
			),
		) );

		register_rest_route( CZUAPI_NS, '/mis-recorridos/(?P<id>\d+)', array(
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'mis_actualizar' ),
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'mis_borrar' ),
				'permission_callback' => '__return_true',
			),
		) );
	}

	/* --------------------------------------------------------------------- */
	/*  Públicos (prehechos)                                                  */
	/* --------------------------------------------------------------------- */

	public function lista( $request ) {
		$posts = get_posts( array_merge( czuapi_args_publicado(), array(
			'post_type'      => self::CPT,
			'posts_per_page' => 50,
			'meta_query'     => array( array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'key'   => self::META_TIPO,
				'value' => 'prehecho',
			) ),
		) ) );

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = $this->formato( $post, false );
		}
		return new WP_REST_Response( $out, 200 );
	}

	public function detalle( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return CZUAPI_Response::no_encontrado();
		}

		$tipo = (string) get_post_meta( $post->ID, self::META_TIPO, true );

		// Un recorrido de usuario solo lo ve su dueño.
		if ( 'usuario' === $tipo ) {
			$cuenta = CZUAPI_Auth::instance()->current_account_id();
			if ( $cuenta <= 0 || (int) get_post_meta( $post->ID, self::META_CUENTA, true ) !== $cuenta ) {
				return CZUAPI_Response::no_encontrado();
			}
		} elseif ( 'publish' !== $post->post_status ) {
			return CZUAPI_Response::no_encontrado();
		}

		return new WP_REST_Response( $this->formato( $post, true ), 200 );
	}

	/* --------------------------------------------------------------------- */
	/*  De usuario                                                            */
	/* --------------------------------------------------------------------- */

	public function mis_lista( $request ) {
		$cuenta = CZUAPI_Auth::instance()->current_account_id();
		if ( $cuenta <= 0 ) {
			return CZUAPI_Response::no_autenticado();
		}

		$posts = get_posts( array(
			'post_type'      => self::CPT,
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'posts_per_page' => 100,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'AND',
				array( 'key' => self::META_TIPO, 'value' => 'usuario' ),
				array( 'key' => self::META_CUENTA, 'value' => $cuenta ),
			),
		) );

		$out = array();
		foreach ( $posts as $post ) {
			$out[] = $this->formato( $post, true );
		}
		return new WP_REST_Response( $out, 200 );
	}

	public function mis_crear( $request ) {
		$cuenta = CZUAPI_Auth::instance()->current_account_id();
		if ( $cuenta <= 0 ) {
			return CZUAPI_Response::no_autenticado();
		}

		$titulo  = sanitize_text_field( (string) $request->get_param( 'titulo' ) );
		$paradas = $this->sanear_paradas( $request->get_param( 'paradas' ) );

		if ( '' === $titulo ) {
			return CZUAPI_Response::error( 'titulo_requerido', __( 'Falta el título.', 'caaguazu-app-api' ), 422 );
		}
		if ( ! $paradas ) {
			return CZUAPI_Response::error( 'paradas_requeridas', __( 'El recorrido necesita al menos una parada válida.', 'caaguazu-app-api' ), 422 );
		}

		$id = wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_title'  => $titulo,
			'post_status' => 'private',
			'post_author' => function_exists( 'caaguazu_service_user_id' ) ? caaguazu_service_user_id() : 0,
		), true );

		if ( is_wp_error( $id ) ) {
			return CZUAPI_Response::error( 'no_se_pudo_crear', $id->get_error_message(), 500 );
		}

		update_post_meta( $id, self::META_TIPO, 'usuario' );
		update_post_meta( $id, self::META_CUENTA, $cuenta );
		update_post_meta( $id, self::META_PARADAS, $paradas );

		return new WP_REST_Response( $this->formato( get_post( $id ), true ), 201 );
	}

	public function mis_actualizar( $request ) {
		$post = $this->mi_recorrido( (int) $request['id'] );
		if ( is_object( $post ) && ! ( $post instanceof WP_Post ) ) {
			return $post; // respuesta de error
		}

		if ( null !== $request->get_param( 'titulo' ) ) {
			wp_update_post( array(
				'ID'         => $post->ID,
				'post_title' => sanitize_text_field( (string) $request->get_param( 'titulo' ) ),
			) );
		}
		if ( null !== $request->get_param( 'paradas' ) ) {
			$paradas = $this->sanear_paradas( $request->get_param( 'paradas' ) );
			if ( ! $paradas ) {
				return CZUAPI_Response::error( 'paradas_requeridas', __( 'El recorrido necesita al menos una parada válida.', 'caaguazu-app-api' ), 422 );
			}
			update_post_meta( $post->ID, self::META_PARADAS, $paradas );
		}

		return new WP_REST_Response( $this->formato( get_post( $post->ID ), true ), 200 );
	}

	public function mis_borrar( $request ) {
		$post = $this->mi_recorrido( (int) $request['id'] );
		if ( is_object( $post ) && ! ( $post instanceof WP_Post ) ) {
			return $post;
		}
		wp_delete_post( $post->ID, true );
		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Recupera un recorrido propio, o devuelve la respuesta de error.
	 *
	 * @return WP_Post|WP_REST_Response
	 */
	private function mi_recorrido( $id ) {
		$cuenta = CZUAPI_Auth::instance()->current_account_id();
		if ( $cuenta <= 0 ) {
			return CZUAPI_Response::no_autenticado();
		}
		$post = get_post( $id );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return CZUAPI_Response::no_encontrado();
		}
		if ( 'usuario' !== get_post_meta( $id, self::META_TIPO, true )
			|| (int) get_post_meta( $id, self::META_CUENTA, true ) !== $cuenta ) {
			return CZUAPI_Response::no_encontrado();
		}
		return $post;
	}

	/* --------------------------------------------------------------------- */

	/**
	 * @param WP_Post $post
	 * @param bool    $completo
	 * @return array
	 */
	private function formato( $post, $completo ) {
		$id      = $post->ID;
		$tipo    = (string) get_post_meta( $id, self::META_TIPO, true );
		$paradas = get_post_meta( $id, self::META_PARADAS, true );
		$paradas = is_array( $paradas ) ? $paradas : array();

		$out = array(
			'id'                => (int) $id,
			'tipo'              => $tipo ? $tipo : 'prehecho',
			'titulo'            => get_the_title( $post ),
			'resumen'           => get_the_excerpt( $post ),
			'portada'           => czuapi_imagen( (int) get_post_thumbnail_id( $id ) ),
			'duracion_estimada' => (string) get_post_meta( $id, self::META_DURACION, true ),
			'cantidad_paradas'  => count( $paradas ),
		);

		if ( ! $completo ) {
			return $out;
		}

		$out['paradas'] = $this->expandir_paradas( $paradas );

		// Calculados, no almacenados.
		$out['costo_total']  = $this->costo_total( $out['paradas'] );
		$out['fechas']       = $this->compatibilidad_fechas( $out['paradas'] );

		$out['historia'] = ( 'prehecho' === $out['tipo'] )
			? $this->historia( $id )
			: null;

		if ( 'prehecho' === $out['tipo'] ) {
			$out['articulo_html'] = apply_filters( 'the_content', $post->post_content );
		}

		return $out;
	}

	/**
	 * Paradas con los datos de cada sitio resueltos, para que el cliente no
	 * tenga que pedir una ficha por parada.
	 *
	 * @return array[]
	 */
	private function expandir_paradas( $paradas ) {
		$out = array();
		foreach ( $paradas as $p ) {
			$ref_id   = (int) $p['ref_id'];
			$ref_tipo = $p['ref_tipo'];
			$post     = get_post( $ref_id );

			if ( ! $post || 'publish' !== $post->post_status ) {
				// La parada quedó colgada (ficha despublicada). Se informa en
				// vez de omitirla en silencio: el usuario tiene que enterarse
				// de que su recorrido guardado perdió un lugar.
				$out[] = array(
					'orden'       => (int) $p['orden'],
					'ref_tipo'    => $ref_tipo,
					'ref_id'      => $ref_id,
					'disponible'  => false,
					'nota'        => (string) $p['nota'],
				);
				continue;
			}

			$es_evento = ( CZUAPI_Eventos::CPT === $post->post_type );

			$out[] = array(
				'orden'      => (int) $p['orden'],
				'ref_tipo'   => $ref_tipo,
				'ref_id'     => $ref_id,
				'disponible' => true,
				'titulo'     => get_the_title( $post ),
				'portada'    => czuapi_imagen( (int) get_post_thumbnail_id( $ref_id ), '', 'medium' ),
				'categoria'  => czuapi_primer_termino( $ref_id, CZUAPI_Taxonomias::TAX_CATEGORIA ),
				'coordenadas'=> $es_evento
					? $this->coord_evento( $ref_id )
					: $this->coord_destino( $ref_id ),
				'costo'      => $es_evento
					? (string) get_post_meta( $ref_id, CZUAPI_Eventos::META_COSTO, true )
					: (string) get_post_meta( $ref_id, '_promotur_costo', true ),
				'horario'    => $es_evento ? null : (string) get_post_meta( $ref_id, '_promotur_horario', true ),
				'inicio'     => $es_evento ? czuapi_fecha( (string) get_post_meta( $ref_id, CZUAPI_Eventos::META_INICIO, true ) ) : null,
				'fin'        => $es_evento ? czuapi_fecha( (string) get_post_meta( $ref_id, CZUAPI_Eventos::META_FIN, true ) ) : null,
				'nota'       => (string) $p['nota'],
			);
		}
		return $out;
	}

	private function coord_destino( $id ) {
		$lat = get_post_meta( $id, '_promotur_lat', true );
		$lng = get_post_meta( $id, '_promotur_lng', true );
		return ( '' === $lat || '' === $lng ) ? null : array( 'lat' => (float) $lat, 'lng' => (float) $lng );
	}

	private function coord_evento( $id ) {
		$ref = (int) get_post_meta( $id, CZUAPI_Eventos::META_LUGAR_ID, true );
		if ( $ref > 0 ) {
			return $this->coord_destino( $ref );
		}
		$lat = get_post_meta( $id, CZUAPI_Eventos::META_LAT, true );
		$lng = get_post_meta( $id, CZUAPI_Eventos::META_LNG, true );
		return ( '' === $lat || '' === $lng ) ? null : array( 'lat' => (float) $lat, 'lng' => (float) $lng );
	}

	/**
	 * Suma de costos. Como el costo es texto libre (decisión editorial del
	 * promotor), no se puede sumar: se devuelve el detalle y una bandera de si
	 * hay algo pago, que es lo que la pantalla necesita mostrar.
	 *
	 * @return array
	 */
	private function costo_total( $paradas ) {
		$detalle  = array();
		$hay_pago = false;
		foreach ( $paradas as $p ) {
			if ( empty( $p['disponible'] ) ) { continue; }
			$costo = isset( $p['costo'] ) ? trim( (string) $p['costo'] ) : '';
			if ( '' === $costo ) { continue; }
			$detalle[] = array( 'titulo' => $p['titulo'], 'costo' => $costo );
			if ( ! preg_match( '/^(gratis|libre|sin costo|0)$/i', $costo ) ) {
				$hay_pago = true;
			}
		}
		return array( 'hay_pago' => $hay_pago, 'detalle' => $detalle );
	}

	/**
	 * Compatibilidad de fechas entre las paradas con fecha (los eventos).
	 * Si hay dos eventos que no se solapan en ningún día, el recorrido no se
	 * puede hacer en una sola salida — eso es lo que la pantalla avisa.
	 *
	 * @return array
	 */
	private function compatibilidad_fechas( $paradas ) {
		$rangos = array();
		foreach ( $paradas as $p ) {
			if ( empty( $p['disponible'] ) || empty( $p['inicio'] ) ) { continue; }
			$rangos[] = array(
				'titulo' => $p['titulo'],
				'inicio' => strtotime( $p['inicio'] ),
				'fin'    => ! empty( $p['fin'] ) ? strtotime( $p['fin'] ) : strtotime( $p['inicio'] ),
			);
		}

		if ( count( $rangos ) < 2 ) {
			return array( 'compatible' => true, 'conflictos' => array() );
		}

		$conflictos = array();
		for ( $i = 0; $i < count( $rangos ); $i++ ) {
			for ( $j = $i + 1; $j < count( $rangos ); $j++ ) {
				$a = $rangos[ $i ];
				$b = $rangos[ $j ];
				if ( $a['fin'] < $b['inicio'] || $b['fin'] < $a['inicio'] ) {
					$conflictos[] = array( 'a' => $a['titulo'], 'b' => $b['titulo'] );
				}
			}
		}

		return array(
			'compatible' => empty( $conflictos ),
			'conflictos' => $conflictos,
		);
	}

	/**
	 * @return array
	 */
	private function historia( $id ) {
		$h = get_post_meta( $id, self::META_HISTORIA, true );
		$h = is_array( $h ) ? $h : array();
		return array(
			'introduccion'  => isset( $h['introduccion'] ) ? (string) $h['introduccion'] : '',
			'correlacion'   => isset( $h['correlacion'] ) ? (string) $h['correlacion'] : '',
			'personas'      => isset( $h['personas'] ) && is_array( $h['personas'] ) ? $h['personas'] : array(),
			'curiosidades'  => isset( $h['curiosidades'] ) && is_array( $h['curiosidades'] ) ? $h['curiosidades'] : array(),
			'articulos_ref' => isset( $h['articulos_ref'] ) && is_array( $h['articulos_ref'] ) ? array_map( 'intval', $h['articulos_ref'] ) : array(),
		);
	}

	/**
	 * Normaliza y valida las paradas que manda el cliente.
	 *
	 * @param mixed $raw
	 * @return array
	 */
	private function sanear_paradas( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out   = array();
		$orden = 1;
		foreach ( $raw as $p ) {
			if ( ! is_array( $p ) || empty( $p['ref_id'] ) ) {
				continue;
			}
			$ref_id = (int) $p['ref_id'];
			$post   = get_post( $ref_id );
			if ( ! $post ) {
				continue;
			}
			$tipo = ( CZUAPI_Eventos::CPT === $post->post_type ) ? 'evento' : 'destino';
			if ( ! in_array( $post->post_type, array( CZUAPI_Eventos::CPT, PROMOTUR_Destinos::CPT ), true ) ) {
				continue;
			}
			$out[] = array(
				'orden'    => $orden++,
				'ref_tipo' => $tipo,
				'ref_id'   => $ref_id,
				'nota'     => isset( $p['nota'] ) ? sanitize_text_field( (string) $p['nota'] ) : '',
			);
		}
		return $out;
	}
}
