<?php
/** Curaduría: destacados de la home + banner de temporada. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$destacados = PROMOTUR_Curaduria::destacados();
$orden_map  = array_flip( $destacados ); // id => posición
$banner     = PROMOTUR_Curaduria::banner();
$publicados = get_posts( array( 'post_type' => PROMOTUR_Destinos::CPT, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );

$page_title = __( 'Curaduría', 'caaguazu-portal' );
$body = function () use ( $destacados, $orden_map, $banner, $publicados ) {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Portada', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Curaduría de la home', 'caaguazu-portal' ); ?></h2>
	<p class="promotur-muted"><?php esc_html_e( 'Elegí los destinos destacados y un banner de temporada. La home pública (shortcode [promotur_home]) refleja esto sin tocar código.', 'caaguazu-portal' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'promotur_curaduria' ); ?>
		<input type="hidden" name="action" value="promotur_save_curaduria">

		<div class="promotur-card">
			<h3 class="promotur-h3"><?php esc_html_e( 'Banner de temporada', 'caaguazu-portal' ); ?></h3>
			<label class="promotur-field"><span><?php esc_html_e( 'Título (vacío = sin banner)', 'caaguazu-portal' ); ?></span><input type="text" name="banner_title" value="<?php echo esc_attr( $banner['title'] ); ?>"></label>
			<label class="promotur-field"><span><?php esc_html_e( 'Texto', 'caaguazu-portal' ); ?></span><input type="text" name="banner_text" value="<?php echo esc_attr( $banner['text'] ); ?>"></label>
			<div class="promotur-grid promotur-grid--3">
				<label class="promotur-field"><span><?php esc_html_e( 'Enlace (URL)', 'caaguazu-portal' ); ?></span><input type="url" name="banner_url" value="<?php echo esc_attr( $banner['url'] ); ?>"></label>
				<label class="promotur-field"><span><?php esc_html_e( 'Desde', 'caaguazu-portal' ); ?></span><input type="date" name="banner_desde" value="<?php echo esc_attr( $banner['desde'] ); ?>"></label>
				<label class="promotur-field"><span><?php esc_html_e( 'Hasta', 'caaguazu-portal' ); ?></span><input type="date" name="banner_hasta" value="<?php echo esc_attr( $banner['hasta'] ); ?>"></label>
			</div>
		</div>

		<h3 class="promotur-h3"><?php esc_html_e( 'Destinos destacados', 'caaguazu-portal' ); ?></h3>
		<?php if ( empty( $publicados ) ) : ?>
			<p class="promotur-muted"><?php esc_html_e( 'Todavía no hay destinos publicados para destacar.', 'caaguazu-portal' ); ?></p>
		<?php else : ?>
			<div class="promotur-list">
				<?php foreach ( $publicados as $p ) :
					$on  = in_array( $p->ID, $destacados, true );
					$pos = isset( $orden_map[ $p->ID ] ) ? $orden_map[ $p->ID ] + 1 : ''; ?>
					<label class="promotur-row">
						<span class="promotur-row__main promotur-row__user">
							<input type="checkbox" name="destacado[]" value="<?php echo esc_attr( $p->ID ); ?>" <?php checked( $on ); ?>>
							<span class="promotur-row__title"><?php echo esc_html( get_the_title( $p ) ); ?></span>
						</span>
						<span class="promotur-inline-form">
							<span class="promotur-muted"><?php esc_html_e( 'Orden', 'caaguazu-portal' ); ?></span>
							<input type="number" name="orden[<?php echo esc_attr( $p->ID ); ?>]" value="<?php echo esc_attr( $pos ); ?>" min="1" style="width:70px">
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<p class="promotur-mt"><button type="submit" class="promotur-btn promotur-btn--primary"><?php esc_html_e( 'Guardar curaduría', 'caaguazu-portal' ); ?></button></p>
	</form>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
