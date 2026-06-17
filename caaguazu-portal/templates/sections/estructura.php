<?php
/** Estructura del sitio: categorías/zonas/etiquetas (placeholder — links a admin por ahora). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$page_title = __( 'Estructura', 'caaguazu-portal' );
$body = function () {
	$cpt = PROMOTUR_Destinos::CPT;
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Organización', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Estructura del sitio', 'caaguazu-portal' ); ?></h2>
	<div class="promotur-card promotur-empty-box">
		<p><?php esc_html_e( 'Categorías, zonas y etiquetas de los destinos. La edición completa llega en la siguiente fase; mientras tanto, gestionalas desde WordPress.', 'caaguazu-portal' ); ?></p>
		<div class="promotur-inline-form">
			<a class="promotur-btn promotur-btn--ghost" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=promotur_categoria&post_type=' . $cpt ) ); ?>"><?php esc_html_e( 'Categorías', 'caaguazu-portal' ); ?></a>
			<a class="promotur-btn promotur-btn--ghost" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=promotur_zona&post_type=' . $cpt ) ); ?>"><?php esc_html_e( 'Zonas', 'caaguazu-portal' ); ?></a>
			<a class="promotur-btn promotur-btn--ghost" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=promotur_etiqueta&post_type=' . $cpt ) ); ?>"><?php esc_html_e( 'Etiquetas', 'caaguazu-portal' ); ?></a>
		</div>
	</div>
	<?php
};
include PROMOTUR_DIR . 'templates/shell.php';
