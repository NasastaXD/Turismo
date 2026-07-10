<?php
/**
 * Integración con el shell propio de Turismo que expone el theme Caaguazú
 * (`caaguazu_tourism_shell_items`, ver caaguazu-theme/inc/tourism-shell.php
 * y caaguazu-turismo/includes/nav-integration.php en el repo del portal
 * principal). El theme no sabe nada de Portal — solo pinta lo que este
 * plugin le pasa por el filtro:
 *  - "Destinos", con un desplegable de las categorías reales de destino
 *    (taxonomía `promotur_categoria`, editable en Categorías de destino).
 *  - "Panel de promotor", solo si el usuario logueado tiene el permiso
 *    real del panel (`promotur_view_panel`) — el mismo check que usa el
 *    guard de PROMOTUR_Router, para no mostrar nunca un link que después
 *    devuelva 403.
 *
 * @package Caaguazu_Portal
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dropdown de "Destinos": una categoría por término real de
 * `promotur_categoria` (con al menos un destino publicado) + "Ver todos".
 */
function promotur_render_shell_dropdown() {
	$terms = get_terms( array(
		'taxonomy'   => 'promotur_categoria',
		'hide_empty' => true,
	) );

	echo '<div class="nav-dropdown"><div class="nav-dropdown-col">';
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			printf( '<a class="nav-dropdown-link" href="%s">%s</a>', esc_url( get_term_link( $term ) ), esc_html( $term->name ) );
		}
	}
	printf(
		'<a class="nav-dropdown-link" href="%s">%s</a>',
		esc_url( get_post_type_archive_link( PROMOTUR_Destinos::CPT ) ),
		esc_html__( 'Ver todos', 'caaguazu-portal' )
	);
	echo '</div></div>';
}

add_filter( 'caaguazu_tourism_shell_items', function ( $items ) {
	$items[] = array(
		'slug'        => 'destinos',
		'label'       => __( 'Destinos', 'caaguazu-portal' ),
		'icon'        => 'map',
		'url'         => get_post_type_archive_link( PROMOTUR_Destinos::CPT ),
		'dropdown_cb' => 'promotur_render_shell_dropdown',
	);

	if ( caaguazu_account_can( 'promotor', 'promotur_view_panel' ) ) {
		$items[] = array(
			'slug'  => 'panel',
			'label' => __( 'Panel de promotor', 'caaguazu-portal' ),
			'icon'  => 'user',
			'url'   => promotur_url( 'panel' ),
		);
	}

	return $items;
} );
