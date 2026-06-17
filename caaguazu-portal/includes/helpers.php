<?php
/**
 * Helpers del portal.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * URL absoluta a una ruta del portal/auth.
 *
 * @param string $route ej. 'panel/equipo', 'login'
 * @return string
 */
function promotur_url( $route = '' ) {
	return home_url( '/' . ltrim( (string) $route, '/' ) );
}

/**
 * URL a un asset del plugin.
 *
 * @param string $path ej. 'icons/icon-192.png'
 * @return string
 */
function promotur_asset( $path ) {
	return PROMOTUR_URI . 'assets/' . ltrim( $path, '/' );
}

/**
 * Carga un template del plugin permitiendo override desde el theme activo
 * en /<theme>/promotur/<ruta>.php. Espeja el patrón de caaguazu-locales.
 *
 * @param string $route ruta sin extensión, ej. 'sections/home'
 * @param array  $vars  variables disponibles dentro del template
 */
function promotur_template( $route, $vars = array() ) {
	$rel      = ltrim( $route, '/' ) . '.php';
	$override = locate_template( array( 'promotur/' . $rel ) );
	$file     = $override ? $override : PROMOTUR_DIR . 'templates/' . $rel;

	if ( ! file_exists( $file ) ) {
		$file = PROMOTUR_DIR . 'templates/sections/404.php';
	}
	if ( ! empty( $vars ) ) {
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
	}
	include $file;
}

/**
 * Rol del portal "más alto" que tiene el usuario actual (o '').
 *
 * @param int|null $user_id
 * @return string
 */
function promotur_user_role( $user_id = null ) {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return '';
	}
	$priority = array( 'promotur_promotor', 'promotur_mini', 'promotur_visitante' );
	foreach ( $priority as $role ) {
		if ( in_array( $role, (array) $user->roles, true ) ) {
			return $role;
		}
	}
	// Admin u otros con acceso al panel pero sin rol de portal.
	if ( user_can( $user, 'manage_options' ) ) {
		return 'promotur_promotor';
	}
	return '';
}

/**
 * Etiqueta legible del rol del portal del usuario actual.
 *
 * @return string
 */
function promotur_role_label() {
	$role = promotur_user_role();
	if ( 'promotur_promotor' === $role && current_user_can( 'manage_options' ) && ! in_array( 'promotur_promotor', (array) wp_get_current_user()->roles, true ) ) {
		return __( 'Administrador', 'caaguazu-portal' );
	}
	return $role ? PROMOTUR_Roles::label( $role ) : __( 'Invitado', 'caaguazu-portal' );
}

/**
 * Ruta/sección actualmente renderizada por el shell (para estado activo del menú).
 *
 * @return string
 */
function promotur_current_route() {
	return isset( $GLOBALS['promotur_section'] ) ? (string) $GLOBALS['promotur_section'] : '';
}

/**
 * Wrapper legible de current_user_can.
 *
 * @param string $cap
 * @return bool
 */
function promotur_can( $cap ) {
	return current_user_can( $cap );
}

/**
 * Pequeño set de íconos SVG inline usados por el menú.
 *
 * @param string $name
 * @return string SVG markup
 */
