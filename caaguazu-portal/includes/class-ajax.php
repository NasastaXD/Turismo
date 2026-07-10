<?php
/**
 * Handlers AJAX del flujo editorial: guardar/enviar ficha, asignar, aprobar/devolver, subir medios.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Ajax {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$map = array(
			'save_destino'   => 'save_destino',
			'submit_destino' => 'submit_destino',
			'assign_review'  => 'assign_review',
			'approve'        => 'approve',
			'return_changes' => 'return_changes',
			'upload_media'   => 'upload_media',
		);
		foreach ( $map as $action => $method ) {
			add_action( 'wp_ajax_promotur_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Guard: nonce + sesión (cuenta propia, o admin de WP vía bypass) + capability.
	 */
	private function guard( $cap ) {
		if ( ! check_ajax_referer( 'promotur', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Sesión expirada. Recargá la página.', 'caaguazu-portal' ) ), 403 );
		}
		if ( ! caaguazu_is_logged_in() && ! caaguazu_wp_admin_bypass() ) {
			wp_send_json_error( array( 'message' => __( 'Necesitás iniciar sesión.', 'caaguazu-portal' ) ), 401 );
		}
		if ( $cap && ! caaguazu_account_can( 'promotor', $cap ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenés permiso para esto.', 'caaguazu-portal' ) ), 403 );
		}
	}

	private function can_edit_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || PROMOTUR_Destinos::CPT !== $post->post_type ) { return false; }
		if ( caaguazu_account_can( 'promotor', 'promotur_review_content' ) ) { return true; }
		$owner  = PROMOTUR_Destinos::owner_account_id( $post_id );
		$mine   = caaguazu_account_id();
		// > 0 en ambos lados a propósito: dos IDs sin resolver (0 === 0)
		// nunca deben leerse como "es mío".
		return $owner > 0 && $mine > 0 && $owner === $mine;
	}

	/**
	 * Sanitiza un valor según el tipo del campo.
	 */
	private function sanitize_field( $type, $raw ) {
		switch ( $type ) {
			case 'coord':    return '' === trim( (string) $raw ) ? '' : (float) $raw;
			case 'url':      return esc_url_raw( wp_unslash( $raw ) );
			case 'textarea': return sanitize_textarea_field( wp_unslash( $raw ) );
			case 'image':    return (int) $raw;
			case 'select':   return sanitize_key( wp_unslash( $raw ) );
			default:         return sanitize_text_field( wp_unslash( $raw ) );
		}
	}

	/* ------------------------------------------------------------------ */
	public function save_destino() {
		$this->guard( 'promotur_edit_destino' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$title   = sanitize_text_field( wp_unslash( $_POST['titulo'] ?? '' ) );
		$content = wp_kses_post( wp_unslash( $_POST['descripcion'] ?? '' ) );

		if ( $post_id ) {
			if ( ! $this->can_edit_post( $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'No podés editar esta ficha.', 'caaguazu-portal' ) ), 403 );
			}
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $title, 'post_content' => $content ) );
		} else {
			// post_author: usuario de servicio (WordPress exige un autor
			// válido, pero ninguna persona del panel es ya un usuario de WP).
			// El dueño real queda en OWNER_META, resuelto por account_id.
			$post_id = wp_insert_post( array(
				'post_type'    => PROMOTUR_Destinos::CPT,
				'post_status'  => 'draft',
				'post_title'   => $title ? $title : __( '(sin título)', 'caaguazu-portal' ),
				'post_content' => $content,
				'post_author'  => caaguazu_service_user_id(),
			) );
			if ( is_wp_error( $post_id ) ) {
				wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
			}
			PROMOTUR_Destinos::set_owner( $post_id, caaguazu_account_id() );
			if ( ! get_post_meta( $post_id, '_promotur_estado', true ) ) {
				update_post_meta( $post_id, '_promotur_estado', 'borrador' );
			}
			if ( class_exists( 'PROMOTUR_Audit' ) ) {
				PROMOTUR_Audit::log( 'destino_created', array( 'entity_type' => 'destino', 'entity_id' => (int) $post_id, 'payload' => array( 'title' => $title ) ) );
			}
		}

		// Guardar metadatos del modelo.
		foreach ( PROMOTUR_Destinos::flat_fields() as $key => $def ) {
			if ( ! isset( $_POST['meta'][ $key ] ) ) { continue; }
			$value = $this->sanitize_field( $def['type'], $_POST['meta'][ $key ] );
			if ( '' === $value || null === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}

		// Taxonomías opcionales.
		if ( isset( $_POST['categoria'] ) ) {
			wp_set_object_terms( $post_id, array_map( 'intval', (array) $_POST['categoria'] ), 'promotur_categoria' );
		}

		// Confianza progresiva: editar una ficha PUBLICADA sin nivel suficiente la deja
		// en re-revisión (sin bajarla del aire); con nivel Jr+ la edición es directa.
		$message = __( 'Borrador guardado.', 'caaguazu-portal' );
		$owner   = PROMOTUR_Destinos::owner_account_id( $post_id );
		$mine    = caaguazu_account_id();
		if ( 'publicado' === PROMOTUR_Editorial::get_estado( $post_id )
			&& $owner > 0 && $mine > 0 && $owner === $mine
			&& ! PROMOTUR_Stats::can_edit_published( $mine ) ) {
			update_post_meta( $post_id, '_promotur_estado', 'en_revision' ); // sigue público (post_status intacto)
			update_post_meta( $post_id, '_promotur_reedit', 1 );
			$message = __( 'Guardado. Como editaste una ficha publicada, queda en re-revisión.', 'caaguazu-portal' );
		}

		$checklist = PROMOTUR_Editorial::checklist( $post_id );
		wp_send_json_success( array(
			'post_id'   => $post_id,
			'checklist' => $checklist,
			'complete'  => PROMOTUR_Editorial::is_complete( $post_id ),
			'message'   => $message,
		) );
	}

	/* ------------------------------------------------------------------ */
	public function submit_destino() {
		$this->guard( 'promotur_create_draft' );
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! $this->can_edit_post( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Ficha inválida.', 'caaguazu-portal' ) ), 403 );
		}
		if ( ! PROMOTUR_Editorial::is_complete( $post_id ) ) {
			wp_send_json_error( array(
				'message'   => __( 'Faltan datos obligatorios. Completá el checklist antes de enviar.', 'caaguazu-portal' ),
				'checklist' => PROMOTUR_Editorial::checklist( $post_id ),
			) );
		}
		// Confianza progresiva: nivel "De confianza" publica directo (con auditoría).
		if ( PROMOTUR_Stats::can_publish_directly( caaguazu_account_id() ) ) {
			PROMOTUR_Editorial::set_estado( $post_id, 'publicado' );
			PROMOTUR_Editorial::add_feedback( $post_id, caaguazu_account_id(), __( 'Publicación directa por nivel de confianza (auditoría posterior).', 'caaguazu-portal' ) );
			wp_send_json_success( array( 'message' => __( '¡Publicado! (nivel de confianza)', 'caaguazu-portal' ), 'redirect' => promotur_url( 'panel/mis-contenidos' ) ) );
		}
		PROMOTUR_Editorial::set_estado( $post_id, 'enviado' );
		wp_send_json_success( array( 'message' => __( '¡Enviado a revisión!', 'caaguazu-portal' ), 'redirect' => promotur_url( 'panel/mis-contenidos' ) ) );
	}

	/* ------------------------------------------------------------------ */
	public function assign_review() {
		$this->guard( 'promotur_review_content' );
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || PROMOTUR_Destinos::CPT !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Ficha inválida.', 'caaguazu-portal' ) ) );
		}
		PROMOTUR_Editorial::set_estado( $post_id, 'en_revision', caaguazu_account_id() );
		wp_send_json_success( array( 'message' => __( 'Te asignaste la revisión.', 'caaguazu-portal' ) ) );
	}

	/* ------------------------------------------------------------------ */
	public function approve() {
		$this->guard( 'promotur_publish_destino' );
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || PROMOTUR_Destinos::CPT !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Ficha inválida.', 'caaguazu-portal' ) ) );
		}
		$comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
		if ( $comment ) {
			PROMOTUR_Editorial::add_feedback( $post_id, caaguazu_account_id(), $comment );
		}
		PROMOTUR_Editorial::set_estado( $post_id, 'publicado' );
		wp_send_json_success( array( 'message' => __( 'Ficha aprobada y publicada.', 'caaguazu-portal' ), 'redirect' => promotur_url( 'panel/revision' ) ) );
	}

	/* ------------------------------------------------------------------ */
	public function return_changes() {
		$this->guard( 'promotur_review_content' );
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
		if ( ! $post_id || PROMOTUR_Destinos::CPT !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Ficha inválida.', 'caaguazu-portal' ) ) );
		}
		if ( '' === $comment ) {
			wp_send_json_error( array( 'message' => __( 'Escribí el feedback para el autor.', 'caaguazu-portal' ) ) );
		}
		PROMOTUR_Editorial::add_feedback( $post_id, caaguazu_account_id(), $comment );
		PROMOTUR_Editorial::set_estado( $post_id, 'necesita_cambios' );
		wp_send_json_success( array( 'message' => __( 'Devuelto al autor con feedback.', 'caaguazu-portal' ), 'redirect' => promotur_url( 'panel/revision' ) ) );
	}

	/* ------------------------------------------------------------------ */
	public function upload_media() {
		$this->guard( 'upload_files' );
		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No llegó ninguna imagen.', 'caaguazu-portal' ) ) );
		}
		$check = wp_check_filetype( $_FILES['file']['name'] );
		if ( ! in_array( $check['ext'], array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Solo se permiten imágenes.', 'caaguazu-portal' ) ) );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$id = media_handle_upload( 'file', 0 );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ) );
		}
		wp_send_json_success( array(
			'id'    => $id,
			'thumb' => wp_get_attachment_image_url( $id, 'medium' ),
		) );
	}
}
