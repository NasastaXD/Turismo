<?php
/** Curaduría / destacados (placeholder — Fase 3). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$page_title = __( 'Curaduría', 'caaguazu-portal' );
$body = function () {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Portada', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Curaduría y destacados', 'caaguazu-portal' ); ?></h2>
	<div class="promotur-card promotur-empty-box">
		<p><?php esc_html_e( 'Desde acá vas a elegir y ordenar los destacados de la home, banners de temporada y colecciones. Próximamente.', 'caaguazu-portal' ); ?></p>
	</div>
	<?php
};
include PROMOTUR_DIR . 'templates/shell.php';
