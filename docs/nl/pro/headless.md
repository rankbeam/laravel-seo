---
description: "Elke Pro-functie, van scans en redirects tot 404-registratie, werkt headless zonder Filament. Een commandoreferentie om Pro volledig via Artisan te beheren."
---

# Headless gebruik {#headless-usage}

Elke Pro-functie — scannen, omleiden en 404's registreren — werkt headless:
ze zit in de engine en heeft geen Filament nodig. Het paneel is alleen een
beheerinterface; deze commando's zijn het equivalent zonder interface.

## Commandoreferentie {#command-reference}

### Installatie en statuscontrole {#setup-health-check}

| Commando | Wat het doet |
|---|---|
| `seo-pro:install` | Publiceer `config/seo-pro.php` en de Pro-migraties, voer de migraties uit en toon de volgende stappen (`--no-migrate`, `--force`) |
| `seo:doctor` | Eenmalige statuscontrole: applicatie-URL, Core- en Pro-tabellen, scandoelen, sitemap, queues per workload, optionele functies en operationele toestand. Elke waarschuwing heeft een concrete correctie (`--json` voor monitoring) |

`seo-pro:install` is de gedocumenteerde installatieroute. Pro-migraties worden alleen
gepubliceerd en nooit automatisch door het pakket geladen. De installer
maakt dus van alleen `composer require` een werkend schema. Hij is idempotent:
je kunt hem op elk moment opnieuw uitvoeren.

`seo:doctor` doet geen netwerkverzoeken en toont nooit geheime waarden.
De AI-controle meldt alleen of de geconfigureerde sleutelvariabele *is ingesteld*.
Het commando valideert configuratie en recente uitvoeringsgeschiedenis. Het
kan niet bewijzen dat een externe cron of worker daadwerkelijk draait.
Alleen een kritieke fout, een ontbrekende vereiste tabel, geeft een
niet-nul-exitcode. Een lokale ontwikkelomgeving met waarschuwingen sluit dus
nog steeds succesvol af. `--json` geeft elke controle een stabiele
`id` als sleutel voor verdere verwerking. Voer het direct na de [installatie](/nl/pro/installation)
en in CI uit.

### Scannen {#scanning}

| Commando | Wat het doet |
|---|---|
| `seo-pro:scan` | Zet een volledige scan van alle geregistreerde doelen in de queue (`--sync` voor directe uitvoering; **CI-controle** via `--fail-on-error`, `--fail-on-warning`, `--report=` en `--format=json\|md\|html`, waarvoor `--sync` vereist is) |
| `seo-pro:scan-status` | Samenvatting van de laatste uitvoering en open bevindingen, ernstigste eerst (`--limit=20`, `--severity=critical\|warning\|notice`) |
| `seo-pro:scan-recover` | Markeer uitvoeringen die door een gestopte queueworker zijn achtergelaten als mislukt |
| `seo-pro:scan-prune` | Verwijder afgeronde uitvoeringen en hun bevindingen na de bewaartermijn |

### Crawler voor kapotte links {#broken-link-crawler}

Deze staat standaard uit. Schakel `seo-pro.broken_links.enabled` in en voer de migraties voor
de twee tabellen uit; `seo-pro:install` publiceert ze. De crawl wordt verdeeld over
begrensde jobs in een queue. Gebruik daarvoor een aparte worker.
Zie [Productieconfiguratie](/nl/pro/production) voor afstemming.

