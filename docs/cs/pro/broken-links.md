---
description: "Robot s omezeným rozsahem a možností pokračování zaznamenává nefunkční interní odkazy opravitelné přesměrováním a volitelně i externí odkazy. Standardně vypnutý."
---

# Robot pro hledání nefunkčních odkazů {#broken-link-crawler}

**Robot s omezeným rozsahem a možností pokračování** prochází web, následuje odkazy na každé stránce a zaznamenává ty, které nejsou dostupné. Jde o nefunkční **interní** odkazy, tedy neexistující trasy na vlastním hostiteli opravitelné přesměrováním jedním kliknutím, a volitelně i nefunkční **externí** odkazy. Ve výchozím nastavení je **vypnutý**.

Návrh určují tři vlastnosti:

- **Omezený rozsah a pokračování.** Procházení běží v mnoha malých úlohách fronty, každá s limitem několika stránek. Úlohy zařazují pokračování, dokud běh neskončí nebo nedosáhne limitů. Omezený je i celý běh: standardně 2 000 stránek. `null` výslovně zapíná neomezený počet, nikdy nejde o výchozí stav, a zbývající limity dávek a času platí dál. Limity a prodlevy přizpůsobte kapacitě webu a serveru.
- **Bezpečné výchozí nastavení.** Výchozí rozsah `internal_only` kontroluje jen odkazy na vlastním hostiteli, bez požadavků na třetí strany. Každé načtení, interní i externí, prochází sdíleným **SsrfGuard**: seznam povolených schémat, rozsah hostitelů a odmítnutí soukromých adres. Externí odkazy se kontrolují jen po výslovném zapnutí a nadále pod touto ochranou.
- **Oddělené od skóre SEO.** Zjištění mají vlastní tabulky a nikdy nezapisují do `seo_scan_issues` ani skóre 0–100. Skóre stránky se nemění podle funkčnosti jejích odchozích odkazů. Nefunkční odkazy jsou provozní záležitost se samostatným sledováním.

## Co získáte {#what-you-get}

V přehledu Filamentu, pouze při zapnuté funkci:

- **Souhrn nefunkčních odkazů** — počty otevřených nefunkčních interních a externích odkazů a poslední procházení, s odkazem na tabulku zjištění.
- **Procházení nefunkčních odkazů** — průběžný stav běžícího procházení: projité stránky, zkontrolované odkazy a nalezené nefunkční odkazy.
- **Nefunkční odkazy podle skenů** — vývoj napříč nedávnými běhy procházení.
- **Resource se zjištěními** — každý nefunkční odkaz `source → target`, s filtry a možností opravit interní odkazy přesměrováním.

Bez panelu poskytují stejná data příkazy `seo-pro:broken-links-*`.

## Proč je standardně vypnutý {#why-it-s-off-by-default}

Na rozdíl od pasivního vykreslování a hodnocení robot **odesílá síťové požadavky** a potřebuje určitou infrastrukturu. Zapnutí je proto záměrná volba, nikoli něco, co se má tiše spustit při instalaci:

- Jeho dvě hlavní tabulky jsou **jen připravené ke zkopírování**, stejně jako všechny migrace Pro. Migrace musíte spustit, než se na ně UI začne dotazovat. Typované kontroly používají také `seo_broken_link_inspections`.
- Procházení se **zařazuje do vyhrazené fronty** a vyžaduje **worker**. Bez workeru nikdy nepokročí.
- Potvrzení probíhá **napříč skeny**, viz níže. Funkce je navržená pro **plánované** běhy po dobu týdnů, nikoli pro okamžitý přínos v okamžiku zapnutí.

## Nastavení {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Potom spusťte migrace. `seo-pro:install` zkopíruje a spustí všechny migrace Pro; je idempotentní a lze jej bezpečně opakovat:

```bash
php artisan seo-pro:install
```

Spusťte **vyhrazený worker** pro frontu procházení. Samostatná fronta `seo-broken-links` zajišťuje, aby dlouhé procházení nezdržovalo úlohy obsluhující uživatele:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Ověřte zapojení: `seo:doctor` zkontroluje přepínač, tabulky a to, zda fronta používá skutečné připojení, nikoli `sync`. Ke každému problému uvede přesnou opravu:

```bash
php artisan seo:doctor
```

