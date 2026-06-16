# Mapas SVG de Caaguazú (para MapSVG)

Tres archivos, todos en formato MapSVG (`<path>` con `id` slug + `title`/`<title>` con acentos),
`viewBox` de 1000 px de ancho y proyección equirectangular con corrección de aspecto por latitud:

| Archivo | Qué muestra | Fronteras |
|---|---|---|
| `caaguazu-distritos.svg` | Departamento de Caaguazú, 22 distritos | **Reales** (geoBoundaries) |
| `caaguazu-ciudad.svg` | Ciudad/municipio de Caaguazú, región única | **Real** (límite municipal) |
| `caaguazu-barrios.svg` | Ciudad subdividida en 23 barrios/compañías | Borde **real** + divisiones **aproximadas** (ver nota) |

## ⚠️ Nota sobre `caaguazu-barrios.svg`

OpenStreetMap **no tiene polígonos** de barrios para Caaguazú, solo **puntos** de localidades.
Por eso este archivo se generó así:
- **Borde exterior:** el límite municipal real (exacto).
- **Divisiones internas:** teselación de **Voronoi** sobre los puntos reales de barrios/compañías
  de OSM (cada zona = área más cercana a una localidad). Es una **aproximación** de "área de
  influencia", no un límite barrial oficial. Sirve como base para afinar a mano en el editor de MapSVG.

Las 23 localidades provienen de OSM (7 barrios urbanos: Empalado, Inmaculada Concepción, Las Palmeras,
IPVU, Santa Isabel, Villa Margarita, Tacuru; y compañías rurales como 6 de Enero, 20 de Julio,
Guaviramindy, Yvy Porã, etc.).

---

`caaguazu-distritos.svg` — mapa del departamento de Caaguazú dividido en sus **22 distritos**,
listo para importar en el plugin **MapSVG** de WordPress.

- `viewBox="0 0 1000 709.9"` (proyección equirectangular con corrección de aspecto por latitud).
- Cada distrito es un `<path>` con `id` (slug ASCII, p. ej. `coronel-oviedo`), atributo
  `title` y un hijo `<title>` con el nombre con acentos (tooltip).
- Peso ~39 KB (geometría simplificada con Douglas–Peucker, error < ~1 px a 1000 px de ancho).

## Cómo usarlo en MapSVG

1. En WordPress: **MapSVG → New map → Upload SVG** y subí `caaguazu-distritos.svg`.
2. MapSVG detecta automáticamente las 22 regiones por su `id`/`title`.
3. En **Regions** podés asociar a cada distrito enlaces, popups, colores, datos, etc.
4. Para editar/afinar una frontera, usá el editor de paths de MapSVG sobre el `<path>` correspondiente.

> Los `id` son estables (slug del nombre). Si en MapSVG vinculás datos por `id`, no cambian aunque
> reordenes o re-exportes.

## Regenerar / re-simplificar

`build-caaguazu-svg.py` reconstruye el SVG desde los GeoJSON de geoBoundaries
(ADM1 para recortar el departamento, ADM2 para los distritos). Para una geometría más detallada
o más simplificada, ajustá `eps` en `simplify_ring()` (más bajo = más detalle = más peso).

## Fuente y licencia de los datos

Fronteras derivadas de **geoBoundaries** (open release, gbOpen), basado en datos de la
Dirección General de Estadística, Encuestas y Censos (DGEEC) de Paraguay.

- Licencia: **Creative Commons Attribution 4.0 International (CC BY 4.0)**.
- Atribución sugerida (incluir en el sitio, p. ej. al pie del mapa):
  *"Límites: geoBoundaries (CC BY 4.0) — DGEEC Paraguay."*
- Proyecto geoBoundaries: https://www.geoboundaries.org

El archivo SVG resultante puede usarse y modificarse manteniendo esta atribución.
