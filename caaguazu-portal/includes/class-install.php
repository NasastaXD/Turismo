<?php
/**
 * Activación / desactivación: roles, capabilities y rewrite rules.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Install {

	/**
	 * Activación del plugin.
	 */
	public static function activate() {
		PROMOTUR_Roles::install();
		// El CPT y las reglas deben existir antes del flush.
		PROMOTUR_Destinos::register_post_type();
		PROMOTUR_Router::add_rewrite_rules();
		flush_rewrite_rules();
		update_option( 'promotur_version', PROMOTUR_VERSION );
		// Marca la DB como actualizada a la versión actual (migraciones acumulativas después).
		if ( false === get_option( 'promotur_db_version', false ) ) {
			update_option( 'promotur_db_version', PROMOTUR_DB_VERSION );
		}
	}

	/**
	 * Desactivación: solo limpia las rewrite rules (no toca roles ni datos).
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
