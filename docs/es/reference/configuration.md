---
description: "Opciones de config/seo.php agrupadas por capa del resolvedor, con sus valores predeterminados."
---

# Configuración {#configuration}

Publica el archivo de configuración:

```bash
php artisan vendor:publish --tag=seo-config
```

Todo lo siguiente pertenece a `config/seo.php`. Se muestran los valores predeterminados.

## Valores globales del sitio: capa 1 {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

`title_suffix` se añade al título resuelto salvo que ya termine con él.

`title_suffix_skip_when_contains` permite omitir el sufijo si el título ya contiene un término de marca de la lista como **palabra completa**. La comparación ignora mayúsculas y respeta los límites de palabra: `Acmestic` no coincide con `Acme`. Así evita repetir la marca. El valor predeterminado `[]` conserva el comportamiento anterior.

## Política de renderizado de robots {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

El `<head>` omite `<meta name="robots">` cuando la directiva resuelta coincide con `default_robots`. La ausencia de un `index,follow` redundante ya se interpreta como index,follow. Una directiva distinta, como `noindex`, `nofollow` o `max-snippet:-1`, se emite siempre tal cual. Configura `emit_default` en `true` para generar siempre la etiqueta y recuperar el comportamiento anterior a 3.1. La directiva individual `@seoRobots` no cambia: su llamada explícita siempre genera la etiqueta. El [contrato de renderizado](/es/contributing/rendering-contract) detalla las directivas admitidas y su precedencia.

## Protección de indexación fuera de producción {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Si está habilitada y el entorno no aparece en `allowed_environments`, la protección fuerza `noindex,nofollow` por encima de toda la cadena de precedencia, incluso de valores guardados por página. Envía la cabecera `X-Robots-Tag` correspondiente, genera un `robots.txt` que rechaza todo rastreo y muestra un aviso en `seo:audit`. No actúa en entornos permitidos, por defecto `production`.

Se distribuye **desactivada**, sin cambios de salida hasta que la habilites. Actívala con `SEO_INDEXING_GUARD=true` y desactívala con `SEO_INDEXING_GUARD=false`. Cambia la lista mediante `SEO_INDEXING_GUARD_ALLOWED`, con valores separados por comas y comodines de `Str::is()` como `prod*`. Una lista vacía explícita en la configuración protege todos los entornos.

`send_header`, habilitada por defecto dentro de la protección, envía `X-Robots-Tag: noindex,nofollow` en cada respuesta que pasa por la aplicación, incluidos PDF, feeds e imágenes que no contienen meta robots. El middleware solo se registra si la protección está habilitada. Su uso está muy recomendado; consulta la [guía de protección de indexación](/es/guide/indexing-guard).

## URL canónicas {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

El canonical que el resolvedor **deduce** de la URL de petición o de `getUrlForSEO()` elimina por defecto los parámetros de consulta. Los de seguimiento, filtro y orden pueden generar destinos canónicos duplicados para una misma página. Las claves de `query_whitelist` se conservan, en el orden indicado, mientras se eliminan las demás. Un caso habitual es `page` para archivos paginados: `/blog?page=2` representa una página distinta de `/blog`.

Un canonical **establecido explícitamente**, desde el administrador o una capa de mayor precedencia, se emite tal cual, incluidos sus parámetros. La lista solo regula el respaldo deducido. `[]` conserva la eliminación de todos los parámetros.

## Activación de funciones {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` crea una fila `seo_meta` vacía al crear un modelo con `HasSEO`. Los seeders que usan `WithoutModelEvents` omiten este comportamiento.

## Palabras clave objetivo {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

Esta opción habilita las comprobaciones del **flujo de palabras clave objetivo**. Mientras sea `false`, su valor predeterminado, ni [`seo:audit`](/es/guide/audit) ni el scan de Pro señalan las páginas sin palabra clave. Así, una aplicación que no usa esta función no recibe avisos por ello. Actívala al empezar a definir palabras clave, por ejemplo mediante el [campo Filament](/es/guide/filament). La auditoría gratuita, el scan y el editor Pro comenzarán a mostrar `missing_focus_keyword` en las páginas que aún no tengan una; todos leen la misma opción.

## Auditoría gratuita: `seo:audit` {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

