<?php
/** Equipo: usuarios por rol, producción, nivel de confianza e invitaciones. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$roles  = PROMOTUR_Roles::roles();
$levels = PROMOTUR_Stats::levels();

$page_title = __( 'Equipo', 'caaguazu-portal' );
$body = function () use ( $roles, $levels ) {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Tu equipo', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( 'Equipo', 'caaguazu-portal' ); ?></h2>

	<div class="promotur-card promotur-invite">
		<h3 class="promotur-h3"><?php esc_html_e( 'Invitar a alguien', 'caaguazu-portal' ); ?></h3>
		<p class="promotur-muted"><?php esc_html_e( 'Generá un enlace de invitación con el rol que elijas (válido 14 días).', 'caaguazu-portal' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="promotur-inline-form">
			<?php wp_nonce_field( 'promotur_invite' ); ?>
			<input type="hidden" name="action" value="promotur_invite">
			<select name="role">
				<option value="promotur_mini"><?php esc_html_e( 'Mini Promotor', 'caaguazu-portal' ); ?></option>
				<option value="promotur_promotor"><?php esc_html_e( 'Promotor', 'caaguazu-portal' ); ?></option>
				<option value="promotur_visitante"><?php esc_html_e( 'Visitante', 'caaguazu-portal' ); ?></option>
			</select>
			<button type="submit" class="promotur-btn promotur-btn--primary"><?php esc_html_e( 'Crear enlace', 'caaguazu-portal' ); ?></button>
		</form>
	</div>

	<?php foreach ( $roles as $role_key => $def ) :
		$users = promotur_team_members( $role_key );
		if ( empty( $users ) ) { continue; }
		$is_mini = ( 'promotur_mini' === $role_key );
		?>
		<h3 class="promotur-h3"><?php echo esc_html( $def['label'] ); ?> <span class="promotur-muted">(<?php echo count( $users ); ?>)</span></h3>
		<div class="promotur-list">
			<?php foreach ( $users as $u ) :
				$counts = PROMOTUR_Stats::author_counts( $u['id'] ); ?>
				<div class="promotur-card promotur-mod" data-user="<?php echo esc_attr( $u['id'] ); ?>">
					<div class="promotur-row__user">
						<?php echo promotur_avatar( $u, 'promotur-avatar--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span>
							<span class="promotur-row__title"><?php echo esc_html( $u['display_name'] ); ?></span>
							<span class="promotur-row__meta">
								<?php
								/* translators: 1: publicadas, 2: total */
								printf( esc_html__( '%1$d publicadas · %2$d en total', 'caaguazu-portal' ), $counts['publicadas'], $counts['total'] );
								if ( $is_mini ) {
									echo ' · ' . esc_html( PROMOTUR_Stats::level_label( $u['id'] ) );
								}
								?>
							</span>
						</span>
					</div>
					<?php if ( $is_mini ) : ?>
						<div class="promotur-inline-form">
							<span class="promotur-muted"><?php esc_html_e( 'Nivel de confianza:', 'caaguazu-portal' ); ?></span>
							<select data-nivel-select>
								<?php $cur = PROMOTUR_Stats::get_level( $u['id'] );
								foreach ( $levels as $lk => $ll ) : ?>
									<option value="<?php echo esc_attr( $lk ); ?>" <?php selected( $cur, $lk ); ?>><?php echo esc_html( $ll ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="button" class="promotur-btn promotur-btn--ghost promotur-btn--small" data-nivel-save><?php esc_html_e( 'Guardar', 'caaguazu-portal' ); ?></button>
							<span class="promotur-form-msg" data-form-msg aria-live="polite"></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
