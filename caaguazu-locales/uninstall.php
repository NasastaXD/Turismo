<?php
/**
 * Desinstalación: limpia tablas, roles y opciones.
 * Solo corre si el usuario elimina el plugin desde wp-admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

global $wpdb;

// Tablas custom.
$tables = array( 'cgz_reviews', 'cgz_review_replies', 'cgz_review_votes', 'cgz_review_photos' );
foreach ( $tables as $t ) {
	$table = $wpdb->prefix . $t;
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
}

// Opciones.
delete_option( 'cgz_db_version' );
delete_option( 'cgz_owner_requests' );
delete_option( 'cgz_account_page_id' );

// Roles.
remove_role( 'cgz_visitante' );
remove_role( 'cgz_dueno' );

// Nota: los posts del CPT `cgz_local` y sus metadatos NO se borran
// automáticamente para evitar pérdidas accidentales de contenido.
