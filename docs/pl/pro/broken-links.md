---
description: "Robot z limitami i możliwością wznowienia, który zapisuje niedziałające linki: martwe trasy wewnętrzne naprawiane przekierowaniem jednym kliknięciem i opcjonalnie niedziałające linki zewnętrzne. Domyślnie wyłączony."
---

# Robot sprawdzający niedziałające linki {#broken-link-crawler}

**Robot z limitami i możliwością wznowienia** przechodzi przez witrynę, podąża za linkami na każdej stronie i zapisuje te, które nie działają. Obejmuje to niedziałające linki **wewnętrzne** (martwe trasy na Twoim hoście, które można naprawić przekierowaniem jednym kliknięciem) i opcjonalnie **zewnętrzne**. Jest **domyślnie wyłączony**.

Trzy założenia konstrukcyjne:

- **Ograniczony zakres i możliwość wznowienia.** Przebieg składa się z wielu małych zadań kolejki, każde z limitem niewielkiej liczby stron. Wysyłają one kontynuację, aż przebieg się zakończy lub osiągnie limity. Cały przebieg również ma limit: domyślnie 2000 stron. `null` jawnie włącza brak limitu i nigdy nie jest wartością domyślną; pozostałe limity partii i czasu nadal obowiązują. Dopasuj limity i opóźnienia do witryny oraz wydajności serwera.
- **Bezpieczne ustawienia domyślne.** Domyślny zakres `internal_only` sprawdza tylko linki na Twoim hoście, bez żądań do stron trzecich. Każde pobranie, wewnętrzne lub zewnętrzne, przechodzi przez wspólny **SsrfGuard**: listę dozwolonych schematów, zakres hostów i odrzucanie adresów prywatnych. Sprawdzanie linków zewnętrznych wymaga włączenia i nadal podlega zabezpieczeniom.
- **Osobno od oceny SEO.** Wyniki mają własne tabele i nigdy nie zapisują do `seo_scan_issues` ani nie zmieniają oceny 0–100. Ocena strony pozostaje taka sama niezależnie od niedziałających linków wychodzących. Są one zagadnieniem operacyjnym śledzonym osobno.

## Co otrzymujesz {#what-you-get}

W panelu Filament, tylko po włączeniu:

- **Podsumowanie niedziałających linków** — liczby otwartych problemów linków wewnętrznych i zewnętrznych oraz ostatni przebieg, z przejściem do tabeli wyników.
- **Przebieg sprawdzania linków** — postęp trwającego przebiegu na żywo: odwiedzone strony, sprawdzone linki i znalezione niedziałające linki.
- **Niedziałające linki na skanowanie** — trend ostatnich przebiegów robota.
- **Zasób wyników** — każdy niedziałający link `source → target`, z filtrowaniem i możliwością naprawienia linków wewnętrznych przez przekierowanie.

Bez panelu te same dane udostępniają polecenia `seo-pro:broken-links-*`.

## Dlaczego jest domyślnie wyłączony {#why-it-s-off-by-default}

W przeciwieństwie do pasywnego renderowania i oceniania robot **wykonuje żądania sieciowe** i potrzebuje infrastruktury. Dlatego jego włączenie jest świadomą decyzją, a nie czymś, co powinno rozpocząć się bez powiadomienia przy instalacji:

- Jego dwie główne tabele wymagają **publikacji**, jak każda migracja Pro. Migracje trzeba wykonać, zanim interfejs zacznie je odpytywać. Kontrole o określonych typach korzystają też z `seo_broken_link_inspections`.
- Przebieg trafia do **osobnej kolejki** i potrzebuje **procesu roboczego**. Bez niego nie postępuje.
- Potwierdzanie następuje **między skanowaniami** (poniżej), więc robot ma działać **według harmonogramu** przez tygodnie, a nie dawać natychmiastowy wynik zaraz po włączeniu.

## Konfiguracja {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Następnie wykonaj migracje. `seo-pro:install` publikuje i uruchamia wszystkie migracje Pro; jest idempotentny i można bezpiecznie go powtarzać:

```bash
php artisan seo-pro:install
```

Uruchom **osobny proces roboczy** dla kolejki robota. Osobna kolejka (`seo-broken-links`) zapewnia, że długi przebieg nie blokuje zadań obsługujących użytkowników:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Sprawdź konfigurację. `seo:doctor` sprawdza opcję włączenia, tabele oraz to, czy kolejka robota używa rzeczywistego połączenia innego niż `sync`, i dla każdego problemu podaje dokładną poprawkę:

```bash
php artisan seo:doctor
```

Pełną architekturę wielu kolejek (Redis, Supervisor, osobne połączenia) i dostrajanie partii opisuje [konfiguracja produkcyjna](/pl/pro/production).

## Uruchamianie robota {#running-a-crawl}

