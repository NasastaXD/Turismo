<?php
/**
 * Instalación: tablas de reseñas, roles y capacidades.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CGZ_Install {

	const DB_VERSION = '1.0.0';

	/**
	 * Hook de activación.
	 */
	public static function activate() {
		self::create_tables();
		self::add_roles();
		self::create_pages();
		// El CPT necesita estar registrado antes de flush; lo registramos puntualmente.
		CGZ_CPT::register_post_type();
		flush_rewrite_rules();
		update_option( 'cgz_db_version', self::DB_VERSION );
	}

	/**
	 * Crea las páginas de cuenta y panel de dueño con sus shortcodes (si no existen).
	 */
	public static function create_pages() {
		$pages = array(
			'cgz_account_page_id' => array(
				'slug'    => 'cuenta',
				'title'   => __( 'Mi cuenta', 'caaguazu-locales' ),
				'content' => '[caaguazu_cuenta]',
			),
			'cgz_owner_page_id'   => array(
				'slug'    => 'panel-de-mi-local',
				'title'   => __( 'Panel de mi local', 'caaguazu-locales' ),
				'content' => '[caaguazu_dueno_panel]',
			),
		);

		foreach ( $pages as $option => $page ) {
			$existing = get_page_by_path( $page['slug'] );
			if ( $existing ) {
				update_option( $option, $existing->ID );
				continue;
			}
			$page_id = wp_insert_post( array(
				'post_title'   => $page['title'],
				'post_name'    => $page['slug'],
				'post_content' => $page['content'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
			) );
			if ( ! is_wp_error( $page_id ) ) {
				update_option( $option, $page_id );
			}
		}
	}

	/**
	 * Hook de desactivación.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Crea/actualiza las tablas custom de reseñas.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$reviews         = $wpdb->prefix . 'cgz_reviews';
		$replies         = $wpdb->prefix . 'cgz_review_replies';
		$votes           = $wpdb->prefix . 'cgz_review_votes';
		$photos          = $wpdb->prefix . 'cgz_review_photos';

		$sql = array();

		$sql[] = "CREATE TABLE {$reviews} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			local_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(200) NOT NULL DEFAULT '',
			content TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'publish',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY local_id (local_id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$replies} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			review_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			content TEXT NOT NULL,
			is_owner TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY review_id (review_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$votes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			review_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			vote TINYINT NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY review_user (review_id, user_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$photos} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			review_id BIGINT UNSIGNED NOT NULL,
			attachment_id BIGINT UNSIGNED NOT NULL,
			sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY review_id (review_id)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Roles personalizados y capacidades sobre el CPT.
	 */
	public static function add_roles() {
		// Visitante: puede escribir reseñas y participar.
		add_role( 'cgz_visitante', __( 'Visitante Caaguazú', 'caaguazu-locales' ), array(
			'read'             => true,
			'cgz_write_review' => true,
		) );

		// Dueño de local: gestiona su propio local + participa.
		add_role( 'cgz_dueno', __( 'Dueño de local', 'caaguazu-locales' ), array(
			'read'                  => true,
			'cgz_write_review'      => true,
			'cgz_manage_own_local'  => true,
			'upload_files'          => true,
			'edit_cgz_locals'       => true,
			'edit_published_cgz_locals' => true,
		) );

		// El administrador puede todo lo del CPT y moderar reseñas.
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$caps = array(
				'cgz_write_review',
				'cgz_manage_own_local',
				'cgz_moderate_reviews',
				'edit_cgz_local',
				'read_cgz_local',
				'delete_cgz_local',
				'edit_cgz_locals',
				'edit_others_cgz_locals',
				'publish_cgz_locals',
				'read_private_cgz_locals',
				'delete_cgz_locals',
				'delete_others_cgz_locals',
				'edit_published_cgz_locals',
			);
			foreach ( $caps as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}
}
