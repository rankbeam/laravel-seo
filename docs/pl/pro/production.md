---
description: "Uruchamiaj Pro na większą skalę: osobne kolejki, harmonogram, zasady ponowień i odzyskiwania, retencja oraz telemetria — architektura niezależna od Filament, używana w produkcyjnej instalacji z około 900 stronami."
---

# Konfiguracja produkcyjna {#production-setup}

Codzienne zadania Pro — skanowanie witryny, sprawdzanie niedziałających linków, opcjonalny zapis liczników użycia przekierowań i usuwanie starych wpisów 404 — korzystają z kolejki i harmonogramu Laravel. To główny przewodnik po pracy na większą skalę: osobnych kolejkach, harmonogramie, zasadach ponowień i odzyskiwania, retencji oraz telemetrii do monitorowania całości. Opisuje architekturę produkcyjnej instalacji z około 20 tys. wizyt dziennie i 900 stronami, tak aby można było ją odtworzyć.

Wszystko tutaj jest **niezależne od Filament**. Silnik, polecenia, kolejki i telemetria działają identycznie z panelem i bez niego. Filament dodaje widoki, ale nie zmienia sposobu planowania ani przetwarzania pracy.

[[toc]]

## Bezpieczna kolejność wdrożenia {#safe-rollout-order}

Wykonuj kroki w podanej kolejności. Każdy można sprawdzić przed przejściem do następnego:

