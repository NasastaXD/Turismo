<?php
/**
 * Textos e imágenes de la interfaz de la app.
 *
 * Requisito del proyecto: cualquier texto o imagen de UI se cambia sin
 * publicar un APK nuevo. Por eso viven en opciones (editables) y se sirven por
 * endpoint con ETag, en vez de estar compilados en el cliente.
 *
 * No aplica a fichas, eventos, recorridos ni artículos: eso es contenido
 * humano y viene de la base como cualquier otro contenido.
 *
 * El manifiesto de medios declara el tipo de cada entrada — pedido del lado de
 * la app, y con buen motivo: una restricción del proyecto es que el público no
 * lee párrafos y que donde haría falta explicar algo va una animación. Si las
 * animaciones se sirven por el mismo canal que las imágenes, se cambian igual
 * de fácil. La app ignora los tipos que no sabe dibujar, así que sumar tipos
 * nuevos no rompe versiones ya publicadas.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_UI_Content {

	private static $instance = null;

	const OPT_STRINGS  = 'czuapi_strings_';   // + locale
	const OPT_MANIFEST = 'czuapi_media_manifest';

	/** Idiomas que ya maneja el ecosistema. */
	const LOCALES = array( 'es', 'en', 'gn' );

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function register_routes() {
		register_rest_route( CZUAPI_NS, '/strings/(?P<locale>[a-z]{2})', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'strings' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( CZUAPI_NS, '/media-manifest', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'manifest' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function strings( $request ) {
		$locale = strtolower( (string) $request['locale'] );
		if ( ! in_array( $locale, self::LOCALES, true ) ) {
			return CZUAPI_Response::error(
				'locale_no_soportado',
				__( 'Idioma no disponible.', 'caaguazu-app-api' ),
				404,
				array( 'disponibles' => self::LOCALES )
			);
		}

		$strings = get_option( self::OPT_STRINGS . $locale, null );
		if ( ! is_array( $strings ) ) {
			// Sin nada cargado: se cae a español, y si tampoco hay, al set
			// base. Nunca se devuelve vacío — un menú sin etiquetas es peor
			// que un menú en otro idioma.
			$strings = get_option( self::OPT_STRINGS . 'es', null );
			if ( ! is_array( $strings ) ) {
				$strings = self::base();
			}
		}

		/**
		 * Permite completar o pisar textos desde otro plugin.
		 *
		 * @param array  $strings
		 * @param string $locale
		 */
		$strings = apply_filters( 'czuapi_strings', $strings, $locale );

		return CZUAPI_Response::with_etag( $strings, $request, 300 );
	}

	public function manifest( $request ) {
		$manifest = get_option( self::OPT_MANIFEST, array() );
		$manifest = is_array( $manifest ) ? $manifest : array();

		$out = array();
		foreach ( $manifest as $clave => $entrada ) {
			if ( ! is_array( $entrada ) ) {
				continue;
			}
			$tipo = isset( $entrada['tipo'] ) ? sanitize_key( $entrada['tipo'] ) : 'imagen';
			$att  = isset( $entrada['id'] ) ? (int) $entrada['id'] : 0;
			$url  = $att ? wp_get_attachment_url( $att ) : ( isset( $entrada['url'] ) ? esc_url_raw( $entrada['url'] ) : '' );

			if ( ! $url ) {
				continue;
			}

			$item = array( 'tipo' => $tipo, 'url' => $url );

			if ( 'animacion' === $tipo ) {
				$item['formato'] = isset( $entrada['formato'] ) ? sanitize_key( $entrada['formato'] ) : 'lottie';
			} else {
				$item['alt'] = isset( $entrada['alt'] ) ? (string) $entrada['alt'] : '';
				if ( $att ) {
					$src = wp_get_attachment_image_src( $att, 'full' );
					if ( $src ) {
						$item['w'] = (int) $src[1];
						$item['h'] = (int) $src[2];
					}
				}
			}

			$out[ $clave ] = $item;
		}

		/**
		 * @param array $out
		 */
		$out = apply_filters( 'czuapi_media_manifest', $out );

		return CZUAPI_Response::with_etag( $out, $request, 300 );
	}

	/**
	 * Set base de claves. Existe para que la app nunca reciba un objeto vacío
	 * en una instalación recién hecha, no como traducción definitiva: los
	 * textos reales los escribe una persona desde el panel.
	 *
	 * @return array
	 */
	public static function base() {
		return array(
			'nav.inventario' => 'Inventario',
			'nav.mapa'       => 'Mapa',
			'nav.recorridos' => 'Recorridos',
			'nav.articulos'  => 'Artículos',
		);
	}

	/**
	 * Guarda el set de textos de un idioma. Lo usa el panel.
	 *
	 * @param string $locale
	 * @param array  $strings
	 * @return bool
	 */
	public static function set_strings( $locale, array $strings ) {
		$locale = strtolower( $locale );
		if ( ! in_array( $locale, self::LOCALES, true ) ) {
			return false;
		}
		$limpio = array();
		foreach ( $strings as $k => $v ) {
			if ( ! is_scalar( $v ) ) { continue; }
			$limpio[ sanitize_text_field( (string) $k ) ] = sanitize_textarea_field( (string) $v );
		}
		return update_option( self::OPT_STRINGS . $locale, $limpio );
	}
}
