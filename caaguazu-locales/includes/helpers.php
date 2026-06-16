<?php
/**
 * Helpers compartidos.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Tipos de local disponibles. Solo restaurante y hotel habilitan reservas por WhatsApp.
 *
 * @return array slug => etiqueta
 */
function cgz_local_types() {
	return apply_filters( 'cgz_local_types', array(
		'restaurante' => __( 'Restaurante', 'caaguazu-locales' ),
		'hotel'       => __( 'Hotel / Hospedaje', 'caaguazu-locales' ),
		'comercio'    => __( 'Comercio / Artesanía', 'caaguazu-locales' ),
		'atraccion'   => __( 'Atracción / Lugar', 'caaguazu-locales' ),
	) );
}

/**
 * Tipos que habilitan la sección de reservas por WhatsApp.
 *
 * @return string[]
 */
function cgz_booking_enabled_types() {
	return apply_filters( 'cgz_booking_enabled_types', array( 'restaurante', 'hotel' ) );
}

/**
 * ¿El local acepta reservas por WhatsApp?
 *
 * @param int $local_id
 * @return bool
 */
function cgz_local_allows_booking( $local_id ) {
	$tipo = get_post_meta( $local_id, '_cgz_tipo', true );
	$wa   = cgz_sanitize_phone( get_post_meta( $local_id, '_cgz_whatsapp', true ) );
	return $wa && in_array( $tipo, cgz_booking_enabled_types(), true );
}

/**
 * Normaliza un teléfono a solo dígitos (formato wa.me).
 *
 * @param string $raw
 * @return string
 */
function cgz_sanitize_phone( $raw ) {
	$digits = preg_replace( '/\D+/', '', (string) $raw );
	return $digits;
}

/**
 * Opciones de reserva por defecto según el tipo de local.
 *
 * @param string $tipo
 * @return array[] lista de { label, message }
 */
function cgz_default_booking_options( $tipo ) {
	if ( 'hotel' === $tipo ) {
		return array(
			array(
				'label'   => __( 'Consultar disponibilidad', 'caaguazu-locales' ),
				'message' => __( 'Hola, me gustaría consultar disponibilidad de habitaciones.', 'caaguazu-locales' ),
			),
			array(
				'label'   => __( 'Reservar habitación', 'caaguazu-locales' ),
				'message' => __( "Hola, quisiera reservar una habitación.\nFechas: \nPersonas: ", 'caaguazu-locales' ),
			),
			array(
				'label'   => __( 'Consultar tarifas', 'caaguazu-locales' ),
				'message' => __( 'Hola, ¿me podrían pasar las tarifas y formas de pago?', 'caaguazu-locales' ),
			),
		);
	}

	// Restaurante (por defecto).
	return array(
		array(
			'label'   => __( 'Reservar mesa', 'caaguazu-locales' ),
			'message' => __( "Hola, quisiera reservar una mesa.\nFecha y hora: \nPersonas: ", 'caaguazu-locales' ),
		),
		array(
			'label'   => __( 'Consultar el menú', 'caaguazu-locales' ),
			'message' => __( 'Hola, ¿me podrían pasar el menú del día?', 'caaguazu-locales' ),
		),
		array(
			'label'   => __( 'Pedido para llevar', 'caaguazu-locales' ),
			'message' => __( 'Hola, quisiera hacer un pedido para llevar.', 'caaguazu-locales' ),
		),
	);
}

/**
 * Devuelve las opciones de reserva configuradas (o las por defecto del tipo).
 *
 * @param int $local_id
 * @return array[]
 */
function cgz_get_booking_options( $local_id ) {
	$stored = get_post_meta( $local_id, '_cgz_booking_opciones', true );
	if ( is_array( $stored ) && ! empty( $stored ) ) {
		return $stored;
	}
	$tipo = get_post_meta( $local_id, '_cgz_tipo', true );
	return cgz_default_booking_options( $tipo );
}

/**
 * Coordenadas del local (lat, lng) o null.
 *
 * @param int $local_id
 * @return array{lat:float,lng:float}|null
 */
function cgz_get_coords( $local_id ) {
	$lat = get_post_meta( $local_id, '_cgz_lat', true );
	$lng = get_post_meta( $local_id, '_cgz_lng', true );
	if ( '' === $lat || '' === $lng || null === $lat || null === $lng ) {
		return null;
	}
	return array( 'lat' => (float) $lat, 'lng' => (float) $lng );
}

/**
 * Centro por defecto del mapa (Caaguazú).
 *
 * @return array{lat:float,lng:float,zoom:int}
 */
function cgz_map_center() {
	return apply_filters( 'cgz_map_center', array( 'lat' => -25.4646, 'lng' => -56.0173, 'zoom' => 14 ) );
}

/**
 * ¿El usuario puede gestionar este local? (admin o dueño asignado)
 *
 * @param int      $local_id
 * @param int|null $user_id
 * @return bool
 */
function cgz_user_can_manage_local( $local_id, $user_id = null ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) { return false; }
	if ( user_can( $user_id, 'manage_options' ) ) { return true; }
	$owner = (int) get_post_meta( $local_id, '_cgz_owner', true );
	return $owner && $owner === (int) $user_id;
}

/**
 * Carga las librerías de Leaflet (CSS + JS) desde CDN.
 * Se llama desde los shortcodes/metaboxes que usan mapa.
 */
function cgz_enqueue_leaflet() {
	if ( ! wp_style_is( 'cgz-leaflet', 'registered' ) ) {
		wp_register_style( 'cgz-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'cgz-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
	}
	wp_enqueue_style( 'cgz-leaflet' );
	wp_enqueue_script( 'cgz-leaflet' );
}

/**
 * URL wa.me con mensaje pre-cargado.
 *
 * @param string $phone  dígitos
 * @param string $message
 * @return string
 */
function cgz_whatsapp_url( $phone, $message = '' ) {
	$phone = cgz_sanitize_phone( $phone );
	$url   = 'https://wa.me/' . $phone;
	if ( '' !== $message ) {
		$url .= '?text=' . rawurlencode( $message );
	}
	return $url;
}
