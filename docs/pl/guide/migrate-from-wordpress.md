---
description: "Przenieś ręcznie przygotowane SEO z Yoast lub Rank Math — tytuły, opisy, kanoniczne URL-e, robots i główne słowa kluczowe — do modeli Laravel. Dokumentacja mapowania pól importera."
---

# Migracja z WordPressa {#migrating-from-wordpress}

Przenosisz witrynę z treściami z WordPressa? Rankbeam może przenieść metadane SEO ręcznie przygotowane przez Twój zespół w Yoast lub Rank Math — tytuły, opisy, kanoniczne URL-e, dyrektywy robots, główne słowa kluczowe i nadpisania społecznościowe — do modeli Laravel, aby zmiana nie oznaczała utraty lat optymalizacji.

::: tip Przeprowadzasz rzeczywiste przełączenie?
Ta strona jest *dokumentacją referencyjną* importera (mapowanie pól, tokeny, klucze źródłowe). **Procedurę** krok po kroku o ograniczonym ryzyku — współistnienie, import, weryfikacja, wycofanie starego systemu — opisuje [Instrukcja migracji z WordPressa](/pl/guide/wordpress-migration-runbook).
:::

Są dwie ścieżki, obie obsługiwane przez to samo polecenie `seo:import-from`:

| Ścieżka | Źródło | Najlepsza do |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | arkusz wyeksportowany z WordPressa | większości migracji agencyjnych; kontrolujesz dokładne URL-e |
| [**Baza danych**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | działająca baza danych WordPressa | pełnej wierności, w tym nadpisań OpenGraph/Twitter i przekierowań Rank Math |

Obie są **idempotentne** (ponowne uruchomienie aktualizuje te same rekordy, nigdy nie tworzy duplikatów), obsługują **`--dry-run`** i domyślnie wyłącznie *uzupełniają* puste pola — nigdy nie nadpisują danych SEO ustawionych już w Rankbeam. Przekaż **`--overwrite`**, aby zamiast tego zastąpić istniejące wartości importowanymi.

## Jak rekordy WordPressa stają się rekordami `seo_meta` {#how-wordpress-rows-become-seo-meta-rows}

Dane WordPressa nie są polimorficznymi danymi Laravel: rekord WordPressa jest identyfikowany przez **URL** lub **ID wpisu**, podczas gdy `seo_meta` Rankbeam jest polimorficzne — każdy rekord należy do rzeczywistego modelu Eloquent. Importer dopasowuje więc każdy rekord WordPressa do jednego z Twoich modeli i uczciwie raportuje, które rekordy zostały powiązane, a które pozostały wyłącznie URL-ami:

- **Powiązane z modelem.** Wskazujesz model docelowy przez `--model="App\Models\Post"`. **Slug** każdego rekordu (ostatni segment ścieżki URL-a lub `post_name` WordPressa) jest dopasowywany do tego modelu — domyślnie według klucza trasy lub kolumny wybranej przez `--match-by=`. Dopasowane rekordy są zapisywane w `seo_meta`.
- **Tylko URL.** Rekord niepasujący do żadnego modelu (lub uruchomienie bez `--model`) nie może stać się rekordem `seo_meta` — nie ma modelu, z którym można go powiązać. Jest raportowany jako pominięty z przyczyną `url-only`. Jego kanoniczny URL nadal może stać się [kandydatem na przekierowanie](#redirects).

Wpisy i strony WordPressa zwykle odpowiadają *różnym* modelom Laravel, dlatego uruchamiaj importer osobno dla każdego typu treści i ograniczaj zakres rekordów:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Własne typy wpisów nie są domyślnie skanowane
Czytniki bazy danych przeglądają tylko typy wpisów **`post`** i **`page`**. Witryny oparte na własnych typach wpisów (`product`, `event`, `pathology` motywu, …) muszą jawnie wskazać każdy typ — powtarzaj `--post-type=`:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. Import CSV {#_1-csv-import}

Ścieżka CSV obejmuje większość migracji agencyjnych. Wyeksportuj jeden rekord na URL z tym nagłówkiem (kolumny mogą być w dowolnej kolejności; nierozpoznane kolumny są ignorowane i raportowane):

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Uruchom:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Kolumna | Mapowanie na `seo_meta` | Uwagi |
|---|---|---|
| `url` | *(klucz dopasowania)* | Slug (ostatni segment ścieżki) jest dopasowywany do modelu. Wymagany. |
| `title` | `title` | Skracane do 70 znaków; zbyt długie wartości są raportowane. |
| `description` | `description` | Skracane do 160 znaków. |
| `canonical` | `canonical` | Służy również do ustalania [kandydatów na przekierowania](#redirects). |
| `robots` | `robots` | Zapisywane dosłownie (np. `noindex, nofollow`); skracane do 50 znaków. |
| `focus_keyword` | `focus_keywords` | Rozdzielone przecinkami; pierwsze słowo kluczowe jest główne. |

Nieprawidłowe rekordy są pomijane (i zliczane): rekord bez `url` lub z liczbą kolumn niezgodną z nagłówkiem.

---

## 2. Import z bazy danych (Yoast / Rank Math) {#_2-database-import-yoast-rank-math}

Jeśli nadal masz bazę danych WordPressa, importer może bezpośrednio odczytać metadane SEO — w tym nadpisania OpenGraph/Twitter i (dla Rank Math) przekierowania, które eksport CSV zwykle pomija.

### Skieruj połączenie na WordPressa {#point-a-connection-at-wordpress}

Dodaj bazę danych WordPressa jako połączenie w `config/database.php`:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Następnie zaimportuj dane (domyślny prefiks tabel to `wp_`; nadpisz go przez `--table=`):

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

Czytnik przegląda `{prefix}posts` (opublikowane wpisy/strony) i pobiera metadane wtyczki dla każdego wpisu z `{prefix}postmeta`, dopasowując jego slug `post_name` do Twojego modelu.

::: tip Niestandardowy prefiks tabel
Zarządzane hostingi WordPressa często używają losowego prefiksu (np. `wppg_` zamiast `wp_`). Sprawdź nazwy `CREATE TABLE` w zrzucie i przekaż rzeczywisty prefiks — `--table=wppg_` — aby czytnik znalazł `{prefix}posts` i `{prefix}postmeta`.
:::

::: tip Odczyt z odtworzonego zrzutu w MySQL 8
Jeśli wczytałeś zrzut WordPressa do MySQL 8+, aby odczytać go lokalnie, złagodź ścisły tryb SQL przed wykonaniem `.sql` — domyślne wartości datetime `'0000-00-00'` WordPressa są odrzucane przez domyślne tryby `STRICT`/`NO_ZERO_DATE` MySQL 8, więc sam import zrzutu kończy się błędem (`Invalid default value for 'post_date'`), zanim rozpocznie się import SEO:

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Mapowanie pól {#field-mapping}

Oba importery mapują pola **jawnie** — klucz bez kolumny w Core 3 jest raportowany jako *niezmapowany*, nigdy nie jest wymyślany.

| Klucz meta Yoast | Klucz meta Rank Math | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Zapisywane są tylko odstępstwa od domyślnych ustawień WordPressa, więc zwykła strona możliwa do indeksowania pozostawia `robots` jako null i dziedziczy domyślną wartość witryny. Osobne flagi `noindex` / `nofollow` / zaawansowane (`noarchive`, `nosnippet`, `noimageindex`) Yoast są składane w jeden ciąg; serializowana tablica `robots` Rank Math jest odczytywana tak samo, z pominięciem wartości domyślnych `index` / `follow`.

**Niezmapowane klucze** (raportowane, nigdy kopiowane): identyfikatory załączonych obrazów (`*-image-id`), oceny słów kluczowych/SEO (`linkdex`, `content_score`, `rank_math_seo_score`), wybór kategorii głównej i znaczniki danych strukturalnych wyników rozszerzonych Rank Math — [graf danych strukturalnych](/pl/guide/schema) jest ich bogatszym, typowanym zamiennikiem.

::: warning Kanoniczne URL-e są importowane dosłownie
Jawny kanoniczny URL (`rank_math_canonical_url` / `_yoast_wpseo_canonical`) jest kopiowany **dokładnie tak, jak go zapisano**. Jeśli strona przypisała kanoniczny URL do bezwzględnego adresu w *starej* domenie — co często zdarza się na hostingach zarządzanych/stagingowych, np. `https://oldsite-staging.example.com/page/` — po imporcie nadal tam wskazuje; importer nigdy nie przepisuje hosta. `--site-url` wyprowadza *ścieżki* żądań z bezwzględnych URL-i dla [kandydatów na przekierowania](#redirects) i dopasowania rekordów CSV, ale **nie** przepisuje zapisanych wartości kanonicznych. Po zmianie domeny przejrzyj zaimportowane kanoniczne URL-e i zaktualizuj hosta — lub wyczyść je, aby resolver użył adresu kanonicznego wskazującego na samą stronę. (Większość stron nie ma jawnego kanonicznego URL-a i nie podlega temu problemowi; Yoast i Rank Math automatycznie ustalają go podczas renderowania).
:::

### Tokeny szablonów {#template-tokens}

Yoast i Rank Math zapisują tytuły i opisy jako **szablony** z tokenami — Yoast używa `%%title%%`, Rank Math — `%title%`. Importer **rozstrzyga tokeny, które może wyprowadzić**, i **usuwa pozostałe**, więc zapisana wartość nigdy nie jest surowym ciągiem `%%token%%`:

| Token | Rozstrzygany do |
|---|---|
| `%%title%%` / `%title%` | tytułu wpisu WordPressa |
| `%%sitename%%` / `%sitename%` | nazwy bloga z `wp_options` (import z bazy danych) |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *usuwany* (pozostaje pusty, otaczające separatory są porządkowane) |

Jeśli uruchomienie rozstrzygnęło jakikolwiek token, raport o tym informuje — **przejrzyj zaimportowane tytuły**, aby potwierdzić, że brzmią tak, jak chcesz, i popraw te nieliczne, które zależały od tokenów niemożliwych do wyprowadzenia.

---

## Przekierowania {#redirects}

`seo_redirects` jest funkcją [Rankbeam **Pro**](/pl/pro/installation), więc importer rdzenia nigdy nie zapisuje bezpośrednio do tej tabeli. Zamiast tego przekaż `--redirects-csv=`, a importer **wygeneruje CSV** z tymi samymi kolumnami co tabela przekierowań Pro — `source_path,target_url,status_code,note` — który zaimportujesz do Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Skąd pochodzą kandydaci na przekierowania:

- **Import CSV** — rekord, którego `canonical` wskazuje **inną ścieżkę** niż jego własny `url`, staje się `301` ze starej ścieżki do adresu kanonicznego. Adres kanoniczny wskazujący tę samą ścieżkę *nie* jest generowany (powstałaby pętla).
- **Baza danych Rank Math** — aktywne reguły w tabeli `{prefix}rank_math_redirections`. Generowane są tylko reguły **dokładnego dopasowania**; reguły regex/contains/start/end są raportowane jako pominięte, ponieważ nie odpowiadają jednej ścieżce.
- **Yoast (bezpłatny)** nie ma tabeli przekierowań — ma ją tylko Yoast Premium, a jej schemat nie należy do bezpłatnego pakietu. Do przekierowań Yoast użyj ścieżki CSV.

Kandydaci są **propozycjami** — przejrzyj CSV, a następnie zaimportuj go do Pro przez [`seo-pro:redirects-import`](/pl/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro), które waliduje każdy rekord (odrzucając pętle, niebezpieczne cele i duplikaty). Struktura CSV jest stabilnym kontraktem — **format CSV przekierowań v1**: `source_path,target_url,status_code,note`.

---

## Co mówi raport {#what-the-report-tells-you}

Uruchomienie bez `--json` wyświetla tabelę wyników (utworzone / zaktualizowane / bez zmian / pominięte / przeskanowane), **raport weryfikacji** i sekcje do przeglądu:

- **Raport weryfikacji** — zwięzły podział do zatwierdzenia: **dopasowane** (rekordy powiązane z modelem), **tylko URL** (bez dopasowania do modelu) oraz liczby skróconych i niezmapowanych wartości.
- **Skrócone** — wartości skrócone, aby zmieściły się w kolumnie `seo_meta`.
- **Niezaimportowane** — klucze źródłowe, które zawierały dane, ale nie mają odpowiednika w Core 3, **w tym każda odrębna wartość `author`** (autor nie jest zapisywaną kolumną — należy do [`getSEOAuthor()`](/pl/concepts/resolver-precedence) — dlatego raport wymienia dane do przeniesienia w inne miejsce, zamiast pozwalać im zniknąć po cichu).
- **Kandydaci na przekierowania** — ile zapisano i do którego pliku.
- **Pominięte rekordy według przyczyny** — rekordy tylko z URL-em, wpisy bez metadanych SEO, reguły przekierowań inne niż dokładne dopasowanie.
- **Ostrzeżenia** — np. o rozstrzygnięciu tokenów szablonów.

Dodaj `--json`, aby otrzymać wszystkie powyższe informacje w wersji do odczytu maszynowego (blok `verification` zawiera liczby dopasowanych rekordów i rekordów tylko z URL-em oraz każdą wartość autora).

### Weryfikacja {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict` kończy się kodem różnym od zera, jeśli dowolna strona ma problem. Zobacz [Bezpłatny audyt SEO](/pl/guide/audit). Pełną, uporządkowaną procedurę przełączenia — współistnienie → import → weryfikacja → wycofanie starego systemu — opisuje [Instrukcja migracji z WordPressa](/pl/guide/wordpress-migration-runbook).

---

Przechodzisz z pakietu SEO dla **Laravel** (ralphjsmit, artesaos, Spatie)? Zobacz [Migracja z innych pakietów Laravel](/pl/guide/migrate-from-other-packages).
