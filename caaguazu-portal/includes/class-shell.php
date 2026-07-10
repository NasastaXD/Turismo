<?php
/**
 * Shell del panel: resuelve la sección, valida la capability y ejecuta
 * el contrato de página ($page_title + $body + include shell.php).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Shell {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Renderiza una sección del panel.
	 *
	 * @param string      $section
	 * @param string|null $id  segmento opcional (vista de detalle)
	 */
	public function render( $section, $id = null ) {
		$sections = PROMOTUR_Roles::sections();

		// Sección desconocida → 404 dentro del shell.
		if ( ! isset( $sections[ $section ] ) ) {
			$GLOBALS['promotur_section'] = '404';
			promotur_template( 'sections/404', array( 'promotur_id' => $id ) );
			return;
		}

		// Capability de la sección.
		$cap = $sections[ $section ];
		if ( ! caaguazu_account_can( 'promotor', $cap ) ) {
			wp_die(
				esc_html__( 'No tenés permiso para ver esta sección.', 'caaguazu-portal' ),
				esc_html__( 'Acceso denegado', 'caaguazu-portal' ),
				array( 'response' => 403 )
			);
		}

		$GLOBALS['promotur_section'] = $section;
		promotur_template( 'sections/' . $section, array( 'promotur_id' => $id ) );
	}
}
