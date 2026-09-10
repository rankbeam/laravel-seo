---
description: "Gestiona Pro desde artisan sin Filament: scans, redirecciones, registro de 404, diagnósticos y mantenimiento."
---

# Uso sin interfaz {#headless-usage}

Todas las funciones de Pro —scans, redirecciones y registro de 404— pertenecen al motor y funcionan sin Filament. El panel es una interfaz de gestión; estos comandos permiten gestionar el mismo motor sin ella.

## Referencia de comandos {#command-reference}

### Instalación y comprobación de estado {#setup-health-check}

| Comando | Función |
|---|---|
| `seo-pro:install` | Publica `config/seo-pro.php` y las migraciones Pro, las ejecuta y muestra los siguientes pasos; admite `--no-migrate` y `--force` |
| `seo:doctor` | Comprueba URL de aplicación, tablas, objetivos, sitemap, colas por tarea, funciones opcionales y estado operativo, con correcciones concretas para cada aviso; `--json` para monitorización |

`seo-pro:install` es la vía de instalación documentada. Las migraciones Pro se publican en la aplicación y nunca se cargan automáticamente desde el paquete; el instalador convierte `composer require` en un esquema funcional. Es idempotente y puedes repetirlo.

`seo:doctor` no hace llamadas de red ni imprime secretos. La comprobación de IA solo indica si la variable de clave configurada tiene valor. Revisa configuración e historial reciente, pero no demuestra que un cron o worker externo esté funcionando. Solo termina con código distinto de cero ante un fallo crítico, como una tabla requerida ausente; una instalación local con avisos puede terminar correctamente. `--json` asigna a cada comprobación un `id` estable. Ejecútalo después de [instalar](/es/pro/installation) y en CI.

### Scans {#scanning}

| Comando | Función |
|---|---|
| `seo-pro:scan` | Encola un scan de todos los objetivos; `--sync` lo ejecuta en el propio proceso. Las opciones de CI `--fail-on-error`, `--fail-on-warning`, `--report=` y `--format=json\|md\|html` requieren `--sync`. |
| `seo-pro:scan-status` | Resumen de la última ejecución y problemas abiertos, primero los más graves; `--limit=20`, `--severity=critical\|warning\|notice` |
| `seo-pro:scan-recover` | Marca como fallidas las ejecuciones abandonadas por un worker detenido |
| `seo-pro:scan-prune` | Elimina ejecuciones finalizadas y sus problemas fuera de la ventana de retención |

### Rastreador de enlaces rotos {#broken-link-crawler}

Está desactivado por defecto. Habilita `seo-pro.broken_links.enabled` y migra sus dos tablas principales, publicadas por `seo-pro:install`. Las inspecciones tienen además su tabla correspondiente. El rastreo se reparte en jobs acotados; utiliza un worker dedicado a su cola. Consulta [Configuración de producción](/es/pro/production).

| Comando | Función |
|---|---|
| `seo-pro:broken-links-scan` | Encola un rastreo acotado y reanudable; `--scope=internal_only\|internal_and_external`, `--url=*` para URL iniciales adicionales |
| `seo-pro:broken-links-status` | Resumen del último rastreo, hallazgos abiertos e [inspecciones por tipo](/es/pro/broken-links#typed-link-inspections); opciones de CI `--fail-on-error`, `--fail-on-warning`, `--report=` y `--format=` |
| `seo-pro:broken-links-cancel` | Cancela un rastreo en ejecución o en cola; `{run?}` usa por defecto el último activo |
| `seo-pro:broken-links-recover` | Marca como fallidos los rastreos abandonados cuyo bloqueo temporal ha caducado |
| `seo-pro:broken-links-prune` | Aplica retención a ejecuciones antiguas y hallazgos resueltos |

### Redirecciones y 404 {#redirects-404s}

| Comando | Función |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Crea una regla; `--code=301`, `--regex`, `--no-preserve-query`, `--note=` |
| `seo-pro:404-list` | Muestra los 404 por número de peticiones descendente; `--status=new\|ignored\|redirected\|all`, `--limit=20` |
| `seo-pro:redirects-flush-hits` | Guarda en la base los contadores de redirección acumulados en caché cuando `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Elimina 404 antiguos y aplica el máximo de filas |

### Lista de comprobación de página {#on-page-checklist}

| Comando | Función |
|---|---|
| `seo-pro:checklist {model} {id}` | Comprobaciones pass/warn/fail de un modelo que tienen en cuenta las palabras clave; `--json`, `--strict`, `--locale=`. Consulta [Lista de comprobación de página](/es/pro/on-page-checklist). |

También está disponible como `SeoPro::checklistFor($model)`. Es el ciclo editorial —ubicación de palabras clave, longitud, imágenes y enlaces internos—, no la [puntuación SEO](/es/pro/scoring).

### Search Console: solo lectura {#search-console-read-only}

| Comando | Función |
|---|---|
| `seo-pro:search-console` | Páginas con problemas abiertos y tráfico de búsqueda, priorizadas por oportunidad; `--view=attention` por defecto |
| `seo-pro:search-console --view=pages` | Páginas principales por impresiones, clics, CTR y posición |
| `seo-pro:search-console --view=queries` | Consultas principales; `--days=`, `--limit=`, `--json` |

Las métricas también se consultan con `SeoPro::searchConsole()`. Consulta [Search Console](/es/pro/search-console). Está desactivado por defecto y es estrictamente de solo lectura.

### Asistencia de IA {#ai-assist}

| Comando | Función |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Sugerencias de título/descripción en JSON; `--field=title\|description\|all`. Consulta [Asistencia de IA](/es/pro/ai-assist). |
| `seo-pro:ai-suggest --issue={id}` | Explicación de cómo corregir un problema de scan, en JSON |

### Resolver un 404 en un paso {#resolving-a-404-in-one-step}

`--from-404={path}` equivale a la acción *Crear redirección* del monitor: crea la regla y marca el registro 404 correspondiente como redirigido, vinculándolo a la nueva regla.

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

El comando usa los mismos validadores que el formulario Filament. Rechaza regex inválidas, valores demasiado grandes y destinos externos fuera de la lista permitida antes de escribir.

## Programación recomendada {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

La [guía de producción](/es/pro/production) indica la frecuencia recomendada de cada comando recurrente, las colas, workers, reintentos, recuperación, retención y **telemetría** estructurada de cada ejecución finalizada: páginas obtenidas, enlaces comprobados, URL bloqueadas, duración y retraso de cola.

## Qué requiere Filament {#what-needs-the-filament-ui}

Ninguna función del motor. Scans, seguimiento de problemas, coincidencia de redirecciones, registro de 404, limpieza y recuperación funcionan igual con Filament o sin él. El panel añade vistas: progreso y estadísticas por gravedad, filtros y detalles por página, botones para ignorar/reabrir, formularios CRUD de redirecciones y tabla de 404 con su acción. Ignorar o reabrir problemas no tiene actualmente un comando dedicado; hazlo en el panel o mediante `SEOScanIssue` (`markIgnored()` / `reopen()`) desde tinker o tu código.
