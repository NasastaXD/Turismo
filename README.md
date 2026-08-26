# Turismo Caaguazú

Portal oficial de turismo de **Caaguazú — Capital de la Madera del Paraguay**.

El repositorio contiene dos piezas que funcionan juntas sobre WordPress:

| Carpeta | Qué es |
|---|---|
| [`caaguazu-theme/`](caaguazu-theme) | Tema clásico de WordPress (conversión del sitio estático original, 27 páginas pre-cargadas). |
| [`caaguazu-locales/`](caaguazu-locales) | Plugin con locales, reservas por WhatsApp, mapa editable, reseñas con cuentas, panel de dueños y autoactualización. |
| [`caaguazu-cuentas/`](caaguazu-cuentas) | Sistema de cuentas universal, propio y separado de los usuarios de WordPress (para maximizar seguridad): identidad email+contraseña, sesión propia firmada y permisos por panel. Base para el Portal de Promotores y futuros paneles — el Portal ya corre enteramente sobre esta cuenta (login, permisos, autoría de fichas). |
| [`caaguazu-app-api/`](caaguazu-app-api) | Capa REST que consume la app Android de turismo (`/wp-json/czu-app/v1/`). Plugin aparte del sitio público a propósito: el rework de la web no debe poder romper la API de una app ya publicada. |
| [`caaguazu-sso-cead/`](caaguazu-sso-cead) | Acceso de un clic desde el panel del CEAD (curso de Servicios Turísticos) al Portal de Promotores: el CEAD afirma quién es la persona, este plugin decide la cuenta/rol acá. Orquesta sobre `caaguazu-cuentas` y `caaguazu-portal`, sin tocar su código. |
| [`updates/`](updates) | Manifiestos JSON que alimentan la autoactualización del tema y del plugin. |

> `Yeeye.zip` es el empaquetado original del tema; el contenido vive descomprimido en `caaguazu-theme/`.

## Funcionalidades del plugin

1. **Reservas por WhatsApp** — restaurantes y hoteles muestran un menú de opciones; el visitante elige una, edita el mensaje y al hacer clic se abre WhatsApp con el texto listo para enviar al número del local.
2. **Mapa interactivo con edición manual** — en lugar de marcado automático (que suele fallar), el admin o el dueño **hacen clic en el mapa** para colocar el marcador exacto y lo arrastran para ajustarlo.
3. **Cuentas y reseñas** — registro/login de visitantes; reseñas con estrellas, **fotos, votos útiles, comentarios y respuestas**.
4. **Cuentas de dueño de local** — formulario de solicitud que se envía a **contacto@caaguazu.net**; al aprobarse, el dueño accede a un panel para editar y gestionar su local.
5. **Autoactualización vía WordPress** — el plugin y el tema se actualizan desde un manifiesto JSON remoto, igual que cualquier plugin/tema del repositorio oficial.

## Instalación

1. **Tema:** subir `caaguazu-theme/` (o su `.zip`) a `wp-content/themes/` y activar. Crea las 27 páginas y el menú.
2. **Plugin:** subir `caaguazu-locales/` a `wp-content/plugins/` y activar. Crea las tablas de reseñas, los roles (Visitante, Dueño) y las páginas `Cuenta` y `Panel de mi local`.
3. Cargar locales desde **Locales → Añadir local**, marcando su ubicación con un clic en el mapa.

Las páginas **Dónde comer**, **Dónde alojarte** y **Mapa interactivo** del tema ya incluyen los shortcodes del plugin, así que se completan solas a medida que se cargan locales.

### Shortcodes

```
[caaguazu_locales tipo="restaurante"]   Directorio (restaurante|hotel|comercio|atraccion)
[caaguazu_booking id="123"]             Widget de reserva por WhatsApp
[caaguazu_mapa alto="480px"]            Mapa interactivo de locales
[caaguazu_resenas id="123"]             Reseñas de un local
[caaguazu_cuenta]                       Login / registro / solicitud de dueño
[caaguazu_dueno_panel]                  Panel de gestión para dueños
```

### Autoactualización

Cada componente consulta su manifiesto en `updates/`. Para publicar una versión nueva:
subí el `.zip` como release en GitHub y actualizá `version` + `download_url` del JSON correspondiente.
WordPress mostrará la actualización en **Escritorio → Actualizaciones**.
