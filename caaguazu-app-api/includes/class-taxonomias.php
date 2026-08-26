<?php
/**
 * Categorías y zonas para la app.
 *
 * Las taxonomías ya existen en caaguazu-portal (`promotur_categoria`,
 * `promotur_zona`); lo que no existía es la presentación que la app necesita:
 * un icono y un color por categoría, y un PNG de marcador pre-renderizado.
 *
 * El marcador se sirve pre-renderizado a propósito: componer el pin en el
 * cliente obliga a cada plataforma a reimplementar el mismo dibujo y a que el
 * color viaje dos veces. Del lado servidor se resuelve una vez.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_Taxonomias {

	private static $instance = null;

	const TAX_CATEGORIA = 'promotur_categoria';
	const TAX_ZONA      = 'promotur_zona';

	const META_ICONO  = 'czuapi_icono';
	const META_COLOR  = 'czuapi_color';
	const META_MARKER = 'czuapi_marker_id'; // ID de adjunto del PNG

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_term_meta' ) );

		// Campos en la pantalla de término de wp-admin. Sirve para que el staff
		// pueda cargar icono y color desde ya; cuando el panel propio tenga su
		// pantalla de categorías, lee y escribe estos mismos metas.
		add_action( self::TAX_CATEGORIA . '_add_form_fields', array( $this, 'campos_alta' ) );
		add_action( self::TAX_CATEGORIA . '_edit_form_fields', array( $this, 'campos_edicion' ) );
		add_action( 'created_' . self::TAX_CATEGORIA, array( $this, 'guardar' ) );
		add_action( 'edited_' . self::TAX_CATEGORIA, array( $this, 'guardar' ) );
	}

	public function register_term_meta() {
		foreach ( array( self::META_ICONO, self::META_COLOR ) as $key ) {
			register_term_meta( self::TAX_CATEGORIA, $key, array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
			) );
		}
		register_term_meta( self::TAX_CATEGORIA, self::META_MARKER, array(
			'type'         => 'integer',
			'single'       => true,
			'show_in_rest' => false,
		) );
	}

	/* --------------------------------------------------------------------- */
	/*  Admin                                                                 */
	/* --------------------------------------------------------------------- */

	public function campos_alta() {
		?>
		<div class="form-field">
			<label for="czuapi_color"><?php esc_html_e( 'Color', 'caaguazu-app-api' ); ?></label>
			<input type="color" name="czuapi_color" id="czuapi_color" value="#2E7D32">
			<p><?php esc_html_e( 'Color del pin en el mapa y del chip en la app.', 'caaguazu-app-api' ); ?></p>
		</div>
		<div class="form-field">
			<label for="czuapi_icono"><?php esc_html_e( 'Icono', 'caaguazu-app-api' ); ?></label>
			<input type="text" name="czuapi_icono" id="czuapi_icono" value="">
			<p><?php esc_html_e( 'Clave de icono, p. ej. "nature", "food", "wood".', 'caaguazu-app-api' ); ?></p>
		</div>
		<?php
	}

	public function campos_edicion( $term ) {
		$color = get_term_meta( $term->term_id, self::META_COLOR, true );
		$icono = get_term_meta( $term->term_id, self::META_ICONO, true );
		?>
		<tr class="form-field">
			<th><label for="czuapi_color"><?php esc_html_e( 'Color', 'caaguazu-app-api' ); ?></label></th>
			<td><input type="color" name="czuapi_color" id="czuapi_color" value="<?php echo esc_attr( $color ? $color : '#2E7D32' ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th><label for="czuapi_icono"><?php esc_html_e( 'Icono', 'caaguazu-app-api' ); ?></label></th>
			<td><input type="text" name="czuapi_icono" id="czuapi_icono" value="<?php echo esc_attr( $icono ); ?>"></td>
		</tr>
		<?php
	}

	public function guardar( $term_id ) {
		if ( isset( $_POST['czuapi_color'] ) ) {
			$raw = wp_unslash( $_POST['czuapi_color'] );
			// sanitize_hex_color() vivió mucho tiempo en el customizer y no
			// siempre está cargada fuera de esa pantalla; el fallback evita un
			// fatal al guardar un término desde cualquier otro contexto.
			$color = function_exists( 'sanitize_hex_color' )
				? sanitize_hex_color( $raw )
				: ( preg_match( '/^#[a-f0-9]{6}$/i', (string) $raw ) ? strtolower( $raw ) : '' );
			update_term_meta( $term_id, self::META_COLOR, $color ? $color : '' );
		}
		if ( isset( $_POST['czuapi_icono'] ) ) {
			update_term_meta( $term_id, self::META_ICONO, sanitize_key( wp_unslash( $_POST['czuapi_icono'] ) ) );
		}
	}

	/* --------------------------------------------------------------------- */
	/*  Endpoints                                                             */
	/* --------------------------------------------------------------------- */

	public function register_routes() {
		register_rest_route( CZUAPI_NS, '/categorias', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'categorias' ),
			'permission_callback' => '__return_true',
		) );

		// Pedido por el lado de la app: las zonas se usan como filtro en
		// /inventario y aparecen en la ficha, pero no había forma de listarlas.
		register_rest_route( CZUAPI_NS, '/zonas', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'zonas' ),
			'permission_callback' => '__return_true',
		) );
	}

	public function categorias( $request ) {
		$terms = get_terms( array(
			'taxonomy'   => self::TAX_CATEGORIA,
			'hide_empty' => false,
		) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$out = array();
		foreach ( $terms as $t ) {
			$marker_id = (int) get_term_meta( $t->term_id, self::META_MARKER, true );
			$marker    = $marker_id ? wp_get_attachment_url( $marker_id ) : '';

			$out[] = array(
				'id'     => (int) $t->term_id,
				'slug'   => $t->slug,
				'nombre' => $t->name,
				'padre'  => $t->parent ? (int) $t->parent : null,
				'icono'  => (string) get_term_meta( $t->term_id, self::META_ICONO, true ),
				'color'  => (string) get_term_meta( $t->term_id, self::META_COLOR, true ),
				'marker' => $marker ? $marker : null,
				'total'  => (int) $t->count,
			);
		}

		return CZUAPI_Response::with_etag( $out, $request, 600 );
	}

	public function zonas( $request ) {
		$terms = get_terms( array(
			'taxonomy'   => self::TAX_ZONA,
			'hide_empty' => false,
		) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$out = array();
		foreach ( $terms as $t ) {
			$out[] = array(
				'id'     => (int) $t->term_id,
				'slug'   => $t->slug,
				'nombre' => $t->name,
				'padre'  => $t->parent ? (int) $t->parent : null,
				'total'  => (int) $t->count,
			);
		}

		return CZUAPI_Response::with_etag( $out, $request, 600 );
	}
}
