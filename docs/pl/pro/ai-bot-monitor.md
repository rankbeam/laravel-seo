---
description: "Rejestruj zaobserwowane żądania robotów AI: które boty odwiedzały witrynę, jak często oraz ostatni URL i status każdego z nich. Obserwacyjna część sterowania robotami AI."
---

# Monitor botów AI {#ai-bot-monitor}

Żądania są przypisywane przez **dopasowanie user-agenta**, a nie zweryfikowaną tożsamość bota. Monitor zapisuje zaobserwowane żądania; user-agent może zostać sfałszowany.

Funkcja [sterowania robotami AI](/pl/guide/ai-crawlers) w rdzeniu określa, co `robots.txt` *przekazuje* robotom AI. **Monitor botów AI** w Pro stanowi drugą część: rejestruje, co faktycznie *zrobiły* — które roboty AI pobierały witrynę, jak często oraz jaki był ostatni URL i status HTTP każdego z nich.

Wykorzystuje mechanizmy monitora 404: globalny middleware wykonywany po odpowiedzi, model tworzący lub aktualizujący rekord z licznikiem trafień i te same zasady prywatności. Kluczem jest jednak **bot**, nie ścieżka, a zapis następuje przy **każdym** statusie odpowiedzi. Obejmuje dokładnie te roboty AI, które monitor 404 celowo wyklucza. Identyfikacja korzysta z `AiCrawlerRegistry` rdzenia, więc zasady robots.txt i obserwowany ruch mają jedno źródło prawdy.

::: tip Wymaga rdzenia ≥ 3.3
Monitor identyfikuje boty za pomocą katalogu robotów AI rdzenia ([`SEO::aiCrawlers()`](/pl/guide/ai-crawlers)). Ze starszym rdzeniem pozostaje nieaktywny.
:::

## Włączanie {#enabling-it}

Domyślnie wyłączony. Po włączeniu globalny middleware zapisuje pasujące roboty po każdej odpowiedzi; nigdy nie opóźnia strony:

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

To wszystko. Middleware jest rejestrowany automatycznie; wyłączysz to przez `ai_bots.auto_register_middleware`. Tworzony lub aktualizowany jest jeden wiersz na znanego bota, więc rozmiar tabeli ogranicza katalog.

## Odczytywanie logu {#reading-the-log}

### Bez panelu {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Każdy wiersz udostępnia `bot`, `label`, `operator`, `purpose`, `hit_count`, `last_path`, `last_status`, `first_seen_at` i `last_seen_at`.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Po zarejestrowaniu wtyczki Pro w grupie nawigacji SEO pojawia się tabela **Boty AI**: bot, operator, cel, trafienia, ostatni status, ostatnia ścieżka i ostatnia obserwacja. Można ją filtrować według celu; służy tylko do odczytu.

## Prywatność {#privacy}

Obowiązują te same zasady co w monitorze 404: **domyślnie nie zapisuje się adresów IP**. Opcjonalne `ai_bots.hash_ip` zapisuje wyłącznie sha256 z kluczem (`ip_hash`); surowy IP nigdy nie jest zapisywany.

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

## Metryki okresowe (dzienne zestawienia) {#period-metrics-daily-buckets}

Tabela sum od początku rejestracji ma jeden wiersz na bota. Nadaje się do zestawienia aktywności, ale nie odpowie, *ile żądań wysłał* lub *ile unikalnych URL-i* odwiedził bot **w danym okresie**. Przy włączonym `daily_enabled` (domyślnie) każde trafienie jest dodatkowo zapisywane w dziennym zestawieniu dla ścieżki (`seo_ai_bot_daily`), dzięki czemu [raport pod własną marką](/pl/pro/reports) pokazuje **rzeczywiste** liczby okresowe: trafienia od ostatniego raportu i unikalne URL-e w okresie, zamiast różnicy sum historycznych.

Ograniczony rozmiar, dla którego log sum miał jeden wiersz na bota, pozostaje zachowany:

- **Limit unikalnych ścieżek na bota i dzień** (`daily_max_paths`). Po jego przekroczeniu kolejne nowe ścieżki bota trafiają do jednego zbiorczego wpisu nadmiarowego. Dzienna suma trafień pozostaje dokładna, a liczba wierszy nie rośnie bez ograniczeń. Liczba unikalnych URL-i, która osiągnęła limit, jest pokazywana jako „N+”.
- **Okres przechowywania danych** (`daily_retention_days`), egzekwowany przez `seo-pro:ai-bots-prune`.

Ustaw `daily_enabled` na `false`, aby zachować tylko zestawienie sum od początku rejestracji. Raport wraca wtedy do różnicy względem migawki poprzedniego raportu dla wartości „od ostatniego”, a istniejące dzienne wpisy są ignorowane, więc nieaktualna tabela nigdy nie jest odczytywana.

Wartości okresowe mają **rozdzielczość dzienną**: „od ostatniego raportu” obejmuje całe dni od dnia poprzedniego raportu, więc trafienie z tego dnia może przypadać przed lub po dokładnej chwili generowania. Przy zwykłej częstotliwości dziennej, tygodniowej lub miesięcznej ta niedokładność granicy jest niewielka.

## Od obserwacji do sterowania {#turning-observation-into-control}

Monitor pokazuje, *kto* odwiedza witrynę, a [sterowanie robotami AI](/pl/guide/ai-crawlers) w rdzeniu określa, *co wolno im pobierać*. Pojawił się bot treningowy, którego dostęp chcesz ograniczyć?

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Pamiętaj, że niektóre boty według dokumentacji nie respektują `robots.txt`. Monitor pozwala je zauważyć i zdecydować o blokadzie na brzegu sieci (firewall / WAF / Cloudflare).
