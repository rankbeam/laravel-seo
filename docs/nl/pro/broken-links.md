---
description: "Een begrensde, hervatbare crawler die onbereikbare links vastlegt: kapotte interne routes die je met één klik via een redirect kunt herstellen, en optioneel kapotte externe links. Standaard uitgeschakeld."
---

# Crawler voor kapotte links {#broken-link-crawler}

Een **begrensde, hervatbare crawler** die je site doorloopt, de links op elke
pagina volgt en vastlegt welke niet bereikbaar zijn. Het gaat om kapotte
**interne** links — een verdwenen route op je eigen host, met één klik via een
redirect te herstellen — en optioneel kapotte **externe** links.
De crawler staat **standaard uit**.

Drie uitgangspunten bepalen het ontwerp:

- **Begrensd en hervatbaar.** Een crawl loopt via meerdere kleine wachtrijtaken,
  elk met een beperkt aantal pagina's. Ze plannen een vervolgtaak in totdat de
  uitvoering klaar is of een limiet bereikt. Ook de volledige uitvoering is
  begrensd: standaard 2000 pagina's. `null` is de expliciete keuze voor
  onbeperkt, nooit de standaard; de overige batch- en tijdslimieten blijven
  gelden. Stem grenzen en wachttijden af op de capaciteit van site en server.
- **Standaard beveiligd.** Het standaardbereik is `internal_only`: alleen links
  op je eigen host worden gecontroleerd, zonder verzoeken aan derden. Elk
  ophaalverzoek, intern of extern, gaat via de gedeelde **SsrfGuard** met
  toegestane URL-protocollen, hostbeperkingen en weigering van privéadressen.
  Externe links controleren vereist expliciete inschakeling en blijft beveiligd.
- **Los van de SEO-score.** Bevindingen staan in eigen tabellen en schrijven
  nooit naar `seo_scan_issues` of de score van 0–100. De paginascore verandert dus
  niet als uitgaande links kapot zijn. Kapotte links zijn een operationeel
  probleem dat afzonderlijk wordt gevolgd.

## Wat je krijgt {#what-you-get}

In het Filament-dashboard, alleen als de functie is ingeschakeld:

- **Overzicht van kapotte links** — aantallen openstaande kapotte links,
  intern en extern, en de laatste crawl, met een link naar de bevindingentabel.
- **Crawl voor kapotte links** — live voortgang van de actieve crawl:
  gecrawlde pagina's, gecontroleerde links en gevonden kapotte links.
- **Kapotte links per scan** — de trend over recente crawls.
- **Een resource met bevindingen** — elke kapotte `source → target`-link,
  filterbaar, waarbij interne links via een redirect kunnen worden hersteld.

Zonder paneel bieden de `seo-pro:broken-links-*`-commando's dezelfde gegevens.

## Waarom de functie standaard uitstaat {#why-it-s-off-by-default}

Anders dan de passieve render- en scorefuncties **doet de crawler
netwerkverzoeken** en heeft hij enige infrastructuur nodig. Inschakelen is
daarom een bewuste keuze; hij hoort niet ongemerkt bij installatie te starten:

- Zijn twee hoofdtabellen worden, net als alle Pro-migraties, **alleen
  gepubliceerd**. De migraties moeten zijn uitgevoerd voordat de interface
  ze kan opvragen. Inspecties per type gebruiken ook `seo_broken_link_inspections`.
- Een crawl gaat **naar een aparte wachtrij** en vereist een **worker**.
  Zonder worker gaat een crawl niet verder.
- Bevestiging gebeurt **over meerdere scans**, zoals hieronder beschreven.
  De crawler is bedoeld om gedurende weken **periodiek** te draaien, niet
  om direct bij het inschakelen bevestigde resultaten op te leveren.

## Inrichting {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Voer daarna de migraties uit. `seo-pro:install` publiceert alle Pro-migraties en
voert ze uit; de opdracht is idempotent en kan veilig opnieuw worden uitgevoerd:

```bash
php artisan seo-pro:install
```

Start een **eigen worker** voor de crawlwachtrij. Deze wachtrij
(`seo-broken-links`) is juist apart gehouden zodat een lange crawl geen taken
voor je gebruikers ophoudt:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Controleer de inrichting. `seo:doctor` controleert de inschakeloptie,
tabellen en of de crawlwachtrij een echte verbinding gebruikt, geen
`sync`. Elke melding geeft de precieze oplossing:

```bash
php artisan seo:doctor
```

Zie [Productie-inrichting](/nl/pro/production) voor de volledige opzet met
meerdere wachtrijen, Redis, Supervisor en aparte verbindingen, en voor het
afstellen van batches.

