<?php
/**
 * Custom Post Type "Local" y su metadata.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CGZ_CPT {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		// El dueño solo puede editar su propio local.
		add_filter( 'map_meta_cap', array( $this, 'restrict_owner_caps' ), 10, 4 );
	}

	/**
	 * Registro del CPT. Estático para reutilizarlo en la activación.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Locales', 'caaguazu-locales' ),
			'singular_name'      => __( 'Local', 'caaguazu-locales' ),
			'add_new'            => __( 'Añadir local', 'caaguazu-locales' ),
			'add_new_item'       => __( 'Añadir nuevo local', 'caaguazu-locales' ),
			'edit_item'          => __( 'Editar local', 'caaguazu-locales' ),
			'new_item'           => __( 'Nuevo local', 'caaguazu-locales' ),
			'view_item'          => __( 'Ver local', 'caaguazu-locales' ),
			'search_items'       => __( 'Buscar locales', 'caaguazu-locales' ),
			'not_found'          => __( 'No se encontraron locales', 'caaguazu-locales' ),
			'menu_name'          => __( 'Locales', 'caaguazu-locales' ),
		);

		register_post_type( 'cgz_local', array(
			'labels'              => $labels,
			'public'              => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-store',
			'menu_position'       => 25,
			'has_archive'         => true,
			'rewrite'             => array( 'slug' => 'local', 'with_front' => false ),
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
			'capability_type'     => array( 'cgz_local', 'cgz_locals' ),
			'map_meta_cap'        => true,
		) );
	}

	/**
	 * Registra la metadata para que aparezca en REST y se sanitice.
	 */
	public function register_meta() {
		$post_type = 'cgz_local';

		$strings = array(
			'_cgz_tipo', '_cgz_whatsapp', '_cgz_telefono',
			'_cgz_direccion', '_cgz_horario', '_cgz_booking_template',
		);
		foreach ( $strings as $key ) {
			register_post_meta( $post_type, $key, array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function( $allowed, $meta_key, $post_id ) {
					return cgz_user_can_manage_local( $post_id );
				},
			) );
		}

		foreach ( array( '_cgz_lat', '_cgz_lng' ) as $key ) {
			register_post_meta( $post_type, $key, array(
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => function( $v ) { return '' === $v ? '' : (float) $v; },
				'auth_callback'     => function( $allowed, $meta_key, $post_id ) {
					return cgz_user_can_manage_local( $post_id );
				},
			) );
		}

		register_post_meta( $post_type, '_cgz_owner', array(
			'type'          => 'integer',
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => function() { return current_user_can( 'manage_options' ); },
		) );
		// Las opciones de reserva se guardan como array serializado (no en REST).
	}

	/**
	 * Un dueño solo puede editar/borrar el local que se le asignó.
	 */
	public function restrict_owner_caps( $caps, $cap, $user_id, $args ) {
		$watched = array( 'edit_cgz_local', 'delete_cgz_local', 'read_cgz_local' );
		if ( ! in_array( $cap, $watched, true ) ) {
			return $caps;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return $caps;
		}
		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( ! $post_id ) {
			return $caps;
		}
		$owner = (int) get_post_meta( $post_id, '_cgz_owner', true );
		if ( $owner && $owner === (int) $user_id ) {
			// Mapea a una cap que el rol dueño sí tiene.
			return array( 'cgz_manage_own_local' );
		}
		return array( 'do_not_allow' );
	}
}
