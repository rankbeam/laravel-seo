---
description: "Panel Google Search Console de solo lectura: consultas y páginas con impresiones, clics, CTR y posición, vinculadas a los objetivos del escáner. Desactivado por defecto."
---

# Search Console: solo lectura {#search-console-read-only}

Un panel Google Search Console de **solo lectura** con consultas y páginas principales, **impresiones, clics, CTR y posición media**, vinculadas a las páginas que conoce el escáner. Permite ver en un mismo lugar qué páginas tienen problemas y pierden impresiones. Está **desactivado por defecto**.

Tres principios definen su funcionamiento:

- **Solo lectura.** La integración solicita un único ámbito OAuth, `webmasters.readonly`, fijado en el paquete. Lee Search Analytics; no envía sitemaps, solicita indexación ni modifica Search Console. No hay una opción para ampliar el ámbito.
- **Tu propiedad y tus credenciales.** Las peticiones van desde tu servidor directamente a Google con tu cuenta de servicio o credencial OAuth. Rankbeam no actúa como intermediario, mide ni revende el consumo, y el paquete no envía telemetría.
- **Errores dentro de la vista.** Credenciales ausentes, errores 403, cuotas o tiempos de espera muestran un mensaje sin interrumpir el panel. El comando de sincronización histórica informa de los fallos y deja de consultar los días siguientes, como se explica abajo.

## Qué incluye {#what-you-get}

- **Páginas que requieren atención**: páginas con problemas de scan abiertos que siguen recibiendo tráfico de búsqueda, ordenadas por mayor número de impresiones entre las páginas afectadas. Ayuda a priorizar correcciones.
- **Páginas principales** y **consultas principales**: las tablas habituales de Search Analytics.

En Filament aparece como página **Search Console** del grupo SEO, solo si está habilitada. Sin panel, las mismas métricas están en `seo-pro:search-console` y `SeoPro::searchConsole()`.

## Preparación {#setup}

Necesitas una credencial de Google con acceso de lectura a la propiedad Search Console. Se admiten dos modos; una **cuenta de servicio** suele ser la opción más sencilla para un servidor.

### Cuenta de servicio: recomendado {#service-account-recommended}

1. Activa la **Search Console API** en Google Cloud, crea una **cuenta de servicio** y descarga su clave JSON.
2. En Search Console → *Configuración → Usuarios y permisos*, añade su correo (`…@….iam.gserviceaccount.com`). El acceso Restringido basta para lectura.
3. Configura la clave y la propiedad:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

Si omites `SEO_PRO_GSC_SITE_URL`, se deduce una propiedad de prefijo URL a partir de `app.url`.

### OAuth con refresh token {#oauth-offline-refresh-token}

