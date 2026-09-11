---
description: Wszystkie opcje config/seo.php, pogrupowane według warstw rozstrzygania, z domyślną wartością każdej z nich.
---

# Konfiguracja {#configuration}

Opublikuj plik konfiguracji:

```bash
php artisan vendor:publish --tag=seo-config
```

Wszystkie poniższe ustawienia znajdują się w `config/seo.php`. Pokazane wartości są domyślne.

## Domyślne wartości całej witryny (warstwa 1) {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

Wartość `title_suffix` jest dopisywana do wynikowego tytułu, chyba że tytuł już kończy się tym przyrostkiem.

`title_suffix_skip_when_contains` to lista wyłączająca przyrostek na podstawie nazwy marki. Jeśli wynikowy tytuł zawiera już jeden z tych elementów **jako całe słowo** (bez rozróżniania wielkości liter, z uwzględnieniem granic słów — dlatego `Acmestic` nie pasuje do `Acme`), przyrostek jest pomijany, aby nie powtarzać marki w tytule. Domyślne `[]` zachowuje dotychczasowe zachowanie.

## Zasady renderowania robots {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

Renderowany `<head>` pomija znacznik `<meta name="robots">`, gdy wynikowa dyrektywa jest równa powyższemu `default_robots` — nadmiarowe `index,follow` nie wnosi informacji, a brak znacznika robot traktuje właśnie jako index,follow. Dyrektywa **różniąca się** od domyślnej (`noindex`, `nofollow`, `max-snippet:-1`, …) jest zawsze zwracana bez zmian. Ustaw `emit_default` na `true`, aby zawsze renderować znacznik (przywraca zachowanie sprzed 3.1). Osobna dyrektywa `@seoRobots` pozostaje bez zmian — jej użycie jest jawnym wyborem i zawsze powoduje renderowanie. Obsługiwane dyrektywy i ich priorytety opisuje [Kontrakt renderowania](/pl/contributing/rendering-contract).

## Zabezpieczenie indeksowania (ochrona poza produkcją) {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Gdy zabezpieczenie jest włączone, a środowiska aplikacji **nie ma** w `allowed_environments`, wymusza ono `noindex,nofollow` na każdej stronie — ponad całym łańcuchem priorytetów, więc nadpisuje nawet zapisaną wartość konkretnej strony. Wysyła zgodny nagłówek `X-Robots-Tag`, generuje `robots.txt` zabraniający dostępu wszystkim robotom i dodaje komunikat do wyniku `seo:audit`. W dozwolonych środowiskach (domyślnie `production`) nie działa.

Domyślnie jest **wyłączone** — do momentu włączenia wynik pozostaje identyczny co do bajtu. Włącz je przez `SEO_INDEXING_GUARD=true`, a wyłącz przez `SEO_INDEXING_GUARD=false`: w obu przypadkach wystarczy jeden wiersz. Listę dozwolonych środowisk nadpiszesz przez `SEO_INDEXING_GUARD_ALLOWED` (wartości rozdzielone przecinkami; działają wzorce `Str::is()`, np. `prod*`; pusta lista włącza ochronę we wszystkich środowiskach).

`send_header`, domyślnie włączone w ramach zabezpieczenia, wysyła też `X-Robots-Tag: noindex,nofollow` w każdej odpowiedzi przechodzącej przez aplikację. Dyrektywa obejmuje więc również PDF-y, kanały i obrazy, które nie zawierają `<meta robots>`. Middleware rejestrowany jest tylko po włączeniu zabezpieczenia. Zdecydowanie zalecane — zobacz pełny [przewodnik po zabezpieczeniu indeksowania](/pl/guide/indexing-guard).

## Kanoniczne URL-e {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Adres kanoniczny **wyprowadzany** przez mechanizm rozstrzygania z URL-a żądania lub `getUrlForSEO()` modelu domyślnie traci parametry zapytania — parametry śledzenia, filtrowania i sortowania tworzą dla tej samej strony adresy kanoniczne prowadzące do zduplikowanej treści. Wymień klucze w `query_whitelist`, a zostaną **zachowane** w wyprowadzanym adresie kanonicznym, w podanej kolejności; pozostałe parametry nadal będą usuwane. Typowym przykładem jest `page` dla stronicowanych archiwów (`/blog?page=2` to inna strona niż `/blog`).

Adres kanoniczny **ustawiony jawnie** — przez administratora lub warstwę o wyższym priorytecie — jest zawsze zwracany dokładnie, razem z parametrami zapytania. Lista dozwolonych parametrów dotyczy wyłącznie wyprowadzanej wartości zastępczej. Domyślne `[]` zachowuje usuwanie wszystkich parametrów.

## Przełączniki funkcji {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` tworzy pusty rekord `seo_meta` przy utworzeniu modelu z `HasSEO` (uwaga: seedery używające `WithoutModelEvents` pomijają ten mechanizm).

## Główne słowa kluczowe {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

To **przełącznik obsługi** głównych słów kluczowych. Przy `false` (wartości domyślnej) brak głównego słowa kluczowego nie jest nigdzie zgłaszany — ani przez [`seo:audit`](/pl/guide/audit), ani przez skanowanie Pro. Aplikacja, która nie używa tej funkcji, nie otrzymuje więc dotyczących jej powiadomień. Włącz ją, gdy zaczniesz ustawiać główne słowa kluczowe, np. przez [pole głównego słowa kluczowego Filament](/pl/guide/filament). Bezpłatny audyt, skanowanie Pro i edytor Pro zaczną wtedy zgłaszać `missing_focus_keyword` na stronach, którym nadal go brakuje. Wszystkie odczytują tę samą flagę, więc ich zachowanie jest zgodne.

