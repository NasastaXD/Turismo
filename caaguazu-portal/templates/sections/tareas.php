<?php
/** Tareas y asignaciones (placeholder de Fase 1 — se completa en Fase 3). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$page_title = __( 'Tareas', 'caaguazu-portal' );
$body = function () {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Asignaciones', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Tareas', 'caaguazu-portal' ); ?></h2>
	<div class="promotur-card promotur-empty-box">
		<p><?php esc_html_e( 'Acá vas a ver las consignas con fecha límite y el tablero de “lo que falta cubrir”. Llega en la siguiente fase.', 'caaguazu-portal' ); ?></p>
	</div>
	<?php
};
include PROMOTUR_DIR . 'templates/shell.php';
