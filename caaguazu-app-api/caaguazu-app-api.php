<?php
/**
 * Plugin Name:       Caaguazú App API
 * Plugin URI:        https://caaguazu.net
 * Description:       Capa REST que consume la app Android (Turismo App Czu). Expone el contenido turístico y la identidad del ecosistema bajo /wp-json/czu-app/v1/, sin depender del theme ni del sitio público — la app sigue funcionando aunque la web se rehaga entera.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  caaguazu-cuentas, caaguazu-portal
 * Author:            Municipalidad de Caaguazú
 * Author URI:        https://caaguazu.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       caaguazu-app-api
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ ES UN PLUGIN APARTE
 *
 * La app es un cliente más del mismo backend, pero deliberadamente NO comparte
 * ciclo de vida con el sitio público: el theme y las páginas de caaguazu.net
 * van a rehacerse, y ese trabajo no debe poder romper la API de una app ya
 * publicada en la tienda. Por eso esta capa:
 *
 *   - No usa el theme ni sus helpers. Nada de locate_template(), get_header(),
 *     ni de los filtros de nav/shell. Si el theme desaparece, la API sigue.
 *   - No renderiza HTML propio. Solo JSON.
 *   - Lee el contenido de donde ya vive (caaguazu-portal) en vez de duplicarlo.
 *
 * Lo que sí aporta de nuevo son las entidades que el panel todavía no tenía
 * (Evento, Recorrido, Artículo) y los campos que la app necesita y no existían
 * (rango de precio, artículos relacionados, icono/color por categoría).
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CZUAPI_VERSION', '0.1.0' );
define( 'CZUAPI_FILE', __FILE__ );
define( 'CZUAPI_DIR', plugin_dir_path( __FILE__ ) );
define( 'CZUAPI_BASENAME', plugin_basename( __FILE__ ) );

/** Namespace REST. Versionado: un cambio incompatible sube a v2 y v1 sigue viva. */
define( 'CZUAPI_NS', 'czu-app/v1' );

require_once CZUAPI_DIR . 'includes/class-install.php';
require_once CZUAPI_DIR . 'includes/helpers.php';
require_once CZUAPI_DIR . 'includes/class-response.php';
require_once CZUAPI_DIR . 'includes/class-auth.php';
require_once CZUAPI_DIR . 'includes/class-taxonomias.php';
require_once CZUAPI_DIR . 'includes/class-inventario.php';
require_once CZUAPI_DIR . 'includes/class-eventos.php';
require_once CZUAPI_DIR . 'includes/class-articulos.php';
require_once CZUAPI_DIR . 'includes/class-recorridos.php';
require_once CZUAPI_DIR . 'includes/class-ui-content.php';
require_once CZUAPI_DIR . 'includes/class-sync.php';

/**
 * ¿Están las dependencias duras? La API no puede resolver identidad sin
 * caaguazu-cuentas ni contenido sin caaguazu-portal.
 *
 * @return bool
 */
function czuapi_deps_active() {
	return function_exists( 'caaguazu_account_can' )
		&& class_exists( 'Caaguazu_Cuentas_Accounts' )
		&& class_exists( 'PROMOTUR_Destinos' );
}

/**
 * Aviso en wp-admin si falta alguna dependencia.
 */
function czuapi_missing_deps_notice() {
	if ( czuapi_deps_active() ) { return; }
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Caaguazú App API necesita "Caaguazú Cuentas" y "Caaguazú Portal" activos. Mientras falte alguno, la API de la app no responde.', 'caaguazu-app-api' ) .
		'</p></div>';
}
add_action( 'admin_notices', 'czuapi_missing_deps_notice' );

/**
 * Arranque. Prioridad 20: después de que caaguazu-cuentas (5) y
 * caaguazu-portal (10) registraron su API y sus CPTs.
 */
function czuapi_boot() {
	if ( ! czuapi_deps_active() ) {
		return;
	}

	// CPTs propios: se registran siempre (no solo en REST) para que el panel
	// y wp-admin puedan verlos.
	CZUAPI_Eventos::instance();
	CZUAPI_Articulos::instance();
	CZUAPI_Recorridos::instance();
	CZUAPI_Taxonomias::instance();
	CZUAPI_Sync::instance();

	add_action( 'rest_api_init', 'czuapi_register_routes' );
}
add_action( 'plugins_loaded', 'czuapi_boot', 20 );

/**
 * Registro de todas las rutas. Un solo punto para poder auditar la superficie
 * pública de un vistazo.
 */
function czuapi_register_routes() {
	CZUAPI_Auth::instance()->register_routes();
	CZUAPI_Taxonomias::instance()->register_routes();
	CZUAPI_Inventario::instance()->register_routes();
	CZUAPI_Eventos::instance()->register_routes();
	CZUAPI_Articulos::instance()->register_routes();
	CZUAPI_Recorridos::instance()->register_routes();
	CZUAPI_UI_Content::instance()->register_routes();
	CZUAPI_Sync::instance()->register_routes();
}

/**
 * Migración de tablas al detectar cambio de versión (sin re-activar).
 */
function czuapi_maybe_upgrade() {
	if ( get_option( 'czuapi_version' ) === CZUAPI_VERSION ) {
		return;
	}
	CZUAPI_Install::create_tables();
	update_option( 'czuapi_version', CZUAPI_VERSION );
}
add_action( 'admin_init', 'czuapi_maybe_upgrade' );

register_activation_hook( __FILE__, array( 'CZUAPI_Install', 'activate' ) );
