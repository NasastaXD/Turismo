<?php
/**
 * Equipo: usuarios por rol del portal + generación de enlaces de invitación.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$roles = PROMOTUR_Roles::roles();

$page_title = __( 'Equipo', 'caaguazu-portal' );
$body = function () use ( $roles ) {
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
		$users = get_users( array( 'role' => $role_key, 'number' => 200, 'orderby' => 'display_name' ) );
		if ( empty( $users ) ) { continue; }
		?>
		<h3 class="promotur-h3"><?php echo esc_html( $def['label'] ); ?> <span class="promotur-muted">(<?php echo count( $users ); ?>)</span></h3>
		<div class="promotur-list">
			<?php foreach ( $users as $u ) : ?>
				<div class="promotur-row">
					<span class="promotur-row__main promotur-row__user">
						<?php echo promotur_avatar( $u->ID, 'promotur-avatar--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span>
							<span class="promotur-row__title"><?php echo esc_html( $u->display_name ); ?></span>
							<span class="promotur-row__meta"><?php echo esc_html( $u->user_email ); ?></span>
						</span>
					</span>
					<span class="promotur-pill is-muted"><?php echo esc_html( $def['label'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
