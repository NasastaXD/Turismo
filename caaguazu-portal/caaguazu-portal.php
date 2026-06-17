<?php
/**
 * Plugin Name:       Caaguazú Portal — Promotores Turísticos
 * Plugin URI:        https://turismo.caaguazu.net
 * Description:       Panel autenticado tipo app (sidebar + topbar + contenido, instalable como PWA) sobre rutas propias, con flujo editorial borrador → revisión → publicación para el Portal de Promotores Turísticos. Hereda los colores del sitio vía tokens CSS.
 * Version:           1.1.0
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

define( 'PROMOTUR_VERSION', '1.1.0' );
define( 'PROMOTUR_DB_VERSION', 2 ); // se incrementa cuando cambia la estructura de datos.
define( 'PROMOTUR_FILE', __FILE__ );
define( 'PROMOTUR_DIR', plugin_dir_path( __FILE__ ) );
define( 'PROMOTUR_URI', plugin_dir_url( __FILE__ ) );
define( 'PROMOTUR_BASENAME', plugin_basename( __FILE__ ) );

/** Repositorio público desde el que se publican los releases (auto-update). */
define( 'PROMOTUR_REPO', 'https://github.com/nasastaxd/turismo/' );

require_once PROMOTUR_DIR . 'includes/helpers.php';
require_once PROMOTUR_DIR . 'includes/class-roles.php';
require_once PROMOTUR_DIR . 'includes/class-install.php';
require_once PROMOTUR_DIR . 'includes/class-router.php';
require_once PROMOTUR_DIR . 'includes/class-shell.php';
require_once PROMOTUR_DIR . 'includes/class-assets.php';
require_once PROMOTUR_DIR . 'includes/class-pwa.php';
require_once PROMOTUR_DIR . 'includes/class-invitations.php';
require_once PROMOTUR_DIR . 'includes/class-audit.php';
require_once PROMOTUR_DIR . 'includes/class-auth.php';
require_once PROMOTUR_DIR . 'includes/class-admin.php';
require_once PROMOTUR_DIR . 'includes/class-notifications.php';
require_once PROMOTUR_DIR . 'includes/class-destinos.php';
require_once PROMOTUR_DIR . 'includes/class-editorial.php';
require_once PROMOTUR_DIR . 'includes/class-ajax.php';
require_once PROMOTUR_DIR . 'includes/class-resenas.php';
require_once PROMOTUR_DIR . 'includes/class-consultas.php';
require_once PROMOTUR_DIR . 'includes/class-seo.php';
require_once PROMOTUR_DIR . 'includes/class-public.php';
require_once PROMOTUR_DIR . 'includes/class-public-ajax.php';
require_once PROMOTUR_DIR . 'includes/class-curaduria.php';
require_once PROMOTUR_DIR . 'includes/class-tareas.php';
require_once PROMOTUR_DIR . 'includes/class-stats.php';
require_once PROMOTUR_DIR . 'includes/class-gestion-ajax.php';
require_once PROMOTUR_DIR . 'includes/class-i18n.php';

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
	PROMOTUR_Resenas::instance();
	PROMOTUR_Consultas::instance();
	PROMOTUR_SEO::instance();
	PROMOTUR_Public::instance();
	PROMOTUR_Public_Ajax::instance();
	PROMOTUR_Curaduria::instance();
	PROMOTUR_Tareas::instance();
	PROMOTUR_Stats::instance();
	PROMOTUR_Gestion_Ajax::instance();
	PROMOTUR_I18n::instance();
	PROMOTUR_Audit::instance();
	if ( is_admin() ) {
		PROMOTUR_Admin::instance();
	}

	// Auto re-sync al cambiar la versión (los hooks de activación NO corren en un update de plugin).
	if ( get_option( 'promotur_version' ) !== PROMOTUR_VERSION ) {
		PROMOTUR_Roles::install();        // re-otorga caps nuevas (p.ej. promotur_manage_users) al admin.
		PROMOTUR_Router::add_rewrite_rules();
		flush_rewrite_rules();
		update_option( 'promotur_version', PROMOTUR_VERSION );
	}

	promotur_init_updater();
}
add_action( 'plugins_loaded', 'promotur_boot' );

/**
 * Traducciones.
 */
function promotur_load_textdomain() {
	load_plugin_textdomain( 'caaguazu-portal', false, dirname( PROMOTUR_BASENAME ) . '/languages' );
}
add_action( 'init', 'promotur_load_textdomain' );

/**
 * Token de GitHub para el auto-updater (repos privados / límite de rate más alto).
 * La constante PROMOTUR_GITHUB_TOKEN (wp-config.php) tiene prioridad sobre la opción
 * editable desde wp-admin → Portal Turismo → Actualizaciones.
 *
 * @return string Token, o cadena vacía si no hay ninguno configurado.
 */
function promotur_github_token() {
	if ( defined( 'PROMOTUR_GITHUB_TOKEN' ) && PROMOTUR_GITHUB_TOKEN ) {
		return (string) PROMOTUR_GITHUB_TOKEN;
	}
	return (string) get_option( 'promotur_github_token', '' );
}

/**
 * Accesor a la instancia del auto-updater (plugin-update-checker).
 * La página de Actualizaciones la usa para consultar versión/estado y forzar comprobaciones.
 *
 * @return \YahnisElsts\PluginUpdateChecker\v5p6\Plugin\UpdateChecker|null
 */
function promotur_updater() {
	static $updater = null;
	static $built   = false;

	if ( $built ) {
		return $updater;
	}
	$built = true;

	$loader = PROMOTUR_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $loader ) ) {
		return null;
	}
	require_once $loader;
	if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		return null;
	}

	$updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		PROMOTUR_REPO,
		PROMOTUR_FILE,
		'caaguazu-portal'
	);

	// Usar el .zip adjunto al release (no el zip del código fuente del repo).
	$api = method_exists( $updater, 'getVcsApi' ) ? $updater->getVcsApi() : null;
	if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
		$api->enableReleaseAssets( '/caaguazu-portal\.zip$/i' );
	}

	$token = promotur_github_token();
	if ( $token ) {
		$updater->setAuthentication( $token );
	}

	return $updater;
}

/**
 * Auto-updater desde GitHub Releases (plugin-update-checker vendoreado).
 * Descarga el asset caaguazu-portal.zip adjunto al release más reciente.
 */
function promotur_init_updater() {
	promotur_updater();
}

/**
 * Migraciones de base de datos acumulativas. Se ejecutan al activar y al detectar
 * un cambio de PROMOTUR_DB_VERSION en admin_init (sin intervención manual).
 *
 * @param int $from versión de DB instalada actualmente
 */
function promotur_run_migrations( $from ) {
	// v2: tablas de invitaciones y auditoría (invite-only + logs).
	if ( $from < 2 ) {
		PROMOTUR_Install::create_tables();
	}
	do_action( 'promotur_run_migrations', $from );
}

/**
 * Detecta un salto de versión de DB y corre las migraciones pendientes.
 */
function promotur_maybe_upgrade() {
	$installed = (int) get_option( 'promotur_db_version', 0 );
	if ( $installed < PROMOTUR_DB_VERSION ) {
		promotur_run_migrations( $installed );
		update_option( 'promotur_db_version', PROMOTUR_DB_VERSION );
	}
}
add_action( 'admin_init', 'promotur_maybe_upgrade' );

register_activation_hook( __FILE__, array( 'PROMOTUR_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PROMOTUR_Install', 'deactivate' ) );
