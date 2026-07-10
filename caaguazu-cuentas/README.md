# Caaguazú Cuentas — Sistema de cuentas universal

Sistema de cuentas **propio y separado de los usuarios de WordPress**, pensado
para maximizar la seguridad de los paneles del ecosistema: el login de un panel
no es un login de WordPress, así que las cuentas de las personas no exponen
`wp-admin` ni XML-RPC, y comprometer un panel no da acceso al WordPress (ni al
revés).

Da tres cosas a cualquier panel que lo use — el **Portal de Promotores** hoy, y
cualquier panel futuro:

1. **Identidad**: cuentas con email + contraseña, en una tabla propia.
2. **Sesión propia**: cookie firmada + tabla de sesiones (token hasheado),
   independiente de la sesión de WordPress.
3. **Permisos por panel**: una cuenta puede tener acceso a varios paneles, cada
   uno con su rol y sus capabilities (los “permisos especiales” por panel).

## Por qué “no usar usuarios de WordPress”

Los usuarios de WordPress arrastran una superficie de ataque grande (wp-admin,
XML-RPC, enumeración de usuarios, la cookie de auth compartida con todo el
sitio). Un panel de trabajo no necesita nada de eso. Con cuentas propias:

- Las personas de un panel **nunca** tienen un usuario de WordPress con login.
- La sesión del panel es una cookie propia, firmada con HMAC sobre las sales de
  WP, `HttpOnly` + `Secure` + `SameSite=Lax`, y en la base sólo vive el **hash**
  del token (si alguien lee la tabla no puede fabricar cookies válidas).
- Las contraseñas se hashean con **bcrypt** (`password_hash`). Los promotores
  migrados conservan su contraseña porque reusamos su hash de WordPress
  (phpass), que el verificador reconoce, y lo **regraba a bcrypt** en el primer
  login (rehash transparente).

## Autoría del contenido (usuario de servicio)

WordPress exige un `post_author` válido para cada entrada. Como ninguna persona
tiene ya un usuario de WordPress, el contenido creado desde los paneles se
atribuye a **un** usuario de servicio bloqueado (sin login, contraseña
aleatoria: `caaguazu-servicio`), y el dueño real se guarda aparte. Ver
`caaguazu_service_user_id()`. Ningún humano se autentica jamás como ese usuario.

## Arquitectura

```
caaguazu-cuentas/
  caaguazu-cuentas.php        Bootstrap, constantes, activación, upgrades.
  includes/
    class-install.php         Tablas (accounts/sessions/grants) + usuario de servicio.
    class-passwords.php       Hash/verify (bcrypt + fallback phpass) + rehash.
    class-accounts.php        CRUD de la identidad.
    class-sessions.php        Sesión propia: cookie firmada + tabla de sesiones.
    class-panels.php          Registro de paneles y permisos por panel (grants).
    class-auth.php            Login/registro/recuperación/reset (lógica pura).
    class-migration.php       Migración automática de promotores WP → cuentas.
    api.php                   API pública (caaguazu_*) + puente con la sesión WP.
```

### Tablas

- `{prefix}caaguazu_accounts` — `email` (único), `pass_hash`, `display_name`,
  `phone`, `status` (`active`/`suspended`/`pending`), `wp_user_id` (traza de
  migración/servicio), timestamps, `metadata` (JSON).
- `{prefix}caaguazu_sessions` — `account_id`, `token_hash` (único), `expires_at`,
  `last_seen_at`, `ip`, `user_agent`.
- `{prefix}caaguazu_grants` — `account_id`, `panel`, `role`, `caps` (JSON de
  override/snapshot), `status`. Único por `(account_id, panel)`.

## API pública (lo que consume un panel)

```php
caaguazu_current_account();                 // array|null (cuenta actual)
caaguazu_account_id();                       // int (0 si no hay)
caaguazu_is_logged_in();                     // bool

caaguazu_account_login( $email, $pass, $remember = false ); // array|WP_Error
caaguazu_account_logout();

caaguazu_account_can( $panel, $cap );        // bool (permiso por panel)
caaguazu_account_has_panel( $panel );        // bool (acceso al panel)
caaguazu_account_grant( $account_id, $panel, $role, $caps = null );

caaguazu_service_user_id();                  // int (autor de servicio para el contenido)
```

### Registrar un panel (permisos)

Un panel declara sus roles → capabilities con el filtro `caaguazu_cuentas_panels`
(o el helper `caaguazu_register_panel()`), con la misma forma que los roles del
Portal:

```php
caaguazu_register_panel( 'promotor', array(
	'label' => 'Promotor turístico',
	'roles' => array(
		'promotur_promotor' => array( 'label' => 'Promotor', 'caps' => array(
			'promotur_view_panel'   => true,
			'promotur_edit_destino' => true,
			// …
		) ),
		'promotur_mini' => array( 'label' => 'Mini Promotor', 'caps' => array(
			'promotur_view_panel' => true,
		) ),
	),
) );
```

Las **capabilities efectivas** de una cuenta en un panel son las del rol del
panel, más el override por cuenta guardado en el grant (`caps` JSON) — así se
pueden dar **permisos especiales** a una cuenta puntual sin tocar el rol, e
incluso revocar una cap del rol para esa cuenta.

## Migración de los promotores existentes

Automática e idempotente (`Caaguazu_Cuentas_Migration`): en `admin_init`
(con un lock corto) levanta a cada usuario de WordPress con rol `promotur_*` que
todavía no tenga cuenta, copiando su email, su **hash de contraseña** (sin
resetear nada) y su rol como grant del panel `promotor`. Como corre
repetidamente, también levanta a los promotores nuevos que se sigan creando por
el flujo viejo durante la etapa de convivencia. A los **administradores** no se
los migra: conservan su login de WordPress.

## Puente (admins de WordPress)

Los administradores de WordPress no se migran a cuentas (siguen entrando por
su login de wp-admin de siempre) pero conservan acceso total a cualquier panel
vía `caaguazu_wp_admin_bypass()` — usado internamente por
`caaguazu_account_can()`/`caaguazu_account_has_panel()` cuando no hay una
cuenta propia logueada. El filtro `caaguazu_cuentas_bridge_wp_session` sigue
existiendo para resolver la cuenta migrada de un usuario WP logueado, por si
algún flujo residual de WordPress lo necesita.

## Estado

**v0.2.0 — cutover integrado.** El Portal de Promotores (`caaguazu-portal`
2.0.0) ya corre enteramente sobre esta API: login, registro, recuperación de
contraseña, permisos por sección, autoría de fichas (usuario de servicio +
meta de dueño real) y niveles de confianza. Ninguna persona del panel tiene ya
un usuario de WordPress. Pendiente como trabajo aparte (documentado en el
README de caaguazu-portal): la pantalla de wp-admin "Usuarios" del Portal
sigue listando usuarios de WordPress legados — no es parte del flujo diario
de un promotor, así que quedó fuera de esta ronda.
