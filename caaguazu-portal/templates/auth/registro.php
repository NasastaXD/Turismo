<?php
/**
 * Registro (con token de invitación opcional). Recibe: $error, $notice, $next, $token.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$error  = isset( $error ) ? $error : '';
$next   = isset( $next ) ? $next : '';
$token  = isset( $token ) ? $token : '';
$invite = PROMOTUR_Auth::instance()->get_invite( $token );
$role_label = $invite ? PROMOTUR_Roles::label( $invite['role'] ) : '';

$page_title = __( 'Crear cuenta', 'caaguazu-portal' );
$body = function () use ( $error, $next, $token, $invite, $role_label ) {
	?>
	<h1 class="promotur-auth__title"><?php esc_html_e( 'Crear cuenta', 'caaguazu-portal' ); ?></h1>

	<?php if ( $invite ) : ?>
		<div class="promotur-notice promotur-notice--success">
			<?php
			/* translators: %s = rol */
			printf( esc_html__( 'Invitación válida: te unirás como %s.', 'caaguazu-portal' ), '<strong>' . esc_html( $role_label ) . '</strong>' );
			?>
		</div>
	<?php else : ?>
		<p class="promotur-auth__sub"><?php esc_html_e( 'Creá tu cuenta de visitante. Las cuentas de Mini Promotor se obtienen por invitación del equipo.', 'caaguazu-portal' ); ?></p>
	<?php endif; ?>

	<?php if ( $error ) : ?>
		<div class="promotur-notice promotur-notice--error"><?php echo esc_html( $error ); ?></div>
	<?php endif; ?>

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
