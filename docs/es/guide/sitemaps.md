---
description: "Registra fuentes de sitemap con modelos, funciones o listas de URL y genera un índice XML disponible en /sitemap.xml."
---

# Registro de sitemaps

El paquete genera un XML por fuente y un índice, disponibles en `/sitemap.xml` y `/sitemap-{name}.xml`. La generación utiliza [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

## Registrar fuentes {#registering-sources}

Registra fuentes con nombre en `boot()` de un proveedor de servicios:

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

Cada fuente genera `sitemap-{name}.xml`; `sitemap.xml` enumera los archivos. El registro también ofrece `has($name)`, `names()`, `forget($name)` y `flush()`.

## Fuentes desde la configuración {#config-driven-sources}

Puedes definir modelos y URL estáticas en `config/seo.php`:

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info El registro tiene prioridad
El descubrimiento automático omite los modelos cubiertos por una fuente registrada. Registrar `posts` no produce además un `sitemap-post.xml`.
:::

## Generar {#generating}

```bash
php artisan seo:sitemap
```

Los archivos se guardan en `seo.sitemap.disk`, por defecto `public`. Programa la ejecución para mantenerlos actualizados:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Los sitemaps que superan `seo.sitemap.max_urls_per_sitemap`, por defecto 50 000 según el límite XML, se dividen automáticamente.

## Servir los archivos {#serving}

Las rutas del paquete sirven los archivos generados con cabeceras XML, de caché y `X-Robots-Tag: noindex`:

- `/sitemap.xml`: índice o sitemap único.
- `/sitemap-posts.xml`: fuente con nombre.

Si sirves tus propios archivos estáticos, desactiva las rutas:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Vista legible en el navegador {#styled-sitemap-in-the-browser}

Rankbeam añade una hoja XSL para mostrar el XML como una tabla con URL, `lastmod`, frecuencia, prioridad, número de imágenes y alternativas lingüísticas, además de notas de validación.

![Sitemap de Rankbeam presentado como una tabla legible con la identidad visual del producto](/sitemap-styled.png)

Cada sitemap incluye una referencia a la hoja:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

La instrucción cambia la presentación para las personas; el documento sigue siendo un sitemap XML para los buscadores. El índice y los archivos secundarios usan el mismo estilo.

Está **activada por defecto**. Solo añade una instrucción, sin datos ni trabajo por registro, a diferencia de las extensiones de imágenes e idiomas.

::: warning Requiere spatie/laravel-sitemap 8.1 o superior
La instrucción usa `setStylesheet()`, incorporado en 8.1. Si tu aplicación resuelve una versión anterior, se genera XML sin estilo y la generación sigue funcionando. `composer update spatie/laravel-sitemap` permite actualizar si las restricciones de tu aplicación lo admiten.
:::

Para desactivarla:

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### Notas de validación {#validation-notes}

La vista señala dos casos que puede comprobar sin salir del navegador:

- **Falta de `lastmod`:** se muestra la ausencia, sin inventar fechas de actualización.
- **URL no absolutas:** un `<loc>` que no contiene una URL `http(s)` absoluta.

### Alojar tu propia hoja de estilo {#self-hosting-the-stylesheet}

Por defecto, el paquete sirve `/sitemap.xsl`. El navegador exige que la hoja tenga el **mismo origen** que el sitemap. Si los archivos están en un CDN, publica allí la hoja y configura su URL:

```bash
php artisan vendor:publish --tag=seo-assets
```

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => [
        'url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
    ],
],
```

::: info Conservar el escape de salida
Todos los valores, incluidas las URL, pasan por el escape XSLT. Un `<loc>` solo se convierte en enlace si usa `http(s)`, por lo que no puede inyectar marcado o un enlace `javascript:` en esta vista. Si personalizas el `.xsl`, conserva este comportamiento y no añadas `disable-output-escaping`.
:::

## Qué se incluye {#what-gets-included}

Las fuentes de modelos incluyen registros resueltos como indexables. Si sus directivas robots contienen `noindex`, quedan fuera. Las URL proceden de `getUrlForSEO()`, también utilizado para el canonical.

## Extensiones de imágenes y hreflang {#image-hreflang-extensions}

Dos extensiones opcionales añaden los datos que el paquete ya resuelve para cada modelo. Ambas están **desactivadas por defecto**:

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

Se aplican a modelos con `HasSEO`, usando su `seoData()` completo:

- **`images`** añade una entrada de [sitemap de imágenes de Google](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps) con la misma imagen que `og:image`. Si el registro no tiene imagen propia, se usa `default_og_image`. Actívala solo si las imágenes por URL tienen sentido para tu contenido.
- **`alternates`** añade `<xhtml:link rel="alternate" hreflang="…">` desde `getSEOAlternates()`, como en el `<head>`. Devuelve URL absolutas:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning Referencias recíprocas y a la propia página
Cada versión debe enumerar **su propia URL y las demás**, con enlaces recíprocos. `getSEOAlternates()` debe devolver el conjunto completo en todas las variantes. Usa códigos `language[-Script][-REGION]` válidos o `x-default` y URL `http(s)` absolutas. Las entradas sin `hreflang` o `href` no vacíos se omiten.

Antes de escribir la lista se aplican las [políticas `seo.hreflang`](/es/guide/multilingual#hreflang): normalización (`it_IT` → `it-IT`) y adición opcional de la propia página y de `x-default`. La lista coincide con la del `<head>`. La auditoría gratuita informa de `hreflang_invalid_code`, `hreflang_duplicate_code` y `hreflang_missing_self`; la reciprocidad requiere el rastreo de Pro.
:::

::: info Coste en catálogos grandes
Cada extensión activa obliga a resolver el `seoData()` completo por URL: valores predeterminados, calculados y getters `getSEO*()`. Esto puede provocar varias operaciones de caché o base de datos, además de las consultas de tus getters. Está pensado para el comando programado `seo:sitemap`. Mide el coste antes de activarlo cerca de 50 000 URL y deja ambas opciones desactivadas si no necesitas esos datos.
:::

::: tip Configuración ya publicada
`config/seo.php` se combina sin fusión recursiva. Una configuración publicada antes de estas extensiones no recibe automáticamente `sitemap.images` y `sitemap.alternates`. Las variables `SEO_SITEMAP_IMAGES` y `SEO_SITEMAP_ALTERNATES` no bastan por sí solas: añade las claves al array publicado o vuelve a publicar la configuración.
:::

## Control completo con etiquetas Spatie {#full-control-hand-built-spatie-tags}

Para pies de imagen, vídeo, noticias o alternativas hreflang personalizadas, devuelve un [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images) construido manualmente desde una fuente. El generador lo conserva sin añadir sus propias extensiones:

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

Lo mismo se aplica por registro: si un modelo implementa `Sitemapable` y `toSitemapTag()` devuelve un `Url`, se emite tal como lo devuelve.
