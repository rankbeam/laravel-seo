---
description: "Provozujte Pro ve větším měřítku: vyhrazené fronty, plánovač, opakování a obnova, uchovávání dat a telemetrie. Uspořádání bez závislosti na Filamentu z produkce s asi 900 stránkami."
---

# Produkční provoz {#production-setup}

Běžné úlohy Pro — skenování webu, hledání nefunkčních odkazů, volitelné zapisování návštěv přesměrování a promazávání 404 — běží přes frontu a plánovač Laravelu. Tento průvodce soustřeďuje pravidla pro provoz ve větším měřítku: vyhrazené fronty, plánovač, opakování a obnovu, uchovávání dat a telemetrii pro dohled. Popisuje reprodukovatelné uspořádání produkční instalace s přibližně 20 tisíci návštěvami denně a 900 stránkami.

Vše zde je **nezávislé na Filamentu**. Jádro Pro, příkazy, fronty i telemetrie jsou s panelem i bez něj stejné. Filament pouze přidává pohledy na data; plánování ani zpracování úloh nemění.

[[toc]]

## Bezpečné pořadí nasazení {#safe-rollout-order}

Postupujte v tomto pořadí. Každý krok lze ověřit před dalším:

1. **Instalace** — zkopírujte konfiguraci a migrace a spusťte je:

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` zkopíruje `config/seo-pro.php` a migrace Pro a potom spustí `migrate`. Migrace Pro se **musí zkopírovat do aplikace**; balíček je nikdy automaticky nenačítá. Tento krok tak po samotném `composer require` vytvoří funkční schéma. Je idempotentní a lze jej opakovat. Přidáním `--force` přepíšete zkopírované soubory, pomocí `--no-migrate` je zkopírujete bez spuštění migrací.

2. **Zaregistrujte cíle skenování** v poskytovateli služeb (`AppServiceProvider::boot()`):

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Ověřte** zapojení před zapnutím úloh na pozadí:

   ```bash
   php artisan seo:doctor
   ```

   Vyřešte každé vypsané upozornění; každé obsahuje přesný příkaz nebo řádek konfigurace. V CI přidejte `--json` a zpracovávejte stabilní ID kontrol.

4. **Nastavte fronty a plánovač** podle pokynů níže, nasaďte worker fronty a záznam cronu pro `schedule:run`.

5. **Volitelné funkce zapněte nakonec.** Robot pro nefunkční odkazy, asistence AI i Search Console jsou ve výchozím nastavení vypnuté. Robot potřebuje migrované tabulky, jejichž migrace už zkopíroval krok 1, a vyhrazený worker popsaný níže.

**Aktualizace na Pro 2.41.0:** pozastavte workery skenování, zkopírujte migrace pomocí `php artisan vendor:publish --tag=seo-pro-migrations --force`, spusťte `php artisan migrate`, poté workery restartujte a spusťte `php artisan seo:doctor`. Nová tabulka `seo_scan_target_completions` a sloupec `seo_scan_runs.target_tracking` jsou povinné. Záznamy dokončení pro každou dvojici běh/cíl zabraňují tomu, aby duplicitní konečné výsledky navyšovaly počítadla. Platí první přijatý výsledek. Staré běhy ve frontě bez zpracovaných cílů pokračují. Částečně zpracované běhy z doby před aktualizací zachovají historii, ale při příštím doručení se uzavřou s pokynem spustit nový sken. Cíle s vyčerpanými pokusy opakujte v novém běhu. Při návratu zpět zastavte workery a vraťte kód před vrácením migrace. Pokud potřebujete vrátit i pozdější skeny, zachovejte zálohu databáze před aktualizací.

## Vyhrazené fronty pro jednotlivé úlohy {#dedicated-queues-per-workload}

Dlouhý sken nebo procházení odkazů nesmí zdržovat úlohy pro uživatele, například e-maily a oznámení. Každému typu úloh SEO přidělte vlastní frontu a worker.

Skenování stránek i robot pro nefunkční odkazy čtou konfigurovatelnou frontu:

| Úloha | Konfigurace | Proměnná prostředí | Výchozí fronta |
|---|---|---|---|
| Úlohy skenování stránek | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | výchozí fronta |
| Úlohy hledání nefunkčních odkazů | `seo-pro.broken_links.queue.name` a `.connection` | `SEO_PRO_BROKEN_LINKS_QUEUE` a `_CONNECTION` | `seo-broken-links` |

### Příklad s Redis (produkční uspořádání) {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Pro každou frontu spusťte worker jako samostatný proces nebo program Supervisoru:

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

Hodnota `--timeout` workeru procházení musí překročit `seo-pro.broken_links.batch.hard_time_budget_seconds`, standardně 180, plus časový limit HTTP, aby se dávka neukončila během zapisování stavu. Úloha nastavuje vlastní `$timeout` na tento součet, proto s ním slaďte parametr workeru. Pro procházení použijte `--tries=1`: zaniklou úlohu převezme další pokračování nebo `seo-pro:broken-links-recover`, takže opakování na úrovni fronty není potřeba.

`seo:doctor` uvádí frontu každé úlohy a upozorní, pokud se vyhodnotí na `sync`, což by úlohu spustilo přímo a blokovalo proces.

## Plánovač {#scheduler}

Laravel 11, 12 a 13 plánují v **`routes/console.php`**. Metoda `schedule()` v `app/Console/Kernel.php` existuje jen v aplikacích aktualizovaných z Laravelu 10; pokud ji vaše aplikace stále má, vložte stejné záznamy tam. Přidejte jediný systémový záznam cronu, aby se plánovač spouštěl každou minutu:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Potom zaregistrujte všechny opakované příkazy s doporučenými intervaly:

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

Přehled doporučených intervalů:

| Příkaz | Interval | Důvod |
|---|---|---|
| `seo:sitemap` | denně | Obnovení mapy webu z aktuálního obsahu |
| `seo-pro:scan` | týdně, při častých změnách obsahu denně | Opakovaný audit všech cílů |
| `seo-pro:scan-recover` | každou hodinu | Obnova stavu běhů po zániku workeru |
| `seo-pro:scan-prune` | denně | Uplatnění doby uchovávání běhů skenování |
| `seo-pro:redirects-flush-hits` | každých 5 minut, jen při `redirects.hits.flush_immediately=false` | Zápis počítadel návštěv shromážděných v mezipaměti do databáze |
| `seo-pro:404-prune` | denně | Omezení protokolu 404 podle stáří a počtu řádků |
| `seo-pro:404-recheck` | denně | Opětovné načtení otevřených cest 404 a označení opravených zdrojů, které už vracejí 200 |
| `seo-pro:broken-links-scan` | týdně | Nové procházení nefunkčních odkazů; potvrzení probíhá napříč skeny |
| `seo-pro:broken-links-recover` | každou hodinu | Obnova stavu procházení po zániku workeru |
| `seo-pro:broken-links-prune` | denně | Uplatnění dob uchovávání dat robota |

`seo-pro:scan` a `seo-pro:broken-links-scan` práci pouze **zařazují do fronty**; vykoná ji worker. Příkazy obnovy a promazávání běží přímo v procesu a jsou nenáročné.

::: tip Potvrzení nefunkčních odkazů napříč skeny
Odkaz se označí jako nefunkční teprve poté, co jej nedokáže načíst `seo-pro.broken_links.mark_broken_after_failures` **po sobě jdoucích skenů**. Každý úspěch počítadlo vynuluje. Proto se procházení plánuje opakovaně, nikoli jednorázově: jediný přechodný výpadek odkaz neoznačí. Při výchozí hodnotě 3 a týdenních skenech nastane potvrzení přibližně dva týdny od prvního neúspěšného pozorování, případně až tři týdny od vzniku problému. Pro rychlejší potvrzení zkraťte interval nebo snižte práh.
:::

## Ladění dávek (robot pro nefunkční odkazy) {#batch-tuning-broken-link-crawler}

Procházení probíhá v mnoha omezených úlohách, které samy zařazují pokračování. Výchozí limity jsou konečné; upravte je podle kapacity svého webu a kontrolovaných hostitelů v `seo-pro.broken_links`:

| Klíč | Výchozí hodnota | Co omezuje |
|---|---|---|
| `max_pages_per_run` | `2000` | Stránky načtené během celého běhu. `null` výslovně zapíná neomezený počet; nikdy nejde o výchozí stav |
| `max_links_per_page` | `200` | Odkazy kontrolované na stránce |
| `max_total_links` | `null` | Volitelný celkový limit kontrol odkazů za běh |
| `batch.max_pages_per_job` | `50` | Stránky na jednu úlohu ve frontě |
| `batch.max_links_per_job` | `1500` | Kontroly odkazů na jednu úlohu ve frontě |
| `batch.hard_time_budget_seconds` | `180` | Po dosažení limitu úloha nezačne **žádné nové načítání** a zařadí pokračování |
| `batch.dispatch_delay_seconds` | `1` | Prodleva mezi pokračovacími úlohami |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Limity jednotlivého požadavku |
| `http.max_response_bytes` | přebírá `seo-pro.http.max_response_bytes` | Limit velikosti těla odpovědi kontrolovaný během přenosu u stránek a kontrolovaných cílů |
| `seed.max_response_bytes` | přebírá limit HTTP robota nebo sdílený limit | Bajty surového XML mapy webu či `.gz` načtené při získávání počátečních URL |
| `seed.max_inflated_bytes` | přebírá limit počátečního načítání, robota nebo sdílený limit | Rozbalené bajty přijaté z mapy `.gz` |
| `http.per_host_delay_ms` | `0` | Ohleduplná prodleva mezi kontrolami; zvyšte pro `internal_and_external` |

Ponechte `batch.hard_time_budget_seconds` s dostatečnou rezervou pod `--timeout` workeru procházení. Probíhající požadavek nelze přerušit uprostřed přenosu; omezuje jej `http.timeout`. Proto časový limit workeru odpovídá rozpočtu dávky plus limitu HTTP a rezervě.

Pro procházení `internal_and_external` rozšiřte `seo-pro.http.scope` nebo `seo-pro.http.allowed_hosts`, aby SsrfGuard dovolil odchozí kontroly. Zvyšte `http.per_host_delay_ms`, aby požadavky na cizího hostitele nepřicházely příliš rychle. `seo:doctor` upozorní, pokud je rozsah procházení externí, ale ochrana by všechny kontroly zablokovala.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Jeden program pro každou frontu. Příklad `/etc/supervisor/conf.d/app-workers.conf`:

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

`stopwaitsecs` musí překročit `--timeout` workeru, aby korektní restart neukončil úlohu uprostřed dávky.

### Horizon {#horizon}

Pokud používáte Horizon, definujte v `config/horizon.php` supervisor pro každý typ úloh a nechte procesy spravovat jím místo Supervisoru:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Opakování a zpracování chyb {#retry-failure-handling}

Úloha cíle skenování používá vlastní pravidla opakování z konfigurace. **Nespoléhá** na `--tries` workeru:

| Klíč | Výchozí hodnota | Význam |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Počet pokusů na úlohu cíle |
| `seo-pro.scan.backoff` | `30` | Sekundy mezi pokusy |
| `seo-pro.scan.timeout` | `300` | Časový limit úlohy cíle; zámek souběhu vyprší po tomto limitu plus 60 sekundách |

Úloha, která vyčerpá opakování, zapíše cíl jako **neúspěšný** a běh se přesto dokončí se stavem `partial` nebo `failed`. Ošetřené chyby cílů nezanechávají běh ve stavu `running`. Worker ukončený před zápisem stavu stále potřebuje níže uvedenou obnovu. Chyby končí ve standardní tabulce `failed_jobs`; spravujte je obvyklým způsobem:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Vedle příkazů SEO naplánujte `queue:prune-failed`, aby tabulka nerostla neomezeně:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

Robot pro nefunkční odkazy používá `--tries=1`. Zaniklou úlohu převezme její další pokračování po zastarání signálu aktivity rezervace běhu (lease heartbeat) nebo `seo-pro:broken-links-recover`. Opakování z fronty by proto pouze duplikovalo práci.

## Obnova {#recovery}

Zánik workeru během úlohy je případ, kdy se evidence průběhu sama neopraví. Uzavírají jej dva kontrolní příkazy; oba naplánujte **každou hodinu**:

- `seo-pro:scan-recover` označí jako neúspěšné běhy skenování stránek, které nepostoupily po dobu `seo-pro.scan.recovery.stuck_scan_timeout_hours`, standardně 2 hodiny.
- `seo-pro:broken-links-recover` převezme běhy procházení se zastaralým signálem pronájmu podle `seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, standardně 2 hodiny. Označí je jako neúspěšné a uvolní místo vyhrazené jedinému aktivnímu běhu v daném rozsahu.

