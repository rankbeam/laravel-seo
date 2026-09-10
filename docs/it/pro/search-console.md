---
description: "Un pannello Google Search Console in sola lettura: query e pagine con impressioni, clic, CTR e posizione, associate alle pagine già note allo scanner. Disattivato per impostazione predefinita."
---

# Search Console (sola lettura) {#search-console-read-only}

Un pannello Google Search Console in **sola lettura** con query e pagine principali, **impressioni, clic, CTR e posizione media**. I dati vengono associati alle pagine già note allo scanner, così puoi vedere nello stesso posto se una pagina ha problemi aperti e riceve traffico dalla ricerca. È **disattivato per impostazione predefinita**.

L'integrazione segue tre criteri:

- **Sola lettura.** Il pacchetto richiede un unico ambito OAuth, `webmasters.readonly`, fissato nel codice. Legge Search Analytics: non invia sitemap, non richiede l'indicizzazione e non modifica Search Console. Nessuna opzione di configurazione amplia l'ambito richiesto.
- **La tua proprietà, le tue credenziali.** Le richieste partono dal **tuo server** e arrivano direttamente a Google, con le **tue** credenziali di account di servizio o OAuth. Nessun proxy, rivendita o conteggio a consumo da parte di Rankbeam; il pacchetto non invia telemetria.
- **Gli errori vengono mostrati nel contesto.** Credenziali mancanti, risposte 403, limiti di quota e timeout producono un messaggio, senza interrompere il rendering della pagina. Il comando di sincronizzazione storico segnala invece gli errori e interrompe il recupero dei giorni successivi, come descritto sotto.

## Cosa include {#what-you-get}

- **Pages needing attention**: pagine con **problemi di scansione aperti** che **ricevono ancora traffico di ricerca**, ordinate per impressioni decrescenti. È un punto di partenza per stabilire quali problemi esaminare prima.
- **Top pages** e **Top queries**: le tabelle principali di Search Analytics.

Nel dashboard Filament trovi la pagina **Search Console** nel gruppo di navigazione *SEO*, visibile solo quando l'integrazione è attiva. Senza Filament, gli stessi dati sono disponibili tramite `seo-pro:search-console` e `SeoPro::searchConsole()`.

## Configurazione iniziale {#setup}

Serve una credenziale Google autorizzata a leggere la proprietà Search Console. Sono supportate due modalità; per un server, un **account di servizio** è la soluzione più semplice.

### Account di servizio (consigliato) {#service-account-recommended}

1. In Google Cloud, abilita la **Search Console API**, crea un **account di servizio** e scarica la chiave JSON.
2. In Search Console → *Impostazioni → Utenti e autorizzazioni*, aggiungi come utente l'indirizzo dell'account di servizio (`…@….iam.gserviceaccount.com`). L'accesso con restrizioni è sufficiente per la lettura.
3. Indica al pacchetto la chiave e la proprietà:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

Se ometti `SEO_PRO_GSC_SITE_URL`, il pacchetto ricava una proprietà con prefisso URL da `app.url`.

### OAuth (refresh token offline) {#oauth-offline-refresh-token}

