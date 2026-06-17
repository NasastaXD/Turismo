<?php
/**
 * Perfil / portafolio del usuario actual.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$user = wp_get_current_user();
$pub  = get_posts( array(
	'post_type'      => PROMOTUR_Destinos::CPT,
	'post_status'    => 'publish',
	'author'         => $user->ID,
	'posts_per_page' => 50,
) );

$page_title = __( 'Mi perfil', 'caaguazu-portal' );
$body = function () use ( $user, $pub ) {
	?>
	<div class="promotur-profile">
		<?php echo promotur_avatar( $user->ID, 'promotur-avatar--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div>
			<h2 class="promotur-h2"><?php echo esc_html( $user->display_name ); ?></h2>
			<p class="promotur-muted"><?php echo esc_html( promotur_role_label() ); ?> · <?php echo esc_html( $user->user_email ); ?></p>
		</div>
	</div>

	<div class="promotur-grid promotur-grid--3">
		<div class="promotur-card promotur-stat">
			<span class="promotur-stat__n"><?php echo esc_html( count( $pub ) ); ?></span>
			<span class="promotur-stat__label"><?php esc_html_e( 'fichas publicadas', 'caaguazu-portal' ); ?></span>
		</div>
	</div>

	<h3 class="promotur-h3"><?php esc_html_e( 'Mi portafolio', 'caaguazu-portal' ); ?></h3>
	<?php if ( empty( $pub ) ) : ?>
		<p class="promotur-muted"><?php esc_html_e( 'Todavía no tenés fichas publicadas.', 'caaguazu-portal' ); ?></p>
	<?php else : ?>
		<div class="promotur-list">
			<?php foreach ( $pub as $p ) : ?>
				<a class="promotur-row" href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener">
					<span class="promotur-row__main"><span class="promotur-row__title"><?php echo esc_html( get_the_title( $p ) ); ?></span></span>
					<span class="promotur-pill is-published"><?php esc_html_e( 'Publicado', 'caaguazu-portal' ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<p class="promotur-muted promotur-mt">
		<a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php esc_html_e( 'Editar mi perfil en WordPress →', 'caaguazu-portal' ); ?></a>
	</p>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
