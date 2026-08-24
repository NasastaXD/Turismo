<?php
/**
 * Plugin Name:       Caaguazú SSO CEAD
 * Plugin URI:        https://turismo.caaguazu.net
 * Description:       Acceso de un clic desde el panel del CEAD (curso de Servicios Turísticos) al Portal de Promotores Turísticos: el CEAD afirma quién es la persona, este plugin decide qué cuenta y qué rol le corresponden acá. No crea usuarios de WordPress ni usa su cookie — todo corre sobre el sistema de cuentas universal (caaguazu-cuentas).
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  caaguazu-cuentas, caaguazu-portal
 * Author:            Municipalidad de Caaguazú
 * Author URI:        https://caaguazu.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       caaguazu-sso-cead
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CEADSSO_VERSION', '1.0.0' );
define( 'CEADSSO_FILE', __FILE__ );
define( 'CEADSSO_DIR', plugin_dir_path( __FILE__ ) );
define( 'CEADSSO_BASENAME', plugin_basename( __FILE__ ) );

require_once CEADSSO_DIR . 'includes/class-install.php';
require_once CEADSSO_DIR . 'includes/class-log.php';
require_once CEADSSO_DIR . 'includes/class-redeem.php';
require_once CEADSSO_DIR . 'includes/class-link.php';
require_once CEADSSO_DIR . 'includes/class-router.php';
require_once CEADSSO_DIR . 'includes/class-admin.php';

/**
 * Aviso en wp-admin si falta alguna de las dos dependencias duras, o si
 * faltan las constantes de configuración en wp-config.php.
 */
function ceadsso_admin_notices() {
	if ( ! ceadsso_deps_active() ) {
		echo '<div class="notice notice-error"><p>' .
			esc_html__( 'Caaguazú SSO CEAD necesita "Caaguazú Cuentas" y "Caaguazú Portal" activos para funcionar.', 'caaguazu-sso-cead' ) .
			'</p></div>';
		return;
	}
	if ( ! CEADSSO_Redeem::configured() ) {
		echo '<div class="notice notice-warning"><p>' .
			esc_html__( 'Caaguazú SSO CEAD: faltan CEAD_TUR_SSO_SECRET y/o CEAD_TUR_SSO_URL en wp-config.php. Ver Herramientas → Vincular cuenta CEAD.', 'caaguazu-sso-cead' ) .
			'</p></div>';
	}
}
add_action( 'admin_notices', 'ceadsso_admin_notices' );

/**
 * Arranque del plugin. En plugins_loaded, después de que caaguazu-cuentas
 * (prioridad 5) y caaguazu-portal (prioridad por defecto) ya registraron su
 * API — el router y el admin de este plugin dependen de ambas.
 */
function ceadsso_boot() {
	CEADSSO_Router::instance();
	if ( is_admin() ) {
		CEADSSO_Admin::instance();
	}
}
add_action( 'plugins_loaded', 'ceadsso_boot', 20 );

/**
 * Auto re-flush de rewrite rules si cambió la versión (upgrades sin
 * re-activar) — mismo patrón que caaguazu-portal.
 */
function ceadsso_maybe_flush_rewrite_rules() {
	if ( get_option( 'caaguazu_sso_cead_version' ) === CEADSSO_VERSION ) {
		return;
	}
	CEADSSO_Router::add_rewrite_rules();
	flush_rewrite_rules();
	update_option( 'caaguazu_sso_cead_version', CEADSSO_VERSION );
}
add_action( 'admin_init', 'ceadsso_maybe_flush_rewrite_rules' );

register_activation_hook( __FILE__, array( 'CEADSSO_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEADSSO_Install', 'deactivate' ) );
