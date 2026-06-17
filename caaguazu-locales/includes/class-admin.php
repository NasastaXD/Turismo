<?php
/**
 * Backend: metabox del local (con selector de mapa por clic), página de
 * solicitudes de dueño y moderación de reseñas.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CGZ_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_cgz_local', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_menu', array( $this, 'menus' ) );
		add_action( 'admin_post_cgz_approve_owner', array( $this, 'handle_approve' ) );
		add_action( 'admin_post_cgz_delete_review', array( $this, 'handle_delete_review' ) );
	}

	/* ----- Metaboxes ----- */
	public function add_meta_boxes() {
		add_meta_box( 'cgz_local_details', __( 'Datos del local', 'caaguazu-locales' ), array( $this, 'render_details' ), 'cgz_local', 'normal', 'high' );
		add_meta_box( 'cgz_local_map', __( 'Ubicación en el mapa', 'caaguazu-locales' ), array( $this, 'render_map' ), 'cgz_local', 'normal', 'high' );
		add_meta_box( 'cgz_local_booking', __( 'Opciones de reserva (WhatsApp)', 'caaguazu-locales' ), array( $this, 'render_booking' ), 'cgz_local', 'normal', 'default' );
		if ( current_user_can( 'manage_options' ) ) {
			add_meta_box( 'cgz_local_owner', __( 'Dueño asignado', 'caaguazu-locales' ), array( $this, 'render_owner' ), 'cgz_local', 'side', 'default' );
		}
	}

	public function render_details( $post ) {
		wp_nonce_field( 'cgz_save_local', 'cgz_local_nonce' );
		$tipo     = get_post_meta( $post->ID, '_cgz_tipo', true );
		$whatsapp = get_post_meta( $post->ID, '_cgz_whatsapp', true );
		$telefono = get_post_meta( $post->ID, '_cgz_telefono', true );
		$direccion= get_post_meta( $post->ID, '_cgz_direccion', true );
		$horario  = get_post_meta( $post->ID, '_cgz_horario', true );
		$types    = cgz_local_types();
		?>
		<p>
			<label><strong><?php esc_html_e( 'Tipo de local', 'caaguazu-locales' ); ?></strong></label><br>
			<select name="cgz_tipo">
				<?php foreach ( $types as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $tipo, $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( 'Solo restaurantes y hoteles muestran el botón de reserva por WhatsApp.', 'caaguazu-locales' ); ?></span>
		</p>
		<p>
			<label><strong><?php esc_html_e( 'WhatsApp', 'caaguazu-locales' ); ?></strong> — <span class="description"><?php esc_html_e( 'con código de país, ej: 595981123456', 'caaguazu-locales' ); ?></span></label><br>
			<input type="text" class="regular-text" name="cgz_whatsapp" value="<?php echo esc_attr( $whatsapp ); ?>">
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Teléfono', 'caaguazu-locales' ); ?></strong></label><br>
			<input type="text" class="regular-text" name="cgz_telefono" value="<?php echo esc_attr( $telefono ); ?>">
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Dirección', 'caaguazu-locales' ); ?></strong></label><br>
			<input type="text" class="large-text" name="cgz_direccion" value="<?php echo esc_attr( $direccion ); ?>">
		</p>
		<p>
			<label><strong><?php esc_html_e( 'Horario', 'caaguazu-locales' ); ?></strong></label><br>
			<input type="text" class="large-text" name="cgz_horario" value="<?php echo esc_attr( $horario ); ?>" placeholder="<?php esc_attr_e( 'Ej: Lun a Sáb 8:00–22:00', 'caaguazu-locales' ); ?>">
		</p>
		<?php
	}

	public function render_map( $post ) {
		$coords = cgz_get_coords( $post->ID );
		$center = cgz_map_center();
		$lat    = $coords ? $coords['lat'] : $center['lat'];
		$lng    = $coords ? $coords['lng'] : $center['lng'];
		?>
		<p class="description"><?php esc_html_e( 'Hacé clic en el mapa para marcar la ubicación exacta del local. Arrastrá el marcador para ajustarlo. Así no dependés de coordenadas automáticas que suelen fallar.', 'caaguazu-locales' ); ?></p>
		<div class="cgz-picker" id="cgz-admin-picker"
			data-cgz-picker
			data-lat="<?php echo esc_attr( $lat ); ?>"
			data-lng="<?php echo esc_attr( $lng ); ?>"
			data-has-point="<?php echo $coords ? '1' : '0'; ?>"
			style="height:360px;border-radius:8px;overflow:hidden"></div>
		<p style="margin-top:10px">
			<label><?php esc_html_e( 'Lat', 'caaguazu-locales' ); ?></label>
			<input type="text" name="cgz_lat" data-cgz-lat value="<?php echo $coords ? esc_attr( $lat ) : ''; ?>" readonly style="width:140px">
			<label><?php esc_html_e( 'Lng', 'caaguazu-locales' ); ?></label>
			<input type="text" name="cgz_lng" data-cgz-lng value="<?php echo $coords ? esc_attr( $lng ) : ''; ?>" readonly style="width:140px">
			<button type="button" class="button" data-cgz-clear-point><?php esc_html_e( 'Quitar marcador', 'caaguazu-locales' ); ?></button>
		</p>
		<?php
	}

	public function render_booking( $post ) {
		$options = get_post_meta( $post->ID, '_cgz_booking_opciones', true );
		$tipo    = get_post_meta( $post->ID, '_cgz_tipo', true );
		if ( ! is_array( $options ) || empty( $options ) ) {
			$options = cgz_default_booking_options( $tipo ? $tipo : 'restaurante' );
		}
		$text = '';
		foreach ( $options as $o ) {
			$text .= ( $o['label'] ?? '' ) . ' | ' . str_replace( "\n", '\\n', $o['message'] ?? '' ) . "\n";
		}
		?>
		<p class="description"><?php esc_html_e( 'Una opción por línea, en el formato: Etiqueta del botón | Mensaje de WhatsApp. Usá \\n para saltos de línea dentro del mensaje. Dejá vacío para usar las opciones por defecto del tipo de local.', 'caaguazu-locales' ); ?></p>
		<textarea name="cgz_booking_options" rows="6" class="large-text code"><?php echo esc_textarea( trim( $text ) ); ?></textarea>
		<?php
	}

	public function render_owner( $post ) {
		$owner = (int) get_post_meta( $post->ID, '_cgz_owner', true );
		$users = get_users( array( 'role__in' => array( 'cgz_dueno', 'administrator' ), 'number' => 200 ) );
		?>
		<p class="description"><?php esc_html_e( 'El dueño podrá editar este local desde el panel de frontend.', 'caaguazu-locales' ); ?></p>
		<select name="cgz_owner" style="width:100%">
			<option value="0"><?php esc_html_e( '— Sin dueño —', 'caaguazu-locales' ); ?></option>
			<?php foreach ( $users as $u ) : ?>
				<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $owner, $u->ID ); ?>>
					<?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/* ----- Guardado ----- */
	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['cgz_local_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cgz_local_nonce'] ) ), 'cgz_save_local' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		update_post_meta( $post_id, '_cgz_tipo', sanitize_key( wp_unslash( $_POST['cgz_tipo'] ?? '' ) ) );
		update_post_meta( $post_id, '_cgz_whatsapp', cgz_sanitize_phone( wp_unslash( $_POST['cgz_whatsapp'] ?? '' ) ) );
		update_post_meta( $post_id, '_cgz_telefono', sanitize_text_field( wp_unslash( $_POST['cgz_telefono'] ?? '' ) ) );
		update_post_meta( $post_id, '_cgz_direccion', sanitize_text_field( wp_unslash( $_POST['cgz_direccion'] ?? '' ) ) );
		update_post_meta( $post_id, '_cgz_horario', sanitize_text_field( wp_unslash( $_POST['cgz_horario'] ?? '' ) ) );

		$lat = wp_unslash( $_POST['cgz_lat'] ?? '' );
		$lng = wp_unslash( $_POST['cgz_lng'] ?? '' );
		if ( '' === trim( (string) $lat ) || '' === trim( (string) $lng ) ) {
			delete_post_meta( $post_id, '_cgz_lat' );
			delete_post_meta( $post_id, '_cgz_lng' );
		} else {
			update_post_meta( $post_id, '_cgz_lat', (float) $lat );
			update_post_meta( $post_id, '_cgz_lng', (float) $lng );
		}

		if ( isset( $_POST['cgz_booking_options'] ) ) {
			update_post_meta( $post_id, '_cgz_booking_opciones', cgz_parse_booking_options( wp_unslash( $_POST['cgz_booking_options'] ) ) );
		}

		if ( current_user_can( 'manage_options' ) && isset( $_POST['cgz_owner'] ) ) {
			$owner = (int) $_POST['cgz_owner'];
			if ( $owner ) {
				update_post_meta( $post_id, '_cgz_owner', $owner );
			} else {
				delete_post_meta( $post_id, '_cgz_owner' );
			}
		}
	}

	/* ----- Assets de admin ----- */
	public function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( $screen && 'cgz_local' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			cgz_enqueue_leaflet();
			wp_enqueue_script( 'cgz-map-picker', CGZ_LOC_URI . 'assets/js/map-picker.js', array( 'cgz-leaflet' ), CGZ_LOC_VERSION, true );
			wp_enqueue_style( 'cgz-loc', CGZ_LOC_URI . 'assets/css/caaguazu-locales.css', array(), CGZ_LOC_VERSION );
			wp_add_inline_script( 'cgz-map-picker', 'window.CGZ_MAP_CENTER = ' . wp_json_encode( cgz_map_center() ) . ';', 'before' );
		}
	}

	/* ----- Menús de admin ----- */
	public function menus() {
		add_submenu_page(
			'edit.php?post_type=cgz_local',
			__( 'Solicitudes de dueño', 'caaguazu-locales' ),
			__( 'Solicitudes de dueño', 'caaguazu-locales' ),
			'manage_options',
			'cgz-owner-requests',
			array( $this, 'render_requests_page' )
		);
		add_submenu_page(
			'edit.php?post_type=cgz_local',
			__( 'Moderar reseñas', 'caaguazu-locales' ),
			__( 'Moderar reseñas', 'caaguazu-locales' ),
			'cgz_moderate_reviews',
			'cgz-reviews',
			array( $this, 'render_reviews_page' )
		);
	}

	public function render_requests_page() {
		$requests = get_option( CGZ_Accounts::REQUESTS_OPTION, array() );
		$locals   = get_posts( array( 'post_type' => 'cgz_local', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Solicitudes de cuenta de dueño', 'caaguazu-locales' ); ?></h1>
			<p><?php printf( esc_html__( 'Las solicitudes llegan desde el formulario público y a %s.', 'caaguazu-locales' ), '<strong>' . esc_html( cgz_contact_email() ) . '</strong>' ); ?></p>
			<?php if ( empty( $requests ) ) : ?>
				<p><?php esc_html_e( 'No hay solicitudes.', 'caaguazu-locales' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Fecha', 'caaguazu-locales' ); ?></th>
						<th><?php esc_html_e( 'Nombre', 'caaguazu-locales' ); ?></th>
						<th><?php esc_html_e( 'Email', 'caaguazu-locales' ); ?></th>
						<th><?php esc_html_e( 'Local', 'caaguazu-locales' ); ?></th>
						<th><?php esc_html_e( 'Teléfono', 'caaguazu-locales' ); ?></th>
						<th><?php esc_html_e( 'Estado', 'caaguazu-locales' ); ?></th>
						<th><?php esc_html_e( 'Aprobar / vincular a local', 'caaguazu-locales' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( array_reverse( $requests ) as $r ) : ?>
						<tr>
							<td><?php echo esc_html( $r['date'] ); ?></td>
							<td><?php echo esc_html( $r['nombre'] ); ?></td>
							<td><?php echo esc_html( $r['email'] ); ?></td>
							<td><?php echo esc_html( $r['local'] ); ?><?php if ( ! empty( $r['mensaje'] ) ) : ?><br><small><?php echo esc_html( $r['mensaje'] ); ?></small><?php endif; ?></td>
							<td><?php echo esc_html( $r['telefono'] ); ?></td>
							<td><span class="cgz-status cgz-status--<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( $r['status'] ); ?></span></td>
							<td>
								<?php if ( 'pending' === $r['status'] ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:6px">
										<?php wp_nonce_field( 'cgz_approve_owner' ); ?>
										<input type="hidden" name="action" value="cgz_approve_owner">
										<input type="hidden" name="request_id" value="<?php echo esc_attr( $r['id'] ); ?>">
										<select name="local_id" required>
											<option value=""><?php esc_html_e( '— Elegí el local —', 'caaguazu-locales' ); ?></option>
											<?php foreach ( $locals as $l ) : ?>
												<option value="<?php echo esc_attr( $l->ID ); ?>"><?php echo esc_html( $l->post_title ); ?></option>
											<?php endforeach; ?>
										</select>
										<button class="button button-primary"><?php esc_html_e( 'Aprobar', 'caaguazu-locales' ); ?></button>
									</form>
									<small><?php esc_html_e( 'Creá el local primero si no existe en la lista.', 'caaguazu-locales' ); ?></small>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_approve() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'cgz_approve_owner' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'caaguazu-locales' ) );
		}
		$request_id = sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );
		$local_id   = (int) ( $_POST['local_id'] ?? 0 );
		$result     = CGZ_Accounts::approve_request( $request_id, $local_id );
		$arg        = is_wp_error( $result ) ? 'error' : 'approved';
		wp_safe_redirect( add_query_arg( 'cgz_msg', $arg, admin_url( 'edit.php?post_type=cgz_local&page=cgz-owner-requests' ) ) );
		exit;
	}

	public function render_reviews_page() {
		global $wpdb;
		$reviews = $wpdb->get_results( "SELECT * FROM " . CGZ_Reviews::t_reviews() . " ORDER BY created_at DESC LIMIT 200" );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Moderar reseñas', 'caaguazu-locales' ); ?></h1>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Local', 'caaguazu-locales' ); ?></th>
					<th><?php esc_html_e( 'Autor', 'caaguazu-locales' ); ?></th>
					<th><?php esc_html_e( 'Rating', 'caaguazu-locales' ); ?></th>
					<th><?php esc_html_e( 'Reseña', 'caaguazu-locales' ); ?></th>
					<th><?php esc_html_e( 'Fecha', 'caaguazu-locales' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $reviews as $rev ) :
					$user = get_userdata( $rev->user_id ); ?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $rev->local_id ) ); ?>"><?php echo esc_html( get_the_title( $rev->local_id ) ); ?></a></td>
						<td><?php echo esc_html( $user ? $user->display_name : '#' . $rev->user_id ); ?></td>
						<td><?php echo esc_html( str_repeat( '★', (int) $rev->rating ) ); ?></td>
						<td><strong><?php echo esc_html( $rev->title ); ?></strong><br><?php echo esc_html( wp_trim_words( $rev->content, 30 ) ); ?></td>
						<td><?php echo esc_html( $rev->created_at ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar esta reseña?', 'caaguazu-locales' ) ); ?>')">
								<?php wp_nonce_field( 'cgz_delete_review' ); ?>
								<input type="hidden" name="action" value="cgz_delete_review">
								<input type="hidden" name="review_id" value="<?php echo esc_attr( $rev->id ); ?>">
								<button class="button button-link-delete"><?php esc_html_e( 'Eliminar', 'caaguazu-locales' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function handle_delete_review() {
		if ( ! current_user_can( 'cgz_moderate_reviews' ) || ! check_admin_referer( 'cgz_delete_review' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'caaguazu-locales' ) );
		}
		CGZ_Reviews::delete_review( (int) ( $_POST['review_id'] ?? 0 ) );
		wp_safe_redirect( admin_url( 'edit.php?post_type=cgz_local&page=cgz-reviews' ) );
		exit;
	}
}
