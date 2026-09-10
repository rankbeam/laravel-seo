---
description: "Eseguire Pro in produzione: code dedicate, scheduler, retry, recupero, conservazione e telemetria. Una configurazione indipendente da Filament, usata in un'installazione di circa 900 pagine."
---

# Configurazione in produzione {#production-setup}

Le attività ricorrenti di Pro, scansioni del sito, crawler dei link, salvataggio differito facoltativo dei contatori dei redirect e pulizia dei 404, usano code e scheduler Laravel. Questa guida raccoglie la configurazione per gestirle: code dedicate, programmazione, retry, recupero, conservazione e telemetria. È basata sulla configurazione descritta nel caso d'uso di un'installazione da circa 20.000 visite giornaliere e 900 pagine, da adattare alle risorse della tua app.

Tutto è **indipendente da Filament**: motore, comandi, code e telemetria funzionano allo stesso modo con o senza pannello. Filament aggiunge le schermate, senza cambiare programmazione o elaborazione.

[[toc]]

## Ordine di attivazione {#safe-rollout-order}

Segui questi passaggi, verificando ciascuno prima del successivo:

1. **Installa**: pubblica configurazione e migrazioni, poi eseguile:

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` pubblica `config/seo-pro.php` e le migrazioni Pro, poi esegue `migrate`. Le migrazioni Pro sono **da pubblicare**: il pacchetto non le carica automaticamente. Questo passaggio crea lo schema necessario dopo `composer require`. Il comando è idempotente; puoi rieseguirlo. `--force` sovrascrive i file pubblicati, quindi usalo tenendo conto delle personalizzazioni; `--no-migrate` pubblica senza eseguire le migrazioni.

2. **Registra i target** in un service provider, per esempio `AppServiceProvider::boot()`:

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Verifica** la configurazione prima di attivare il lavoro in background:

   ```bash
   php artisan seo:doctor
   ```

   Esamina gli avvisi: ciascuno indica il comando o la configurazione da correggere. In CI, usa `--json` e gli ID stabili dei controlli.

4. **Configura code e scheduler**, come sotto, poi avvia i worker e aggiungi il cron per `schedule:run`.

5. **Attiva per ultime le funzioni facoltative**: crawler dei link, assistenza AI e Search Console sono disattivati per impostazione predefinita. Il crawler richiede le migrazioni del primo passaggio e un worker dedicato.

## Code dedicate per tipo di lavoro {#dedicated-queues-per-workload}

Una scansione lunga non dovrebbe precedere nella stessa coda email e notifiche per gli utenti. Assegna a ciascun carico SEO una coda e un worker propri.

La scansione delle pagine e il crawler leggono queste impostazioni:

| Carico | Configurazione | Variabile d'ambiente | Coda predefinita |
|---|---|---|---|
| Job di scansione delle pagine | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | coda predefinita dell'app |
| Job del crawler dei link | `seo-pro.broken_links.queue.name` e `.connection` | `SEO_PRO_BROKEN_LINKS_QUEUE` e `_CONNECTION` | `seo-broken-links` |

### Esempio Redis per la produzione {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Avvia un worker per coda, ciascuno come processo o programma Supervisor separato:

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

Il `--timeout` del worker del crawler deve superare `seo-pro.broken_links.batch.hard_time_budget_seconds`, predefinito 180, più il timeout HTTP e un margine per il completamento del batch. Il job imposta anche il proprio `$timeout`: allinea la configurazione operativa a questo limite. Per il crawler usa `--tries=1`: la continuazione successiva o `seo-pro:broken-links-recover` gestiscono un job interrotto, senza retry aggiuntivi della coda.

`seo:doctor` mostra la coda di ogni carico e avvisa quando la connessione risolta è `sync`, che eseguirebbe il lavoro nel processo chiamante.

## Scheduler {#scheduler}

Le nuove app Laravel 11, 12 e 13 definiscono normalmente lo scheduler in **`routes/console.php`**. Se l'app conserva `app/Console/Kernel.php` da una struttura precedente, puoi inserire le stesse definizioni nel suo metodo `schedule()`. Aggiungi un cron di sistema che esegua lo scheduler ogni minuto:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Registra quindi i comandi ricorrenti:

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

Cadenze consigliate:

| Comando | Cadenza | Motivo |
|---|---|---|
| `seo:sitemap` | giornaliera | Aggiornare la sitemap dai contenuti correnti |
| `seo-pro:scan` | settimanale, giornaliera se i contenuti cambiano spesso | Ricontrollare tutti i target |
| `seo-pro:scan-recover` | ogni ora | Chiudere esecuzioni abbandonate da un worker |
| `seo-pro:scan-prune` | giornaliera | Applicare la conservazione delle scansioni |
| `seo-pro:redirects-flush-hits` | ogni 5 minuti, solo con `redirects.hits.flush_immediately=false` | Salvare nel database i contatori accumulati in cache |
| `seo-pro:404-prune` | giornaliera | Applicare conservazione e tetto di righe al log 404 |
| `seo-pro:404-recheck` | giornaliera | Ricontrollare i percorsi 404 aperti e segnare quelli tornati a 200 |
| `seo-pro:broken-links-scan` | settimanale | Ricontrollare i link; la conferma usa più scansioni |
| `seo-pro:broken-links-recover` | ogni ora | Chiudere scansioni del crawler abbandonate |
| `seo-pro:broken-links-prune` | giornaliera | Applicare la conservazione del crawler |

`seo-pro:scan` e `seo-pro:broken-links-scan` **accodano** il lavoro; sono i worker a eseguirlo. Recupero e pulizia vengono eseguiti direttamente dal comando.

::: tip Conferma dei link su più scansioni
Un link viene confermato come non funzionante dopo `seo-pro.broken_links.mark_broken_after_failures` **scansioni consecutive** fallite. Un esito positivo azzera il contatore. Con la soglia predefinita di tre, un solo disservizio transitorio non basta. A cadenza settimanale servono tre scansioni fallite: circa due settimane dalla prima osservazione e fino a circa tre dalla comparsa del problema. Aumenta la frequenza o riduci la soglia per una conferma più rapida.
:::

## Dimensionare i batch del crawler {#batch-tuning-broken-link-crawler}

Il crawler suddivide il lavoro in job limitati che accodano una continuazione. I valori predefiniti sono finiti; adattali alla capacità del sito e dei server raggiunti in `seo-pro.broken_links`:

| Chiave | Valore predefinito | Limite |
|---|---|---|
| `max_pages_per_run` | `2000` | Pagine recuperate nell'intera esecuzione; solo `null` rimuove esplicitamente il limite |
| `max_links_per_page` | `200` | Link controllati per pagina |
| `max_total_links` | `null` | Tetto globale facoltativo dei controlli dei link |
| `batch.max_pages_per_job` | `50` | Pagine per job in coda |
| `batch.max_links_per_job` | `1500` | Controlli dei link per job |
| `batch.hard_time_budget_seconds` | `180` | Dopo questo tempo il job non avvia **nuove richieste** e accoda una continuazione |
| `batch.dispatch_delay_seconds` | `1` | Ritardo tra job di continuazione |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Limiti temporali per richiesta |
| `http.max_response_bytes` | eredita `seo-pro.http.max_response_bytes` | Tetto applicato durante la lettura dei corpi delle risposte |
| `seed.max_response_bytes` | eredita il limite HTTP del crawler o condiviso | Byte XML o `.gz` della sitemap recuperati per gli URL iniziali |
| `seed.max_inflated_bytes` | eredita il limite seed, crawler o condiviso | Byte accettati dopo la decompressione di una sitemap `.gz` |
| `http.per_host_delay_ms` | `0` | Ritardo tra controlli sullo stesso host; aumentalo con `internal_and_external` |

Mantieni `batch.hard_time_budget_seconds` ben al di sotto del timeout del worker. Una richiesta già avviata segue `http.timeout`: prevedi quindi tempo del batch + timeout HTTP + margine.

Per `internal_and_external`, amplia `seo-pro.http.scope` oppure `seo-pro.http.allowed_hosts` affinché `SsrfGuard` consenta le destinazioni esterne, e aumenta `http.per_host_delay_ms` per ridurre la frequenza delle richieste a terzi. `seo:doctor` avvisa se l'ambito del crawler include l'esterno ma quello della protezione lo blocca.

## Horizon e Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Un programma per coda. Esempio di `/etc/supervisor/conf.d/app-workers.conf`:

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

`stopwaitsecs` deve superare il timeout effettivo dei job e del worker, così un riavvio ordinato lascia tempo al batch di terminare.

### Horizon {#horizon}

Se usi Horizon, definisci un supervisor per carico in `config/horizon.php` e affidagli la gestione dei processi:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Retry e gestione degli errori {#retry-failure-handling}

Il job di un target usa la propria politica di retry dalla configurazione, senza dipendere da `--tries` del worker:

| Chiave | Valore predefinito | Significato |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Tentativi per job del target |
| `seo-pro.scan.backoff` | `30` | Secondi tra tentativi |
| `seo-pro.scan.timeout` | `300` | Timeout del job; il lock contro le sovrapposizioni scade dopo timeout + 60 |

Quando esaurisce i tentativi, il job registra il target come **fallito** e permette alla scansione di terminare come `partial` o `failed`. Se il worker muore senza completare questa registrazione, interviene il recupero descritto sotto. I job falliti usano la tabella standard `failed_jobs`:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Programma anche `queue:prune-failed` per limitarne la crescita:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

Il crawler usa `--tries=1`. Un job interrotto viene gestito dalla continuazione successiva, quando la lease non riceve più aggiornamenti, oppure da `seo-pro:broken-links-recover`. Retry aggiuntivi della coda potrebbero duplicare il lavoro.

## Recupero {#recovery}

Se un worker termina durante un job senza aggiornare l'avanzamento, programma **ogni ora** questi due controlli:

- `seo-pro:scan-recover`: segna come fallite le scansioni delle pagine senza progressi da `seo-pro.scan.recovery.stuck_scan_timeout_hours`, predefinito 2 ore.
- `seo-pro:broken-links-recover`: segna come fallite le scansioni del crawler con lease non aggiornata da `seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, predefinito 2 ore, liberando lo spazio per una nuova esecuzione nello stesso ambito.

