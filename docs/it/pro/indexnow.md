---
description: "Notifica la pubblicazione o modifica di un URL ai motori aderenti a IndexNow. Pro invia all’endpoint condiviso api.indexnow.org. La funzione è disattivata per impostazione predefinita."
---

# IndexNow: notifiche alla pubblicazione {#indexnow-—-push-on-publish-indexing}

**IndexNow** consente di notificare ai motori di ricerca la pubblicazione o modifica di un URL, senza aspettare che un crawler scopra il cambiamento. Pro invia all’endpoint condiviso `api.indexnow.org`, che distribuisce la notifica ai [partecipanti al protocollo](https://www.indexnow.org/faq), tra cui Amazon, Bing, Yandex, Naver, Seznam e Yep, con una sola chiamata. Non vengono effettuate chiamate separate per ogni motore. La notifica non garantisce indicizzazione.

La funzione è **disattivata per impostazione predefinita**. Non ci sono richieste di rete finché non la abiliti e invii un URL.

## Configurazione iniziale {#setup}

### 1. Generare una chiave {#_1-generate-a-key}

IndexNow verifica il controllo dell’host tramite una **chiave**. Pro accetta da 8 a 128 caratteri dell’insieme `[a-f0-9-]`; una stringa esadecimale di 32 caratteri è adatta. Generala una volta, mantienila stabile e configurala nell’ambiente:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip La chiave passa dalla configurazione e resta disponibile con `config:cache`
A differenza delle credenziali Search Console, la chiave IndexNow **non è un segreto**: viene servita pubblicamente in `/{key}.txt` per verificare il controllo dell’host. Pro la legge quindi dalla configurazione, `indexnow.key`, il cui valore predefinito è `env('SEO_PRO_INDEXNOW_KEY')`. Le variabili definite soltanto in `.env` non sono disponibili tramite `env()` fuori dalla configurazione quando è attiva `config:cache`; una lettura al momento della chiamata potrebbe quindi perderle in produzione. La configurazione le acquisisce quando viene creata la cache. **Dopo aver cambiato chiave, riesegui `php artisan config:cache`.** La chiave non viene scritta nei log. Se il file restituisce 404 in produzione, vedi [Server con configurazione in cache](#config-cached-servers).
:::

### 2. Servire il file della chiave {#_2-serve-the-key-file}

IndexNow scarica `https://{host}/{key}.txt`, contenente soltanto la chiave, per verificare l’host. Con l’opzione `route` attiva, come per impostazione predefinita, **Pro lo serve automaticamente**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

La rotta risponde solo al percorso della chiave configurata; gli altri percorsi intercettati `*.txt` restituiscono 404. Quando IndexNow è disabilitato, anche la rotta della chiave restituisce 404. Se preferisci ospitare il file direttamente o su una CDN, disattiva `route` e imposta `key_location` sul suo URL.

## Inviare URL {#submitting-urls}

### Automaticamente al salvataggio {#automatically-on-save-the-push-on-publish-path}

Aggiungi il trait al modello e abilita `auto_submit`. Ogni salvataggio idoneo accoda l’invio dell’URL restituito da `getUrlForSEO()`:

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

Il trait rispetta un controllo di pubblicazione. Implementa `shouldSubmitToIndexNow(): bool` per definirlo; altrimenti usa l’attributo `is_published`, se presente, oppure invia a ogni salvataggio. L’invio è sempre **in coda**, quindi il salvataggio del modello non aspetta la rete.

### Manualmente {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` accoda per impostazione predefinita. Passa `queue: false` per eseguire nello stesso processo.

### Dalla riga di comando {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Solo URL dello stesso host
Ogni URL deve usare `http(s)` **e** appartenere all’`host` configurato. Gli altri vengono **scartati**, conteggiati ma mai inviati; l’endpoint rifiuterebbe comunque un host diverso. Gli elenchi che superano `max_urls_per_request`, pari a 10.000 nel limite del protocollo, vengono suddivisi automaticamente.
:::

## Configurazione {#configuration}

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

## Nuovi tentativi {#how-retries-work}

`SubmitToIndexNowJob` riprova gli errori temporanei: `429`, `5xx` e timeout seguono `backoff` fino al limite `tries`. Errori permanenti del client come `400`, `403` e `422`, per esempio chiave errata o host diverso, vengono registrati e **interrompono** i tentativi. `200` e `202` indicano rispettivamente ricezione e attesa della verifica della chiave; entrambi sono trattati come invii riusciti.

In produzione assegna al job una **coda dedicata**, così un endpoint lento non ritarda altre operazioni dell’applicazione:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Risoluzione dei problemi {#troubleshooting}

### Server con configurazione in cache {#config-cached-servers}

Se `/{key}.txt` restituisce 404 in produzione, o gli invii non partono nonostante `indexnow.enabled` sia `true`, controlla che la chiave non esista **soltanto in `.env`** su un server con `php artisan config:cache`. Laravel non rilegge `.env` quando la configurazione è in cache: una lettura runtime con `env('SEO_PRO_INDEXNOW_KEY')` può restituire `null`, impedendo la registrazione della rotta e lasciando gli invii nello stato “not configured”.

La configurazione predefinita legge `indexnow.key` da `env(...)` durante la creazione della cache e funziona normalmente. Il problema si presenta se hai **pubblicato la configurazione e rimosso quel valore predefinito**, oppure usi un **nome `key_env` personalizzato presente soltanto in `.env`**. Puoi correggerlo in due modi:

1. **Conserva la chiave nella configurazione**, scelta consigliata. Lascia `indexnow.key` impostato a `env('SEO_PRO_INDEXNOW_KEY')`, oppure a un valore letterale, e riesegui `php artisan config:cache`. Ogni successiva rotazione richiede di ricreare la cache.
2. **Definisci una vera variabile d’ambiente del processo.** Imposta `SEO_PRO_INDEXNOW_KEY` nel pool PHP-FPM con `env[...]`, in systemd con `Environment=` o nelle impostazioni della piattaforma, anziché soltanto in `.env`. Le variabili del sistema restano leggibili con la configurazione in cache.

Esegui `php artisan seo:doctor`: quando rileva questa situazione, mostra **“IndexNow is enabled but no valid key resolves”** insieme alla correzione. Pro registra anche un avviso una volta per processo se l’app si avvia con configurazione in cache e chiave non leggibile.

::: tip Google
Google non figura tra i partecipanti IndexNow elencati nel protocollo. Per Google usa l’integrazione [Search Console](/it/pro/search-console) e una sitemap aggiornata.
:::
