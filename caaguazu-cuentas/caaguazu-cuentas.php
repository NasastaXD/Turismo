<?php
/**
 * Plugin Name:       Caaguazú Cuentas — Sistema de cuentas universal
 * Plugin URI:        https://turismo.caaguazu.net
 * Description:       Sistema de cuentas universal, propio y separado de los usuarios de WordPress (para maximizar seguridad). Provee identidad (email + contraseña), sesión propia firmada y un modelo de permisos por panel — el Promotor turístico hoy, y cualquier panel futuro con permisos especiales. Otros plugins consumen su API pública (caaguazu_current_account(), caaguazu_account_can()).
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Municipalidad de Caaguazú
 * Author URI:        https://caaguazu.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       caaguazu-cuentas
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CAAGUAZU_CUENTAS_VERSION', '0.1.0' );
define( 'CAAGUAZU_CUENTAS_DB_VERSION', 1 ); // se incrementa cuando cambia la estructura de datos.
define( 'CAAGUAZU_CUENTAS_FILE', __FILE__ );
define( 'CAAGUAZU_CUENTAS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAAGUAZU_CUENTAS_URI', plugin_dir_url( __FILE__ ) );
define( 'CAAGUAZU_CUENTAS_BASENAME', plugin_basename( __FILE__ ) );

require_once CAAGUAZU_CUENTAS_DIR . 'includes/class-install.php';
require_once CAAGUAZU_CUENTAS_DIR . 'includes/class-passwords.php';
require_once CAAGUAZU_CUENTAS_DIR . 'includes/class-accounts.php';
require_once CAAGUAZU_CUENTAS_DIR . 'includes/class-sessions.php';
require_once CAAGUAZU_CUENTAS_DIR . 'includes/class-panels.php';
require_once CAAGUAZU_CUENTAS_DIR . 'includes/class-auth.php';
require_once CAAGUAZU_CUENTAS_DIR . 'includes/class-migration.php';
require_once CAAGUAZU_CUENTAS_DIR . 'includes/api.php';

/**
 * Arranque del plugin. Se instancia temprano (plugins_loaded, prioridad baja)
 * para que la sesión propia esté resuelta antes de que otros plugins —el
 * Portal, futuros paneles— pregunten por la cuenta actual.
 */
function caaguazu_cuentas_boot() {
	Caaguazu_Cuentas_Sessions::instance();  // resuelve la cookie de sesión propia
	Caaguazu_Cuentas_Auth::instance();
	Caaguazu_Cuentas_Panels::instance();
}
add_action( 'plugins_loaded', 'caaguazu_cuentas_boot', 5 );

/**
 * Traducciones.
 */
function caaguazu_cuentas_load_textdomain() {
	load_plugin_textdomain( 'caaguazu-cuentas', false, dirname( CAAGUAZU_CUENTAS_BASENAME ) . '/languages' );
}
add_action( 'init', 'caaguazu_cuentas_load_textdomain' );

/**
 * Migraciones de base de datos acumulativas. Corren al activar y al detectar
 * un salto de CAAGUAZU_CUENTAS_DB_VERSION en admin_init (sin intervención
 * manual), mismo patrón que el resto de los plugins del ecosistema.
 *
 * @param int $from versión de DB instalada actualmente
 */
function caaguazu_cuentas_run_migrations( $from ) {
	if ( $from < 1 ) {
		Caaguazu_Cuentas_Install::create_tables();
	}
	do_action( 'caaguazu_cuentas_run_migrations', $from );
}

/**
 * Detecta un salto de versión de DB y corre las migraciones pendientes.
 * Además dispara —una sola vez— la migración de los promotores que ya
 * existían como usuarios de WordPress hacia el sistema de cuentas propio.
 */
function caaguazu_cuentas_maybe_upgrade() {
	$installed = (int) get_option( 'caaguazu_cuentas_db_version', 0 );
	if ( $installed < CAAGUAZU_CUENTAS_DB_VERSION ) {
		caaguazu_cuentas_run_migrations( $installed );
		update_option( 'caaguazu_cuentas_db_version', CAAGUAZU_CUENTAS_DB_VERSION );
	}
	// Migración de cuentas legadas (idempotente, se autolimita con un flag).
	Caaguazu_Cuentas_Migration::maybe_run();
}
add_action( 'admin_init', 'caaguazu_cuentas_maybe_upgrade' );

register_activation_hook( __FILE__, array( 'Caaguazu_Cuentas_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Caaguazu_Cuentas_Install', 'deactivate' ) );
