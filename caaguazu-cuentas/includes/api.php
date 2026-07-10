<?php
/**
 * API pública del sistema de cuentas — la superficie que consumen el Portal y
 * los futuros paneles. Nombres genéricos (caaguazu_*), estables, para que un
 * panel no dependa de las clases internas.
 *
 * Puente de transición: mientras el Portal siga autenticando con usuarios de
 * WordPress (ronda de fundación), caaguazu_current_account() resuelve primero
 * la sesión propia y, si no hay, cae en la cuenta migrada del usuario WP
 * logueado. Así la API devuelve la cuenta correcta se haya entrado por el
 * flujo viejo o el nuevo, y el cutover posterior es suave. El puente se apaga
 * con el filtro `caaguazu_cuentas_bridge_wp_session`.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------------------- */
/*  Cuenta actual                                                            */
/* ------------------------------------------------------------------------- */

/**
 * Cuenta actualmente autenticada (o null).
 *
 * @return array|null Fila de cuenta.
 */
function caaguazu_current_account() {
	$account = Caaguazu_Cuentas_Sessions::instance()->current();
	if ( $account ) {
		return $account;
	}

	// Puente: usuario de WordPress logueado → su cuenta migrada.
	if ( apply_filters( 'caaguazu_cuentas_bridge_wp_session', true ) && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
		$bridged = caaguazu_account_for_wp_user( get_current_user_id() );
		if ( $bridged && 'active' === $bridged['status'] ) {
			return $bridged;
		}
	}
	return null;
}

/**
 * ID de la cuenta actual (0 si no hay).
 *
 * @return int
 */
function caaguazu_account_id() {
	$account = caaguazu_current_account();
	return $account ? (int) $account['id'] : 0;
}

/**
 * ¿Hay una cuenta autenticada?
 *
 * @return bool
 */
function caaguazu_is_logged_in() {
	return caaguazu_account_id() > 0;
}

/**
 * Cuenta asociada a un usuario de WordPress (por la columna wp_user_id).
 * Usada por el puente y por la migración.
 *
 * @param int $wp_user_id
 * @return array|null
 */
function caaguazu_account_for_wp_user( $wp_user_id ) {
	global $wpdb;
	$wp_user_id = (int) $wp_user_id;
	if ( $wp_user_id <= 0 ) {
		return null;
	}
	$t   = Caaguazu_Cuentas_Install::tables();
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['accounts']} WHERE wp_user_id = %d", $wp_user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	return $row ? $row : null;
}

/* ------------------------------------------------------------------------- */
/*  Login / logout                                                           */
/* ------------------------------------------------------------------------- */

/**
 * Inicia sesión con email + contraseña.
 *
 * @param string $email
 * @param string $password
 * @param bool   $remember
 * @return array|WP_Error
 */
function caaguazu_account_login( $email, $password, $remember = false ) {
	return Caaguazu_Cuentas_Auth::instance()->login( $email, $password, $remember );
}

/**
 * Cierra la sesión de la cuenta actual.
 */
function caaguazu_account_logout() {
	Caaguazu_Cuentas_Auth::instance()->logout();
}

/* ------------------------------------------------------------------------- */
/*  Permisos por panel                                                       */
/* ------------------------------------------------------------------------- */

/**
 * ¿La cuenta actual (o una dada) puede X en un panel?
 *
 * Bypass de administrador: los administradores de WordPress no se migran a
 * cuentas propias (siguen entrando por wp-admin/wp-login.php a propósito,
 * ver class-migration.php) pero deben poder seguir abriendo cualquier panel
 * con su login de WP — ese bypass sólo aplica cuando no se pasó un
 * $account_id explícito (o sea, para "la cuenta actual").
 *
 * @param string   $panel
 * @param string   $cap
 * @param int|null $account_id null = cuenta actual
 * @return bool
 */
function caaguazu_account_can( $panel, $cap, $account_id = null ) {
	if ( null === $account_id ) {
		$account_id = caaguazu_account_id();
		if ( $account_id <= 0 ) {
			return caaguazu_wp_admin_bypass();
		}
	}
	$account_id = (int) $account_id;
	if ( $account_id <= 0 ) {
		return false;
	}
	return Caaguazu_Cuentas_Panels::instance()->account_can( $account_id, $panel, $cap );
}

