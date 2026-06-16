<?php
/**
 * Walkers personalizados para reproducir el estilo de menú del sitio original.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Walker para el menú principal en desktop.
 */
class Caaguazu_Primary_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$class = 'text-sm font-display font-medium text-snow/85 hover:text-snow transition-colors';
		$output .= sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $item->url ),
			esc_attr( $class ),
			esc_html( $item->title )
		);
	}

	public function end_el( &$output, $item, $depth = 0, $args = null ) {}
}

/**
 * Walker para el menú móvil.
 */
class Caaguazu_Mobile_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}

	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$output .= sprintf(
			'<li><a href="%s" class="block py-2 text-snow/90 hover:text-snow font-display font-medium">%s</a></li>',
			esc_url( $item->url ),
			esc_html( $item->title )
		);
	}

	public function end_el( &$output, $item, $depth = 0, $args = null ) {}
}
