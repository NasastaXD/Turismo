<?php
/**
 * Cola de revisión (lista) y revisión lado a lado (detalle, si hay $promotur_id).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$detail_id = isset( $promotur_id ) ? (int) $promotur_id : 0;

if ( $detail_id ) {
	/* ----- Detalle de revisión ----- */
	$post = get_post( $detail_id );
	if ( ! $post || PROMOTUR_Destinos::CPT !== $post->post_type ) {
		wp_die( esc_html__( 'Ficha no encontrada.', 'caaguazu-portal' ), '', array( 'response' => 404 ) );
	}
	$estado   = PROMOTUR_Editorial::get_estado( $detail_id );
	$author_name = promotur_account_display_name( PROMOTUR_Destinos::owner_account_id( $detail_id ) );
	$groups   = PROMOTUR_Destinos::fields();
	$feedback = PROMOTUR_Editorial::get_feedback( $detail_id );

	$page_title = __( 'Revisión', 'caaguazu-portal' );
	$body = function () use ( $post, $detail_id, $estado, $author_name, $groups, $feedback ) {
		?>
		<div class="promotur-pagehead">
			<div>
				<a class="promotur-back" href="<?php echo esc_url( promotur_url( 'panel/revision' ) ); ?>">&larr; <?php esc_html_e( 'Volver a la cola', 'caaguazu-portal' ); ?></a>
				<h2 class="promotur-h2"><?php echo esc_html( get_the_title( $post ) ); ?></h2>
				<p class="promotur-muted">
					<?php
					/* translators: %s = autor */
					printf( esc_html__( 'Por %s', 'caaguazu-portal' ), esc_html( $author_name ) );
					?>
					· <span class="promotur-pill <?php echo esc_attr( PROMOTUR_Editorial::estado_class( $estado ) ); ?>"><?php echo esc_html( PROMOTUR_Editorial::estado_label( $estado ) ); ?></span>
				</p>
			</div>
		</div>

		<div class="promotur-review" data-review="<?php echo esc_attr( $detail_id ); ?>">
			<div class="promotur-review__doc promotur-card">
				<?php $portada = get_post_meta( $detail_id, '_promotur_portada', true );
				if ( $portada ) {
					echo wp_get_attachment_image( (int) $portada, 'large', false, array( 'class' => 'promotur-review__cover' ) );
				} ?>
				<h3 class="promotur-h3"><?php esc_html_e( 'Descripción', 'caaguazu-portal' ); ?></h3>
				<div class="promotur-prose"><?php echo wp_kses_post( wpautop( $post->post_content ) ); ?></div>

				<?php foreach ( $groups as $group ) : ?>
					<h4 class="promotur-review__grouptitle"><?php echo esc_html( $group['label'] ); ?></h4>
					<dl class="promotur-deflist">
						<?php foreach ( $group['fields'] as $key => $def ) :
							$val = get_post_meta( $detail_id, $key, true );
							if ( '' === trim( (string) $val ) ) { continue; } ?>
							<dt><?php echo esc_html( $def['label'] ); ?></dt>
							<dd><?php echo esc_html( (string) $val ); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endforeach; ?>
			</div>

			<aside class="promotur-review__panel">
				<div class="promotur-card">
					<h3 class="promotur-h3"><?php esc_html_e( 'Acciones', 'caaguazu-portal' ); ?></h3>

					<?php if ( 'enviado' === $estado ) : ?>
						<button type="button" class="promotur-btn promotur-btn--ghost promotur-btn--block" data-review-action="assign"><?php esc_html_e( 'Asignarme la revisión', 'caaguazu-portal' ); ?></button>
					<?php endif; ?>

					<label class="promotur-field">
						<span><?php esc_html_e( 'Feedback para el autor', 'caaguazu-portal' ); ?></span>
						<textarea data-review-comment rows="4" placeholder="<?php esc_attr_e( 'Qué corregir, qué mejorar…', 'caaguazu-portal' ); ?>"></textarea>
					</label>
					<div class="promotur-quickfb">
						<?php foreach ( PROMOTUR_Editorial::quick_feedback() as $qf ) : ?>
							<button type="button" class="promotur-chip" data-quickfb="<?php echo esc_attr( $qf ); ?>"><?php echo esc_html( $qf ); ?></button>
						<?php endforeach; ?>
					</div>

					<div class="promotur-review__buttons">
						<button type="button" class="promotur-btn promotur-btn--danger" data-review-action="return"><?php esc_html_e( 'Devolver con cambios', 'caaguazu-portal' ); ?></button>
						<?php if ( caaguazu_account_can( 'promotor', 'promotur_publish_destino' ) ) : ?>
							<button type="button" class="promotur-btn promotur-btn--primary" data-review-action="approve"><?php esc_html_e( 'Aprobar y publicar', 'caaguazu-portal' ); ?></button>
						<?php endif; ?>
					</div>
					<span class="promotur-form-msg" data-form-msg aria-live="polite"></span>
				</div>

				<?php if ( ! empty( $feedback ) ) : ?>
					<div class="promotur-card">
						<h3 class="promotur-h3"><?php esc_html_e( 'Historial', 'caaguazu-portal' ); ?></h3>
						<?php foreach ( $feedback as $c ) : ?>
							<div class="promotur-feedback__item">
								<strong><?php echo esc_html( $c->comment_author ); ?></strong>
								<p><?php echo esc_html( $c->comment_content ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</aside>
		</div>
		<?php
	};

	include PROMOTUR_DIR . 'templates/shell.php';
	return;
}