`seo:doctor` tento stav zobrazuje jako **nedávné doklady o aktivitě**: jakmile se skenování používá, hlásí uvízlé běhy a odkazuje na příkaz obnovy. Nedokáže prokázat, že cron skutečně běží — to nedokáže žádný příkaz. Uvádí, co je vidět z historie běhů.

## Uchovávání dat {#retention}

Udržujte velikost tabulek pod kontrolou. Výchozí hodnoty jsou v `seo-pro.*`; `null` vypíná příslušné promazávání:

| Data | Konfigurace | Výchozí hodnota | Příkaz |
|---|---|---|---|
| Běhy skenování a problémy | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| Protokol 404 | `monitor_404.retention_days` a `max_rows` `10000` | `90` | `seo-pro:404-prune` |
| Běhy procházení | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Vyřešené nálezy | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Provozní telemetrie {#operational-telemetry}

Každý dokončený běh skenování stránek **i** procházení nefunkčních odkazů zapíše do logovacího systému jeden strukturovaný řádek dokončení. Získáte tak historii metrik bez panelu. Data obsahují jen počty a časové údaje, žádné URL, těla odpovědí, hlavičky ani údaje návštěvníků:

| Metrika | Skenování | Procházení |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (cíle odmítnuté ochranou SSRF) | — | ✓ |
| `transient_failures` (síťové chyby, kontrolované znovu při příštím skenu) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (zařazení do fronty → první dávka) | ✓ | ✓ |

Nastavte ji v `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Nasměrujte `channel` na vyhrazený logovací kanál, aby řádky mířily do používaného systému, například Loki, Datadog či CloudWatch, a nemísily se s aplikačními logy:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Pro podrobnější zpracování odebírejte události přímo. Každá vystavuje stejná data `metrics()`:

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

Telemetrie se zapisuje podle možností: chybně nastavený kanál nikdy nezpůsobí selhání skenu.

## Nasazení bez závislosti na Filamentu {#filament-independent-deployment}

Nic na této stránce nevyžaduje panel. Jádro Pro, všechny příkazy, fronty, plánovač, obnova, uchovávání dat i telemetrie fungují bez panelu stejně. Panel Filament (`SeoProPlugin`) přidává pouze **pohledy**: živý průběh skenování, tabulku problémů, správu přesměrování, monitor 404 a přehled nefunkčních odkazů. Nasaďte jádro Pro a spravujte je přes CLI a plánovač. Panel můžete přidat později nebo vůbec, bez dalších migrací či přepracování. Úplný přehled příkazů obsahuje [Použití bez panelu](/cs/pro/headless).
