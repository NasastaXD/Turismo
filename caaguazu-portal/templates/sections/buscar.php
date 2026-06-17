<?php
/**
 * Resultados de búsqueda del panel (sobre destinos).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$results = array();
if ( '' !== $q ) {
	$results = get_posts( array(
		'post_type'      => PROMOTUR_Destinos::CPT,
		'post_status'    => 'any',
		's'              => $q,
		'posts_per_page' => 50,
	) );
}

$page_title = __( 'Buscar', 'caaguazu-portal' );
$body = function () use ( $q, $results ) {
	?>
	<h2 class="promotur-h2"><?php esc_html_e( 'Buscar', 'caaguazu-portal' ); ?></h2>
	<?php if ( '' === $q ) : ?>
		<p class="promotur-muted"><?php esc_html_e( 'Escribí algo en el buscador de arriba para encontrar fichas.', 'caaguazu-portal' ); ?></p>
	<?php else : ?>
		<p class="promotur-muted">
			<?php
			/* translators: 1: cantidad, 2: término */
			printf( esc_html( _n( '%1$d resultado para «%2$s»', '%1$d resultados para «%2$s»', count( $results ), 'caaguazu-portal' ) ), count( $results ), esc_html( $q ) );
			?>
		</p>
		<?php if ( empty( $results ) ) : ?>
			<div class="promotur-card promotur-empty-box"><p><?php esc_html_e( 'Sin resultados. Probá con otras palabras.', 'caaguazu-portal' ); ?></p></div>
		<?php else : ?>
			<div class="promotur-list">
				<?php foreach ( $results as $p ) :
					$estado = PROMOTUR_Editorial::get_estado( $p->ID ); ?>
					<a class="promotur-row" href="<?php echo esc_url( promotur_url( 'panel/editor/' . $p->ID ) ); ?>">
						<span class="promotur-row__main"><span class="promotur-row__title"><?php echo esc_html( get_the_title( $p ) ); ?></span></span>
						<span class="promotur-pill <?php echo esc_attr( PROMOTUR_Editorial::estado_class( $estado ) ); ?>"><?php echo esc_html( PROMOTUR_Editorial::estado_label( $estado ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