/* ----- Lista de la cola ----- */
$queue = get_posts( array(
	'post_type'      => PROMOTUR_Destinos::CPT,
	'post_status'    => 'any',
	'posts_per_page' => 100,
	'orderby'        => 'modified',
	'order'          => 'ASC',
	'meta_query'     => array( array( 'key' => '_promotur_estado', 'value' => array( 'enviado', 'en_revision' ), 'compare' => 'IN' ) ),
) );

$page_title = __( 'Cola de revisión', 'caaguazu-portal' );
$body = function () use ( $queue ) {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Taller editorial', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Cola de revisión', 'caaguazu-portal' ); ?></h2>

	<?php if ( empty( $queue ) ) : ?>
		<div class="promotur-card promotur-empty-box"><p><?php esc_html_e( 'No hay fichas esperando revisión. 🎉', 'caaguazu-portal' ); ?></p></div>
	<?php else : ?>
		<div class="promotur-list">
			<?php foreach ( $queue as $p ) :
				$estado = PROMOTUR_Editorial::get_estado( $p->ID );
				$author_name = promotur_account_display_name( PROMOTUR_Destinos::owner_account_id( $p->ID ) );
				$rev    = (int) get_post_meta( $p->ID, '_promotur_revisor', true );
				?>
				<a class="promotur-row" href="<?php echo esc_url( promotur_url( 'panel/revision/' . $p->ID ) ); ?>">
					<span class="promotur-row__main">
						<span class="promotur-row__title"><?php echo esc_html( get_the_title( $p ) ); ?></span>
						<span class="promotur-row__meta">
							<?php
							/* translators: 1: autor, 2: hace cuánto */
							printf(
								esc_html__( '%1$s · esperó %2$s', 'caaguazu-portal' ),
								esc_html( $author_name ),
								esc_html( human_time_diff( (int) get_post_modified_time( 'U', true, $p ) ) )
							);
							if ( $rev ) {
								echo ' · ' . esc_html( sprintf( __( 'revisa %s', 'caaguazu-portal' ), promotur_account_display_name( $rev, '' ) ) );
							}
							?>
						</span>
					</span>
					<span class="promotur-pill <?php echo esc_attr( PROMOTUR_Editorial::estado_class( $estado ) ); ?>"><?php echo esc_html( PROMOTUR_Editorial::estado_label( $estado ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
