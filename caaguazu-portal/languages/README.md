# Traducciones — caaguazu-portal

El idioma **fuente** del plugin es **español** (las cadenas `__()` ya están en ES), así que
"ES" funciona sin archivos de traducción. El selector de idioma del panel (ES/EN/GN) cambia el
locale del front-end; para que las cadenas del plugin se traduzcan a **EN** y **GN** hay que
generar y colocar acá los archivos de traducción.

## Generar la plantilla (.pot)

Con WP-CLI:

```bash
wp i18n make-pot caaguazu-portal caaguazu-portal/languages/caaguazu-portal.pot \
  --exclude=vendor
```

## Crear las traducciones

1. Copiá el `.pot` a `caaguazu-portal-en_US.po` y `caaguazu-portal-gn.po`.
2. Traducí las cadenas (Poedit, GlotPress, etc.).
3. Compilá a `.mo` (Poedit lo hace al guardar, o `msgfmt archivo.po -o archivo.mo`).
4. Dejá los `.mo` en esta carpeta. El plugin los carga vía `load_plugin_textdomain()`.

Archivos esperados:

```
caaguazu-portal/languages/
  caaguazu-portal.pot
  caaguazu-portal-en_US.po / .mo
  caaguazu-portal-gn.po    / .mo
```

> Para traducir también el **contenido** (las fichas de destino) por idioma, se necesita un
> plugin multilingüe como Polylang o WPML; eso queda fuera del alcance de este plugin.
