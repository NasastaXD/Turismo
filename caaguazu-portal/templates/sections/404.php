<?php
/**
 * Sección desconocida dentro del shell.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$page_title = __( 'No encontrado', 'caaguazu-portal' );
$body = function () {
	?>
	<div class="promotur-empty-box promotur-card">
		<div class="promotur-eyebrow"><?php esc_html_e( 'Error 404', 'caaguazu-portal' ); ?></div>
		<h2 class="promotur-h2"><?php esc_html_e( 'Esta sección no existe', 'caaguazu-portal' ); ?></h2>
		<p class="promotur-muted"><?php esc_html_e( 'Puede que el enlace esté roto o que no tengas acceso.', 'caaguazu-portal' ); ?></p>
		<a class="promotur-btn promotur-btn--primary" href="<?php echo esc_url( promotur_url( 'panel' ) ); ?>"><?php esc_html_e( 'Ir al inicio del panel', 'caaguazu-portal' ); ?></a>
	</div>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