function promotur_icon( $name ) {
	$paths = array(
		'home'    => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>',
		'doc'     => '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v4h4"/>',
		'edit'    => '<path d="M4 20h16"/><path d="M14 4l6 6L8 22H2v-6z"/>',
		'inbox'   => '<path d="M3 12h5l2 3h4l2-3h5"/><path d="M5 5h14l2 7v7H3v-7z"/>',
		'check'   => '<path d="M20 6 9 17l-5-5"/>',
		'tasks'   => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>',
		'star'    => '<path d="m12 3 2.9 5.9 6.6.9-4.8 4.6 1.1 6.5L12 18l-5.8 3 1.1-6.5L2.5 9.8l6.6-.9z"/>',
		'shield'  => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
		'team'    => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 6a3 3 0 0 1 0 6"/><path d="M21 20a6 6 0 0 0-5-6"/>',
		'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
		'image'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5L5 21"/>',
		'layout'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'search'  => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
		'bell'    => '<path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 20a2 2 0 0 0 4 0"/>',
		'logout'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
		'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4 12H2M22 12h-2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>',
		'install' => '<path d="M12 3v12"/><path d="m8 11 4 4 4-4"/><path d="M4 21h16"/>',
		'menu'    => '<path d="M4 6h16M4 12h16M4 18h16"/>',
	);
	$d = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['doc'];
	return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/**
 * Items del menú del panel: base + por capability. Cada item: { route, label, icon, cap }.
 * Se filtran luego por capability en el sidebar.
 *
 * @return array[]
 */
function promotur_nav_items() {
	$items = array(
		array( 'route' => 'panel',                'label' => __( 'Inicio', 'caaguazu-portal' ),        'icon' => 'home',   'cap' => 'promotur_view_panel' ),
		array( 'route' => 'panel/mis-contenidos', 'label' => __( 'Mis contenidos', 'caaguazu-portal' ),'icon' => 'doc',    'cap' => 'promotur_create_draft' ),
		array( 'route' => 'panel/editor',         'label' => __( 'Nueva ficha', 'caaguazu-portal' ),   'icon' => 'edit',   'cap' => 'promotur_edit_destino' ),
		array( 'route' => 'panel/captura',        'label' => __( 'Salida de campo', 'caaguazu-portal' ),'icon' => 'image', 'cap' => 'promotur_create_draft' ),
		array( 'route' => 'panel/revision',       'label' => __( 'Cola de revisión', 'caaguazu-portal' ),'icon' => 'inbox','cap' => 'promotur_review_content', 'badge' => 'revision' ),
		array( 'route' => 'panel/tareas',         'label' => __( 'Tareas', 'caaguazu-portal' ),         'icon' => 'tasks',  'cap' => 'promotur_view_own_tasks', 'badge' => 'tareas' ),
		array( 'route' => 'panel/curaduria',      'label' => __( 'Curaduría', 'caaguazu-portal' ),      'icon' => 'star',   'cap' => 'promotur_curate_featured' ),
		array( 'route' => 'panel/moderacion',     'label' => __( 'Moderación', 'caaguazu-portal' ),     'icon' => 'shield', 'cap' => 'promotur_moderate' ),
		array( 'route' => 'panel/equipo',         'label' => __( 'Equipo', 'caaguazu-portal' ),         'icon' => 'team',   'cap' => 'promotur_manage_team' ),
		array( 'route' => 'panel/reportes',       'label' => __( 'Reportes', 'caaguazu-portal' ),       'icon' => 'chart',  'cap' => 'promotur_view_reports' ),
		array( 'route' => 'panel/biblioteca',     'label' => __( 'Biblioteca', 'caaguazu-portal' ),     'icon' => 'image',  'cap' => 'promotur_manage_media' ),
		array( 'route' => 'panel/estructura',     'label' => __( 'Estructura', 'caaguazu-portal' ),     'icon' => 'layout', 'cap' => 'promotur_manage_structure' ),
		array( 'route' => 'panel/perfil',         'label' => __( 'Mi perfil', 'caaguazu-portal' ),      'icon' => 'user',   'cap' => 'promotur_edit_profile' ),
	);
	return apply_filters( 'promotur_nav_items', $items );
}

/**
 * Mensaje efímero (flash) por usuario, vía transient. Patrón PRG.
 *
 * @param string|null $msg  null = leer y limpiar; string = setear
 * @param string      $type info|success|error
 * @return array|null  al leer: { message, type } o null
 */
function promotur_flash( $msg = null, $type = 'info' ) {
	$uid = get_current_user_id();
	if ( ! $uid ) { return null; }
	$key = 'promotur_flash_' . $uid;

	if ( null === $msg ) {
		$data = get_transient( $key );
		if ( false !== $data ) {
			delete_transient( $key );
			return is_array( $data ) ? $data : null;
		}
		return null;
	}

	set_transient( $key, array( 'message' => (string) $msg, 'type' => $type ), 60 );
	return null;
}

/**
 * Avatar del usuario: imagen si tiene, o iniciales como fallback.
 *
 * @param int    $user_id
 * @param string $extra_class
 * @return string HTML
 */
function promotur_avatar( $user_id, $extra_class = '' ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) { return ''; }

	$has_gravatar = function_exists( 'get_avatar_url' ) ? get_avatar_url( $user_id ) : '';
	if ( $has_gravatar ) {
		return sprintf(
			'<span class="promotur-avatar %s"><img src="%s" alt="" width="36" height="36" loading="lazy"></span>',
			esc_attr( $extra_class ),
			esc_url( $has_gravatar )
		);
	}
	$name    = $user->display_name ? $user->display_name : $user->user_login;
	$parts   = preg_split( '/\s+/', trim( $name ) );
	$initials = strtoupper( mb_substr( $parts[0], 0, 1 ) . ( isset( $parts[1] ) ? mb_substr( $parts[1], 0, 1 ) : '' ) );
	return sprintf(
		'<span class="promotur-avatar promotur-avatar--initials %s" aria-hidden="true">%s</span>',
		esc_attr( $extra_class ),
		esc_html( $initials )
	);
}