## Bezpłatny audyt (`seo:audit`) {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

Modele sprawdzane przez bezpłatne polecenie [`seo:audit`](/pl/guide/audit), gdy nie podano opcji `--model`. Każdy musi używać traitu `HasSEO`. Jeśli lista jest pusta, polecenie korzysta z modeli zarejestrowanych w `sitemap.models`.

## Obliczane wartości zastępcze (warstwa 5) {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

Przy opcjonalnej strategii `best` mechanizm budujący ocenia uporządkowaną listę kandydatów według tego, jak bliskie ideału są wymiary obrazu w pikselach, i **pomija obrazy poniżej minimum**. Najpierw rozpatruje `getSEOImage()` (zachowuje najwyższy priorytet), następnie metodę `getSEOImages()` modelu, typowe pola obrazów, pierwszy obraz z treści i skonfigurowaną wartość domyślną. Mierzone są wyłącznie obrazy **lokalne**: ścieżka względna w `public/`, dysk publiczny lub bezwzględny URL na własnym hoście. Zdalny URL nigdy nie jest pobierany i służy tylko jako wartość zastępcza. Jeśli żaden lokalny kandydat nie spełnia minimum, wybór wraca do pierwszego dopasowania, więc `best` nie zwraca mniej niż `first`. Udostępnij kandydatów z modelu:

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## Mapy witryny {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

Źródła rejestrowane programowo opisuje [przewodnik po rejestrze map witryny](/pl/guide/sitemaps).

## Schemat (JSON-LD) {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

Te wartości zasilają węzły [grafu schematu](/pl/guide/schema).

## Trasy {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Ustaw `enabled => false`, jeśli aplikacja udostępnia własny statyczny `/sitemap.xml`.

## Pamięć podręczna {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Pamięć podręczna wyniku rozstrzygania {#resolver-result-cache}

`SEOResolver` przechodzi cały łańcuch priorytetów przy **każdym** renderowaniu frontendu: konfiguracja → wartości domyślne globalne / typu modelu / trasy → wartości obliczone z modelu → jawne `seo_meta` → przyrostek tytułu / adres kanoniczny / schemat. W witrynie o dużym ruchu (aplikacja referencyjna obsługuje około 20 tys. żądań dziennie) oznacza to kilka odczytów bazy na stronę.

Włącz `cache.resolver.enabled`, a w pełni rozstrzygnięte dane SEO modelu trafią do pamięci podręcznej. **Trafienie w pamięci podręcznej pomija cały łańcuch priorytetów**: w teście wydajności pakietu odczyt gotowego wpisu wykonuje **zero** zapytań do bazy, podczas gdy każde rozstrzyganie bez pamięci podręcznej ponownie odczytuje `seo_meta` modelu. Zapisywana jest zwykła tablica, odtwarzana przez `SEOData::fromArray()`, nigdy obiekt — Laravel 13 domyślnie ustawia `cache.serializable_classes = false`, więc zapisany obiekt wraca jako `__PHP_Incomplete_Class`.

Mechanizm korzysta ze skonfigurowanego powyżej `store`. Na produkcji wskaż więc **współdzieloną, trwałą pamięć podręczną** (`redis` / `memcached`): zarówno wpisy, jak i ich unieważnianie muszą być widoczne dla każdego procesu obsługującego żądania i kolejki. Pozostaw funkcję wyłączoną, dopóki nie masz takiego magazynu.

**Unieważnianie jest automatyczne i poprawne** — włączona pamięć podręczna daje taki sam wynik rozstrzygania jak wyłączona. Klucze wpisów mają postać `(model class, id, locale, route, request URL)`, a wpisy są usuwane, gdy:

- rekord `seo_meta` strony jest **zapisywany lub usuwany** dowolną drogą: przez `saveSEO()`, Filament lub bezpośredni zapis `SEOMeta`;
- zmienia się **pole treści** modelu, czyli kolumna z `getSEOContentFields()` (domyślnie obejmuje wszystkie wbudowane pola obliczanych wartości zastępczych: title/headline, excerpt/summary/content/body/text/article oraz typowe pola obrazów, takie jak `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` i `hero_image`; nadpisz tę metodę, jeśli model oblicza SEO z dodatkowych kolumn);
- zmienia się **dowolny rekord `seo_defaults`** — wartość domyślna może zasilać każdy model, więc czyszczona jest cała pamięć podręczna rozstrzygania.

W magazynie **obsługującym tagi** (`redis`, `memcached`, `array`) wpisy modelu są usuwane przez **tagi** pamięci podręcznej. W magazynie **bez obsługi tagów** (`file`, `database`) pakiet używa **znacznika wersji** osobnego dla każdego modelu. Obie metody działają bez przeszukiwania kluczy.

::: tip
W pamięci podręcznej zapisywane są tylko wyniki rozstrzygania oparte na modelach. `SEO::render()`/`@seo()` dla ręcznie zbudowanego `SEOData` i `@seoForRoute()` dla trasy bez modelu nadal wyliczają wynik na bieżąco.
:::

::: warning
Pamięć podręczna odzwierciedla `updated_at` modelu i obliczone `modified_time` z ostatniej zmiany **pola treści**, do następnej takiej zmiany lub wygaśnięcia TTL. Samo `touch()`, zmieniające tylko `updated_at` bez zmiany kolumny z `getSEOContentFields()`, nie wymusza ponownego rozstrzygania — `article:modified_time` może być opóźnione nawet o cały TTL. Dodaj własne kolumny używane do obliczeń do `getSEOContentFields()`, jeśli ich zmiana ma od razu unieważniać wpis.
:::
