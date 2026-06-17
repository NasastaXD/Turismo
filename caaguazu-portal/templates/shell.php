<?php
/**
 * Shell (layout único del panel). Recibe del contrato de página:
 *   $page_title (string) y $body (closure que imprime el contenido).
 * Ejecuta $body() dentro de <main>.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$page_title = isset( $page_title ) ? $page_title : get_bloginfo( 'name' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="">
<head>
<?php include __DIR__ . '/partials/head.php'; ?>
</head>
<body <?php body_class( 'promotur-app' ); ?>>
<?php wp_body_open(); ?>

	<?php include __DIR__ . '/partials/splash.php'; ?>

	<div class="promotur-backdrop" data-drawer-backdrop hidden></div>

	<?php include __DIR__ . '/partials/sidebar.php'; ?>

	<div class="promotur-main">
		<?php include __DIR__ . '/partials/topbar.php'; ?>

		<main class="promotur-content" id="promotur-content" tabindex="-1">
			<?php
			$flash = promotur_flash();
			if ( $flash ) {
				printf(
					'<div class="promotur-flash promotur-flash--%s" role="status">%s</div>',
					esc_attr( $flash['type'] ),
					esc_html( $flash['message'] )
				);
			}
			if ( isset( $body ) && is_callable( $body ) ) {
				$body();
			}
			?>
		</main>
	</div>

	<?php include __DIR__ . '/partials/bottomnav.php'; ?>

<?php wp_footer(); ?>
</body>
</html>
