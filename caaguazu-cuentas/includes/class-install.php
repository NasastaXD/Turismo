<?php
/**
 * Activación / desactivación e instalación de la estructura de datos.
 *
 * Tres tablas propias, deliberadamente separadas de wp_users para que el
 * login de un panel NO sea un login de WordPress (sin superficie de wp-admin
 * ni de XML-RPC para las cuentas de personas):
 *
 *   - caaguazu_accounts  : la identidad (email + hash de contraseña + estado).
 *   - caaguazu_sessions  : sesiones propias (se guarda sólo el HASH del token).
 *   - caaguazu_grants    : permisos por panel (una cuenta puede tener acceso a
 *                          varios paneles, cada uno con su rol/capabilities).
 *
 * Además crea UN usuario de WordPress de servicio, bloqueado (sin login,
 * contraseña aleatoria), usado sólo como `post_author` de contenido creado
 * desde los paneles — WordPress exige un autor válido para cada entrada, pero
 * ninguna persona se autentica jamás como ese usuario. El dueño real de cada
 * pieza de contenido se guarda aparte, en un meta de cuenta.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Caaguazu_Cuentas_Install {

	/** Opción donde guardamos el ID del usuario de servicio. */
	const SERVICE_USER_OPTION = 'caaguazu_cuentas_service_user';

	/** Login (interno) del usuario de servicio. */
	const SERVICE_USER_LOGIN = 'caaguazu-servicio';

	/**
	 * Activación del plugin.
	 */
	public static function activate() {
		self::create_tables();
		self::ensure_service_user();
		update_option( 'caaguazu_cuentas_version', CAAGUAZU_CUENTAS_VERSION );
		update_option( 'caaguazu_cuentas_db_version', CAAGUAZU_CUENTAS_DB_VERSION );
	}

	/**
	 * Nombres de tabla (con el prefijo del sitio).
	 *
	 * @return array{accounts:string,sessions:string,grants:string}
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'accounts' => $wpdb->prefix . 'caaguazu_accounts',
			'sessions' => $wpdb->prefix . 'caaguazu_sessions',
			'grants'   => $wpdb->prefix . 'caaguazu_grants',
		);
	}

	/**
	 * Crea/actualiza las tablas custom. Idempotente (dbDelta).
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t       = self::tables();

		// La identidad. email es único (ci): el login es por email.
		dbDelta( "CREATE TABLE {$t['accounts']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL,
			pass_hash VARCHAR(255) NOT NULL,
			display_name VARCHAR(190) NULL,
			phone VARCHAR(40) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			wp_user_id BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			last_login_at DATETIME NULL,
			metadata LONGTEXT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status),
			KEY wp_user_id (wp_user_id)
		) {$charset};" );

		// Sesiones: guardamos sólo el hash del token (nunca el token en claro),
		// igual que el patrón de token_hash de las invitaciones del Portal.
		dbDelta( "CREATE TABLE {$t['sessions']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			account_id BIGINT(20) UNSIGNED NOT NULL,
			token_hash CHAR(64) NOT NULL,
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			last_seen_at DATETIME NULL,
			ip VARCHAR(64) NULL,
			user_agent VARCHAR(255) NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token_hash (token_hash),
			KEY account_id (account_id),
			KEY expires_at (expires_at)
		) {$charset};" );

		// Permisos por panel. Una fila = "esta cuenta tiene el rol X en el panel Y".
		// caps es un JSON opcional para overrides finos por cuenta (permisos
		// especiales), por encima de lo que trae el rol del panel.
		dbDelta( "CREATE TABLE {$t['grants']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			account_id BIGINT(20) UNSIGNED NOT NULL,
			panel VARCHAR(60) NOT NULL,
			role VARCHAR(60) NULL,
			caps LONGTEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			granted_by BIGINT(20) UNSIGNED NULL,
			granted_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY account_panel (account_id, panel),
			KEY panel (panel),
			KEY status (status)
		) {$charset};" );
	}

	/**
	 * Garantiza que exista el usuario de WordPress de servicio (bloqueado).
	 * Idempotente: si ya está, sólo cachea su ID en la opción.
	 *
	 * @return int ID del usuario de servicio (0 si no se pudo crear).
	 */
	public static function ensure_service_user() {
		$existing = (int) get_option( self::SERVICE_USER_OPTION, 0 );
		if ( $existing && get_userdata( $existing ) ) {
			return $existing;
		}

		$user = get_user_by( 'login', self::SERVICE_USER_LOGIN );
		if ( $user ) {
			update_option( self::SERVICE_USER_OPTION, $user->ID );
			return (int) $user->ID;
		}

		if ( ! function_exists( 'wp_insert_user' ) ) {
			require_once ABSPATH . 'wp-includes/registration.php';
		}

		$user_id = wp_insert_user( array(
			'user_login'   => self::SERVICE_USER_LOGIN,
			'user_pass'    => wp_generate_password( 64, true, true ),
			'user_email'   => 'servicio+cuentas@' . self::site_email_domain(),
			'display_name' => __( 'Contenido de paneles', 'caaguazu-cuentas' ),
			'role'         => 'subscriber',
		) );

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		// Marcarlo como "de sistema": sin login humano posible.
		update_user_meta( $user_id, '_caaguazu_service_account', 1 );
		update_option( self::SERVICE_USER_OPTION, $user_id );
		return (int) $user_id;
	}

	/**
	 * Dominio para el email técnico del usuario de servicio.
	 *
	 * @return string
	 */
	private static function site_email_domain() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = $host ? preg_replace( '/^www\./', '', $host ) : 'caaguazu.net';
		return $host;
	}

	/**
	 * Desactivación: no borra datos ni el usuario de servicio (las cuentas y
	 * su contenido deben sobrevivir a una desactivación accidental).
	 */
	public static function deactivate() {
		// Sin efectos destructivos a propósito.
	}
}
