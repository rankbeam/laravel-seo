---
description: "Procedura zastąpienia Yoast lub Rank Math na działającej witrynie: domyślnie uzupełniane są puste pola, próba bez zapisu niczego nie zmienia, WordPress pozostaje nietknięty."
---

# Procedura migracji WordPress → Rankbeam {#wordpress-→-rankbeam-migration-runbook}

Procedura zastąpienia narzędzi SEO WordPressa (Yoast lub Rank Math) przez Rankbeam, krok po kroku. Domyślnie importery uzupełniają puste pola docelowe; `--overwrite` jawnie zezwala na zastępowanie wartości. Próby bez zapisu niczego nie zapisują, a źródłowa baza WordPressa pozostaje nietknięta. Przed importem wykonaj kopię zarówno źródła, jak i miejsca docelowego.

To praktyczne uzupełnienie [Migracji z WordPressa](/pl/guide/migrate-from-wordpress), która szczegółowo opisuje mapowanie pól, obsługę znaczników szablonów i klucze źródłowe. Tam przeczytasz, *co* jest przenoszone; tutaj — *jak i w jakiej kolejności*.

::: tip Czego potrzebujesz
- **Core** (`rankbeam/laravel-seo`) do importu metadanych i `seo:audit`.
- **Pro** (`rankbeam/laravel-seo-pro`) tylko wtedy, gdy przenosisz także **przekierowania** — tabela `seo_redirects` należy do Pro.
- Treści już odwzorowanej w modelach Laravel (np. `App\Models\Post`), z traitem [`HasSEO`](/pl/guide/quickstart), oraz sposobu dopasowania sluga WordPressa do modelu (klucza trasy modelu lub kolumny wskazanej przez `--match-by`).
:::

## Jak przebiega migracja {#the-shape-of-the-migration}

Rekordy WordPressa są identyfikowane przez **URL / wpis**; rekordy `seo_meta` w Rankbeam są **polimorficzne**, czyli przypisane do modelu Eloquent. Import dopasowuje każdy rekord WordPressa do jednego z twoich modeli. Możliwe są trzy wyniki, a każde uruchomienie podaje ich podział:

| Wynik | Znaczenie | Działanie |
|---|---|---|
| **matched** | rekord przypisano do modelu — zapisano `seo_meta` | brak |
| **url-only** | rekord nie pasował do żadnego modelu (lub nie podano `--model`) | zdecyduj, czy strona potrzebuje modelu, czy przekierowania |
| **unmapped** | rekord zawierał dane bez odpowiednika w Core 3 (przede wszystkim **autora**) | umieść je we właściwym miejscu (np. w metodzie `getSEOAuthor()`) |

---

## Krok 0 — Równoległe działanie (jeszcze bez przełączenia) {#step-0-—-coexist-no-cutover-yet}

Uruchom Rankbeam **obok** działającej witryny. Dodaj trait `HasSEO` do modeli i renderuj znaczniki przez fasadę lub dyrektywę, ale **nie usuwaj** jeszcze instalacji WordPressa ani jego wtyczki SEO. Na tym etapie niczego nie zaimportowano i nic nie jest usuwane — sprawdzasz tylko, czy nowy zestaw komponentów się uruchamia.

Jeśli podczas przełączania obsługujesz nową aplikację Laravel i starą witrynę WordPress na tym samym hoście, utrzymuj je pod osobnymi ścieżkami do kroku 5.

## Krok 1 — Import metadanych (najpierw próba bez zapisu) {#step-1-—-import-the-metadata-dry-run-first}

Zawsze zaczynaj od `--dry-run`: **niczego nie zapisuje** i wyświetla pełny raport weryfikacyjny pokazujący, co *zostałoby* wykonane.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

Przydatne opcje (pełną listę pokaże `php artisan seo:import-from --help`):

| Opcja | Przeznaczenie |
|---|---|
| `--model=` | pełna nazwa klasy modelu docelowego, FQCN (opcja powtarzalna; importery WordPressa przypisują **jeden** model na uruchomienie — uruchom osobno dla każdego typu treści) |
| `--match-by=` | kolumna modelu, do której dopasowywany jest slug (domyślnie klucz trasy) |
| `--post-type=` | ograniczenie odczytu bazy do tych typów wpisów (domyślnie `post` + `page`) |
| `--connection=` | połączenie z bazą zawierającą tabele WordPressa |
| `--table=` | **prefiks** tabel WordPressa (domyślnie `wp_`) |
| `--locale=` | język, dla którego zapisywane są rekordy `seo_meta` |
| `--redirects-csv=` | dodatkowy zapis proponowanych przekierowań do tego pliku na potrzeby kroku 3 |
| `--site-url=` | URL starej witryny, służący do wyprowadzania ścieżek z bezwzględnych URL-i |
| `--overwrite` | zastąpienie istniejących niepustych wartości `seo_meta` (domyślnie **tylko uzupełnianie pustych pól**) |
| `--limit=` | limit liczby rekordów źródłowych (przydatny przy pierwszej próbie) |
| `--json` | raport do odczytu maszynowego |

Gdy wynik próby jest poprawny, usuń `--dry-run`, aby wykonać import:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

Domyślnie import jest **idempotentny** i **uzupełnia wyłącznie puste pola**, więc w tym trybie można go bezpiecznie powtarzać: nie nadpisze metadanych już edytowanych w Rankbeam.

## Krok 2 — Przeczytaj i zarchiwizuj raport weryfikacyjny {#step-2-—-read-and-archive-the-verification-report}

