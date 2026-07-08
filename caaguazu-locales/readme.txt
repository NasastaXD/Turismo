=== Caaguazú Locales ===
Contributors: municipalidadcaaguazu
Tags: tourism, reviews, whatsapp, maps, booking
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Locales turísticos para Caaguazú: reservas por WhatsApp, mapa interactivo editable a mano, reseñas con cuentas y panel para dueños de local.

== Description ==

Plugin complementario del tema **Caaguazú Turismo**. Agrega:

1. **Reservas por WhatsApp** — restaurantes y hoteles muestran un menú de opciones; el visitante elige una, edita el mensaje y al hacer clic se abre WhatsApp con el texto listo para enviar al número del local.
2. **Mapa interactivo con edición manual** — en vez de depender del marcado automático (que suele fallar), el admin o el dueño hacen clic en el mapa para colocar el marcador exacto y lo arrastran para ajustarlo.
3. **Sistema de cuentas y reseñas** — registro/login de visitantes; reseñas con estrellas, fotos, votos útiles, comentarios y respuestas.
4. **Cuentas de dueño de local** — formulario de solicitud que se envía a contacto@caaguazu.net; al aprobarse, el dueño accede a un panel para editar y gestionar su local.
5. **Autoactualización vía WordPress** — el plugin (y el tema) se actualizan desde un manifiesto JSON remoto, igual que cualquier plugin del repositorio oficial.

== Shortcodes ==

* `[caaguazu_locales tipo="restaurante"]` — directorio de locales (tipos: restaurante, hotel, comercio, atraccion).
* `[caaguazu_booking id="123"]` — widget de reserva por WhatsApp de un local.
* `[caaguazu_mapa tipo="" alto="480px"]` — mapa interactivo con todos los locales geolocalizados.
* `[caaguazu_resenas id="123"]` — bloque de reseñas de un local.
* `[caaguazu_cuenta]` — login, registro y solicitud de cuenta de dueño.
* `[caaguazu_dueno_panel]` — panel de gestión para dueños de local.

En la ficha individual de un local (`single`) las reseñas, la reserva y la info se muestran automáticamente.

== Instalación ==

1. Subir la carpeta `caaguazu-locales` a `/wp-content/plugins/` (o instalar el .zip desde Plugins → Añadir nuevo).
2. Activar el plugin. Se crean las tablas de reseñas y los roles (Visitante, Dueño de local).
3. Crear páginas y pegar los shortcodes deseados (ej. una página "Cuenta" con `[caaguazu_cuenta]` y otra "Panel" con `[caaguazu_dueno_panel]`).
4. Cargar locales desde **Locales → Añadir local**, marcando su ubicación en el mapa.

== Autoactualización ==

El plugin consulta `updates/caaguazu-locales.json` del repositorio. Subí ahí un JSON con `version` y `download_url` apuntando al .zip de la nueva versión y WordPress ofrecerá la actualización automáticamente. Filtros disponibles: `cgz_plugin_update_manifest`.

== Changelog ==

= 1.1.0 =
* Integración con el shell propio de Turismo del theme Caaguazú (`caaguazu_tourism_shell_items`): agrega un ítem "Dónde ir" con un link por tipo de local (restaurante, hotel, comercio, atracción) + "Ver todos". Suma también el filtrado real por tipo en el archivo del CPT (`?tipo=restaurante`), vía una query var pública y `pre_get_posts`.

= 1.0.0 =
* Versión inicial: reservas por WhatsApp, mapa editable, reseñas con cuentas, panel de dueño y autoactualización.
