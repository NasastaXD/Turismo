<?php
/**
 * Caaguazú Turismo — funciones del tema
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CAAGUAZU_THEME_VERSION', '1.0.0' );
define( 'CAAGUAZU_THEME_DIR', get_template_directory() );
define( 'CAAGUAZU_THEME_URI', get_template_directory_uri() );

/**
 * Theme setup.
 */
function caaguazu_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'automatic-feed-links' );

	register_nav_menus( array(
		'primary' => __( 'Navegación principal', 'caaguazu' ),
		'footer'  => __( 'Pie de página', 'caaguazu' ),
	) );

	load_theme_textdomain( 'caaguazu', CAAGUAZU_THEME_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'caaguazu_setup' );

/**
 * Enqueue scripts & styles.
 */
function caaguazu_enqueue_assets() {
	// Google Fonts (Sora display + Lato body) — usadas por el CSS compilado.
	wp_enqueue_style(
		'caaguazu-fonts',
		'https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700;800&family=Lato:wght@300;400;700&display=swap',
		array(),
		null
	);

	// CSS principal (Tailwind compilado + variables del sitio original).
	wp_enqueue_style(
		'caaguazu-main',
		CAAGUAZU_THEME_URI . '/assets/css/styles.css',
		array( 'caaguazu-fonts' ),
		CAAGUAZU_THEME_VERSION
	);

	// CSS de leaflet (solo se carga en páginas con mapa).
	if ( is_page( array( 'mapaciudad', 'mapa-interactivo' ) ) ) {
		wp_enqueue_style(
			'caaguazu-leaflet',
			CAAGUAZU_THEME_URI . '/assets/css/leaflet.css',
			array(),
			CAAGUAZU_THEME_VERSION
		);
		wp_enqueue_script(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			array(),
			'1.9.4',
			true
		);
		wp_enqueue_script(
			'caaguazu-map',
			CAAGUAZU_THEME_URI . '/assets/js/map.js',
			array( 'leaflet' ),
			CAAGUAZU_THEME_VERSION,
			true
		);
	}

	// JS de interacción (menú móvil, reveal-on-scroll, contadores, tooltips de glosario).
	wp_enqueue_script(
		'caaguazu-main',
		CAAGUAZU_THEME_URI . '/assets/js/main.js',
		array(),
		CAAGUAZU_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'caaguazu_enqueue_assets' );

/**
 * Custom nav walker para reproducir el menú del sitio original.
 */
require_once CAAGUAZU_THEME_DIR . '/inc/nav-walker.php';

/**
 * Importador de contenido: crea las 27 páginas al activar el tema.
 */
require_once CAAGUAZU_THEME_DIR . '/inc/page-seeder.php';

/**
 * Autoactualización del tema vía WordPress (manifiesto JSON remoto).
 */
require_once CAAGUAZU_THEME_DIR . '/inc/theme-updater.php';
function caaguazu_register_updater() {
	new Caaguazu_Theme_Updater(
		get_template(),
		CAAGUAZU_THEME_VERSION,
		apply_filters(
			'caaguazu_theme_update_manifest',
			'https://raw.githubusercontent.com/nasastaxd/turismo/main/updates/caaguazu-theme.json'
		)
	);
}
add_action( 'after_setup_theme', 'caaguazu_register_updater' );

/**
 * Helper: lee el contenido HTML estático de una página por slug.
 */
function caaguazu_get_static_content( $slug ) {
	$filename = str_replace( '/', '__', $slug );
	$path = CAAGUAZU_THEME_DIR . '/content/' . $filename . '.html';
	if ( file_exists( $path ) ) {
		return file_get_contents( $path );
	}
	return '';
}

/**
 * Permite HTML completo en el editor sin filtrado agresivo, para usuarios admin.
 * Útil porque parte del contenido se edita como HTML literal.
 */
function caaguazu_allow_full_html_for_admins() {
	if ( current_user_can( 'unfiltered_html' ) ) { return; }
	if ( current_user_can( 'manage_options' ) ) {
		// Comentado por seguridad: descomentar solo si el equipo lo necesita.
		// define( 'DISALLOW_UNFILTERED_HTML', false );
	}
}
add_action( 'init', 'caaguazu_allow_full_html_for_admins' );

/**
 * Limpia el wp_head de cruft innecesario.
 */
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );

/**
 * Permite SVG en el media library (necesario para íconos).
 */
function caaguazu_allow_svg( $mimes ) {
	$mimes['svg']  = 'image/svg+xml';
	$mimes['svgz'] = 'image/svg+xml';
	return $mimes;
}
add_filter( 'upload_mimes', 'caaguazu_allow_svg' );
