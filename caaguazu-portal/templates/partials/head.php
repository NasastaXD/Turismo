<?php
/**
 * <head> compartido por el shell y el auth-shell.
 * Espera $page_title en el scope.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$promotur_title = ( isset( $page_title ) ? $page_title : get_bloginfo( 'name' ) ) . ' — ' . get_bloginfo( 'name' );

// El theme tiene title-tag; reemplazamos el título del documento por el de la página.
add_filter( 'pre_get_document_title', function () use ( $promotur_title ) {
	return $promotur_title;
}, 99 );

$brand_hex = apply_filters( 'promotur_brand_hex', '#155c33' );
?>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<?php // Anti-parpadeo: setear data-theme ANTES del primer paint. ?>
<script>(function(){try{var t=localStorage.getItem('promotur-theme');if(t==='dark'||(t===null&&window.matchMedia&&matchMedia('(prefers-color-scheme:dark)').matches)){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();</script>

<?php // Splash una vez por sesión. ?>
<script>(function(){try{if(sessionStorage.getItem('promotur-splash')){document.documentElement.classList.add('promotur-no-splash');}else{sessionStorage.setItem('promotur-splash','1');}}catch(e){document.documentElement.classList.add('promotur-no-splash');}})();</script>

<link rel="manifest" href="<?php echo esc_url( home_url( '/promotur-manifest.webmanifest' ) ); ?>">
<meta name="theme-color" content="<?php echo esc_attr( $brand_hex ); ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
<link rel="icon" type="image/png" href="<?php echo esc_url( home_url( '/promotur-icon-192.png' ) ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( home_url( '/promotur-icon-192.png' ) ); ?>">

<?php wp_head(); // carga el CSS/JS del portal + el styles.css del theme (tokens + fuentes). ?>
