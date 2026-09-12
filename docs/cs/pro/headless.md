---
description: "Všechny funkce Pro — skenování, přesměrování i protokol 404 — fungují bez Filamentu. Přehled příkazů pro úplnou správu Pro prostřednictvím Artisanu."
---

# Použití bez panelu {#headless-usage}

Každá funkce Pro — skenování, přesměrování i záznam 404 — funguje bez panelu. Patří do jádra Pro a nepotřebuje Filament. Panel je pouze administrační rozhraní; tyto příkazy jej doplňují pro provoz bez něj.

## Přehled příkazů {#command-reference}

### Nastavení a kontrola stavu {#setup-health-check}

| Příkaz | Co dělá |
|---|---|
| `seo-pro:install` | Zkopíruje `config/seo-pro.php` a migrace Pro, spustí je a vypíše další kroky; `--no-migrate`, `--force` |
| `seo:doctor` | Jednorázová kontrola stavu: URL aplikace, tabulky Core a Pro, cíle skenování, mapa webu, fronty jednotlivých úloh, volitelné funkce i provozní stav. Každé upozornění obsahuje přesnou opravu; `--json` pro monitoring |

Dokumentovaný způsob instalace je `seo-pro:install`. Migrace Pro se musí zkopírovat do aplikace, protože je balíček automaticky nenačítá. Instalátor tak po samotném `composer require` vytvoří funkční schéma. Je idempotentní a lze jej kdykoli zopakovat.

`seo:doctor` neprovádí síťová volání a nikdy nevypisuje tajné hodnoty. Kontrola AI uvádí pouze, zda je nakonfigurovaná proměnná klíče *nastavená*. Validuje konfiguraci a nedávnou historii běhů; nedokáže prokázat, že externí cron nebo worker skutečně běží. Nenulový stav vrací jen při kritické chybě, například chybějící povinné tabulce. Vývojové prostředí na localhostu s upozorněními tedy stále skončí úspěšně. `--json` přidává každé kontrole stabilní `id` pro zpracování. Spouštějte jej hned po [instalaci](/cs/pro/installation) a v CI.

### Skenování {#scanning}

| Příkaz | Co dělá |
|---|---|
| `seo-pro:scan` | Zařadí úplný sken všech registrovaných cílů do fronty; `--sync` pro přímý běh. **Kontroly pro CI** `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=json\|md\|html` vyžadují `--sync` |
| `seo-pro:scan-status` | Souhrn nejnovějšího běhu a otevřené problémy od nejzávažnějších; `--limit=20`, `--severity=critical\|warning\|notice` |
| `seo-pro:scan-recover` | Označí běhy opuštěné zaniklým workerem fronty jako neúspěšné |
| `seo-pro:scan-prune` | Odstraní dokončené běhy a jejich problémy po uplynutí doby uchovávání |

### Robot pro nefunkční odkazy {#broken-link-crawler}

Ve výchozím nastavení je vypnutý. Zapněte `seo-pro.broken_links.enabled` a spusťte migrace jeho dvou tabulek; `seo-pro:install` je zkopíruje do aplikace. Procházení běží v omezených úlohách fronty. Pro jeho frontu spusťte vyhrazený worker. Ladění popisuje [Produkční provoz](/cs/pro/production).

