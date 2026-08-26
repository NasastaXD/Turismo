<?php
/**
 * CPT "Destino" (ficha turística) + taxonomías + metadata + single público.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Destinos {

	private static $instance = null;
	const CPT = 'promotur_destino';

	/**
	 * Meta donde vive el dueño REAL de la ficha: el ID de cuenta del sistema
	 * de cuentas universal (caaguazu-cuentas). `post_author` deja de servir
	 * para esto — en toda ficha creada desde el panel apunta al usuario de
	 * servicio (caaguazu_service_user_id()), porque WordPress exige un autor
	 * válido pero ninguna persona del panel es ya un usuario de WordPress.
	 */
	const OWNER_META = '_caaguazu_owner';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'template_include', array( $this, 'single_template' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_single' ) );
	}

	/**
	 * Carga el CSS del portal en la ficha pública (usa clases .promotur-*).
	 */
	public function enqueue_single() {
		if ( is_singular( self::CPT ) ) {
			wp_enqueue_style( 'promotur', promotur_asset( 'css/caaguazu-portal.css' ), array(), PROMOTUR_VERSION );
		}
	}

	/**
	 * Capabilities primitivas que genera el capability_type (para el admin).
	 *
	 * @return string[]
	 */
	public static function cpt_caps() {
		$s = 'promotur_destino';
		$p = 'promotur_destinos';
		return array(
			"edit_{$s}", "read_{$s}", "delete_{$s}",
			"edit_{$p}", "edit_others_{$p}", "publish_{$p}", "read_private_{$p}",
			"delete_{$p}", "delete_private_{$p}", "delete_published_{$p}", "delete_others_{$p}",
			"edit_private_{$p}", "edit_published_{$p}",
		);
	}

	public static function register_post_type() {
		$labels = array(
			'name'          => __( 'Destinos', 'caaguazu-portal' ),
			'singular_name' => __( 'Destino', 'caaguazu-portal' ),
			'add_new_item'  => __( 'Nuevo destino', 'caaguazu-portal' ),
			'edit_item'     => __( 'Editar destino', 'caaguazu-portal' ),
			'search_items'  => __( 'Buscar destinos', 'caaguazu-portal' ),
		);
		register_post_type( self::CPT, array(
			'labels'          => $labels,
			'public'          => true,
			'show_in_rest'    => true,
			'show_in_menu'    => true,
			'menu_icon'       => 'dashicons-palmtree',
			'menu_position'   => 26,
			'has_archive'     => true,
			'rewrite'         => array( 'slug' => 'destino', 'with_front' => false ),
			'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
			'capability_type' => array( 'promotur_destino', 'promotur_destinos' ),
			'map_meta_cap'    => true,
		) );
	}

	public static function register_taxonomies() {
		$common = array( 'hierarchical' => true, 'show_in_rest' => true, 'show_admin_column' => true );
		register_taxonomy( 'promotur_categoria', self::CPT, array_merge( $common, array(
			'labels' => array( 'name' => __( 'Categorías', 'caaguazu-portal' ), 'singular_name' => __( 'Categoría', 'caaguazu-portal' ) ),
			'rewrite' => array( 'slug' => 'categoria-destino' ),
		) ) );
		register_taxonomy( 'promotur_zona', self::CPT, array_merge( $common, array(
			'labels' => array( 'name' => __( 'Zonas', 'caaguazu-portal' ), 'singular_name' => __( 'Zona', 'caaguazu-portal' ) ),
			'rewrite' => array( 'slug' => 'zona' ),
		) ) );
		register_taxonomy( 'promotur_etiqueta', self::CPT, array(
			'hierarchical' => false, 'show_in_rest' => true,
			'labels' => array( 'name' => __( 'Etiquetas', 'caaguazu-portal' ), 'singular_name' => __( 'Etiqueta', 'caaguazu-portal' ) ),
			'rewrite' => array( 'slug' => 'etiqueta-destino' ),
		) );
	}

	/**
	 * Definición de campos de la ficha (data model + base del editor y del checklist).
	 * 'req' => true marca los obligatorios que componen el checklist de mínimos.
	 *
	 * @return array grupo => [ key => { label, type, req } ]
	 */
	public static function fields() {
		return array(
			'identidad' => array(
				'label'  => __( 'Identidad', 'caaguazu-portal' ),
				'fields' => array(
					'_promotur_gancho'        => array( 'label' => __( 'Gancho (una línea)', 'caaguazu-portal' ), 'type' => 'text', 'req' => true ),
					'_promotur_portada'       => array( 'label' => __( 'Foto de portada', 'caaguazu-portal' ), 'type' => 'image', 'req' => true ),
					'_promotur_credito_fotos' => array( 'label' => __( 'Crédito de fotos', 'caaguazu-portal' ), 'type' => 'text', 'req' => true ),
					'_promotur_video'         => array( 'label' => __( 'Video (URL, opcional)', 'caaguazu-portal' ), 'type' => 'url', 'req' => false ),
				),
			),
			'ubicacion' => array(
				'label'  => __( 'Ubicación y acceso', 'caaguazu-portal' ),
				'fields' => array(
					'_promotur_lat'         => array( 'label' => __( 'Latitud (pin)', 'caaguazu-portal' ), 'type' => 'coord', 'req' => true ),
					'_promotur_lng'         => array( 'label' => __( 'Longitud (pin)', 'caaguazu-portal' ), 'type' => 'coord', 'req' => true ),
					'_promotur_referencia'  => array( 'label' => __( 'Referencia (“a 3 km de…”)', 'caaguazu-portal' ), 'type' => 'text', 'req' => false ),
					'_promotur_como_llegar' => array( 'label' => __( 'Cómo llegar (auto / colectivo / a pie)', 'caaguazu-portal' ), 'type' => 'textarea', 'req' => true ),
					'_promotur_estado_camino' => array( 'label' => __( 'Estado del camino', 'caaguazu-portal' ), 'type' => 'select', 'req' => false, 'options' => array( 'asfalto' => 'Asfalto', 'ripio' => 'Ripio', 'tierra' => 'Tierra' ) ),
					'_promotur_accesibilidad' => array( 'label' => __( 'Accesibilidad', 'caaguazu-portal' ), 'type' => 'text', 'req' => false ),
				),
			),
			'practicos' => array(
				'label'  => __( 'Datos prácticos', 'caaguazu-portal' ),
				'fields' => array(
					'_promotur_horario'   => array( 'label' => __( 'Horario y mejor momento', 'caaguazu-portal' ), 'type' => 'text', 'req' => true ),
					'_promotur_temporada' => array( 'label' => __( 'Temporada ideal / cuándo evitar', 'caaguazu-portal' ), 'type' => 'text', 'req' => false ),
					'_promotur_costo'     => array( 'label' => __( 'Costo / entrada', 'caaguazu-portal' ), 'type' => 'text', 'req' => true ),
					// Rango de precio como número, ADEMÁS del texto libre de
					// arriba y no en su reemplazo: la app necesita filtrar por
					// precio y pintar el indicador de la tarjeta, y eso no se
					// puede hacer interpretando frases escritas por distintos
					// promotores. El texto sigue diciendo lo que un número no
					// ("entrada libre, estacionamiento 5.000 Gs").
					'_promotur_rango_precio' => array(
						'label'   => __( 'Rango de precio', 'caaguazu-portal' ),
						'type'    => 'select',
						'req'     => false,
						'options' => array(
							''  => __( 'Sin especificar', 'caaguazu-portal' ),
							'0' => __( 'Gratis', 'caaguazu-portal' ),
							'1' => __( '$ — Muy barato', 'caaguazu-portal' ),
							'2' => __( '$$ — Barato', 'caaguazu-portal' ),
							'3' => __( '$$$ — Intermedio', 'caaguazu-portal' ),
							'4' => __( '$$$$ — Caro', 'caaguazu-portal' ),
						),
					),
					'_promotur_servicios' => array( 'label' => __( 'Servicios (baños, comida, sombra…)', 'caaguazu-portal' ), 'type' => 'text', 'req' => false ),
					'_promotur_duracion'  => array( 'label' => __( 'Duración sugerida', 'caaguazu-portal' ), 'type' => 'text', 'req' => false ),
					'_promotur_contacto'  => array( 'label' => __( 'Contacto del lugar', 'caaguazu-portal' ), 'type' => 'text', 'req' => false ),
				),
			),
			'editorial' => array(
				'label'  => __( 'Editorial / fuentes', 'caaguazu-portal' ),
				'fields' => array(
					'_promotur_fuentes' => array( 'label' => __( 'Fuentes / referencias', 'caaguazu-portal' ), 'type' => 'textarea', 'req' => false ),
				),
			),
		);
	}

	/**
	 * Lista plana de todas las meta keys editables.
	 *
	 * @return array key => def
	 */
	public static function flat_fields() {
		$out = array();
		foreach ( self::fields() as $group ) {
			foreach ( $group['fields'] as $key => $def ) {
				$out[ $key ] = $def;
			}
		}
		return $out;
	}

	public function register_meta() {
		foreach ( self::flat_fields() as $key => $def ) {
			$type = in_array( $def['type'], array( 'coord' ), true ) ? 'number' : 'string';
			register_post_meta( self::CPT, $key, array(
				'type'          => $type,
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => function () { return caaguazu_account_can( 'promotor', 'promotur_edit_destino' ); },
			) );
		}
		// Meta de flujo editorial.
		foreach ( array( '_promotur_estado', '_promotur_revisor', '_promotur_galeria', '_promotur_destacado', '_promotur_verificado_en', self::OWNER_META ) as $key ) {
			register_post_meta( self::CPT, $key, array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => function () { return caaguazu_account_can( 'promotor', 'promotur_edit_destino' ); },
			) );
		}
	}

	/**
	 * ID de cuenta dueña real de una ficha.
	 *
	 * Prioriza el meta propio (`OWNER_META`, seteado en toda ficha creada
	 * desde el cutover). Si no está (ficha creada ANTES del cutover, cuando
	 * post_author todavía era un usuario de WordPress real), resuelve vía el
	 * vínculo que dejó la migración (wp_user_id de la cuenta) — así el
	 * contenido viejo sigue reconociendo a su dueño sin necesidad de tocar
	 * cada fila a mano.
	 *
	 * @param int $post_id
	 * @return int ID de cuenta, o 0 si no se puede resolver.
	 */
	public static function owner_account_id( $post_id ) {
		$meta = (int) get_post_meta( $post_id, self::OWNER_META, true );
		if ( $meta > 0 ) {
			return $meta;
		}
		if ( ! function_exists( 'caaguazu_account_for_wp_user' ) ) {
			return 0;
		}
		$post_author = (int) get_post_field( 'post_author', $post_id );
		if ( $post_author <= 0 ) {
			return 0;
		}
		$account = caaguazu_account_for_wp_user( $post_author );
		return $account ? (int) $account['id'] : 0;
	}

	/**
	 * Marca el dueño real (cuenta) de una ficha nueva.
	 *
	 * @param int $post_id
	 * @param int $account_id
	 */
	public static function set_owner( $post_id, $account_id ) {
		update_post_meta( $post_id, self::OWNER_META, (int) $account_id );
	}

	/**
	 * Single público del CPT desde el plugin (con override de theme).
	 */
	public function single_template( $template ) {
		if ( is_singular( self::CPT ) ) {
			$override = locate_template( array( 'promotur/public/single-destino.php' ) );
			if ( $override ) { return $override; }
			return PROMOTUR_DIR . 'templates/public/single-destino.php';
		}
		return $template;
	}
}
