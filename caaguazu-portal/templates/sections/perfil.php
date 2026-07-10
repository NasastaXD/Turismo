<?php
/**
 * Perfil / portafolio del usuario actual (con nivel de confianza y estadísticas).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$identity = promotur_current_identity();
$uid      = caaguazu_account_id();
$pub      = get_posts( array(
	'post_type'      => PROMOTUR_Destinos::CPT,
	'post_status'    => 'publish',
	'meta_query'     => array( array( 'key' => PROMOTUR_Destinos::OWNER_META, 'value' => $uid ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	'posts_per_page' => 50,
) );
$total_views = 0;
foreach ( $pub as $p ) { $total_views += PROMOTUR_Stats::views( $p->ID ); }
$is_mini = ( 'promotur_mini' === promotur_user_role() );

$page_title = __( 'Mi perfil', 'caaguazu-portal' );
$body = function () use ( $identity, $uid, $pub, $total_views, $is_mini ) {
	?>
	<div class="promotur-profile">
		<?php echo promotur_avatar( $identity, 'promotur-avatar--lg' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div>
			<h2 class="promotur-h2"><?php echo esc_html( $identity['display_name'] ); ?></h2>
			<p class="promotur-muted">
				<?php echo esc_html( promotur_role_label() ); ?>
				<?php if ( $is_mini ) : ?> · <span class="promotur-pill is-approved"><?php echo esc_html( PROMOTUR_Stats::level_label( $uid ) ); ?></span><?php endif; ?>
			</p>
		</div>
	</div>

	<?php if ( $is_mini ) : ?>
		<div class="promotur-card promotur-trust">
			<h3 class="promotur-h3"><?php esc_html_e( 'Tu progreso de confianza', 'caaguazu-portal' ); ?></h3>
			<div class="promotur-trustbar">
				<?php
				$levels = PROMOTUR_Stats::levels();
				$cur    = PROMOTUR_Stats::get_level( $uid );
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

	<?php if ( 0 === $uid ) : // bypass de administrador de WP: sí tiene un perfil de WordPress que editar. ?>
		<p class="promotur-muted promotur-mt">
			<a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php esc_html_e( 'Editar mi perfil en WordPress →', 'caaguazu-portal' ); ?></a>
		</p>
	<?php endif; ?>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
