<?php
/**
 * Template de fallback.
 * Para páginas estáticas el tema usa page.php; este archivo solo aplica
 * a vistas de blog, archivos, búsquedas, etc.
 */
get_header(); ?>

<div class="container-wide section-y">
	<?php if ( have_posts() ) : ?>
		<header class="section-rule mb-12">
			<h1 class="text-h1"><?php
				if ( is_home() || is_front_page() ) {
					bloginfo( 'name' );
				} elseif ( is_search() ) {
					/* translators: %s is the search query */
					printf( esc_html__( 'Resultados para: %s', 'caaguazu' ), '<em>' . esc_html( get_search_query() ) . '</em>' );
				} elseif ( is_archive() ) {
					the_archive_title();
				} else {
					esc_html_e( 'Entradas recientes', 'caaguazu' );
				}
			?></h1>
		</header>

		<div class="grid md:grid-cols-2 gap-10">
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'border-t border-border pt-5' ); ?>>
					<p class="eyebrow mb-3"><?php echo esc_html( get_the_date() ); ?></p>
					<h2 class="text-h3"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="mt-3 text-text-secondary"><?php the_excerpt(); ?></div>
				</article>
			<?php endwhile; ?>
		</div>

		<div class="mt-12">
			<?php the_posts_pagination(); ?>
		</div>

	<?php else : ?>
		<div class="section-rule">
			<h1 class="text-h2"><?php esc_html_e( 'Nada por aquí', 'caaguazu' ); ?></h1>
			<p class="mt-5 text-body-lg text-text-secondary">
				<?php esc_html_e( 'No encontramos resultados. Probá una búsqueda o volvé al inicio.', 'caaguazu' ); ?>
			</p>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="pill-link mt-6">
				<?php esc_html_e( 'Volver al inicio', 'caaguazu' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>

<?php get_footer();
