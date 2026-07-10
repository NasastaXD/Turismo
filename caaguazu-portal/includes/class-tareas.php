<?php
/**
 * Tareas / asignaciones y tablero "lo que falta cubrir" (huecos reclamables).
 * CPT privado promotur_tarea.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Tareas {

	private static $instance = null;
	const CPT = 'promotur_tarea';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	public static function register_post_type() {
		register_post_type( self::CPT, array(
			'labels'          => array( 'name' => __( 'Tareas', 'caaguazu-portal' ), 'singular_name' => __( 'Tarea', 'caaguazu-portal' ) ),
			'public'          => false,
			'show_ui'         => false,
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	public static function estados() {
		return array(
			'pendiente'  => __( 'Pendiente', 'caaguazu-portal' ),
			'en_curso'   => __( 'En curso', 'caaguazu-portal' ),
			'completada' => __( 'Completada', 'caaguazu-portal' ),
		);
	}

	public static function get_estado( $id ) {
		$e = get_post_meta( $id, '_promotur_estado', true );
		return $e ? $e : 'pendiente';
	}

	/**
	 * Crea una tarea o hueco.
	 *
	 * @param array $data { titulo, detalle, asignados[], vence, tipo, destino }
	 * @return int|WP_Error
	 */
	public static function create( $data ) {
		$titulo = sanitize_text_field( $data['titulo'] ?? '' );
		if ( '' === $titulo ) {
			return new WP_Error( 'empty', __( 'La tarea necesita un título.', 'caaguazu-portal' ) );
		}
		// post_author: el usuario de servicio (ver caaguazu_service_user_id()) —
		// ninguna cuenta del panel es ya un usuario de WordPress. La lista de
		// asignados (_promotur_asignados) son IDs de cuenta y es lo único que
		// esta clase usa para resolver "de quién es esta tarea".
		$id = wp_insert_post( array(
			'post_type'    => self::CPT,
			'post_status'  => 'private',
			'post_title'   => $titulo,
			'post_content' => sanitize_textarea_field( $data['detalle'] ?? '' ),
			'post_author'  => function_exists( 'caaguazu_service_user_id' ) ? caaguazu_service_user_id() : get_current_user_id(),
		) );
		if ( is_wp_error( $id ) ) { return $id; }

		$asignados = array_map( 'intval', (array) ( $data['asignados'] ?? array() ) );
		update_post_meta( $id, '_promotur_asignados', $asignados );
		update_post_meta( $id, '_promotur_vence', sanitize_text_field( $data['vence'] ?? '' ) );
		update_post_meta( $id, '_promotur_tipo', in_array( ( $data['tipo'] ?? '' ), array( 'hueco', 'tarea' ), true ) ? $data['tipo'] : 'tarea' );
		update_post_meta( $id, '_promotur_destino', (int) ( $data['destino'] ?? 0 ) );
		update_post_meta( $id, '_promotur_estado', 'pendiente' );
		return (int) $id;
	}

	/** Un Mini reclama un hueco: se autoasigna y pasa a en curso. */
	public static function claim( $id, $user_id ) {
		$asig = (array) get_post_meta( $id, '_promotur_asignados', true );
		if ( ! in_array( (int) $user_id, array_map( 'intval', $asig ), true ) ) {
			$asig[] = (int) $user_id;
			update_post_meta( $id, '_promotur_asignados', $asig );
		}
		update_post_meta( $id, '_promotur_estado', 'en_curso' );
	}

	public static function complete( $id ) {
		update_post_meta( $id, '_promotur_estado', 'completada' );
	}

	public static function is_assigned( $id, $user_id ) {
		$asig = array_map( 'intval', (array) get_post_meta( $id, '_promotur_asignados', true ) );
		return in_array( (int) $user_id, $asig, true );
	}

	/**
	 * Tareas visibles para una cuenta: si gestiona, todas; si no, las suyas + huecos abiertos.
	 *
	 * @param int $account_id ID de cuenta (0 = sin cuenta propia; bypass de administrador de WP).
	 * @return WP_Post[]
	 */
	public static function visible_for( $account_id ) {
		$all = get_posts( array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		$can_assign = $account_id > 0
			? caaguazu_account_can( 'promotor', 'promotur_assign_tasks', $account_id )
			: ( function_exists( 'caaguazu_wp_admin_bypass' ) && caaguazu_wp_admin_bypass() );
		if ( $can_assign ) {
			return $all;
		}
		$out = array();
		foreach ( $all as $t ) {
			$tipo = get_post_meta( $t->ID, '_promotur_tipo', true );
			if ( self::is_assigned( $t->ID, $account_id ) || ( 'hueco' === $tipo && 'completada' !== self::get_estado( $t->ID ) ) ) {
				$out[] = $t;
			}
		}
		return $out;
	}

	/** Cantidad de tareas pendientes asignadas al usuario (badge). */
	public static function pending_count_for( $user_id ) {
		$n = 0;
		foreach ( self::visible_for( $user_id ) as $t ) {
			if ( 'completada' !== self::get_estado( $t->ID ) && self::is_assigned( $t->ID, $user_id ) ) { $n++; }
		}
		return $n;
	}
}