| Příkaz | Co dělá |
|---|---|
| `seo-pro:broken-links-scan` | Zařadí omezené procházení s možností pokračování; `--scope=internal_only\|internal_and_external`, `--url=*` pro další počáteční URL |
| `seo-pro:broken-links-status` | Souhrn nejnovějšího procházení, otevřené nálezy a [typované kontroly](/cs/pro/broken-links#typed-link-inspections) tohoto běhu. **Kontroly pro CI**: `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=` |
| `seo-pro:broken-links-cancel` | Zruší běžící nebo čekající procházení; `{run?}`, standardně nejnovější aktivní běh |
| `seo-pro:broken-links-recover` | Označí procházení opuštěná zaniklým workerem jako neúspěšná podle zastaralé rezervace běhu |
| `seo-pro:broken-links-prune` | Uplatní pravidla uchovávání dat robota: staré běhy a vyřešené nálezy |

### Přesměrování a 404 {#redirects-404s}

| Příkaz | Co dělá |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Vytvoří pravidlo přesměrování; `--code=301`, `--regex`, `--no-preserve-query`, `--note=` |
| `seo-pro:404-list` | Zaznamenané 404 od nejnavštěvovanějších; `--status=new\|ignored\|redirected\|all`, `--limit=20` |
| `seo-pro:redirects-flush-hits` | Zapíše počítadla návštěv přesměrování shromážděná v mezipaměti do databáze při `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Odstraní zastaralé záznamy 404 a uplatní limit řádků |

### Kontrolní seznam stránky {#on-page-checklist}

| Příkaz | Co dělá |
|---|---|
| `seo-pro:checklist {model} {id}` | Kontrolní seznam pass/warn/fail pro jeden model se zohledněním klíčových slov; `--json`, `--strict`, `--locale=`. Viz [Kontrolní seznam stránky](/cs/pro/on-page-checklist) |

Stejný seznam je dostupný jako `SeoPro::checklistFor($model)`. Jde o redakční práci s umístěním klíčových slov, délkou, obrázky a interními odkazy, **nikoli** [SEO skóre](/cs/pro/scoring).

### Search Console (pouze čtení) {#search-console-read-only}

| Příkaz | Co dělá |
|---|---|
| `seo-pro:search-console` | Stránky s otevřenými problémy **a** návštěvností z vyhledávání, od největší příležitosti ke zlepšení; `--view=attention` je výchozí |
| `seo-pro:search-console --view=pages` | Hlavní stránky podle zobrazení, kliknutí, CTR a pozice |
| `seo-pro:search-console --view=queries` | Hlavní vyhledávací dotazy; `--days=`, `--limit=`, `--json` |

Stejné metriky poskytuje `SeoPro::searchConsole()`; viz [Search Console](/cs/pro/search-console). Ve výchozím nastavení vypnuto; výhradně pro čtení.

### Asistence AI {#ai-assist}

| Příkaz | Co dělá |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Návrhy titulku a popisu ve formátu JSON; `--field=title\|description\|all`. Viz [Asistence AI](/cs/pro/ai-assist) |
| `seo-pro:ai-suggest --issue={id}` | Srozumitelné vysvětlení opravy problému ze skenu ve formátu JSON |

### Vyřešení 404 jedním krokem {#resolving-a-404-in-one-step}

`--from-404={path}` je protějšek akce *Vytvořit přesměrování* monitoru 404 pro použití bez panelu. Vytvoří pravidlo **a** označí odpovídající záznam protokolu jako přesměrovaný s vazbou na nové pravidlo:

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

Příkaz používá stejné validátory jako formulář Filamentu. Neplatné regulární výrazy, příliš dlouhé hodnoty a externí cíle mimo seznam povolených odmítne před jakýmkoli zápisem.

## Doporučený plán {#recommended-schedule}

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

Každý výše uvedený opakovaný příkaz má doporučený interval v průvodci [Produkční provoz](/cs/pro/production). Ten popisuje také uspořádání front, nastavení workerů, pravidla opakování a obnovy, uchovávání dat a strukturovanou **telemetrii** dokončených běhů: načtené stránky, zkontrolované odkazy, zablokované URL, dobu trvání a zpoždění fronty.

## Co vyžaduje rozhraní Filament? {#what-needs-the-filament-ui}

Žádná funkce jádra Pro. Úplné jádro — skenování, sledování problémů, párování přesměrování, protokol 404, promazávání a obnova — je s Filamentem i bez něj totožné. Panel přidává *pohledy*: přehled s živým průběhem skenování a statistikami závažnosti, procházení problémů s filtry a dialogy jednotlivých stránek, tlačítka ignorování a znovuotevření, formuláře správy přesměrování a tabulku 404 s akcí jedním kliknutím. Ignorování a znovuotevření problémů zatím nemá vlastní příkaz. Použijte panel nebo model `SEOScanIssue` s `markIgnored()` / `reopen()` v Tinkeru či vlastním kódu.
