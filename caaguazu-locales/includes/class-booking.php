<?php
/**
 * Reservas por WhatsApp: menú de opciones + mensaje editable + botón que abre wa.me.
 * Disponible solo para restaurantes y hoteles.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CGZ_Booking {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Renderiza el widget de reservas para un local.
	 *
	 * @param int $local_id
	 * @return string HTML (vacío si el local no acepta reservas)
	 */
	public static function render_widget( $local_id ) {
		$local_id = (int) $local_id;
		if ( ! cgz_local_allows_booking( $local_id ) ) {
			return '';
		}

		$phone   = cgz_sanitize_phone( get_post_meta( $local_id, '_cgz_whatsapp', true ) );
		$tipo    = get_post_meta( $local_id, '_cgz_tipo', true );
		$options = cgz_get_booking_options( $local_id );
		$name    = get_the_title( $local_id );

		$heading = ( 'hotel' === $tipo )
			? __( 'Reservá tu estadía', 'caaguazu-locales' )
			: __( 'Reservá tu mesa', 'caaguazu-locales' );

		// Prefijo de cortesía con el nombre del local.
		$greeting = sprintf(
			/* translators: %s = nombre del local */
			__( "Hola %s,", 'caaguazu-locales' ),
			$name
		);

		ob_start();
		?>
		<div class="cgz-booking" data-cgz-booking data-phone="<?php echo esc_attr( $phone ); ?>">
			<h3 class="cgz-booking__title">
				<span class="cgz-booking__wa" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19.05 4.91A9.82 9.82 0 0 0 12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.9-4.45 9.9-9.91 0-2.65-1.03-5.14-2.9-7.02ZM12.05 20.1h-.01a8.2 8.2 0 0 1-4.18-1.15l-.3-.18-3.11.82.83-3.04-.2-.31a8.22 8.22 0 0 1-1.26-4.36c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.54-3.69 8.23-8.24 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.16.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.01-.38.11-.5.11-.11.25-.29.37-.43.13-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.43h-.48c-.17 0-.43.06-.66.31-.22.25-.86.85-.86 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.1-.22-.16-.47-.28Z"/></svg>
				</span>
				<?php echo esc_html( $heading ); ?>
			</h3>
			<p class="cgz-booking__intro"><?php esc_html_e( 'Elegí una opción, ajustá el mensaje y se envía directo al WhatsApp del local.', 'caaguazu-locales' ); ?></p>

			<div class="cgz-booking__options" role="tablist">
				<?php foreach ( $options as $i => $opt ) :
					$opt_label = isset( $opt['label'] ) ? $opt['label'] : '';
					$opt_msg   = isset( $opt['message'] ) ? $opt['message'] : '';
					if ( '' === $opt_label ) { continue; }
					?>
					<button type="button"
						class="cgz-booking__option<?php echo 0 === $i ? ' is-active' : ''; ?>"
						data-cgz-option
						data-message="<?php echo esc_attr( $greeting . "\n" . $opt_msg ); ?>"
						aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>">
						<?php echo esc_html( $opt_label ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<label class="cgz-booking__label" for="cgz-msg-<?php echo esc_attr( $local_id ); ?>">
				<?php esc_html_e( 'Tu mensaje', 'caaguazu-locales' ); ?>
			</label>
			<textarea id="cgz-msg-<?php echo esc_attr( $local_id ); ?>" class="cgz-booking__textarea" data-cgz-message rows="5"><?php
				$first = isset( $options[0]['message'] ) ? $options[0]['message'] : '';
				echo esc_textarea( $greeting . "\n" . $first );
			?></textarea>

			<a href="#" class="cgz-booking__send" data-cgz-send target="_blank" rel="noopener">
				<?php esc_html_e( 'Enviar por WhatsApp', 'caaguazu-locales' ); ?>
			</a>
			<p class="cgz-booking__note"><?php esc_html_e( 'Se abre WhatsApp con tu mensaje listo para enviar.', 'caaguazu-locales' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
