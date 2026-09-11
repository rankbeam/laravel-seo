---
description: "Dodaj kompletną sekcję SEO do formularza dowolnego zasobu Filament w dwóch liniach za pomocą bezpłatnego pakietu laravel-seo-filament. Obsługuje Filament 4.x i 5.x z traitem HasSEO."
---

# Pola administracyjne Filament {#filament-admin-fields}

Bezpłatny pakiet [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) dodaje kompletną sekcję SEO do formularza dowolnego zasobu Filament — **dwie linie na zasób**. Obsługuje Filament **4.x i 5.x** (Livewire 3 i 4). Edycja metadanych jest bezpłatna; Pro dodaje skany i ocenę widoczną w poniższym przykładzie.

## Wymagania wstępne {#prerequisites}

Użyj istniejącego panelu Filament 4 lub 5 i modelu z traitem `HasSEO` rdzenia. Przed dodaniem edytora wykonaj [Szybki start rdzenia](/pl/guide/quickstart), łącznie z migracjami i renderowaniem.

## Instalacja {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

Model zasobu musi używać traitu `HasSEO` rdzenia.

## Dodaj sekcję do zasobu {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## Sprawdź zapisany wynik {#check-the-saved-result}

Otwórz istniejący rekord. Wpisz opis SEO, zapisz i ponownie załaduj formularz. Wartość powinna zostać zachowana, podgląd powinien ją pokazywać, a źródło powinno wskazywać **Ręcznie**. Sprawdź sekcję `<head>` wyrenderowanej strony, aby potwierdzić, że ten sam opis trafia do odwiedzających.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="Pola SEO w demo Merchant: tytuł, opis, kanoniczny URL, obraz społecznościowy, podgląd wyszukiwania i źródła rozstrzygniętych wartości." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Przykład z demo Merchant. Pola korzystają z motywu Twojego panelu; dostępne kontrolki i limity znaków zależą od zainstalowanej wersji i konfiguracji.*

Sekcja zawiera:

