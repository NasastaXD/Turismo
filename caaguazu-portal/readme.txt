=== Caaguazú Portal — Promotores Turísticos ===
Contributors: municipalidadcaaguazu
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
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

== Changelog ==

= 1.0.0 =
* Fase 0 (framework del panel) + Fase 1 (MVP editorial).