/**
 * ¿La cuenta tiene acceso (activo) a un panel?
 *
 * @param string   $panel
 * @param int|null $account_id
 * @return bool
 */
function caaguazu_account_has_panel( $panel, $account_id = null ) {
	if ( null === $account_id ) {
		$account_id = caaguazu_account_id();
		if ( $account_id <= 0 ) {
			return caaguazu_wp_admin_bypass();
		}
	}
	$account_id = (int) $account_id;
	if ( $account_id <= 0 ) {
		return false;
	}
	return Caaguazu_Cuentas_Panels::instance()->has_panel( $account_id, $panel );
}

/**
 * ¿El visitante actual es un administrador de WordPress logueado? Único
 * bypass que sortea el sistema de cuentas propio — los administradores no
 * tienen (a propósito) una cuenta migrada, pero conservan acceso total a
 * cualquier panel a través de su login de WordPress existente.
 *
 * @return bool
 */
function caaguazu_wp_admin_bypass() {
	return apply_filters( 'caaguazu_cuentas_wp_admin_bypass', true )
		&& function_exists( 'current_user_can' )
		&& is_user_logged_in()
		&& current_user_can( 'manage_options' );
}

/**
 * Otorga acceso a un panel (helper de conveniencia).
 *
 * @param int        $account_id
 * @param string     $panel
 * @param string     $role
 * @param array|null $caps
 * @param int|null   $granted_by
 * @return int|WP_Error
 */
function caaguazu_account_grant( $account_id, $panel, $role, $caps = null, $granted_by = null ) {
	return Caaguazu_Cuentas_Panels::instance()->grant( $account_id, $panel, $role, $caps, $granted_by );
}

/* ------------------------------------------------------------------------- */
/*  Usuario de servicio (autor de contenido)                                 */
/* ------------------------------------------------------------------------- */

/**
 * ID del usuario de WordPress de servicio, usado como post_author del
 * contenido creado desde los paneles. El dueño real se guarda en un meta.
 *
 * @return int
 */
function caaguazu_service_user_id() {
	$id = (int) get_option( Caaguazu_Cuentas_Install::SERVICE_USER_OPTION, 0 );
	if ( ! $id || ! get_userdata( $id ) ) {
		$id = Caaguazu_Cuentas_Install::ensure_service_user();
	}
	return $id;
}

/* ------------------------------------------------------------------------- */
/*  Metadata de cuenta (JSON en la columna metadata)                         */
/* ------------------------------------------------------------------------- */

/**
 * Lee una clave del metadata JSON de una cuenta.
 *
 * @param int    $account_id
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function caaguazu_account_meta_get( $account_id, $key, $default = null ) {
	$account = Caaguazu_Cuentas_Accounts::get( $account_id );
	if ( ! $account || empty( $account['metadata'] ) ) {
		return $default;
	}
	$meta = json_decode( $account['metadata'], true );
	return ( is_array( $meta ) && array_key_exists( $key, $meta ) ) ? $meta[ $key ] : $default;
}

/**
 * Setea una clave en el metadata JSON de una cuenta.
 *
 * @param int    $account_id
 * @param string $key
 * @param mixed  $value
 * @return bool
 */
function caaguazu_account_meta_set( $account_id, $key, $value ) {
	$account = Caaguazu_Cuentas_Accounts::get( $account_id );
	if ( ! $account ) {
		return false;
	}
	$meta = ! empty( $account['metadata'] ) ? json_decode( $account['metadata'], true ) : array();
	if ( ! is_array( $meta ) ) {
		$meta = array();
	}
	$meta[ $key ] = $value;
	return Caaguazu_Cuentas_Accounts::update( $account_id, array( 'metadata' => $meta ) );
}

/**
 * Borra una clave del metadata JSON de una cuenta.
 *
 * @param int    $account_id
 * @param string $key
 * @return bool
 */
function caaguazu_account_meta_delete( $account_id, $key ) {
	$account = Caaguazu_Cuentas_Accounts::get( $account_id );
	if ( ! $account || empty( $account['metadata'] ) ) {
		return false;
	}
	$meta = json_decode( $account['metadata'], true );
	if ( ! is_array( $meta ) || ! array_key_exists( $key, $meta ) ) {
		return false;
	}
	unset( $meta[ $key ] );
	return Caaguazu_Cuentas_Accounts::update( $account_id, array( 'metadata' => $meta ) );
}
