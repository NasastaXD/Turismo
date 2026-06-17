<?php
/** Moderación de reseñas/consultas/reportes (placeholder — Fase 2/3). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$page_title = __( 'Moderación', 'caaguazu-portal' );
$body = function () {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Comunidad', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Moderación', 'caaguazu-portal' ); ?></h2>
	<div class="promotur-card promotur-empty-box">
		<p><?php esc_html_e( 'Reseñas, consultas del visitante y reportes de “info desactualizada” se gestionan acá. Llega con la experiencia del visitante (Fase 2).', 'caaguazu-portal' ); ?></p>
	</div>
	<?php
};
include PROMOTUR_DIR . 'templates/shell.php';