## Een crawl uitvoeren {#running-a-crawl}

Start een crawl met de actie **Nu scannen** in het dashboard, of zonder paneel:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Beide commando's zetten de crawl alleen **in de wachtrij**; de worker voert het werk uit.

## Wanneer een link als kapot wordt gemeld {#how-a-link-gets-flagged}

Een link wordt pas als kapot gemeld nadat hij tijdens `seo-pro.broken_links.mark_broken_after_failures`
**opeenvolgende crawls** onbereikbaar is. Elke succesvolle controle zet de
teller terug op nul; de standaarddrempel is **3**. Eén tijdelijke storing
markeert een link dus nooit als kapot. Daarom is de crawler bedoeld voor
**periodiek** gebruik. Bij wekelijkse crawls en de standaarddrempel bevestigen
drie mislukte crawls het probleem: ongeveer twee weken na de eerste waarneming,
of tot ongeveer drie weken nadat de link kapot ging. Verhoog de frequentie
of verlaag de drempel als je snellere bevestiging wilt.

## Linkinspecties per type {#typed-link-inspections}

Naast bereikbaarheid krijgt elke gecrawlde link een reeks **inspecties per
type**. Die controles op URL-kwaliteit signaleren onder andere inconsistente
afsluitende slashes, onjuiste codering, redirectketens, `javascript:`-hrefs,
kapotte ankerverwijzingen binnen pagina's en onduidelijke linktekst. Elke
inspectie heeft een vaste **ernst**: `critical` · `warning` ·
`notice`. Dat zijn dezelfde niveaus als bij [scanproblemen](/nl/pro/scan-issues),
zodat één CI-controle beide kan afdekken. Inspecties worden per crawl vastgelegd
in `seo_broken_link_inspections`. Een kapotte-linkbevinding wordt pas na meerdere opeenvolgende
crawls bevestigd; een inspectie is een momentopname per uitvoering en verschijnt
**direct bij de eerste crawl**, precies wat een CI-controle nodig heeft.

### Inspectie-overzicht {#inspection-reference}

| Inspectie | Ernst | Signaleert | Van toepassing op |
| --- | --- | --- | --- |
| `broken_link` | critical | Het doel gaf HTTP ≥ 400 terug | Elke link |
| `redirect_chain` | notice · warning | Het doel is alleen via een redirect bereikbaar; `warning` boven `redirect_chain_warning_hops` | Elke link |
| `link_unreachable` | notice | Onbereikbaar tijdens deze crawl door netwerkfout, time-out of blokkering; mogelijk tijdelijk | Elke link |
| `insecure_link` | warning | Een `http://`-link op een `https`-site, met een minder veilige transportverbinding | Elke link |
| `trailing_slash` | notice | Een intern pad wijkt af van de opgegeven afspraak voor afsluitende slashes; **uit tenzij `trailing_slash` is ingesteld** | Interne links |
| `double_slash_url` | warning | Een intern pad bevat `//`, een leeg segment | Interne links |
| `duplicate_query_param` | notice | Een querysleutel komt herhaald voor, zoals `?a=1&a=2`; arraysyntaxis met `key[]` is uitgezonderd | Interne links |
| `non_ascii_url` | notice | Een intern pad bevat ongecodeerde niet-ASCII-tekens | Interne links |
| `uppercase_url` | notice | Een intern pad bevat hoofdletters; controleer varianten die afzonderlijk worden geserveerd | Interne links |
| `underscore_in_url` | notice | Een intern pad gebruikt underscores; koppeltekens zijn voor SEO het aanbevolen scheidingsteken | Interne links |
| `javascript_link` | warning | Een link gebruikt een `javascript:`-href, geen normale crawlbare bestemming | Elke link |
| `missing_fragment` | warning | Een `#fragment` binnen dezelfde pagina heeft geen overeenkomende `id`/`name` op de pagina | Verwijzingen binnen dezelfde pagina |
| `non_descriptive_anchor` | notice | Linktekst is algemeen, zoals 'klik hier' of 'lees meer', of bestaat alleen uit een URL | Elke link |
| `absolute_internal_link` | notice | Een interne link is geschreven als absolute URL in plaats van als pad vanaf de siteroot | Interne links |

De controles op URL-kwaliteit, zoals afsluitende slashes, hoofdletters, codering
en dubbele slashes, gelden alleen voor **interne** links. De URL-stijl van een
externe site bepaal jij niet. Inspecties op redirects, kapotte of onbereikbare
links en onveilige verbindingen gelden voor elke link. Links naar je eigen
frameworkroutes en statische assets worden overgeslagen om bij een eerste scan
overbodige meldingen te voorkomen; zie `exclude_paths` / `exclude_extensions` hieronder.

