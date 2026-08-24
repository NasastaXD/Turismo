<?php
/**
 * Canje del código opaco contra el REST del CEAD — el único tramo servidor a
 * servidor del flujo (ver README). El navegador solo nos trae `?code=`; acá
 * lo cambiamos por los claims reales, firmados con el secreto compartido.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CEADSSO_Redeem {

	/**
	 * ¿Están puestas las dos constantes de wp-config que hacen falta?
	 *
	 * @return bool
	 */
	public static function configured() {
		return defined( 'CEAD_TUR_SSO_SECRET' ) && CEAD_TUR_SSO_SECRET
			&& defined( 'CEAD_TUR_SSO_URL' ) && CEAD_TUR_SSO_URL;
	}

	/**
	 * Canjea un código por los claims de la persona. Servidor a servidor,
	 * autenticado con HMAC — el código en sí no dice nada.
	 *
	 * @param string $code 64 hex, tal como llega en el query string.
	 * @return array|WP_Error claims { cead_uid, email, nombre, telefono, rol, curso, emitido }
	 */
	public static function redeem( $code ) {
		if ( ! self::configured() ) {
			return new WP_Error( 'sin_configurar', __( 'Falta configurar la integración con el CEAD.', 'caaguazu-sso-cead' ) );
		}
		if ( ! is_string( $code ) || ! preg_match( '/^[a-f0-9]{64}$/', $code ) ) {
			return new WP_Error( 'code_invalido', __( 'Enlace inválido.', 'caaguazu-sso-cead' ) );
		}

		$ts  = time();
		$sig = hash_hmac( 'sha256', $code . '|' . $ts, CEAD_TUR_SSO_SECRET );

		$response = wp_remote_post( CEAD_TUR_SSO_URL, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'code' => $code, 'ts' => $ts, 'sig' => $sig ) ),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'error_red', __( 'No pudimos comunicarnos con el CEAD. Probá de nuevo en un momento.', 'caaguazu-sso-cead' ) );
		}

		$code_http = wp_remote_retrieve_response_code( $response );
		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$body      = is_array( $body ) ? $body : array();

		if ( 200 !== $code_http || empty( $body['ok'] ) ) {
			$clave = isset( $body['error'] ) ? sanitize_key( $body['error'] ) : 'error_desconocido';
			return new WP_Error( $clave, self::error_message( $clave ) );
		}

		if ( empty( $body['cead_uid'] ) || empty( $body['email'] ) || ! is_email( $body['email'] ) || empty( $body['rol'] ) ) {
			return new WP_Error( 'respuesta_invalida', __( 'El CEAD devolvió datos incompletos.', 'caaguazu-sso-cead' ) );
		}

		return array(
			'cead_uid' => (int) $body['cead_uid'],
			'email'    => sanitize_email( $body['email'] ),
			'nombre'   => isset( $body['nombre'] ) ? sanitize_text_field( $body['nombre'] ) : '',
			'telefono' => isset( $body['telefono'] ) ? sanitize_text_field( $body['telefono'] ) : '',
			'rol'      => sanitize_key( $body['rol'] ),
			'curso'    => isset( $body['curso'] ) ? sanitize_text_field( $body['curso'] ) : '',
		);
	}

	/**
	 * Mensaje sin detalles técnicos para cada clave de error del contrato.
	 *
	 * @param string $clave
	 * @return string
	 */
	public static function error_message( $clave ) {
		$mensajes = array(
			'code_invalido'    => __( 'Ese enlace de acceso no es válido.', 'caaguazu-sso-cead' ),
			'code_vencido'     => __( 'Ese enlace de acceso venció. Volvé al panel del CEAD y probá de nuevo.', 'caaguazu-sso-cead' ),
			'code_usado'       => __( 'Ese enlace de acceso ya fue usado. Volvé al panel del CEAD y probá de nuevo.', 'caaguazu-sso-cead' ),
			'firma_invalida'   => __( 'No pudimos verificar ese enlace de acceso.', 'caaguazu-sso-cead' ),
			'desfase_horario'  => __( 'No pudimos verificar ese enlace de acceso (reloj desincronizado).', 'caaguazu-sso-cead' ),
		);
		return isset( $mensajes[ $clave ] ) ? $mensajes[ $clave ] : __( 'No pudimos verificar tu acceso desde el CEAD.', 'caaguazu-sso-cead' );
	}
}