Úplné uspořádání více front s Redis, Supervisorem a vyhrazenými připojeními i ladění dávek popisuje [produkční nastavení](/cs/pro/production).

## Spuštění procházení {#running-a-crawl}

Spusťte je akcí **Skenovat nyní** v přehledu nebo bez panelu:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Oba příkazy procházení pouze **zařadí do fronty**; skutečnou práci provede worker.

## Jak se odkaz označí {#how-a-link-gets-flagged}

Odkaz se nahlásí jako nefunkční až po `seo-pro.broken_links.mark_broken_after_failures` **po sobě jdoucích procházeních**, která jej nedokážou načíst. Jakýkoli úspěch počitadlo vynuluje; výchozí hodnota je **3**. Jediný přechodný výpadek odkaz neoznačí. Proto má procházení běžet **plánovaně**, nikoli jednorázově. Při týdenní četnosti a výchozím prahu jej potvrdí tři neúspěšné běhy: přibližně dva týdny od prvního pozorování nebo až zhruba tři týdny od poruchy. Pro rychlejší potvrzení zvyšte četnost nebo snižte práh.

## Typované kontroly odkazů {#typed-link-inspections}

Vedle dostupnosti prochází každý odkaz sadou **typovaných kontrol**. Tyto kategorie čistoty URL označují nekonzistentní koncová lomítka, problematické kódování, řetězce přesměrování, href s `javascript:`, nefunkční kotvy na stránce, nevýstižné texty odkazů a další problémy. Každá kontrola má pevnou **závažnost**, `critical` · `warning` · `notice`, stejný slovník jako [problémy skenu](/cs/pro/scan-issues), takže je může pokrývat jedna podmínka CI. Za každý běh se zaznamenává do `seo_broken_link_inspections`. Na rozdíl od *zjištění* nefunkčního odkazu potvrzeného až několika po sobě jdoucími běhy je kontrola snímkem jednoho běhu. Objeví se **okamžitě při prvním procházení**, což CI potřebuje.

### Přehled kontrol {#inspection-reference}

| Kontrola | Závažnost | Co označí | Rozsah |
| --- | --- | --- | --- |
| `broken_link` | critical | Cíl vrátil HTTP ≥ 400 | každý odkaz |
| `redirect_chain` | notice · warning | Cíl je dostupný jen přes přesměrování; `warning` po překročení `redirect_chain_warning_hops` | každý odkaz |
| `link_unreachable` | notice | Nedostupný při tomto běhu: síťová chyba, časový limit či blokace; může být přechodné | každý odkaz |
| `insecure_link` | warning | Odkaz `http://` na webu `https`, oslabení přenosu | každý odkaz |
| `trailing_slash` | notice | Interní cesta porušuje deklarované pravidlo koncového lomítka; **vypnuto bez nastaveného `trailing_slash`** | interní |
| `double_slash_url` | warning | Interní cesta obsahuje `//`, prázdný segment | interní |
| `duplicate_query_param` | notice | Klíč dotazu se opakuje, `?a=1&a=2`; syntaxe pole `key[]` je vyňatá | interní |
| `non_ascii_url` | notice | Interní cesta má nekódované znaky mimo ASCII | interní |
| `uppercase_url` | notice | Interní cesta má velká písmena; posuďte samostatně poskytované varianty velikosti | interní |
| `underscore_in_url` | notice | Interní cesta používá podtržítka; pro SEO se upřednostňují spojovníky | interní |
| `javascript_link` | warning | Odkaz používá href `javascript:`, nikoli běžný cíl procházení | každá kotva |
| `missing_fragment` | warning | `#fragment` na téže stránce bez odpovídajícího `id`/`name` | stejná stránka |
| `non_descriptive_anchor` | notice | Obecný text odkazu jako „click here“, „read more“ nebo holá URL | každá kotva |
| `absolute_internal_link` | notice | Interní odkaz zapsaný absolutní URL místo cesty relativní ke kořeni | interní |

Kontroly čistoty URL, například koncového lomítka, velikosti písmen, kódování či dvojitého lomítka, se týkají jen **interních** odkazů. Styl URL cizího webu neurčujete vy. Kontroly přesměrování, chybové odpovědi, nedostupnosti a nezabezpečeného přenosu se týkají každého odkazu. Odkazy na vlastní trasy frameworku a statické prostředky se přeskakují, aby první běh nezaplavila hlášení; viz níže `exclude_paths` / `exclude_extensions`.

