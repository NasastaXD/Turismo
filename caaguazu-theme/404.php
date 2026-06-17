<?php
/**
 * 404 template.
 */
get_header(); ?>

<div class="container-wide section-y">
	<div class="section-rule max-w-2xl">
		<p class="eyebrow mb-3">404</p>
		<h1 class="text-h1"><?php esc_html_e( 'Esta página no existe', 'caaguazu' ); ?></h1>
		<p class="mt-5 text-body-lg text-text-secondary">
			<?php esc_html_e( 'La dirección que buscás no está en el portal. Probá volver al inicio o explorar las secciones principales.', 'caaguazu' ); ?>
		</p>
		<div class="mt-8 flex flex-wrap gap-3">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="pill-link">
				<?php esc_html_e( 'Volver al inicio', 'caaguazu' ); ?>
			</a>
			<a href="<?php echo esc_url( home_url( '/que-hacer' ) ); ?>" class="pill-link">
				<?php esc_html_e( 'Qué hacer en Caaguazú', 'caaguazu' ); ?>
			</a>
		</div>
	</div>
</div>

<?php get_footer();
