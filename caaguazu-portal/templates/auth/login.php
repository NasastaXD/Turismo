<?php
/**
 * Login. Recibe: $error, $notice, $next.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$error  = isset( $error ) ? $error : '';
$notice = isset( $notice ) ? $notice : '';
$next   = isset( $next ) ? $next : '';
$reset  = isset( $_GET['reset'] ); // phpcs:ignore WordPress.Security.NonceVerification

$page_title = __( 'Iniciar sesión', 'caaguazu-portal' );
$body = function () use ( $error, $notice, $next, $reset ) {
	?>
	<h1 class="promotur-auth__title"><?php esc_html_e( 'Iniciar sesión', 'caaguazu-portal' ); ?></h1>
	<p class="promotur-auth__sub"><?php esc_html_e( 'Entrá al panel de Promotores Turísticos.', 'caaguazu-portal' ); ?></p>

	<?php if ( $reset ) : ?>
		<div class="promotur-notice promotur-notice--success"><?php esc_html_e( 'Tu contraseña fue actualizada. Ya podés entrar.', 'caaguazu-portal' ); ?></div>
	<?php endif; ?>
	<?php if ( $error ) : ?>
		<div class="promotur-notice promotur-notice--error"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>
	<?php if ( $notice ) : ?>
		<div class="promotur-notice promotur-notice--info"><?php echo esc_html( $notice ); ?></div>
	<?php endif; ?>

	<form class="promotur-form" method="post">
		<?php wp_nonce_field( 'promotur_login', 'promotur_nonce' ); ?>
		<input type="hidden" name="promotur_auth" value="login">
		<input type="hidden" name="next" value="<?php echo esc_attr( $next ); ?>">

		<label class="promotur-field">
			<span><?php esc_html_e( 'Email', 'caaguazu-portal' ); ?></span>
			<input type="email" name="user_login" autocomplete="username" required autofocus>
		</label>
		<label class="promotur-field">
			<span><?php esc_html_e( 'Contraseña', 'caaguazu-portal' ); ?></span>
			<input type="password" name="user_pass" autocomplete="current-password" required>
		</label>
		<label class="promotur-check">
			<input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Mantener sesión', 'caaguazu-portal' ); ?>
		</label>

		<button type="submit" class="promotur-btn promotur-btn--primary promotur-btn--block"><?php esc_html_e( 'Entrar', 'caaguazu-portal' ); ?></button>
	</form>

	<div class="promotur-auth__links">
		<a href="<?php echo esc_url( promotur_url( 'recuperar' ) ); ?>"><?php esc_html_e( '¿Olvidaste tu contraseña?', 'caaguazu-portal' ); ?></a>
<span class="promotur-muted"><?php esc_html_e( 'Acceso solo por invitación', 'caaguazu-portal' ); ?></span>
	</div>
	<?php
};

include PROMOTUR_DIR . 'templates/auth-shell.php';