Každý odkaz se načte na **přesně zapsané URL**, pouze bez `#fragment`, nikoli v normalizovaném tvaru. Kanonické přesměrování serveru jako `/about/ → /about` se tak skutečně zjistí a projeví jako `redirect_chain`, místo aby je předem skryla normalizace. Kontroluje se každý odlišně zapsaný tvar odkazu na stránce, takže `/page#ok` i `/page#missing` nebo `/a//b` i `/a/b` se posoudí samostatně, nejen první. Podkladové *zjištění* nefunkčního odkazu přesto slučuje všechny aliasy cíle do jedné identity. Řádky kontrol se zaznamenávají podle `(page, target, inspection)`, takže cíl s několika nefunkčními kotvami má jeden řádek `missing_fragment` s příkladem, nikoli řádek pro každou kotvu.

### Nastavení kategorií {#tuning-the-taxonomy}

Vše je pod `seo-pro.broken_links.inspections`:

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

**Jedno pravidlo vypnete** odebráním jeho třídy z `rules`; **celou sadu** pomocí `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`. Dvě pravidla je vhodné znát předem:

- `trailing_slash` je **vypnuté, dokud neurčíte pravidlo** `'always'` / `'never'`. Web poskytující `/x` i `/x/` se stavem `200` nemá „nesprávný“ styl k označení. Kde server kanonizuje přesměrováním, už se to projeví jako `redirect_chain`.
- `absolute_internal_link` se vyvolá pro **každý** interní odkaz zapsaný absolutní URL. Pokud web takové URL používá záměrně, vznikne mnoho neškodných řádků úrovně `notice`. Odeberte pravidlo z `rules`, chcete-li je umlčet.

## Průběžná integrace {#continuous-integration}

Sken odkazů i [audit SEO](/cs/pro/scan-issues) mohou **označit sestavení za neúspěšné** a **zapsat artefakt reportu**, takže Rankbeam slouží i jako kontrola kvality v CI. `--fail-on-error` odpovídá úrovni `critical`, tedy nefunkčnímu odkazu či kritickému problému. `--fail-on-warning` selže při `critical` **nebo** `warning`. Samostatná úroveň „error“ neexistuje.

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

`--report=<file|dir>` zapíše artefakt; pro adresář odvodí název souboru. `--format` je `json`, výchozí, `md` nebo `html`. JSON je formát pro zpracování v pipeline; HTML je samostatná stránka k přiložení k běhu.

### GitHub Actions {#github-actions}

Robot načítá stránky přes HTTP, takže mu CI musí zpřístupnit dosažitelný obsah: lokálně obsluhovanou aplikaci níže nebo testovací URL přes `SEO_PRO_BROKEN_LINKS_BASE_URL`. Zaregistrujte modely a mapu webu, aby procházení mělo výchozí cíle.

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

## Plánování {#scheduling}

Zaregistrujte procházení i jeho údržbu v `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Přehled příkazů {#command-reference}

| Příkaz | Co dělá |
| --- | --- |
| `seo-pro:broken-links-scan` | Zařadí omezené procházení s pokračováním; `--scope=internal_only\|internal_and_external`, další výchozí cíle `--url=*` |
| `seo-pro:broken-links-status` | Souhrn posledního procházení, otevřená nefunkční zjištění a počty kontrol tohoto běhu; **podmínka CI**, `--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html` |
| `seo-pro:broken-links-cancel` | Zruší běžící nebo čekající procházení; `{run?}`, standardně poslední aktivní |
| `seo-pro:broken-links-recover` | Označí jako neúspěšné běhy opuštěné mrtvým workerem, s prošlou dobou přidělení práce workeru |
| `seo-pro:broken-links-prune` | Uplatní dobu uchovávání robotu na staré běhy a vyřešená zjištění |

## Ladění {#tuning}

Omezení procházení, tedy stránky na běh, odkazy na stránku, limity úloh, pevný časový rozpočet a šetrné prodlevy pro jednotlivé hostitele, jsou v `seo-pro.broken_links`. Výchozí hodnoty jsou konzervativní a konečné. Než je zvýšíte, přečtěte [tabulku ladění dávek v produkčním nastavení](/cs/pro/production).
