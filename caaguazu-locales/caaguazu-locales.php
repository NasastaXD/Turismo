<?php
/**
 * Plugin Name:       Caaguazú Locales
 * Plugin URI:        https://turismo.caaguazu.net
 * Description:       Locales turísticos para Caaguazú: reservas por WhatsApp (restaurantes y hoteles), mapa interactivo con edición manual de marcadores, sistema de cuentas con reseñas (fotos, comentarios, votos y respuestas) y panel para dueños de local. Incluye autoactualización vía WordPress.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Municipalidad de Caaguazú
 * Author URI:        https://caaguazu.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       caaguazu-locales
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CGZ_LOC_VERSION', '1.1.0' );
define( 'CGZ_LOC_FILE', __FILE__ );
define( 'CGZ_LOC_DIR', plugin_dir_path( __FILE__ ) );
define( 'CGZ_LOC_URI', plugin_dir_url( __FILE__ ) );
define( 'CGZ_LOC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Email de contacto usado para solicitudes de cuenta de dueño de local.
 * Filtrable por si cambia el dominio.
 */
function cgz_contact_email() {
	return apply_filters( 'cgz_contact_email', 'contacto@caaguazu.net' );
}

require_once CGZ_LOC_DIR . 'includes/helpers.php';
require_once CGZ_LOC_DIR . 'includes/class-install.php';
require_once CGZ_LOC_DIR . 'includes/class-cpt.php';
require_once CGZ_LOC_DIR . 'includes/class-accounts.php';
require_once CGZ_LOC_DIR . 'includes/class-reviews.php';
require_once CGZ_LOC_DIR . 'includes/class-booking.php';
require_once CGZ_LOC_DIR . 'includes/class-shortcodes.php';
require_once CGZ_LOC_DIR . 'includes/class-ajax.php';
require_once CGZ_LOC_DIR . 'includes/class-admin.php';
require_once CGZ_LOC_DIR . 'includes/class-updater.php';
require_once CGZ_LOC_DIR . 'includes/nav-integration.php';

/**
 * Arranque del plugin.
 */
function cgz_loc_boot() {
	CGZ_CPT::instance();
	CGZ_Accounts::instance();
	CGZ_Reviews::instance();
	CGZ_Booking::instance();
	CGZ_Shortcodes::instance();
	CGZ_Ajax::instance();

	if ( is_admin() ) {
		CGZ_Admin::instance();
	}

	// Autoactualización del propio plugin desde un manifiesto JSON remoto.
	new CGZ_Updater( array(
		'type'         => 'plugin',
		'slug'         => 'caaguazu-locales',
		'basename'     => CGZ_LOC_BASENAME,
		'version'      => CGZ_LOC_VERSION,
		'metadata_url' => apply_filters(
			'cgz_plugin_update_manifest',
			'https://raw.githubusercontent.com/nasastaxd/turismo/main/updates/caaguazu-locales.json'
		),
	) );
}
add_action( 'plugins_loaded', 'cgz_loc_boot' );

/**
 * Carga de traducciones.
 */
function cgz_loc_load_textdomain() {
	load_plugin_textdomain( 'caaguazu-locales', false, dirname( CGZ_LOC_BASENAME ) . '/languages' );
}
add_action( 'init', 'cgz_loc_load_textdomain' );

/**
 * Assets de frontend.
 */
function cgz_loc_frontend_assets() {
	wp_register_style(
		'cgz-loc',
		CGZ_LOC_URI . 'assets/css/caaguazu-locales.css',
		array(),
		CGZ_LOC_VERSION
	);
	wp_register_script(
		'cgz-loc-frontend',
		CGZ_LOC_URI . 'assets/js/frontend.js',
		array(),
		CGZ_LOC_VERSION,
		true
	);

	wp_localize_script( 'cgz-loc-frontend', 'CGZ_LOC', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'cgz_loc' ),
		'isUser'  => is_user_logged_in(),
		'i18n'    => array(
			'sending'      => __( 'Enviando…', 'caaguazu-locales' ),
			'error'        => __( 'Ocurrió un error. Intentá de nuevo.', 'caaguazu-locales' ),
			'loginNeeded'  => __( 'Iniciá sesión para participar.', 'caaguazu-locales' ),
			'confirmDelete'=> __( '¿Eliminar esto?', 'caaguazu-locales' ),
		),
	) );

	// Estos siempre se registran; los shortcodes hacen wp_enqueue_* según los necesiten.
	wp_enqueue_style( 'cgz-loc' );
	wp_enqueue_script( 'cgz-loc-frontend' );
}
add_action( 'wp_enqueue_scripts', 'cgz_loc_frontend_assets' );

/**
 * Plantilla del CPT `local` servida desde el plugin si el tema no la define.
 */
function cgz_loc_single_template( $template ) {
	if ( is_singular( 'cgz_local' ) ) {
		$theme_template = locate_template( array( 'single-cgz_local.php' ) );
		if ( $theme_template ) {
			return $theme_template;
		}
		return CGZ_LOC_DIR . 'includes/template-single-local.php';
	}
	return $template;
}
add_filter( 'template_include', 'cgz_loc_single_template' );

/* -------------------------------------------------------------------------
 * Activación / desactivación
 * ---------------------------------------------------------------------- */
register_activation_hook( __FILE__, array( 'CGZ_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CGZ_Install', 'deactivate' ) );