1. **Instalacja** — opublikuj konfigurację i migracje, a następnie je uruchom:

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` publikuje `config/seo-pro.php` i migracje Pro, a następnie uruchamia `migrate`. Migracje Pro wymagają **publikacji** (pakiet nigdy nie ładuje ich automatycznie), więc ten krok zamienia samo `composer require` w działający schemat bazy. Jest idempotentny: możesz powtórzyć go w dowolnym momencie. Dodaj `--force`, aby nadpisać opublikowane pliki, lub `--no-migrate`, aby opublikować je bez migracji.

2. **Zarejestruj cele skanowania** w dostawcy usług (`AppServiceProvider::boot()`):

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Sprawdź** konfigurację przed włączeniem pracy w tle:

   ```bash
   php artisan seo:doctor
   ```

   Usuń przyczynę każdego ostrzeżenia. Każde podaje dokładne polecenie lub wiersz konfiguracji. W CI dodaj `--json` i korzystaj ze stabilnych identyfikatorów kontroli.

4. **Skonfiguruj kolejki i harmonogram** (poniżej), uruchom proces kolejki i dodaj wpis cron dla `schedule:run`.

5. **Funkcje opcjonalne włącz na końcu**. Robot sprawdzający niedziałające linki, pomoc AI i Search Console są domyślnie wyłączone. Robot wymaga migracji swoich tabel (krok 1 już je opublikował) i osobnego procesu kolejki (poniżej).

**Aktualizacja do Pro 2.41.0:** wstrzymaj procesy kolejki skanowania, opublikuj migracje przez `php artisan vendor:publish --tag=seo-pro-migrations --force`, uruchom `php artisan migrate`, a następnie zrestartuj procesy i uruchom `php artisan seo:doctor`. Wymagane są nowa tabela `seo_scan_target_completions` i kolumna `seo_scan_runs.target_tracking`. Potwierdzenia dla każdej pary przebieg/cel zapobiegają zawyżaniu liczników przez zduplikowane wyniki końcowe; wygrywa pierwszy zaakceptowany wynik. Stare przebiegi w kolejce bez przetworzonych celów są kontynuowane. Częściowo przetworzone przebiegi sprzed aktualizacji zachowują historię, ale przy kolejnym dostarczeniu kończą się z instrukcją wykonania nowego skanowania. Cele, które wyczerpały próby, ponów w nowym przebiegu. Przy wycofywaniu aktualizacji zatrzymaj procesy kolejki i przywróć kod przed wycofaniem migracji. Zachowaj kopię bazy sprzed aktualizacji, jeśli chcesz też cofnąć późniejsze skanowania.

## Osobne kolejki dla poszczególnych zadań {#dedicated-queues-per-workload}

Długie skanowanie witryny lub linków nigdy nie powinno blokować zadań użytkowników, takich jak poczta czy powiadomienia. Każdemu rodzajowi pracy SEO przydziel osobną kolejkę i proces roboczy.

Proces skanowania i robot sprawdzający niedziałające linki odczytują osobne ustawienia kolejki:

| Rodzaj pracy | Konfiguracja | Zmienna środowiskowa | Domyślna kolejka |
|---|---|---|---|
| Zadania skanowania stron | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | kolejka domyślna |
| Zadania sprawdzania niedziałających linków | `seo-pro.broken_links.queue.name` (+ `.connection`) | `SEO_PRO_BROKEN_LINKS_QUEUE` (+ `_CONNECTION`) | `seo-broken-links` |

### Przykład Redis (architektura produkcyjna) {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Uruchom proces roboczy dla każdej kolejki. Każdy jest osobnym procesem lub programem Supervisor:

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

`--timeout` procesu robota musi przekraczać `seo-pro.broken_links.batch.hard_time_budget_seconds` (domyślnie 180) powiększone o limit czasu HTTP, aby zadanie nie zostało przerwane w trakcie zapisywania stanu partii. Zadanie ustawia własne `$timeout` na tę sumę, więc dopasuj do niej opcję procesu. Dla robota użyj `--tries=1`: przerwane zadanie odzyskuje kolejna kontynuacja (lub `seo-pro:broken-links-recover`), więc ponowienia na poziomie kolejki są zbędne.

`seo:doctor` podaje kolejkę każdego rodzaju pracy i ostrzega, gdy rozstrzyga się ona do `sync`, co wykonałoby pracę bezpośrednio, blokując obsługę.

## Harmonogram {#scheduler}

Laravel 11, 12 i 13 definiują harmonogram w **`routes/console.php`**. Metoda `schedule()` w `app/Console/Kernel.php` występuje tylko w aplikacjach zaktualizowanych z Laravel 10; jeśli Twoja nadal ją ma, umieść tam te same wpisy. Dodaj jeden systemowy wpis cron, aby harmonogram uruchamiał się co minutę:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Następnie zarejestruj każde cykliczne polecenie z zalecaną częstotliwością:

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

Zalecane częstotliwości:

| Polecenie | Częstotliwość | Powód |
|---|---|---|
| `seo:sitemap` | codziennie | Odświeżenie mapy witryny na podstawie bieżących treści |
| `seo-pro:scan` | co tydzień (codziennie przy częstych zmianach treści) | Ponowny audyt wszystkich celów |
| `seo-pro:scan-recover` | co godzinę | Odzyskanie przebiegów przerwanych przez awarię procesu |
| `seo-pro:scan-prune` | codziennie | Zastosowanie okresu retencji przebiegów skanowania |
| `seo-pro:redirects-flush-hits` | co 5 minut, tylko gdy `redirects.hits.flush_immediately=false` | Zapis liczników trafień zebranych w pamięci podręcznej do bazy |
| `seo-pro:404-prune` | codziennie | Ograniczenie logu 404 według retencji i limitu wierszy |
| `seo-pro:404-recheck` | codziennie | Ponowne pobranie otwartych ścieżek 404 i oznaczenie jako odzyskanych tych naprawionych u źródła (teraz 200) |
| `seo-pro:broken-links-scan` | co tydzień | Ponowne sprawdzenie niedziałających linków (potwierdzanie między skanowaniami) |
| `seo-pro:broken-links-recover` | co godzinę | Odzyskanie przebiegów robota przerwanych przez awarię procesu |
| `seo-pro:broken-links-prune` | codziennie | Zastosowanie okresów retencji robota |

`seo-pro:scan` i `seo-pro:broken-links-scan` jedynie **dodają pracę do kolejki**; wykonuje ją proces roboczy. Polecenia odzyskiwania i usuwania starych danych wykonują się bezpośrednio i są lekkie.

::: tip Potwierdzanie niedziałających linków między skanowaniami
Link zostaje oznaczony jako niedziałający dopiero po `seo-pro.broken_links.mark_broken_after_failures` **kolejnych skanowaniach**, w których nie udało się go osiągnąć. Każdy sukces zeruje licznik. Dlatego robot działa według harmonogramu, a nie jednorazowo: jedna przejściowa awaria nigdy nie oznacza linku jako niedziałającego. Przy domyślnej wartości 3 cotygodniowe skanowanie potwierdzi problem po około dwóch tygodniach od pierwszej nieudanej obserwacji lub do około trzech tygodni od wystąpienia awarii. Zwiększ częstotliwość lub obniż próg, jeśli potrzebujesz szybszego potwierdzenia.
:::

## Dostrajanie partii robota sprawdzającego linki {#batch-tuning-broken-link-crawler}

Robot wykonuje wiele zadań o ograniczonym zakresie, które same wysyłają kolejne zadania kontynuacji. Domyślne limity są skończone. Dopasuj je do wydajności witryny i sprawdzanych hostów w `seo-pro.broken_links`:

| Klucz | Wartość domyślna | Co ogranicza |
|---|---|---|
| `max_pages_per_run` | `2000` | Strony pobrane w całym przebiegu. `null` = jawne włączenie braku limitu (nigdy domyślnie) |
| `max_links_per_page` | `200` | Linki sprawdzane na stronę |
| `max_total_links` | `null` | Opcjonalny globalny limit sprawdzeń linków w całym przebiegu |
| `batch.max_pages_per_job` | `50` | Strony na zadanie w kolejce |
| `batch.max_links_per_job` | `1500` | Sprawdzenia linków na zadanie w kolejce |
| `batch.hard_time_budget_seconds` | `180` | Po tym czasie zadanie **nie rozpoczyna nowego pobierania** i wysyła kontynuację |
| `batch.dispatch_delay_seconds` | `1` | Opóźnienie między zadaniami kontynuacji |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Limity pojedynczego żądania |
| `http.max_response_bytes` | dziedziczy `seo-pro.http.max_response_bytes` | Strumieniowy limit treści odpowiedzi dla stron i sprawdzanych celów |
| `seed.max_response_bytes` | dziedziczy limit robota / współdzielony limit HTTP | Surowe bajty XML mapy witryny / `.gz` pobierane przy ustalaniu adresów początkowych |
| `seed.max_inflated_bytes` | dziedziczy limit adresów początkowych / robota / wspólny | Rozpakowane bajty przyjmowane z mapy witryny `.gz` |
| `http.per_host_delay_ms` | `0` | Przerwa między sprawdzeniami, aby ograniczać obciążenie hostów (zwiększ dla `internal_and_external`) |

Utrzymuj `batch.hard_time_budget_seconds` wyraźnie poniżej `--timeout` procesu robota. Trwającego żądania nie można przerwać w połowie; ogranicza je `http.timeout`. Dlatego limit procesu = budżet zadania + limit HTTP + zapas.

Dla skanowania `internal_and_external` rozszerz `seo-pro.http.scope` (lub `seo-pro.http.allowed_hosts`), aby SsrfGuard dopuszczał sprawdzenia wychodzące, i zwiększ `http.per_host_delay_ms`, aby nie wysyłać zbyt wielu żądań do zewnętrznego hosta. `seo:doctor` ostrzega, gdy zakres robota jest zewnętrzny, ale zakres zabezpieczenia blokowałby każde sprawdzenie.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Jeden program na kolejkę. Przykładowy `/etc/supervisor/conf.d/app-workers.conf`:

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

`stopwaitsecs` musi przekraczać `--timeout` procesu roboczego, aby łagodny restart nie przerywał zadania w środku partii.

### Horizon {#horizon}

Jeśli używasz Horizon, zdefiniuj osobnego nadzorcę dla każdego rodzaju pracy w `config/horizon.php` i powierz mu zarządzanie procesami zamiast Supervisor:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Ponowienia i obsługa niepowodzeń {#retry-failure-handling}

Zadanie celu skanowania ma własne zasady ponowień z konfiguracji. **Nie** polega na `--tries` procesu kolejki:

| Klucz | Wartość domyślna | Znaczenie |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Próby na zadanie celu |
| `seo-pro.scan.backoff` | `30` | Sekundy między próbami |
| `seo-pro.scan.timeout` | `300` | Limit czasu zadania celu (blokada nakładania wygasa po tym limicie + 60) |

Zadanie, które wyczerpie ponowienia, zapisuje cel jako **nieudany**, a przebieg nadal się kończy (`partial` lub `failed`). Obsłużone niepowodzenia celów nie pozostawiają przebiegu w stanie `running`. Proces zabity przed zapisaniem stanu nadal wymaga opisanej poniżej procedury odzyskiwania. Niepowodzenia trafiają do standardowej tabeli `failed_jobs`. Zarządzaj nimi w zwykły sposób:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Zaplanuj `queue:prune-failed` obok zadań SEO, aby ograniczyć rozmiar tej tabeli:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

Robot sprawdzający linki używa `--tries=1`. Przerwane zadanie odzyskuje kolejna kontynuacja (sygnał dzierżawy staje się nieaktualny) lub `seo-pro:broken-links-recover`, więc ponowienia kolejki tylko powielałyby pracę.

## Odzyskiwanie {#recovery}

Śmierć procesu w trakcie zadania to przypadek, którego ewidencja postępu nie naprawi samodzielnie. Obsługują go dwie procedury; zaplanuj obie **co godzinę**:

- `seo-pro:scan-recover` — oznacza jako nieudane przebiegi skanowania stron bez postępu przez `seo-pro.scan.recovery.stuck_scan_timeout_hours` (domyślnie 2).
- `seo-pro:broken-links-recover` — odzyskuje przebiegi robota z nieaktualnym sygnałem dzierżawy (`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, domyślnie 2), oznaczając je jako nieudane i zwalniając miejsce dla jednego aktywnego przebiegu na zakres.

