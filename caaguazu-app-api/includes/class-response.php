<?php
/**
 * Formato de respuesta y de error, y soporte de ETag.
 *
 * El formato de error es una de las confirmaciones que pidió el lado de la
 * app: para poder distinguir un fallo de red de uno de servidor hace falta que
 * TODO 4xx/5xx traiga el mismo cuerpo, siempre.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_Response {

	/**
	 * Error uniforme.
	 *
	 * {
	 *   "error": { "codigo": "no_encontrado", "mensaje": "…", "detalle": {} }
	 * }
	 *
	 * `codigo` es estable y pensado para que el cliente ramifique; `mensaje` es
	 * legible y puede cambiar sin romper nada.
	 *
	 * @param string $codigo
	 * @param string $mensaje
	 * @param int    $status
	 * @param array  $detalle
	 * @return WP_REST_Response
	 */
	public static function error( $codigo, $mensaje, $status = 400, $detalle = array() ) {
		return new WP_REST_Response( array(
			'error' => array(
				'codigo'  => $codigo,
				'mensaje' => $mensaje,
				'detalle' => (object) $detalle,
			),
		), $status );
	}

	/** Atajos de los errores frecuentes, para que el código no repita literales. */
	public static function no_autenticado() {
		return self::error( 'no_autenticado', __( 'Falta el token o no es válido.', 'caaguazu-app-api' ), 401 );
	}
	public static function sin_permiso() {
		return self::error( 'sin_permiso', __( 'La cuenta no tiene permiso para esto.', 'caaguazu-app-api' ), 403 );
	}
	public static function no_encontrado() {
		return self::error( 'no_encontrado', __( 'No existe o no está publicado.', 'caaguazu-app-api' ), 404 );
	}

	/**
	 * Respuesta con ETag. Si el cliente mandó If-None-Match y coincide,
	 * devuelve 304 sin cuerpo.
	 *
	 * Lo usan /strings y /media-manifest, que la app pide en cada arranque:
	 * sin esto se descargarían enteros cada vez.
	 *
	 * @param mixed            $data
	 * @param WP_REST_Request  $request
	 * @param int              $max_age segundos de cache-control
	 * @return WP_REST_Response
	 */
	public static function with_etag( $data, $request, $max_age = 300 ) {
		$etag     = '"' . md5( wp_json_encode( $data ) ) . '"';
		$if_none  = $request->get_header( 'if_none_match' );

		if ( $if_none && trim( $if_none ) === $etag ) {
			$res = new WP_REST_Response( null, 304 );
			$res->header( 'ETag', $etag );
			$res->header( 'Cache-Control', 'public, max-age=' . (int) $max_age );
			return $res;
		}

		$res = new WP_REST_Response( $data, 200 );
		$res->header( 'ETag', $etag );
		$res->header( 'Cache-Control', 'public, max-age=' . (int) $max_age );
		return $res;
	}

	/**
	 * Lista paginada con el sobre que pidió la app: sin `total` el cliente no
	 * puede saber si quedan más páginas sin pedir una de más.
	 *
	 * @param array $items
	 * @param int   $total
	 * @param int   $pagina
	 * @param int   $por_pagina
	 * @return array
	 */
	public static function paginado( $items, $total, $pagina, $por_pagina ) {
		return array(
			'items'      => $items,
			'total'      => (int) $total,
			'pagina'     => (int) $pagina,
			'por_pagina' => (int) $por_pagina,
		);
	}
}
