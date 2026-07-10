<?php
/**
 * AJAX público (visitante): enviar reseña, consulta y reporte (con variantes nopriv).
 * Y acciones de moderación/bandeja (autenticadas, gateadas por capability).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Public_Ajax {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Visitante (priv + nopriv).
		foreach ( array( 'submit_resena', 'submit_consulta', 'submit_reporte' ) as $a ) {
			add_action( 'wp_ajax_promotur_' . $a, array( $this, $a ) );
			add_action( 'wp_ajax_nopriv_promotur_' . $a, array( $this, $a ) );
		}
		// Moderación / bandeja (solo autenticados).
		foreach ( array( 'moderate_resena', 'assign_consulta', 'resolve_consulta', 'resolve_reporte' ) as $a ) {
			add_action( 'wp_ajax_promotur_' . $a, array( $this, $a ) );
		}
	}

	private function check_pub() {
		if ( ! check_ajax_referer( 'promotur_pub', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sesión expirada. Recargá la página.', 'caaguazu-portal' ) ), 403 );
		}
	}

	private function check_mod() {
		if ( ! check_ajax_referer( 'promotur', 'nonce', false ) || ! caaguazu_account_can( 'promotor', 'promotur_moderate' ) ) {
			wp_send_json_error( array( 'message' => __( 'No autorizado.', 'caaguazu-portal' ) ), 403 );
		}
	}

	/* ----- Visitante ----- */

	public function submit_resena() {
		$this->check_pub();
		$res = PROMOTUR_Resenas::add( array(
			'post_id' => (int) ( $_POST['post_id'] ?? 0 ),
			'rating'  => (int) ( $_POST['rating'] ?? 0 ),
			'author'  => wp_unslash( $_POST['author'] ?? '' ),
			'email'   => wp_unslash( $_POST['email'] ?? '' ),
			'content' => wp_unslash( $_POST['content'] ?? '' ),
			'user_id' => 0, // visitante público — ver PROMOTUR_Resenas para cómo se guarda la identidad opcional.
		) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( '¡Gracias! Tu reseña se publicará tras una breve moderación.', 'caaguazu-portal' ) ) );
	}

	public function submit_consulta() {
		$this->check_pub();
		$res = PROMOTUR_Consultas::add( array(
			'nombre'  => wp_unslash( $_POST['nombre'] ?? '' ),
			'email'   => wp_unslash( $_POST['email'] ?? '' ),
			'mensaje' => wp_unslash( $_POST['mensaje'] ?? '' ),
			'destino' => (int) ( $_POST['destino'] ?? 0 ),
		) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( '¡Recibimos tu consulta! Te responderemos pronto.', 'caaguazu-portal' ) ) );
	}

	public function submit_reporte() {
		$this->check_pub();
		$res = PROMOTUR_Consultas::report(
			(int) ( $_POST['post_id'] ?? 0 ),
			wp_unslash( $_POST['content'] ?? '' ),
			wp_unslash( $_POST['author'] ?? '' ),
			wp_unslash( $_POST['email'] ?? '' ),
			0 // visitante público — ya no hay una sesión de WordPress que atribuirle.
		);
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( array( 'message' => __( '¡Gracias por avisar! Un Promotor lo va a revisar.', 'caaguazu-portal' ) ) );
	}

	/* ----- Moderación / bandeja ----- */

	public function moderate_resena() {
		$this->check_mod();
		$id     = (int) ( $_POST['comment_id'] ?? 0 );
		$action = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
		if ( 'approve' === $action ) {
			wp_set_comment_status( $id, 'approve' );
		} elseif ( 'trash' === $action ) {
			wp_trash_comment( $id );
		} else {
			wp_send_json_error( array( 'message' => __( 'Acción inválida.', 'caaguazu-portal' ) ) );
		}
		wp_send_json_success();
	}

	public function assign_consulta() {
		$this->check_mod();
		$id   = (int) ( $_POST['id'] ?? 0 );
		$user = (int) ( $_POST['user_id'] ?? 0 );
		if ( ! $id || ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Faltan datos.', 'caaguazu-portal' ) ) );
		}
		PROMOTUR_Consultas::assign( $id, $user );
		wp_send_json_success( array( 'message' => __( 'Consulta derivada.', 'caaguazu-portal' ) ) );
	}

	public function resolve_consulta() {
		$this->check_mod();
		PROMOTUR_Consultas::resolve( (int) ( $_POST['id'] ?? 0 ) );
		wp_send_json_success();
	}

	public function resolve_reporte() {
		$this->check_mod();
		wp_trash_comment( (int) ( $_POST['comment_id'] ?? 0 ) );
		wp_send_json_success();
	}
}
