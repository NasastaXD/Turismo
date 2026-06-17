<?php
/**
 * Reseñas de visitantes sobre destinos (moderadas). Se guardan como comentarios
 * de tipo promotur_resena con meta de rating; se publican solo al aprobarse.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Resenas {

	private static $instance = null;
	const CTYPE = 'promotur_resena';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Mantener reseñas/reportes fuera de las queries públicas genéricas de comentarios.
		add_filter( 'comments_clauses', array( $this, 'hide_from_public' ) );
		add_filter( 'comment_text', array( $this, 'noop_text' ), 10, 2 );
	}

	public function noop_text( $text, $comment = null ) {
		return $text;
	}

	/**
	 * Crea una reseña (pendiente de moderación).
	 *
	 * @param array $data { post_id, rating, author, email, content, user_id }
	 * @return int|WP_Error comment_id
	 */
	public static function add( $data ) {
		$post_id = (int) ( $data['post_id'] ?? 0 );
		$rating  = max( 1, min( 5, (int) ( $data['rating'] ?? 0 ) ) );
		$content = sanitize_textarea_field( $data['content'] ?? '' );
		$user_id = (int) ( $data['user_id'] ?? 0 );

		if ( ! $post_id || PROMOTUR_Destinos::CPT !== get_post_type( $post_id ) ) {
			return new WP_Error( 'bad_post', __( 'Destino inválido.', 'caaguazu-portal' ) );
		}
		if ( '' === $content ) {
			return new WP_Error( 'empty', __( 'Escribí tu reseña.', 'caaguazu-portal' ) );
		}

		if ( $user_id ) {
			$u      = get_userdata( $user_id );
			$author = $u ? $u->display_name : __( 'Usuario', 'caaguazu-portal' );
			$email  = $u ? $u->user_email : '';
		} else {
			$author = sanitize_text_field( $data['author'] ?? '' );
			$email  = sanitize_email( $data['email'] ?? '' );
			if ( '' === $author ) {
				return new WP_Error( 'no_name', __( 'Decinos tu nombre.', 'caaguazu-portal' ) );
			}
		}

		$comment_id = wp_insert_comment( array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => $content,
			'comment_type'         => self::CTYPE,
			'comment_author'       => $author,
			'comment_author_email' => $email,
			'user_id'              => $user_id,
			'comment_approved'     => 0, // moderación
		) );
		if ( $comment_id ) {
			add_comment_meta( $comment_id, 'promotur_rating', $rating );
		}
		return $comment_id ? (int) $comment_id : new WP_Error( 'fail', __( 'No se pudo guardar.', 'caaguazu-portal' ) );
	}

	/**
	 * Reseñas aprobadas de un destino.
	 *
	 * @return WP_Comment[]
	 */
	public static function approved( $post_id ) {
		return get_comments( array(
			'post_id' => $post_id,
			'type'    => self::CTYPE,
			'status'  => 'approve',
			'orderby' => 'comment_date',
			'order'   => 'DESC',
		) );
	}

	/**
	 * Resumen: promedio y cantidad de reseñas aprobadas.
	 *
	 * @return array{average:float,count:int}
	 */
	public static function summary( $post_id ) {
		$list  = self::approved( $post_id );
		$count = count( $list );
		$sum   = 0;
		foreach ( $list as $c ) {
			$sum += (int) get_comment_meta( $c->comment_ID, 'promotur_rating', true );
		}
		return array(
			'average' => $count ? round( $sum / $count, 1 ) : 0.0,
			'count'   => $count,
		);
	}

	public static function rating_of( $comment_id ) {
		return (int) get_comment_meta( $comment_id, 'promotur_rating', true );
	}

	/**
	 * Reseñas pendientes de moderación (todas).
	 *
	 * @return WP_Comment[]
	 */
	public static function pending() {
		return get_comments( array(
			'type'    => self::CTYPE,
			'status'  => 'hold',
			'orderby' => 'comment_date',
			'order'   => 'DESC',
			'number'  => 100,
		) );
	}

	/** Estrellas en HTML para un rating 0–5. */
	public static function stars( $rating ) {
		$rating = (float) $rating;
		$full   = (int) round( $rating );
		$html   = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$html .= '<span class="promotur-star' . ( $i <= $full ? ' is-on' : '' ) . '">★</span>';
		}
		return '<span class="promotur-stars" aria-label="' . esc_attr( sprintf( __( '%s de 5', 'caaguazu-portal' ), $rating ) ) . '">' . $html . '</span>';
	}

	/**
	 * Excluye nuestros tipos de comentarios de las queries públicas genéricas
	 * (las que no piden un type explícito).
	 */
	public function hide_from_public( $clauses ) {
		if ( is_admin() ) {
			return $clauses;
		}
		// Si la query ya filtra por comment_type, respetarla.
		if ( false !== strpos( $clauses['where'], 'comment_type' ) ) {
			return $clauses;
		}
		global $wpdb;
		$clauses['where'] .= " AND {$wpdb->comments}.comment_type NOT IN ('promotur_resena','promotur_reporte','promotur_feedback')";
		return $clauses;
	}

	/* ----- Render en la ficha pública ----- */

	/**
	 * Bloque de reseñas (resumen + lista + formulario) para el single.
	 */
	public static function render_block( $post_id ) {
		$summary = self::summary( $post_id );
		$list    = self::approved( $post_id );
		ob_start();
		?>
		<section class="promotur-resenas" id="resenas">
			<h2 class="text-h3"><?php esc_html_e( 'Reseñas', 'caaguazu-portal' ); ?></h2>
			<div class="promotur-resenas__summary">
				<?php if ( $summary['count'] ) : ?>
					<span class="promotur-resenas__avg"><?php echo esc_html( $summary['average'] ); ?></span>
					<?php echo self::stars( $summary['average'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="promotur-muted"><?php
						/* translators: %d = cantidad */
						printf( esc_html( _n( '%d reseña', '%d reseñas', $summary['count'], 'caaguazu-portal' ) ), (int) $summary['count'] );
					?></span>
				<?php else : ?>
					<span class="promotur-muted"><?php esc_html_e( 'Sé el primero en dejar una reseña.', 'caaguazu-portal' ); ?></span>
				<?php endif; ?>
			</div>

			<div class="promotur-resenas__list">
				<?php foreach ( $list as $c ) : ?>
					<article class="promotur-resena">
						<header>
							<strong><?php echo esc_html( $c->comment_author ); ?></strong>
							<?php echo self::stars( self::rating_of( $c->comment_ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span class="promotur-muted"><?php echo esc_html( get_comment_date( '', $c ) ); ?></span>
						</header>
						<p><?php echo esc_html( $c->comment_content ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>

			<form class="promotur-form promotur-resena-form" data-resena-form data-post="<?php echo esc_attr( $post_id ); ?>">
				<h3 class="text-h3"><?php esc_html_e( 'Dejá tu reseña', 'caaguazu-portal' ); ?></h3>
				<div class="promotur-rating-input" data-rating>
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<button type="button" class="promotur-rating-star" data-value="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%d estrellas', 'caaguazu-portal' ), $i ) ); ?>">★</button>
					<?php endfor; ?>
					<input type="hidden" name="rating" value="5">
				</div>
				<?php if ( ! is_user_logged_in() ) : ?>
					<div class="promotur-grid promotur-grid--2">
						<label class="promotur-field"><span><?php esc_html_e( 'Tu nombre', 'caaguazu-portal' ); ?></span><input type="text" name="author" required></label>
						<label class="promotur-field"><span><?php esc_html_e( 'Email (no se publica)', 'caaguazu-portal' ); ?></span><input type="email" name="email"></label>
					</div>
				<?php endif; ?>
				<label class="promotur-field"><span><?php esc_html_e( 'Tu experiencia', 'caaguazu-portal' ); ?></span><textarea name="content" rows="3" required></textarea></label>
				<button type="submit" class="promotur-btn promotur-btn--primary"><?php esc_html_e( 'Enviar reseña', 'caaguazu-portal' ); ?></button>
				<span class="promotur-form-msg" data-form-msg aria-live="polite"></span>
				<p class="promotur-muted"><?php esc_html_e( 'Tu reseña se publica luego de una breve moderación.', 'caaguazu-portal' ); ?></p>
			</form>
		</section>
		<?php
		return ob_get_clean();
	}
}
