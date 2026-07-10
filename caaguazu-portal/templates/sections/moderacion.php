<?php
/** Moderación unificada: reseñas, consultas (bandeja derivable) y reportes. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$resenas   = PROMOTUR_Resenas::pending();
$consultas = PROMOTUR_Consultas::all();
$reportes  = PROMOTUR_Consultas::pending_reports();
$minis     = promotur_team_members( 'promotur_mini' );

$page_title = __( 'Moderación', 'caaguazu-portal' );
$body = function () use ( $resenas, $consultas, $reportes, $minis ) {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Comunidad', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Moderación', 'caaguazu-portal' ); ?></h2>

	<h3 class="promotur-h3"><?php esc_html_e( 'Reseñas pendientes', 'caaguazu-portal' ); ?> <span class="promotur-muted">(<?php echo count( $resenas ); ?>)</span></h3>
	<?php if ( empty( $resenas ) ) : ?>
		<p class="promotur-muted"><?php esc_html_e( 'No hay reseñas para moderar. 🎉', 'caaguazu-portal' ); ?></p>
	<?php else : ?>
		<div class="promotur-list">
			<?php foreach ( $resenas as $c ) : ?>
				<div class="promotur-card promotur-mod" data-mod-resena="<?php echo esc_attr( $c->comment_ID ); ?>">
					<div class="promotur-row__meta">
						<strong><?php echo esc_html( $c->comment_author ); ?></strong> ·
						<?php echo PROMOTUR_Resenas::stars( PROMOTUR_Resenas::rating_of( $c->comment_ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?> ·
						<?php echo esc_html( get_the_title( $c->comment_post_ID ) ); ?>
					</div>
					<p><?php echo esc_html( $c->comment_content ); ?></p>
					<div class="promotur-inline-form">
						<button type="button" class="promotur-btn promotur-btn--primary promotur-btn--small" data-op="approve"><?php esc_html_e( 'Aprobar', 'caaguazu-portal' ); ?></button>
						<button type="button" class="promotur-btn promotur-btn--danger promotur-btn--small" data-op="trash"><?php esc_html_e( 'Descartar', 'caaguazu-portal' ); ?></button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<h3 class="promotur-h3"><?php esc_html_e( 'Bandeja de consultas', 'caaguazu-portal' ); ?></h3>
	<?php if ( empty( $consultas ) ) : ?>
		<p class="promotur-muted"><?php esc_html_e( 'No hay consultas.', 'caaguazu-portal' ); ?></p>
	<?php else : ?>
		<div class="promotur-list">
			<?php foreach ( $consultas as $cons ) :
				$estado = PROMOTUR_Consultas::get_estado( $cons->ID );
				$asig   = (int) get_post_meta( $cons->ID, '_promotur_asignado', true ); ?>
				<div class="promotur-card promotur-mod" data-consulta="<?php echo esc_attr( $cons->ID ); ?>">
					<div class="promotur-row__meta">
						<strong><?php echo esc_html( get_post_meta( $cons->ID, '_promotur_nombre', true ) ); ?></strong>
						&lt;<?php echo esc_html( get_post_meta( $cons->ID, '_promotur_email', true ) ); ?>&gt; ·
						<span class="promotur-pill is-muted"><?php echo esc_html( PROMOTUR_Consultas::estados()[ $estado ] ); ?></span>
					</div>
					<p><?php echo esc_html( $cons->post_content ); ?></p>
					<div class="promotur-inline-form">
						<?php if ( ! empty( $minis ) ) : ?>
							<select data-consulta-user>
								<option value=""><?php esc_html_e( 'Derivar a un Mini…', 'caaguazu-portal' ); ?></option>
								<?php foreach ( $minis as $m ) : ?>
									<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $asig, $m['id'] ); ?>><?php echo esc_html( $m['display_name'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="promotur-btn promotur-btn--ghost promotur-btn--small" data-op="assign"><?php esc_html_e( 'Derivar', 'caaguazu-portal' ); ?></button>
						<?php endif; ?>
						<?php if ( 'resuelta' !== $estado ) : ?>
							<button type="button" class="promotur-btn promotur-btn--primary promotur-btn--small" data-op="resolve"><?php esc_html_e( 'Marcar resuelta', 'caaguazu-portal' ); ?></button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<h3 class="promotur-h3"><?php esc_html_e( 'Reportes de info desactualizada', 'caaguazu-portal' ); ?> <span class="promotur-muted">(<?php echo count( $reportes ); ?>)</span></h3>
	<?php if ( empty( $reportes ) ) : ?>
		<p class="promotur-muted"><?php esc_html_e( 'Sin reportes abiertos.', 'caaguazu-portal' ); ?></p>
	<?php else : ?>
		<div class="promotur-list">
			<?php foreach ( $reportes as $r ) : ?>
				<div class="promotur-card promotur-mod" data-reporte="<?php echo esc_attr( $r->comment_ID ); ?>">
					<div class="promotur-row__meta">
						<a href="<?php echo esc_url( promotur_url( 'panel/editor/' . $r->comment_post_ID ) ); ?>"><?php echo esc_html( get_the_title( $r->comment_post_ID ) ); ?></a>
					</div>
					<p><?php echo esc_html( $r->comment_content ); ?></p>
					<button type="button" class="promotur-btn promotur-btn--primary promotur-btn--small" data-op="resolve"><?php esc_html_e( 'Marcar resuelto', 'caaguazu-portal' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