Estos son los modelos que audita [`seo:audit`](/es/guide/audit) cuando no se pasa `--model`. Todos deben usar `HasSEO`. Si la lista está vacía, se utilizan los modelos registrados en `sitemap.models`.

## Valores calculados de respaldo: capa 5 {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

La estrategia opcional `best` evalúa una lista ordenada de candidatos: primero `getSEOImage()`, que mantiene la máxima prioridad, después `getSEOImages()`, campos de imagen habituales, primera imagen del contenido y valor predeterminado. Los evalúa por la cercanía de sus dimensiones al tamaño ideal y **descarta los inferiores al mínimo**. Solo mide imágenes **locales**: rutas bajo `public/`, disco público o URL absolutas del mismo host. Nunca descarga una URL remota, que solo sirve de respaldo. Si ningún candidato local alcanza el mínimo, vuelve a la primera coincidencia; así, `best` no devuelve menos de lo que devolvería `first`. Expón candidatos desde el modelo:

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## Sitemaps {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

Consulta las fuentes programáticas en la [guía del registro de sitemaps](/es/guide/sitemaps).

## Datos estructurados: JSON-LD {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

Estos valores alimentan los nodos del [grafo de datos estructurados](/es/guide/schema).

## Rutas {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Establece `enabled => false` si la aplicación sirve su propio `/sitemap.xml` estático.

## Caché {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Caché de resultados del resolvedor {#resolver-result-cache}

`SEOResolver` recorre la cadena completa en cada renderizado público: configuración → valores globales / por tipo de modelo / por ruta → valores calculados → `seo_meta` explícito → sufijo / canonical / datos estructurados. En un sitio con tráfico elevado, como la aplicación de referencia de unas 20.000 peticiones diarias, eso implica varias lecturas de base de datos por página.

Activa `cache.resolver.enabled` para guardar el SEO completamente resuelto de cada modelo. Un **acierto de caché omite toda la cadena**. En el benchmark del paquete, un acierto con caché caliente hace **cero consultas**, mientras cada resolución sin caché relee `seo_meta`. El contenido guardado es un array simple, reconstruido con `SEOData::fromArray()`, nunca un objeto: Laravel 13 usa `cache.serializable_classes = false`, por lo que un objeto almacenado volvería como `__PHP_Incomplete_Class`.

Se utiliza el `store` anterior. En producción, elige una **caché compartida y persistente**, como `redis` o `memcached`, para que todos los procesos web y de cola vean los datos y su invalidación. Déjala desactivada hasta disponer de ella.

La **invalidación es automática** y mantiene el mismo resultado con caché o sin ella. Las entradas se identifican por `(clase de modelo, id, idioma, ruta, URL de petición)` y se eliminan cuando:

- Se **guarda o elimina** la fila `seo_meta` por cualquier vía: `saveSEO()`, Filament o escritura directa de `SEOMeta`.
- Cambia un **campo de contenido** del modelo incluido en `getSEOContentFields()`. Por defecto incluye todos los campos del respaldo calculado integrado: título/encabezado, excerpt/summary/content/body/text/article y campos de imagen habituales como `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` y `hero_image`. Amplíalo si tu modelo calcula SEO a partir de otras columnas.
- Cambia **cualquier fila de `seo_defaults`**. Como puede alimentar cualquier modelo, se vacía toda la caché de resolución.

En almacenes **con etiquetas**, `redis`, `memcached` y `array`, las entradas del modelo se eliminan mediante tags. En almacenes **sin etiquetas**, `file` y `database`, se usa una marca de versión por modelo. Ambos mecanismos funcionan sin recorrer claves.

::: tip
Solo se cachean resoluciones de modelos. `SEO::render()`/`@seo()` con un `SEOData` manual y `@seoForRoute()` para una ruta sin modelo siguen resolviéndose en cada llamada.
:::

::: warning
La caché refleja `updated_at` y el `modified_time` calculado desde el último cambio de un campo de contenido o hasta que vence el TTL. Un `touch()` que solo cambia `updated_at`, sin modificar ninguna columna de `getSEOContentFields()`, no fuerza una nueva resolución; `article:modified_time` puede retrasarse hasta el TTL. Añade las columnas calculadas específicas de tu aplicación a `getSEOContentFields()` si necesitas invalidación inmediata.
:::
