---
description: "Genera el SEO de Laravel en el servidor con las directivas Blade de Rankbeam: @seo resuelve un modelo y emite metadatos, Open Graph, Twitter Cards y JSON-LD."
---

# Guía de Blade {#blade-guide}

El paquete incluye siete directivas Blade para aplicaciones que generan el HTML en el servidor. Normalmente basta con una: `@seo`.

## La directiva que reúne todas las etiquetas {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` resuelve el modelo mediante la [cadena de precedencia](/es/concepts/resolver-precedence) y genera el bloque completo del head: `<title>`, meta description, enlace canonical, robots, etiquetas Open Graph y Twitter Card, y el JSON-LD asociado. La etiqueta robots se emite **solo si difiere del valor predeterminado del sitio**: se omite un `index,follow` redundante, pues su ausencia ya significa index,follow. Activa `seo.robots.emit_default` para generarla siempre. Consulta el [contrato de renderizado (EN)](/es/contributing/rendering-contract) completo.

Firmas:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` acepta un `Model`, un `SEOData` construido manualmente o `null`. Los argumentos de ruta e idioma solo se aplican a `Model`/`null`; un `SEOData` manual lleva sus propios valores.

## Páginas de ruta sin modelo {#route-pages-no-model}

Para páginas estáticas, archivos y otras páginas asociadas a una ruta:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Los valores de ruta proceden de las filas de `seo_defaults` asociadas al nombre de esa ruta.

## Páginas sin modelo: construir `SEOData` manualmente {#model-less-pages-hand-built-seodata}

Los listados, resultados de búsqueda y contenidos compuestos en un controlador a menudo no corresponden a un único modelo. Construye un `SEOData` y pásalo directamente a `@seo` o a la fachada `SEO`, sin recurrir a `app(TagRenderer::class)->render(...)`:

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

Un `SEOData` manual se trata como una **intención explícita**. Se conserva cada valor que establezcas; solo se completan estos campos al renderizar:

- `canonical` / `og:url` se deducen de la URL actual si faltan; un `canonical` explícito se conserva tal cual, incluidos sus parámetros de consulta.
- `title_suffix` solo se añade si el título no lo contiene. Se omite por completo si el título ya incluye un término de marca; consulta [`title_suffix_skip_when_contains` (EN)](/es/reference/configuration).
- Las rutas relativas de `og:image` / `twitter:image` se convierten en absolutas con `url()`, que respeta el esquema actual y **no** fuerza HTTPS.
- `og:site_name` y `locale` se completan con la configuración y el idioma de la aplicación.

La cadena de precedencia de la base de datos —valores globales, por tipo de modelo, por ruta y de `seo_meta`— **no** se combina con un `SEOData` manual. Se renderiza lo que pases, con los campos anteriores completados.

El mismo objeto funciona mediante la fachada:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Un patrón de layout reutilizable {#a-layout-pattern-that-scales}

Un único layout para páginas de modelo, páginas de ruta y el resto:

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

Los controladores pasan `'seoModel' => $post` o `'seoRoute' => 'blog.index'` sin tocar el marcado.

## Directivas individuales {#granular-directives}

Cuando necesites controlar etiquetas concretas, por ejemplo al combinar la salida de otro paquete:

| Directiva | Genera |
|---|---|
| `@seoTitle($post)` | Solo `<title>` |
| `@seoMeta($post)` | Solo meta description |
| `@seoCanonical($post)` | Solo el enlace canonical; usa la URL actual si falta |
| `@seoRobots($post)` | Solo meta robots, siempre: esta llamada explícita **no** aplica la omisión del valor predeterminado que aplica `@seo` |
| `@seoSchema($post)` | Solo el `<script>` JSON-LD, válido en head o body |

Todas aceptan la misma expresión `($model, $route, $locale)` que `@seo`, o ningún argumento para la página actual.

## Alternativas hreflang {#hreflang-alternates}

Los modelos que usan `HasSEO` pueden proporcionar enlaces hreflang directamente al resolvedor:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Usa URL absolutas. `@seo($post)` resuelve estas entradas y genera cada una como `<link rel="alternate" hreflang="..." href="...">`. Primero normaliza los códigos a BCP 47 (`it_IT` → `it-IT`). Las políticas de `seo.hreflang` pueden añadir una referencia a la propia página y un `x-default`; la auditoría gratuita detecta entradas inválidas, duplicadas o sin autorreferencia. Consulta [Contenido multilingüe](/es/guide/multilingual#hreflang).

## Escape y seguridad {#escaping-and-safety}

Los valores de texto se escapan con `e()`. JSON-LD se codifica con `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`, de modo que un `</script>` en contenido de usuario no pueda cerrar el elemento script.
