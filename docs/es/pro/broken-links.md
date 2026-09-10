---
description: "Rastreador acotado y reanudable de enlaces internos rotos y, opcionalmente, externos. Registra hallazgos y permite crear redirecciones internas. Desactivado por defecto."
---

# Rastreador de enlaces rotos {#broken-link-crawler}

Un **rastreador acotado y reanudable** que recorre el sitio y registra enlaces que no resuelven: enlaces **internos** a rutas inexistentes, corregibles mediante redirección, y enlaces **externos** si lo habilitas. Está **desactivado por defecto**.

Tres principios:

- **Acotado y reanudable.** Trabaja en jobs pequeños con límite de páginas y encola continuaciones hasta terminar o alcanzar los límites. La ejecución completa también tiene un máximo, 2.000 páginas por defecto. `null` permite eliminar expresamente ese límite, nunca por defecto; los límites de lote y tiempo siguen aplicándose. Ajusta límites y pausas a la capacidad del sitio y servidor.
- **Restricciones por defecto.** `internal_only` comprueba enlaces del mismo host sin peticiones a terceros. Cada petición pasa por **SsrfGuard**, con esquemas permitidos, ámbito de host y rechazo de direcciones privadas. Los enlaces externos requieren activación y siguen protegidos.
- **Separado de la puntuación SEO.** Los hallazgos tienen tablas propias y no escriben en `seo_scan_issues` ni modifican el resultado de 0 a 100. Los enlaces rotos se gestionan como un problema operativo independiente.

## Qué incluye {#what-you-get}

En el panel Filament, cuando está habilitado:

- **Resumen de enlaces rotos**: totales abiertos internos/externos y último rastreo, con enlace a los hallazgos.
- **Rastreo en curso**: progreso de páginas recorridas, enlaces comprobados y problemas encontrados.
- **Enlaces rotos por scan**: tendencia de rastreos recientes.
- **Recurso de hallazgos**: cada enlace `origen → destino`, con filtros y creación de redirecciones para los internos.

Sin panel, los mismos datos están en los comandos `seo-pro:broken-links-*`.

## Por qué está desactivado por defecto {#why-it-s-off-by-default}

El rastreador hace **peticiones de red** y necesita infraestructura, por lo que requiere activación deliberada:

- Sus dos tablas principales se publican mediante migraciones Pro y deben migrarse antes de consultar la interfaz. Las inspecciones por tipo usan además `seo_broken_link_inspections`.
- El trabajo se encola en una **cola dedicada** y necesita un **worker**; sin él no avanza.
- La confirmación se hace entre varios rastreos, por lo que está pensado para ejecutarse de forma programada a lo largo del tiempo.

## Preparación {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Ejecuta las migraciones. `seo-pro:install` publica y ejecuta todas las migraciones Pro y se puede repetir:

```bash
php artisan seo-pro:install
```

Arranca un **worker dedicado** para `seo-broken-links`, de forma que un rastreo largo no se coloque delante de tareas de los usuarios:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Comprueba la configuración. `seo:doctor` revisa activación, tablas y que la cola use una conexión real distinta de `sync`, con correcciones concretas:

```bash
php artisan seo:doctor
```

Consulta [Configuración de producción](/es/pro/production) para Redis, Supervisor, conexiones dedicadas y ajuste de lotes.

## Ejecutar un rastreo {#running-a-crawl}

Usa **Scan now** en el panel o los comandos:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Ambos comandos solo **encolan** el rastreo; el worker lo ejecuta.

## Cuándo se confirma un enlace roto {#how-a-link-gets-flagged}

Un hallazgo se confirma después de `seo-pro.broken_links.mark_broken_after_failures` **rastreos consecutivos** fallidos, tres por defecto. Cualquier éxito reinicia el contador. Una interrupción puntual no confirma el enlace, por eso conviene programar rastreos repetidos. Con frecuencia semanal y umbral tres, se confirma unas dos semanas después del primer fallo observado, o hasta unas tres semanas después de romperse. Aumenta la frecuencia o reduce el umbral si necesitas confirmación más rápida.

## Inspecciones de enlaces por tipo {#typed-link-inspections}

Además de comprobar disponibilidad, cada enlace pasa por inspecciones de URL: barras finales, codificaciones, cadenas de redirección, `javascript:`, fragmentos inexistentes, texto de enlace poco descriptivo y otras. Cada tipo tiene una gravedad `critical`, `warning` o `notice`, compatible con los [problemas del scan](/es/pro/scan-issues), y se registra por ejecución en `seo_broken_link_inspections`. A diferencia de los hallazgos que requieren varios fallos, las inspecciones aparecen **desde el primer rastreo**, para poder usarlas en CI.

### Referencia de inspecciones {#inspection-reference}

