<?php
/**
 * Helpers del portal.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Nombre para mostrar de una cuenta (autor/revisor de una ficha, etc.).
 * Acepta un ID de cuenta del sistema de cuentas universal.
 *
 * @param int    $account_id
 * @param string $fallback texto si no se puede resolver (cuenta borrada, ID 0, etc.)
 * @return string
 */
function promotur_account_display_name( $account_id, $fallback = '—' ) {
	$account_id = (int) $account_id;
	if ( $account_id <= 0 || ! class_exists( 'Caaguazu_Cuentas_Accounts' ) ) {
		return $fallback;
	}
	$account = Caaguazu_Cuentas_Accounts::get( $account_id );
	if ( ! $account ) { return $fallback; }
	return $account['display_name'] ? $account['display_name'] : $account['email'];
}

/**
 * Miembros del equipo del panel "promotor": cuentas con grant activo,
 * opcionalmente filtradas por rol. Reemplaza a `get_users( array( 'role' =>
 * ... ) )` en las pantallas del panel (Equipo, Moderación, Reportes, Tareas)
 * ahora que los promotores no son usuarios de WordPress — el "quién puede
 * asignarse esto" vive en `caaguazu_grants`, no en `wp_users`.
 *
 * @param string|string[]|null $roles rol o roles a filtrar (null = todos los roles del panel)
 * @return array[] { id (int, ID de cuenta), email, display_name, role }
 */
function promotur_team_members( $roles = null ) {
	if ( ! class_exists( 'Caaguazu_Cuentas_Install' ) ) { return array(); }
	global $wpdb;
	$t     = Caaguazu_Cuentas_Install::tables();
	$roles = $roles ? (array) $roles : null;

	$sql    = "SELECT a.id, a.email, a.display_name, g.role
		FROM {$t['grants']} g
		INNER JOIN {$t['accounts']} a ON a.id = g.account_id
		WHERE g.panel = %s AND g.status = 'active' AND a.status = 'active'";
	$params = array( 'promotor' );
	if ( $roles ) {
		$placeholders = implode( ',', array_fill( 0, count( $roles ), '%s' ) );
		$sql         .= " AND g.role IN ($placeholders)";
		$params       = array_merge( $params, $roles );
	}
	$sql .= ' ORDER BY a.display_name ASC';

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $rows ) { return array(); }
	foreach ( $rows as &$r ) {
		$r['id']           = (int) $r['id'];
		$r['display_name'] = $r['display_name'] ? $r['display_name'] : $r['email'];
	}
	return $rows;
}

/**
 * URL absoluta a una ruta del portal/auth.
 *
 * El panel vive bajo /turismo/panel (no en la raíz) — las demás rutas
 * (login, registro, recuperar, salir) quedan como estaban.
 *
 * @param string $route ej. 'panel/equipo', 'login'
 * @return string
 */
