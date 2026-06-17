<?php
/**
 * Restablecer contraseña (con login + key). Recibe: $error, $login, $key, $valid_key.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$error     = isset( $error ) ? $error : '';
$login     = isset( $login ) ? $login : '';
$key       = isset( $key ) ? $key : '';
$valid_key = isset( $valid_key ) ? $valid_key : false;

$page_title = __( 'Nueva contraseña', 'caaguazu-portal' );
$body = function () use ( $error, $login, $key, $valid_key ) {
	?>
	<h1 class="promotur-auth__title"><?php esc_html_e( 'Nueva contraseña', 'caaguazu-portal' ); ?></h1>

	<?php if ( $error ) : ?>
		<div class="promotur-notice promotur-notice--error"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

	<?php if ( $valid_key ) : ?>
		<form class="promotur-form" method="post">
			<?php wp_nonce_field( 'promotur_restablecer', 'promotur_nonce' ); ?>
			<input type="hidden" name="promotur_auth" value="restablecer">
			<input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>">
			<input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
			<label class="promotur-field">
				<span><?php esc_html_e( 'Nueva contraseña (6+ caracteres)', 'caaguazu-portal' ); ?></span>
				<input type="password" name="pass1" autocomplete="new-password" minlength="6" required autofocus>
			</label>
			<button type="submit" class="promotur-btn promotur-btn--primary promotur-btn--block"><?php esc_html_e( 'Guardar contraseña', 'caaguazu-portal' ); ?></button>
		</form>
	<?php else : ?>
		<p class="promotur-auth__sub"><?php esc_html_e( 'El enlace no es válido o venció. Pedí uno nuevo.', 'caaguazu-portal' ); ?></p>
		<div class="promotur-auth__links">
			<a href="<?php echo esc_url( promotur_url( 'recuperar' ) ); ?>"><?php esc_html_e( 'Pedir un nuevo enlace', 'caaguazu-portal' ); ?></a>
		</div>
	<?php endif; ?>
	<?php
};

include PROMOTUR_DIR . 'templates/auth-shell.php';
