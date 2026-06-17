<?php
/**
 * Handlers AJAX: reseñas, fotos, respuestas, votos, borrado y guardado del panel de dueño.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CGZ_Ajax {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$actions = array(
			'submit_review'  => 'submit_review',
			'upload_photo'   => 'upload_photo',
			'submit_reply'   => 'submit_reply',
			'vote_review'    => 'vote_review',
			'delete_review'  => 'delete_review',
			'delete_reply'   => 'delete_reply',
			'save_local'     => 'save_local',
		);
		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_cgz_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Verifica nonce + login. Termina con error JSON si falla.
	 */
	private function guard( $cap = 'read' ) {
		if ( ! check_ajax_referer( 'cgz_loc', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sesión expirada. Recargá la página.', 'caaguazu-locales' ) ), 403 );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Necesitás iniciar sesión.', 'caaguazu-locales' ) ), 401 );
		}
		if ( $cap && ! current_user_can( $cap ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenés permiso para esto.', 'caaguazu-locales' ) ), 403 );
		}
	}

	/* ------------------------------------------------------------------ */
	public function submit_review() {
		$this->guard( 'cgz_write_review' );

		$result = CGZ_Reviews::add_review( array(
			'local_id' => (int) ( $_POST['local_id'] ?? 0 ),
			'user_id'  => get_current_user_id(),
			'rating'   => (int) ( $_POST['rating'] ?? 0 ),
			'title'    => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'content'  => sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) ),
			'photos'   => array_filter( array_map( 'absint', explode( ',', (string) ( $_POST['photos'] ?? '' ) ) ) ),
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$review = CGZ_Reviews::get_review( $result );
		wp_send_json_success( array(
			'html'    => CGZ_Shortcodes::review_card( $review, get_current_user_id() ),
			'summary' => CGZ_Reviews::get_summary( $review['local_id'] ),
			'message' => __( '¡Gracias por tu reseña!', 'caaguazu-locales' ),
		) );
	}

	/* ------------------------------------------------------------------ */
	public function upload_photo() {
		$this->guard( 'cgz_write_review' );

		if ( empty( $_FILES['photo'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No llegó ninguna imagen.', 'caaguazu-locales' ) ) );
		}

		// Validación de tipo.
		$check = wp_check_filetype( $_FILES['photo']['name'] );
		if ( ! preg_match( '/^image\//', (string) ( $_FILES['photo']['type'] ?? '' ) ) || ! in_array( $check['ext'], array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Solo se permiten imágenes.', 'caaguazu-locales' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'photo', 0 );
		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		// Marca de origen para moderación.
		update_post_meta( $attachment_id, '_cgz_review_photo', 1 );

		wp_send_json_success( array(
			'id'    => $attachment_id,
			'thumb' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
		) );
	}

	/* ------------------------------------------------------------------ */
	public function submit_reply() {
		$this->guard();

		$review_id = (int) ( $_POST['review_id'] ?? 0 );
		$content   = wp_unslash( $_POST['content'] ?? '' );

		$result = CGZ_Reviews::add_reply( $review_id, get_current_user_id(), $content );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$replies = CGZ_Reviews::get_replies( $review_id );
		$new     = end( $replies );
		wp_send_json_success( array( 'html' => CGZ_Shortcodes::reply_card( $new ) ) );
	}

	/* ------------------------------------------------------------------ */
	public function vote_review() {
		$this->guard();

		$review_id = (int) ( $_POST['review_id'] ?? 0 );
		$vote      = (int) ( $_POST['vote'] ?? 0 );

		// Toggle: si ya tenía el mismo voto, lo quita.
		$current = CGZ_Reviews::get_user_vote( $review_id, get_current_user_id() );
		if ( $current === $vote ) {
			$vote = 0;
		}

		$score = CGZ_Reviews::vote( $review_id, get_current_user_id(), $vote );
		wp_send_json_success( array( 'score' => $score, 'vote' => $vote ) );
	}

	/* ------------------------------------------------------------------ */
	public function delete_review() {
		$this->guard();

		$review_id = (int) ( $_POST['review_id'] ?? 0 );
		$review    = CGZ_Reviews::get_review( $review_id );
		if ( ! $review ) {
			wp_send_json_error( array( 'message' => __( 'Reseña no encontrada.', 'caaguazu-locales' ) ) );
		}
		$is_author = (int) $review['user_id'] === get_current_user_id();
		if ( ! $is_author && ! current_user_can( 'cgz_moderate_reviews' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenés permiso.', 'caaguazu-locales' ) ), 403 );
		}

		CGZ_Reviews::delete_review( $review_id );
		wp_send_json_success( array( 'summary' => CGZ_Reviews::get_summary( $review['local_id'] ) ) );
	}

	/* ------------------------------------------------------------------ */
	public function delete_reply() {
		$this->guard();

		$reply_id = (int) ( $_POST['reply_id'] ?? 0 );
		$reply    = CGZ_Reviews::get_reply( $reply_id );
		if ( ! $reply ) {
			wp_send_json_error( array( 'message' => __( 'No encontrado.', 'caaguazu-locales' ) ) );
		}
		if ( (int) $reply->user_id !== get_current_user_id() && ! current_user_can( 'cgz_moderate_reviews' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenés permiso.', 'caaguazu-locales' ) ), 403 );
		}
		CGZ_Reviews::delete_reply( $reply_id );
		wp_send_json_success();
	}

	/* ------------------------------------------------------------------ */
	/**
	 * Guarda los cambios del panel de dueño.
	 */
	public function save_local() {
		$this->guard();

		$local_id = (int) ( $_POST['local_id'] ?? 0 );
		if ( ! cgz_user_can_manage_local( $local_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No podés editar este local.', 'caaguazu-locales' ) ), 403 );
		}

		// Meta de texto.
		update_post_meta( $local_id, '_cgz_tipo', sanitize_key( wp_unslash( $_POST['tipo'] ?? '' ) ) );
		update_post_meta( $local_id, '_cgz_whatsapp', cgz_sanitize_phone( wp_unslash( $_POST['whatsapp'] ?? '' ) ) );
		update_post_meta( $local_id, '_cgz_telefono', sanitize_text_field( wp_unslash( $_POST['telefono'] ?? '' ) ) );
		update_post_meta( $local_id, '_cgz_horario', sanitize_text_field( wp_unslash( $_POST['horario'] ?? '' ) ) );
		update_post_meta( $local_id, '_cgz_direccion', sanitize_text_field( wp_unslash( $_POST['direccion'] ?? '' ) ) );

		// Coordenadas.
		$lat = wp_unslash( $_POST['lat'] ?? '' );
		$lng = wp_unslash( $_POST['lng'] ?? '' );
		if ( '' === trim( (string) $lat ) || '' === trim( (string) $lng ) ) {
			delete_post_meta( $local_id, '_cgz_lat' );
			delete_post_meta( $local_id, '_cgz_lng' );
		} else {
			update_post_meta( $local_id, '_cgz_lat', (float) $lat );
			update_post_meta( $local_id, '_cgz_lng', (float) $lng );
		}

		// Opciones de reserva (parseo del textarea: "Etiqueta | Mensaje" por línea).
		$raw_options = wp_unslash( $_POST['booking_options'] ?? '' );
		update_post_meta( $local_id, '_cgz_booking_opciones', cgz_parse_booking_options( $raw_options ) );

		// Descripción => post_content.
		$content = wp_kses_post( wp_unslash( $_POST['descripcion'] ?? '' ) );
		wp_update_post( array( 'ID' => $local_id, 'post_content' => $content ) );

		wp_send_json_success( array( 'message' => __( 'Cambios guardados.', 'caaguazu-locales' ) ) );
	}
}

/**
 * Parsea el textarea de opciones de reserva del panel de dueño.
 *
 * @param string $raw
 * @return array[] { label, message }
 */
function cgz_parse_booking_options( $raw ) {
	$out   = array();
	$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line ) { continue; }
		$parts   = explode( '|', $line, 2 );
		$label   = sanitize_text_field( trim( $parts[0] ) );
		$message = isset( $parts[1] ) ? sanitize_textarea_field( str_replace( '\\n', "\n", trim( $parts[1] ) ) ) : '';
		if ( '' === $label ) { continue; }
		$out[] = array( 'label' => $label, 'message' => $message );
	}
	return $out;
}
