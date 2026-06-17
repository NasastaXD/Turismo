<?php
/**
 * Centro de notificaciones. Las notificaciones se derivan en vivo del estado editorial;
 * "no leídas" = posteriores a la última marca de lectura (user meta).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Notifications {

	private static $instance = null;
	const READ_META = 'promotur_notifs_read_at';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_promotur_mark_read', array( $this, 'handle_mark_read' ) );
	}

	/**
	 * Lista de notificaciones del usuario actual.
	 *
	 * @return array[] { icon, title, when, url, time }
	 */
	public function get_items() {
		$items = array();
		$uid   = get_current_user_id();
		if ( ! $uid ) { return $items; }

		// Para revisores: fichas que esperan revisión.
		if ( current_user_can( 'promotur_review_content' ) ) {
			$pending = get_posts( array(
				'post_type'      => 'promotur_destino',
				'post_status'    => array( 'draft', 'pending' ),
				'posts_per_page' => 10,
				'meta_key'       => '_promotur_estado',
				'meta_value'     => 'enviado',
				'fields'         => 'ids',
			) );
			foreach ( $pending as $pid ) {
				$items[] = array(
					'icon'  => 'inbox',
					'title' => sprintf( __( '«%s» espera revisión', 'caaguazu-portal' ), get_the_title( $pid ) ),
					'time'  => (int) get_post_time( 'U', true, $pid ),
					'when'  => human_time_diff( (int) get_post_time( 'U', true, $pid ) ) . ' ' . __( 'atrás', 'caaguazu-portal' ),
					'url'   => promotur_url( 'panel/revision/' . $pid ),
				);
			}
		}

		// Para autores: sus fichas que necesitan cambios.
		$mine = get_posts( array(
			'post_type'      => 'promotur_destino',
			'post_status'    => array( 'draft', 'pending' ),
			'author'         => $uid,
			'posts_per_page' => 10,
			'meta_key'       => '_promotur_estado',
			'meta_value'     => 'necesita_cambios',
			'fields'         => 'ids',
		) );
		foreach ( $mine as $pid ) {
			$items[] = array(
				'icon'  => 'edit',
				'title' => sprintf( __( '«%s» necesita cambios', 'caaguazu-portal' ), get_the_title( $pid ) ),
				'time'  => (int) get_post_modified_time( 'U', true, $pid ),
				'when'  => human_time_diff( (int) get_post_modified_time( 'U', true, $pid ) ) . ' ' . __( 'atrás', 'caaguazu-portal' ),
				'url'   => promotur_url( 'panel/editor/' . $pid ),
			);
		}

		usort( $items, function ( $a, $b ) { return $b['time'] <=> $a['time']; } );
		return $items;
	}

	/**
	 * Cantidad de no leídas.
	 */
	public function get_unread_count() {
		$read_at = (int) get_user_meta( get_current_user_id(), self::READ_META, true );
		$count   = 0;
		foreach ( $this->get_items() as $item ) {
			if ( $item['time'] > $read_at ) { $count++; }
		}
		return $count;
	}

	/**
	 * Cantidad de fichas en la cola de revisión (para el badge del sidebar).
	 */
	public static function review_queue_count() {
		if ( ! current_user_can( 'promotur_review_content' ) ) { return 0; }
		$q = new WP_Query( array(
			'post_type'      => 'promotur_destino',
			'post_status'    => array( 'draft', 'pending' ),
			'meta_query'     => array( array( 'key' => '_promotur_estado', 'value' => array( 'enviado', 'en_revision' ), 'compare' => 'IN' ) ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		) );
		return (int) $q->found_posts;
	}

	/**
	 * admin-post: marcar todo como leído.
	 */
	public function handle_mark_read() {
		if ( ! is_user_logged_in() || ! check_admin_referer( 'promotur_mark_read' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'caaguazu-portal' ) );
		}
		update_user_meta( get_current_user_id(), self::READ_META, time() );
		promotur_flash( __( 'Notificaciones marcadas como leídas.', 'caaguazu-portal' ), 'success' );
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : promotur_url( 'panel' ) );
		exit;
	}
}
