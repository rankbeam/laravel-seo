---
description: "Notifica a los buscadores cuando publicas o actualizas una URL. Pro envía a api.indexnow.org, que comparte la notificación con los motores participantes. Desactivado por defecto."
---

# IndexNow: notificar URL al publicar {#indexnow-—-push-on-publish-indexing}

**IndexNow** permite avisar a los buscadores al publicar o actualizar una URL, sin esperar a que un rastreador descubra el cambio. Pro envía a `api.indexnow.org`, que **propaga la notificación a los motores participantes** en una sola llamada, sin envíos separados por buscador. La [FAQ oficial](https://www.indexnow.org/faq) enumera Amazon, Bing, Naver, Seznam, Yandex y Yep. Una notificación no garantiza la indexación.

Está **desactivado por defecto**. No se hacen llamadas de red hasta que lo habilites y se envíe una URL.

## Preparación {#setup}

### 1. Generar una clave {#_1-generate-a-key}

IndexNow utiliza una **clave** para verificar el control del host. Pro acepta entre 8 y 128 caracteres de `[a-f0-9-]`; una cadena hexadecimal de 32 caracteres es adecuada. Genérala una vez, mantenla estable y configúrala en el entorno:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip La clave se lee desde la configuración y funciona con `config:cache`
A diferencia de las credenciales Search Console, la clave IndexNow **no es secreta**: se sirve públicamente en `/{key}.txt` para demostrar el control del host. Pro la resuelve desde `indexnow.key`, cuyo valor predeterminado es `env('SEO_PRO_INDEXNOW_KEY')`. Después de `config:cache`, Laravel deja de cargar `.env`; las variables que solo existen allí ya no están disponibles para llamadas directas a `env()`, aunque sí las variables reales del proceso. Al leerla desde configuración, su valor queda capturado al crear la caché. Por tanto, **al rotar la clave debes volver a ejecutar `php artisan config:cache`**. La clave no se registra en logs. Consulta [Servidores con configuración cacheada](#config-cached-servers) si el archivo devuelve 404 en producción.
:::

### 2. Servir el archivo de clave {#_2-serve-the-key-file}

IndexNow obtiene `https://{host}/{key}.txt`, que contiene solo la clave, para verificar el host. Con `route` activo, su valor predeterminado, **Pro lo sirve automáticamente**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Solo responde la ruta de la clave configurada. Las demás rutas interceptadas devuelven 404, igual que toda la ruta si IndexNow está desactivado. Si prefieres alojar el archivo tú mismo o en un CDN, desactiva `route` e indica su URL en `key_location`.

## Enviar URL {#submitting-urls}

### Automáticamente al guardar {#automatically-on-save-the-push-on-publish-path}

Añade el trait al modelo y activa `auto_submit`. Cada guardado encola el envío de `getUrlForSEO()`:

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

El trait respeta una condición de publicación. Implementa `shouldSubmitToIndexNow(): bool` para controlarla; si no existe, utiliza el atributo `is_published`, y si tampoco existe, envía al guardar siempre. El envío se **encola**, para no esperar a la red durante el guardado con un backend de cola asíncrono.

### Manualmente {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` encola por defecto. Pasa `queue: false` para ejecutarlo en el propio proceso.

### Desde la línea de comandos {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Solo URL del mismo host
Cada URL debe usar `http(s)` y pertenecer al `host` configurado. Las demás se **descartan y cuentan sin enviarse**. Solo puedes enviar URL del host que controlas; el endpoint también rechazaría una discrepancia. Las listas superiores a `max_urls_per_request`, 10.000 según el límite del protocolo, se dividen automáticamente.
:::

## Configuración {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## Reintentos {#how-retries-work}

`SubmitToIndexNowJob` reintenta las respuestas `429`, `5xx` y los tiempos de espera agotados, aplicando `backoff` hasta el límite `tries`. Los errores permanentes del cliente, `400`/`403`/`422`, como clave incorrecta o host distinto, se registran y **detienen** el envío sin gastar más reintentos. `200` y `202`, recibido o pendiente de comprobar la clave, se consideran correctos.

En producción, asigna una **cola dedicada** para que un endpoint lento no retrase trabajo de los usuarios:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Resolver problemas {#troubleshooting}

### Servidores con configuración cacheada {#config-cached-servers}

Si `/{key}.txt` devuelve 404 o los envíos no hacen nada aunque `indexnow.enabled` sea `true`, comprueba si la clave existe solo en `.env` y el servidor usa `config:cache`. Laravel no analiza ese archivo con configuración cacheada; una lectura directa mediante `env('SEO_PRO_INDEXNOW_KEY')` puede devolver null, impedir el registro de la ruta y rechazar los envíos por falta de configuración.

La configuración predeterminada resuelve `indexnow.key` mediante `env(...)` al crear la caché, así que funciona en una instalación normal. El problema aparece si publicaste la configuración y eliminaste ese valor predeterminado, o usas un nombre `key_env` personalizado que solo existe en `.env`. Dos soluciones:

1. **Conservar la clave en configuración**, recomendado. Mantén `indexnow.key` como `env('SEO_PRO_INDEXNOW_KEY')` o establece un literal y ejecuta de nuevo `php artisan config:cache`. Al rotarla, reconstruye la caché.
2. **Inyectar una variable real de entorno**. Define `SEO_PRO_INDEXNOW_KEY` en el proceso o sistema operativo: `env[...]` del pool PHP-FPM, `Environment=` de systemd o ajustes de la plataforma. No basta con `.env`. Las variables reales siguen siendo accesibles con configuración cacheada.

Ejecuta `php artisan seo:doctor`: si detecta este estado, informa de que IndexNow está habilitado pero no se resuelve una clave válida e indica la corrección. Pro también registra un aviso una vez por proceso si arranca con configuración cacheada y una clave ilegible.

::: tip Google
Google **no participa** en IndexNow. Para Google, utiliza [Search Console](/es/pro/search-console) y un sitemap actualizado.
:::
