<?php
/**
 * Perfil / portafolio del usuario actual (con nivel de confianza y estadísticas).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$user = wp_get_current_user();
$pub  = get_posts( array(
	'post_type'      => PROMOTUR_Destinos::CPT,
	'post_status'    => 'publish',
	'author'         => $user->ID,
	'posts_per_page' => 50,
) );
$total_views = 0;
foreach ( $pub as $p ) { $total_views += PROMOTUR_Stats::views( $p->ID ); }
$is_mini = in_array( 'promotur_mini', (array) $user->roles, true );

$page_title = __( 'Mi perfil', 'caaguazu-portal' );
$body = function () use ( $user, $pub, $total_views, $is_mini ) {
	?>
	<div class="promotur-profile">
		<?php echo promotur_avatar( $user->ID, 'promotur-avatar--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div>
			<h2 class="promotur-h2"><?php echo esc_html( $user->display_name ); ?></h2>
			<p class="promotur-muted">
				<?php echo esc_html( promotur_role_label() ); ?>
				<?php if ( $is_mini ) : ?> · <span class="promotur-pill is-approved"><?php echo esc_html( PROMOTUR_Stats::level_label( $user->ID ) ); ?></span><?php endif; ?>
			</p>
		</div>
	</div>

	<?php if ( $is_mini ) : ?>
		<div class="promotur-card promotur-trust">
			<h3 class="promotur-h3"><?php esc_html_e( 'Tu progreso de confianza', 'caaguazu-portal' ); ?></h3>
			<div class="promotur-trustbar">
				<?php
				$levels = PROMOTUR_Stats::levels();
				$cur    = PROMOTUR_Stats::get_level( $user->ID );
				$keys   = array_keys( $levels );
				$ci     = array_search( $cur, $keys, true );
				foreach ( $keys as $idx => $lk ) : ?>
					<span class="promotur-truststep<?php echo $idx <= $ci ? ' is-on' : ''; ?>"><?php echo esc_html( $levels[ $lk ] ); ?></span>
				<?php endforeach; ?>
			</div>
			<p class="promotur-muted">
				<?php
				if ( 'confianza' === $cur ) {
					esc_html_e( 'Nivel máximo: publicás directo, con auditoría posterior. ¡Gracias por tu compromiso!', 'caaguazu-portal' );
				} elseif ( 'jr' === $cur ) {
					esc_html_e( 'Promotor Jr: editás fichas publicadas sin re-revisión. Seguí sumando aprobaciones para llegar a "De confianza".', 'caaguazu-portal' );
				} else {
					esc_html_e( 'Aprendiz: todo tu contenido pasa por revisión. Con más aprobaciones desbloqueás autonomía.', 'caaguazu-portal' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="promotur-grid promotur-grid--3">
		<div class="promotur-card promotur-stat">
			<span class="promotur-stat__n"><?php echo esc_html( count( $pub ) ); ?></span>
			<span class="promotur-stat__label"><?php esc_html_e( 'fichas publicadas', 'caaguazu-portal' ); ?></span>
		</div>
		<div class="promotur-card promotur-stat">
			<span class="promotur-stat__n"><?php echo esc_html( number_format_i18n( $total_views ) ); ?></span>
			<span class="promotur-stat__label"><?php esc_html_e( 'vistas en total', 'caaguazu-portal' ); ?></span>
		</div>
	</div>

	<h3 class="promotur-h3"><?php esc_html_e( 'Mi portafolio', 'caaguazu-portal' ); ?></h3>
	<?php if ( empty( $pub ) ) : ?>
		<p class="promotur-muted"><?php esc_html_e( 'Todavía no tenés fichas publicadas.', 'caaguazu-portal' ); ?></p>
	<?php else : ?>
		<div class="promotur-list">
			<?php foreach ( $pub as $p ) : ?>
				<a class="promotur-row" href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener">
					<span class="promotur-row__main">
						<span class="promotur-row__title"><?php echo esc_html( get_the_title( $p ) ); ?></span>
						<span class="promotur-row__meta"><?php
							/* translators: %d = vistas */
							printf( esc_html( _n( '%d vista', '%d vistas', PROMOTUR_Stats::views( $p->ID ), 'caaguazu-portal' ) ), PROMOTUR_Stats::views( $p->ID ) );
						?></span>
					</span>
					<span class="promotur-pill is-published"><?php esc_html_e( 'Publicado', 'caaguazu-portal' ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<p class="promotur-muted promotur-mt">
		<a class="promotur-pjax-skip" href="<?php echo esc_url( promotur_url( 'salir' ) ); ?>"><?php esc_html_e( 'Cerrar sesión', 'caaguazu-portal' ); ?></a>
	</p>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
