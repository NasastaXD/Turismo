<?php
/**
 * Selector de idioma ES / EN / GN. Persiste la elección en cookie y aplica el locale.
 *
 * Nota: el idioma fuente del plugin es español (las cadenas __() ya están en ES).
 * Para EN y GN hay que proveer los archivos de traducción en /languages
 * (caaguazu-portal-en_US.mo, caaguazu-portal-gn.mo). El switcher ya cambia el locale;
 * las cadenas se traducen en cuanto existan esos .mo.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_I18n {

	private static $instance = null;
	const COOKIE = 'promotur_lang';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'maybe_set_lang' ), 1 );
		add_filter( 'locale', array( $this, 'filter_locale' ) );
	}

	/** Idiomas disponibles: clave corta → { code (locale WP), label }. */
	public static function langs() {
		return array(
			'es' => array( 'code' => 'es_ES', 'label' => 'ES' ),
			'en' => array( 'code' => 'en_US', 'label' => 'EN' ),
			'gn' => array( 'code' => 'gn',    'label' => 'GN' ),
		);
	}

	/** Clave de idioma elegida (o '' si no hay). */
	public static function current() {
		$c = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return array_key_exists( $c, self::langs() ) ? $c : '';
	}

	/**
	 * ?promotur_setlang=es|en|gn → setea cookie y redirige limpio.
	 */
	public function maybe_set_lang() {
		if ( ! isset( $_GET['promotur_setlang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$lang = sanitize_key( wp_unslash( $_GET['promotur_setlang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( array_key_exists( $lang, self::langs() ) && ! headers_sent() ) {
			setcookie( self::COOKIE, $lang, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/' );
		}
		wp_safe_redirect( remove_query_arg( 'promotur_setlang' ) );
		exit;
	}

	/**
	 * Aplica el locale elegido en el front-end (no toca wp-admin).
	 */
	public function filter_locale( $locale ) {
		if ( is_admin() ) {
			return $locale;
		}
		$cur = self::current();
		if ( $cur ) {
			$langs = self::langs();
			return $langs[ $cur ]['code'];
		}
		return $locale;
	}

	/**
	 * Markup del switcher (para el topbar del panel).
	 */
	public static function switcher() {
		$cur = self::current();
		if ( '' === $cur ) { $cur = 'es'; }
		ob_start();
		echo '<div class="promotur-langs" role="group" aria-label="' . esc_attr__( 'Idioma', 'caaguazu-portal' ) . '">';
		foreach ( self::langs() as $key => $l ) {
			printf(
				'<a class="promotur-lang%s" href="%s">%s</a>',
				$key === $cur ? ' is-active' : '',
				esc_url( add_query_arg( 'promotur_setlang', $key ) ),
				esc_html( $l['label'] )
			);
		}
		echo '</div>';
		return ob_get_clean();
	}
}