| Commando | Wat het doet |
|---|---|
| `seo-pro:broken-links-scan` | Zet een begrensde, hervatbare crawl in de queue (`--scope=internal_only\|internal_and_external`, `--url=*` voor extra start-URL's) |
| `seo-pro:broken-links-status` | Samenvatting van de laatste crawl, open bevindingen en [inspecties per linktype](/nl/pro/broken-links#typed-link-inspections) van deze uitvoering; **CI-controle** via `--fail-on-error`, `--fail-on-warning`, `--report=` en `--format=` |
| `seo-pro:broken-links-cancel` | Annuleer een actieve of wachtende crawl (`{run?}`, standaard de meest recente actieve crawl) |
| `seo-pro:broken-links-recover` | Markeer crawls die door een gestopte worker zijn achtergelaten als mislukt (verlopen lease) |
| `seo-pro:broken-links-prune` | Pas het bewaarbeleid van de crawler toe op oude uitvoeringen en opgeloste bevindingen |

### Redirects en 404's {#redirects-404s}

| Commando | Wat het doet |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Maak een redirectregel aan (`--code=301`, `--regex`, `--no-preserve-query`, `--note=`) |
| `seo-pro:404-list` | Geregistreerde 404's, meest bezochte eerst (`--status=new\|ignored\|redirected\|all`, `--limit=20`) |
| `seo-pro:redirects-flush-hits` | Schrijf in de cache gebundelde redirecttellers naar de database wanneer `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Verwijder verouderde 404-vermeldingen en handhaaf het maximumaantal rijen |

### On-page checklist {#on-page-checklist}

| Commando | Wat het doet |
|---|---|
| `seo-pro:checklist {model} {id}` | Checklist met pass/warn/fail (geslaagd/waarschuwing/fout) voor één model, rekening houdend met zoekwoorden (`--json`, `--strict`, `--locale=`). Zie [On-page checklist](/nl/pro/on-page-checklist) |

Dezelfde checklist is beschikbaar via `SeoPro::checklistFor($model)`. Het is de redactionele
controlecyclus voor zoekwoordplaatsing, lengte, afbeeldingen en interne links,
**niet** de [SEO-score](/nl/pro/scoring).

### Search Console (alleen-lezen) {#search-console-read-only}

| Commando | Wat het doet |
|---|---|
| `seo-pro:search-console` | Pagina's met open bevindingen **en** zoekverkeer, met de grootste onbenutte kans eerst (`--view=attention`, de standaard) |
| `seo-pro:search-console --view=pages` | Toppagina's op vertoningen, klikken, CTR of positie |
| `seo-pro:search-console --view=queries` | Belangrijkste zoekopdrachten (`--days=`, `--limit=`, `--json`) |

Dezelfde statistieken zijn beschikbaar via `SeoPro::searchConsole()`.
Zie [Search Console](/nl/pro/search-console). Standaard uit en strikt alleen-lezen.

### AI-ondersteuning {#ai-assist}

| Commando | Wat het doet |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Titel- en beschrijvingsvoorstellen als JSON (`--field=title\|description\|all`). Zie [AI-ondersteuning](/nl/pro/ai-assist) |
| `seo-pro:ai-suggest --issue={id}` | Uitleg in gewone taal voor het oplossen van een scanbevinding, als JSON |

### Een 404 in één stap oplossen {#resolving-a-404-in-one-step}

`--from-404={path}` is het headless equivalent van de actie *Redirect aanmaken* in
de 404-monitor. Het maakt de regel aan **en** markeert de overeenkomende
logboekvermelding als omgeleid, met een koppeling naar de nieuwe regel:

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

Het commando gebruikt dezelfde validators als het Filament-formulier.
Ongeldige regex-patronen, te grote waarden en externe doelen buiten de
toestemmingslijst worden geweigerd voordat er iets wordt geschreven.

## Aanbevolen planning {#recommended-schedule}

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

Voor elk terugkerend commando hierboven staat een aanbevolen frequentie in de
handleiding [Productieconfiguratie](/nl/pro/production). Daar vind je ook de
queue-indeling, workerconfiguratie, herhaal- en herstelbeleid, bewaartermijnen
en de gestructureerde **telemetrie** die elke voltooide uitvoering levert:
opgehaalde pagina's, gecontroleerde links, geblokkeerde URL's, duur en queuevertraging.

## Waarvoor is de Filament-interface nodig? {#what-needs-the-filament-ui}

Voor geen enkele functionele enginebewerking. De complete engine is met en
zonder Filament identiek: scanpipeline, bevindingenbeheer, redirectmatching,
404-registratie, opruimen en herstel. Het paneel voegt de *weergaven* toe:
het dashboard met live scanvoortgang en ernststatistieken, bevindingen bekijken
met filters en dialoogvensters per pagina, knoppen voor negeren en heropenen,
CRUD-formulieren voor redirects en de 404-tabel met haar eenkliksactie.
Er is momenteel geen afzonderlijk commando om bevindingen te negeren of te
heropenen. Doe dat via het paneel of via het model `SEOScanIssue`
(`markIgnored()` / `reopen()`) in Tinker of je eigen code.
