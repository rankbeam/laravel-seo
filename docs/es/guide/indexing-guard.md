---
description: "Vincula las directivas de indexación al entorno de Laravel: fuera de los entornos permitidos, fuerza noindex y genera reglas que piden a los rastreadores no acceder."
---

# Protección de indexación fuera de producción {#indexing-guard-non-production-safety-net}

Que una copia de staging o local aparezca en Google puede causar contenido duplicado que compite con las páginas reales, un entorno privado en el índice y semanas de limpieza con la herramienta de retirada de URL. Una causa habitual es un `noindex` que depende de un `.env` olvidado o una regla robots sobrescrita durante el despliegue.

La **protección de indexación** reduce ese riesgo vinculando la indexabilidad al *entorno* de Laravel, en lugar de a una opción que alguien debe recordar. Fuera de la lista de entornos permitidos, fuerza `noindex,nofollow` en cada página, el `robots.txt` gestionado indica a todos los rastreadores que no accedan y `seo:audit` lo señala claramente.

Es una función gratuita del núcleo.

## Qué hace cuando está activa {#what-it-does-when-active}

Si `app()->environment()` **no** está en `seo.indexing_guard.allowed_environments` y la protección está habilitada, ocurren cuatro cosas:

1. **El resolvedor fuerza `noindex,nofollow` en cada página.** Se aplica por encima de toda la [cadena de precedencia](/es/concepts/resolver-precedence), incluso de un valor robots explícito guardado en `seo_meta`.
2. Se añade una **cabecera HTTP `X-Robots-Tag: noindex,nofollow`** a todas las respuestas que pasan por la aplicación. Consulta [Respuestas que no son HTML](#non-html-responses-pdfs-feeds-images).
3. **`SEO::robotsTxt()->build()` genera un `robots.txt` y un `ai.txt` que rechazan todo rastreo**, mediante `User-agent: *` / `Disallow: /`. Se aplica tanto al comando `seo:robots-txt` como a la [ruta dinámica](/es/guide/ai-crawlers) opcional.
4. **`seo:audit` muestra un aviso destacado** para dejar claro que todo está marcado como noindex al leer el informe.

En los entornos permitidos, por defecto `production`, la protección es completamente **inactiva**: la salida no cambia y el renderizado es idéntico byte a byte.

## Respuestas que no son HTML: PDF, feeds e imágenes {#non-html-responses-pdfs-feeds-images}

La **metaetiqueta** robots solo llega a rastreadores que interpretan HTML. Un PDF, un feed RSS/Atom, una imagen u otra respuesta no HTML carece de `<head>`. Por eso, mientras está activa, la protección también envía la misma directiva en una cabecera HTTP mediante un middleware global:

```http
X-Robots-Tag: noindex,nofollow
```

La cabecera y la metaetiqueta proceden de la misma fuente para que coincidan. La cabecera está **habilitada por defecto dentro de la protección**; la protección en sí requiere activación y no actúa en los entornos permitidos. Puedes desactivar la cabecera para mantener solo la metaetiqueta:

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

El middleware se registra **solo si la protección está habilitada**. Si está desactivada, el paquete no añade ese middleware.

::: warning Los archivos estáticos no pasan por PHP
Un archivo que el servidor web devuelve directamente desde `public/` nunca entra en Laravel y no puede recibir esta cabecera. Protégelo en el servidor web o CDN. La protección cubre las respuestas que pasan por la aplicación.
:::

## Por qué sustituye un valor robots explícito {#why-it-overrides-an-explicit-robots-value}

En el resto de Rankbeam, gana el valor guardado explícitamente: esa es la finalidad de la cadena de precedencia. Esta protección es una excepción deliberada y se sitúa por encima de esa capa, por la naturaleza del riesgo:

- La base de datos de staging suele ser una copia de producción. Una página con `index,follow` llevaría esa directiva a staging y pediría ser indexada.
- **Indexar staging por error causa problemas; marcarlo como `noindex` coincide con lo deseado.** Por eso, el valor guardado no puede superar la protección en los entornos que nunca deben indexarse.

## Activarla {#enabling-it}

La protección se distribuye **desactivada**. Instalar o actualizar el paquete no cambia la salida fuera de producción sin que la actives, siguiendo la misma política de conservar los bytes que [`blank_is_unset`](/es/concepts/resolver-precedence) y las imágenes OG generadas. Actívala con una línea:

```dotenv
SEO_INDEXING_GUARD=true
```

Con la lista predeterminada, `production` no cambia, así que puedes mantenerla habilitada en la configuración compartida. Comprueba que la lista incluya todos los entornos que quieras indexar. Su uso está **muy recomendado** y se contempla activarla por defecto en Core 4.

Desactívala con la misma opción:

```dotenv
SEO_INDEXING_GUARD=false
```

## Elegir los entornos que pueden indexarse {#choosing-which-environments-may-index}

Por defecto solo se permite `production`. Puedes cambiar la lista mediante una variable de entorno con valores separados por comas:

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

O en `config/seo.php`:

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

Las entradas se comparan mediante `Str::is()`, por lo que admiten **comodines**: `'prod*'` coincide con `production` y `prod-eu`.

```php
'allowed_environments' => ['prod*'],
```

Una lista **vacía** significa que ningún entorno puede indexarse: la protección actúa en todos. Sin embargo, un valor vacío o en blanco de `SEO_INDEXING_GUARD_ALLOWED` usa `['production']` como respaldo, para que un error al escribir la variable no retire producción del índice silenciosamente. Escribe `[]` de forma explícita en la configuración si quieres aplicarla en todos los entornos.

## Verificarla {#verifying-it}

`seo:audit` muestra el aviso y, con `--json`, incluye el estado en un formato procesable:

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

El `robots.txt` servido o generado en un entorno protegido:

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Alcance {#scope}

La protección controla **directivas de indexación**: meta robots, la cabecera `X-Robots-Tag` y `robots.txt`. No cambia títulos, descripciones, canonical ni datos estructurados. Es independiente de la [política de renderizado de robots](/es/concepts/resolver-precedence), `seo.robots.emit_default`: como `noindex,nofollow` difiere del valor predeterminado del sitio, la etiqueta siempre se genera.
