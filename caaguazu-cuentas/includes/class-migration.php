<?php
/**
 * Migración automática de los promotores que ya existían como usuarios de
 * WordPress hacia el sistema de cuentas propio.
 *
 * Es idempotente y se puede correr muchas veces: migra sólo a los usuarios con
 * un rol promotur_* que todavía no tengan una cuenta asociada (por wp_user_id),
 * así también levanta a los promotores nuevos que se sigan creando por el flujo
 * viejo durante la etapa de convivencia (puente).
 *
 * Clave del diseño: NO se resetea ninguna contraseña. WordPress guarda el hash
 * (phpass $P$…, o el formato nuevo) en wp_users.user_pass; lo copiamos tal cual
 * a la cuenta y el verificador de contraseñas lo entiende. El primer login por
 * el sistema nuevo lo regraba a bcrypt de forma transparente.
 *
 * A los administradores no se los migra: conservan su login de WordPress y el
 * acceso a wp-admin. La ronda de cutover define cómo entra un admin al panel.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Caaguazu_Cuentas_Migration {

	/** Panel al que se otorgan los grants de los promotores migrados. */
	const PANEL = 'promotor';

	/** Prioridad de roles del portal (más alto primero), igual que el helper del Portal. */
	private static $role_priority = array( 'promotur_promotor', 'promotur_mini', 'promotur_visitante' );

	/**
	 * Corre la migración con un lock corto para no repetir el trabajo en cada
	 * carga de admin. Los promotores nuevos se levantan en la siguiente ventana.
	 */
	public static function maybe_run() {
		if ( get_transient( 'caaguazu_cuentas_migr_lock' ) ) {
			return;
		}
		set_transient( 'caaguazu_cuentas_migr_lock', 1, 15 * MINUTE_IN_SECONDS );
		self::run();
	}

	/**
	 * Migra todos los usuarios promotur_* sin cuenta. Devuelve el conteo migrado.
	 *
	 * @return int
	 */
	public static function run() {
		$roles = self::$role_priority;
		$users = get_users( array(
			'role__in' => $roles,
			'fields'   => array( 'ID' ),
			'number'   => 0,
		) );

		$count = 0;
		foreach ( $users as $u ) {
			if ( self::migrate_user( (int) $u->ID ) ) {
				$count++;
			}
		}

		if ( $count ) {
			do_action( 'caaguazu_cuentas_migrated', $count );
		}
		return $count;
	}

	/**
	 * Migra un usuario de WordPress puntual. Idempotente: si ya tiene cuenta,
	 * sólo garantiza que el grant del panel exista.
	 *
	 * @param int $wp_user_id
	 * @return bool true si creó una cuenta nueva.
	 */
	public static function migrate_user( $wp_user_id ) {
		$user = get_userdata( $wp_user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		$role = self::highest_role( (array) $user->roles );
		if ( '' === $role ) {
			return false; // no es un usuario de panel
		}

		// ¿Ya migrado? (por wp_user_id o por email)
		$existing = caaguazu_account_for_wp_user( $wp_user_id );
		if ( ! $existing ) {
			$existing = Caaguazu_Cuentas_Accounts::get_by_email( $user->user_email );
		}

		if ( $existing ) {
			$account_id = (int) $existing['id'];
			// Asegurar el vínculo wp_user_id si venía por email.
			if ( empty( $existing['wp_user_id'] ) ) {
				Caaguazu_Cuentas_Accounts::update( $account_id, array( 'wp_user_id' => $wp_user_id ) );
			}
			self::ensure_grant( $account_id, $role );
			return false;
		}

		$suspended = get_user_meta( $wp_user_id, '_promotur_suspended', true );
		$phone     = (string) get_user_meta( $wp_user_id, '_promotur_phone', true );

		$account_id = Caaguazu_Cuentas_Accounts::create_with_hash( array(
			'email'        => $user->user_email,
			'pass_hash'    => $user->user_pass, // hash existente de WP, sin resetear
			'display_name' => $user->display_name ? $user->display_name : $user->user_login,
			'phone'        => $phone,
			'status'       => $suspended ? 'suspended' : 'active',
			'wp_user_id'   => $wp_user_id,
			'metadata'     => array( 'migrated_from_wp' => $wp_user_id, 'migrated_at' => current_time( 'mysql', true ) ),
		) );

		if ( is_wp_error( $account_id ) ) {
			return false;
		}

		self::ensure_grant( (int) $account_id, $role );
		return true;
	}

	/**
	 * Garantiza el grant del panel para una cuenta, snapshoteando las caps del
	 * rol desde el registro de roles del Portal si está disponible (así el grant
	 * es autónomo aunque el panel no esté registrado en este request).
	 *
	 * @param int    $account_id
	 * @param string $role
	 */
	private static function ensure_grant( $account_id, $role ) {
		$panels = Caaguazu_Cuentas_Panels::instance();
		if ( $panels->has_panel( $account_id, self::PANEL ) ) {
			return;
		}
		$caps = self::role_caps_snapshot( $role );
		$panels->grant( $account_id, self::PANEL, $role, $caps );
	}

	/**
	 * Snapshot de las caps de un rol del Portal (o null si no se conoce).
	 *
	 * @param string $role
	 * @return array|null
	 */
	private static function role_caps_snapshot( $role ) {
		if ( ! class_exists( 'PROMOTUR_Roles' ) ) {
			return null;
		}
		$roles = PROMOTUR_Roles::roles();
		return isset( $roles[ $role ]['caps'] ) ? (array) $roles[ $role ]['caps'] : null;
	}

	/**
	 * Rol de portal "más alto" de una lista de roles de WP (o '').
	 *
	 * @param array $roles
	 * @return string
	 */
	private static function highest_role( array $roles ) {
		foreach ( self::$role_priority as $r ) {
			if ( in_array( $r, $roles, true ) ) {
				return $r;
			}
		}
		return '';
	}
}
