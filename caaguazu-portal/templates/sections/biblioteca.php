<?php
/** Biblioteca de medios (placeholder — Fase 3/4). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$page_title = __( 'Biblioteca', 'caaguazu-portal' );
$body = function () {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Medios', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Biblioteca de medios', 'caaguazu-portal' ); ?></h2>
	<div class="promotur-card promotur-empty-box">
		<p><?php esc_html_e( 'Tu galería de fotos con créditos y atribución. Por ahora subís imágenes directo desde el editor de cada ficha.', 'caaguazu-portal' ); ?></p>
		<a class="promotur-btn promotur-btn--ghost" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Abrir la biblioteca de WordPress', 'caaguazu-portal' ); ?></a>
	</div>
	<?php
};
include PROMOTUR_DIR . 'templates/shell.php';
