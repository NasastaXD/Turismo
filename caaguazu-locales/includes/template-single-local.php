<?php
/**
 * Plantilla de un local. Se usa solo si el tema activo no provee single-cgz_local.php.
 * Diseñada para encajar con el tema Caaguazú (clases utilitarias del CSS del tema)
 * y degradar de forma elegante en cualquier otro tema.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

while ( have_posts() ) :
	the_post();
	$id        = get_the_ID();
	$tipo      = get_post_meta( $id, '_cgz_tipo', true );
	$types     = cgz_local_types();
	$direccion = get_post_meta( $id, '_cgz_direccion', true );
	$horario   = get_post_meta( $id, '_cgz_horario', true );
	$telefono  = get_post_meta( $id, '_cgz_telefono', true );
	$coords    = cgz_get_coords( $id );
	?>
	<div class="cgz-single container-wide section-y">
		<article>
			<header class="cgz-single__header">
				<?php if ( isset( $types[ $tipo ] ) ) : ?>
					<p class="eyebrow cgz-single__eyebrow"><?php echo esc_html( $types[ $tipo ] ); ?></p>
				<?php endif; ?>
				<h1 class="text-h1"><?php the_title(); ?></h1>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<div class="cgz-single__media">
					<?php the_post_thumbnail( 'large' ); ?>
				</div>
			<?php endif; ?>

			<div class="cgz-single__grid">
				<div class="cgz-single__main">
					<div class="prose-content text-body-lg text-text-secondary">
						<?php the_content(); ?>
					</div>

					<?php
					// Reseñas.
					echo do_shortcode( '[caaguazu_resenas id="' . $id . '"]' );
					?>
				</div>

				<aside class="cgz-single__aside">
					<?php
					// Reserva por WhatsApp (solo restaurantes/hoteles con número).
					$booking = CGZ_Booking::render_widget( $id );
					if ( $booking ) {
						echo $booking; // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>

					<div class="cgz-info-card">
						<h3 class="cgz-info-card__title"><?php esc_html_e( 'Información', 'caaguazu-locales' ); ?></h3>
						<ul class="cgz-info-card__list">
							<?php if ( $direccion ) : ?>
								<li><strong><?php esc_html_e( 'Dirección:', 'caaguazu-locales' ); ?></strong> <?php echo esc_html( $direccion ); ?></li>
							<?php endif; ?>
							<?php if ( $horario ) : ?>
								<li><strong><?php esc_html_e( 'Horario:', 'caaguazu-locales' ); ?></strong> <?php echo esc_html( $horario ); ?></li>
							<?php endif; ?>
							<?php if ( $telefono ) : ?>
								<li><strong><?php esc_html_e( 'Teléfono:', 'caaguazu-locales' ); ?></strong> <?php echo esc_html( $telefono ); ?></li>
							<?php endif; ?>
						</ul>
					</div>

					<?php if ( $coords ) : ?>
						<?php echo do_shortcode( '[caaguazu_mapa alto="280px"]' ); // muestra todos; el usuario ve la ciudad ?>
					<?php endif; ?>
				</aside>
			</div>
		</article>
	</div>
	<?php
endwhile;

get_footer();
