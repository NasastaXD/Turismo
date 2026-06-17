<?php
/**
 * Plugin Name:       Caaguazú Portal — Promotores Turísticos
 * Plugin URI:        https://turismo.caaguazu.net
 * Description:       Panel autenticado tipo app (sidebar + topbar + contenido, instalable como PWA) sobre rutas propias, con flujo editorial borrador → revisión → publicación para el Portal de Promotores Turísticos. Hereda los colores del sitio vía tokens CSS.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Municipalidad de Caaguazú
 * Author URI:        https://caaguazu.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       caaguazu-portal
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'PROMOTUR_VERSION', '1.0.0' );
define( 'PROMOTUR_FILE', __FILE__ );
define( 'PROMOTUR_DIR', plugin_dir_path( __FILE__ ) );
define( 'PROMOTUR_URI', plugin_dir_url( __FILE__ ) );
define( 'PROMOTUR_BASENAME', plugin_basename( __FILE__ ) );

require_once PROMOTUR_DIR . 'includes/helpers.php';
require_once PROMOTUR_DIR . 'includes/class-roles.php';
require_once PROMOTUR_DIR . 'includes/class-install.php';
require_once PROMOTUR_DIR . 'includes/class-router.php';
require_once PROMOTUR_DIR . 'includes/class-shell.php';
require_once PROMOTUR_DIR . 'includes/class-assets.php';
require_once PROMOTUR_DIR . 'includes/class-pwa.php';
require_once PROMOTUR_DIR . 'includes/class-auth.php';
require_once PROMOTUR_DIR . 'includes/class-notifications.php';
require_once PROMOTUR_DIR . 'includes/class-destinos.php';
require_once PROMOTUR_DIR . 'includes/class-editorial.php';
require_once PROMOTUR_DIR . 'includes/class-ajax.php';
require_once PROMOTUR_DIR . 'includes/class-public.php';

/**
 * Arranque del plugin.
 */
function promotur_boot() {
	PROMOTUR_Roles::instance();
	PROMOTUR_Router::instance();
	PROMOTUR_Shell::instance();
	PROMOTUR_Assets::instance();
	PROMOTUR_PWA::instance();
	PROMOTUR_Auth::instance();
	PROMOTUR_Notifications::instance();
	PROMOTUR_Destinos::instance();
	PROMOTUR_Editorial::instance();
	PROMOTUR_Ajax::instance();
	PROMOTUR_Public::instance();

	// Auto re-flush de rewrite rules si cambió la versión (upgrades sin re-activar).
	if ( get_option( 'promotur_version' ) !== PROMOTUR_VERSION ) {
		PROMOTUR_Router::add_rewrite_rules();
		flush_rewrite_rules();
		update_option( 'promotur_version', PROMOTUR_VERSION );
	}
}
add_action( 'plugins_loaded', 'promotur_boot' );

/**
 * Traducciones.
 */
function promotur_load_textdomain() {
	load_plugin_textdomain( 'caaguazu-portal', false, dirname( PROMOTUR_BASENAME ) . '/languages' );
}
add_action( 'init', 'promotur_load_textdomain' );

register_activation_hook( __FILE__, array( 'PROMOTUR_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PROMOTUR_Install', 'deactivate' ) );