Każde uruchomienie wyświetla **raport weryfikacyjny** — liczby, które trzeba zaakceptować przed usunięciem czegokolwiek. Zachowaj go jako trwały plik:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

Co sprawdzić:

- **matched** powinno odpowiadać oczekiwanej liczbie stron z metadanymi SEO.
- **url-only** to lista stron niedopasowanych do żadnego modelu — zdecyduj, czy każda potrzebuje modelu, przekierowania (krok 3), czy żadnego działania.
- **truncated** wymienia pola skrócone do rozmiaru kolumny `seo_meta` — sprawdź te tytuły i opisy.
- **unmapped** wymienia dane źródłowe bez kolumny w Core 3, **z każdą odrębną wartością `author`** podaną wprost. Autorzy nie są przechowywani w kolumnie (odpowiada za nich `getSEOAuthor()`); raport pozwala świadomie przenieść ich dane, zamiast odkryć stratę po kilku miesiącach.

## Krok 3 — Zaimportuj przekierowania do Pro {#step-3-—-import-the-redirects-into-pro}

Importer pakietu podstawowego **nigdy nie zapisuje `seo_redirects`** — to tabela Pro. Zwraca CSV o stałym, wersjonowanym formacie (**format CSV przekierowań v1**: `source_path,target_url,status_code,note`). Zaimportuj go do Pro, zaczynając od próby bez zapisu:

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Każdy wiersz przechodzi taką samą walidację jak formularz przekierowań Filament: błędne wiersze, nieprawidłowe kody statusu, **niebezpieczne cele zewnętrzne**, **powtórzone źródła** i reguły tworzące **pętlę przekierowań** są pomijane z podaniem przyczyny, nigdy zapisywane po cichu. Próba bez zapisu sprawdza cały plik, w tym pętle i duplikaty, i niczego nie zapisuje; przekaż `--overwrite`, by zastąpić cel istniejącej reguły.

## Krok 4 — Sprawdź przez `seo:audit --strict` {#step-4-—-verify-with-seo-audit-strict}

Uzależnij przełączenie od wyniku bezpłatnego audytu działającego wewnątrz procesu. `--strict` zwraca niezerowy kod wyjścia, jeśli **jakakolwiek** strona ma problem, więc nadaje się jako warunek w CI i przed przełączeniem:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

Audyt obejmuje kontrole modelu i mechanizmu rozstrzygania: obecność oraz długość tytułu i opisu, obraz OG, sprzeczności robots i format adresu kanonicznego. Kontrole wyrenderowanego HTML i działających adresów kanonicznych oraz ocena 0–100 należą do [skanowania Pro](/pl/pro/scan-issues); uruchom je również, jeśli masz Pro. Zobacz [Bezpłatny audyt SEO](/pl/guide/audit).

Następnie sprawdź kilka rzeczywistych stron w przeglądarce: otwórz ich źródło i potwierdź, że `<title>`, `<meta name="description">`, canonical, robots i znaczniki OpenGraph zawierają zaimportowane wartości.

## Krok 5 — Sprawdź PRZED usunięciem starego pakietu lub tabeli {#step-5-—-verify-before-removing-the-legacy-package-table}

**Nie usuwaj** bazy WordPressa, wtyczki SEO ani starego pakietu, dopóki **wszystkie** poniższe warunki nie są spełnione:

- [ ] Import uruchomiono dla **każdego** typu treści (jeden `--model` na uruchomienie).
- [ ] Zarchiwizowany raport pokazuje oczekiwaną liczbę **matched** i brak nieoczekiwanych rekordów **url-only**.
- [ ] Każda potrzebna wartość **unmapped author** została przeniesiona we właściwe miejsce.
- [ ] Przekierowania zaimportowano do Pro (`seo-pro:redirects-import`), a kilka starych URL-i rzeczywiście zwraca przekierowanie 301 na nowe.
- [ ] `php artisan seo:audit --strict` zwraca kod `0`.
- [ ] (Pro) `php artisan seo:doctor` nie zgłasza pozostałości starej tabeli `seo` ani kolizji `config/seo.php`.
- [ ] Wyrenderowane strony sprawdzono wyrywkowo w przeglądarce.

Ponieważ w trybie domyślnym importery tylko uzupełniają puste pola i są idempotentne, przed spełnieniem tych warunków można w każdej chwili powtórzyć krok 1 bez nadpisywania istniejących metadanych — stare dane nadal znajdują się w WordPressie.

## Krok 6 — Wyłącz stary system {#step-6-—-decommission}

Dopiero po spełnieniu listy z kroku 5 wyłącz witrynę WordPress, a następnie usuń jej bazę lub tabele i stary pakiet SEO. Zachowaj kopię bazy, dopóki nie upewnisz się, że nowy zestaw komponentów poprawnie działa na produkcji.

::: tip Wycofanie migracji
Przy domyślnym uzupełnianiu pustych pól kroki 1–4 nie zastępują istniejących metadanych: `seo_meta` jest uzupełniane, przekierowania są walidowane i można usunąć dodane reguły, a dane WordPressa pozostają nietknięte. Jeśli jawnie zezwolisz na nadpisywanie, przywrócenie zastąpionych wartości lub reguł wymaga kopii zapasowej. Przed krokiem 6 wycofanie przełączenia oznacza po prostu *„dalej obsługuj witrynę przez WordPress”*; po kroku 6 — *„przywróć kopię WordPressa”*.
:::

---

Przechodzisz z pakietu SEO dla **Laravel** (ralphjsmit, artesaos, Spatie)? Zobacz [Migrację z innych pakietów Laravel](/pl/guide/migrate-from-other-packages).
