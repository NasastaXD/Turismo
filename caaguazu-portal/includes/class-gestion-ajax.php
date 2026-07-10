<?php
/**
 * AJAX de gestión (Fase 3): tareas (crear/reclamar/completar) y nivel de confianza.
 * Autenticado y gateado por capability.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Gestion_Ajax {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		foreach ( array( 'create_tarea', 'claim_tarea', 'complete_tarea', 'set_nivel' ) as $a ) {
			add_action( 'wp_ajax_promotur_' . $a, array( $this, $a ) );
		}
	}

	private function guard( $cap ) {
		if ( ! check_ajax_referer( 'promotur', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sesión expirada. Recargá la página.', 'caaguazu-portal' ) ), 403 );
		}
		if ( $cap && ! caaguazu_account_can( 'promotor', $cap ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenés permiso.', 'caaguazu-portal' ) ), 403 );
		}
	}

	public function create_tarea() {
		$this->guard( 'promotur_assign_tasks' );
		$res = PROMOTUR_Tareas::create( array(
			'titulo'    => wp_unslash( $_POST['titulo'] ?? '' ),
			'detalle'   => wp_unslash( $_POST['detalle'] ?? '' ),
			'asignados' => (array) ( $_POST['asignados'] ?? array() ),
			'vence'     => wp_unslash( $_POST['vence'] ?? '' ),
			'tipo'      => wp_unslash( $_POST['tipo'] ?? 'tarea' ),
			'destino'   => (int) ( $_POST['destino'] ?? 0 ),
		) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( 'Tarea creada.', 'caaguazu-portal' ), 'reload' => true ) );
	}

	public function claim_tarea() {
		$this->guard( 'promotur_view_panel' );
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id || PROMOTUR_Tareas::CPT !== get_post_type( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Tarea inválida.', 'caaguazu-portal' ) ) );
		}
		PROMOTUR_Tareas::claim( $id, caaguazu_account_id() );
		wp_send_json_success( array( 'message' => __( 'Reclamaste esta tarea. ¡A producir!', 'caaguazu-portal' ), 'reload' => true ) );
	}

	public function complete_tarea() {
		$this->guard( 'promotur_view_panel' );
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id || PROMOTUR_Tareas::CPT !== get_post_type( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Tarea inválida.', 'caaguazu-portal' ) ) );
		}
		// Solo el asignado o quien puede asignar.
		if ( ! PROMOTUR_Tareas::is_assigned( $id, caaguazu_account_id() ) && ! caaguazu_account_can( 'promotor', 'promotur_assign_tasks' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenés permiso.', 'caaguazu-portal' ) ), 403 );
		}
		PROMOTUR_Tareas::complete( $id );
		wp_send_json_success( array( 'message' => __( 'Tarea completada. 🎉', 'caaguazu-portal' ), 'reload' => true ) );
	}

	public function set_nivel() {
		$this->guard( 'promotur_manage_team' );
		$user  = (int) ( $_POST['user_id'] ?? 0 );
		$level = sanitize_key( wp_unslash( $_POST['level'] ?? '' ) );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Usuario inválido.', 'caaguazu-portal' ) ) );
		}
		PROMOTUR_Stats::set_level( $user, $level );
		wp_send_json_success( array( 'message' => __( 'Nivel actualizado.', 'caaguazu-portal' ) ) );
	}
}
