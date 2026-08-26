=== Caaguazú App API ===
Contributors: municipalidadcaaguazu
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

Capa REST que consume la app Android de turismo (Turismo App Czu).

== Description ==

Expone el contenido turístico y la identidad del ecosistema bajo
`/wp-json/czu-app/v1/`, para que la app Android los consuma.

**Es un plugin aparte a propósito.** El theme y el sitio público de
caaguazu.net van a rehacerse, y ese trabajo no debe poder romper la API de una
app ya publicada en la tienda. Por eso esta capa no usa el theme ni sus
helpers, no renderiza HTML, y lee el contenido de donde ya vive en vez de
duplicarlo.

**No reimplementa nada de lo que ya existe:**

* Identidad y cuentas → `caaguazu-cuentas`
* Permisos (rol + nivel de confianza) → `caaguazu_account_can()`
* Flujo editorial y visibilidad → `caaguazu-portal`
* Fichas turísticas → CPT `promotur_destino`

**Sí aporta lo que faltaba:** los CPTs Evento, Recorrido y Artículo, el rango
de precio numérico, los artículos relacionados, y el icono y color por
categoría.

== Endpoints ==

Namespace: `/wp-json/czu-app/v1/`

= Autenticación =
* `POST /auth/login` — email + contraseña → token bearer
* `POST /auth/logout`
* `GET  /auth/me`

= Contenido =
* `GET /categorias` — con icono, color y PNG de marcador
* `GET /zonas`
* `GET /inventario` — filtros: `categoria`, `zona`, `bbox`, `buscar`, `pagina`, `por_pagina`
* `GET /inventario/{id}`
* `GET /eventos` — filtros: `desde`, `hasta`, `categoria`
* `GET /eventos/{id}`
* `GET /mapa/markers`
* `GET /recorridos`, `GET /recorridos/{id}`
* `GET·POST·PUT·DELETE /mis-recorridos` — requiere token
* `GET /articulos`, `GET /articulos/{id}`

= Interfaz =
* `GET /strings/{locale}` — `es`, `en`, `gn`
* `GET /media-manifest`

= Sincronización =
* `GET /sync?since={iso8601}`

== Decisiones que conviene conocer ==

**El autor no es `post_author`.** WordPress exige un autor válido en cada
entrada, pero la gente del panel no es usuaria de WordPress, así que el autor
técnico de todo el contenido es el usuario de servicio. El autor que publica la
API sale del meta del dueño real. Si algún día se cambia eso, todos los
artículos van a aparecer firmados por `caaguazu-servicio`.

**`/sync` informa bajas, no solo altas.** Una lista de lo que cambió no
alcanza: si una ficha se despublica y el delta solo trae altas y cambios, el
teléfono la sigue mostrando para siempre. El plugin registra lápidas cada vez
que algo sale de publicado, y las devuelve en `eliminados`. Se conservan 90
días; con un `since` más viejo se responde `completo: true`, que le pide a la
app recargar todo desde cero.

**Costo total y compatibilidad de fechas se calculan, no se guardan.**
Guardarlos duplicaría un dato que cambia cuando cambia una ficha, y quedaría
desactualizado en silencio.

**Los markers van separados del mapa base.** Es lo que mantiene el mapa
retroactivo: se registra un lugar y el pin aparece sin regenerar nada. El mapa
base lo resuelve la app con tiles vectoriales embebidos; este plugin no genera
ni sirve tiles.

**Tabla de tokens propia, no la de sesiones de `caaguazu-cuentas`.** Una sesión
de navegador y un token de teléfono tienen ciclos de vida distintos — cerrar
sesión en la web no debe desloguear el celular. Misma disciplina: se guarda
solo el hash SHA-256, nunca el token.

== Instalación ==

1. Requiere `caaguazu-cuentas` y `caaguazu-portal` activos.
2. Subir a `/wp-content/plugins/` y activar. Crea sus dos tablas.
3. Cargar icono y color de cada categoría en **Destinos → Categorías**.

== Pendiente ==

* Escritura desde la app (`POST /contenido`) para que un promotor cargue
  fichas desde el teléfono. La lectura ya está; falta el alta.
* Pantalla de panel para editar textos e imágenes de UI. Hoy las opciones
  existen y la API las sirve, pero se cargan por código.
* Este plugin todavía no tiene auto-updater, igual que `caaguazu-cuentas` y
  `caaguazu-sso-cead`.
