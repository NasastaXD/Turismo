<?php
/**
 * Auditoría centralizada: registra acciones de usuarios (login, registro, invites,
 * suspensión) y de posts (ciclo editorial de los destinos). Modelado en CEAD.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Audit {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_login', array( __CLASS__, 'on_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( __CLASS__, 'on_login_failed' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'promotur_audit_log';
	}

	/** Acciones consideradas "de posts" (para la pestaña de logs de posts). */
	public static function post_actions() {
		return array( 'destino_created', 'destino_enviado', 'destino_publicado', 'destino_necesita_cambios', 'destino_aprobado' );
	}

	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return substr( sanitize_text_field( $ip ), 0, 64 );
	}

	/**
	 * Registra un evento.
	 *
	 * @param string $action
	 * @param array  $args { user_id, entity_type, entity_id, payload(array) }
	 */
	public static function log( $action, array $args = array() ) {
		global $wpdb;
		$payload = $args['payload'] ?? null;
		if ( is_array( $payload ) ) {
			$payload = wp_json_encode( $payload );
		}
		// user_id: ID de cuenta del sistema de cuentas universal (o el ID de
		// WordPress del administrador, cuando entra por el bypass — ambos
		// espacios de ID conviven en esta columna de auditoría sin FK real).
		$default_actor = function_exists( 'caaguazu_account_id' ) ? ( caaguazu_account_id() ?: get_current_user_id() ) : get_current_user_id();
		return (bool) $wpdb->insert( self::table(), array(
			'user_id'     => isset( $args['user_id'] ) ? (int) $args['user_id'] : ( $default_actor ?: null ),
			'action'      => substr( sanitize_key( $action ), 0, 80 ),
			'entity_type' => isset( $args['entity_type'] ) ? substr( sanitize_key( $args['entity_type'] ), 0, 60 ) : null,
			'entity_id'   => isset( $args['entity_id'] ) ? (int) $args['entity_id'] : null,
			'payload'     => $payload,
			'ip'          => self::client_ip(),
			'created_at'  => current_time( 'mysql' ),
		), array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' ) );
	}

	public static function on_login( $user_login, $user = null ) {
		$uid = $user instanceof WP_User ? $user->ID : 0;
		self::log( 'login_success', array( 'user_id' => $uid, 'entity_type' => 'user', 'entity_id' => $uid ) );
	}

	public static function on_login_failed( $user_login ) {
		self::log( 'login_failed', array( 'user_id' => 0, 'payload' => array( 'login' => substr( (string) $user_login, 0, 60 ) ) ) );
	}

	/**
	 * Consulta paginada para wp-admin.
	 *
	 * @param array $args { actions(array), search, paged, per_page }
	 * @return array{ rows:array, total:int, pages:int }
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array( 'actions' => array(), 'search' => '', 'paged' => 1, 'per_page' => 50 ) );
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['actions'] ) ) {
			$ph     = implode( ',', array_fill( 0, count( $args['actions'] ), '%s' ) );
			$where[] = "action IN ($ph)";
			$params  = array_merge( $params, array_map( 'sanitize_key', $args['actions'] ) );
		}
		if ( '' !== $args['search'] ) {
			$where[]  = '(action LIKE %s OR payload LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}
		$where_sql = implode( ' AND ', $where );

		$per   = max( 1, min( 200, (int) $args['per_page'] ) );
		$paged = max( 1, (int) $args['paged'] );
		$off   = ( $paged - 1 ) * $per;

		$total_sql = "SELECT COUNT(*) FROM " . self::table() . " WHERE $where_sql";
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $total_sql, $params ) ) : $wpdb->get_var( $total_sql ) );

		$rows_sql = "SELECT * FROM " . self::table() . " WHERE $where_sql ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, array_merge( $params, array( $per, $off ) ) ), ARRAY_A );

		return array( 'rows' => $rows ? $rows : array(), 'total' => $total, 'pages' => (int) ceil( $total / $per ) );
	}
}
