<?php
/**
 * Pantalla de administración: vincular a mano una cuenta existente que un
 * canje rechazó por email duplicado (ver class-link.php), y ver los últimos
 * intentos de canje. Es la mitad manual de la decisión "no vincular solo".
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CEADSSO_Admin {

	private static $instance = null;
	private $notice = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu() {
		add_management_page(
			__( 'Vincular cuenta CEAD', 'caaguazu-sso-cead' ),
			__( 'Vincular cuenta CEAD', 'caaguazu-sso-cead' ),
			'manage_options',
			'caaguazu-sso-cead',
			array( $this, 'render' )
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! empty( $_POST['ceadsso_link_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['ceadsso_link_nonce'] ), 'ceadsso_link' ) ) {
			$this->handle_link_submit();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Vincular cuenta CEAD', 'caaguazu-sso-cead' ); ?></h1>

			<?php if ( ! CEADSSO_Redeem::configured() ) : ?>
				<div class="notice notice-error"><p>
					<?php esc_html_e( 'Faltan CEAD_TUR_SSO_SECRET y/o CEAD_TUR_SSO_URL en wp-config.php — el acceso desde el CEAD no puede funcionar todavía.', 'caaguazu-sso-cead' ); ?>
				</p></div>
			<?php endif; ?>

			<?php if ( $this->notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $this->notice['type'] ); ?>"><p><?php echo esc_html( $this->notice['text'] ); ?></p></div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Cuando alguien entra desde el CEAD con un email que ya tiene cuenta en el portal (sin vincular todavía), el acceso se rechaza a propósito — vincular solo por email es la puerta de un robo de cuenta. Usá este formulario para confirmar el vínculo a mano.', 'caaguazu-sso-cead' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'ceadsso_link', 'ceadsso_link_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="ceadsso_email"><?php esc_html_e( 'Email de la cuenta existente', 'caaguazu-sso-cead' ); ?></label></th>
						<td><input type="email" id="ceadsso_email" name="ceadsso_email" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="ceadsso_uid"><?php esc_html_e( 'cead_uid (visible en el log de abajo)', 'caaguazu-sso-cead' ); ?></label></th>
						<td><input type="number" id="ceadsso_uid" name="ceadsso_uid" class="regular-text" min="1" required></td>
					</tr>
				</table>
				<?php submit_button( __( 'Vincular', 'caaguazu-sso-cead' ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Últimos intentos de acceso', 'caaguazu-sso-cead' ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fecha', 'caaguazu-sso-cead' ); ?></th>
						<th><?php esc_html_e( 'Resultado', 'caaguazu-sso-cead' ); ?></th>
						<th><?php esc_html_e( 'Motivo', 'caaguazu-sso-cead' ); ?></th>
						<th>cead_uid</th>
						<th><?php esc_html_e( 'Email', 'caaguazu-sso-cead' ); ?></th>
						<th><?php esc_html_e( 'Rol CEAD', 'caaguazu-sso-cead' ); ?></th>
						<th><?php esc_html_e( 'Cuenta', 'caaguazu-sso-cead' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( CEADSSO_Log::recent() as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['created_at'] ); ?></td>
							<td><?php echo esc_html( $row['resultado'] ); ?></td>
							<td><?php echo esc_html( $row['motivo'] ? $row['motivo'] : '—' ); ?></td>
							<td><?php echo esc_html( $row['cead_uid'] ? $row['cead_uid'] : '—' ); ?></td>
							<td><?php echo esc_html( $row['email'] ? $row['email'] : '—' ); ?></td>
							<td><?php echo esc_html( $row['rol_cead'] ? $row['rol_cead'] : '—' ); ?></td>
							<td><?php echo esc_html( $row['account_id'] ? $row['account_id'] : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function handle_link_submit() {
		$email    = isset( $_POST['ceadsso_email'] ) ? sanitize_email( wp_unslash( $_POST['ceadsso_email'] ) ) : '';
		$cead_uid = isset( $_POST['ceadsso_uid'] ) ? (int) $_POST['ceadsso_uid'] : 0;

		if ( ! is_email( $email ) || $cead_uid <= 0 ) {
			$this->notice = array( 'type' => 'error', 'text' => __( 'Datos inválidos.', 'caaguazu-sso-cead' ) );
			return;
		}

		$account = Caaguazu_Cuentas_Accounts::get_by_email( $email );
		if ( ! $account ) {
			$this->notice = array( 'type' => 'error', 'text' => __( 'No hay ninguna cuenta con ese email.', 'caaguazu-sso-cead' ) );
			return;
		}

		$linked = CEADSSO_Link::link( $account['id'], $cead_uid );
		if ( ! $linked ) {
			$this->notice = array( 'type' => 'error', 'text' => __( 'Esa cuenta o ese cead_uid ya están vinculados a otra cosa.', 'caaguazu-sso-cead' ) );
			return;
		}

		caaguazu_account_meta_set( $account['id'], 'cead_uid', $cead_uid );
		$this->notice = array( 'type' => 'success', 'text' => __( 'Cuenta vinculada. La próxima vez que entre desde el CEAD, va a caer en esa misma cuenta.', 'caaguazu-sso-cead' ) );
	}
}