`seo:doctor` pokazuje to jako **dowody niedawnej aktywności**. Gdy skanowanie jest już używane, zgłasza zatrzymane przebiegi i wskazuje polecenie odzyskiwania. Nie potwierdzi, że cron rzeczywiście działa — żadne polecenie tego nie potrafi. Raportuje to, co wynika z historii przebiegów.

## Retencja {#retention}

Ograniczaj rozmiar tabel. Wartości domyślne (wszystkie w `seo-pro.*`; `null` wyłącza dane usuwanie):

| Dane | Konfiguracja | Wartość domyślna | Polecenie |
|---|---|---|---|
| Przebiegi skanowania (i problemy) | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| Log 404 | `monitor_404.retention_days` (+ `max_rows` `10000`) | `90` | `seo-pro:404-prune` |
| Przebiegi robota | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Rozwiązane problemy linków | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Telemetria operacyjna {#operational-telemetry}

Każdy zakończony przebieg — zarówno skanowania stron, **jak i** robota sprawdzającego linki — emituje jeden ustrukturyzowany wpis zakończenia przez mechanizm logowania. Otrzymujesz historię metryk bez panelu. Dane zawierają wyłącznie liczniki i czasy, bez URL-i, treści odpowiedzi, nagłówków i danych odwiedzających:

| Metryka | Skanowanie | Robot |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (cele odrzucone przez ochronę SSRF) | — | ✓ |
| `transient_failures` (błędy sieci, sprawdzane ponownie w następnym skanowaniu) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (od kolejki do pierwszej partii) | ✓ | ✓ |

Skonfiguruj ją w `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Skieruj `channel` do osobnego kanału logowania, aby przesyłać wpisy do wybranego systemu (Loki / Datadog / CloudWatch) bez mieszania ich z logami aplikacji:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Aby obsługiwać je szerzej, subskrybuj zdarzenia bezpośrednio. Każde udostępnia te same dane `metrics()`:

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

Telemetria działa w miarę możliwości: błędnie skonfigurowany kanał nigdy nie może spowodować niepowodzenia skanowania.

## Wdrożenie niezależne od Filament {#filament-independent-deployment}

Nic na tej stronie nie wymaga panelu. Silnik, każde polecenie, kolejki, harmonogram, odzyskiwanie, retencja i telemetria działają identycznie bez panelu. Panel Filament (`SeoProPlugin`) dodaje tylko **widoki**: postęp skanowania na żywo, tabelę problemów, CRUD przekierowań, monitor 404 i panel niedziałających linków. Wdróż silnik i obsługuj go przez CLI i harmonogram. Panel możesz dodać później lub wcale, bez migracji i ponownego wykonywania pracy. Pełną listę poleceń zawiera [użycie bez panelu](/pl/pro/headless).
