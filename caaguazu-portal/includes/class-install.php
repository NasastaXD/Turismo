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
		self::create_tables();
		// El CPT y las reglas deben existir antes del flush.
		PROMOTUR_Destinos::register_post_type();
		PROMOTUR_Router::add_rewrite_rules();
		flush_rewrite_rules();
		update_option( 'promotur_version', PROMOTUR_VERSION );
		update_option( 'promotur_db_version', PROMOTUR_DB_VERSION );
	}

	/**
	 * Crea/actualiza las tablas custom (invitaciones y auditoría). Idempotente (dbDelta).
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$invitations = $wpdb->prefix . 'promotur_invitations';
		$audit       = $wpdb->prefix . 'promotur_audit_log';

		dbDelta( "CREATE TABLE {$invitations} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			token_hash VARCHAR(64) NOT NULL,
			email VARCHAR(190) NULL,
			role VARCHAR(40) NOT NULL,
			invited_by BIGINT(20) UNSIGNED NULL,
			expires_at DATETIME NOT NULL,
			used_at DATETIME NULL,
			used_by_user_id BIGINT(20) UNSIGNED NULL,
			revoked_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			metadata LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY email (email),
			KEY expires_at (expires_at)
		) {$charset};" );

		dbDelta( "CREATE TABLE {$audit} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NULL,
			action VARCHAR(80) NOT NULL,
			entity_type VARCHAR(60) NULL,
			entity_id BIGINT(20) UNSIGNED NULL,
			payload LONGTEXT NULL,
			ip VARCHAR(64) NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY action (action),
			KEY user_id (user_id),
			KEY entity (entity_type, entity_id)
		) {$charset};" );
	}

	/**
	 * Desactivación: solo limpia las rewrite rules (no toca roles ni datos).
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
