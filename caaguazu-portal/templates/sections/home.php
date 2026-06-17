<?php
/**
 * Inicio / pulso accionable del panel.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$uid        = get_current_user_id();
$user       = wp_get_current_user();
$can_review = current_user_can( 'promotur_review_content' );
$can_draft  = current_user_can( 'promotur_create_draft' );

/** Cuenta destinos por estado (y autor opcional). */
$count_by = function ( $estado, $author = 0 ) {
	$args = array(
		'post_type'      => PROMOTUR_Destinos::CPT,
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => '_promotur_estado', 'value' => (array) $estado, 'compare' => 'IN' ) ),
	);
	if ( $author ) { $args['author'] = $author; }
	$q = new WP_Query( $args );
	return (int) $q->found_posts;
};

$pulse = array();
if ( $can_review ) {
	$pulse[] = array( 'n' => PROMOTUR_Notifications::review_queue_count(), 'label' => __( 'esperan revisión', 'caaguazu-portal' ), 'url' => 'panel/revision', 'icon' => 'inbox' );
	$pulse[] = array( 'n' => $count_by( 'publicado' ), 'label' => __( 'publicados', 'caaguazu-portal' ), 'url' => 'panel/revision', 'icon' => 'check' );
}
if ( $can_draft ) {
	$pulse[] = array( 'n' => $count_by( 'necesita_cambios', $uid ), 'label' => __( 'esperan tu corrección', 'caaguazu-portal' ), 'url' => 'panel/mis-contenidos', 'icon' => 'edit' );
	$pulse[] = array( 'n' => $count_by( array( 'borrador', 'enviado', 'en_revision' ), $uid ), 'label' => __( 'en proceso', 'caaguazu-portal' ), 'url' => 'panel/mis-contenidos', 'icon' => 'doc' );
}
if ( current_user_can( 'promotur_moderate' ) ) {
	$pulse[] = array( 'n' => count( PROMOTUR_Resenas::pending() ), 'label' => __( 'reseñas por moderar', 'caaguazu-portal' ), 'url' => 'panel/moderacion', 'icon' => 'star' );
	$pulse[] = array( 'n' => PROMOTUR_Consultas::count_open(), 'label' => __( 'consultas sin responder', 'caaguazu-portal' ), 'url' => 'panel/moderacion', 'icon' => 'inbox' );
}

$page_title = __( 'Inicio', 'caaguazu-portal' );
$body = function () use ( $user, $pulse, $can_draft, $can_review ) {
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Tu pulso de hoy', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php
		/* translators: %s = nombre */
		printf( esc_html__( 'Hola, %s 👋', 'caaguazu-portal' ), esc_html( $user->display_name ) );
	?></h2>

	<?php if ( ! empty( $pulse ) ) : ?>
		<div class="promotur-grid promotur-grid--3 promotur-pulse">
			<?php foreach ( $pulse as $p ) : ?>
				<a class="promotur-card promotur-card--link promotur-stat" href="<?php echo esc_url( promotur_url( $p['url'] ) ); ?>">
					<span class="promotur-stat__icon"><?php echo promotur_icon( $p['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="promotur-stat__n"><?php echo esc_html( $p['n'] ); ?></span>
					<span class="promotur-stat__label"><?php echo esc_html( $p['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<h3 class="promotur-h3"><?php esc_html_e( 'Accesos rápidos', 'caaguazu-portal' ); ?></h3>
	<div class="promotur-grid promotur-grid--3">
		<?php
		$quick = array();
		if ( current_user_can( 'promotur_edit_destino' ) ) {
			$quick[] = array( 'icon' => 'edit', 'label' => __( 'Crear una ficha', 'caaguazu-portal' ), 'url' => 'panel/editor' );
		}
		if ( $can_draft ) {
			$quick[] = array( 'icon' => 'doc', 'label' => __( 'Mis contenidos', 'caaguazu-portal' ), 'url' => 'panel/mis-contenidos' );
		}
		if ( $can_review ) {
			$quick[] = array( 'icon' => 'inbox', 'label' => __( 'Cola de revisión', 'caaguazu-portal' ), 'url' => 'panel/revision' );
		}
		if ( current_user_can( 'promotur_manage_team' ) ) {
			$quick[] = array( 'icon' => 'team', 'label' => __( 'Equipo', 'caaguazu-portal' ), 'url' => 'panel/equipo' );
		}
		$quick[] = array( 'icon' => 'user', 'label' => __( 'Mi perfil', 'caaguazu-portal' ), 'url' => 'panel/perfil' );
		foreach ( $quick as $qk ) :
			?>
			<a class="promotur-quick" href="<?php echo esc_url( promotur_url( $qk['url'] ) ); ?>">
				<?php echo promotur_icon( $qk['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span><?php echo esc_html( $qk['label'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