Elke link wordt opgehaald via de **precies zo geschreven URL**. Alleen het
`#fragment` wordt verwijderd; verder wordt de URL niet genormaliseerd.
Daardoor wordt een canonical-redirect op de server, zoals `/about/ → /about`,
daadwerkelijk waargenomen en gemeld als `redirect_chain`. Vooraf normaliseren
zou die redirect verbergen. Elke afzonderlijk geschreven vorm van een link op
een pagina wordt geïnspecteerd: `/page#ok` en `/page#missing`, of
`/a//b` en `/a/b`, worden dus elk beoordeeld. De onderliggende
kapotte-linkbevinding bundelt alle aliassen van een doel nog steeds onder één
identiteit. Inspectierijen worden vastgelegd per `(page, target, inspection)`. Een doel met
meerdere kapotte ankerverwijzingen binnen de pagina levert daardoor één
`missing_fragment`-rij met een voorbeeld op, niet één rij per anker.

### De inspecties afstellen {#tuning-the-taxonomy}

Alles staat onder `seo-pro.broken_links.inspections`:

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

**Schakel één regel uit** door de klasse uit `rules` te verwijderen,
of **alle inspecties** met `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`. Twee regels verdienen vooraf aandacht:

- `trailing_slash` staat **uit totdat je een afspraak opgeeft**:
  `'always'` / `'never'`. Als een site zowel `/x` als
  `/x/` met `200` serveert, is er geen 'verkeerde' stijl
  om te melden. Als de server wel via een redirect één vorm afdwingt,
  verschijnt dat al als `redirect_chain`.
- `absolute_internal_link` wordt gemeld bij **elke** interne link die als absolute URL
  is geschreven. Als je site standaard absolute interne URL's gebruikt,
  levert dat veel onschuldige rijen van het niveau `notice` op.
  Verwijder de regel uit `rules` om die meldingen uit te schakelen.

## Continue integratie {#continuous-integration}

Zowel de linkscan als de [SEO-audit](/nl/pro/scan-issues) kunnen **een build
laten mislukken** en **een rapportbestand schrijven**. Zo kan Rankbeam ook
als kwaliteitscontrole dienen. `--fail-on-error` komt overeen met het niveau
`critical`, voor een kapotte link of kritiek probleem.
`--fail-on-warning` laat de build mislukken bij `critical` **of**
`warning`; er is geen afzonderlijk niveau 'error'.

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` schrijft het rapportbestand. Geef je een map op, dan wordt
de bestandsnaam afgeleid. `--format` is `json`, de standaard,
`md` of `html`. Gebruik JSON voor verwerking in een pipeline;
HTML is een zelfstandige pagina die je aan een uitvoering kunt toevoegen.

### GitHub Actions {#github-actions}

De crawler haalt je pagina's via HTTP op. In CI moet je hem dus naar bereikbare
inhoud laten verwijzen: een lokaal geserveerde app, zoals hieronder, of een
staging-URL via `SEO_PRO_BROKEN_LINKS_BASE_URL`. Registreer je modellen of sitemap zodat de
crawl start-URL's kan verzamelen.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## Planning {#scheduling}

Registreer de crawl en zijn onderhoudstaken in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Commando-overzicht {#command-reference}

| Commando | Werking |
| --- | --- |
| `seo-pro:broken-links-scan` | Een begrensde, hervatbare crawl in de wachtrij zetten; `--scope=internal_only\|internal_and_external` en `--url=*` voor extra start-URL's |
| `seo-pro:broken-links-status` | Samenvatting van de laatste crawl, openstaande kapotte-linkbevindingen en inspectieaantallen van deze uitvoering; **CI-controle** via `--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html` |
| `seo-pro:broken-links-cancel` | Een actieve crawl of crawl in de wachtrij annuleren; `{run?}` gebruikt standaard de laatst actieve |
| `seo-pro:broken-links-recover` | Crawls die door een gestopte worker zijn achtergelaten als mislukt markeren, op basis van een verouderde lease |
| `seo-pro:broken-links-prune` | De bewaartermijnen van de crawler toepassen op oude uitvoeringen en opgeloste bevindingen |

## Afstellen {#tuning}

De grenzen van de crawl staan allemaal in `seo-pro.broken_links`: pagina's per
uitvoering, links per pagina, limieten per taak, het harde tijdsbudget en
wachttijden per host. De standaardwaarden zijn terughoudend en begrensd.
Raadpleeg de [batchtabel in Productie-inrichting](/nl/pro/production)
voordat je ze verhoogt.
