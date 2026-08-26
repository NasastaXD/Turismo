<?php
/**
 * Helpers compartidos por los endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Imagen normalizada para la app: siempre la misma forma, o null.
 *
 * @param int    $attachment_id
 * @param string $credito
 * @param string $size
 * @return array|null { url, credito, alt, w, h }
 */
function czuapi_imagen( $attachment_id, $credito = '', $size = 'large' ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) {
		return null;
	}
	$src = wp_get_attachment_image_src( $attachment_id, $size );
	if ( ! $src ) {
		return null;
	}
	return array(
		'url'     => $src[0],
		'w'       => (int) $src[1],
		'h'       => (int) $src[2],
		'credito' => (string) $credito,
		'alt'     => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
	);
}

/**
 * Autor visible de una pieza de contenido.
 *
 * IMPORTANTE — este es el punto que marcó el lado de la app y tenía razón:
 * WordPress exige un post_author válido, pero como la gente del panel no es
 * usuaria de WordPress, el autor técnico de TODO el contenido es el usuario de
 * servicio (`caaguazu-servicio`). Si la app leyera post_author, cada ficha y
 * cada artículo aparecerían firmados por ese usuario técnico.
 *
 * El dueño real vive en el meta de cuenta (PROMOTUR_Destinos::OWNER_META). Para
 * contenido anterior al cutover de identidad, el meta puede no estar: en ese
 * caso se cae al vínculo wp_user_id que dejó la migración de cuentas.
 *
 * @param int $post_id
 * @return array|null { id, nombre }
 */
function czuapi_autor( $post_id ) {
	$account_id = 0;

	if ( class_exists( 'PROMOTUR_Destinos' ) && method_exists( 'PROMOTUR_Destinos', 'owner_account_id' ) ) {
		$account_id = (int) PROMOTUR_Destinos::owner_account_id( $post_id );
	}
	if ( $account_id <= 0 ) {
		$account_id = (int) get_post_meta( $post_id, '_caaguazu_owner', true );
	}
	// Último recurso: contenido pre-cutover, cuando el autor sí era un usuario
	// de WordPress real y la migración dejó el vínculo en la cuenta.
	if ( $account_id <= 0 && function_exists( 'caaguazu_account_for_wp_user' ) ) {
		$cuenta = caaguazu_account_for_wp_user( (int) get_post_field( 'post_author', $post_id ) );
		if ( $cuenta ) {
			$account_id = (int) $cuenta['id'];
		}
	}
	if ( $account_id <= 0 ) {
		return null;
	}

	$cuenta = Caaguazu_Cuentas_Accounts::get( $account_id );
	if ( ! $cuenta ) {
		return null;
	}
	return array(
		'id'     => $account_id,
		'nombre' => $cuenta['display_name'] ? $cuenta['display_name'] : $cuenta['email'],
	);
}

/**
 * Término normalizado (categoría o zona) con su presentación.
 *
 * @param WP_Term|int|null $term
 * @return array|null
 */
function czuapi_termino( $term ) {
	if ( is_numeric( $term ) ) {
		$term = get_term( (int) $term );
	}
	if ( ! $term || is_wp_error( $term ) ) {
		return null;
	}
	$out = array(
		'id'     => (int) $term->term_id,
		'slug'   => $term->slug,
		'nombre' => $term->name,
	);
	$color = get_term_meta( $term->term_id, CZUAPI_Taxonomias::META_COLOR, true );
	if ( $color ) {
		$out['color'] = $color;
	}
	return $out;
}

/**
 * Primer término de una taxonomía en un post, normalizado.
 *
 * @param int    $post_id
 * @param string $taxonomy
 * @return array|null
 */
function czuapi_primer_termino( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}
	return czuapi_termino( reset( $terms ) );
}

/**
 * Fecha en ISO 8601 UTC, o null. La app parsea un solo formato.
 *
 * @param string $mysql_date
 * @return string|null
 */
function czuapi_fecha( $mysql_date ) {
	if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
		return null;
	}
	$ts = strtotime( $mysql_date . ' UTC' );
	return $ts ? gmdate( 'c', $ts ) : null;
}

/**
 * Solo el contenido publicado es visible por la API pública. El estado
 * editorial de caaguazu-portal manda: `publicado` es lo único público, y esa
 * regla no se reimplementa acá — se lee de donde ya está.
 *
 * @return array argumentos de WP_Query
 */
function czuapi_args_publicado() {
	return array(
		'post_status'         => 'publish',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => false,
	);
}
