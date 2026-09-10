---
description: "Migra desde otros paquetes SEO Laravel a HasSEO y saveSEO(), con importación de datos de ralphjsmit/laravel-seo."
---

# Migrar desde otros paquetes SEO de Laravel

Esta guía relaciona las API y formas de almacenamiento habituales con las dos piezas de Rankbeam: el trait [`HasSEO`](/es/guide/quickstart) y `saveSEO()`. Para el paquete que guarda SEO por modelo hay un comando de importación. El esfuerzo concreto depende de tus personalizaciones.

::: tip ¿Vienes de WordPress?
La guía [Migrar desde WordPress (EN)](/guide/migrate-from-wordpress) describe el importador CSV y los lectores de bases de datos para Yoast y Rank Math.
:::

| Paquete de origen | Almacenamiento | Migración |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | Tabla polimórfica `seo` | **`php artisan seo:import-from ralphjsmit`** y cambio de trait |
| [`artesaos/seotools`](#from-artesaos-seotools) | Ninguno en base de datos; ejecución y configuración | Sustituir llamadas por `saveSEO()` o getters calculados |
| [`spatie/*`](#from-spatie-packages) | Builders de esquemas y sitemaps, sin tabla de metadatos | Conservar las funciones complementarias y migrar las demás |

Solo **ralphjsmit** tiene aquí datos SEO en una tabla para importar en lote. Los otros generadores construyen etiquetas durante la petición: reemplaza sus llamadas por valores guardados en `seo_meta` o calculados.

## Desde `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

Guarda una fila polimórfica por modelo en `seo`, con una estructura parecida a `seo_meta`. Esto permite una importación idempotente.

### 1. Instalar Rankbeam junto al paquete actual {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Ambos pueden coexistir durante la migración porque usan tablas (`seo` y `seo_meta`) y espacios de nombres distintos.

::: warning Una clave de configuración compartida
Un `config/seo.php` antiguo de ralphjsmit oculta la configuración de Rankbeam, ya que ambos usan la clave `seo`. Haz una copia, retíralo y publica el archivo de Rankbeam con `php artisan vendor:publish --tag=seo-config`.
:::

### 2. Ejecutar el importador {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

El importador lee `seo`, resuelve cada fila al modelo Eloquent real y escribe sus datos en `seo_meta`.

| Opción | Efecto |
|---|---|
| `--dry-run` | Mostrar la importación prevista sin escribir. |
| `--model="App\Models\Post"` | Limitar a una o varias clases; repetible. |
| `--locale=fr` | Idioma de destino; por defecto, el de la aplicación. |
| `--table=legacy_seo` | Leer una tabla de origen con otro nombre. |
| `--connection=legacy` | Leer mediante otra conexión de base de datos. |
| `--limit=100` | Importar como máximo N filas para avanzar por etapas. |
| `--overwrite` | Reemplazar valores no vacíos; por defecto, solo completar campos vacíos. |
| `--json` | Informe legible por máquina. |
| `--force` | Omitir la confirmación en scripts o CI. |

Repetir el comando actualiza las mismas filas sin duplicarlas. Sin `--overwrite`, conserva los datos que ya hayas definido en Rankbeam. Activa esa opción solo si quieres reemplazarlos por los importados.

### 3. Cambiar el trait de los modelos {#_3-swap-the-trait-on-your-models}

Sustituye el trait de ralphjsmit por el de Rankbeam. Algunos nombres de métodos cambian; la tabla leída pasa a ser `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Traslada la lógica de `getDynamicSEOData()` a `getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()` y `getSEOAlternates()`. Consulta el [inicio rápido](/es/guide/quickstart). Los valores explícitos se guardan con `saveSEO()`:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Correspondencia de campos {#field-mapping}

Los campos se asignan de forma explícita, sin copiar columnas ausentes del esquema Core 3.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Notas |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | Se calculan desde el modelo actual, no se copian literalmente. |
| `title` | `title` | Límite de columna de 70 caracteres; se informa de los recortes. |
| `description` | `description` | Límite de 160 caracteres; se informa de los recortes. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Límite de 50 caracteres. |
| `image` | `og_image` | `twitter:image` lo hereda mediante el resolvedor. |
| `author` | No importado | Core 3 no tiene columna de autor en `seo_meta`; la autoría del artículo pertenece a los datos resueltos. Las filas afectadas se cuentan y notifican para que elijas un almacenamiento o cálculo adecuado. |
| `id`, `created_at`, `updated_at` | No importados | Campos estructurales del origen. |

**Por qué se recalcula el tipo polimórfico:** cada fila se resuelve al modelo real y usa su `getMorphClass()` para obtener las claves `seoable`. Así respeta el [morph map actual](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types), aunque el paquete anterior guardara otra convención. Los modelos eliminados se notifican como omitidos y no crean relaciones huérfanas.

### Entender el informe {#what-the-report-tells-you}

Sin `--json`, se muestra una tabla y tres secciones de revisión:

- **Truncated:** valores acortados para caber en `seo_meta`.
- **Not imported:** columnas con datos, como `author`, sin destino en Core 3.
- **Skipped rows by reason:** filas vacías, modelos eliminados o tipos que no se pueden resolver.

### Verificar {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Una vez revisados los datos, puedes retirar `ralphjsmit/laravel-seo` y eliminar la antigua tabla `seo`.

## Desde `artesaos/seotools` {#from-artesaos-seotools}

Este paquete genera etiquetas **en ejecución** mediante `SEOMeta`, `OpenGraph`, `TwitterCard` y `JsonLd`, normalmente desde un controlador, con valores iniciales en `config/seotools.php`. No hay una tabla por modelo que importar: mueve esas llamadas a valores guardados o calculados.

| Llamada artesaos/seotools | Equivalente Rankbeam |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` o `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` o `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` o `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | No hay una etiqueta keywords equivalente: las palabras clave objetivo sirven para controles editoriales internos. `saveSEO(['focus_keywords' => [...]])`, consulta [auditoría](/es/guide/audit) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [Grafo JSON-LD (EN)](/guide/schema) |
| Valores de `config/seotools.php` | `config/seo.php` y [prioridad del resolvedor](/es/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` en el layout | `@seo($model)`, consulta [Blade (EN)](/guide/blade) |

En lugar de fijar etiquetas en cada controlador, guarda los datos una vez por modelo en `seo_meta` y deja que el resolvedor los emita. Los valores globales pasan a la [configuración (EN)](/reference/configuration). Las páginas estáticas por ruta usan `@seoForRoute()`.

## Desde paquetes Spatie {#from-spatie-packages}

No existe un paquete de almacenamiento SEO `spatie/laravel-seo`. Los paquetes Spatie habituales son builders complementarios que puedes conservar o sustituir por separado:

- **`spatie/schema-org`:** builder fluent de JSON-LD. El [grafo Rankbeam (EN)](/guide/schema) ofrece builders tipados `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` y `Organization`, guardados en `seo_meta.schema_jsonld` y emitidos sin duplicados. Pasa la salida `->toArray()` de objetos existentes a `saveSEO(['schema_jsonld' => $array])` o reescríbelos con los builders de Rankbeam.
- **`spatie/laravel-sitemap`:** el [registro de Rankbeam](/es/guide/sitemaps) se basa en él. Registra los modelos como fuentes de un sitemap combinado o conserva tu sitemap actual y desactiva la ruta Rankbeam.

Para [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO), otro generador en ejecución, usa el mismo patrón que con artesaos: reemplaza `setTitle` y `addMeta` por `saveSEO()` o getters calculados.

## Ampliar el importador {#extending-the-importer}

`seo:import-from` utiliza un registro de implementaciones de `Rankbeam\Seo\Importing\Contracts\Importer`. Añadir una fuente no exige modificar el comando. Incluye `ralphjsmit` y los [importadores WordPress (EN)](/guide/migrate-from-wordpress) `wordpress-csv`, `yoast` y `rank-math`. Registra uno propio en un proveedor de servicios:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```
