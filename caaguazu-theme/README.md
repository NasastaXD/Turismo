# Caaguazú Turismo — Tema de WordPress

Conversión del sitio estático `caaguazu-static.zip` a un tema clásico de WordPress, editable desde el admin.

## Instalación

1. Comprimir esta carpeta (`caaguazu-theme`) en un `.zip` (si no lo está ya).
2. En WordPress: **Apariencia → Temas → Añadir nuevo → Subir tema**.
3. Activar.
4. Al activarse, el tema crea automáticamente las 27 páginas del sitio original, configura la home y arma el menú principal.

Después de activar, la home queda en `/` y todas las secciones en sus URLs originales (`/que-hacer`, `/la-capital-de-la-madera/historia`, etc.).

## Cómo editar contenido

Cada una de las 27 páginas tiene su HTML original guardado en `/content/` del tema. La plantilla `page.php` decide qué mostrar:

- Si la página **no tiene contenido en el editor de WP**, sirve el HTML estático original.
- Si pegás contenido en el editor, ese contenido **sobrescribe** al estático.

Para editar una página manteniendo el diseño:

1. Ir a **Páginas** en wp-admin.
2. Abrir la página que querés editar.
3. (Opcional) Copiar el HTML actual desde `/wp-content/themes/caaguazu/content/<slug>.html` al editor.
4. Modificar y guardar.

Para volver al original, basta con vaciar el contenido del editor.

## Estructura

```
caaguazu-theme/
├── style.css              ← header del tema (requerido por WP)
├── functions.php          ← setup, enqueue, requires
├── header.php             ← header con menú principal y selector ES/GN/EN
├── footer.php             ← footer del sitio
├── front-page.php         ← home (delega a page.php)
├── page.php               ← plantilla universal: WP content o HTML estático
├── index.php              ← fallback (blog, búsqueda, archivos)
├── 404.php
├── searchform.php
├── assets/
│   ├── css/
│   │   ├── styles.css     ← CSS principal (Tailwind compilado del sitio original)
│   │   └── leaflet.css    ← solo se carga en páginas con mapa
│   └── js/
│       ├── main.js        ← menú móvil, reveal-on-scroll, contadores, tooltips
│       └── map.js         ← mapa Leaflet (reemplaza el componente React)
├── content/               ← 27 HTML originales + manifest.json
└── inc/
    ├── nav-walker.php     ← walkers para menús primary y móvil
    └── page-seeder.php    ← importador automático de páginas
```

## Lo que NO se migró (y por qué)

- **Bundle de React y TanStack Router.** Innecesario para un tema clásico; el HTML pre-renderizado contiene todo el markup. El JS de `assets/js/main.js` reproduce la interactividad sin la dependencia.
- **Animaciones de transición entre rutas.** WordPress navega entre páginas con carga completa; las transiciones de SPA no aplican. Las animaciones de **reveal-on-scroll** y **contadores** sí están implementadas.
- **Selector de idioma funcional (GN/EN).** El selector es visual; para traducir realmente las páginas hay que instalar Polylang o WPML y duplicar contenido por idioma.
- **Imágenes de Unsplash.** El HTML las referencia por URL externa, igual que el sitio original. Si querés alojarlas localmente, reemplazá las URLs en los archivos de `/content/` o subílas al Media Library y editá las páginas.

## Re-importar páginas

Si necesitás resetear las páginas (por ejemplo, después de eliminarlas accidentalmente):

1. Ir a **Apariencia → Caaguazú** en wp-admin.
2. Click en **Re-importar páginas ahora**.

El re-importador no sobrescribe páginas existentes con contenido editado.

## Requisitos

- WordPress 6.0+
- PHP 7.4+
- Permalinks habilitados (Ajustes → Enlaces permanentes → "Nombre de la entrada" o superior). Sin permalinks bonitos, los slugs anidados (`/la-capital-de-la-madera/historia`) no funcionan.

## Compatibilidad

Tema clásico (no FSE). Probado conceptualmente con WP 6.5. Las plantillas usan PHP estándar y los hooks oficiales; no debería romperse con actualizaciones.