Uruchom go akcją **Skanuj teraz** w panelu lub przez CLI:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Oba polecenia tylko **dodają przebieg do kolejki**. Właściwą pracę wykonuje proces roboczy.

## Kiedy link zostaje oznaczony {#how-a-link-gets-flagged}

Link jest zgłaszany jako niedziałający dopiero po `seo-pro.broken_links.mark_broken_after_failures` **kolejnych przebiegach**, w których nie udało się go osiągnąć. Każdy sukces zeruje licznik; domyślna wartość to **3**. Pojedyncza przejściowa awaria nigdy nie oznacza linku, dlatego robot powinien działać **według harmonogramu**, a nie jednorazowo. Przy cotygodniowych przebiegach i domyślnym progu trzy nieudane obserwacje potwierdzają problem po około dwóch tygodniach od pierwszej obserwacji lub do około trzech tygodni od awarii. Zwiększ częstotliwość lub obniż próg, jeśli potrzebujesz szybszego potwierdzenia.

## Kontrole linków o określonych typach {#typed-link-inspections}

Poza osiągalnością każdy odwiedzany link przechodzi zestaw **kontroli o określonych typach**. Ta klasyfikacja jakości URL-i wykrywa m.in. niespójność końcowego ukośnika, nieprawidłowe kodowanie, łańcuchy przekierowań, href `javascript:`, niedziałające kotwice na stronie i nieopisowy tekst linku. Każda kontrola ma stałą **ważność** (`critical` · `warning` · `notice` — ten sam zestaw co [problemy skanowania](/pl/pro/scan-issues), więc jeden warunek CI obejmuje oba) i jest zapisywana dla każdego przebiegu w `seo_broken_link_inspections`. W przeciwieństwie do *problemu niedziałającego linku*, potwierdzanego dopiero po kilku kolejnych przebiegach, kontrola jest migawką pojedynczego przebiegu: pojawia się **od razu, przy pierwszym sprawdzeniu**, czyli dokładnie wtedy, gdy potrzebuje jej CI.

### Lista kontroli {#inspection-reference}

| Kontrola | Ważność | Co wykrywa | Zakres |
|---|---|---|---|
| `broken_link` | critical | Cel zwrócił HTTP ≥ 400 | każdy link |
| `redirect_chain` | notice · warning | Cel jest osiągalny tylko przez przekierowanie; `warning` po przekroczeniu `redirect_chain_warning_hops` | każdy link |
| `link_unreachable` | notice | Nieosiągalny w tym przebiegu (błąd sieci, limit czasu, blokada); problem może być przejściowy | każdy link |
| `insecure_link` | warning | Link `http://` na witrynie `https` (obniżenie bezpieczeństwa transportu) | każdy link |
| `trailing_slash` | notice | Ścieżka wewnętrzna narusza zadeklarowaną konwencję końcowego ukośnika (**wyłączone, dopóki nie ustawisz `trailing_slash`**) | wewnętrzne |
| `double_slash_url` | warning | Ścieżka wewnętrzna zawiera `//` (pusty segment) | wewnętrzne |
| `duplicate_query_param` | notice | Klucz zapytania się powtarza (`?a=1&a=2`); składnia tablicowa `key[]` jest wyjątkiem | wewnętrzne |
| `non_ascii_url` | notice | Ścieżka wewnętrzna zawiera niezakodowane znaki spoza ASCII | wewnętrzne |
| `uppercase_url` | notice | Ścieżka wewnętrzna zawiera wielkie litery (sprawdź warianty wielkości liter obsługiwane osobno) | wewnętrzne |
| `underscore_in_url` | notice | Ścieżka wewnętrzna używa podkreśleń (łączniki są preferowanym separatorem w SEO) | wewnętrzne |
| `javascript_link` | warning | Link używa href `javascript:`, czyli adresu, którego nie można normalnie odwiedzić robotem | każdy odnośnik |
| `missing_fragment` | warning | `#fragment` na tej samej stronie bez pasującego `id`/`name` | ta sama strona |
| `non_descriptive_anchor` | notice | Tekst linku jest ogólny („click here”, „read more”) lub zawiera sam URL | każdy odnośnik |
| `absolute_internal_link` | notice | Link wewnętrzny zapisano jako bezwzględny URL zamiast ścieżki względem katalogu głównego | wewnętrzne |

Kontrole jakości zapisu, takie jak końcowy ukośnik, wielkość liter, kodowanie i podwójny ukośnik, dotyczą wyłącznie linków **wewnętrznych**. Styl adresów zewnętrznej witryny nie należy do Twoich obowiązków. Kontrole przekierowań, niedziałających i nieosiągalnych celów oraz niezabezpieczonego transportu dotyczą każdego linku. Linki do tras frameworka i zasobów statycznych Twojej aplikacji są pomijane, aby pierwszy przebieg nie generował zbędnych zgłoszeń; zobacz `exclude_paths` / `exclude_extensions` poniżej.