- **Tytuł i opis** z licznikami znaków na żywo — limit wynika z [zasad długości](/pl/guide/multilingual#title-and-description-budgets-per-script) rdzenia dla wpisywanego systemu pisma (60/160 dla tekstu łacińskiego, około 30/80 dla CJK, liczone jako grafemy).
- **Główne słowa kluczowe** — pole tagów. Wpisujesz zwykłe słowa kluczowe; są zapisywane w strukturze `[{keyword, is_primary}]` rdzenia (pierwsze jest główne), dzięki czemu `getPrimaryKeyword()` i `SEOData` odczytują je bez zmian. Włącz `seo.keywords.enabled`, aby polecenie [`seo:audit`](/pl/guide/audit) i skan Pro oznaczały strony nadal pozbawione słowa kluczowego (domyślnie wyłączone — jeden wspólny przełącznik, zobacz [Konfiguracja](/pl/reference/configuration#focus-keywords)).
- **Kanoniczny URL** (pusty = automatyczny, bez parametrów zapytania).
- Lista wyboru **Robots** (pusta = domyślna wartość witryny).
- Przesyłanie **obrazu do udostępniania w mediach społecznościowych** (og:image / twitter:image), zapisywanego na domyślnym dysku Filament w `seo/`.
- **Podgląd wyniku wyszukiwania**, który odzwierciedla łańcuch wartości zastępczych resolvera na żywo podczas pisania.
- **Wskaźniki źródeł** — warstwa resolvera, która dostarczyła wynikową wartość każdego pola: *Ręcznie*, *Z treści*, *Domyślne dla typu modelu*, *Domyślne globalne*, *Konfiguracja witryny* lub *Wyprowadzone z URL*.

## Ograniczanie pól {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

Akceptuje dowolny podzbiór `title`, `description`, `focus_keywords`, `canonical`, `robots`, `og_image`.

Bez traitu `SEOFields::make(?array $only)` zwraca bezpośrednio tę samą sekcję.

## Jak zapisywane są wartości {#how-values-persist}

Sekcja jest powiązana z grupą stanu `seo_meta` i zapisuje przez relację `seoMeta()` rdzenia (aktualizacja lub utworzenie) — bez kolumn we własnych tabelach. Wartości natychmiast stają się warstwą 6 (jawną) w [resolverze](/pl/concepts/resolver-precedence).

## Kilka języków {#several-languages}

Rdzeń przechowuje [jeden rekord `seo_meta` na parę (model, ustawienia regionalne)](/pl/guide/multilingual). Przekaż języki, w których strona jest opublikowana, a sekcja wyświetli **jedną kartę na język** (Filament 1.9):

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

Możesz też ustawić to raz dla wszystkich zasobów w konfiguracji pakietu:

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

Każda karta edytuje własny rekord i ma własne:

- **liczniki** — zgodne z [zasadami długości](/pl/guide/multilingual#title-and-description-budgets-per-script) dla systemu pisma danego języka, więc pusty japoński tytuł pokazuje `0 / 30`, a angielska karta tej samej strony — `0 / 60`;
- **podgląd** (SERP / karta społecznościowa) renderowany z rozstrzygniętych wartości danego wariantu językowego;
- **wskaźniki wartości zastępczych** opisujące rekord danego wariantu językowego;
- **oznaczenie** liczby ustawionych pól w danej wersji, dzięki któremu puste tłumaczenia są łatwo widoczne.

Karta jest podpisana nazwą języka w języku panelu (`Italiano` / `Italian`), gdy załadowano `ext-intl`, a w przeciwnym razie — kodem. Wszystkie karty są walidowane i zapisywane razem; język, dla którego niczego nie wpisano, nigdy nie otrzymuje pustego rekordu zastępczego.

::: details Własne powiązania stanu formularza
Przy kilku wariantach językowych ścieżka stanu to `seo_meta.{locale}.title`; przy jednym pozostaje `seo_meta.title`. We własnych akcjach formularza używaj odpowiedniej ścieżki.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Karty angielska, włoska i japońska w demo Merchant, z japońskimi limitami tytułu i opisu 30 i 80 oraz nieustawionym opisem." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Demo Merchant, 9 września 2026, z `locales: ['en', 'it', 'ja']`. Pusta karta japońska ma własne liczniki. Angielski tytuł pochodzi tutaj z wartości zastępczej treści modelu demonstracyjnego: dodanie karty języka nie tłumaczy treści. Ocena Pro nad polami jest wynikiem ostatniego skanu rekordu, a nie osobną oceną każdej karty języka.*

### Z wtyczką tłumaczeń {#with-a-translatable-plugin}

Z `lara-zeus/spatie-translatable` **1.x na Filament 4** lub **2.x na Filament 5** używaj adapterów stron Edit i Create dostarczanych przez Rankbeam. Zastąp tylko importy traitów stron; zachowaj traity zasobu/listy wtyczki, wtyczkę panelu i akcję `LocaleSwitcher`:

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

Każda strona nadal deklaruje `use Translatable;` wewnątrz swojej klasy. Wtyczka pozostaje opcjonalną zależnością aplikacji. Używaj najnowszej wersji z poprawkami; lokalny scenariusz integracyjny obejmuje wtyczkę 1.0.4 / Filament 4.13.1 oraz wtyczkę 2.0.1 / Filament 5.8.1.

Przełączanie zachowuje w edytorze niezapisaną treść nadrzędnego rekordu, metadane SEO i wersje robocze danych strukturalnych. Zapis waliduje każdy odwiedzony język i zapisuje je razem w transakcji bazy danych. Błąd walidacji otwiera język wymagający uwagi. Przesyłane pliki są zapisywane przy zapisie formularza; opuszczenie lub ponowne załadowanie strony odrzuca niezapisane wersje robocze. Zapis wersji roboczej nie tłumaczy brakujących treści za Ciebie.

Adaptery zachowują zwykłe hooki przed/po i mutatory danych formularza. Jeśli Twoja strona nadpisuje `handleRecordCreation()`, `handleRecordUpdate()`, `callHook()` lub metody transakcji, włącz zachowanie adaptera do tych zmian i przetestuj zapis. Transakcje bazy danych nie wycofują zapisów w systemie plików; aplikacje powinny zachować zwykłe czyszczenie osieroconych plików.

Dla własnych pól tekstowych aktualizowanych na żywo w Livewire 3 wybieraj `->live()` lub `->live(onBlur: true)` zamiast jawnego debounce: ten ostatni opóźnia lokalny stan modelu i może zgubić ostatnie naciśnięcia klawiszy podczas szybkiej zmiany języka. Pola tytułu i opisu Rankbeam używają domyślnego debounce żądań.

Same traity stron z wtyczki ponownie wypełniają formularze podczas przełączania. Rankbeam chroni przed ich przypadkowymi zapisami metadanych, ale te traity nie zachowują wersji roboczych SEO; przenieś strony Edit/Create na adaptery. Jawne karty `locales:` pozostają wspólnym edytorem i mają pierwszeństwo przed przełącznikiem strony.

Bez jawnej listy języków ani ustawień regionalnych strony sekcja edytuje wariant zgodny z ustawieniami aplikacji.

## Dane strukturalne (schema.org) {#structured-data-schema-org}

Opcjonalna sekcja **Dane strukturalne** pozwala redaktorom dołączać dane JSON-LD dla wyników rozszerzonych bez dotykania kodu. Dodaj ją obok sekcji SEO:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

(lub bezpośrednio `SEOSchemaFields::make()`, bez traitu).

Zapisuje w kolumnie `seo_meta.schema_jsonld` rdzenia — tę samą wartość generuje [renderer danych strukturalnych](/pl/guide/schema) — i jest **wyłącznie powiązaniem interfejsu**: każdy dokument powstaje przez builder danych strukturalnych rdzenia i przed zapisem jest walidowany przez `SchemaValidator` rdzenia. Sekcja nie dodaje własnej logiki danych strukturalnych.

Sekcja oferuje:

- **Automatyczna ścieżka nawigacyjna** — pojedynczy przełącznik, pokazany na początku jako korzyść niewymagająca konfiguracji. Wyprowadza `BreadcrumbList` z łańcucha rodziców rekordu przez `BreadcrumbSchema::fromModelAncestors()`. Niczego nie trzeba uzupełniać — podąża za przodkami modelu.
- **Bloki schematu** — pole powtarzalne. Każdy blok to **FAQ** (pary pytanie / odpowiedź → `FAQPage`) albo **Produkt** (nazwa, opis, obraz, marka, SKU, cena i waluta, dostępność → `Product`), budowany przez buildery `FAQSchema` / `ProductSchema` rdzenia.

### Walidacja {#validation}

Blok tworzący nieprawidłowy JSON-LD jest **odrzucany przy zapisie** z komunikatem walidatora rdzenia — np. wpis FAQ bez odpowiedzi lub Product bez obrazu albo oferty (ten builder wymaga tych pól; nie jest to pełny opis wymagań Google dla każdej funkcji wyszukiwania Product). Puste bloki są po prostu ignorowane.

### Co zapisuje {#what-it-stores}

`schema_jsonld` zawiera zbudowane dokumenty: pojedynczy obiekt, gdy jest jeden, albo tablicę JSON, gdy jest ich kilka (najpierw ścieżka nawigacji, potem Twoje bloki). Obie formy są poprawnym JSON-LD i są renderowane bez zmian przez `@seo` / `renderSchema()`.

### Dane strukturalne, którymi nie zarządza {#schema-it-doesn-t-manage}

Dane strukturalne napisane w kodzie, których ten edytor nie potrafi przedstawić — ręcznie przygotowany `@graph`, nietypowy `@type` lub Product z polami niedostępnymi w formularzu (recenzje, oceny, GTIN/MPN) — są **zachowywane dosłownie**. Otwarcie i zapis formularza nigdy ich nie nadpisują.

## Rozwiązywanie problemów {#troubleshooting}

- **Zapisanego pola brakuje na stronie:** potwierdź, że szablon renderuje `@seo($model)` dla tego samego rekordu i wariantu językowego.
- **Pole nadal korzysta z wartości zastępczej:** sprawdź, czy ma zapisane nadpisanie w aktywnym języku. Wskaźniki źródeł wskazują rozstrzygniętą warstwę.
- **Brakuje karty języka:** sprawdź jawny argument `locales:`, konfigurację pakietu i ewentualny przełącznik tłumaczeń na poziomie strony. Ich pierwszeństwo opisano powyżej.

::: details Testowanie własnego panelu w Testbench

Jeśli uruchamiasz Filament wewnątrz orchestra/testbench, zarejestruj `SupportServiceProvider` Filament **przed** `LivewireServiceProvider` — Filament ponownie wiąże `DataStore` Livewire, a błędna kolejność powoduje niepowodzenie każdego testu Livewire z `ViewErrorBag::put(): ... null given`. Nie dotyczy to zwykłych aplikacji (wykrywanie pakietów poprawnie porządkuje dostawców).
:::
