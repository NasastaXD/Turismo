<?php
/**
 * Paneles y permisos por panel (los "permisos especiales" del pedido).
 *
 * Una cuenta universal puede tener acceso a varios paneles (hoy el Promotor
 * turístico; mañana otros), y en cada uno un rol con su set de capabilities.
 * El modelo es deliberadamente parecido al de roles del Portal, para que la
 * migración sea directa y la lógica de "gatear por capability, no por rol"
 * se conserve.
 *
 * - Registro de paneles: filtro `caaguazu_cuentas_panels` (o el helper
 *   caaguazu_register_panel()). Cada panel declara sus roles → caps.
 * - Grants: filas cuenta↔panel con un rol y, opcionalmente, un override de
 *   caps (JSON) para permisos finos por cuenta.
 * - Chequeo: account_can( cuenta, panel, cap ). Las caps efectivas de un grant
 *   son (caps del rol del panel) ∪ (override de caps del grant). Si el grant
 *   trae su propio snapshot de caps (p. ej. migración), ese snapshot alcanza
 *   aunque el panel todavía no esté registrado en este request.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Caaguazu_Cuentas_Panels {

	private static $instance = null;

	/** Registro de paneles cacheado por request. */
	private $panels = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * @return string Nombre de la tabla de grants.
	 */
	private static function table() {
		$t = Caaguazu_Cuentas_Install::tables();
		return $t['grants'];
	}

	/* --------------------------------------------------------------------- */
	/*  Registro de paneles                                                   */
	/* --------------------------------------------------------------------- */

	/**
	 * Paneles registrados: id → { label, roles: { rol → { label, caps } } }.
	 *
	 * @return array
	 */
	public function panels() {
		if ( null === $this->panels ) {
			/**
			 * Registro de paneles del sistema de cuentas. Cada plugin de panel
			 * agrega el suyo.
			 *
			 * @param array $panels
			 */
			$this->panels = (array) apply_filters( 'caaguazu_cuentas_panels', array() );
		}
		return $this->panels;
	}

	/**
	 * Definición de un panel puntual (o null).
	 *
	 * @param string $panel
	 * @return array|null
	 */
	public function panel( $panel ) {
		$panels = $this->panels();
		return isset( $panels[ $panel ] ) ? $panels[ $panel ] : null;
	}

	/**
	 * Caps de un rol dentro de un panel (o array vacío si no se conoce).
	 *
	 * @param string $panel
	 * @param string $role
	 * @return array cap → bool
	 */
	public function role_caps( $panel, $role ) {
		$def = $this->panel( $panel );
		if ( ! $def || empty( $def['roles'][ $role ]['caps'] ) ) {
			return array();
		}
		return (array) $def['roles'][ $role ]['caps'];
	}

	/* --------------------------------------------------------------------- */
	/*  Grants (cuenta ↔ panel)                                               */
	/* --------------------------------------------------------------------- */

	/**
	 * Otorga (o actualiza) el acceso de una cuenta a un panel.
	 *
	 * @param int         $account_id
	 * @param string      $panel
	 * @param string      $role
	 * @param array|null  $caps       override de caps { cap → bool }; si es null y
	 *                                el panel está registrado, se snapshotean las
	 *                                caps del rol para que el grant sea autónomo.
	 * @param int|null    $granted_by cuenta que otorga (auditoría)
	 * @return int|WP_Error ID del grant
	 */
	public function grant( $account_id, $panel, $role, $caps = null, $granted_by = null ) {
		global $wpdb;
		$account_id = (int) $account_id;
		$panel      = sanitize_key( $panel );
		$role       = sanitize_key( $role );
		if ( $account_id <= 0 || '' === $panel ) {
			return new WP_Error( 'invalid_grant', __( 'Datos de permiso inválidos.', 'caaguazu-cuentas' ) );
		}

		if ( null === $caps ) {
			$caps = $this->role_caps( $panel, $role ); // snapshot (puede quedar vacío)
		}
		$now = current_time( 'mysql', true );

		$existing = $this->get_grant( $account_id, $panel );
		$data     = array(
			'account_id' => $account_id,
			'panel'      => $panel,
			'role'       => $role,
			'caps'       => ! empty( $caps ) ? wp_json_encode( $caps ) : null,
			'status'     => 'active',
			'granted_by' => $granted_by ? (int) $granted_by : null,
			'granted_at' => $now,
		);

		if ( $existing ) {
			$wpdb->update( self::table(), $data, array( 'id' => (int) $existing['id'] ) ); // phpcs:ignore WordPress.DB
			$id = (int) $existing['id'];
		} else {
			$wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB
			$id = (int) $wpdb->insert_id;
		}

		/**
		 * Se otorgó/actualizó un permiso de panel.
		 *
		 * @param int    $account_id
		 * @param string $panel
		 * @param string $role
		 */
		do_action( 'caaguazu_cuentas_granted', $account_id, $panel, $role );
		return $id;
	}

	/**
	 * Revoca (marca inactivo) el acceso de una cuenta a un panel.
	 *
	 * @param int    $account_id
	 * @param string $panel
	 * @return bool
	 */
	public function revoke( $account_id, $panel ) {
		global $wpdb;
		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB
			self::table(),
			array( 'status' => 'revoked' ),
			array( 'account_id' => (int) $account_id, 'panel' => sanitize_key( $panel ) )
		);
	}

	/**
	 * Grant puntual cuenta↔panel (cualquier estado).
	 *
	 * @param int    $account_id
	 * @param string $panel
	 * @return array|null
	 */
	public function get_grant( $account_id, $panel ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT * FROM {$table} WHERE account_id = %d AND panel = %s",
			(int) $account_id,
			sanitize_key( $panel )
		), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Todos los grants activos de una cuenta.
	 *
	 * @param int $account_id
	 * @return array[] filas de grant
	 */
	public function account_grants( $account_id ) {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT * FROM {$table} WHERE account_id = %d AND status = 'active'",
			(int) $account_id
		), ARRAY_A );
		return $rows ? $rows : array();
	}

	/**
	 * ¿La cuenta tiene acceso (activo) a este panel?
	 *
	 * @param int    $account_id
	 * @param string $panel
	 * @return bool
	 */
	public function has_panel( $account_id, $panel ) {
		$grant = $this->get_grant( $account_id, $panel );
		return $grant && 'active' === $grant['status'];
	}

	/**
	 * Caps efectivas de una cuenta en un panel (rol del panel ∪ override/snapshot).
	 *
	 * @param int    $account_id
	 * @param string $panel
	 * @return array cap → bool
	 */
	public function effective_caps( $account_id, $panel ) {
		$grant = $this->get_grant( $account_id, $panel );
		if ( ! $grant || 'active' !== $grant['status'] ) {
			return array();
		}
		$caps = $this->role_caps( $panel, (string) $grant['role'] );
		if ( ! empty( $grant['caps'] ) ) {
			$override = json_decode( $grant['caps'], true );
			if ( is_array( $override ) ) {
				$caps = array_merge( $caps, $override );
			}
		}
		return $caps;
	}

	/**
	 * Chequeo de capability de una cuenta en un panel.
	 *
	 * @param int    $account_id
	 * @param string $panel
	 * @param string $cap
	 * @return bool
	 */
	public function account_can( $account_id, $panel, $cap ) {
		$caps = $this->effective_caps( $account_id, $panel );
		$can  = ! empty( $caps[ $cap ] );

		/**
		 * Permite ajustar el resultado de un chequeo de permiso.
		 *
		 * @param bool   $can
		 * @param int    $account_id
		 * @param string $panel
		 * @param string $cap
		 */
		return (bool) apply_filters( 'caaguazu_cuentas_account_can', $can, $account_id, $panel, $cap );
	}
}

/**
 * Helper para registrar un panel desde un plugin de panel.
 *
 * @param string $id
 * @param array  $args { label, roles }
 */
function caaguazu_register_panel( $id, array $args ) {
	add_filter( 'caaguazu_cuentas_panels', function ( $panels ) use ( $id, $args ) {
		$panels[ sanitize_key( $id ) ] = $args;
		return $panels;
	} );
}