Si tienes un cliente OAuth y un **refresh token** de larga duración, preferiblemente autorizado solo para `webmasters.readonly`, configúralos así. Cada renovación solicita ese ámbito. El paquete rechaza el token devuelto salvo que la respuesta confirme explícitamente el ámbito de solo lectura exacto; no supone que Google reduzca siempre un permiso más amplio.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Publicar la migración de tokens {#publish-the-token-migration}

La caché cifrada de tokens de acceso utiliza `seo_gsc_tokens`. Publica y migra una vez:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Comprueba después la configuración con `php artisan seo:doctor`. Indica si Search Console está activado y configurado, sin acceder a la red ni imprimir secretos.

## Uso sin panel {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## Métricas históricas {#historical-metrics}

El panel y los comandos anteriores leen una **ventana móvil en directo**, almacenada por Search Console. Para consultar un historial propio por día, ejecuta la sincronización, que guarda métricas diarias por consulta y por página en `seo_gsc_metrics`:

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **La primera ejecución recupera** `sync.backfill_days`, 90 por defecto. Search Console conserva aproximadamente 16 meses; aumenta el valor si necesitas más historial. Las siguientes reanudan desde la última fecha guardada y vuelven a consultar `sync.overlap_days` al final para recoger datos recientes que se consolidaron más tarde. La ventana termina siempre tres días antes por el retraso de los datos.
- **Es idempotente.** Hace upsert por `(date, dimension, key)`, de modo que se puede repetir. Si falla un día, por ejemplo por cuota, la ejecución se detiene e informa de cuántas filas guardó; la siguiente reanuda desde el progreso disponible.
- **Qué alimenta.** Cuando la tabla cubre ambos períodos, los cambios de Search Console del [informe](/es/pro/reports) pasan a comparar períodos reales de igual duración, en lugar de comparar con la instantánea del informe anterior. También permite análisis adicionales de consultas.

Solo se almacenan métricas agregadas: texto de consulta, URL de página y clics, impresiones, CTR y posición por día. No se solicitan ni guardan datos por usuario o petición.

## Tratamiento de datos y seguridad {#data-handling-security}

- **Ámbito de solo lectura comprobado.** El JWT de cuenta de servicio y la renovación OAuth solicitan `webmasters.readonly`. El paquete rechaza respuestas OAuth con ámbito ausente o más amplio. Usa una credencial autorizada solo para lectura. No contiene llamadas de modificación de Search Console.
- **Credenciales en el entorno.** La clave de cuenta de servicio, secreto OAuth y refresh token se leen en cada llamada desde variables de entorno cuyos nombres están configurados, igual que la clave de IA. `config:cache` no escribe sus valores en `bootstrap/cache/config.php`. Deben estar disponibles como variables del proceso si la caché impide cargar `.env`.
- **Tokens cifrados en reposo.** El token de acceso de corta duración se guarda cifrado con la clave de la aplicación en `seo_gsc_tokens` y se reutiliza hasta cerca de su caducidad. No se intercambia en cada vista. La credencial de larga duración permanece en el entorno, nunca en la base.
- **Peticiones protegidas contra SSRF.** Tanto el intercambio de tokens como Search Analytics pasan por `SsrfGuard`: solo HTTPS, host con dirección pública y redirecciones desactivadas, para impedir saltos a servicios internos.
- **Secretos fuera de los logs.** No se registran tokens, claves ni cabeceras de autenticación. Los errores muestran un mensaje de Google saneado y de longitud limitada.
- **Métricas en caché local** durante `seo-pro.search_console.cache_ttl` segundos, 30 minutos por defecto. El panel y comando en directo no persisten más datos que esa caché y el token cifrado. Solo `seo-pro:gsc-sync`, ejecutado expresamente, guarda métricas agregadas diarias en `seo_gsc_metrics`, sin datos por usuario.

## Referencia de configuración {#configuration-reference}

Todas las claves pertenecen a `config/seo-pro.php` → `search_console`:

| Clave | Valor predeterminado | Finalidad |
| --- | --- | --- |
| `enabled` | `false` | Interruptor principal, `SEO_PRO_GSC_ENABLED` |
| `connection` | `service_account` | `service_account` u `oauth` |
| `site_url` | Deducido de `app.url` | Propiedad: `https://example.com/` o `sc-domain:example.com` |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Nombre** de la variable con el JSON de clave o su ruta |
| `oauth.client_id` | — | ID de cliente OAuth, no secreto |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Nombre** de la variable con el secreto de cliente |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Nombre** de la variable con el refresh token |
| `default_days` | `28` | Ventana del informe, terminada tres días antes por el retraso de GSC |
| `row_limit` | `100` | Filas principales por informe; máximo API 25.000 |
| `cache_ttl` | `1800` | Segundos de caché del informe consultado |
| `sync.backfill_days` | `90` | Días recuperados en el primer `gsc-sync` con tabla vacía |
| `sync.overlap_days` | `2` | Días finales que se vuelven a consultar por consolidación tardía |
| `sync.row_limit` | `5000` | Máximo solicitado por día y dimensión durante la sincronización |
