<?php
/**
 * Modo salida de campo: captura rápida (foto + nota + GPS) que se guarda offline
 * y se sincroniza como borrador cuando hay conexión.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$page_title = __( 'Salida de campo', 'caaguazu-portal' );
$body = function () {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Captura en el lugar', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Salida de campo', 'caaguazu-portal' ); ?></h2>
	<p class="promotur-muted"><?php esc_html_e( 'Capturá foto, nota y ubicación aunque no tengas señal. Se guarda en tu dispositivo y lo sincronizás como borrador cuando vuelva la conexión.', 'caaguazu-portal' ); ?></p>

	<div class="promotur-card" data-captura>
		<form class="promotur-form" data-captura-form>
			<label class="promotur-field"><span><?php esc_html_e( 'Nombre del lugar', 'caaguazu-portal' ); ?></span><input type="text" name="titulo" required></label>
			<label class="promotur-field"><span><?php esc_html_e( 'Nota rápida', 'caaguazu-portal' ); ?></span><textarea name="nota" rows="3"></textarea></label>
			<div class="promotur-grid promotur-grid--2">
				<label class="promotur-field">
					<span><?php esc_html_e( 'Foto', 'caaguazu-portal' ); ?></span>
					<input type="file" accept="image/*" capture="environment" data-captura-photo>
				</label>
				<div class="promotur-field">
					<span><?php esc_html_e( 'Ubicación (GPS)', 'caaguazu-portal' ); ?></span>
					<div class="promotur-inline-form">
						<button type="button" class="promotur-btn promotur-btn--ghost promotur-btn--small" data-captura-geo>📍 <?php esc_html_e( 'Tomar ubicación', 'caaguazu-portal' ); ?></button>
						<input type="text" name="lat" data-captura-lat placeholder="lat" readonly style="width:110px">
						<input type="text" name="lng" data-captura-lng placeholder="lng" readonly style="width:110px">
					</div>
				</div>
			</div>
			<button type="submit" class="promotur-btn promotur-btn--primary"><?php esc_html_e( 'Guardar captura', 'caaguazu-portal' ); ?></button>
			<span class="promotur-form-msg" data-form-msg aria-live="polite"></span>
		</form>
	</div>

	<div class="promotur-pagehead promotur-mt">
		<h3 class="promotur-h3"><?php esc_html_e( 'Capturas pendientes', 'caaguazu-portal' ); ?> <span data-captura-count></span></h3>
		<button type="button" class="promotur-btn promotur-btn--primary" data-captura-sync><?php esc_html_e( 'Sincronizar', 'caaguazu-portal' ); ?></button>
	</div>
	<div class="promotur-list" data-captura-list></div>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