Se hai già un client OAuth e un **refresh token** di lunga durata, usa preferibilmente un token ottenuto con il solo ambito `webmasters.readonly`. Il pacchetto richiede questo ambito anche a ogni rinnovo e rifiuta la risposta se non conferma esattamente il solo ambito di lettura:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Pubblicare la migrazione dei token {#publish-the-token-migration}

La cache cifrata degli access token usa la tabella `seo_gsc_tokens`. Pubblica ed esegui la migrazione una volta:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Verifica poi la configurazione con `php artisan seo:doctor`: indica se Search Console è attiva e configurata, senza richieste di rete né stampa di segreti.

## Uso senza Filament {#headless-usage}

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

## Metriche storiche {#historical-metrics}

Il pannello e il comando descritti sopra leggono una **finestra temporale mobile**; Search Console resta l'unico archivio dei dati. Per conservare uno **storico giornaliero** interrogabile nei periodi disponibili, esegui la sincronizzazione: salva le metriche per giorno e per query o pagina in `seo_gsc_metrics`.

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

- **La prima esecuzione recupera** `sync.backfill_days` giorni, 90 per impostazione predefinita. Search Console conserva circa 16 mesi: aumenta il valore per recuperarne di più. Le esecuzioni successive **ripartono dall'ultima data salvata**, recuperando di nuovo gli ultimi `sync.overlap_days` giorni per includere le revisioni dei dati recenti. La finestra termina sempre tre giorni prima della data corrente, per tenere conto del ritardo dei dati.
- **Sincronizzazione idempotente.** Le righe usano un upsert su `(date, dimension, key)`: puoi rieseguire il comando. Se un giorno fallisce, per esempio per una quota esaurita, il comando si ferma e indica quante righe ha salvato; la successiva esecuzione riprende dal punto raggiunto.
- **Dove vengono usate.** Quando la tabella copre entrambi i periodi, le variazioni Search Console del [report personalizzabile](/it/pro/reports) confrontano il periodo scelto con quello precedente di uguale durata. Non dipendono più dallo snapshot del report precedente. Lo storico costituisce anche la base per ulteriori analisi delle query.

Vengono salvate solo metriche aggregate: testo della query, URL della pagina e quattro metriche giornaliere, clic, impressioni, CTR e posizione. Non vengono recuperati né scritti dati per singolo utente o singola richiesta.

## Trattamento dei dati e sicurezza {#data-handling-security}

- **Ambito richiesto di sola lettura.** Il pacchetto richiede solo `webmasters.readonly`, sia nel JWT dell'account di servizio sia nella richiesta di rinnovo OAuth. Il token OAuth viene accettato solo se la risposta conferma esattamente il solo ambito richiesto; risposte senza ambito o con permessi più ampi vengono rifiutate. Usa preferibilmente credenziali autorizzate con questo solo ambito. Il pacchetto non chiama endpoint che modificano Search Console.
- **Le credenziali restano nell'ambiente.** La chiave dell'account di servizio, il segreto OAuth e il refresh token vengono letti al momento dell'uso dalle variabili d'ambiente di cui la configurazione contiene il **nome**, come per la chiave AI. `php artisan config:cache` non li copia quindi in `bootstrap/cache/config.php`. Devono essere disponibili all'ambiente del processo anche con la configurazione in cache.
- **Token cifrati a riposo.** L'access token a breve durata viene salvato **cifrato** con la chiave dell'app in `seo_gsc_tokens` e riutilizzato finché non si avvicina alla scadenza. Lo scambio del token non avviene a ogni visualizzazione. La credenziale di lunga durata resta nell'ambiente, senza essere salvata nel database.
- **Protezione SSRF sulle richieste.** Lo scambio del token e le chiamate Search Analytics passano entrambi da `SsrfGuard`: solo HTTPS, host risolto a un indirizzo pubblico e redirect disabilitati, per evitare che un redirect porti a un servizio interno.
- **I segreti non vengono registrati nei log.** Access token, chiavi e header di autenticazione non vengono scritti nei log. Gli errori API mostrano solo il messaggio Google ripulito e limitato in lunghezza.
- **Cache locale delle metriche** per `seo-pro.search_console.cache_ttl` secondi, 30 minuti per impostazione predefinita. Il pannello non richiama l'API a ogni rendering. Pannello e comando di lettura non conservano altro oltre alla cache e al token cifrato. Solo `seo-pro:gsc-sync`, eseguito esplicitamente, salva lo storico in `seo_gsc_metrics`: aggregati giornalieri per query e pagina, senza dati individuali.

## Riferimento della configurazione {#configuration-reference}

Tutte le chiavi si trovano in `config/seo-pro.php` → `search_console`:

| Chiave | Valore predefinito | Funzione |
| --- | --- | --- |
| `enabled` | `false` | Interruttore generale (`SEO_PRO_GSC_ENABLED`). |
| `connection` | `service_account` | `service_account` oppure `oauth`. |
| `site_url` | ricavato da `app.url` | Proprietà (`https://example.com/` oppure `sc-domain:example.com`). |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Nome** della variabile con il JSON della chiave o il suo percorso. |
| `oauth.client_id` | — | ID del client OAuth, non segreto. |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Nome** della variabile con il segreto del client. |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Nome** della variabile con il refresh token. |
| `default_days` | `28` | Finestra del report; termina tre giorni fa per il ritardo dei dati GSC. |
| `row_limit` | `100` | Prime N righe per report; massimo API 25000. |
| `cache_ttl` | `1800` | Durata in secondi della cache di un report recuperato. |
| `sync.backfill_days` | `90` | Giorni recuperati dal primo `gsc-sync`, con tabella vuota. |
| `sync.overlap_days` | `2` | Giorni finali recuperati di nuovo per includere revisioni tardive. |
| `sync.row_limit` | `5000` | Massimo di righe richieste per giorno e dimensione. |