Każdy link jest pobierany pod **dokładnie zapisanym URL-em**; usuwany jest tylko `#fragment`. Nie używa się postaci znormalizowanej, aby rzeczywiście zaobserwować kanoniczne przekierowanie serwera, takie jak `/about/ → /about`, i zgłosić je jako `redirect_chain`, zamiast pominąć je przez wcześniejszą normalizację. Każda odmienna zapisana postać linku na stronie jest sprawdzana, więc `/page#ok` i `/page#missing` (lub `/a//b` i `/a/b`) są oceniane osobno, a nie tylko pierwsza z nich. Podstawowy *problem niedziałającego linku* nadal łączy wszystkie aliasy celu w jedną tożsamość. Wiersze kontroli są zapisywane dla `(page, target, inspection)`, więc cel z kilkoma błędnymi kotwicami na stronie daje jeden wiersz `missing_fragment` z przykładem, a nie wiersz dla każdej kotwicy.

### Dostosowywanie klasyfikacji {#tuning-the-taxonomy}

Wszystko znajduje się pod `seo-pro.broken_links.inspections`:

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

**Wyłącz jedną regułę**, usuwając jej klasę z `rules`, lub **wyłącz całą klasyfikację** przez `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`. Dwie reguły warto poznać od razu:

- `trailing_slash` jest **wyłączona, dopóki nie zadeklarujesz konwencji** (`'always'` / `'never'`). Witryna, która obsługuje zarówno `/x`, jak i `/x/` odpowiedzią `200`, nie ma „błędnego” stylu. Jeśli serwer ujednolica adres przez przekierowanie, jest ono już zgłaszane jako `redirect_chain`.
- `absolute_internal_link` uruchamia się dla **każdego** wewnętrznego linku zapisanego jako bezwzględny URL. Jeśli Twoja witryna przyjmuje taką konwencję, powstanie wiele nieszkodliwych wierszy poziomu `notice`. Usuń regułę z `rules`, aby je wyciszyć.

## Ciągła integracja {#continuous-integration}

Zarówno skanowanie linków, jak i [audyt SEO](/pl/pro/scan-issues) mogą **zakończyć proces budowania niepowodzeniem** i **zapisać artefakt raportu**, dzięki czemu Rankbeam pełni też funkcję kontroli jakości CI. `--fail-on-error` odpowiada poziomowi `critical` (niedziałający link, problem krytyczny), a `--fail-on-warning` powoduje niepowodzenie przy `critical` **lub** `warning`. Nie ma osobnego poziomu „error”.

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

`--report=<file|dir>` zapisuje artefakt; przy wskazaniu katalogu nazwa pliku jest wyznaczana automatycznie. `--format` przyjmuje `json` (domyślnie), `md` lub `html`. JSON służy do przetwarzania w pipeline, a HTML jest samodzielną stroną do dołączenia do przebiegu.

### GitHub Actions {#github-actions}

Robot pobiera strony przez HTTP, więc CI musi wskazać osiągalną treść: aplikację uruchomioną lokalnie (poniżej) lub URL stagingowy przez `SEO_PRO_BROKEN_LINKS_BASE_URL`. Zarejestruj modele/mapę witryny, aby robot miał źródło adresów początkowych.

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

## Harmonogram {#scheduling}

Zarejestruj przebiegi i zadania porządkowe robota w `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Lista poleceń {#command-reference}

| Polecenie | Działanie |
|---|---|
| `seo-pro:broken-links-scan` | Dodaje do kolejki ograniczony przebieg z możliwością wznowienia (`--scope=internal_only\|internal_and_external`, dodatkowe adresy początkowe `--url=*`) |
| `seo-pro:broken-links-status` | Podsumowanie ostatniego przebiegu, otwarte problemy niedziałających linków i liczby kontroli z tego przebiegu; **warunek przejścia CI** (`--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html`) |
| `seo-pro:broken-links-cancel` | Anuluje trwający lub oczekujący przebieg (`{run?}` — domyślnie ostatni aktywny) |
| `seo-pro:broken-links-recover` | Oznacza jako nieudane przebiegi porzucone przez niedziałający proces (nieaktualna dzierżawa) |
| `seo-pro:broken-links-prune` | Stosuje zasady retencji robota (stare przebiegi i rozwiązane problemy) |

## Dostrajanie {#tuning}

Limity robota — strony na przebieg, linki na stronę, zakres pojedynczego zadania, ścisły budżet czasu i przerwy chroniące hosty przed nadmiernym obciążeniem — znajdują się w `seo-pro.broken_links`. Wartości domyślne są ostrożne i skończone. Przed ich zwiększeniem przeczytaj [tabelę dostrajania partii w konfiguracji produkcyjnej](/pl/pro/production).
