<?php
/**
 * Header del tema Caaguazú Turismo.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a href="#main-content" class="skip-to-content"><?php esc_html_e( 'Saltar al contenido', 'caaguazu' ); ?></a>

<header class="sticky top-0 z-40 pointer-events-none">
	<div class="container-wide pt-4 pb-2">
		<div class="pointer-events-auto bg-ink text-snow rounded-2xl shadow-lg flex items-center justify-between gap-4 pl-5 pr-3 h-14">

			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Caaguazú Turismo — inicio" class="flex items-center gap-2.5 group">
				<span class="inline-block w-6 h-6 rounded-full border-2 border-snow/80" aria-hidden="true"></span>
				<span class="font-display font-bold text-base tracking-tight">
					<?php bloginfo( 'name' ); ?>
				</span>
			</a>

			<nav class="hidden lg:flex items-center gap-6" aria-label="<?php esc_attr_e( 'Navegación principal', 'caaguazu' ); ?>">
				<?php
				if ( has_nav_menu( 'primary' ) ) {
					wp_nav_menu( array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'flex items-center gap-6',
						'items_wrap'     => '%3$s',
						'walker'         => new Caaguazu_Primary_Walker(),
						'depth'          => 1,
					) );
				} else {
					// Menú por defecto si el admin aún no configuró uno.
					$default_items = array(
						'/la-capital-de-la-madera' => 'La Capital de la Madera',
						'/que-hacer'               => 'Qué hacer',
						'/sabores-de-caaguazu'     => 'Sabores',
						'/vivir-caaguazu'          => 'Vivir Caaguazú',
						'/planifica-tu-visita'     => 'Planificá tu visita',
					);
					foreach ( $default_items as $url => $label ) {
						printf(
							'<a href="%s" class="text-sm font-display font-medium text-snow/85 hover:text-snow transition-colors">%s</a>',
							esc_url( home_url( $url ) ),
							esc_html( $label )
						);
					}
				}
				?>
			</nav>

			<div class="flex items-center gap-1.5">
				<div class="flex items-center gap-2 text-sm" role="group" aria-label="<?php esc_attr_e( 'Selector de idioma', 'caaguazu' ); ?>">
					<button type="button" class="text-text-secondary hover:text-wood-charcoal text-wood-lapacho font-semibold border-wood-lapacho px-1.5 py-0.5 border-b transition-colors" aria-pressed="true">ES</button>
					<span title="<?php esc_attr_e( 'Próximamente', 'caaguazu' ); ?>" class="text-text-secondary opacity-40 cursor-not-allowed px-1.5 py-0.5 border-b border-transparent" aria-disabled="true">GN</span>
					<button type="button" class="text-text-secondary hover:text-wood-charcoal px-1.5 py-0.5 border-b transition-colors" aria-pressed="false">EN</button>
				</div>

				<button type="button" class="hidden sm:inline-flex items-center justify-center w-10 h-10 rounded-full hover:bg-snow/10 transition-colors" aria-label="<?php esc_attr_e( 'Buscar', 'caaguazu' ); ?>" data-search-toggle>
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4" aria-hidden="true">
						<path d="m21 21-4.34-4.34"></path><circle cx="11" cy="11" r="8"></circle>
					</svg>
				</button>

				<button type="button" class="inline-flex items-center justify-center w-10 h-10 rounded-full hover:bg-snow/10 transition-colors lg:hidden" aria-label="<?php esc_attr_e( 'Abrir menú', 'caaguazu' ); ?>" aria-expanded="false" data-mobile-toggle>
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" aria-hidden="true">
						<path d="M4 5h16"></path><path d="M4 12h16"></path><path d="M4 19h16"></path>
					</svg>
				</button>
			</div>
		</div>

		<!-- Mobile menu (oculto por defecto, abierto por JS) -->
		<div id="mobile-menu" class="lg:hidden pointer-events-auto bg-ink text-snow rounded-2xl shadow-lg mt-2 p-5 hidden" aria-hidden="true">
			<nav aria-label="<?php esc_attr_e( 'Navegación móvil', 'caaguazu' ); ?>">
				<?php
				if ( has_nav_menu( 'primary' ) ) {
					wp_nav_menu( array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'flex flex-col gap-3',
						'items_wrap'     => '<ul class="flex flex-col gap-3">%3$s</ul>',
						'walker'         => new Caaguazu_Mobile_Walker(),
					) );
				}
				?>
			</nav>
		</div>
	</div>
</header>

<main id="main-content">