function promotur_url( $route = '' ) {
	$route = ltrim( (string) $route, '/' );
	if ( 'panel' === $route || 0 === strpos( $route, 'panel/' ) ) {
		$route = 'turismo/' . $route;
	}
	return home_url( '/' . $route );
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
 * Rol del portal "más alto" de una cuenta (o '').
 *
 * Sin argumento: resuelve la cuenta ACTUAL del sistema de cuentas universal
 * (caaguazu-cuentas), o cae en el bypass de administrador de WP si no hay
 * cuenta propia logueada. Con un $user_id explícito: uso legado de
 * wp-admin (class-admin.php lista todavía usuarios de WordPress con roles
 * promotur_* — pantalla no migrada aún, ver README de caaguazu-cuentas).
 *
 * @param int|null $user_id ID de usuario de WordPress (uso legado, no de cuenta).
 * @return string
 */
function promotur_user_role( $user_id = null ) {
	if ( null === $user_id ) {
		if ( function_exists( 'caaguazu_account_id' ) && caaguazu_account_id() > 0 ) {
			$grant = Caaguazu_Cuentas_Panels::instance()->get_grant( caaguazu_account_id(), 'promotor' );
			return ( $grant && 'active' === $grant['status'] ) ? (string) $grant['role'] : '';
		}
		// Sin cuenta propia: ¿es un administrador de WP con acceso vía bypass?
		return current_user_can( 'manage_options' ) ? 'promotur_promotor' : '';
	}

	$user = get_userdata( $user_id );
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
 * Etiqueta legible del rol del portal de la cuenta actual.
 *
 * @return string
 */
function promotur_role_label() {
	$role = promotur_user_role();
	// El rol sólo puede salir de "promotur_promotor" sin una cuenta propia
	// (caaguazu_account_id() <= 0) por el bypass de administrador de WP.
	if ( 'promotur_promotor' === $role && function_exists( 'caaguazu_account_id' ) && caaguazu_account_id() <= 0 ) {
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
 * Wrapper legible del chequeo de capability del panel "promotor" (cuenta
 * actual, con bypass de administrador de WP incluido — ver
 * caaguazu_account_can()).
 *
 * @param string $cap
 * @return bool
 */
function promotur_can( $cap ) {
	return function_exists( 'caaguazu_account_can' ) ? caaguazu_account_can( 'promotor', $cap ) : current_user_can( $cap );
}

/**
 * Identidad normalizada de "quién es" para la topbar/perfil/equipo: cuenta
 * propia si hay una logueada, o los datos del administrador de WP si entró
 * por el bypass. Evita que cada template tenga que resolver esa rama a mano.
 *
 * @return array{id:int,display_name:string,email:string,phone:string,is_admin_bypass:bool}
 */
function promotur_current_identity() {
	$account_id = function_exists( 'caaguazu_account_id' ) ? caaguazu_account_id() : 0;
	if ( $account_id > 0 ) {
		$account = caaguazu_current_account();
		return array(
			'id'              => $account_id,
			'display_name'    => $account['display_name'] ? $account['display_name'] : $account['email'],
			'email'           => $account['email'],
			'phone'           => (string) $account['phone'],
			'is_admin_bypass' => false,
		);
	}
	$wp_user = wp_get_current_user();
	return array(
		'id'              => 0,
		'display_name'    => $wp_user->display_name ? $wp_user->display_name : $wp_user->user_login,
		'email'           => (string) $wp_user->user_email,
		'phone'           => '',
		'is_admin_bypass' => true,
	);
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
		'help'    => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.8.4-1 .9-1 1.7"/><path d="M12 17h.01"/>',
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
		array( 'route' => 'panel/ayuda',          'label' => __( 'Ayuda', 'caaguazu-portal' ),          'icon' => 'help',   'cap' => 'promotur_view_panel' ),
	);
	return apply_filters( 'promotur_nav_items', $items );
}

/**
 * Teléfono de la cuenta actual (columna `phone` de caaguazu_accounts).
 *
 * @param int|null $user_id ID de usuario de WordPress (uso legado; ignorado
 *                          para la cuenta actual, que ya no es un WP user).
 * @return string
 */
function promotur_user_phone( $user_id = null ) {
	if ( null === $user_id && function_exists( 'caaguazu_current_account' ) ) {
		$account = caaguazu_current_account();
		return $account ? (string) $account['phone'] : '';
	}
	return $user_id ? (string) get_user_meta( $user_id, '_promotur_phone', true ) : '';
}

/**
 * Mensaje efímero (flash) por visitante, vía transient. Patrón PRG.
 *
 * La clave usa el ID de cuenta si hay una logueada, o el ID de WordPress
 * prefijado ("wp123") para el bypass de administrador — así nunca colisiona
 * un ID de cuenta con un ID de usuario de WP que casualmente coincida.
 *
 * @param string|null $msg  null = leer y limpiar; string = setear
 * @param string      $type info|success|error
 * @return array|null  al leer: { message, type } o null
 */
function promotur_flash( $msg = null, $type = 'info' ) {
	$account_id = function_exists( 'caaguazu_account_id' ) ? caaguazu_account_id() : 0;
	if ( $account_id > 0 ) {
		$uid = $account_id;
	} else {
		$wp_id = get_current_user_id();
		if ( ! $wp_id ) { return null; }
		$uid = 'wp' . $wp_id;
	}
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
 * Avatar: imagen (Gravatar por email) si hay, o iniciales como fallback.
 *
 * Acepta dos formas: un ID de usuario de WordPress (uso legado — todavía lo
 * usa templates/sections/equipo.php, que sigue listando usuarios de WP, ver
 * README de caaguazu-cuentas), o un array de identidad normalizada
 * { email, display_name } como el que devuelve promotur_current_identity().
 *
 * @param int|array $identity
 * @param string    $extra_class
 * @return string HTML
 */
function promotur_avatar( $identity, $extra_class = '' ) {
	if ( is_array( $identity ) ) {
		$email = (string) ( $identity['email'] ?? '' );
		$name  = (string) ( $identity['display_name'] ?? '' );
		if ( '' === $email && '' === $name ) { return ''; }
	} else {
		$user = get_userdata( (int) $identity );
		if ( ! $user ) { return ''; }
		$email = $user->user_email;
		$name  = $user->display_name ? $user->display_name : $user->user_login;
	}

	$has_gravatar = ( $email && function_exists( 'get_avatar_url' ) ) ? get_avatar_url( $email ) : '';
	if ( $has_gravatar ) {
		return sprintf(
			'<span class="promotur-avatar %s"><img src="%s" alt="" width="36" height="36" loading="lazy"></span>',
			esc_attr( $extra_class ),
			esc_url( $has_gravatar )
		);
	}
	$parts    = preg_split( '/\s+/', trim( $name ) );
	$initials = strtoupper( mb_substr( $parts[0], 0, 1 ) . ( isset( $parts[1] ) ? mb_substr( $parts[1], 0, 1 ) : '' ) );
	return sprintf(
		'<span class="promotur-avatar promotur-avatar--initials %s" aria-hidden="true">%s</span>',
		esc_attr( $extra_class ),
		esc_html( $initials )
	);
}
