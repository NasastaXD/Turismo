<?php
/**
 * Consultas/contacto del visitante (CPT privado, bandeja en el panel, derivable a un Mini)
 * y reportes de "info desactualizada" (comentarios sobre el destino).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Consultas {

	private static $instance = null;
	const CPT     = 'promotur_consulta';
	const RTYPE   = 'promotur_reporte';

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
			'labels'          => array( 'name' => __( 'Consultas', 'caaguazu-portal' ), 'singular_name' => __( 'Consulta', 'caaguazu-portal' ) ),
			'public'          => false,
			'show_ui'         => false,
			'show_in_rest'    => false,
			'supports'        => array( 'title' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	/* ----- Consultas ----- */

	public static function estados() {
		return array(
			'nueva'    => __( 'Nueva', 'caaguazu-portal' ),
			'asignada' => __( 'Asignada', 'caaguazu-portal' ),
			'resuelta' => __( 'Resuelta', 'caaguazu-portal' ),
		);
	}

	/**
	 * Crea una consulta.
	 *
	 * @param array $data { nombre, email, mensaje, destino }
	 * @return int|WP_Error
	 */
	public static function add( $data ) {
		$nombre  = sanitize_text_field( $data['nombre'] ?? '' );
		$email   = sanitize_email( $data['email'] ?? '' );
		$mensaje = sanitize_textarea_field( $data['mensaje'] ?? '' );
		$destino = (int) ( $data['destino'] ?? 0 );

		if ( '' === $nombre || ! is_email( $email ) || '' === $mensaje ) {
			return new WP_Error( 'incompleto', __( 'Completá nombre, email y mensaje.', 'caaguazu-portal' ) );
		}

		$title = $destino ? sprintf( __( 'Consulta sobre %s', 'caaguazu-portal' ), get_the_title( $destino ) ) : __( 'Consulta general', 'caaguazu-portal' );
		$id = wp_insert_post( array(
			'post_type'   => self::CPT,
			'post_status' => 'private',
			'post_title'  => $title,
			'post_content'=> $mensaje,
		) );
		if ( is_wp_error( $id ) ) { return $id; }

		update_post_meta( $id, '_promotur_nombre', $nombre );
		update_post_meta( $id, '_promotur_email', $email );
		update_post_meta( $id, '_promotur_destino', $destino );
		update_post_meta( $id, '_promotur_estado', 'nueva' );

		// Aviso por email al contacto del sitio.
		$to = apply_filters( 'promotur_contact_email', get_option( 'admin_email' ) );
		wp_mail(
			$to,
			sprintf( __( '[Portal] %s', 'caaguazu-portal' ), $title ),
			$mensaje . "\n\n" . sprintf( __( 'De: %1$s <%2$s>', 'caaguazu-portal' ), $nombre, $email ),
			array( 'Reply-To: ' . $nombre . ' <' . $email . '>' )
		);
		return (int) $id;
	}

	public static function all( $estado = '' ) {
		$args = array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $estado ) {
			$args['meta_query'] = array( array( 'key' => '_promotur_estado', 'value' => $estado ) );
		}
		return get_posts( $args );
	}

	public static function get_estado( $id ) {
		$e = get_post_meta( $id, '_promotur_estado', true );
		return $e ? $e : 'nueva';
	}

	public static function assign( $id, $user_id ) {
		update_post_meta( $id, '_promotur_asignado', (int) $user_id );
		update_post_meta( $id, '_promotur_estado', 'asignada' );
	}

	public static function resolve( $id ) {
		update_post_meta( $id, '_promotur_estado', 'resuelta' );
	}

	public static function count_open() {
		$q = new WP_Query( array(
			'post_type'      => self::CPT,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_promotur_estado', 'value' => array( 'nueva', 'asignada' ), 'compare' => 'IN' ) ),
		) );
		return (int) $q->found_posts;
	}

	/* ----- Reportes de info desactualizada ----- */

	/**
	 * Crea un reporte sobre un destino.
	 *
	 * @return int|WP_Error comment_id
	 */
	public static function report( $post_id, $content, $author = '', $email = '', $user_id = 0 ) {
		$post_id = (int) $post_id;
		$content = sanitize_textarea_field( $content );
		if ( ! $post_id || PROMOTUR_Destinos::CPT !== get_post_type( $post_id ) ) {
			return new WP_Error( 'bad_post', __( 'Destino inválido.', 'caaguazu-portal' ) );
		}
		if ( '' === $content ) {
			return new WP_Error( 'empty', __( 'Contanos qué está desactualizado.', 'caaguazu-portal' ) );
		}
		if ( $user_id ) {
			$u = get_userdata( $user_id );
			$author = $u ? $u->display_name : $author;
			$email  = $u ? $u->user_email : $email;
		}
		$cid = wp_insert_comment( array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => $content,
			'comment_type'         => self::RTYPE,
			'comment_author'       => sanitize_text_field( $author ),
			'comment_author_email' => sanitize_email( $email ),
			'user_id'              => (int) $user_id,
			'comment_approved'     => 0,
		) );
		return $cid ? (int) $cid : new WP_Error( 'fail', __( 'No se pudo enviar.', 'caaguazu-portal' ) );
	}

	/**
	 * Reportes abiertos (sin resolver).
	 *
	 * @return WP_Comment[]
	 */
	public static function pending_reports() {
		return get_comments( array(
			'type'    => self::RTYPE,
			'status'  => 'hold',
			'orderby' => 'comment_date',
			'order'   => 'DESC',
			'number'  => 100,
		) );
	}

	public static function count_open_reports() {
		return count( self::pending_reports() );
	}
}
