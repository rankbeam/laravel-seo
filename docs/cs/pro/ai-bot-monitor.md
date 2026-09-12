---
description: "Zaznamenávejte, co AI roboti na webu skutečně dělali: kdo jej načetl, jak často a jaká byla poslední URL a stav HTTP. Pozorování provozu doplňující řízení přístupu AI robotů."
---

# Monitor AI robotů {#ai-bot-monitor}

Požadavky se přiřazují podle **shody user-agentu**, nikoli ověřené identity robota. Monitor zaznamenává pozorované požadavky; user-agent lze podvrhnout.

Funkce [řízení přístupu AI robotů](/cs/guide/ai-crawlers) v Core určuje, co `robots.txt` robotům *sděluje*. **Monitor AI robotů** v Pro doplňuje druhou stranu: zaznamenává, co skutečně *udělali*, tedy kteří AI roboti načetli web, jak často a jakou URL s jakým stavem HTTP navštívili naposledy.

Využívá stejné mechanismy jako monitor 404: globální middleware se zpracováním po odpovědi, model s vložením či aktualizací a počítáním návštěv a stejný přístup k soukromí. Klíčem je ale **robot** místo cesty a zaznamenává se **jakýkoli** stav odpovědi. Jde právě o AI roboty, které monitor 404 záměrně vylučuje. Identifikace používá `AiCrawlerRegistry` z Core, takže pravidla robots.txt a pozorovaný provoz sdílejí jeden zdroj pravdy.

::: tip Vyžaduje Core ≥ 3.3
Monitor identifikuje roboty pomocí katalogu AI robotů v Core ([`SEO::aiCrawlers()`](/cs/guide/ai-crawlers)). Se starším Core zůstává nečinný.
:::

## Zapnutí {#enabling-it}

Ve výchozím nastavení je vypnutý. Po zapnutí globální middleware zaznamenává odpovídající roboty po každé odpovědi, takže neodkládá odeslání stránky:

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

To je vše. Middleware se registruje automaticky; vypnete to pomocí `ai_bots.auto_register_middleware`. Pro každého známého robota se vkládá nebo aktualizuje jediný řádek, takže velikost tabulky omezuje katalog.

## Čtení protokolu {#reading-the-log}

### Bez panelu {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Každý řádek vystavuje `bot`, `label`, `operator`, `purpose`, `hit_count`, `last_path`, `last_status`, `first_seen_at` a `last_seen_at`.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Po registraci pluginu Pro se v navigační skupině SEO objeví tabulka **AI boti**: robot, provozovatel, účel, počet návštěv, poslední stav, poslední cesta a poslední návštěva. Lze ji filtrovat podle účelu a slouží pouze ke čtení.

## Soukromí {#privacy}

Platí stejný přístup jako u monitoru 404: **ve výchozím nastavení se IP adresa neukládá.** Volitelné `ai_bots.hash_ip` ukládá pouze klíčovaný hash sha256 (`ip_hash`), nikdy samotnou IP adresu.

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## Metriky za období (denní záznamy) {#period-metrics-daily-buckets}

Celková tabulka uchovává jeden řádek na robota. Hodí se pro žebříček, ale neodpoví na otázku, *kolikrát* robot web navštívil nebo *kolik různých URL* načetl **v konkrétním období**. Při zapnutém `daily_enabled`, což je výchozí nastavení, se každá návštěva zapisuje také do záznamu pro daný den a cestu (`seo_ai_bot_daily`). [Report s vlastní značkou](/cs/pro/reports) tak ukazuje **skutečné** údaje za období: návštěvy od posledního reportu a různé URL v tomto období, místo rozdílu celkových počítadel.

Omezená velikost, kvůli které měl celkový protokol jeden řádek na robota, zůstává zachována:

- **Limit různých cest** na robota a den (`daily_max_paths`). Další nové cesty se po jeho dosažení sloučí do jediného souhrnného záznamu přebytku. Celkový denní počet návštěv tak zůstává přesný, ale počet řádků neroste neomezeně. Počet různých URL, který dosáhl limitu, se zobrazí jako „N+“.
- **Doba uchovávání** (`daily_retention_days`), kterou uplatňuje `seo-pro:ai-bots-prune`.

Nastavte `daily_enabled` na `false`, pokud chcete pouze celkový žebříček. Report pak pro údaj „od posledního reportu“ použije rozdíl vůči předchozímu snímku a existující denní záznamy ignoruje, aby nečetl zastaralou tabulku.

Údaje za období mají **rozlišení na dny**. „Od posledního reportu“ počítá celé dny počínaje dnem předchozího reportu, takže návštěva z tohoto dne mohla nastat před jeho přesným časem vytvoření i po něm. Při běžném denním, týdenním nebo měsíčním intervalu je tato nepřesnost hranice zanedbatelná.

## Od pozorování k řízení přístupu {#turning-observation-into-control}

Monitor ukazuje, *kdo* web prochází. [Řízení přístupu AI robotů](/cs/guide/ai-crawlers) v Core určuje, *co smí načítat*. Objevil se trénovací robot, kterého chcete omezit?

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Pamatujte, že někteří roboti podle dokumentace nerespektují `robots.txt`. Monitor vám pomůže je rozpoznat a rozhodnout, zda je blokovat na okraji infrastruktury pomocí firewallu, WAF nebo Cloudflare.