| Inspección | Gravedad | Detecta | Se aplica a |
| --- | --- | --- | --- |
| `broken_link` | critical | Destino con HTTP ≥ 400 | Cualquier enlace |
| `redirect_chain` | notice · warning | Destino que requiere redirecciones; warning al superar `redirect_chain_warning_hops` | Cualquier enlace |
| `link_unreachable` | notice | Error de red, timeout o bloqueo en este rastreo; puede ser temporal | Cualquier enlace |
| `insecure_link` | warning | Enlace `http://` en un sitio `https`, con degradación del transporte | Cualquier enlace |
| `trailing_slash` | notice | Ruta interna que incumple la convención; desactivado hasta configurar `trailing_slash` | Internos |
| `double_slash_url` | warning | Ruta interna con `//`, segmento vacío | Internos |
| `duplicate_query_param` | notice | Clave repetida como `?a=1&a=2`; la sintaxis de array `key[]` queda excluida | Internos |
| `non_ascii_url` | notice | Caracteres no ASCII sin codificar en la ruta | Internos |
| `uppercase_url` | notice | Mayúsculas en la ruta; revisa variantes que el servidor sirve por separado | Internos |
| `underscore_in_url` | notice | Guiones bajos en la ruta; se prefieren guiones como separador SEO | Internos |
| `javascript_link` | warning | `href` con `javascript:`, sin destino rastreable normal | Cualquier enlace de anclaje |
| `missing_fragment` | warning | Fragmento de la misma página sin `id`/`name` coincidente | Misma página |
| `non_descriptive_anchor` | notice | Texto genérico como «haz clic aquí», «leer más» o una URL sin descripción | Cualquier enlace de anclaje |
| `absolute_internal_link` | notice | Enlace interno absoluto en vez de ruta relativa a la raíz | Internos |

Las inspecciones de formato —barra final, mayúsculas, codificación, doble barra— solo se aplican a enlaces internos. Redirecciones, roturas, falta de acceso e inseguridad se comprueban en todos. Se omiten las rutas de framework y recursos estáticos propios configurados en `exclude_paths` / `exclude_extensions`.

Cada enlace se solicita con su **URL exacta escrita**, quitando solo `#fragment`, sin normalizarla antes. Así se observa una redirección `/about/ → /about` como `redirect_chain`. Se inspecciona cada forma distinta, como `/page#ok` y `/page#missing`, o `/a//b` y `/a/b`. El hallazgo de enlace roto agrupa los alias de un destino bajo una identidad; las inspecciones se guardan por `(página, destino, inspección)`. Varios fragmentos fallidos del mismo destino producen una fila `missing_fragment` con un ejemplo, no una por ancla.

### Ajustar las inspecciones {#tuning-the-taxonomy}

Las opciones están en `seo-pro.broken_links.inspections`:

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

Elimina una clase de `rules` para desactivar esa regla, o usa `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false` para desactivarlas todas. Dos particularidades:

- `trailing_slash` permanece desactivado hasta declarar `'always'` o `'never'`. Si `/x` y `/x/` devuelven 200 sin una convención, no hay un formato incorrecto declarado. Si el servidor redirige, eso ya aparece como `redirect_chain`.
- `absolute_internal_link` se activa en cada enlace interno absoluto. Si esa es tu convención, puede generar muchos notices inocuos; retíralo de `rules` para silenciarlo.

## Integración continua {#continuous-integration}

Tanto el rastreo como la [auditoría SEO Pro](/es/pro/scan-issues) pueden hacer fallar una compilación y escribir un informe. `--fail-on-error` corresponde a `critical`; `--fail-on-warning` falla ante `critical` o `warning`. No existe una categoría error separada.

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` escribe el informe y deduce el nombre si indicas un directorio. `--format` admite `json`, predeterminado, `md` o `html`. JSON sirve para procesamiento automático; HTML produce una página independiente adjuntable a la ejecución.

### GitHub Actions {#github-actions}

El rastreador obtiene las páginas mediante HTTP. CI debe apuntar a una aplicación local accesible o a una URL de staging mediante `SEO_PRO_BROKEN_LINKS_BASE_URL`. Registra modelos/sitemap para proporcionar URL iniciales.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## Programación {#scheduling}

Registra el rastreo y mantenimiento en `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Referencia de comandos {#command-reference}

| Comando | Función |
| --- | --- |
| `seo-pro:broken-links-scan` | Encola un rastreo acotado y reanudable; `--scope=internal_only\|internal_and_external`, `--url=*` para URL iniciales |
| `seo-pro:broken-links-status` | Resumen, hallazgos abiertos e inspecciones; opciones CI `--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html` |
| `seo-pro:broken-links-cancel` | Cancela un rastreo activo o en cola; `{run?}` usa el último activo por defecto |
| `seo-pro:broken-links-recover` | Marca como fallidos rastreos abandonados con bloqueo temporal caducado |
| `seo-pro:broken-links-prune` | Aplica retención a ejecuciones antiguas y hallazgos resueltos |

## Ajuste {#tuning}

Páginas por ejecución, enlaces por página, límites por job, tiempo máximo y pausas por host se configuran en `seo-pro.broken_links`. Los valores predeterminados son finitos y conservadores. Consulta la [tabla de ajuste de producción](/es/pro/production) antes de aumentarlos.
