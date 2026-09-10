---
description: "Programa scans SEO completos y consulta qué problemas aparecieron, reaparecieron o se resolvieron, ordenados por impacto, en el panel y en un correo opcional."
---

# Programación de scans y comparación de cambios {#scan-scheduling-delta}

Ejecuta scans SEO completos **según una programación** y consulta **qué cambió desde el anterior**: problemas nuevos, reaparecidos y resueltos, ordenados por impacto, en el panel y opcionalmente en un resumen por correo.

Ambas funciones se explican juntas porque la comparación de cambios es lo que hace útil el resultado de un scan programado.

## Qué cambió desde el scan anterior {#what-changed-since-the-last-scan}

Cada scan completado conserva una **instantánea de los problemas abiertos** en `seo_scan_run_issues`. Comparar dos instantáneas produce tres grupos:

- **New**: un problema que antes no estaba abierto, ahora sí y nunca había estado abierto en una ejecución anterior; es la primera detección.
- **Regressed**: un problema que se había resuelto y ha reaparecido. No significa que aumentara su gravedad, que es fija por tipo; corresponde a una reapertura en el [ciclo de vida](/es/pro/scan-issues#issue-lifecycle).
- **Fixed**: un problema que estaba abierto en el scan anterior y ya no lo está.

Cada grupo se [ordena por impacto](#impact-ordering) para mostrar primero los problemas prioritarios.

### Por qué guardar una instantánea {#why-a-snapshot-not-the-issues-table}

Los problemas tienen un [ciclo de resolución y reapertura](/es/pro/scan-issues#issue-lifecycle). La misma fila se actualiza entre scans y su `scan_run_id` apunta a la ejecución más reciente mientras sigue abierta. Eso conserva el historial del problema, pero la tabla actual no permite saber qué problemas estaban abiertos al terminar una ejecución antigua.

Cada ejecución guarda por ello su conjunto abierto, identificado por una **huella estable**: `issue_type | target | field`, la misma identidad que compara el [informe con marca propia](/es/pro/reports). La diferencia es una comparación de dos conjuntos fijos y funciona entre dos ejecuciones cualesquiera, no solo consecutivas.

### Casos particulares {#edge-cases-handled-honestly}

- **Una página deja de formar parte de los objetivos.** Sus problemas no vuelven a analizarse, siguen abiertos y se incluyen en las instantáneas posteriores. Se muestran como todavía abiertos, no como resueltos por dejar de comprobarlos.
- **Se desactiva una comprobación entre scans.** Deja de emitir problemas, el ciclo los marca como resueltos y salen del conjunto abierto. Se muestran como **fixed**, lo que refleja el estado del escáner. No hay una distinción por problema entre corregir el contenido y desactivar la comprobación.
- **Primer scan tras actualizar.** Las ejecuciones anteriores sin instantánea no sirven de referencia. La primera con instantánea muestra el estado inicial sin comparación, en lugar de presentar todo como nuevo. La comparación funciona desde la segunda.

### En el panel {#on-the-dashboard}

El widget **What changed since the last scan** del [panel SEO](/es/pro/installation) compara las dos últimas ejecuciones completadas. Muestra totales new / regressed / fixed y los principales problemas de cada grupo por impacto. Hasta que existan dos instantáneas, muestra una nota de referencia inicial.

## Orden por impacto {#impact-ordering}

Cada grupo se ordena mediante una puntuación de **impacto**:

```
impact = severity_weight × page_importance
```

- **severity_weight** reutiliza las [reglas publicadas de puntuación](/es/pro/scoring): critical vale `40`, warning `15` y notice `5`. Utiliza la valoración de gravedad del producto sin inventar otra escala.
- **page_importance** se basa en la **demanda de búsqueda observada**, es decir, las impresiones de la página en [Search Console](/es/pro/search-console):

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

Las impresiones se escalan logarítmicamente y se normalizan respecto a la página con más impresiones. Una página con diez veces más tráfico no recibe diez veces más importancia, y la fórmula funciona tanto en un blog pequeño como en un catálogo grande. `<priority>` del sitemap es una **señal secundaria de poco peso**: suele no estar definida o tener un valor uniforme. Influye si configuraste prioridades por tipo en `seo.sitemap.models`.

**Sin Search Console**, historial sincronizado ni prioridades configuradas, `page_importance` vale `1` para todas las páginas y el orden depende exclusivamente de la **gravedad**. Sincroniza el [historial GSC](/es/pro/search-console#historical-metrics) con `seo-pro:gsc-sync` para ponderar por demanda.

Ajusta pesos y ventana en `seo-pro.scan.delta.impact`.

## Programar un scan {#scheduling-a-scan}

El paquete **no programa nada por defecto**. Actívalo:

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

O utiliza una expresión cron completa, que tiene prioridad sobre `frequency`:

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

El paquete registra `seo-pro:scan` en el scheduler de Laravel con `withoutOverlapping`, que evita ejecuciones simultáneas del **comando programado**. Ese bloqueo no cubre toda la vida de los jobs en cola. El registro solo ocurre en contexto de consola/scheduler y no añade trabajo a las peticiones web.

::: warning Necesita un scheduler activo
La programación no funciona si el scheduler de Laravel no está ejecutándose. Usa el cron habitual `* * * * * php artisan schedule:run` o `php artisan schedule:work` en desarrollo. Consulta [Producción](/es/pro/production#scheduler).
:::

Si prefieres registrarlo tú, deja `schedule.enabled` desactivado y programa el comando en tu kernel de consola. La comparación y el resumen siguen funcionando:

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` ejecuta el scan en el propio proceso en lugar de encolar un job por objetivo. Puede servir para un sitio pequeño sin worker; evita ese modo en producción.

## Resumen por correo {#summary-e-mail}

Puedes recibir un correo al terminar un scan programado, con un resumen HTML de problemas nuevos, reaparecidos y resueltos, ordenados por impacto:

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

Reutiliza la marca y configuración de correo de los [informes](/es/pro/reports): nombre de agencia, logo y color. Si no defines destinatarios específicos, usa los de los informes. `only_on_change` omite el correo cuando no hay cambios; el primer scan de referencia siempre se envía si la notificación está habilitada.

Solo se envía para una ejecución iniciada con `--notify`, que el scheduler añade cuando `notify.enabled` está activo. Un `seo-pro:scan` manual **sin `--notify` no envía correo**.

::: tip Otro canal
Para Slack, un webhook o un resumen personalizado, escucha `Rankbeam\Seo\Pro\Events\SeoScanCompleted`. Se emite una vez por ejecución finalizada e incluye esa ejecución. Puedes construir la comparación con `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` y enviarla por el canal elegido.
:::

## Retención {#retention}

Las instantáneas se eliminan en cascada junto a su ejecución. [`seo-pro:scan-prune`](/es/pro/production#scheduler) aplica la retención sin añadir otra tarea programada. Una ejecución solo se limpia cuando no tiene problemas abiertos, para conservar la referencia necesaria para comparar.

Desactiva las instantáneas y, con ellas, la comparación y el resumen, mediante `seo-pro.scan.delta.snapshot => false`.
