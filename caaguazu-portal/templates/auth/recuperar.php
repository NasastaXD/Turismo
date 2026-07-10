<?php
/**
 * Recuperar contraseña (solicitar enlace). Recibe: $error, $notice.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$error  = isset( $error ) ? $error : '';
$notice = isset( $notice ) ? $notice : '';

$page_title = __( 'Recuperar contraseña', 'caaguazu-portal' );
$body = function () use ( $error, $notice ) {
	?>
	<h1 class="promotur-auth__title"><?php esc_html_e( 'Recuperar contraseña', 'caaguazu-portal' ); ?></h1>
	<p class="promotur-auth__sub"><?php esc_html_e( 'Te enviamos un enlace para restablecerla.', 'caaguazu-portal' ); ?></p>

	<?php if ( $error ) : ?>
		<div class="promotur-notice promotur-notice--error"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>
	<?php if ( $notice ) : ?>
		<div class="promotur-notice promotur-notice--success"><?php echo esc_html( $notice ); ?></div>
	<?php endif; ?>

	<form class="promotur-form" method="post">
		<?php wp_nonce_field( 'promotur_recuperar', 'promotur_nonce' ); ?>
		<input type="hidden" name="promotur_auth" value="recuperar">
		<label class="promotur-field">
			<span><?php esc_html_e( 'Email', 'caaguazu-portal' ); ?></span>
			<input type="email" name="user_login" required autofocus>
		</label>
		<button type="submit" class="promotur-btn promotur-btn--primary promotur-btn--block"><?php esc_html_e( 'Enviar enlace', 'caaguazu-portal' ); ?></button>
	</form>

	<div class="promotur-auth__links">
		<a href="<?php echo esc_url( promotur_url( 'login' ) ); ?>"><?php esc_html_e( 'Volver a iniciar sesión', 'caaguazu-portal' ); ?></a>
	</div>
	<?php
};

include PROMOTUR_DIR . 'templates/auth-shell.php';
