<?php
/**
 * Delta de sincronización para la caché offline de la app.
 *
 * El punto fino, que marcó el lado de la app y es correcto: **una lista de lo
 * que cambió no alcanza; hace falta saber qué dejó de existir.** Si una ficha
 * se despublica y el delta solo trae altas y modificaciones, el teléfono la
 * sigue mostrando para siempre — y falla en silencio, que es lo peor.
 *
 * Por eso esta clase mantiene lápidas (`tombstones`): cada vez que algo sale
 * de publicado —despublicado, archivado, borrado, o movido a la papelera— se
 * registra el hecho con su fecha. `/sync` las devuelve en `eliminados`.
 *
 * `completo: true` es la señal de recargar todo desde cero. Se emite cuando el
 * `since` es más viejo que la lápida más antigua que conservamos: en ese caso
 * no podemos afirmar que la lista de bajas esté completa, y es más honesto
 * pedir una recarga que devolver un delta con agujeros.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CZUAPI_Sync {

	private static $instance = null;

	/** Cuánto se conservan las lápidas. Más allá, se pide recarga completa. */
	const RETENCION_DIAS = 90;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Cualquier salida de "publicado" deja lápida.
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_delete' ) );
		add_action( 'wp_trash_post', array( $this, 'on_delete' ) );

		if ( ! wp_next_scheduled( 'czuapi_purgar_lapidas' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'czuapi_purgar_lapidas' );
		}
		add_action( 'czuapi_purgar_lapidas', array( __CLASS__, 'purgar' ) );
	}

	private static function table() {
		$t = CZUAPI_Install::tables();
		return $t['tombstones'];
	}

	/**
	 * Tipos que la app cachea, mapeados desde el post_type.
	 *
	 * @param string $post_type
	 * @return string|null
	 */
	private function tipo_de( $post_type ) {
		$mapa = array(
			PROMOTUR_Destinos::CPT   => 'inventario',
			CZUAPI_Eventos::CPT      => 'eventos',
			CZUAPI_Articulos::CPT    => 'articulos',
			CZUAPI_Recorridos::CPT   => 'recorridos',
		);
		return isset( $mapa[ $post_type ] ) ? $mapa[ $post_type ] : null;
	}

	public function on_transition( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status ) {
			return;
		}
		$tipo = $this->tipo_de( $post->post_type );
		if ( ! $tipo ) {
			return;
		}
		// Salió de publicado → lápida. Entró a publicado → se levanta.
		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$this->registrar( $tipo, (int) $post->ID );
		} elseif ( 'publish' === $new_status ) {
			$this->levantar( $tipo, (int) $post->ID );
		}
	}

	public function on_delete( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$tipo = $this->tipo_de( $post->post_type );
		if ( $tipo ) {
			$this->registrar( $tipo, (int) $post_id );
		}
	}

	private function registrar( $tipo, $object_id ) {
		global $wpdb;
		$table = self::table();
		// Idempotente: si ya hay lápida, se actualiza la fecha.
		$existe = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT id FROM {$table} WHERE tipo = %s AND object_id = %d",
			$tipo, $object_id
		) );
		if ( $existe ) {
			$wpdb->update( $table, array( 'deleted_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $existe ) ); // phpcs:ignore WordPress.DB
			return;
		}
		$wpdb->insert( $table, array( // phpcs:ignore WordPress.DB
			'tipo'       => $tipo,
			'object_id'  => $object_id,
			'deleted_at' => gmdate( 'Y-m-d H:i:s' ),
		) );
	}

	/** Volvió a publicarse: la lápida ya no aplica. */
	private function levantar( $tipo, $object_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'tipo' => $tipo, 'object_id' => $object_id ) ); // phpcs:ignore WordPress.DB
	}

	public static function purgar() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"DELETE FROM {$table} WHERE deleted_at < %s",
			gmdate( 'Y-m-d H:i:s', time() - ( self::RETENCION_DIAS * DAY_IN_SECONDS ) )
		) );
	}

	/* --------------------------------------------------------------------- */

	public function register_routes() {
		register_rest_route( CZUAPI_NS, '/sync', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'sync' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'since' => array( 'type' => 'string' ),
			),
		) );
	}

	public function sync( $request ) {
		$hasta = gmdate( 'c' );
		$since = (string) $request->get_param( 'since' );
		$ts    = $since ? strtotime( $since ) : 0;

		// Sin `since`, o con uno anterior a lo que conservamos: recarga total.
		$limite_retencion = time() - ( self::RETENCION_DIAS * DAY_IN_SECONDS );
		if ( ! $ts || $ts < $limite_retencion ) {
			return new WP_REST_Response( array(
				'desde'      => $since ? gmdate( 'c', $ts ) : null,
				'hasta'      => $hasta,
				'cambiados'  => (object) array(),
				'eliminados' => (object) array(),
				'completo'   => true,
			), 200 );
		}

		$desde_mysql = gmdate( 'Y-m-d H:i:s', $ts );

		$tipos = array(
			'inventario' => PROMOTUR_Destinos::CPT,
			'eventos'    => CZUAPI_Eventos::CPT,
			'articulos'  => CZUAPI_Articulos::CPT,
			'recorridos' => CZUAPI_Recorridos::CPT,
		);

		$cambiados = array();
		foreach ( $tipos as $clave => $cpt ) {
			$cambiados[ $clave ] = array_map( 'intval', get_posts( array(
				'post_type'      => $cpt,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'date_query'     => array( array( 'column' => 'post_modified_gmt', 'after' => $desde_mysql ) ),
			) ) );
		}

		return new WP_REST_Response( array(
			'desde'      => gmdate( 'c', $ts ),
			'hasta'      => $hasta,
			'cambiados'  => $cambiados,
			'eliminados' => $this->eliminados( $desde_mysql, array_keys( $tipos ) ),
			'completo'   => false,
		), 200 );
	}

	/**
	 * @return array tipo => int[]
	 */
	private function eliminados( $desde_mysql, $tipos ) {
		global $wpdb;
		$table = self::table();

		$out = array_fill_keys( $tipos, array() );

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT tipo, object_id FROM {$table} WHERE deleted_at > %s",
			$desde_mysql
		), ARRAY_A );

		foreach ( (array) $rows as $r ) {
			if ( isset( $out[ $r['tipo'] ] ) ) {
				$out[ $r['tipo'] ][] = (int) $r['object_id'];
			}
		}
		return $out;
	}
}
