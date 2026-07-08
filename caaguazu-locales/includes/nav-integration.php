<?php
/**
 * Integración con el shell propio de Turismo que expone el theme Caaguazú
 * (`caaguazu_tourism_shell_items`, ver caaguazu-theme/inc/tourism-shell.php
 * y caaguazu-turismo/includes/nav-integration.php en el repo del portal
 * principal). El theme no sabe nada de Locales — solo pinta lo que este
 * plugin le pasa por el filtro, con un ítem "Dónde ir" que despliega un
 * link por cada tipo de local (`cgz_local_types()`, la misma lista que ya
 * usa el resto del plugin), para que restaurantes/hoteles/comercios/
 * atracciones sean alcanzables desde el nav sin duplicar contenido.
 *
 * También registra el filtrado real por tipo (`?tipo=restaurante`) sobre
 * el archivo del CPT, para que esos links no sean decorativos.
 *
 * @package Caaguazu_Locales
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * `tipo` como query var pública, para poder filtrar el archivo de
 * `cgz_local` por URL (?tipo=restaurante) sin depender de una taxonomía
 * (acá `tipo` es post meta, no taxonomía — ver cgz_local_types()).
 */
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'tipo';
	return $vars;
} );

add_action( 'pre_get_posts', function ( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'cgz_local' ) ) {
		return;
	}
	$tipo = $query->get( 'tipo' );
	if ( ! $tipo || ! array_key_exists( $tipo, cgz_local_types() ) ) {
		return;
	}
	$query->set( 'meta_query', array(
		array(
			'key'   => '_cgz_tipo',
			'value' => sanitize_key( $tipo ),
		),
	) );
} );

/**
 * URL al archivo de locales, opcionalmente filtrado por tipo.
 */
function cgz_local_archive_url( $tipo = '' ) {
	$url = get_post_type_archive_link( 'cgz_local' );
	if ( ! $url ) {
		return home_url( '/local/' );
	}
	return $tipo ? add_query_arg( 'tipo', $tipo, $url ) : $url;
}

/**
 * Dropdown de "Dónde ir": un link por tipo de local + "Ver todos".
 */
function cgz_render_shell_dropdown() {
	echo '<div class="nav-dropdown"><div class="nav-dropdown-col">';
	foreach ( cgz_local_types() as $slug => $label ) {
		printf( '<a class="nav-dropdown-link" href="%s">%s</a>', esc_url( cgz_local_archive_url( $slug ) ), esc_html( $label ) );
	}
	printf( '<a class="nav-dropdown-link" href="%s">%s</a>', esc_url( cgz_local_archive_url() ), esc_html__( 'Ver todos', 'caaguazu-locales' ) );
	echo '</div></div>';
}

add_filter( 'caaguazu_tourism_shell_items', function ( $items ) {
	$items[] = array(
		'slug'        => 'donde-ir',
		'label'       => __( 'Dónde ir', 'caaguazu-locales' ),
		'icon'        => 'pin',
		'url'         => cgz_local_archive_url(),
		'dropdown_cb' => 'cgz_render_shell_dropdown',
	);
	return $items;
} );
