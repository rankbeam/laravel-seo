---
description: "Każda funkcja Pro — skanowanie, przekierowania i rejestrowanie błędów 404 — działa bez Filament. Lista poleceń do zarządzania całym Pro przez artisan."
---

# Użycie bez panelu {#headless-usage}

Każda funkcja Pro — skanowanie, przekierowania i rejestrowanie błędów 404 — działa bez panelu: należy do silnika i nie wymaga Filament. Panel jest tylko interfejsem zarządzania, a poniższe polecenia są jego odpowiednikiem bez interfejsu graficznego.

## Lista poleceń {#command-reference}

### Konfiguracja i kontrola stanu {#setup-health-check}

| Polecenie | Działanie |
|---|---|
| `seo-pro:install` | Publikuje `config/seo-pro.php` i migracje Pro, uruchamia je, a następnie wypisuje kolejne kroki (`--no-migrate`, `--force`) |
| `seo:doctor` | Jednorazowa kontrola stanu: URL aplikacji, tabele rdzenia i Pro, cele skanowania, mapa witryny, kolejki poszczególnych zadań, funkcje opcjonalne i stan operacyjny. Każde ostrzeżenie wskazuje dokładną poprawkę (`--json` do monitorowania) |

`seo-pro:install` to udokumentowana ścieżka konfiguracji. Migracje Pro wymagają publikacji (pakiet nigdy nie ładuje ich automatycznie), więc to instalator zamienia samo `composer require` w działający schemat bazy. Jest idempotentny; możesz uruchomić go ponownie w dowolnym momencie.

`seo:doctor` nie wykonuje wywołań sieciowych i nigdy nie wypisuje sekretów. Kontrola AI informuje tylko, czy skonfigurowana zmienna klucza jest *ustawiona*. Sprawdza konfigurację i historię ostatnich przebiegów, ale nie potwierdza, że zewnętrzny cron lub proces kolejki faktycznie działa. Zwraca niezerowy kod tylko przy krytycznym błędzie — braku wymaganej tabeli — więc lokalne środowisko deweloperskie z ostrzeżeniami nadal kończy kontrolę poprawnie. `--json` nadaje każdej kontroli stabilne `id`, na którym możesz oprzeć integrację. Uruchom je zaraz po [instalacji](/pl/pro/installation) i w CI.

### Skanowanie {#scanning}

| Polecenie | Działanie |
|---|---|
| `seo-pro:scan` | Dodaje do kolejki pełne skanowanie wszystkich zarejestrowanych celów (`--sync` wykonuje je bezpośrednio; **warunki przejścia CI** `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=json\|md\|html` wymagają `--sync`) |
| `seo-pro:scan-status` | Podsumowanie ostatniego przebiegu i otwarte problemy, od najpoważniejszych (`--limit=20`, `--severity=critical\|warning\|notice`) |
| `seo-pro:scan-recover` | Oznacza jako nieudane przebiegi porzucone przez niedziałający proces kolejki |
| `seo-pro:scan-prune` | Usuwa zakończone przebiegi wraz z problemami po upływie okresu retencji |

### Robot sprawdzający niedziałające linki {#broken-link-crawler}

Domyślnie wyłączony. Włącz `seo-pro.broken_links.enabled` i wykonaj migracje jego dwóch tabel (publikuje je `seo-pro:install`). Robot działa w zadaniach kolejki o ograniczonym zakresie. Uruchom osobny proces dla jego kolejki. Dostrajanie opisuje [konfiguracja produkcyjna](/pl/pro/production).

