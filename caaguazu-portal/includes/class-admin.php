<?php
/**
 * Gestión en wp-admin (estilo CEAD): Usuarios, Invitaciones y Logs.
 * Gateado por la capability promotur_manage_users.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Admin {

	private static $instance = null;
	const CAP = 'promotur_manage_users';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Capability para la página de Actualizaciones (la tienen los administradores). */
	const CAP_UPDATES = 'update_plugins';

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_promotur_admin_users', array( $this, 'handle_users' ) );
		add_action( 'admin_post_promotur_admin_invites', array( $this, 'handle_invites' ) );
		add_action( 'admin_post_promotur_admin_updates', array( $this, 'handle_updates' ) );
	}

	/* ----- Menú ----- */
	public function menu() {
		if ( ! current_user_can( self::CAP ) && ! current_user_can( self::CAP_UPDATES ) ) { return; }

		// El menú padre y su slug por defecto se gatean por la capability que tenga el rol.
		$parent_cap = current_user_can( self::CAP ) ? self::CAP : self::CAP_UPDATES;
		$parent_cb  = current_user_can( self::CAP ) ? array( $this, 'render_users' ) : array( $this, 'render_updates' );
		add_menu_page( __( 'Portal Turismo', 'caaguazu-portal' ), __( 'Portal Turismo', 'caaguazu-portal' ), $parent_cap, 'promotur', $parent_cb, 'dashicons-palmtree', 57 );

		if ( current_user_can( self::CAP ) ) {
			add_submenu_page( 'promotur', __( 'Usuarios', 'caaguazu-portal' ), __( 'Usuarios', 'caaguazu-portal' ), self::CAP, 'promotur', array( $this, 'render_users' ) );
			add_submenu_page( 'promotur', __( 'Invitaciones', 'caaguazu-portal' ), __( 'Invitaciones', 'caaguazu-portal' ), self::CAP, 'promotur-invites', array( $this, 'render_invites' ) );
			add_submenu_page( 'promotur', __( 'Logs', 'caaguazu-portal' ), __( 'Logs', 'caaguazu-portal' ), self::CAP, 'promotur-logs', array( $this, 'render_logs' ) );
		}
		add_submenu_page( 'promotur', __( 'Actualizaciones', 'caaguazu-portal' ), __( 'Actualizaciones', 'caaguazu-portal' ), self::CAP_UPDATES, 'promotur-updates', array( $this, 'render_updates' ) );
	}

	private function notice( $msg, $type = 'success' ) {
		set_transient( 'promotur_admin_notice_' . get_current_user_id(), array( 'm' => $msg, 't' => $type ), 60 );
	}
	private function show_notice() {
		$n = get_transient( 'promotur_admin_notice_' . get_current_user_id() );
		if ( $n ) {
			delete_transient( 'promotur_admin_notice_' . get_current_user_id() );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $n['t'] ), wp_kses_post( $n['m'] ) );
		}
	}
	private function guard() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'No autorizado.', 'caaguazu-portal' ) );
		}
	}

	/* ================= USUARIOS ================= */
	public function render_users() {
		$this->guard();
		$roles   = PROMOTUR_Roles::roles();
		$users   = get_users( array( 'role__in' => array_keys( $roles ), 'orderby' => 'display_name', 'number' => 500 ) );
		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$edit    = $edit_id ? get_userdata( $edit_id ) : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Usuarios del portal', 'caaguazu-portal' ); ?></h1>
			<?php $this->show_notice(); ?>

			<?php if ( $edit ) : ?>
				<h2><?php printf( esc_html__( 'Editar: %s', 'caaguazu-portal' ), esc_html( $edit->display_name ) ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'promotur_admin_users' ); ?>
					<input type="hidden" name="action" value="promotur_admin_users">
					<input type="hidden" name="op" value="edit">
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $edit->ID ); ?>">
					<table class="form-table"><tbody>
						<tr><th><?php esc_html_e( 'Nombre', 'caaguazu-portal' ); ?></th><td><input type="text" name="display_name" class="regular-text" value="<?php echo esc_attr( $edit->display_name ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Email', 'caaguazu-portal' ); ?></th><td><input type="email" name="email" class="regular-text" value="<?php echo esc_attr( $edit->user_email ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Teléfono', 'caaguazu-portal' ); ?></th><td><input type="text" name="phone" class="regular-text" value="<?php echo esc_attr( get_user_meta( $edit->ID, '_promotur_phone', true ) ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Rol', 'caaguazu-portal' ); ?></th><td>
							<select name="role"><?php $cur = promotur_user_role( $edit->ID );
							foreach ( $roles as $rk => $rd ) { printf( '<option value="%s" %s>%s</option>', esc_attr( $rk ), selected( $cur, $rk, false ), esc_html( $rd['label'] ) ); } ?></select>
						</td></tr>
						<tr><th><?php esc_html_e( 'Resetear contraseña', 'caaguazu-portal' ); ?></th><td><label><input type="checkbox" name="reset_pass" value="1"> <?php esc_html_e( 'Generar una nueva y mostrarla', 'caaguazu-portal' ); ?></label></td></tr>
					</tbody></table>
					<p><button class="button button-primary"><?php esc_html_e( 'Guardar cambios', 'caaguazu-portal' ); ?></button>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=promotur' ) ); ?>"><?php esc_html_e( 'Cancelar', 'caaguazu-portal' ); ?></a></p>
				</form>
				<hr>
			<?php endif; ?>

			<table class="widefat striped">
				<thead><tr>
					<th>ID</th><th><?php esc_html_e( 'Usuario', 'caaguazu-portal' ); ?></th><th><?php esc_html_e( 'Nombre', 'caaguazu-portal' ); ?></th>
					<th><?php esc_html_e( 'Email', 'caaguazu-portal' ); ?></th><th><?php esc_html_e( 'Teléfono', 'caaguazu-portal' ); ?></th>
					<th><?php esc_html_e( 'Rol', 'caaguazu-portal' ); ?></th><th><?php esc_html_e( 'Estado', 'caaguazu-portal' ); ?></th><th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $users as $u ) :
					$suspended = (bool) get_user_meta( $u->ID, '_promotur_suspended', true );
					$role_key  = promotur_user_role( $u->ID ); ?>
					<tr>
						<td><?php echo (int) $u->ID; ?></td>
						<td><?php echo esc_html( $u->user_login ); ?></td>
						<td><?php echo esc_html( $u->display_name ); ?></td>
						<td><?php echo esc_html( $u->user_email ); ?></td>
						<td><?php echo esc_html( get_user_meta( $u->ID, '_promotur_phone', true ) ); ?></td>
						<td><?php echo esc_html( isset( $roles[ $role_key ] ) ? $roles[ $role_key ]['label'] : $role_key ); ?></td>
						<td><?php echo $suspended ? '<span style="color:#b32d2e">' . esc_html__( 'Suspendido', 'caaguazu-portal' ) . '</span>' : esc_html__( 'Activo', 'caaguazu-portal' ); ?></td>
						<td>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=promotur&edit=' . $u->ID ) ); ?>"><?php esc_html_e( 'Editar', 'caaguazu-portal' ); ?></a>
							<?php $this->row_action( $u->ID, $suspended ? 'reactivate' : 'suspend', $suspended ? __( 'Reactivar', 'caaguazu-portal' ) : __( 'Suspender', 'caaguazu-portal' ) ); ?>
							<?php $this->row_action( $u->ID, 'delete', __( 'Eliminar', 'caaguazu-portal' ), true ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function row_action( $user_id, $op, $label, $confirm = false ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"<?php echo $confirm ? ' onsubmit="return confirm(\'' . esc_js( __( '¿Seguro? Esta acción no se puede deshacer.', 'caaguazu-portal' ) ) . '\')"' : ''; ?>>
			<?php wp_nonce_field( 'promotur_admin_users' ); ?>
			<input type="hidden" name="action" value="promotur_admin_users">
			<input type="hidden" name="op" value="<?php echo esc_attr( $op ); ?>">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
			<button class="button button-small"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	public function handle_users() {
		$this->guard();
		check_admin_referer( 'promotur_admin_users' );
		$op   = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
		$uid  = (int) ( $_POST['user_id'] ?? 0 );
		$me   = get_current_user_id();
		$user = $uid ? get_userdata( $uid ) : null;

		if ( ! $user ) {
			$this->notice( __( 'Usuario inválido.', 'caaguazu-portal' ), 'error' );
			$this->redirect_users();
		}
		if ( user_can( $uid, 'manage_options' ) || $uid === $me ) {
			$this->notice( __( 'No podés modificar a un administrador ni a vos mismo desde acá.', 'caaguazu-portal' ), 'error' );
			$this->redirect_users();
		}

		switch ( $op ) {
			case 'edit':
				$data = array( 'ID' => $uid,
					'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
					'user_email'   => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
				);
				wp_update_user( $data );
				update_user_meta( $uid, '_promotur_phone', sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ) );
				$new_role = sanitize_key( wp_unslash( $_POST['role'] ?? '' ) );
				if ( array_key_exists( $new_role, PROMOTUR_Roles::roles() ) ) {
					foreach ( array_keys( PROMOTUR_Roles::roles() ) as $rk ) { $user->remove_role( $rk ); }
					$user->add_role( $new_role );
				}
				$msg = __( 'Usuario actualizado.', 'caaguazu-portal' );
				if ( ! empty( $_POST['reset_pass'] ) ) {
					$pass = wp_generate_password( 12, false );
					wp_set_password( $pass, $uid );
					$msg .= ' ' . sprintf( __( 'Nueva contraseña: %s', 'caaguazu-portal' ), '<code>' . esc_html( $pass ) . '</code>' );
				}
				PROMOTUR_Audit::log( 'user_updated', array( 'entity_type' => 'user', 'entity_id' => $uid ) );
				$this->notice( $msg );
				break;

			case 'suspend':
				update_user_meta( $uid, '_promotur_suspended', time() );
				$tokens = WP_Session_Tokens::get_instance( $uid );
				$tokens->destroy_all();
				PROMOTUR_Audit::log( 'user_suspended', array( 'entity_type' => 'user', 'entity_id' => $uid ) );
				$this->notice( __( 'Usuario suspendido (se cerró su sesión).', 'caaguazu-portal' ) );
				break;

			case 'reactivate':
				delete_user_meta( $uid, '_promotur_suspended' );
				PROMOTUR_Audit::log( 'user_reactivated', array( 'entity_type' => 'user', 'entity_id' => $uid ) );
				$this->notice( __( 'Usuario reactivado.', 'caaguazu-portal' ) );
				break;

			case 'delete':
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $uid, $me ); // reasigna contenido al admin actual
				PROMOTUR_Audit::log( 'user_deleted', array( 'entity_type' => 'user', 'entity_id' => $uid ) );
				$this->notice( __( 'Usuario eliminado (su contenido se reasignó a tu cuenta).', 'caaguazu-portal' ) );
				break;
		}
		$this->redirect_users();
	}

	private function redirect_users() {
		wp_safe_redirect( admin_url( 'admin.php?page=promotur' ) );
		exit;
	}

	/* ================= INVITACIONES ================= */
	public function render_invites() {
		$this->guard();
		$roles = PROMOTUR_Roles::roles();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Invitaciones', 'caaguazu-portal' ); ?></h1>
			<?php $this->show_notice(); ?>

			<h2><?php esc_html_e( 'Crear invitación', 'caaguazu-portal' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'promotur_admin_invites' ); ?>
				<input type="hidden" name="action" value="promotur_admin_invites">
				<input type="hidden" name="op" value="create">
				<table class="form-table"><tbody>
					<tr><th><?php esc_html_e( 'Rol', 'caaguazu-portal' ); ?></th><td>
						<select name="role"><?php foreach ( $roles as $rk => $rd ) { printf( '<option value="%s">%s</option>', esc_attr( $rk ), esc_html( $rd['label'] ) ); } ?></select>
					</td></tr>
					<tr><th><?php esc_html_e( 'Email (opcional)', 'caaguazu-portal' ); ?></th><td><input type="email" name="email" class="regular-text"></td></tr>
					<tr><th><?php esc_html_e( 'Expira (días)', 'caaguazu-portal' ); ?></th><td><input type="number" name="expires_days" value="14" min="1" max="90"></td></tr>
					<tr><th><?php esc_html_e( 'Cantidad', 'caaguazu-portal' ); ?></th><td><input type="number" name="count" value="1" min="1" max="50"></td></tr>
				</tbody></table>
				<p><button class="button button-primary"><?php esc_html_e( 'Generar link(s)', 'caaguazu-portal' ); ?></button></p>
			</form>

			<h2><?php esc_html_e( 'Invitaciones recientes', 'caaguazu-portal' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Estado', 'caaguazu-portal' ); ?></th><th><?php esc_html_e( 'Rol', 'caaguazu-portal' ); ?></th>
					<th><?php esc_html_e( 'Email', 'caaguazu-portal' ); ?></th><th><?php esc_html_e( 'Expira', 'caaguazu-portal' ); ?></th>
					<th><?php esc_html_e( 'Link', 'caaguazu-portal' ); ?></th><th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( PROMOTUR_Invitations::recent( 50 ) as $row ) :
					$status = PROMOTUR_Invitations::status( $row );
					$token  = PROMOTUR_Invitations::plain_token( $row );
					$link   = $token ? PROMOTUR_Invitations::registration_url( $token ) : '';
					$rk     = $row['role']; ?>
					<tr>
						<td><?php echo esc_html( PROMOTUR_Invitations::status_label( $status ) ); ?></td>
						<td><?php echo esc_html( isset( $roles[ $rk ] ) ? $roles[ $rk ]['label'] : $rk ); ?></td>
						<td><?php echo esc_html( $row['email'] ? $row['email'] : '—' ); ?></td>
						<td><?php echo esc_html( $row['expires_at'] ); ?></td>
						<td><?php if ( 'valid' === $status && $link ) : ?><input type="text" readonly value="<?php echo esc_attr( $link ); ?>" class="regular-text" onclick="this.select()"><?php else : ?>—<?php endif; ?></td>
						<td>
							<?php if ( 'valid' === $status ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
									<?php wp_nonce_field( 'promotur_admin_invites' ); ?>
									<input type="hidden" name="action" value="promotur_admin_invites">
									<input type="hidden" name="op" value="revoke">
									<input type="hidden" name="id" value="<?php echo esc_attr( $row['id'] ); ?>">
									<button class="button button-small"><?php esc_html_e( 'Revocar', 'caaguazu-portal' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_invites() {
		$this->guard();
		check_admin_referer( 'promotur_admin_invites' );
		$op = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );

		if ( 'create' === $op ) {
			$tokens = PROMOTUR_Invitations::create( array(
				'role'         => sanitize_key( wp_unslash( $_POST['role'] ?? 'promotur_mini' ) ),
				'email'        => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
				'expires_days' => (int) ( $_POST['expires_days'] ?? 14 ),
				'count'        => (int) ( $_POST['count'] ?? 1 ),
			) );
			$links = array_map( array( 'PROMOTUR_Invitations', 'registration_url' ), $tokens );
			$this->notice( __( 'Invitación(es) creada(s):', 'caaguazu-portal' ) . '<br>' . implode( '<br>', array_map( 'esc_html', $links ) ) );
		} elseif ( 'revoke' === $op ) {
			PROMOTUR_Invitations::revoke( (int) ( $_POST['id'] ?? 0 ) );
			$this->notice( __( 'Invitación revocada.', 'caaguazu-portal' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=promotur-invites' ) );
		exit;
	}

	/* ================= LOGS ================= */
	public function render_logs() {
		$this->guard();
		$tab   = isset( $_GET['tab'] ) && 'posts' === $_GET['tab'] ? 'posts' : 'usuarios'; // phpcs:ignore WordPress.Security.NonceVerification
		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification

		$actions = 'posts' === $tab
			? PROMOTUR_Audit::post_actions()
			: array( 'login_success', 'login_failed', 'user_registered', 'invitation_created', 'invitation_used', 'invitation_revoked', 'user_suspended', 'user_reactivated', 'user_updated', 'user_deleted' );

		$res = PROMOTUR_Audit::query( array( 'actions' => $actions, 'paged' => $paged, 'per_page' => 50 ) );
		$base = admin_url( 'admin.php?page=promotur-logs&tab=' . $tab );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Logs', 'caaguazu-portal' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab <?php echo 'usuarios' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=promotur-logs&tab=usuarios' ) ); ?>"><?php esc_html_e( 'Usuarios', 'caaguazu-portal' ); ?></a>
				<a class="nav-tab <?php echo 'posts' === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=promotur-logs&tab=posts' ) ); ?>"><?php esc_html_e( 'Posts', 'caaguazu-portal' ); ?></a>
			</h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Fecha', 'caaguazu-portal' ); ?></th><th><?php esc_html_e( 'Usuario', 'caaguazu-portal' ); ?></th>
					<th><?php esc_html_e( 'Acción', 'caaguazu-portal' ); ?></th><th><?php esc_html_e( 'Entidad', 'caaguazu-portal' ); ?></th>
					<th><?php esc_html_e( 'IP', 'caaguazu-portal' ); ?></th><th><?php esc_html_e( 'Detalle', 'caaguazu-portal' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( empty( $res['rows'] ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Sin registros.', 'caaguazu-portal' ); ?></td></tr>
				<?php else : foreach ( $res['rows'] as $r ) :
					$u = $r['user_id'] ? get_userdata( $r['user_id'] ) : null; ?>
					<tr>
						<td><?php echo esc_html( $r['created_at'] ); ?></td>
						<td><?php echo esc_html( $u ? $u->display_name : ( $r['user_id'] ? '#' . $r['user_id'] : '—' ) ); ?></td>
						<td><code><?php echo esc_html( $r['action'] ); ?></code></td>
						<td><?php echo esc_html( trim( $r['entity_type'] . ' ' . ( $r['entity_id'] ? '#' . $r['entity_id'] : '' ) ) ); ?></td>
						<td><?php echo esc_html( $r['ip'] ); ?></td>
						<td><?php echo $r['payload'] ? '<code>' . esc_html( $r['payload'] ) . '</code>' : '—'; ?></td>
					</tr>
				<?php endforeach; endif; ?>
				</tbody>
			</table>
			<?php if ( $res['pages'] > 1 ) : ?>
				<p class="tablenav"><span class="pagination-links">
					<?php for ( $i = 1; $i <= $res['pages']; $i++ ) : ?>
						<a class="button button-small <?php echo $i === $paged ? 'button-primary' : ''; ?>" href="<?php echo esc_url( $base . '&paged=' . $i ); ?>"><?php echo (int) $i; ?></a>
					<?php endfor; ?>
				</span></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ================= ACTUALIZACIONES ================= */
	private function guard_updates() {
		if ( ! current_user_can( self::CAP_UPDATES ) ) {
			wp_die( esc_html__( 'No autorizado.', 'caaguazu-portal' ) );
		}
	}

	public function render_updates() {
		$this->guard_updates();

		$updater = function_exists( 'promotur_updater' ) ? promotur_updater() : null;

		// Versión instalada (header del plugin) y datos del plugin.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$data      = get_plugin_data( PROMOTUR_FILE, false, false );
		$installed = $data['Version'] ? $data['Version'] : ( defined( 'PROMOTUR_VERSION' ) ? PROMOTUR_VERSION : '0' );

		// Última versión disponible (caché del updater) y última comprobación.
		$update      = $updater ? $updater->getUpdate() : null;
		$last_check  = 0;
		if ( $updater && method_exists( $updater, 'getUpdateState' ) ) {
			$state      = $updater->getUpdateState();
			$last_check = $state ? (int) $state->getLastCheck() : 0;
		}

		// Estado del token.
		$token_const = defined( 'PROMOTUR_GITHUB_TOKEN' ) && PROMOTUR_GITHUB_TOKEN;
		$token_opt   = (string) get_option( 'promotur_github_token', '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Actualizaciones del portal', 'caaguazu-portal' ); ?></h1>
			<?php $this->show_notice(); ?>

			<?php if ( ! $updater ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'No se pudo inicializar el verificador de actualizaciones (plugin-update-checker). Revisá que la carpeta vendor/ esté presente.', 'caaguazu-portal' ); ?></p></div>
			<?php endif; ?>

			<?php if ( defined( 'PROMOTUR_VERSION' ) && version_compare( $installed, PROMOTUR_VERSION, '!=' ) ) : ?>
				<div class="notice notice-warning"><p>
					<?php printf(
						/* translators: 1: header version, 2: constant version */
						esc_html__( 'Atención: la versión del encabezado del plugin (%1$s) no coincide con la constante PROMOTUR_VERSION (%2$s). El workflow de releases y el updater usan la del encabezado; mantenelas iguales para evitar problemas de publicación.', 'caaguazu-portal' ),
						esc_html( $installed ),
						esc_html( PROMOTUR_VERSION )
					); ?>
				</p></div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:680px">
				<tbody>
					<tr><th style="width:200px"><?php esc_html_e( 'Versión instalada', 'caaguazu-portal' ); ?></th><td><code><?php echo esc_html( $installed ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Última disponible', 'caaguazu-portal' ); ?></th><td>
						<?php if ( $update && ! empty( $update->version ) ) : ?>
							<code><?php echo esc_html( $update->version ); ?></code>
							<?php
							$link = wp_nonce_url(
								self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( PROMOTUR_BASENAME ) ),
								'upgrade-plugin_' . PROMOTUR_BASENAME
							);
							?>
							&nbsp;<a class="button button-primary" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Actualizar ahora', 'caaguazu-portal' ); ?></a>
						<?php else : ?>
							<?php esc_html_e( 'Estás al día.', 'caaguazu-portal' ); ?>
						<?php endif; ?>
					</td></tr>
					<tr><th><?php esc_html_e( 'Última comprobación', 'caaguazu-portal' ); ?></th><td>
						<?php echo $last_check ? esc_html( date_i18n( 'Y-m-d H:i', $last_check ) ) : esc_html__( 'nunca', 'caaguazu-portal' ); ?>
					</td></tr>
					<tr><th><?php esc_html_e( 'Repositorio', 'caaguazu-portal' ); ?></th><td><a href="<?php echo esc_url( PROMOTUR_REPO ); ?>" target="_blank" rel="noopener"><?php echo esc_html( PROMOTUR_REPO ); ?></a></td></tr>
				</tbody>
			</table>

			<p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<?php wp_nonce_field( 'promotur_admin_updates' ); ?>
					<input type="hidden" name="action" value="promotur_admin_updates">
					<input type="hidden" name="op" value="check">
					<button class="button"><?php esc_html_e( 'Buscar actualizaciones ahora', 'caaguazu-portal' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<?php wp_nonce_field( 'promotur_admin_updates' ); ?>
					<input type="hidden" name="action" value="promotur_admin_updates">
					<input type="hidden" name="op" value="reset">
					<button class="button"><?php esc_html_e( 'Limpiar caché del updater', 'caaguazu-portal' ); ?></button>
				</form>
			</p>

			<h2><?php esc_html_e( 'Token de GitHub', 'caaguazu-portal' ); ?></h2>
			<?php if ( $token_const ) : ?>
				<p><?php esc_html_e( 'Definido en wp-config.php mediante la constante PROMOTUR_GITHUB_TOKEN (no editable acá). Tiene prioridad sobre el token guardado en la base de datos.', 'caaguazu-portal' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'El repositorio es público, así que normalmente no hace falta token. Configurá uno solo si el repo pasa a privado o si chocás con el límite de peticiones de GitHub.', 'caaguazu-portal' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'promotur_admin_updates' ); ?>
					<input type="hidden" name="action" value="promotur_admin_updates">
					<input type="hidden" name="op" value="save_token">
					<table class="form-table"><tbody>
						<tr><th><?php esc_html_e( 'Token', 'caaguazu-portal' ); ?></th><td>
							<input type="password" name="github_token" class="regular-text" autocomplete="off" placeholder="<?php echo $token_opt ? esc_attr__( '•••• guardado (dejá vacío para conservarlo)', 'caaguazu-portal' ) : 'ghp_…'; ?>">
							<?php if ( $token_opt ) : ?>
								<p><label><input type="checkbox" name="clear_token" value="1"> <?php esc_html_e( 'Eliminar el token guardado', 'caaguazu-portal' ); ?></label></p>
							<?php endif; ?>
						</td></tr>
					</tbody></table>
					<p><button class="button button-primary"><?php esc_html_e( 'Guardar token', 'caaguazu-portal' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_updates() {
		$this->guard_updates();
		check_admin_referer( 'promotur_admin_updates' );
		$op      = sanitize_key( wp_unslash( $_POST['op'] ?? '' ) );
		$updater = function_exists( 'promotur_updater' ) ? promotur_updater() : null;

		switch ( $op ) {
			case 'check':
				if ( $updater ) {
					$update = $updater->checkForUpdates();
					delete_site_transient( 'update_plugins' );
					if ( $update && ! empty( $update->version ) ) {
						$this->notice( sprintf( __( 'Hay una versión nueva disponible: %s.', 'caaguazu-portal' ), '<code>' . esc_html( $update->version ) . '</code>' ) );
					} else {
						$this->notice( __( 'No hay actualizaciones: estás en la última versión.', 'caaguazu-portal' ) );
					}
				} else {
					$this->notice( __( 'El verificador de actualizaciones no está disponible.', 'caaguazu-portal' ), 'error' );
				}
				break;

			case 'reset':
				if ( $updater && method_exists( $updater, 'resetUpdateState' ) ) {
					$updater->resetUpdateState();
				}
				delete_site_transient( 'update_plugins' );
				$this->notice( __( 'Caché del updater limpiada.', 'caaguazu-portal' ) );
				break;

			case 'save_token':
				if ( defined( 'PROMOTUR_GITHUB_TOKEN' ) && PROMOTUR_GITHUB_TOKEN ) {
					$this->notice( __( 'El token está fijado en wp-config.php; no se puede cambiar desde acá.', 'caaguazu-portal' ), 'error' );
					break;
				}
				$clear = ! empty( $_POST['clear_token'] );
				$token = sanitize_text_field( wp_unslash( $_POST['github_token'] ?? '' ) );
				if ( $clear ) {
					delete_option( 'promotur_github_token' );
					$this->notice( __( 'Token eliminado.', 'caaguazu-portal' ) );
				} elseif ( '' !== $token ) {
					update_option( 'promotur_github_token', $token, false );
					$this->notice( __( 'Token guardado.', 'caaguazu-portal' ) );
				} else {
					$this->notice( __( 'Sin cambios en el token.', 'caaguazu-portal' ) );
				}
				if ( class_exists( 'PROMOTUR_Audit' ) ) {
					PROMOTUR_Audit::log( 'update_settings', array( 'entity_type' => 'plugin' ) );
				}
				break;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=promotur-updates' ) );
		exit;
	}
}
