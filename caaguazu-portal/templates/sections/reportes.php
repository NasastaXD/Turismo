<?php
/** Reportes / pulso: producción, lo más visto, búsquedas sin resultado y salud del contenido. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$top     = PROMOTUR_Stats::top_viewed( 8 );
$empties = PROMOTUR_Stats::empty_searches();
$health  = PROMOTUR_Stats::content_health( 6 );
$autores = promotur_team_members( array( 'promotur_promotor', 'promotur_mini' ) );

$page_title = __( 'Reportes', 'caaguazu-portal' );
$body = function () use ( $top, $empties, $health, $autores ) {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Métricas', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Pulso del portal', 'caaguazu-portal' ); ?></h2>

	<h3 class="promotur-h3"><?php esc_html_e( 'Producción por autor', 'caaguazu-portal' ); ?></h3>
	<div class="promotur-list">
		<?php foreach ( $autores as $u ) :
			$c = PROMOTUR_Stats::author_counts( $u['id'] );
			if ( 0 === $c['total'] ) { continue; } ?>
			<div class="promotur-row">
				<span class="promotur-row__main"><span class="promotur-row__title"><?php echo esc_html( $u['display_name'] ); ?></span></span>
				<span class="promotur-row__meta"><?php
					/* translators: 1: publicadas, 2: total */
					printf( esc_html__( '%1$d publicadas / %2$d', 'caaguazu-portal' ), $c['publicadas'], $c['total'] );
				?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<h3 class="promotur-h3"><?php esc_html_e( 'Lo más visto', 'caaguazu-portal' ); ?></h3>
	<?php if ( empty( $top ) ) : ?>
		<p class="promotur-muted"><?php esc_html_e( 'Todavía no hay vistas registradas.', 'caaguazu-portal' ); ?></p>
	<?php else : ?>
		<div class="promotur-list">
			<?php foreach ( $top as $p ) : ?>
				<a class="promotur-row" href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener">
					<span class="promotur-row__main"><span class="promotur-row__title"><?php echo esc_html( get_the_title( $p ) ); ?></span></span>
					<span class="promotur-row__meta"><?php
						/* translators: %d = vistas */
						printf( esc_html( _n( '%d vista', '%d vistas', PROMOTUR_Stats::views( $p->ID ), 'caaguazu-portal' ) ), PROMOTUR_Stats::views( $p->ID ) );
					?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<h3 class="promotur-h3"><?php esc_html_e( 'Búsquedas sin resultado', 'caaguazu-portal' ); ?> <span class="promotur-muted"><?php esc_html_e( '(huecos de contenido)', 'caaguazu-portal' ); ?></span></h3>
	<?php if ( empty( $empties ) ) : ?>
		<p class="promotur-muted"><?php esc_html_e( 'Sin búsquedas fallidas registradas.', 'caaguazu-portal' ); ?></p>
	<?php else : ?>
		<div class="promotur-list">
			<?php $i = 0; foreach ( $empties as $e ) { if ( $i++ >= 15 ) { break; } ?>
				<div class="promotur-row">
					<span class="promotur-row__main"><span class="promotur-row__title">“<?php echo esc_html( $e['q'] ); ?>”</span></span>
					<span class="promotur-row__meta"><?php echo esc_html( sprintf( _n( '%d vez', '%d veces', $e['count'], 'caaguazu-portal' ), $e['count'] ) ); ?></span>
				</div>
			<?php } ?>
		</div>
	<?php endif; ?>

	<h3 class="promotur-h3"><?php esc_html_e( 'Salud del contenido', 'caaguazu-portal' ); ?></h3>
	<div class="promotur-grid promotur-grid--2">
		<div class="promotur-card">
			<strong><?php echo esc_html( count( $health['sin_foto'] ) ); ?></strong> <?php esc_html_e( 'fichas publicadas sin portada', 'caaguazu-portal' ); ?>
			<?php if ( $health['sin_foto'] ) : ?>
				<ul class="promotur-muted">
					<?php foreach ( array_slice( $health['sin_foto'], 0, 6 ) as $p ) : ?>
						<li><a href="<?php echo esc_url( promotur_url( 'panel/editor/' . $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<div class="promotur-card">
			<strong><?php echo esc_html( count( $health['viejas'] ) ); ?></strong> <?php esc_html_e( 'fichas sin verificar hace +6 meses', 'caaguazu-portal' ); ?>
			<?php if ( $health['viejas'] ) : ?>
				<ul class="promotur-muted">
					<?php foreach ( array_slice( $health['viejas'], 0, 6 ) as $p ) : ?>
						<li><a href="<?php echo esc_url( promotur_url( 'panel/editor/' . $p->ID ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
