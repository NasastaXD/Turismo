<?php
/**
 * Page seeder: crea automáticamente las 27 páginas del sitio
 * la primera vez que se activa el tema.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Activación: dispara la importación.
 */
function caaguazu_seed_pages() {
	$already_done = get_option( 'caaguazu_pages_seeded' );
	if ( $already_done ) { return; }

	$manifest_path = CAAGUAZU_THEME_DIR . '/content/manifest.json';
	if ( ! file_exists( $manifest_path ) ) { return; }

	$manifest = json_decode( file_get_contents( $manifest_path ), true );
	if ( ! is_array( $manifest ) ) { return; }

	$slug_to_id = array();
	$home_id    = null;

	// Pasada 1: crear todas las páginas raíz (sin parent).
	foreach ( $manifest as $page ) {
		if ( $page['parent'] !== null && $page['parent'] !== '' ) { continue; }

		$wp_slug = $page['is_home'] ? 'inicio' : $page['wp_slug'];

		// ¿Ya existe?
		$existing = get_page_by_path( $wp_slug, OBJECT, 'page' );
		if ( $existing ) {
			$slug_to_id[ $page['slug'] ] = $existing->ID;
			if ( $page['is_home'] ) { $home_id = $existing->ID; }
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_title'   => $page['title'],
			'post_name'    => $wp_slug,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '', // intencionalmente vacío: la plantilla page.php cargará el HTML estático.
			'post_excerpt' => $page['desc'],
		) );

		if ( is_wp_error( $post_id ) ) { continue; }

		$slug_to_id[ $page['slug'] ] = $post_id;
		if ( $page['is_home'] ) { $home_id = $post_id; }
	}

	// Pasada 2: páginas hijas.
	foreach ( $manifest as $page ) {
		if ( $page['parent'] === null || $page['parent'] === '' ) { continue; }

		$parent_id = isset( $slug_to_id[ $page['parent'] ] ) ? $slug_to_id[ $page['parent'] ] : 0;

		$existing = get_page_by_path( $page['slug'], OBJECT, 'page' );
		if ( $existing ) {
			$slug_to_id[ $page['slug'] ] = $existing->ID;
			continue;
		}

		$post_id = wp_insert_post( array(
			'post_title'   => $page['title'],
			'post_name'    => $page['wp_slug'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_parent'  => $parent_id,
			'post_content' => '',
			'post_excerpt' => $page['desc'],
		) );

		if ( is_wp_error( $post_id ) ) { continue; }
		$slug_to_id[ $page['slug'] ] = $post_id;
	}

	// Configurar página de inicio.
	if ( $home_id ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
	}

	// Crear menú primario por defecto si no existe.
	if ( ! wp_get_nav_menu_object( 'Navegación principal' ) ) {
		$menu_id = wp_create_nav_menu( 'Navegación principal' );
		$top_level = array(
			'la-capital-de-la-madera' => 'La Capital de la Madera',
			'que-hacer'               => 'Qué hacer',
			'sabores-de-caaguazu'     => 'Sabores',
			'vivir-caaguazu'          => 'Vivir Caaguazú',
			'planifica-tu-visita'     => 'Planificá tu visita',
		);
		foreach ( $top_level as $slug => $label ) {
			if ( ! isset( $slug_to_id[ $slug ] ) ) { continue; }
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'     => $label,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $slug_to_id[ $slug ],
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			) );
		}

		$locations = get_theme_mod( 'nav_menu_locations' );
		if ( ! is_array( $locations ) ) { $locations = array(); }
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	update_option( 'caaguazu_pages_seeded', 1 );
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'caaguazu_seed_pages' );

/**
 * Permite forzar el re-seed desde wp-admin (útil en desarrollo).
 * Visitar: /wp-admin/admin.php?caaguazu_reseed=1 como administrador.
 */
function caaguazu_maybe_reseed() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	if ( empty( $_GET['caaguazu_reseed'] ) ) { return; }
	if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'caaguazu_reseed' ) ) {
		wp_die( esc_html__( 'Nonce inválido. Generá un link nuevo desde Apariencia > Caaguazú.', 'caaguazu' ) );
	}
	delete_option( 'caaguazu_pages_seeded' );
	caaguazu_seed_pages();
	wp_safe_redirect( admin_url( 'edit.php?post_type=page' ) );
	exit;
}
add_action( 'admin_init', 'caaguazu_maybe_reseed' );

/**
 * Pequeño panel en Apariencia para re-importar o resetear.
 */
function caaguazu_admin_page() {
	add_theme_page(
		__( 'Caaguazú — Contenido', 'caaguazu' ),
		__( 'Caaguazú', 'caaguazu' ),
		'manage_options',
		'caaguazu-content',
		'caaguazu_render_admin_page'
	);
}
add_action( 'admin_menu', 'caaguazu_admin_page' );

function caaguazu_render_admin_page() {
	$reseed_url = wp_nonce_url( admin_url( 'admin.php?caaguazu_reseed=1' ), 'caaguazu_reseed' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Caaguazú Turismo — Contenido', 'caaguazu' ); ?></h1>
		<p><?php esc_html_e( 'El tema viene con 27 páginas pre-cargadas a partir del sitio estático original. Cada página tiene su HTML guardado en /content/ del tema; cuando editás una página desde wp-admin y le agregás contenido propio, ese contenido sobrescribe al estático automáticamente.', 'caaguazu' ); ?></p>

		<h2><?php esc_html_e( 'Re-importar páginas', 'caaguazu' ); ?></h2>
		<p><?php esc_html_e( 'Esto recrea las páginas faltantes desde el manifest del tema. Las páginas existentes con contenido editado no se sobreescriben.', 'caaguazu' ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( $reseed_url ); ?>"><?php esc_html_e( 'Re-importar páginas ahora', 'caaguazu' ); ?></a></p>

		<h2><?php esc_html_e( 'Estado', 'caaguazu' ); ?></h2>
		<ul>
			<li><?php
				/* translators: %d is the count */
				printf( esc_html__( 'Páginas existentes (post_type=page): %d', 'caaguazu' ), (int) wp_count_posts( 'page' )->publish );
			?></li>
			<li><?php
				printf(
					esc_html__( 'Seed marcado como completado: %s', 'caaguazu' ),
					get_option( 'caaguazu_pages_seeded' ) ? esc_html__( 'sí', 'caaguazu' ) : esc_html__( 'no', 'caaguazu' )
				);
			?></li>
		</ul>
	</div>
	<?php
}
