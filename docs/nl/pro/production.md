---
description: "Pro op schaal draaien: aparte wachtrijen, scheduler, herhaalpogingen en herstel, bewaartermijnen en telemetrie. De opzet achter een productie-installatie met circa 900 pagina's, onafhankelijk van Filament."
---

# Productie-inrichting {#production-setup}

Het dagelijkse werk van Pro — sitescans, de crawler voor kapotte links, het optioneel
wegschrijven van redirecttellers en het opschonen van 404-meldingen — draait op de
wachtrijen en scheduler van Laravel. Deze gids beschrijft de volledige inrichting
voor gebruik op schaal: aparte wachtrijen, een scheduler, herhaalpogingen en herstel,
bewaartermijnen en de telemetrie waarmee je dit volgt. De opzet komt uit een
productie-installatie met circa 20.000 bezoeken per dag en circa 900 pagina's,
en is hier beschreven zodat je hem kunt overnemen.

Alles hier is **onafhankelijk van Filament**: de engine, commando's, wachtrijen en
telemetrie zijn identiek met of zonder paneel. Filament voegt schermen toe;
het verandert niets aan de planning of verwerking van het werk.

[[toc]]

## Veilige volgorde voor de uitrol {#safe-rollout-order}

Voer deze stappen in volgorde uit. Je kunt elke stap controleren voordat je verdergaat:

1. **Installeren** — publiceer de configuratie en migraties en voer ze uit:

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` publiceert `config/seo-pro.php` en de Pro-migraties en voert daarna
   `migrate` uit. Pro-migraties worden **alleen gepubliceerd**; het pakket laadt
   ze nooit automatisch. Deze stap maakt dus van alleen `composer require` een werkend
   databaseschema. De opdracht is idempotent: je kunt hem opnieuw uitvoeren.
   Voeg `--force` toe om gepubliceerde bestanden te overschrijven, of
   `--no-migrate` om te publiceren zonder de migraties uit te voeren.

2. **Scandoelen registreren** in een serviceprovider (`AppServiceProvider::boot()`):

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **De inrichting controleren** voordat je achtergrondtaken inschakelt:

   ```bash
   php artisan seo:doctor
   ```

   Los elke weergegeven waarschuwing op. Elke waarschuwing noemt het precieze
   commando of de configuratieregel. Voeg in CI `--json` toe en gebruik
   de stabiele controle-ID's.

4. **De wachtrijen en scheduler configureren** zoals hieronder beschreven.
   Start een queueworker en voeg een cronregel voor `schedule:run` toe.

5. **Optionele functies als laatste inschakelen** — de crawler voor kapotte links,
   AI-assistentie en Search Console staan standaard uit. De crawler heeft zijn
   gemigreerde tabellen nodig (al gepubliceerd in stap 1) en een eigen worker
   (hieronder).

**Upgraden naar Pro 2.41.0:** pauzeer scanworkers, publiceer migraties met
`php artisan vendor:publish --tag=seo-pro-migrations --force`, voer `php artisan migrate` uit, herstart de workers en voer vervolgens
`php artisan seo:doctor` uit. De nieuwe tabel `seo_scan_target_completions` en kolom `seo_scan_runs.target_tracking` zijn
vereist. Registraties per scanuitvoering en doel voorkomen dat dubbele
eindresultaten de tellers verhogen; het eerste geaccepteerde resultaat telt.
Oude wachtrijtaken zonder verwerkte doelen gaan verder. Gedeeltelijk verwerkte
uitvoeringen van vóór de upgrade behouden hun geschiedenis, maar worden bij de
volgende aflevering afgesloten met de instructie een nieuwe scan te starten.
Probeer doelen waarvan alle pogingen zijn verbruikt opnieuw in een nieuwe
uitvoering. Stop bij een rollback eerst de workers en herstel de vorige code
voordat je de migratie terugdraait. Bewaar een databaseback-up van vóór de upgrade
als je ook latere scans ongedaan moet kunnen maken.

## Aparte wachtrijen per soort werk {#dedicated-queues-per-workload}

Een lange scan of crawl mag nooit taken voor gebruikers, zoals e-mail of
meldingen, ophouden. Geef elke SEO-werklast een eigen wachtrij en worker.

De scanpipeline en de crawler voor kapotte links gebruiken elk een instelbare wachtrij:

| Werklast | Configuratie | Omgevingsvariabele | Standaardwachtrij |
|---|---|---|---|
| Scantaken voor pagina's | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | de standaardwachtrij |
| Crawltaken voor kapotte links | `seo-pro.broken_links.queue.name` (+ `.connection`) | `SEO_PRO_BROKEN_LINKS_QUEUE` (+ `_CONNECTION`) | `seo-broken-links` |

### Redis-voorbeeld (de productie-opzet) {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Start een worker per wachtrij, elk als apart proces of Supervisor-programma:

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

De `--timeout` van de crawlworker moet hoger zijn dan `seo-pro.broken_links.batch.hard_time_budget_seconds`
(standaard 180) plus de HTTP-time-out. Zo wordt een batch niet midden in de
voortgangsregistratie afgebroken. De taak stelt zijn eigen `$timeout` in op
die som; stem de workeroptie daarop af. Gebruik `--tries=1` voor de crawl:
een afgebroken taak wordt opgevangen door de volgende vervolgtaak of door
`seo-pro:broken-links-recover`. Herhaalpogingen op wachtrijniveau zijn daarom niet nodig.

`seo:doctor` toont de wachtrij van elke werklast en waarschuwt als deze
uitkomt op `sync`, wat het werk synchroon zou uitvoeren en de verwerking zou blokkeren.

## Scheduler {#scheduler}

In Laravel 11, 12 en 13 plan je taken in **`routes/console.php`**. De methode
`schedule()` in `app/Console/Kernel.php` bestaat alleen nog in applicaties die vanaf
Laravel 10 zijn bijgewerkt; gebruik daar dezelfde regels als jouw app die methode
nog heeft. Voeg één cronregel op systeemniveau toe zodat de scheduler elke minuut draait:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Registreer vervolgens elk terugkerend commando met de aanbevolen frequentie:

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

De aanbevolen frequenties op een rij:

| Commando | Frequentie | Reden |
|---|---|---|
| `seo:sitemap` | dagelijks | De sitemap bijwerken met de actuele inhoud |
| `seo-pro:scan` | wekelijks, dagelijks bij snel veranderende inhoud | Elk doel opnieuw controleren |
| `seo-pro:scan-recover` | elk uur | Uitvoeringen afhandelen die door een gestopte worker zijn achtergelaten |
| `seo-pro:scan-prune` | dagelijks | De bewaartermijn voor scans toepassen |
| `seo-pro:redirects-flush-hits` | elke 5 min, alleen bij `redirects.hits.flush_immediately=false` | Gebundelde bezoekstellers uit de cache naar de database schrijven |
| `seo-pro:404-prune` | dagelijks | Het 404-logboek beperken tot de bewaartermijn en het maximale aantal rijen |
| `seo-pro:404-recheck` | dagelijks | Openstaande 404-paden opnieuw ophalen; paden die weer 200 teruggeven als hersteld markeren |
| `seo-pro:broken-links-scan` | wekelijks | Opnieuw crawlen op kapotte links; bevestiging gebeurt over meerdere scans |
| `seo-pro:broken-links-recover` | elk uur | Crawls afhandelen die door een gestopte worker zijn achtergelaten |
| `seo-pro:broken-links-prune` | dagelijks | De bewaartermijnen van de crawler toepassen |

`seo-pro:scan` en `seo-pro:broken-links-scan` zetten werk alleen **in de wachtrij**; de worker
voert het uit. De herstel- en opschooncommando's draaien synchroon en zijn licht.

::: tip Kapotte links bevestigen over meerdere scans
Een link wordt pas als kapot gemarkeerd nadat hij tijdens `seo-pro.broken_links.mark_broken_after_failures`
**opeenvolgende scans** onbereikbaar is. Elke succesvolle controle zet de teller
terug op nul. Daarom plan je de crawl periodiek: één tijdelijke storing markeert
een link nooit als kapot. Bij de standaardwaarde 3 bevestigen wekelijkse scans
het probleem na ongeveer twee weken vanaf de eerste mislukte waarneming, of tot
ongeveer drie weken nadat de link kapot ging. Verhoog de frequentie of verlaag
de drempel als je snellere bevestiging wilt.
:::

## Batches afstellen (crawler voor kapotte links) {#batch-tuning-broken-link-crawler}

De crawl bestaat uit meerdere begrensde taken die hun eigen vervolgtaak inplannen.
De standaardinstellingen zijn begrensd; stem ze af op de capaciteit van je site
en de hosts die je controleert. Je stelt ze in via `seo-pro.broken_links`:

| Sleutel | Standaard | Wat wordt begrensd |
|---|---|---|
| `max_pages_per_run` | `2000` | Opgehaalde pagina's per volledige uitvoering. `null` = expliciet kiezen voor onbeperkt, nooit de standaard |
| `max_links_per_page` | `200` | Gecontroleerde links per pagina |
| `max_total_links` | `null` | Optioneel totaalmaximum aan linkcontroles per uitvoering |
| `batch.max_pages_per_job` | `50` | Pagina's per wachtrijtaak |
| `batch.max_links_per_job` | `1500` | Linkcontroles per wachtrijtaak |
| `batch.hard_time_budget_seconds` | `180` | Hierna start de taak **geen nieuwe ophaalactie** en plant hij een vervolgtaak in |
| `batch.dispatch_delay_seconds` | `1` | Wachttijd tussen vervolgtaken |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Grenzen per verzoek |
| `http.max_response_bytes` | neemt `seo-pro.http.max_response_bytes` over | Limiet tijdens het streamen van antwoordinhoud voor pagina's en doelcontroles |
| `seed.max_response_bytes` | neemt de HTTP-limiet van de crawler of de gedeelde configuratie over | Ruwe sitemap-XML- of `.gz`-bytes die bij het verzamelen van start-URL's worden opgehaald |
| `seed.max_inflated_bytes` | neemt de limiet voor start-URL's, de crawler of de gedeelde configuratie over | Geaccepteerde gedecomprimeerde bytes uit een `.gz`-sitemap |
| `http.per_host_delay_ms` | `0` | Wachttijd tussen controles om hosts te ontzien; verhogen bij `internal_and_external` |

Houd `batch.hard_time_budget_seconds` ruim onder de `--timeout` van de crawlworker.
Een lopend verzoek kan niet tussentijds worden afgebroken; `http.timeout`
begrenst het. Daarom is de workertime-out gelijk aan het tijdsbudget plus de
HTTP-time-out plus een marge.

Verruim voor een `internal_and_external`-crawl `seo-pro.http.scope` of `seo-pro.http.allowed_hosts`, zodat
SsrfGuard de uitgaande controles toestaat. Verhoog `http.per_host_delay_ms` om hosts van
derden niet te snel achter elkaar te benaderen. `seo:doctor` waarschuwt als
de crawl externe links omvat terwijl de beveiligingsinstellingen elke controle
zouden blokkeren.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Eén programma per wachtrij. Voorbeeld voor `/etc/supervisor/conf.d/app-workers.conf`:

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

`stopwaitsecs` moet hoger zijn dan de `--timeout` van de worker, zodat een
nette herstart geen taak midden in een batch afbreekt.

### Horizon {#horizon}

Gebruik je Horizon, definieer dan een supervisor per werklast in `config/horizon.php`
en laat Horizon de processen beheren in plaats van Supervisor:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Herhaalpogingen en foutafhandeling {#retry-failure-handling}

De taak voor een scandoel gebruikt een eigen beleid voor herhaalpogingen uit de
configuratie. Hij is **niet** afhankelijk van de workeroptie `--tries`:

| Sleutel | Standaard | Betekenis |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Pogingen per doeltaak |
| `seo-pro.scan.backoff` | `30` | Seconden tussen pogingen |
| `seo-pro.scan.timeout` | `300` | Time-out per doeltaak; het slot tegen overlap verloopt na de time-out plus 60 |

Een doeltaak die al zijn pogingen verbruikt, registreert het doel als **mislukt**.
De scan wordt wel afgesloten, met `partial` of `failed`.
Afgehandelde fouten bij doelen laten een uitvoering niet op `running` staan.
Als een worker vóór de voortgangsregistratie wordt afgebroken, is nog wel de
herstelronde hieronder nodig. Fouten komen in de standaardtabel `failed_jobs`;
beheer ze op de gebruikelijke manier:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Plan ook `queue:prune-failed` naast de SEO-taken om die tabel binnen de perken te houden:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

De crawl voor kapotte links gebruikt `--tries=1`. Een afgebroken taak wordt
opgevangen door zijn volgende vervolgtaak, zodra de heartbeat van de lease
veroudert, of door `seo-pro:broken-links-recover`. Herhaalpogingen vanuit de wachtrij zouden
het werk dus alleen dupliceren.

## Herstel {#recovery}

Een worker die midden in een taak stopt, is het enige geval waarin de
voortgangsregistratie zich niet zelf kan herstellen. Twee herstelrondes handelen
dit af; plan beide **elk uur**:

- `seo-pro:scan-recover` markeert paginascans zonder voortgang gedurende
  `seo-pro.scan.recovery.stuck_scan_timeout_hours` uur (standaard 2) als mislukt.
- `seo-pro:broken-links-recover` handelt crawls af waarvan de heartbeat van de lease is
  verouderd (`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, standaard 2 uur). Het markeert ze als mislukt en
  maakt de plek vrij voor een nieuwe actieve uitvoering binnen dat bereik.

