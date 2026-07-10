<?php
/**
 * Hashing y verificación de contraseñas del sistema de cuentas.
 *
 * Las cuentas NO son usuarios de WordPress, pero para que la migración de los
 * promotores existentes no obligue a nadie a resetear su clave, reusamos el
 * hash que ya tenían en wp_users: WordPress guarda un hash phpass ($P$…) (o
 * bcrypt/argon en WP 6.8+), y este verificador lo reconoce.
 *
 * - Contraseñas nuevas: password_hash() nativo de PHP (bcrypt por defecto).
 * - Verificación: primero password_verify() (bcrypt/argon), y como respaldo el
 *   verificador phpass de WordPress para los hashes $P$ heredados.
 * - "Rehash on login": si un hash heredado (phpass) verifica bien, la capa de
 *   login lo regraba en bcrypt, así el formato viejo desaparece con el uso.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Caaguazu_Cuentas_Passwords {

	/** Largo mínimo de contraseña (alineado con el registro actual del Portal). */
	const MIN_LENGTH = 6;

	/**
	 * Hashea una contraseña nueva con el algoritmo por defecto de PHP (bcrypt).
	 *
	 * @param string $password
	 * @return string hash
	 */
	public static function hash( $password ) {
		return password_hash( $password, PASSWORD_DEFAULT );
	}

	/**
	 * Verifica una contraseña contra un hash almacenado.
	 *
	 * Acepta hashes nativos de PHP (bcrypt/argon) y, como respaldo, hashes
	 * phpass ($P$) heredados de wp_users (para cuentas migradas).
	 *
	 * @param string $password
	 * @param string $hash
	 * @return bool
	 */
	public static function verify( $password, $hash ) {
		if ( '' === (string) $hash ) {
			return false;
		}
		// Hashes nativos de PHP: bcrypt ($2y$), argon2 ($argon2…).
		if ( 0 === strpos( $hash, '$2y$' ) || 0 === strpos( $hash, '$2b$' ) || 0 === strpos( $hash, '$argon2' ) ) {
			return password_verify( $password, $hash );
		}
		// Hash heredado de WordPress (phpass $P$/$H$, o el $wp$ de WP 6.8+):
		// delegamos en el verificador de WordPress, que los entiende todos.
		if ( function_exists( 'wp_check_password' ) ) {
			return (bool) wp_check_password( $password, $hash );
		}
		// Último recurso (entornos sin WP cargado): sólo formatos nativos.
		return password_verify( $password, $hash );
	}

	/**
	 * ¿Este hash conviene regrabarse al algoritmo por defecto?
	 * True para cualquier hash que no sea ya bcrypt/argon nativo (p. ej. los
	 * phpass heredados), y también si cambian los parámetros de bcrypt.
	 *
	 * @param string $hash
	 * @return bool
	 */
	public static function needs_rehash( $hash ) {
		$is_native = ( 0 === strpos( $hash, '$2y$' ) || 0 === strpos( $hash, '$2b$' ) || 0 === strpos( $hash, '$argon2' ) );
		if ( ! $is_native ) {
			return true;
		}
		return password_needs_rehash( $hash, PASSWORD_DEFAULT );
	}

	/**
	 * Valida el formato de una contraseña propuesta (largo mínimo).
	 *
	 * @param string $password
	 * @return bool
	 */
	public static function is_valid( $password ) {
		return strlen( (string) $password ) >= self::MIN_LENGTH;
	}
}
