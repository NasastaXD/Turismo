<?php
/**
 * Cuentas: registro de visitantes, login, y solicitud de cuenta de dueño de local.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CGZ_Accounts {

	private static $instance = null;
	/** @var array notices acumulados para mostrar en el shortcode de cuenta. */
	public static $notices = array();

	const REQUESTS_OPTION = 'cgz_owner_requests';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'handle_post' ) );
	}

	/**
	 * Procesa los formularios de cuenta (registro, login, logout, solicitud de dueño).
	 */
	public function handle_post() {
		if ( empty( $_POST['cgz_account_action'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_POST['cgz_account_action'] ) );

		switch ( $action ) {
			case 'register':
				$this->process_register();
				break;
			case 'login':
				$this->process_login();
				break;
			case 'owner_request':
				$this->process_owner_request();
				break;
		}
	}

	private function verify( $nonce_action ) {
		return isset( $_POST['cgz_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cgz_nonce'] ) ), $nonce_action );
	}

	/**
	 * Registro de un visitante (rol cgz_visitante).
	 */
	private function process_register() {
		if ( ! $this->verify( 'cgz_register' ) ) {
			self::$notices[] = array( 'error', __( 'Sesión expirada. Recargá la página.', 'caaguazu-locales' ) );
			return;
		}
		if ( ! get_option( 'users_can_register' ) && ! current_user_can( 'create_users' ) ) {
			// Permitimos registro propio aunque el ajuste global esté apagado,
			// porque el plugin gestiona su propio rol. Filtrable.
			if ( ! apply_filters( 'cgz_allow_self_registration', true ) ) {
				self::$notices[] = array( 'error', __( 'El registro está deshabilitado.', 'caaguazu-locales' ) );
				return;
			}
		}

		$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$password = (string) ( $_POST['password'] ?? '' );

		if ( ! $username || ! is_email( $email ) || strlen( $password ) < 6 ) {
			self::$notices[] = array( 'error', __( 'Completá usuario, un email válido y una contraseña de 6+ caracteres.', 'caaguazu-locales' ) );
			return;
		}
		if ( username_exists( $username ) || email_exists( $email ) ) {
			self::$notices[] = array( 'error', __( 'Ese usuario o email ya está registrado.', 'caaguazu-locales' ) );
			return;
		}

		$user_id = wp_insert_user( array(
			'user_login' => $username,
			'user_email' => $email,
			'user_pass'  => $password,
			'role'       => 'cgz_visitante',
		) );

		if ( is_wp_error( $user_id ) ) {
			self::$notices[] = array( 'error', $user_id->get_error_message() );
			return;
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );
		self::$notices[] = array( 'success', __( '¡Cuenta creada! Ya podés reseñar y comentar.', 'caaguazu-locales' ) );
	}

	/**
	 * Login con usuario/email + contraseña.
	 */
	private function process_login() {
		if ( ! $this->verify( 'cgz_login' ) ) {
			self::$notices[] = array( 'error', __( 'Sesión expirada. Recargá la página.', 'caaguazu-locales' ) );
			return;
		}
		$creds = array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) ),
			'user_password' => (string) ( $_POST['password'] ?? '' ),
			'remember'      => ! empty( $_POST['remember'] ),
		);
		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			self::$notices[] = array( 'error', __( 'Usuario o contraseña incorrectos.', 'caaguazu-locales' ) );
			return;
		}
		self::$notices[] = array( 'success', __( '¡Bienvenido de nuevo!', 'caaguazu-locales' ) );
	}

	/**
	 * Solicitud de cuenta de dueño de local: guarda la solicitud y envía email a contacto@caaguazu.net.
	 */
	private function process_owner_request() {
		if ( ! $this->verify( 'cgz_owner_request' ) ) {
			self::$notices[] = array( 'error', __( 'Sesión expirada. Recargá la página.', 'caaguazu-locales' ) );
			return;
		}

		$nombre   = sanitize_text_field( wp_unslash( $_POST['nombre'] ?? '' ) );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$local    = sanitize_text_field( wp_unslash( $_POST['local'] ?? '' ) );
		$telefono = sanitize_text_field( wp_unslash( $_POST['telefono'] ?? '' ) );
		$mensaje  = sanitize_textarea_field( wp_unslash( $_POST['mensaje'] ?? '' ) );

		if ( ! $nombre || ! is_email( $email ) || ! $local ) {
			self::$notices[] = array( 'error', __( 'Completá tu nombre, email y el nombre del local.', 'caaguazu-locales' ) );
			return;
		}

		$requests   = get_option( self::REQUESTS_OPTION, array() );
		$requests[] = array(
			'id'       => uniqid( 'req_', false ),
			'nombre'   => $nombre,
			'email'    => $email,
			'local'    => $local,
			'telefono' => $telefono,
			'mensaje'  => $mensaje,
			'user_id'  => get_current_user_id(),
			'status'   => 'pending',
			'date'     => current_time( 'mysql' ),
		);
		update_option( self::REQUESTS_OPTION, $requests );

		// Email a la Secretaría de Turismo.
		$to      = cgz_contact_email();
		$subject = sprintf(
			/* translators: %s = nombre del local */
			__( '[Turismo Caaguazú] Solicitud de cuenta de dueño: %s', 'caaguazu-locales' ),
			$local
		);
		$body = sprintf(
			"Nueva solicitud de cuenta de dueño de local.\n\n" .
			"Nombre: %s\nEmail: %s\nLocal: %s\nTeléfono: %s\n\nMensaje:\n%s\n\n" .
			"Aprobala desde: %s",
			$nombre,
			$email,
			$local,
			$telefono ? $telefono : '—',
			$mensaje ? $mensaje : '—',
			admin_url( 'admin.php?page=cgz-owner-requests' )
		);
		$headers = array( 'Reply-To: ' . $nombre . ' <' . $email . '>' );
		wp_mail( $to, $subject, $body, $headers );

		self::$notices[] = array(
			'success',
			sprintf(
				/* translators: %s = email de contacto */
				__( 'Recibimos tu solicitud. El equipo de %s la revisará y te contactará para activar tu cuenta de dueño.', 'caaguazu-locales' ),
				cgz_contact_email()
			),
		);
	}

	/**
	 * Aprueba una solicitud: asigna/crea usuario dueño y lo vincula a un local.
	 * Llamado desde el admin.
	 *
	 * @param string $request_id
	 * @param int    $local_id
	 * @return true|WP_Error
	 */
	public static function approve_request( $request_id, $local_id ) {
		$requests = get_option( self::REQUESTS_OPTION, array() );
		$found    = null;
		foreach ( $requests as $i => $r ) {
			if ( $r['id'] === $request_id ) { $found = $i; break; }
		}
		if ( null === $found ) {
			return new WP_Error( 'not_found', __( 'Solicitud no encontrada.', 'caaguazu-locales' ) );
		}
		if ( ! get_post( $local_id ) || 'cgz_local' !== get_post_type( $local_id ) ) {
			return new WP_Error( 'bad_local', __( 'Local inválido.', 'caaguazu-locales' ) );
		}

		$req  = $requests[ $found ];
		$user = get_user_by( 'email', $req['email'] );

		if ( ! $user ) {
			$password = wp_generate_password( 12 );
			$user_id  = wp_insert_user( array(
				'user_login'   => self::unique_login_from_email( $req['email'] ),
				'user_email'   => $req['email'],
				'user_pass'    => $password,
				'display_name' => $req['nombre'],
				'role'         => 'cgz_dueno',
			) );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			wp_new_user_notification( $user_id, null, 'user' );
		} else {
			$user_id = $user->ID;
			$user->add_role( 'cgz_dueno' );
		}

		update_post_meta( $local_id, '_cgz_owner', (int) $user_id );

		$requests[ $found ]['status']   = 'approved';
		$requests[ $found ]['local_id'] = (int) $local_id;
		update_option( self::REQUESTS_OPTION, $requests );

		return true;
	}

	private static function unique_login_from_email( $email ) {
		$base  = sanitize_user( current( explode( '@', $email ) ), true );
		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . $i;
			$i++;
		}
		return $login;
	}
}
