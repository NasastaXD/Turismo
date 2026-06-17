<?php
/** Reportes / pulso (placeholder — Fase 3). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$page_title = __( 'Reportes', 'caaguazu-portal' );
$body = function () {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Métricas', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Reportes', 'caaguazu-portal' ); ?></h2>
	<div class="promotur-card promotur-empty-box">
		<p><?php esc_html_e( 'Producción por período y autor, lo más visto y las búsquedas sin resultado (huecos de contenido). Próximamente.', 'caaguazu-portal' ); ?></p>
	</div>
	<?php
};
include PROMOTUR_DIR . 'templates/shell.php';
