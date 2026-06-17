<?php
/**
 * Desinstalación: quita roles/caps y opciones del portal.
 * No borra los Destinos (contenido) para evitar pérdidas accidentales.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

require_once plugin_dir_path( __FILE__ ) . 'includes/class-destinos.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-roles.php';

if ( class_exists( 'PROMOTUR_Roles' ) ) {
	PROMOTUR_Roles::uninstall();
}

delete_option( 'promotur_version' );
delete_option( 'promotur_db_version' );
delete_option( 'promotur_invites' );
delete_option( 'promotur_destacados' );
delete_option( 'promotur_banner' );
delete_option( 'promotur_empty_searches' );

// Tablas custom (invitaciones y auditoría).
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}promotur_invitations" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}promotur_audit_log" );    // phpcs:ignore WordPress.DB

// Limpia las marcas de lectura de notificaciones.
delete_metadata( 'user', 0, 'promotur_notifs_read_at', '', true );
