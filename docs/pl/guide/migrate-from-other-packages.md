---
description: "Przejdź na Rankbeam z innego pakietu SEO dla Laravel, mapując jego API i zapis danych na trait HasSEO i saveSEO(), z importerem danych SEO modeli uruchamianym jednym poleceniem."
---

# Migracja z innych pakietów SEO dla Laravel {#migrating-from-other-laravel-seo-packages}

Korzystasz już z innego pakietu SEO? Przejście na Rankbeam ma zajmować dzień pracy, a nie wymagać przepisywania aplikacji. Ten przewodnik mapuje API i sposób przechowywania danych popularnych pakietów na dwa podstawowe mechanizmy Rankbeam — trait [`HasSEO`](/pl/guide/quickstart) i `saveSEO()` — oraz opisuje importer uruchamiany jednym poleceniem dla pakietu zapisującego dane SEO poszczególnych modeli.

::: tip Przechodzisz z WordPressa?
Jeśli przenosisz witrynę z treściami z WordPressa (Yoast lub Rank Math), zobacz osobny przewodnik [**Migracja z WordPressa**](/pl/guide/migrate-from-wordpress) — obejmuje importer CSV i czytniki działającej bazy danych.
:::

| Pakiet źródłowy | Gdzie zapisuje dane | Sposób migracji |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | tabela polimorficzna `seo` | **`php artisan seo:import-from ralphjsmit`** + zamiana traitu |
| [`artesaos/seotools`](#from-artesaos-seotools) | nigdzie (czas wykonania + konfiguracja) | zamiana kodu — ustawianie wartości przez `saveSEO()` / gettery obliczające wartości |
| [`spatie/*`](#from-spatie-packages) | nigdzie (buildery schema-org / map witryny) | zachowaj uzupełniające narzędzia, a resztę przenieś do Rankbeam |

Tylko **ralphjsmit** zapisuje dane SEO w tabeli bazy danych, więc tylko on ma dane do zbiorczego importu. Pozostałe to buildery tagów działające w czasie wykonania — nie ma tabeli do odczytu; ich wywołania przy każdym żądaniu zastępujesz zapisanym `seo_meta`.

---

## Z `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

`ralphjsmit/laravel-seo` zapisuje po jednym rekordzie polimorficznym na model w tabeli `seo`, której struktura przypomina `seo_meta` Rankbeam. Umożliwia to prosty, idempotentny import zbiorczy.

### 1. Zainstaluj Rankbeam obok obecnego pakietu {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Oba pakiety mogą współistnieć podczas migracji — korzystają z różnych tabel (`seo` i `seo_meta`) oraz różnych przestrzeni nazw traitów.

::: warning Jeden plik konfiguracji, nie dwa
Jeśli aplikacja nadal zawiera opublikowany `config/seo.php` z `ralphjsmit/laravel-seo`, przesłoni on konfigurację Rankbeam (pakiety współdzielą klucz konfiguracji `seo`). Utwórz jego kopię, usuń go i ponownie opublikuj konfigurację Rankbeam: `php artisan vendor:publish
--tag=seo-config`.
:::

### 2. Uruchom importer {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

Importer odczytuje tabelę `seo` ralphjsmit, rozstrzyga każdy rekord do jego rzeczywistego modelu Eloquent i zapisuje dane w `seo_meta`.

| Opcja | Działanie |
|---|---|
| `--dry-run` | Raportuje, co zostałoby zaimportowane; niczego nie zapisuje. |
| `--model="App\Models\Post"` | Ogranicza zakres do jednej lub kilku klas modeli (można powtarzać). |
| `--locale=fr` | Zapisuje importowane rekordy dla tych ustawień regionalnych (domyślnie ustawienia aplikacji). |
| `--table=legacy_seo` | Odczytuje tabelę źródłową o zmienionej nazwie. |
| `--connection=legacy` | Odczytuje tabelę źródłową przez inne połączenie z bazą danych. |
| `--limit=100` | Importuje najwyżej N rekordów (przydatne przy migracji etapowej). |
| `--overwrite` | Zastępuje istniejące niepuste wartości (domyślnie uzupełnia tylko puste pola). |
| `--json` | Raport do odczytu maszynowego. |
| `--force` | Pomija prośbę o potwierdzenie (dla skryptów/CI). |

Jest **idempotentny**: ponowne uruchomienie aktualizuje te same rekordy i nigdy nie tworzy duplikatów, a domyślnie wyłącznie *uzupełnia* puste pola — nie nadpisze danych SEO ustawionych już w Rankbeam. Przekaż `--overwrite`, jeśli zamiast tego importowane wartości mają zastąpić istniejące.

### 3. Zamień trait w modelach {#_3-swap-the-trait-on-your-models}

Zastąp trait ralphjsmit traitem Rankbeam. Nazwy metod nieznacznie się różnią; trait odczytuje teraz tabelę `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Jeśli dostosowywałeś dane SEO przez `getDynamicSEOData()` ralphjsmit, przenieś tę logikę do getterów obliczających poszczególne pola Rankbeam (`getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()`, `getSEOAlternates()`) — zobacz [Szybki start](/pl/guide/quickstart). Zapisane nadpisania przechodzą przez `saveSEO()`:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Mapowanie pól {#field-mapping}

Importer mapuje pola **jawnie** — nigdy nie kopiuje w ciemno kolumny, której nie ma w schemacie Core 3.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Uwagi |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | **Ponownie rozstrzygane** z bieżącego modelu (zobacz poniżej), a nie kopiowane dosłownie. |
| `title` | `title` | Skracane do 70 znaków (długość kolumny `seo_meta`); zbyt długie wartości są raportowane. |
| `description` | `description` | Skracane do 160 znaków; zbyt długie wartości są raportowane. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Skracane do 50 znaków. |
| `image` | `og_image` | `twitter:image` dziedziczy tę wartość automatycznie przez resolver. |
| `author` | *(nie jest importowane)* | `seo_meta` w Core 3 nie ma kolumny autora — autor artykułu jest rozstrzygany na poziomie resolvera, a nie zapisywany jako metadane społecznościowe. Rekordy z autorem są **zliczane i raportowane**, abyś mógł zdecydować, gdzie powinien się znaleźć (np. jako wartość obliczana w stylu `getSEOData`). |
| `id`, `created_at`, `updated_at` | *(nie są importowane)* | Pola strukturalne. |

**Dlaczego typ polimorficzny jest rozstrzygany ponownie.** Każdy rekord źródłowy jest rozstrzygany do swojego rzeczywistego modelu, a klucze `seoable` pochodzą z jego własnego `getMorphClass()`. Dzięki temu relacja pozostaje poprawna przy *bieżącej* [mapie typów polimorficznych](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types) aplikacji, nawet jeśli ralphjsmit zapisał inną konwencję. Importer może też pominąć rekordy, których model został usunięty (raportowane jako pominięte, nigdy zapisywane jako osierocone).

### Co mówi raport {#what-the-report-tells-you}

Uruchomienie bez `--json` wyświetla tabelę wyników i trzy sekcje do przeglądu:

- **Skrócone** — wartości skrócone, aby zmieściły się w kolumnie `seo_meta`. Sprawdź je.
- **Niezaimportowane** — kolumny źródłowe (np. `author`), które zawierały dane, ale nie mają odpowiednika w Core 3.
- **Pominięte rekordy według przyczyny** — puste rekordy źródłowe, usunięte modele, nierozstrzygnięte typy modeli.

### Weryfikacja {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Gdy wynik jest zadowalający, usuń `ralphjsmit/laravel-seo` i jego tabelę `seo`.

---

## Z `artesaos/seotools` {#from-artesaos-seotools}

`artesaos/seotools` to builder tagów działający **w czasie wykonania**: ustawiasz wartości przy każdym żądaniu przez fasady `SEOMeta`, `OpenGraph`, `TwitterCard` i `JsonLd` (często w kontrolerze), wspierane wartościami domyślnymi `config/seotools.php`. Nic nie jest zapisywane dla poszczególnych modeli, więc nie ma tabeli do importu — przenosisz wywołania wykonywane przy każdym żądaniu do wartości zapisanych lub obliczanych.

| Wywołanie artesaos/seotools | Odpowiednik Rankbeam |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` lub `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` lub `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` lub `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | Brak odpowiednika metatagu keywords: główne słowa kluczowe służą do wewnętrznych kontroli redakcyjnych. `saveSEO(['focus_keywords' => [...]])` (zobacz [audyt](/pl/guide/audit)) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [graf danych strukturalnych JSON-LD](/pl/guide/schema) |
| wartości domyślne `config/seotools.php` | wartości domyślne witryny `config/seo.php` + [pierwszeństwo resolvera](/pl/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` w układzie | `@seo($model)` (zobacz [Blade](/pl/guide/blade)) |

Zmiana dotyczy koncepcji: zamiast imperatywnie ustawiać tagi w każdym kontrolerze, zapisujesz dane SEO raz (dla każdego modelu w `seo_meta`), a resolver Rankbeam je renderuje. Wartości zastępcze całej witryny zapisane wcześniej w `config/seotools.php` stają się [wartościami domyślnymi konfiguracji](/pl/reference/configuration) Rankbeam; statyczne strony poszczególnych tras używają `@seoForRoute()`.

---

## Z pakietów Spatie {#from-spatie-packages}

Nie ma pakietu `spatie/laravel-seo` do przechowywania metadanych, więc nie ma czego importować. Pakiety Spatie łączone z SEO to **uzupełniające buildery**, które możesz zachowywać lub zastępować pojedynczo:

- **`spatie/schema-org`** — builder JSON-LD z interfejsem opartym na łańcuchach wywołań. Rankbeam ma własny [graf danych strukturalnych](/pl/guide/schema) z typowanymi builderami `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` i `Organization`, które zapisują do `seo_meta.schema_jsonld` i renderują bez duplikatów. Jeśli masz ręcznie zbudowane obiekty `spatie/schema-org`, przekaż ich wynik `->toArray()` do `saveSEO(['schema_jsonld' => $array])` lub zapisz je ponownie builderami Rankbeam.
- **`spatie/laravel-sitemap`** — generator map witryny. [Rejestr map witryny](/pl/guide/sitemaps) Rankbeam opiera się na nim; możesz zarejestrować modele jako źródła i pozwolić Rankbeam generować wspólną mapę witryny albo zachować istniejącą mapę Spatie i wyłączyć trasę Rankbeam.

(Jeśli korzystałeś z [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO), innego buildera metadanych działającego w czasie wykonania i opartego na strukturach, zastosuj ten sam wzorzec co dla artesaos: przenieś wywołania `setTitle`/`addMeta` wykonywane przy każdym żądaniu do `saveSEO()` lub getterów obliczających wartości).

---

## Rozszerzanie importera {#extending-the-importer}

Polecenie `seo:import-from` korzysta z małego rejestru implementacji `Rankbeam\Seo\Importing\Contracts\Importer`, więc nowe źródła można dodawać bez zmiany polecenia. Obecnie wbudowane źródła to `ralphjsmit` i importery WordPressa (`wordpress-csv`, `yoast`, `rank-math` — zobacz [Migracja z WordPressa](/pl/guide/migrate-from-wordpress)). Zarejestruj własne w dostawcy usług:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```

