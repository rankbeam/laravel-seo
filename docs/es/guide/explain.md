---
description: "seo:explain muestra qué capa asignó cada campo SEO y qué valores sustituyó. Es de solo lectura y no necesita red ni licencia."
---

# Explicar la resolución (`seo:explain`) {#explain-the-resolution-seo-explain}

Rankbeam resuelve el SEO de una página mediante una [cadena de precedencia por capas](/es/concepts/resolver-precedence): configuración, valores predeterminados de la base de datos —globales, por tipo de modelo y por ruta—, valores calculados del modelo y valores explícitos de `seo_meta`. Después aplica el posprocesamiento —sufijo del título, canonical y conversión de imágenes a URL absolutas— y la [protección de indexación](/es/guide/indexing-guard). Si `<title>` o robots no contienen lo que esperabas, **`seo:explain` muestra qué capa asignó cada campo y qué valores sustituyó**.

Es de solo lectura, no necesita red ni licencia y no vuelve a implementar la combinación. La atribución procede de las aportaciones de cada capa del propio resolvedor, y los valores finales vienen del resolvedor real, para que la explicación corresponda al resultado renderizado.

## Uso {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

El modelo debe utilizar el trait [`HasSEO`](/es/guide/quickstart).

## Interpretar la salida {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by**: la capa ganadora, es decir, la de mayor precedencia que estableció un valor no nulo. Muestra `post-processing` si ninguna capa lo asignó y el valor se dedujo, como un canonical a partir de la URL de petición/modelo, un og:url a partir del canonical o una imagen convertida a URL absoluta.
- **Overrode**: todas las capas de menor precedencia que propusieron un valor descartado, en orden, para ver qué quedó sustituido.
- **↳ notes**: el posprocesamiento que cambió el valor después de combinar las capas: sufijo del título, eliminación de parámetros del canonical, derivación de og:url, URL absolutas de imágenes y protección de indexación que fuerza `noindex` por encima de todas las capas.

::: tip og:type y twitter:card
Ambos tienen valores predeterminados no nulos del framework (`website` / `summary_large_image`), así que la capa más alta que los establece —normalmente `computed`— gana a `config`. Una página sin fila guardada en `seo_meta` no aporta valores para ellos, por lo que un `og:type` calculado como `article` nunca queda sustituido por un simple `website`. Así funciona la combinación real.
:::

## Resolución a nivel de sitio {#site-level-resolution}

Según la [ampliación del registro de configuración del sitio](/es/concepts/resolver-precedence), `seo:explain` también informa del origen de los valores globales que suelen generar dudas: **qué fuente estableció el host canónico, el nombre del sitio y el idioma predeterminado**.

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Conviene revisar especialmente el host canónico. Un host incorrecto —`localhost` publicado por error, `http://` en un sitio `https`, o una URL de aplicación distinta de la del modelo— suele causar errores en las referencias canónicas a la propia página.

## Salida JSON {#json-output}

`--json` emite la traza completa para herramientas o CI: `target`, los campos `winner` / `losers` / `final` / `notes` de cada propiedad y el registro `site_level`.

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## Véase también {#see-also}

- [Precedencia del resolvedor](/es/concepts/resolver-precedence): la cadena completa que recorre `seo:explain`.
- [Auditoría SEO gratuita](/es/guide/audit): `seo:audit` detecta qué falla; `seo:explain` explica por qué un valor tiene ese resultado.
