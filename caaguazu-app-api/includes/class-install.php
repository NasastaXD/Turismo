<?php
/**
 * Tablas propias de la capa de API.
 *
 * Dos, y ninguna duplica contenido: el contenido vive donde ya vivía.
 *
 *   - caaguazu_app_tokens    : tokens bearer de la app. Tabla propia y no la
 *     de sesiones de caaguazu-cuentas, a propósito — una sesión de navegador y
 *     un token de teléfono tienen ciclos de vida distintos (cerrar sesión en
 *     la web no debe desloguear el celular), y así esta capa no escribe en la
 *     tabla de otro plugin. Misma disciplina que allá: se guarda SOLO el hash
 *     SHA-256 del token, nunca el token en claro.
 *
 *   - caaguazu_app_tombstones: qué dejó de estar visible y cuándo. Sin esto,
 *     /sync solo puede informar altas y cambios, y una ficha despublicada
 *     queda para siempre en la caché del teléfono (ver class-sync.php).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_Install {

	/**
	 * @return array{tokens:string,tombstones:string}
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'tokens'     => $wpdb->prefix . 'caaguazu_app_tokens',
			'tombstones' => $wpdb->prefix . 'caaguazu_app_tombstones',
		);
	}

	public static function activate() {
		self::create_tables();
		update_option( 'czuapi_version', CZUAPI_VERSION );
		flush_rewrite_rules();
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		dbDelta( "CREATE TABLE {$t['tokens']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			account_id BIGINT(20) UNSIGNED NOT NULL,
			token_hash CHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			last_seen_at DATETIME NULL,
			device VARCHAR(255) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY account_id (account_id),
			KEY expires_at (expires_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$t['tombstones']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			tipo VARCHAR(20) NOT NULL,
			object_id BIGINT(20) UNSIGNED NOT NULL,
			deleted_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY tipo_fecha (tipo, deleted_at),
			KEY object_id (object_id)
		) {$charset};" );
	}
}
