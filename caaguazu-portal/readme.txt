=== Caaguazú Portal — Promotores Turísticos ===
Contributors: municipalidadcaaguazu
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later

Panel autenticado tipo app (PWA) con enrutador propio, login propio, roles y un MVP editorial (fichas de destino con flujo borrador → revisión → publicación).

== Description ==

Plugin del Portal de Promotores Turísticos de Caaguazú. Monta un panel sobre rutas propias
(no usa el theme para el panel) con sensación de aplicación: sidebar + topbar + contenido,
instalable como PWA, con modo claro/oscuro y los colores del sitio heredados vía tokens CSS.

**Fase 0 — Framework del panel**
* Enrutador propio (rewrite rules) con guards: `/login`, `/registro`, `/recuperar`, `/recuperar/restablecer`, `/salir`, invitación `/i/{token}`, PWA (`/promotur-manifest.webmanifest`, `/promotur-sw.js`, `/promotur-icon-{n}.png`, `/promotur-offline`) y panel `/panel/...`.
* Shell único + contrato de página (`$page_title` + `$body` + `include shell.php`).
* Sidebar y topbar dinámicos, gateados por capability (no por rol).
* Tokens CSS que heredan del theme con fallback; modo claro/oscuro persistente.
* PWA instalable con lectura offline; override de templates desde el theme en `/<theme>/promotur/<ruta>.php`.
* Roles: Promotor, Mini Promotor, Visitante (capabilities `promotur_*`).

**Fase 1 — MVP editorial**
* CPT Destino (ficha guiada) + taxonomías (categoría, zona, etiqueta).
* Editor con checklist de mínimos en vivo (bloquea el envío si falta algo) + subida de fotos + geolocalización.
* Flujo: borrador → enviar → cola de revisión (asignarme) → aprobar/devolver con feedback → publicado.
* "Mis contenidos" (Mini Promotor) y "Cola de revisión" (Promotor) funcionando.
* Vitrina pública mínima: ficha pública (single), `[promotur_destinos]` (grid) y `[promotur_mapa]` (Leaflet).

== Shortcodes ==

* `[promotur_destinos categoria="" cantidad="24"]` — grid de destinos publicados.
* `[promotur_mapa alto="480px" categoria=""]` — mapa de destinos con coordenadas.

== Instalación ==

1. Subir `caaguazu-portal` a `/wp-content/plugins/` y activar.
2. Se crean los roles y se vacían (flush) las rewrite rules automáticamente.
3. Entrar a `/login`, crear una cuenta o iniciar sesión, e ir a `/panel`.

Convive con el plugin `caaguazu-locales` sin colisiones (prefijos distintos).

== Auto-actualización ==

El plugin se actualiza desde wp-admin sin pasar por WordPress.org, usando
plugin-update-checker (vendoreado en `vendor/`) contra los GitHub Releases del
repositorio. Al hacer push a `main`, el workflow `.github/workflows/publish-releases.yml`
lee la versión del header, empaqueta `caaguazu-portal.zip` y publica el release
`v{version}`; el checker lo detecta (~cada 12 h) y ofrece la actualización.

* Versión en un solo lugar: header `Version:` + constante `PROMOTUR_VERSION` (semver).
* Migraciones de BD: incrementar `PROMOTUR_DB_VERSION`; corren solas en `admin_init`
  vía `promotur_run_migrations()`.
* Repo privado: definir `PROMOTUR_GITHUB_TOKEN` (PAT de solo lectura) en `wp-config.php`.

== Changelog ==

= 1.1.3 =
* Integración con el shell propio de Turismo del theme Caaguazú (`caaguazu_tourism_shell_items`): agrega "Destinos" (desplegable con las categorías reales de `promotur_categoria`) y, solo para usuarios logueados con el permiso `promotur_view_panel`, un link directo al panel de promotor.

= 1.1.0 =
* Registro INVITE-ONLY con teléfono obligatorio; invitaciones en tabla custom con link corto /i/<token>.
* Gestión en wp-admin: Usuarios (editar/eliminar/suspender), Invitaciones y Logs (usuarios y posts) sobre una tabla de auditoría.
* Suspensión reversible de usuarios. Sección "Ayuda" en el panel. Barra de navegación inferior en móvil y pulido del modo claro.

= 1.0.0 =
* Fase 0 (framework del panel) + Fase 1 (MVP editorial).
