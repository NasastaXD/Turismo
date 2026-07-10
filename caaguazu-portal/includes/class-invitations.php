<?php
/**
 * Invitaciones (invite-only) en tabla custom. Token en claro solo se muestra una vez
 * (y se guarda en metadata para reconstruir el link corto /i/<token> desde wp-admin).
 * Modelado en el plugin CEAD (modules/auth/class-invitations.php).
 *
 * `invited_by` y `used_by_user_id` son IDs de cuenta del sistema de cuentas
 * universal (caaguazu-cuentas), no de wp_users — no hay FK real (columnas
 * BIGINT simples), así que el cambio de espacio de IDs no requiere migración
 * de esquema, sólo de significado hacia adelante.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Invitations {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'promotur_invitations';
	}

	public static function hash( $token ) {
		return hash( 'sha256', (string) $token );
	}

	public static function token() {
		return wp_generate_password( 16, false, false ); // 16 alfanuméricos
	}

	/**
	 * Crea N invitaciones.
	 *
	 * @param array $args { role, email, expires_days, count }
	 * @return string[] tokens en claro (única oportunidad de verlos)
	 */
	public static function create( $args = array() ) {
		global $wpdb;
		$args = wp_parse_args( $args, array(
			'role'         => 'promotur_mini',
			'email'        => '',
			'expires_days' => 14,
			'count'        => 1,
		) );

		$role = array_key_exists( $args['role'], PROMOTUR_Roles::roles() ) ? $args['role'] : 'promotur_mini';
		$count   = max( 1, min( 100, (int) $args['count'] ) );
		$email   = $args['email'] ? sanitize_email( $args['email'] ) : null;
		$expires = gmdate( 'Y-m-d H:i:s', time() + ( (int) $args['expires_days'] * DAY_IN_SECONDS ) );
		$now     = current_time( 'mysql', 1 );
		$by      = caaguazu_account_id(); // 0 si la crea un administrador de WP (bypass), no rompe el insert.

		$tokens = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$token = self::token();
			$wpdb->insert( self::table(), array(
				'token_hash' => self::hash( $token ),
				'email'      => $email,
				'role'       => $role,
				'invited_by' => $by,
				'expires_at' => $expires,
				'created_at' => $now,
				'metadata'   => wp_json_encode( array( 'token' => $token ) ),
			), array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' ) );

			$tokens[] = $token;
			if ( class_exists( 'PROMOTUR_Audit' ) ) {
				PROMOTUR_Audit::log( 'invitation_created', array( 'entity_type' => 'invitation', 'entity_id' => (int) $wpdb->insert_id, 'payload' => array( 'role' => $role ) ) );
			}
		}
		return $tokens;
	}

	public static function find_by_token( $token ) {
		global $wpdb;
		if ( ! $token ) { return null; }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token_hash = %s', self::hash( $token ) ), ARRAY_A );
		return $row ?: null;
	}

	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Estado calculado: valid|used|expired|revoked|invalid.
	 */
	public static function status( $row ) {
		if ( ! $row ) { return 'invalid'; }
		if ( ! empty( $row['revoked_at'] ) ) { return 'revoked'; }
		if ( ! empty( $row['used_at'] ) ) { return 'used'; }
		if ( strtotime( $row['expires_at'] . ' UTC' ) < time() ) { return 'expired'; }
		return 'valid';
	}

	public static function status_label( $status ) {
		$map = array(
			'valid'   => __( 'Válida', 'caaguazu-portal' ),
			'used'    => __( 'Usada', 'caaguazu-portal' ),
			'expired' => __( 'Expirada', 'caaguazu-portal' ),
			'revoked' => __( 'Revocada', 'caaguazu-portal' ),
			'invalid' => __( 'Inválida', 'caaguazu-portal' ),
		);
		return $map[ $status ] ?? $status;
	}

	public static function mark_used( $id, $user_id ) {
		global $wpdb;
		$wpdb->update( self::table(),
			array( 'used_at' => current_time( 'mysql', 1 ), 'used_by_user_id' => (int) $user_id ),
			array( 'id' => (int) $id ), array( '%s', '%d' ), array( '%d' )
		);
		if ( class_exists( 'PROMOTUR_Audit' ) ) {
			PROMOTUR_Audit::log( 'invitation_used', array( 'entity_type' => 'invitation', 'entity_id' => (int) $id, 'user_id' => (int) $user_id ) );
		}
	}

	public static function revoke( $id ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'revoked_at' => current_time( 'mysql', 1 ) ), array( 'id' => (int) $id ), array( '%s' ), array( '%d' ) );
		if ( class_exists( 'PROMOTUR_Audit' ) ) {
			PROMOTUR_Audit::log( 'invitation_revoked', array( 'entity_type' => 'invitation', 'entity_id' => (int) $id ) );
		}
	}

	/**
	 * Últimas invitaciones (para wp-admin).
	 *
	 * @return array[]
	 */
	public static function recent( $limit = 50 ) {
		global $wpdb;
		$limit = max( 1, min( 200, (int) $limit ) );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A );
	}

	/** Token en claro almacenado en metadata (para reconstruir el link). */
	public static function plain_token( $row ) {
		if ( empty( $row['metadata'] ) ) { return ''; }
		$meta = json_decode( $row['metadata'], true );
		return is_array( $meta ) && ! empty( $meta['token'] ) ? $meta['token'] : '';
	}

	/** Link corto de registro. */
	public static function registration_url( $token ) {
		return promotur_url( 'i/' . rawurlencode( $token ) );
	}
}