`seo:doctor` toont dit als **bewijs van recente heartbeats**: zodra scans in
gebruik zijn, meldt het vastgelopen uitvoeringen en verwijst het naar het
herstelcommando. Het kan niet bewijzen dat je cron daadwerkelijk draait — geen
commando kan dat — maar rapporteert wat de uitvoeringsgeschiedenis laat zien.

## Bewaartermijnen {#retention}

Houd de tabellen begrensd. De standaardwaarden hieronder staan allemaal onder
`seo-pro.*`; `null` schakelt de betreffende opschoning uit:

| Gegevens | Configuratie | Standaard | Commando |
|---|---|---|---|
| Scans en hun problemen | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| 404-logboek | `monitor_404.retention_days` (+ `max_rows` `10000`) | `90` | `seo-pro:404-prune` |
| Crawls | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Opgeloste bevindingen | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Operationele telemetrie {#operational-telemetry}

Elke afgesloten uitvoering, zowel een paginascan **als** een crawl voor kapotte
links, schrijft één gestructureerde afsluitregel via de loggingstack. Zo heb je
ook zonder paneel een meetgeschiedenis. De gegevens bevatten alleen aantallen
en tijdmetingen, geen URL's, antwoordinhoud, headers of bezoekersgegevens:

| Meetwaarde | Scan | Crawl |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (door SSRF-beveiliging geweigerde doelen) | — | ✓ |
| `transient_failures` (netwerkfouten, opnieuw gecontroleerd bij de volgende scan) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (in wachtrij → eerste batch) | ✓ | ✓ |

Configureer dit in `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Stel `channel` in op een apart logkanaal om de regels naar een
loggingsysteem zoals Loki, Datadog of CloudWatch te sturen, zonder ze met de
applicatielogs te vermengen:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Voor uitgebreidere verwerking kun je je rechtstreeks op de events abonneren.
Elk event biedt dezelfde gegevens via `metrics()`:

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

Telemetrie wordt naar beste vermogen verwerkt: een verkeerd ingesteld kanaal
kan een scan nooit laten mislukken.

## Deployen zonder afhankelijkheid van Filament {#filament-independent-deployment}

Niets op deze pagina vereist een paneel. De engine, alle commando's, wachtrijen,
scheduler, herstel, bewaartermijnen en telemetrie werken identiek zonder
gebruikersinterface. Het Filament-paneel (`SeoProPlugin`) voegt alleen
**schermen** toe: live scanvoortgang, de problementabel, redirectbeheer,
de 404-monitor en het dashboard voor kapotte links. Deploy de engine en beheer
hem via de CLI en scheduler. Je kunt het paneel later toevoegen, of helemaal
niet, zonder iets te migreren of opnieuw te doen. Zie [Gebruik zonder paneel](/nl/pro/headless)
voor het volledige commando-overzicht.
