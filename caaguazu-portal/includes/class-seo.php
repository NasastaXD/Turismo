<?php
/**
 * SEO básico + Open Graph para las fichas de destino (compartir en redes).
 * El sitemap de WordPress (core) ya incluye el CPT público automáticamente.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_SEO {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_head', array( $this, 'meta_tags' ), 5 );
	}

	public function meta_tags() {
		if ( ! is_singular( PROMOTUR_Destinos::CPT ) ) {
			return;
		}
		$id     = get_queried_object_id();
		$gancho = get_post_meta( $id, '_promotur_gancho', true );
		$desc   = $gancho ? $gancho : wp_strip_all_tags( get_the_excerpt( $id ) );
		$desc   = $desc ? $desc : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $id ) ), 30 );
		$title  = get_the_title( $id );
		$url    = get_permalink( $id );

		$portada = get_post_meta( $id, '_promotur_portada', true );
		$img     = $portada ? wp_get_attachment_image_url( (int) $portada, 'large' ) : get_the_post_thumbnail_url( $id, 'large' );

		$tags = array(
			array( 'name', 'description', $desc ),
			array( 'property', 'og:type', 'article' ),
			array( 'property', 'og:title', $title ),
			array( 'property', 'og:description', $desc ),
			array( 'property', 'og:url', $url ),
			array( 'property', 'og:site_name', get_bloginfo( 'name' ) ),
			array( 'name', 'twitter:card', $img ? 'summary_large_image' : 'summary' ),
			array( 'name', 'twitter:title', $title ),
			array( 'name', 'twitter:description', $desc ),
		);
		if ( $img ) {
			$tags[] = array( 'property', 'og:image', $img );
			$tags[] = array( 'name', 'twitter:image', $img );
		}

		echo "\n<!-- Promotores Turísticos SEO -->\n";
		foreach ( $tags as $t ) {
			if ( '' === trim( (string) $t[2] ) ) { continue; }
			printf(
				'<meta %s="%s" content="%s">' . "\n",
				esc_attr( $t[0] ),
				esc_attr( $t[1] ),
				esc_attr( $t[2] )
			);
		}
	}
}
