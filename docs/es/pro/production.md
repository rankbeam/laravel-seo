---
description: "Opera Pro con colas dedicadas, scheduler, reintentos, recuperación, retención y telemetría, independientemente de Filament."
---

# Configuración de producción {#production-setup}

Los scans, rastreo de enlaces, volcado opcional de contadores de redirección y limpieza de 404 utilizan las colas y el scheduler de Laravel. Esta guía reúne la configuración para operarlos: colas dedicadas, programación, reintentos, recuperación, retención y telemetría. Describe la estructura utilizada en una instalación de unas 900 páginas y 20.000 visitas diarias.

Todo es **independiente de Filament**. Motor, comandos, colas y telemetría funcionan igual con panel o sin él. Filament añade vistas, sin cambiar la programación ni el procesamiento.

[[toc]]

## Orden de puesta en marcha {#safe-rollout-order}

Sigue estos pasos y verifica cada uno antes de pasar al siguiente:

1. **Instala**: publica configuración y migraciones y ejecútalas:

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` publica `config/seo-pro.php` y las migraciones Pro y ejecuta `migrate`. El paquete no carga automáticamente esas migraciones; este paso crea el esquema tras `composer require`. Es idempotente. `--force` sobrescribe archivos publicados; `--no-migrate` publica sin migrar.

2. **Registra objetivos** en un proveedor, como `AppServiceProvider::boot()`:

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Verifica** antes de activar tareas en segundo plano:

   ```bash
   php artisan seo:doctor
   ```

   Corrige los avisos siguiendo el comando o ajuste indicado. En CI usa `--json` y los identificadores estables de comprobación.

4. **Configura colas y scheduler**, despliega workers y añade el cron de `schedule:run`.

5. **Activa las funciones opcionales al final**. Rastreador, IA y Search Console están desactivados por defecto. El rastreador necesita sus tablas migradas y un worker dedicado.

**Actualización a Pro 2.41.0:** pausa los workers de escaneo, publica las migraciones con `php artisan vendor:publish --tag=seo-pro-migrations --force`, ejecuta `php artisan migrate`, reinicia los workers y ejecuta `php artisan seo:doctor`. Se requieren la tabla `seo_scan_target_completions` y la columna `seo_scan_runs.target_tracking`. Los registros por ejecución/objetivo impiden que resultados terminales duplicados aumenten los contadores; prevalece el primer resultado aceptado. Las ejecuciones antiguas en cola sin objetivos procesados continúan. Las procesadas parcialmente antes de actualizar conservan el historial, pero se cierran en la siguiente entrega pidiendo un nuevo escaneo. Reintenta los objetivos agotados en una nueva ejecución. Para revertir, detén los workers y restaura el código antes de deshacer la migración; conserva una copia previa de la base de datos si también necesitas deshacer escaneos posteriores.

## Colas dedicadas por tarea {#dedicated-queues-per-workload}

Un scan largo no debe retrasar correo o notificaciones. Asigna a cada tarea SEO una cola y un worker propios.

| Tarea | Configuración | Variable | Cola predeterminada |
|---|---|---|---|
| Jobs de scan de página | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | Cola predeterminada de la aplicación |
| Jobs de rastreo de enlaces | `seo-pro.broken_links.queue.name` y `.connection` | `SEO_PRO_BROKEN_LINKS_QUEUE` y `_CONNECTION` | `seo-broken-links` |

### Ejemplo Redis para producción {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Ejecuta un proceso o programa Supervisor por cola:

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

El timeout del worker de rastreo debe superar `seo-pro.broken_links.batch.hard_time_budget_seconds`, 180 por defecto, más el timeout HTTP, con margen para registrar el progreso. El job define su `$timeout` a partir de la suma; alinea los ajustes del worker. Usa `--tries=1` para el rastreador: una continuación o `seo-pro:broken-links-recover` recupera un job detenido, por lo que no necesita reintentos adicionales de la cola.

`seo:doctor` informa de la cola de cada tarea y avisa si resuelve a `sync`, que ejecutaría el trabajo en línea y bloquearía el proceso.

## Scheduler {#scheduler}

En Laravel 11, 12 y 13, registra la programación en **`routes/console.php`**. Las aplicaciones actualizadas desde Laravel 10 pueden conservar `app/Console/Kernel.php::schedule()`; usa ahí las mismas entradas si corresponde. Añade un cron que ejecute el scheduler cada minuto:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Registra los comandos recurrentes:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Frecuencias recomendadas:

| Comando | Frecuencia | Motivo |
|---|---|---|
| `seo:sitemap` | Diaria | Actualizar el sitemap con el contenido actual |
| `seo-pro:scan` | Semanal; diaria si el contenido cambia mucho | Revisar todos los objetivos |
| `seo-pro:scan-recover` | Cada hora | Recuperar ejecuciones abandonadas |
| `seo-pro:scan-prune` | Diaria | Aplicar retención de scans |
| `seo-pro:redirects-flush-hits` | Cada cinco minutos, solo con `redirects.hits.flush_immediately=false` | Pasar los contadores de caché a la base |
| `seo-pro:404-prune` | Diaria | Aplicar retención y máximo de filas de 404 |
| `seo-pro:404-recheck` | Diaria | Volver a obtener rutas 404 abiertas y marcar como recuperadas las que responden 200 |
| `seo-pro:broken-links-scan` | Semanal | Rastrear de nuevo para confirmar entre ejecuciones |
| `seo-pro:broken-links-recover` | Cada hora | Recuperar rastreos abandonados |
| `seo-pro:broken-links-prune` | Diaria | Aplicar retención del rastreador |

`seo-pro:scan` y `seo-pro:broken-links-scan` solo encolan; los workers realizan el trabajo. Recuperación y limpieza se ejecutan en el propio proceso y son ligeras.

::: tip Confirmación entre rastreos
Un enlace se confirma roto tras `seo-pro.broken_links.mark_broken_after_failures` fallos consecutivos; cualquier éxito reinicia el contador. Con el valor predeterminado tres y frecuencia semanal, se confirma unas dos semanas después del primer fallo observado o hasta tres semanas después de romperse. Aumenta la frecuencia o reduce el umbral si necesitas confirmación más rápida.
:::

## Ajustar los lotes del rastreador {#batch-tuning-broken-link-crawler}

El rastreo se divide en jobs acotados que encolan continuaciones. Ajusta los valores finitos predeterminados a la capacidad del sitio y hosts consultados, en `seo-pro.broken_links`:

| Clave | Valor predeterminado | Límite |
|---|---|---|
| `max_pages_per_run` | `2000` | Páginas por ejecución; `null` elimina explícitamente ese límite |
| `max_links_per_page` | `200` | Enlaces por página |
| `max_total_links` | `null` | Límite global opcional de comprobaciones |
| `batch.max_pages_per_job` | `50` | Páginas por job |
| `batch.max_links_per_job` | `1500` | Enlaces por job |
| `batch.hard_time_budget_seconds` | `180` | Al alcanzarlo no inicia peticiones nuevas y encola una continuación |
| `batch.dispatch_delay_seconds` | `1` | Pausa entre continuaciones |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Límites por petición |
| `http.max_response_bytes` | Hereda `seo-pro.http.max_response_bytes` | Límite durante la lectura de cuerpos de páginas y destinos |
| `seed.max_response_bytes` | Hereda el límite de inicialización/rastreador/HTTP compartido | Bytes de XML o `.gz` del sitemap inicial |
| `seed.max_inflated_bytes` | Hereda el límite correspondiente | Bytes descomprimidos aceptados de un sitemap `.gz` |
| `http.per_host_delay_ms` | `0` | Pausa entre comprobaciones al mismo host; auméntala para `internal_and_external` |

Mantén el presupuesto del lote por debajo del timeout del worker, con margen. Una petición ya iniciada puede continuar hasta `http.timeout`; por eso debes contemplar presupuesto del lote, timeout HTTP y margen de finalización.

Para `internal_and_external`, amplía `seo-pro.http.scope` o `allowed_hosts` para permitir los destinos y aumenta la pausa por host. `seo:doctor` avisa si el ámbito externo está habilitado pero la protección bloquearía todas esas peticiones.

## Horizon y Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Un programa por cola. Ejemplo de `/etc/supervisor/conf.d/app-workers.conf`:

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

`stopwaitsecs` debe superar `--timeout` para que un reinicio ordenado no corte un lote.

### Horizon {#horizon}

Si usas Horizon, define un supervisor por tarea en `config/horizon.php` y deja que gestione los procesos:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Reintentos y fallos {#retry-failure-handling}

El job de scan de objetivo define su propia política y no depende de `--tries` del worker:

| Clave | Valor predeterminado | Significado |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Intentos por objetivo |
| `seo-pro.scan.backoff` | `30` | Segundos entre intentos |
| `seo-pro.scan.timeout` | `300` | Timeout por objetivo; el bloqueo de solapamiento caduca 60 segundos después |

Al agotar reintentos, el objetivo se registra como fallido y la ejecución termina como `partial` o `failed`. Los fallos gestionados no la dejan en `running`. Si el worker muere antes de registrar el progreso, hace falta la recuperación siguiente. Los fallos llegan a `failed_jobs`:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Programa `queue:prune-failed` para limitar esa tabla:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

El rastreador usa `--tries=1`: una continuación detecta el bloqueo temporal caducado o `seo-pro:broken-links-recover` recupera la ejecución. Reintentos de cola adicionales duplicarían trabajo.

## Recuperación {#recovery}

Si el worker muere en mitad del job, dos comprobaciones programadas **cada hora** cierran las ejecuciones abandonadas:

- `seo-pro:scan-recover` marca como fallidos scans sin progreso durante `seo-pro.scan.recovery.stuck_scan_timeout_hours`, dos horas por defecto.
- `seo-pro:broken-links-recover` recupera rastreos sin señal de actividad durante `seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, dos horas por defecto, los marca fallidos y libera el puesto de ejecución activa de ese ámbito.

