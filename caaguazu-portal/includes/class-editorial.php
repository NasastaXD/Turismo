<?php
/**
 * Flujo editorial: estados, checklist de mínimos, asignación de revisor y feedback.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Editorial {

	private static $instance = null;
	const FEEDBACK_TYPE = 'promotur_feedback';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Mantener el feedback fuera del conteo de comentarios normales.
		add_filter( 'comments_clauses', array( $this, 'hide_feedback_from_public' ) );
	}

	/**
	 * Estados del flujo: slug → label.
	 */
	public static function estados() {
		return array(
			'borrador'        => __( 'Borrador', 'caaguazu-portal' ),
			'enviado'         => __( 'Enviado', 'caaguazu-portal' ),
			'en_revision'     => __( 'En revisión', 'caaguazu-portal' ),
			'necesita_cambios'=> __( 'Necesita cambios', 'caaguazu-portal' ),
			'aprobado'        => __( 'Aprobado', 'caaguazu-portal' ),
			'publicado'       => __( 'Publicado', 'caaguazu-portal' ),
			'despublicado'    => __( 'Despublicado', 'caaguazu-portal' ),
			'archivado'       => __( 'Archivado', 'caaguazu-portal' ),
		);
	}

	public static function estado_label( $slug ) {
		$e = self::estados();
		return isset( $e[ $slug ] ) ? $e[ $slug ] : ( $slug ? $slug : __( 'Borrador', 'caaguazu-portal' ) );
	}

	public static function get_estado( $post_id ) {
		$e = get_post_meta( $post_id, '_promotur_estado', true );
		return $e ? $e : 'borrador';
	}

	public static function set_estado( $post_id, $estado, $revisor = null ) {
		update_post_meta( $post_id, '_promotur_estado', $estado );
		if ( null !== $revisor ) {
			update_post_meta( $post_id, '_promotur_revisor', (int) $revisor );
		}
		// Visibilidad pública: solo "publicado" → publish.
		$status = ( 'publicado' === $estado ) ? 'publish' : 'draft';
		if ( get_post_status( $post_id ) !== $status ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ) );
		}
		if ( 'publicado' === $estado ) {
			update_post_meta( $post_id, '_promotur_verificado_en', current_time( 'mysql' ) );
		}
		// Auditoría del ciclo editorial (log de posts).
		if ( class_exists( 'PROMOTUR_Audit' ) && in_array( $estado, array( 'enviado', 'publicado', 'necesita_cambios', 'aprobado' ), true ) ) {
			PROMOTUR_Audit::log( 'destino_' . $estado, array(
				'entity_type' => 'destino',
				'entity_id'   => (int) $post_id,
				'payload'     => array( 'title' => get_the_title( $post_id ) ),
			) );
		}
	}

	/**
	 * Checklist de mínimos del destino: cada ítem { key, label, done }.
	 *
	 * @param int $post_id
	 * @return array[]
	 */
	public static function checklist( $post_id ) {
		$items = array();

		// Título (campo nativo).
		$items[] = array(
			'key'   => 'titulo',
			'label' => __( 'Nombre del destino', 'caaguazu-portal' ),
			'done'  => '' !== trim( (string) get_the_title( $post_id ) ),
		);
		// Descripción (post_content).
		$content = get_post_field( 'post_content', $post_id );
		$items[] = array(
			'key'   => 'descripcion',
			'label' => __( 'Descripción', 'caaguazu-portal' ),
			'done'  => '' !== trim( wp_strip_all_tags( (string) $content ) ),
		);

		// Campos obligatorios del modelo.
		foreach ( PROMOTUR_Destinos::flat_fields() as $key => $def ) {
			if ( empty( $def['req'] ) ) { continue; }
			$val  = get_post_meta( $post_id, $key, true );
			$done = ( '' !== trim( (string) $val ) );
			$items[] = array( 'key' => $key, 'label' => $def['label'], 'done' => $done );
		}

		return $items;
	}

	/**
	 * ¿Cumple todos los mínimos?
	 */
	public static function is_complete( $post_id ) {
		foreach ( self::checklist( $post_id ) as $item ) {
			if ( ! $item['done'] ) { return false; }
		}
		return true;
	}

	/**
	 * Comentarios rápidos predefinidos para el feedback.
	 */
	public static function quick_feedback() {
		return array(
			__( 'Faltan fuentes / referencias.', 'caaguazu-portal' ),
			__( 'Mejorá las fotos (luz, encuadre, portada).', 'caaguazu-portal' ),
			__( 'Verificá horarios y costos.', 'caaguazu-portal' ),
			__( 'Revisá ortografía y redacción.', 'caaguazu-portal' ),
			__( 'Precisá el “cómo llegar”.', 'caaguazu-portal' ),
		);
	}

	/**
	 * Agrega un comentario de feedback al hilo de revisión.
	 *
	 * $account_id es un ID de cuenta del sistema de cuentas universal
	 * (caaguazu-cuentas), no un usuario de WordPress — por eso el comentario
	 * se guarda con `user_id => 0` (WordPress no tiene ningún usuario al que
	 * asociarlo) y el nombre/email se toman directo de la cuenta. 0 también
	 * cubre al bypass de administrador de WP (sin cuenta propia).
	 *
	 * @param int    $post_id
	 * @param int    $account_id
	 * @param string $text
	 * @return int
	 */
	public static function add_feedback( $post_id, $account_id, $text ) {
		$text = sanitize_textarea_field( $text );
		if ( '' === $text ) { return 0; }

		$name  = '';
		$email = '';
		if ( $account_id > 0 && class_exists( 'Caaguazu_Cuentas_Accounts' ) ) {
			$account = Caaguazu_Cuentas_Accounts::get( $account_id );
			if ( $account ) {
				$name  = $account['display_name'] ? $account['display_name'] : $account['email'];
				$email = $account['email'];
			}
		}
		if ( '' === $name && function_exists( 'wp_get_current_user' ) ) {
			// Bypass de administrador de WP: sin cuenta propia, se usa su identidad de WP.
			$wp_user = wp_get_current_user();
			$name    = $wp_user->display_name ? $wp_user->display_name : $wp_user->user_login;
			$email   = $wp_user->user_email;
		}

		return wp_insert_comment( array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => $text,
			'comment_type'         => self::FEEDBACK_TYPE,
			'user_id'              => 0,
			'comment_author'       => $name,
			'comment_author_email' => $email,
			'comment_approved'     => 1,
		) );
	}

	/**
	 * Hilo de feedback de un destino.
	 *
	 * @return WP_Comment[]
	 */
	public static function get_feedback( $post_id ) {
		return get_comments( array(
			'post_id' => $post_id,
			'type'    => self::FEEDBACK_TYPE,
			'orderby' => 'comment_date',
			'order'   => 'ASC',
		) );
	}

	/**
	 * Excluye el feedback de las queries públicas de comentarios.
	 */
	public function hide_feedback_from_public( $clauses ) {
		if ( is_admin() ) { return $clauses; }
		global $wpdb;
		$clauses['where'] .= $wpdb->prepare( " AND {$wpdb->comments}.comment_type != %s", self::FEEDBACK_TYPE );
		return $clauses;
	}

	/**
	 * Color/clase de pill por estado (para la UI).
	 */
	public static function estado_class( $slug ) {
		$map = array(
			'borrador'         => 'is-draft',
			'enviado'          => 'is-sent',
			'en_revision'      => 'is-review',
			'necesita_cambios' => 'is-changes',
			'aprobado'         => 'is-approved',
			'publicado'        => 'is-published',
			'despublicado'     => 'is-muted',
			'archivado'        => 'is-muted',
		);
		return isset( $map[ $slug ] ) ? $map[ $slug ] : 'is-draft';
	}
}
