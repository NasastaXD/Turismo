<?php
/**
 * Activación/desactivación y estructura de datos propia.
 *
 * Dos tablas, deliberadamente separadas de `caaguazu_accounts`:
 *
 *   - caaguazu_sso_cead_links : mapa account_id ↔ cead_uid (1 a 1). Es la
 *     llave estable que evita depender del email para reencontrar la misma
 *     cuenta (ver class-link.php) — una búsqueda indexada, en vez de mirar
 *     dentro del JSON de `metadata` de la cuenta.
 *   - caaguazu_sso_cead_log   : auditoría de cada intento de canje (éxito,
 *     rechazo o error), para que un admin vea sin adivinar por qué alguien
 *     no pudo entrar (típicamente: email que ya existe sin vincular, rol que
 *     no reconocemos, o el canje falló del lado del CEAD).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CEADSSO_Install {

	/**
	 * Nombres de tabla (con el prefijo del sitio).
	 *
	 * @return array{links:string,log:string}
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'links' => $wpdb->prefix . 'caaguazu_sso_cead_links',
			'log'   => $wpdb->prefix . 'caaguazu_sso_cead_log',
		);
	}

	/**
	 * Activación del plugin.
	 */
	public static function activate() {
		self::create_tables();
		update_option( 'caaguazu_sso_cead_version', CEADSSO_VERSION );
	}

	/**
	 * Crea/actualiza las tablas custom. Idempotente (dbDelta).
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		dbDelta( "CREATE TABLE {$t['links']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			account_id BIGINT(20) UNSIGNED NOT NULL,
			cead_uid BIGINT(20) UNSIGNED NOT NULL,
			linked_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY account_id (account_id),
			UNIQUE KEY cead_uid (cead_uid)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$t['log']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			cead_uid BIGINT(20) UNSIGNED NULL,
			email VARCHAR(190) NULL,
			rol_cead VARCHAR(60) NULL,
			resultado VARCHAR(20) NOT NULL,
			motivo VARCHAR(60) NULL,
			account_id BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY resultado (resultado),
			KEY created_at (created_at)
		) {$charset};" );
	}

	/**
	 * Desactivación: no borra datos a propósito (mismo criterio que
	 * caaguazu-cuentas — los vínculos y la auditoría deben sobrevivir a una
	 * desactivación accidental).
	 */
	public static function deactivate() {}
}