| Polecenie | Działanie |
|---|---|
| `seo-pro:broken-links-scan` | Dodaje do kolejki ograniczony przebieg robota z możliwością wznowienia (`--scope=internal_only\|internal_and_external`, dodatkowe adresy początkowe `--url=*`) |
| `seo-pro:broken-links-status` | Podsumowanie ostatniego przebiegu, otwarte problemy i [kontrole o określonych typach](/pl/pro/broken-links#typed-link-inspections) z tego przebiegu; **warunek przejścia CI** (`--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=`) |
| `seo-pro:broken-links-cancel` | Anuluje trwający lub oczekujący przebieg robota (`{run?}` — domyślnie ostatni aktywny) |
| `seo-pro:broken-links-recover` | Oznacza jako nieudane przebiegi porzucone przez niedziałający proces (nieaktualna dzierżawa) |
| `seo-pro:broken-links-prune` | Stosuje zasady retencji robota (stare przebiegi i rozwiązane problemy) |

### Przekierowania i błędy 404 {#redirects-404s}

| Polecenie | Działanie |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Tworzy regułę przekierowania (`--code=301`, `--regex`, `--no-preserve-query`, `--note=`) |
| `seo-pro:404-list` | Zarejestrowane błędy 404, od największej liczby żądań (`--status=new\|ignored\|redirected\|all`, `--limit=20`) |
| `seo-pro:redirects-flush-hits` | Zapisuje do bazy liczniki trafień przekierowań zebrane w pamięci podręcznej, gdy `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Usuwa stare wpisy 404 i egzekwuje limit wierszy |

### Lista kontrolna treści strony {#on-page-checklist}

| Polecenie | Działanie |
|---|---|
| `seo-pro:checklist {model} {id}` | Lista kontroli pass/warn/fail uwzględniająca słowo kluczowe dla jednego modelu (`--json`, `--strict`, `--locale=`). Zobacz [listę kontrolną treści strony](/pl/pro/on-page-checklist) |

Ta sama lista jest dostępna jako `SeoPro::checklistFor($model)`. Służy pracy redakcyjnej (rozmieszczenie słów kluczowych, długość, obrazy i linki wewnętrzne), **nie** jest [oceną SEO](/pl/pro/scoring).

### Search Console (tylko odczyt) {#search-console-read-only}

| Polecenie | Działanie |
|---|---|
| `seo-pro:search-console` | Strony z otwartymi problemami **i** ruchem z wyszukiwarki, najpierw największe niewykorzystane możliwości (`--view=attention`, domyślnie) |
| `seo-pro:search-console --view=pages` | Najważniejsze strony według wyświetleń/kliknięć/CTR/pozycji |
| `seo-pro:search-console --view=queries` | Najważniejsze zapytania (`--days=`, `--limit=`, `--json`) |

Te same metryki są dostępne jako `SeoPro::searchConsole()`. Zobacz [Search Console](/pl/pro/search-console). Funkcja domyślnie wyłączona, wyłącznie do odczytu.

### Pomoc AI {#ai-assist}

| Polecenie | Działanie |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Propozycje tytułu/opisu jako JSON (`--field=title\|description\|all`). Zobacz [pomoc AI](/pl/pro/ai-assist) |
| `seo-pro:ai-suggest --issue={id}` | Wyjaśnienie poprawki problemu skanowania prostym językiem, jako JSON |

### Rozwiązanie błędu 404 w jednym kroku {#resolving-a-404-in-one-step}

`--from-404={path}` jest odpowiednikiem akcji *Utwórz przekierowanie* monitora 404, dostępnym bez panelu: tworzy regułę **i** oznacza pasujący wpis logu jako przekierowany, wiążąc go z nową regułą:

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

Polecenie korzysta z tych samych walidatorów co formularz Filament. Nieprawidłowe wyrażenia regularne, zbyt duże wartości i zewnętrzne adresy docelowe spoza listy dozwolonych są odrzucane przed jakimkolwiek zapisem.

## Zalecany harmonogram {#recommended-schedule}

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

Zalecaną częstotliwość każdego cyklicznego polecenia opisuje [konfiguracja produkcyjna](/pl/pro/production), obok architektury kolejek, konfiguracji procesów, zasad ponowień i odzyskiwania, retencji oraz ustrukturyzowanej **telemetrii** emitowanej po każdym zakończonym przebiegu (pobrane strony, sprawdzone linki, zablokowane URL-e, czas trwania i opóźnienie kolejki).

## Co wymaga interfejsu Filament? {#what-needs-the-filament-ui}

Żadna funkcja silnika. Cały silnik — proces skanowania, śledzenie problemów, dopasowanie przekierowań, rejestrowanie 404, usuwanie starych danych i odzyskiwanie — działa identycznie z Filament i bez niego. Panel dodaje *widoki*: pulpit z postępem skanowania na żywo i statystykami ważności, przeglądanie problemów z filtrami i oknami dla poszczególnych stron, przyciski ignorowania/ponownego otwierania, formularze CRUD przekierowań i tabelę 404 z akcją wykonywaną jednym kliknięciem. Ignorowanie i ponowne otwieranie problemów nie mają obecnie osobnego polecenia. Wykonuj je w panelu albo przez model `SEOScanIssue` (`markIgnored()` / `reopen()`) w tinker lub własnym kodzie.
