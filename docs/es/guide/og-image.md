---
description: "Genera imágenes Open Graph por página con Blade, Browsershot y Chrome: generación previa, caché, plantillas, fuentes y límites de operación."
---

# Generar imágenes OG {#generated-og-images}

Desde Core 3.20, Chrome desactiva JavaScript y bloquea peticiones de recursos HTTP(S), FTP y WebSocket. Las plantillas propias deben usar HTML/CSS estático y recursos integrados, como las incluidas.

Sin imagen propia, las páginas comparten `default_og_image`. Esta función genera **una tarjeta Open Graph / Twitter de 1200×630 píxeles por página**, a partir de Blade y un navegador headless mediante [spatie/browsershot](https://github.com/spatie/browsershot). El navegador gestiona líneas, acentos, fuentes de respaldo y títulos largos. Las escrituras no latinas requieren fuentes adecuadas en el host.

Es gratuita en el núcleo y está **desactivada por defecto**. Desactivada, mantiene `default_og_image` y no exige la dependencia opcional del navegador.

::: info Generación previa estática
Un comando Artisan genera las tarjetas antes de las visitas. Una página solo enlaza archivos que ya existen; la petición del visitante no inicia un navegador. No hay endpoint de renderizado bajo demanda; consulta las [limitaciones](#caveats).
:::

## Requisitos {#requirements}

Instala el controlador opcional en tu aplicación:

```bash
composer require spatie/browsershot
```

También necesitas:

- **Node.js** en el host.
- **Puppeteer** en la **raíz de la aplicación**, para que Node encuentre el módulo:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium**. Puppeteer descarga un navegador por defecto. En producción puedes indicar un Chrome instalado con [`chrome_path`](#configuration).

::: warning Puppeteer en la raíz bajo Windows
`npm_module_path` llama a `setNodeModulePath()` de Browsershot, que usa el prefijo POSIX `NODE_PATH=…`, sin efecto en Windows. Allí Node resuelve módulos recorriendo directorios superiores desde la aplicación. Instala Puppeteer en su raíz; consulta [limitaciones](#caveats).
:::

## Activar {#enabling}

Publica la configuración si hace falta con `php artisan vendor:publish --tag=seo-config` y activa la función:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Después genera las tarjetas. No se renderiza nada hasta este paso:

```bash
php artisan seo:og-images
```

## Cómo se resuelve la imagen {#how-resolution-works}

La tarjeta generada no reemplaza una imagen elegida para la página. El resolvedor completa `og:image` solo si está vacío o aún coincide con `default_og_image`. Las imágenes explícitas de `getSEOImage()`, una fila `seo_meta` o un campo de contenido tienen prioridad.

La consulta calcula la ruta y devuelve la URL pública **solo si el archivo ya existe** en el disco configurado. No genera imágenes:

- Una petición web nunca inicia el navegador; sin tarjeta conserva la imagen estática.
- La página no enlaza una tarjeta pendiente de generación.

Tras cambiar contenido, ejecuta [`seo:og-images`](#the-seo-og-images-command) al desplegar o según un horario para producir el nuevo archivo.

## El comando `seo:og-images` {#the-seo-og-images-command}

Genera las tarjetas que consultará el resolvedor:

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*`: una o varias clases, opción repetible. Sin argumento usa `seo.og_image.models` y, como respaldo, los [modelos del sitemap](/es/guide/sitemaps) en `seo.sitemap.models`, al igual que `seo:llms-txt`.
- `--force`: vuelve a generar tarjetas existentes, por ejemplo al editar una plantilla sin cambiar `cache_version`.
- `--prune`: después de generar, elimina tarjetas huérfanas en la ruta configurada. Solo borra nombres con formato de hash generado, no otros recursos. Se ignora en ejecuciones limitadas con `--model`, porque la lista de archivos a conservar no cubriría los demás modelos.

Los modelos deben usar `HasSEO`. Se omiten registros sin título. El informe cuenta `generated`, `skipped`, `failed` y, con `--prune`, `pruned`.

### Programación {#scheduling}

Actualiza las tarjetas y retira las que quedan huérfanas tras modificar títulos:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Invalidación de caché {#the-invalidation-model}

El nombre del archivo es un hash de los datos que afectan al resultado: título, nombre del sitio, nombre de plantilla, controlador, dimensiones, colores del degradado, `cache_version` y versión instalada del paquete.

- **Cambiar el título produce otro hash y archivo.** La tarjeta antigua queda huérfana. La página vuelve al respaldo estático hasta generar la nueva; `--prune` puede borrar la anterior.
- **Cambiar `cache_version` o actualizar el paquete renueva los hashes.** Incrementa `cache_version` tras editar el contenido de una plantilla para invalidar todas las tarjetas. La versión del paquete se incorpora automáticamente para no conservar diseños antiguos después de una actualización.

## Plantillas incluidas {#bundled-templates}

Las tres plantillas comparten degradado y tamaño inicial de 1200×630:

| Plantilla | Uso | Contenido |
|---|---|---|
| `seo::og.default` | General | Título y nombre del sitio |
| `seo::og.article` | Publicaciones y noticias | Sección, título, autor y fecha |
| `seo::og.product` | Productos y anuncios | Marca, categoría, título y descripción |

Elige una plantilla global con `seo.og_image.template` o asígnalas por clase de modelo:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Un modelo también puede definir `getOgImageTemplate(): ?string`. Devuelve un nombre de vista o `null` para usar la asignación o valor predeterminado. Prioridad: hook del modelo, mapa `templates`, `template` global.

## Personalizar la plantilla {#customizing-the-template}

La tarjeta es una vista Blade, por defecto `seo::og.default`, que produce HTML autónomo. La fuente incluida se integra como URI de datos y no requiere descargar recursos.

**Publicar y editar la vista incluida:**

```bash
php artisan vendor:publish --tag=seo-views
```

Edita `resources/views/vendor/seo/og/default.blade.php`.

**O seleccionar una vista propia:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

Variables disponibles:

| Variable | Tipo | Significado |
|---|---|---|
| `$title` | `string` | Título OG o, si falta, título de página. |
| `$siteName` | `?string` | `og:site_name` resuelto. |
| `$fontDataUri` | `string` | Fuente negrita incluida como URI `data:`; vacía si no está disponible, con respaldo sans-serif del navegador. |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Ancho, por defecto `1200`. |
| `$height` | `int` | Alto, por defecto `630`. |
| `$locale` | `?string` | Idioma resuelto para `<html lang>`. |
| `$author` | `?string` | Autor para `seo::og.article`. |
| `$publishedDate` | `?string` | Fecha de publicación para `seo::og.article`: formato medio de ICU en la configuración regional de la página, si está disponible; de lo contrario, Carbon traduce el mes con el orden `M j, Y`. Null si no hay fecha. |
| `$section` | `?string` | Sección o categoría de artículo/producto. |
| `$description` | `?string` | Descripción OG o de página para `seo::og.product`. |

::: info El nombre de plantilla forma parte de la caché
Cambiar el nombre de plantilla o los colores invalida las tarjetas. Editar una vista bajo el mismo nombre no lo hace: incrementa `cache_version` o usa `--force`.
:::

## Configuración {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

Muchos valores escalares tienen variables de entorno equivalentes: `SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH`, `SEO_OG_IMAGE_NO_SANDBOX`, etc. Consulta la lista completa en la configuración. Los arrays `templates`, `models`, `browsershot_args` y `font_stack` se editan directamente en el archivo.

El disco debe ser **públicamente accesible**, porque su `url()` se usa para `og:image`. Con `public`, ejecuta una vez `php artisan storage:link` para crear `public/storage`.

## Linux y el sandbox {#running-on-linux-the-sandbox}

Si el host restringe los mecanismos de aislamiento de Chrome, la generación puede fallar con:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Una posible causa son los espacios de nombres de usuario restringidos en Ubuntu 23.10+. Revisa el error real de arranque y la [guía de Puppeteer](https://pptr.dev/troubleshooting). Prefiere corregir el host para conservar el sandbox.

**1. Alternativa explícita: `--no-sandbox`.** Desactiva el aislamiento del navegador. Úsala solo si el despliegue acepta conscientemente ese compromiso:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

El HTML estático y el bloqueo de recursos remotos no sustituyen el sandbox. Ejecuta el proceso sin privilegios y aislado de otras aplicaciones y sus secretos.

**2. Conservar el sandbox.** Deja `no_sandbox` desactivado. Si AppArmor es la causa, adapta un perfil para el ejecutable exacto de Chrome según las [indicaciones de Chromium](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md). Ejemplo:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Carga el perfil con `sudo apparmor_parser -r /etc/apparmor.d/chrome-og` y comprueba que Chrome arranca con sandbox.

::: tip Otros argumentos
En contenedores con poca memoria compartida, Chrome puede detenerse durante el renderizado. Añade argumentos mediante `browsershot_args`:

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Controladores personalizados {#custom-drivers}

`browsershot` es el único incluido, pero el renderizador implementa `Rankbeam\Seo\Contracts\OgImageRenderer`. Registra uno propio, por ejemplo basado en canvas o un servicio, y selecciónalo en `seo.og_image.driver`:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

El controlador convierte una cadena HTML autónoma en bytes PNG con las dimensiones solicitadas. No gestiona diseño ni plantillas.

## Fuentes y escrituras no latinas {#fonts-and-non-latin-scripts}

Noto Sans Bold, incluida con licencia OFL, cubre **latín, cirílico y griego**. Chino, japonés, coreano, tailandés, árabe, hebreo, devanagari y emoji dependen de fuentes del host que ejecuta `seo:og-images`. Una fuente CJK puede superar 16 MB y no se incluye. Chrome usa su respaldo por carácter cuando hay fuentes adecuadas.

Desde 3.15 intervienen tres mecanismos:

1. **Lista de familias en cada plantilla.** El body empieza con `'OGBrand'`, continúa con `seo.og_image.font_stack` y termina en `sans-serif`. La lista integrada contiene `Noto Sans`, cuatro familias `Noto Sans CJK`, `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`, `Noto Sans Devanagari` y `Noto Color Emoji`. Se omiten familias no instaladas. La familia CJK del idioma pasa delante: `ja` → JP, `zh-Hans` → SC, `zh-Hant` / `zh-TW` / `zh-HK` → TC, `ko` → KR. Esto selecciona formas nacionales de caracteres Han compartidos. `<html lang>` expresa el idioma BCP47. La lista forma parte de la clave de caché; cambiarla renueva las tarjetas.

2. **Comprobación previa de `seo:og-images`.** Consulta fontconfig (`fc-list :lang=ja`, `th`, `ar`, …) para las escrituras del título, nombre del sitio y descripción, incluidas minoritarias en textos mixtos. Avisa una vez por escritura e indica una instalación posible:

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   Sin fontconfig, como en Windows, macOS o contenedores mínimos, no adivina la cobertura y no emite ese aviso. Una fuente ausente no hace fallar necesariamente el renderizado: Chrome puede dibujar cuadros .notdef, motivo de la comprobación.

3. **Fixtures de glifos en pruebas reales.** Con `SEO_OG_IMAGE_LIVE_TEST=1`, `tests/Feature/OgImage/BrowsershotSmokeTest.php` renderiza títulos en ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he e hi, junto a controles de longitud equivalente formados por un punto de código sin asignar. PNG idénticos provocan un fallo con el idioma y paquete necesario. Es una prueba de muestra, no una garantía de todos los glifos: texto latino o saltos de línea distintos pueden producir diferencias aunque falten caracteres. Inspecciona el resultado real y las fuentes utilizadas en producción. FontProbe tampoco certifica toda la cobertura. No existe `seo:doctor` en el núcleo; usa `seo:og-images` para esta comprobación.

En Debian/Ubuntu:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

Las plantillas publicadas antes de 3.15 siguen funcionando. Reciben `$fontFamily` y `$lang`, pero pueden ignorarlas.

## Limitaciones {#caveats}

- **Solo generación previa.** No hay ruta pública que renderice tarjetas bajo demanda. Esta función no expone ese tipo de endpoint que requiera URL firmada o protección SSRF/DoS. Debes ejecutar [`seo:og-images`](#the-seo-og-images-command) al desplegar o según un horario.
- **`npm_module_path` no funciona en Windows.** El prefijo POSIX de Browsershot se ignora. Instala Puppeteer en la raíz de la aplicación. El ajuste funciona en Linux/macOS.
- **Las escrituras no latinas necesitan fuentes del host.** Solo se incluyen latín, cirílico y griego. Consulta [fuentes](#fonts-and-non-latin-scripts), avisos y resultados reales.
- **Respaldo ante fallos.** El comando informa de dependencias ausentes, cierres del navegador o tiempos de espera agotados. La página conserva `default_og_image`; un navegador averiado no provoca una respuesta 500 de la página.
