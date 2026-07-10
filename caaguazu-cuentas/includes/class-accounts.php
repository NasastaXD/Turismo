<?php
/**
 * CRUD del registro de cuentas (la identidad universal).
 *
 * Una "cuenta" es un objeto propio, independiente de wp_users:
 *   id, email, pass_hash, display_name, phone, status, wp_user_id (traza de
 *   migración/servicio, opcional), timestamps, metadata (JSON libre).
 *
 * status: active | suspended | pending
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Caaguazu_Cuentas_Accounts {

	/**
	 * @return string Nombre de la tabla de cuentas.
	 */
	private static function table() {
		$t = Caaguazu_Cuentas_Install::tables();
		return $t['accounts'];
	}

	/**
	 * Normaliza un email para comparación/almacenamiento (minúsculas, trim).
	 *
	 * @param string $email
	 * @return string
	 */
	public static function normalize_email( $email ) {
		return strtolower( trim( (string) $email ) );
	}

	/**
	 * Busca una cuenta por ID.
	 *
	 * @param int $id
	 * @return array|null Fila (array asociativo) o null.
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * Busca una cuenta por email (case-insensitive).
	 *
	 * @param string $email
	 * @return array|null
	 */
	public static function get_by_email( $email ) {
		global $wpdb;
		$email = self::normalize_email( $email );
		if ( '' === $email ) {
			return null;
		}
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? $row : null;
	}

	/**
	 * ¿Existe una cuenta con este email?
	 *
	 * @param string $email
	 * @return bool
	 */
	public static function email_exists( $email ) {
		return null !== self::get_by_email( $email );
	}

	/**
	 * Crea una cuenta. El hash se calcula acá a partir de la contraseña en claro.
	 * Para importar un hash ya existente (migración), usar create_with_hash().
	 *
	 * @param array $data { email, password, display_name?, phone?, status?, wp_user_id?, metadata? }
	 * @return int|WP_Error ID de la cuenta nueva, o WP_Error.
	 */
	public static function create( array $data ) {
		$password = isset( $data['password'] ) ? (string) $data['password'] : '';
		if ( ! Caaguazu_Cuentas_Passwords::is_valid( $password ) ) {
			return new WP_Error( 'weak_password', __( 'La contraseña es demasiado corta.', 'caaguazu-cuentas' ) );
		}
		$data['pass_hash'] = Caaguazu_Cuentas_Passwords::hash( $password );
		unset( $data['password'] );
		return self::create_with_hash( $data );
	}

	/**
	 * Crea una cuenta a partir de un hash ya calculado (p. ej. migración desde
	 * wp_users, donde reusamos el hash phpass existente).
	 *
	 * @param array $data { email, pass_hash, display_name?, phone?, status?, wp_user_id?, metadata? }
	 * @return int|WP_Error
	 */
	public static function create_with_hash( array $data ) {
		global $wpdb;

		$email = self::normalize_email( $data['email'] ?? '' );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'El email no es válido.', 'caaguazu-cuentas' ) );
		}
		if ( self::email_exists( $email ) ) {
			return new WP_Error( 'email_exists', __( 'Ya existe una cuenta con ese email.', 'caaguazu-cuentas' ) );
		}
		if ( empty( $data['pass_hash'] ) ) {
			return new WP_Error( 'missing_hash', __( 'Falta el hash de contraseña.', 'caaguazu-cuentas' ) );
		}

		$now = current_time( 'mysql', true );
		$row = array(
			'email'        => $email,
			'pass_hash'    => (string) $data['pass_hash'],
			'display_name' => isset( $data['display_name'] ) ? mb_substr( (string) $data['display_name'], 0, 190 ) : null,
			'phone'        => isset( $data['phone'] ) ? mb_substr( (string) $data['phone'], 0, 40 ) : null,
			'status'       => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'active',
			'wp_user_id'   => isset( $data['wp_user_id'] ) ? (int) $data['wp_user_id'] : null,
			'created_at'   => $now,
			'updated_at'   => $now,
			'last_login_at'=> null,
			'metadata'     => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
		);

		$ok = $wpdb->insert( self::table(), $row ); // phpcs:ignore WordPress.DB
		if ( ! $ok ) {
			return new WP_Error( 'db_error', __( 'No se pudo crear la cuenta.', 'caaguazu-cuentas' ) );
		}
		$id = (int) $wpdb->insert_id;

		/**
		 * Se creó una cuenta nueva.
		 *
		 * @param int   $id
		 * @param array $row
		 */
		do_action( 'caaguazu_cuentas_account_created', $id, $row );
		return $id;
	}

	/**
	 * Actualiza campos de una cuenta. Sólo toca las columnas provistas.
	 *
	 * @param int   $id
	 * @param array $fields columnas → valores (email/display_name/phone/status/wp_user_id/metadata)
	 * @return bool
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 || empty( $fields ) ) {
			return false;
		}
		$allowed = array( 'email', 'display_name', 'phone', 'status', 'wp_user_id', 'metadata' );
		$data    = array();
		foreach ( $allowed as $col ) {
			if ( ! array_key_exists( $col, $fields ) ) {
				continue;
			}
			if ( 'email' === $col ) {
				$data['email'] = self::normalize_email( $fields['email'] );
			} elseif ( 'metadata' === $col && is_array( $fields['metadata'] ) ) {
				$data['metadata'] = wp_json_encode( $fields['metadata'] );
			} else {
				$data[ $col ] = $fields[ $col ];
			}
		}
		if ( empty( $data ) ) {
			return false;
		}
		$data['updated_at'] = current_time( 'mysql', true );
		return (bool) $wpdb->update( self::table(), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Regraba el hash de contraseña de una cuenta (p. ej. tras reset, o rehash
	 * transparente en login cuando venía de un formato heredado).
	 *
	 * @param int    $id
	 * @param string $hash
	 * @return bool
	 */
	public static function set_hash( $id, $hash ) {
		global $wpdb;
		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB
			self::table(),
			array( 'pass_hash' => (string) $hash, 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Cambia la contraseña de una cuenta (recibe el texto en claro).
	 *
	 * @param int    $id
	 * @param string $password
	 * @return bool|WP_Error
	 */
	public static function set_password( $id, $password ) {
		if ( ! Caaguazu_Cuentas_Passwords::is_valid( $password ) ) {
			return new WP_Error( 'weak_password', __( 'La contraseña es demasiado corta.', 'caaguazu-cuentas' ) );
		}
		return self::set_hash( $id, Caaguazu_Cuentas_Passwords::hash( $password ) );
	}

	/**
	 * Marca el momento del último login.
	 *
	 * @param int $id
	 */
	public static function touch_login( $id ) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			self::table(),
			array( 'last_login_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * ¿La cuenta está activa (puede iniciar sesión)?
	 *
	 * @param array|int $account fila o ID
	 * @return bool
	 */
	public static function is_active( $account ) {
		$row = is_array( $account ) ? $account : self::get( $account );
		return $row && 'active' === $row['status'];
	}
}
