<?php
/**
 * Sidebar: marca, menú dinámico (gateado por capability + estado activo) y footer con logout.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$current      = promotur_current_route(); // slug de sección actual
$review_badge = PROMOTUR_Notifications::review_queue_count();
$tareas_badge = class_exists( 'PROMOTUR_Tareas' ) ? PROMOTUR_Tareas::pending_count_for( get_current_user_id() ) : 0;
?>
<aside class="promotur-sidebar" data-sidebar>
	<a class="promotur-brand" href="<?php echo esc_url( promotur_url( 'panel' ) ); ?>">
		<span class="promotur-brand__mark" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21V11"/><path d="M12 11c0-4 2-6 6-7-1 4-3 6-6 7Z"/><path d="M12 11C12 7 10 5 4 4c1 4 3 6 8 7Z"/></svg>
		</span>
		<span class="promotur-brand__text"><?php bloginfo( 'name' ); ?></span>
	</a>

	<nav class="promotur-nav" aria-label="<?php esc_attr_e( 'Navegación del panel', 'caaguazu-portal' ); ?>">
		<?php
		foreach ( promotur_nav_items() as $item ) {
			if ( ! promotur_can( $item['cap'] ) ) {
				continue;
			}
			$seg    = ( 'panel' === $item['route'] ) ? 'home' : substr( $item['route'], strlen( 'panel/' ) );
			$active = ( 'home' === $seg ) ? ( 'home' === $current ) : ( '' !== $current && 0 === strpos( $current, $seg ) );

			$badge_html = '';
			if ( ! empty( $item['badge'] ) && 'revision' === $item['badge'] && $review_badge > 0 ) {
				$badge_html = '<span class="promotur-nav__badge">' . esc_html( $review_badge ) . '</span>';
			} elseif ( ! empty( $item['badge'] ) && 'tareas' === $item['badge'] && $tareas_badge > 0 ) {
				$badge_html = '<span class="promotur-nav__badge">' . esc_html( $tareas_badge ) . '</span>';
			}
			printf(
				'<a class="promotur-nav__item%s" href="%s"%s>%s<span class="promotur-nav__label">%s</span>%s</a>',
				$active ? ' is-active' : '',
				esc_url( promotur_url( $item['route'] ) ),
				$active ? ' aria-current="page"' : '',
				promotur_icon( $item['icon'] ), // phpcs:ignore WordPress.Security.EscapeOutput -- SVG controlado
				esc_html( $item['label'] ),
				$badge_html // phpcs:ignore WordPress.Security.EscapeOutput
			);
		}
		?>
	</nav>

	<div class="promotur-sidebar__foot">
		<button type="button" class="promotur-nav__item promotur-install" data-install-app hidden>
			<?php echo promotur_icon( 'install' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="promotur-nav__label"><?php esc_html_e( 'Instalar app', 'caaguazu-portal' ); ?></span>
		</button>
		<a class="promotur-nav__item" href="<?php echo esc_url( promotur_url( 'salir' ) ); ?>">
			<?php echo promotur_icon( 'logout' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="promotur-nav__label"><?php esc_html_e( 'Cerrar sesión', 'caaguazu-portal' ); ?></span>
		</a>
	</div>
</aside>