`seo:doctor` usa lo **storico recente dell'avanzamento**: segnala esecuzioni ferme e indica il comando di recupero. Non dimostra che cron sia effettivamente in esecuzione; descrive ciò che risulta dallo storico.

## Conservazione {#retention}

Limita la crescita delle tabelle. I valori seguenti sono in `seo-pro.*`; `null` disabilita la relativa pulizia:

| Dati | Configurazione | Valore predefinito | Comando |
|---|---|---|---|
| Esecuzioni di scansione e relativi problemi | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| Log 404 | `monitor_404.retention_days`, più `max_rows` a `10000` | `90` | `seo-pro:404-prune` |
| Esecuzioni del crawler | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Problemi risolti del crawler | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Telemetria operativa {#operational-telemetry}

Ogni scansione delle pagine o del crawler completata emette una riga strutturata nei log. Il payload contiene conteggi e tempi, senza URL, corpi, header o dati dei visitatori:

| Metrica | Scansione pagine | Crawler |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls`, destinazioni rifiutate dalla protezione SSRF | — | ✓ |
| `transient_failures`, errori di rete da ricontrollare | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds`, dall'accodamento al primo batch | ✓ | ✓ |

Configurala in `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Imposta `channel` su un canale dedicato per inviare le righe a Loki, Datadog o CloudWatch senza mescolarle ai log dell'app:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Per un'elaborazione personalizzata, ascolta gli eventi: ciascuno espone lo stesso payload `metrics()`:

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

La registrazione è best effort: un canale configurato male non deve far fallire la scansione.

## Distribuzione senza Filament {#filament-independent-deployment}

Nessun passaggio di questa guida richiede il pannello. Motore, comandi, code, scheduler, recupero, conservazione e telemetria funzionano senza Filament. `SeoProPlugin` aggiunge le **schermate**: avanzamento, problemi, gestione dei redirect, monitor 404 e dashboard del crawler. Puoi gestire il motore da CLI e scheduler e aggiungere il pannello in seguito senza rifare questa configurazione. La guida all'[uso senza Filament](/it/pro/headless) contiene il riferimento completo dei comandi.
