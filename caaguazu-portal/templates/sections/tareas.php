<?php
/** Tareas / asignaciones + tablero "lo que falta cubrir". */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$uid       = caaguazu_account_id();
$tareas    = PROMOTUR_Tareas::visible_for( $uid );
$can_assign = caaguazu_account_can( 'promotor', 'promotur_assign_tasks' );
$minis     = $can_assign ? promotur_team_members( 'promotur_mini' ) : array();
$destinos  = $can_assign ? get_posts( array( 'post_type' => PROMOTUR_Destinos::CPT, 'post_status' => array( 'publish', 'draft', 'pending' ), 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) : array();

$page_title = __( 'Tareas', 'caaguazu-portal' );
$body = function () use ( $uid, $tareas, $can_assign, $minis, $destinos ) {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Asignaciones', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Tareas y lo que falta cubrir', 'caaguazu-portal' ); ?></h2>

	<?php if ( $can_assign ) : ?>
		<details class="promotur-card promotur-newtask">
			<summary><strong><?php esc_html_e( '+ Nueva tarea o hueco', 'caaguazu-portal' ); ?></strong></summary>
			<form class="promotur-form" data-tarea-form>
				<label class="promotur-field"><span><?php esc_html_e( 'Título', 'caaguazu-portal' ); ?></span><input type="text" name="titulo" required></label>
				<label class="promotur-field"><span><?php esc_html_e( 'Detalle', 'caaguazu-portal' ); ?></span><textarea name="detalle" rows="2"></textarea></label>
				<div class="promotur-grid promotur-grid--3">
					<label class="promotur-field"><span><?php esc_html_e( 'Tipo', 'caaguazu-portal' ); ?></span>
						<select name="tipo">
							<option value="tarea"><?php esc_html_e( 'Tarea asignada', 'caaguazu-portal' ); ?></option>
							<option value="hueco"><?php esc_html_e( 'Hueco (reclamable)', 'caaguazu-portal' ); ?></option>
						</select>
					</label>
					<label class="promotur-field"><span><?php esc_html_e( 'Vence', 'caaguazu-portal' ); ?></span><input type="date" name="vence"></label>
					<label class="promotur-field"><span><?php esc_html_e( 'Destino (opcional)', 'caaguazu-portal' ); ?></span>
						<select name="destino"><option value="0">—</option>
							<?php foreach ( $destinos as $d ) : ?><option value="<?php echo esc_attr( $d->ID ); ?>"><?php echo esc_html( get_the_title( $d ) ); ?></option><?php endforeach; ?>
						</select>
					</label>
				</div>
				<label class="promotur-field"><span><?php esc_html_e( 'Asignar a (Mini Promotores)', 'caaguazu-portal' ); ?></span>
					<select name="asignados[]" multiple size="4">
						<?php foreach ( $minis as $m ) : ?><option value="<?php echo esc_attr( $m['id'] ); ?>"><?php echo esc_html( $m['display_name'] ); ?></option><?php endforeach; ?>
					</select>
				</label>
				<button type="submit" class="promotur-btn promotur-btn--primary"><?php esc_html_e( 'Crear', 'caaguazu-portal' ); ?></button>
				<span class="promotur-form-msg" data-form-msg aria-live="polite"></span>
			</form>
		</details>
	<?php endif; ?>

	<?php if ( empty( $tareas ) ) : ?>
		<div class="promotur-card promotur-empty-box"><p><?php esc_html_e( 'No hay tareas por ahora.', 'caaguazu-portal' ); ?></p></div>
	<?php else : ?>
		<div class="promotur-list">
			<?php foreach ( $tareas as $t ) :
				$estado = PROMOTUR_Tareas::get_estado( $t->ID );
				$tipo   = get_post_meta( $t->ID, '_promotur_tipo', true );
				$vence  = get_post_meta( $t->ID, '_promotur_vence', true );
				$mine   = PROMOTUR_Tareas::is_assigned( $t->ID, $uid );
				$estados = PROMOTUR_Tareas::estados();
				?>
				<div class="promotur-card promotur-mod" data-tarea="<?php echo esc_attr( $t->ID ); ?>">
					<div class="promotur-row__meta">
						<?php if ( 'hueco' === $tipo ) : ?><span class="promotur-pill is-sent"><?php esc_html_e( 'Hueco', 'caaguazu-portal' ); ?></span> <?php endif; ?>
						<span class="promotur-pill is-muted"><?php echo esc_html( $estados[ $estado ] ); ?></span>
						<?php if ( $vence ) : ?> · <?php echo esc_html( sprintf( __( 'vence %s', 'caaguazu-portal' ), $vence ) ); ?><?php endif; ?>
					</div>
					<strong class="promotur-row__title"><?php echo esc_html( get_the_title( $t ) ); ?></strong>
					<?php if ( $t->post_content ) : ?><p><?php echo esc_html( $t->post_content ); ?></p><?php endif; ?>
					<div class="promotur-inline-form">
						<?php if ( 'hueco' === $tipo && 'completada' !== $estado && ! $mine ) : ?>
							<button type="button" class="promotur-btn promotur-btn--ghost promotur-btn--small" data-op="claim"><?php esc_html_e( 'Reclamar', 'caaguazu-portal' ); ?></button>
						<?php endif; ?>
						<?php if ( 'completada' !== $estado && ( $mine || $can_assign ) ) : ?>
							<button type="button" class="promotur-btn promotur-btn--primary promotur-btn--small" data-op="complete"><?php esc_html_e( 'Marcar completada', 'caaguazu-portal' ); ?></button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
