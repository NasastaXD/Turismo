<?php
/**
 * Topbar: hamburguesa (móvil) + título; buscador, toggle de tema, notificaciones y user chip.
 * Espera $page_title en scope.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$user    = wp_get_current_user();
$notifs  = PROMOTUR_Notifications::instance();
$items   = $notifs->get_items();
$unread  = $notifs->get_unread_count();
$q       = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
?>
<header class="promotur-topbar">
	<button type="button" class="promotur-hamburger" data-drawer-toggle aria-label="<?php esc_attr_e( 'Abrir menú', 'caaguazu-portal' ); ?>" aria-expanded="false">
		<?php echo promotur_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</button>

	<h1 class="promotur-topbar__title"><?php echo esc_html( isset( $page_title ) ? $page_title : '' ); ?></h1>

	<div class="promotur-topbar__actions">
		<form class="promotur-search" method="get" action="<?php echo esc_url( promotur_url( 'panel/buscar' ) ); ?>" role="search">
			<span class="promotur-search__icon"><?php echo promotur_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Buscar…', 'caaguazu-portal' ); ?>" aria-label="<?php esc_attr_e( 'Buscar', 'caaguazu-portal' ); ?>">
		</form>

		<button type="button" class="promotur-iconbtn" data-theme-toggle aria-label="<?php esc_attr_e( 'Cambiar tema', 'caaguazu-portal' ); ?>">
			<span class="promotur-theme-light"><?php echo promotur_icon( 'sun' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="promotur-theme-dark"><?php echo promotur_icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		</button>

		<div class="promotur-notifs" data-dropdown>
			<button type="button" class="promotur-iconbtn" data-dropdown-toggle aria-label="<?php esc_attr_e( 'Notificaciones', 'caaguazu-portal' ); ?>" aria-expanded="false">
				<?php echo promotur_icon( 'bell' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( $unread > 0 ) : ?>
					<span class="promotur-notifs__badge"><?php echo esc_html( $unread > 9 ? '9+' : $unread ); ?></span>
				<?php endif; ?>
			</button>
			<div class="promotur-dropdown promotur-dropdown--notifs" data-dropdown-panel hidden>
				<div class="promotur-dropdown__head">
					<strong><?php esc_html_e( 'Notificaciones', 'caaguazu-portal' ); ?></strong>
					<?php if ( ! empty( $items ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'promotur_mark_read' ); ?>
							<input type="hidden" name="action" value="promotur_mark_read">
							<button type="submit" class="promotur-link-btn"><?php esc_html_e( 'Marcar todo leído', 'caaguazu-portal' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
				<div class="promotur-dropdown__body">
					<?php if ( empty( $items ) ) : ?>
						<p class="promotur-empty"><?php esc_html_e( 'Sin novedades por ahora. ✨', 'caaguazu-portal' ); ?></p>
					<?php else : ?>
						<?php foreach ( $items as $it ) : ?>
							<a class="promotur-notif" href="<?php echo esc_url( $it['url'] ); ?>">
								<span class="promotur-notif__icon"><?php echo promotur_icon( $it['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
								<span class="promotur-notif__main">
									<span class="promotur-notif__title"><?php echo esc_html( $it['title'] ); ?></span>
									<span class="promotur-notif__when"><?php echo esc_html( $it['when'] ); ?></span>
								</span>
							</a>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<a class="promotur-userchip" href="<?php echo esc_url( promotur_url( 'panel/perfil' ) ); ?>">
			<?php echo promotur_avatar( $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span class="promotur-userchip__meta">
				<span class="promotur-userchip__name"><?php echo esc_html( $user->display_name ); ?></span>
				<span class="promotur-userchip__role"><?php echo esc_html( promotur_role_label() ); ?></span>
			</span>
		</a>
	</div>
</header>
