<?php
/**
 * Layout de auth: centrado, sin sidebar/topbar. Comparte el <head> del panel.
 * Recibe $page_title y $body (closure).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$page_title = isset( $page_title ) ? $page_title : __( 'Acceso', 'caaguazu-portal' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body <?php body_class( 'promotur-app promotur-auth-page' ); ?>>
<?php wp_body_open(); ?>
	<main class="promotur-auth">
		<div class="promotur-auth__card">
			<a class="promotur-brand promotur-brand--auth" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span class="promotur-brand__mark" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21V11"/><path d="M12 11c0-4 2-6 6-7-1 4-3 6-6 7Z"/><path d="M12 11C12 7 10 5 4 4c1 4 3 6 8 7Z"/></svg>
				</span>
				<span class="promotur-brand__text"><?php bloginfo( 'name' ); ?></span>
			</a>
			<?php
			if ( isset( $body ) && is_callable( $body ) ) {
				$body();
			}
			?>
		</div>
	</main>
<?php wp_footer(); ?>
</body>
</html>
