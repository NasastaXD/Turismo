<?php
/**
 * Footer del tema Caaguazú Turismo.
 */
?>
</main>

<footer class="bg-wood-charcoal text-paper mt-20">
	<div class="container-wide py-16 grid grid-cols-1 md:grid-cols-4 gap-10">
		<div class="md:col-span-1">
			<div class="font-display text-2xl mb-3"><?php bloginfo( 'name' ); ?></div>
			<p class="text-sm text-paper/70 mb-5">
				<?php echo esc_html( get_theme_mod( 'caaguazu_tagline', __( 'Donde la madera cuenta historias', 'caaguazu' ) ) ); ?>
			</p>
			<div class="inline-block px-6 py-3 border-2 border-double rounded-sm font-display tracking-wider text-sm uppercase" style="border-color:var(--color-wood-lapacho);color:var(--color-wood-lapacho)">
				<?php esc_html_e( 'Caaguazú · Capital de la Madera · 1845', 'caaguazu' ); ?>
			</div>
		</div>

		<div>
			<h3 class="font-display text-base text-wood-cedro mb-4 uppercase tracking-wider">
				<?php esc_html_e( 'Explorar Caaguazú', 'caaguazu' ); ?>
			</h3>
			<ul class="space-y-2 text-sm">
				<?php
				$explore = array(
					'/la-capital-de-la-madera' => __( 'La Capital de la Madera', 'caaguazu' ),
					'/que-hacer'               => __( 'Qué hacer', 'caaguazu' ),
					'/sabores-de-caaguazu'     => __( 'Sabores', 'caaguazu' ),
					'/vivir-caaguazu'          => __( 'Vivir Caaguazú', 'caaguazu' ),
				);
				foreach ( $explore as $url => $label ) {
					printf(
						'<li><a href="%s" class="text-paper/80 hover:text-paper">%s</a></li>',
						esc_url( home_url( $url ) ),
						esc_html( $label )
					);
				}
				?>
			</ul>
		</div>

		<div>
			<h3 class="font-display text-base text-wood-cedro mb-4 uppercase tracking-wider">
				<?php esc_html_e( 'Planificá tu visita', 'caaguazu' ); ?>
			</h3>
			<ul class="space-y-2 text-sm">
				<li><a href="<?php echo esc_url( home_url( '/planifica-tu-visita' ) ); ?>" class="text-paper/80 hover:text-paper"><?php esc_html_e( 'Planificá tu visita', 'caaguazu' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/contacto' ) ); ?>" class="text-paper/80 hover:text-paper"><?php esc_html_e( 'Contacto', 'caaguazu' ); ?></a></li>
			</ul>
		</div>

		<div>
			<h3 class="font-display text-base text-wood-cedro mb-4 uppercase tracking-wider">
				<?php esc_html_e( 'Sobre el portal', 'caaguazu' ); ?>
			</h3>
			<p class="text-sm text-paper/70 mb-3">
				<?php esc_html_e( 'Parte del ecosistema caaguazu.net', 'caaguazu' ); ?>
			</p>
			<a href="https://caaguazu.net" class="text-sm text-wood-cedro hover:text-paper">caaguazu.net →</a>
		</div>
	</div>

	<div class="border-t border-paper/10">
		<div class="container-wide py-6 flex flex-col md:flex-row items-center justify-between gap-3 text-xs text-paper/60">
			<span><?php esc_html_e( 'Municipalidad de Caaguazú · Paraguay', 'caaguazu' ); ?></span>
			<span>© <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php esc_html_e( 'Municipalidad de Caaguazú', 'caaguazu' ); ?></span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
