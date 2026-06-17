<?php
/**
 * Registro INVITE-ONLY. Recibe: $error, $next, $token, $invite_status, $invite_role.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$error         = isset( $error ) ? $error : '';
$next          = isset( $next ) ? $next : '';
$token         = isset( $token ) ? $token : '';
$invite_status = isset( $invite_status ) ? $invite_status : 'invalid';
$invite_role   = isset( $invite_role ) ? $invite_role : '';
$is_valid      = ( 'valid' === $invite_status );

$page_title = __( 'Crear cuenta', 'caaguazu-portal' );
$body = function () use ( $error, $next, $token, $invite_status, $invite_role, $is_valid ) {
	?>
	<h1 class="promotur-auth__title"><?php esc_html_e( 'Crear cuenta', 'caaguazu-portal' ); ?></h1>

	<?php if ( $error ) : ?>
		<div class="promotur-notice promotur-notice--error"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

	<?php if ( ! $is_valid ) : ?>
		<div class="promotur-notice promotur-notice--info">
			<?php
			switch ( $invite_status ) {
				case 'used':    esc_html_e( 'Esa invitación ya fue usada.', 'caaguazu-portal' ); break;
				case 'expired': esc_html_e( 'Esa invitación expiró. Pedí una nueva al equipo.', 'caaguazu-portal' ); break;
				case 'revoked': esc_html_e( 'Esa invitación fue revocada.', 'caaguazu-portal' ); break;
				default:        esc_html_e( 'El registro es solo por invitación. Pedí tu enlace al equipo de Turismo.', 'caaguazu-portal' );
			}
			?>
		</div>
		<div class="promotur-auth__links">
			<a href="<?php echo esc_url( promotur_url( 'login' ) ); ?>"><?php esc_html_e( 'Ya tengo cuenta', 'caaguazu-portal' ); ?></a>
		</div>
		<?php
		return;
	endif; ?>

	<div class="promotur-notice promotur-notice--success">
		<?php
		/* translators: %s = rol */
		printf( esc_html__( 'Invitación válida: te unirás como %s.', 'caaguazu-portal' ), '<strong>' . esc_html( $invite_role ) . '</strong>' );
		?>
	</div>

	<form class="promotur-form" method="post">
		<?php wp_nonce_field( 'promotur_registro', 'promotur_nonce' ); ?>
		<input type="hidden" name="promotur_auth" value="registro">
		<input type="hidden" name="next" value="<?php echo esc_attr( $next ); ?>">
		<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">

		<label class="promotur-field">
			<span><?php esc_html_e( 'Nombre de usuario', 'caaguazu-portal' ); ?></span>
			<input type="text" name="user_login" autocomplete="username" required autofocus>
		</label>
		<label class="promotur-field">
			<span><?php esc_html_e( 'Email', 'caaguazu-portal' ); ?></span>
			<input type="email" name="email" autocomplete="email" required>
		</label>
		<label class="promotur-field">
			<span><?php esc_html_e( 'Teléfono', 'caaguazu-portal' ); ?></span>
			<input type="tel" name="phone" autocomplete="tel" inputmode="tel" required placeholder="<?php esc_attr_e( 'Ej: 0981 123 456', 'caaguazu-portal' ); ?>">
		</label>
		<label class="promotur-field">
			<span><?php esc_html_e( 'Contraseña (6+ caracteres)', 'caaguazu-portal' ); ?></span>
			<input type="password" name="user_pass" autocomplete="new-password" minlength="6" required>
		</label>

		<button type="submit" class="promotur-btn promotur-btn--primary promotur-btn--block"><?php esc_html_e( 'Crear cuenta', 'caaguazu-portal' ); ?></button>
	</form>

	<div class="promotur-auth__links">
		<a href="<?php echo esc_url( promotur_url( 'login' ) ); ?>"><?php esc_html_e( 'Ya tengo cuenta', 'caaguazu-portal' ); ?></a>
	</div>
	<?php
};

include PROMOTUR_DIR . 'templates/auth-shell.php';
