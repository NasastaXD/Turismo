<?php
/**
 * Reseñas: ratings, fotos, comentarios/respuestas y votos útiles.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CGZ_Reviews {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Al borrar un local, limpiamos sus reseñas.
		add_action( 'before_delete_post', array( $this, 'cleanup_local_reviews' ) );
	}

	/* ----- Tablas ----- */
	public static function t_reviews() { global $wpdb; return $wpdb->prefix . 'cgz_reviews'; }
	public static function t_replies() { global $wpdb; return $wpdb->prefix . 'cgz_review_replies'; }
	public static function t_votes()   { global $wpdb; return $wpdb->prefix . 'cgz_review_votes'; }
	public static function t_photos()  { global $wpdb; return $wpdb->prefix . 'cgz_review_photos'; }

	/**
	 * Crea una reseña.
	 *
	 * @param array $data { local_id, user_id, rating, title, content, photos[] }
	 * @return int|WP_Error review_id
	 */
	public static function add_review( $data ) {
		global $wpdb;

		$local_id = (int) ( $data['local_id'] ?? 0 );
		$user_id  = (int) ( $data['user_id'] ?? 0 );
		$rating   = max( 1, min( 5, (int) ( $data['rating'] ?? 0 ) ) );
		$title    = sanitize_text_field( $data['title'] ?? '' );
		$content  = sanitize_textarea_field( $data['content'] ?? '' );
		$photos   = array_map( 'absint', (array) ( $data['photos'] ?? array() ) );

		if ( ! $local_id || 'cgz_local' !== get_post_type( $local_id ) ) {
			return new WP_Error( 'bad_local', __( 'Local inválido.', 'caaguazu-locales' ) );
		}
		if ( ! $user_id ) {
			return new WP_Error( 'no_user', __( 'Necesitás una cuenta para reseñar.', 'caaguazu-locales' ) );
		}
		if ( '' === $content ) {
			return new WP_Error( 'empty', __( 'Escribí tu reseña.', 'caaguazu-locales' ) );
		}

		// Un usuario, una reseña por local (puede editarla).
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . self::t_reviews() . " WHERE local_id = %d AND user_id = %d",
			$local_id, $user_id
		) );

		$now = current_time( 'mysql' );

		if ( $existing ) {
			$wpdb->update(
				self::t_reviews(),
				array( 'rating' => $rating, 'title' => $title, 'content' => $content, 'updated_at' => $now ),
				array( 'id' => $existing ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			$review_id = (int) $existing;
		} else {
			$wpdb->insert(
				self::t_reviews(),
				array(
					'local_id'   => $local_id,
					'user_id'    => $user_id,
					'rating'     => $rating,
					'title'      => $title,
					'content'    => $content,
					'status'     => 'publish',
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);
			$review_id = (int) $wpdb->insert_id;
		}

		if ( $review_id ) {
			self::set_photos( $review_id, $photos );
		}

		return $review_id;
	}

	/**
	 * Asocia fotos (attachment IDs) a una reseña, reemplazando las previas.
	 */
	public static function set_photos( $review_id, $attachment_ids ) {
		global $wpdb;
		$wpdb->delete( self::t_photos(), array( 'review_id' => $review_id ), array( '%d' ) );
		$order = 0;
		foreach ( array_slice( $attachment_ids, 0, 6 ) as $att_id ) {
			$att_id = (int) $att_id;
			if ( ! $att_id || 'attachment' !== get_post_type( $att_id ) ) { continue; }
			$wpdb->insert(
				self::t_photos(),
				array( 'review_id' => $review_id, 'attachment_id' => $att_id, 'sort_order' => $order++ ),
				array( '%d', '%d', '%d' )
			);
		}
	}

	/**
	 * Fotos de una reseña como [{id, url, thumb}].
	 */
	public static function get_photos( $review_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT attachment_id FROM " . self::t_photos() . " WHERE review_id = %d ORDER BY sort_order ASC",
			$review_id
		) );
		$out = array();
		foreach ( $rows as $r ) {
			$full  = wp_get_attachment_image_url( $r->attachment_id, 'large' );
			$thumb = wp_get_attachment_image_url( $r->attachment_id, 'medium' );
			if ( $full ) {
				$out[] = array( 'id' => (int) $r->attachment_id, 'url' => $full, 'thumb' => $thumb ? $thumb : $full );
			}
		}
		return $out;
	}

	/**
	 * Lista reseñas publicadas de un local, con datos enriquecidos.
	 *
	 * @param int   $local_id
	 * @param array $args { orderby: recent|top, number, offset }
	 * @return array
	 */
	public static function get_reviews( $local_id, $args = array() ) {
		global $wpdb;
		$args   = wp_parse_args( $args, array( 'orderby' => 'recent', 'number' => 20, 'offset' => 0 ) );
		$number = (int) $args['number'];
		$offset = (int) $args['offset'];

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . self::t_reviews() . " WHERE local_id = %d AND status = 'publish' ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$local_id, $number, $offset
		) );

		$reviews = array();
		foreach ( $rows as $row ) {
			$reviews[] = self::hydrate( $row );
		}

		if ( 'top' === $args['orderby'] ) {
			usort( $reviews, function( $a, $b ) {
				return $b['score'] <=> $a['score'];
			} );
		}

		return $reviews;
	}

	/**
	 * Obtiene una reseña hidratada por ID.
	 */
	public static function get_review( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::t_reviews() . " WHERE id = %d", $id ) );
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Convierte una fila en un array para la vista.
	 */
	private static function hydrate( $row ) {
		$user = get_userdata( $row->user_id );
		return array(
			'id'         => (int) $row->id,
			'local_id'   => (int) $row->local_id,
			'user_id'    => (int) $row->user_id,
			'author'     => $user ? $user->display_name : __( 'Usuario', 'caaguazu-locales' ),
			'avatar'     => get_avatar_url( $row->user_id, array( 'size' => 80 ) ),
			'rating'     => (int) $row->rating,
			'title'      => $row->title,
			'content'    => $row->content,
			'created_at' => $row->created_at,
			'date_human' => mysql2date( get_option( 'date_format' ), $row->created_at ),
			'photos'     => self::get_photos( $row->id ),
			'replies'    => self::get_replies( $row->id ),
			'score'      => self::get_vote_score( $row->id ),
		);
	}

	/* ----- Respuestas / comentarios ----- */

	public static function add_reply( $review_id, $user_id, $content ) {
		global $wpdb;
		$content = sanitize_textarea_field( $content );
		if ( '' === $content ) {
			return new WP_Error( 'empty', __( 'El comentario está vacío.', 'caaguazu-locales' ) );
		}
		$review = self::get_review( $review_id );
		if ( ! $review ) {
			return new WP_Error( 'no_review', __( 'Reseña no encontrada.', 'caaguazu-locales' ) );
		}
		$is_owner = cgz_user_can_manage_local( $review['local_id'], $user_id ) ? 1 : 0;

		$wpdb->insert(
			self::t_replies(),
			array(
				'review_id'  => $review_id,
				'user_id'    => $user_id,
				'content'    => $content,
				'is_owner'   => $is_owner,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	public static function get_replies( $review_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . self::t_replies() . " WHERE review_id = %d ORDER BY created_at ASC",
			$review_id
		) );
		$out = array();
		foreach ( $rows as $row ) {
			$user  = get_userdata( $row->user_id );
			$out[] = array(
				'id'         => (int) $row->id,
				'review_id'  => (int) $row->review_id,
				'user_id'    => (int) $row->user_id,
				'author'     => $user ? $user->display_name : __( 'Usuario', 'caaguazu-locales' ),
				'avatar'     => get_avatar_url( $row->user_id, array( 'size' => 56 ) ),
				'content'    => $row->content,
				'is_owner'   => (bool) $row->is_owner,
				'date_human' => mysql2date( get_option( 'date_format' ), $row->created_at ),
			);
		}
		return $out;
	}

	/* ----- Votos útiles ----- */

	/**
	 * Registra/actualiza/quita el voto de un usuario. vote: 1 (útil), -1 (no útil), 0 (quitar).
	 */
	public static function vote( $review_id, $user_id, $vote ) {
		global $wpdb;
		$vote = (int) $vote;
		if ( 0 === $vote ) {
			$wpdb->delete( self::t_votes(), array( 'review_id' => $review_id, 'user_id' => $user_id ), array( '%d', '%d' ) );
		} else {
			$vote = $vote > 0 ? 1 : -1;
			// upsert manual.
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . self::t_votes() . " WHERE review_id = %d AND user_id = %d",
				$review_id, $user_id
			) );
			if ( $exists ) {
				$wpdb->update( self::t_votes(), array( 'vote' => $vote ), array( 'id' => $exists ), array( '%d' ), array( '%d' ) );
			} else {
				$wpdb->insert(
					self::t_votes(),
					array( 'review_id' => $review_id, 'user_id' => $user_id, 'vote' => $vote, 'created_at' => current_time( 'mysql' ) ),
					array( '%d', '%d', '%d', '%s' )
				);
			}
		}
		return self::get_vote_score( $review_id );
	}

	public static function get_vote_score( $review_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(vote),0) FROM " . self::t_votes() . " WHERE review_id = %d",
			$review_id
		) );
	}

	public static function get_user_vote( $review_id, $user_id ) {
		global $wpdb;
		if ( ! $user_id ) { return 0; }
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT vote FROM " . self::t_votes() . " WHERE review_id = %d AND user_id = %d",
			$review_id, $user_id
		) );
	}

	/* ----- Resumen / borrado ----- */

	/**
	 * Promedio y cantidad de reseñas de un local.
	 *
	 * @return array{ average:float, count:int, distribution:array }
	 */
	public static function get_summary( $local_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT AVG(rating) AS avg, COUNT(*) AS cnt FROM " . self::t_reviews() . " WHERE local_id = %d AND status = 'publish'",
			$local_id
		) );
		$dist = array( 5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0 );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT rating, COUNT(*) AS c FROM " . self::t_reviews() . " WHERE local_id = %d AND status = 'publish' GROUP BY rating",
			$local_id
		) );
		foreach ( $rows as $r ) {
			$dist[ (int) $r->rating ] = (int) $r->c;
		}
		return array(
			'average'      => $row && $row->avg ? round( (float) $row->avg, 1 ) : 0.0,
			'count'        => $row ? (int) $row->cnt : 0,
			'distribution' => $dist,
		);
	}

	public static function delete_review( $review_id ) {
		global $wpdb;
		$wpdb->delete( self::t_reviews(), array( 'id' => $review_id ), array( '%d' ) );
		$wpdb->delete( self::t_replies(), array( 'review_id' => $review_id ), array( '%d' ) );
		$wpdb->delete( self::t_votes(), array( 'review_id' => $review_id ), array( '%d' ) );
		$wpdb->delete( self::t_photos(), array( 'review_id' => $review_id ), array( '%d' ) );
	}

	public static function delete_reply( $reply_id ) {
		global $wpdb;
		$wpdb->delete( self::t_replies(), array( 'id' => $reply_id ), array( '%d' ) );
	}

	public static function get_reply( $reply_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::t_replies() . " WHERE id = %d", $reply_id ) );
	}

	/**
	 * Limpieza al eliminar un local.
	 */
	public function cleanup_local_reviews( $post_id ) {
		if ( 'cgz_local' !== get_post_type( $post_id ) ) { return; }
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . self::t_reviews() . " WHERE local_id = %d", $post_id ) );
		foreach ( $ids as $id ) {
			self::delete_review( $id );
		}
	}
}
