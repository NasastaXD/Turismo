<?php
/**
 * Ficha pública de destino (vista turista): lo accionable arriba, la prosa después.
 * Integra con el theme activo (get_header/get_footer).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

while ( have_posts() ) :
	the_post();
	$id      = get_the_ID();
	$gancho  = get_post_meta( $id, '_promotur_gancho', true );
	$portada = get_post_meta( $id, '_promotur_portada', true );
	$lat     = get_post_meta( $id, '_promotur_lat', true );
	$lng     = get_post_meta( $id, '_promotur_lng', true );
	$horario = get_post_meta( $id, '_promotur_horario', true );
	$costo   = get_post_meta( $id, '_promotur_costo', true );
	$comollegar = get_post_meta( $id, '_promotur_como_llegar', true );
	$credito = get_post_meta( $id, '_promotur_credito_fotos', true );
	$verif   = get_post_meta( $id, '_promotur_verificado_en', true );
	$maps_url = ( $lat && $lng ) ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $lat . ',' . $lng ) : '';

	$facts = array(
		__( 'Horario', 'caaguazu-portal' ) => $horario,
		__( 'Costo / entrada', 'caaguazu-portal' ) => $costo,
		__( 'Cómo llegar', 'caaguazu-portal' ) => $comollegar,
	);
	?>
	<article class="promotur-destino container-wide section-y">
		<header class="promotur-destino__head">
			<p class="eyebrow"><?php
				$terms = get_the_term_list( $id, 'promotur_categoria', '', ', ' );
				echo $terms ? wp_kses_post( $terms ) : esc_html__( 'Destino', 'caaguazu-portal' );
			?></p>
			<h1 class="text-h1"><?php the_title(); ?></h1>
			<?php if ( $gancho ) : ?><p class="promotur-destino__gancho"><?php echo esc_html( $gancho ); ?></p><?php endif; ?>
		</header>

		<?php if ( $portada ) : ?>
			<figure class="promotur-destino__cover">
				<?php echo wp_get_attachment_image( (int) $portada, 'large', false, array( 'loading' => 'eager' ) ); ?>
				<?php if ( $credito ) : ?><figcaption><?php echo esc_html( $credito ); ?></figcaption><?php endif; ?>
			</figure>
		<?php endif; ?>

		<?php // Lo accionable, arriba de todo. ?>
		<div class="promotur-destino__facts">
			<?php foreach ( $facts as $label => $val ) :
				if ( '' === trim( (string) $val ) ) { continue; } ?>
				<div class="promotur-destino__fact">
					<span class="promotur-destino__factlabel"><?php echo esc_html( $label ); ?></span>
					<span class="promotur-destino__factval"><?php echo esc_html( $val ); ?></span>
				</div>
			<?php endforeach; ?>
			<?php if ( $maps_url ) : ?>
				<a class="promotur-btn promotur-btn--primary" href="<?php echo esc_url( $maps_url ); ?>" target="_blank" rel="noopener">📍 <?php esc_html_e( 'Cómo llegar', 'caaguazu-portal' ); ?></a>
			<?php endif; ?>
			<button type="button" class="promotur-btn promotur-btn--ghost" data-itin-add data-id="<?php echo esc_attr( $id ); ?>" data-title="<?php echo esc_attr( get_the_title() ); ?>" data-url="<?php echo esc_url( get_permalink() ); ?>">➕ <?php esc_html_e( 'Agregar a mi viaje', 'caaguazu-portal' ); ?></button>
			<button type="button" class="promotur-btn promotur-btn--ghost" data-qr data-url="<?php echo esc_url( get_permalink() ); ?>" data-title="<?php echo esc_attr( get_the_title() ); ?>">🔳 <?php esc_html_e( 'QR', 'caaguazu-portal' ); ?></button>
		</div>
		<div class="promotur-qrmodal" data-qr-modal hidden>
			<div class="promotur-qrmodal__box">
				<button type="button" class="promotur-qrmodal__close" data-qr-close aria-label="<?php esc_attr_e( 'Cerrar', 'caaguazu-portal' ); ?>">×</button>
				<div data-qr-canvas></div>
				<p class="promotur-muted"><?php esc_html_e( 'Escaneá para abrir esta ficha en el celular.', 'caaguazu-portal' ); ?></p>
			</div>
		</div>

		<div class="promotur-destino__body prose-content">
			<?php the_content(); ?>
		</div>

		<?php
		// Resto de datos prácticos / editoriales.
		$more_groups = PROMOTUR_Destinos::fields();
		foreach ( $more_groups as $gkey => $group ) :
			$rows = array();
			foreach ( $group['fields'] as $key => $def ) {
				if ( in_array( $key, array( '_promotur_gancho', '_promotur_portada', '_promotur_credito_fotos', '_promotur_horario', '_promotur_costo', '_promotur_como_llegar', '_promotur_lat', '_promotur_lng' ), true ) ) {
					continue; // ya mostrados arriba
				}
				$v = get_post_meta( $id, $key, true );
				if ( '' !== trim( (string) $v ) ) { $rows[ $def['label'] ] = $v; }
			}
			if ( empty( $rows ) ) { continue; }
			?>
			<section class="promotur-destino__section">
				<h2 class="text-h3"><?php echo esc_html( $group['label'] ); ?></h2>
				<dl class="promotur-deflist">
					<?php foreach ( $rows as $l => $v ) : ?>
						<dt><?php echo esc_html( $l ); ?></dt><dd><?php echo esc_html( $v ); ?></dd>
					<?php endforeach; ?>
				</dl>
			</section>
		<?php endforeach; ?>

		<?php if ( $verif ) : ?>
			<p class="promotur-destino__verif">✓ <?php
				/* translators: %s = fecha */
				printf( esc_html__( 'Verificado por un Promotor el %s', 'caaguazu-portal' ), esc_html( mysql2date( get_option( 'date_format' ), $verif ) ) );
			?></p>
		<?php endif; ?>

		<?php
		// Reseñas de visitantes (moderadas).
		echo PROMOTUR_Resenas::render_block( $id ); // phpcs:ignore WordPress.Security.EscapeOutput

		// Reportar info desactualizada.
		?>
		<section class="promotur-report">
			<button type="button" class="promotur-link-btn" data-report-toggle>⚠️ <?php esc_html_e( 'Reportar información desactualizada', 'caaguazu-portal' ); ?></button>
			<form class="promotur-form promotur-report-form" data-reporte-form data-post="<?php echo esc_attr( $id ); ?>" hidden>
				<label class="promotur-field"><span><?php esc_html_e( '¿Qué está desactualizado?', 'caaguazu-portal' ); ?></span><textarea name="content" rows="2" required></textarea></label>
				<button type="submit" class="promotur-btn promotur-btn--ghost promotur-btn--small"><?php esc_html_e( 'Enviar reporte', 'caaguazu-portal' ); ?></button>
				<span class="promotur-form-msg" data-form-msg aria-live="polite"></span>
			</form>
		</section>

		<?php
		// Consulta sobre este destino.
		echo PROMOTUR_Public::contact_form( $id ); // phpcs:ignore WordPress.Security.EscapeOutput
		?>

		<p class="promotur-destino__author promotur-muted">
			<?php
			$author_name = function_exists( 'promotur_account_display_name' )
				? promotur_account_display_name( PROMOTUR_Destinos::owner_account_id( $id ), '' )
				: '';
			/* translators: %s = autor */
			printf( esc_html__( 'Ficha producida por %s — Promotores Turísticos del Bachiller técnico de servicios.', 'caaguazu-portal' ), esc_html( $author_name ) );
			?>
		</p>
	</article>
	<?php
endwhile;

get_footer();