`seo:doctor` informa de las señales recientes del historial: detecta ejecuciones estancadas e indica el comando de recuperación. No demuestra que el cron externo esté funcionando.

## Retención {#retention}

Limita las tablas. Valores predeterminados en `seo-pro.*`; `null` desactiva la limpieza correspondiente:

| Datos | Configuración | Valor | Comando |
|---|---|---|---|
| Scans y problemas | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| Registro 404 | `monitor_404.retention_days`, con `max_rows` de `10000` | `90` | `seo-pro:404-prune` |
| Rastreos | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Hallazgos resueltos | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Telemetría operativa {#operational-telemetry}

Cada scan y rastreo finalizado emite una línea estructurada mediante el sistema de logs. Solo contiene recuentos y tiempos, sin URL, cuerpos, cabeceras ni datos de visitantes:

| Métrica | Scan | Rastreo |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls`, rechazadas por SSRF | — | ✓ |
| `transient_failures`, fallos de red que se vuelven a comprobar | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds`, de encolado al primer lote | ✓ | ✓ |

Configúrala en `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Apunta `channel` a un canal de logs dedicado para enviar las líneas a Loki, Datadog o CloudWatch sin mezclarlas con los logs de aplicación:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

También puedes escuchar eventos; todos exponen el mismo contenido `metrics()`:

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

La telemetría es de mejor esfuerzo: un canal mal configurado no hace fallar el scan.

## Despliegue independiente de Filament {#filament-independent-deployment}

Motor, comandos, colas, scheduler, recuperación, retención y telemetría no necesitan panel. `SeoProPlugin` añade vistas de progreso, problemas, redirecciones, 404 y enlaces rotos. Puedes operar con CLI y scheduler y añadir el panel después, sin rehacer el procesamiento ni migrar por ese cambio. Consulta [Uso sin interfaz](/es/pro/headless).
