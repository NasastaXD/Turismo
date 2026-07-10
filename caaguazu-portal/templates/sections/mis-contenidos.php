<?php
/**
 * Mis contenidos: fichas del usuario actual, agrupadas por estado.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$uid   = caaguazu_account_id();
$posts = get_posts( array(
	'post_type'      => PROMOTUR_Destinos::CPT,
	'post_status'    => 'any',
	'meta_query'     => array( array( 'key' => PROMOTUR_Destinos::OWNER_META, 'value' => $uid ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	'posts_per_page' => 100,
	'orderby'        => 'modified',
	'order'          => 'DESC',
) );

$page_title = __( 'Mis contenidos', 'caaguazu-portal' );
$body = function () use ( $posts ) {
	?>
	<div class="promotur-pagehead">
		<div>
			<div class="promotur-eyebrow"><?php esc_html_e( 'Tu producción', 'caaguazu-portal' ); ?></div>
			<h2 class="promotur-h2"><?php esc_html_e( 'Mis contenidos', 'caaguazu-portal' ); ?></h2>
		</div>
		<a class="promotur-btn promotur-btn--primary" href="<?php echo esc_url( promotur_url( 'panel/editor' ) ); ?>"><?php esc_html_e( '+ Nueva ficha', 'caaguazu-portal' ); ?></a>
	</div>

	<?php if ( empty( $posts ) ) : ?>
		<div class="promotur-card promotur-empty-box">
			<p><?php esc_html_e( 'Todavía no creaste ninguna ficha. ¡Empezá por una!', 'caaguazu-portal' ); ?></p>
			<a class="promotur-btn promotur-btn--primary" href="<?php echo esc_url( promotur_url( 'panel/editor' ) ); ?>"><?php esc_html_e( 'Crear mi primera ficha', 'caaguazu-portal' ); ?></a>
		</div>
	<?php else : ?>
		<div class="promotur-list">
			<?php foreach ( $posts as $p ) :
				$estado = PROMOTUR_Editorial::get_estado( $p->ID );
				?>
				<a class="promotur-row" href="<?php echo esc_url( promotur_url( 'panel/editor/' . $p->ID ) ); ?>">
					<span class="promotur-row__main">
						<span class="promotur-row__title"><?php echo esc_html( get_the_title( $p ) ? get_the_title( $p ) : __( '(sin título)', 'caaguazu-portal' ) ); ?></span>
						<span class="promotur-row__meta"><?php echo esc_html( get_the_modified_date( '', $p ) ); ?></span>
					</span>
					<span class="promotur-pill <?php echo esc_attr( PROMOTUR_Editorial::estado_class( $estado ) ); ?>"><?php echo esc_html( PROMOTUR_Editorial::estado_label( $estado ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
