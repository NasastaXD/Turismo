<?php
/**
 * Plantilla universal de página.
 *
 * Comportamiento:
 *  1. Si la página tiene contenido propio en el editor de WP, se renderiza ese contenido.
 *  2. Si está vacía y existe un archivo HTML estático correspondiente al slug en /content/,
 *     se renderiza ese HTML (caso por defecto al activar el tema con las 27 páginas seed).
 *  3. El admin puede sobrescribir cualquier página simplemente pegando contenido en el editor.
 */
get_header();

while ( have_posts() ) :
	the_post();

	$content        = get_the_content();
	$has_wp_content = trim( wp_strip_all_tags( $content ) ) !== '';

	if ( $has_wp_content ) {
		// El admin editó la página: renderiza wp content normal.
		?>
		<div class="container-wide section-y">
			<article <?php post_class(); ?>>
				<header class="section-rule mb-10">
					<h1 class="text-h1"><?php the_title(); ?></h1>
				</header>
				<div class="prose-content text-body-lg text-text-secondary max-w-3xl">
					<?php the_content(); ?>
				</div>
			</article>
		</div>
		<?php
	} else {
		// Sin contenido en WP: intenta cargar HTML estático por slug.
		$slug      = get_post_field( 'post_name', get_the_ID() );
		$ancestors = get_post_ancestors( get_the_ID() );
		$full_slug = $slug;

		if ( ! empty( $ancestors ) ) {
			$parts = array( $slug );
			foreach ( $ancestors as $ancestor_id ) {
				array_unshift( $parts, get_post_field( 'post_name', $ancestor_id ) );
			}
			$full_slug = implode( '/', $parts );
		}

		// Caso especial: home.
		if ( is_front_page() ) {
			$full_slug = 'home';
		}

		$static_html = caaguazu_get_static_content( $full_slug );

		if ( $static_html ) {
			// Renderizar HTML estático. do_shortcode permite incrustar los
			// shortcodes del plugin Caaguazú Locales (directorio, mapa, reservas, etc.).
			echo do_shortcode( $static_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenido del tema, no input del usuario.
		} else {
			?>
			<div class="container-wide section-y">
				<header class="section-rule">
					<h1 class="text-h1"><?php the_title(); ?></h1>
					<p class="mt-5 text-body-lg text-text-secondary">
						<?php esc_html_e( 'Esta página todavía no tiene contenido. Editala desde el admin de WordPress.', 'caaguazu' ); ?>
					</p>
				</header>
			</div>
			<?php
		}
	}

endwhile;

get_footer();
