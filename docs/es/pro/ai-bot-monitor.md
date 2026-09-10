---
description: "Registra las peticiones atribuidas a rastreadores de IA: frecuencia, última URL y estado HTTP observado. Complemento de observabilidad del control de rastreadores."
---

# Monitor de bots de IA {#ai-bot-monitor}

Las peticiones se atribuyen mediante **coincidencia de user-agent**, no mediante una identidad de bot verificada. El monitor registra peticiones observadas; un user-agent puede falsificarse.

El [control de rastreadores de IA](/es/guide/ai-crawlers) del núcleo decide qué les indica `robots.txt`. El **monitor de bots de IA** de Pro registra la actividad observada: qué user-agents de rastreadores accedieron al sitio, cuántas veces y qué última URL y estado HTTP se registraron para cada uno.

Reutiliza la estructura del monitor de 404 —middleware global terminable, modelo con upsert y contador de peticiones, y las mismas opciones de privacidad—, pero agrupa por **bot** en vez de por ruta y registra **cualquier** estado de respuesta. Incluye precisamente los rastreadores que el monitor de 404 excluye. La identificación utiliza `AiCrawlerRegistry` del núcleo, de modo que la política robots y la observación comparten catálogo.

::: tip Requiere core ≥ 3.3
El monitor identifica bots con el catálogo de rastreadores del núcleo, [`SEO::aiCrawlers()`](/es/guide/ai-crawlers). Con versiones anteriores permanece inactivo.
:::

## Activarlo {#enabling-it}

Está desactivado por defecto. Al habilitarlo, el middleware registra los rastreadores coincidentes después de cada respuesta, sin retrasar el envío de la página:

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

El middleware se registra automáticamente; puedes desactivarlo con `ai_bots.auto_register_middleware`. Se inserta o actualiza una fila por bot conocido, por lo que el tamaño de la tabla queda limitado por el catálogo.

## Consultar el registro {#reading-the-log}

### Sin interfaz {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Cada fila expone `bot`, `label`, `operator`, `purpose`, `hit_count`, `last_path`, `last_status`, `first_seen_at` y `last_seen_at`.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Con el plugin Pro registrado, aparece una tabla **AI Bots** en el grupo de navegación SEO: bot, operador, finalidad, peticiones, último estado, última ruta y última observación. Es de solo lectura y se puede filtrar por finalidad.

## Privacidad {#privacy}

Como en el monitor de 404, **no se guarda la IP por defecto**. La opción `ai_bots.hash_ip` almacena únicamente un sha256 con clave en `ip_hash`, nunca la IP original.

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## Métricas por período: agrupaciones diarias {#period-metrics-daily-buckets}

La tabla histórica conserva una fila por bot. Sirve para ver totales, pero no responde cuántas peticiones o URL distintas hubo en un período concreto. Con `daily_enabled` activo, su valor predeterminado, cada petición se registra también por día y ruta en `seo_ai_bot_daily`. Así, el [informe con marca propia](/es/pro/reports) muestra cifras del período —peticiones desde el informe anterior y URL distintas— en lugar de una diferencia entre totales históricos.

El crecimiento se mantiene acotado:

- Un **máximo de rutas distintas por bot y día**, `daily_max_paths`. Superado el límite, las rutas nuevas se agrupan en una única fila de desbordamiento. El total diario de peticiones sigue siendo exacto y se limita el número de filas; el recuento de URL que alcanza el límite se muestra como «N+».
- Una **ventana de retención**, `daily_retention_days`, aplicada por `seo-pro:ai-bots-prune`.

Pon `daily_enabled` en `false` para conservar solo los totales históricos. En ese caso, el informe calcula «desde el anterior» mediante la diferencia con la instantánea del informe previo e ignora las agrupaciones existentes para no leer una tabla desactualizada.

Las cifras tienen **resolución diaria**. «Desde el informe anterior» cuenta días completos desde el día de ese informe; una petición de ese día puede ser anterior o posterior a la hora exacta de generación. En una frecuencia diaria, semanal o mensual habitual, esta diferencia de límite temporal suele ser pequeña.

## Pasar de la observación al control {#turning-observation-into-control}

El monitor muestra qué user-agents acceden; el [control de rastreadores de IA](/es/guide/ai-crawlers) define lo que les permite la política robots. Si aparece un rastreador de entrenamiento cuyo acceso quieres restringir:

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Algunos bots no respetan `robots.txt`, según su documentación. El monitor ayuda a detectar esa actividad y decidir si bloquearla en el firewall, WAF o Cloudflare.
