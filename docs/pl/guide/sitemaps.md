---
description: "Generuj mapy witryny XML — po jednym pliku na źródło i indeks — udostępniane pod /sitemap.xml. Rejestruj źródła z modeli, domknięć lub list URL-i; korzysta ze spatie/laravel-sitemap."
---

# Rejestr map witryny {#sitemap-registry}

Pakiet generuje mapy witryny XML (po jednym pliku na źródło i indeks) i udostępnia je pod `/sitemap.xml` oraz `/sitemap-{name}.xml`. Generowanie korzysta ze [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

## Rejestrowanie źródeł {#registering-sources}

Rejestruj nazwane źródła w `boot()` dostawcy usług:

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

Każde źródło jest renderowane do `sitemap-{name}.xml`; `sitemap.xml` staje się indeksem wymieniającym je wszystkie.

API rejestru oferuje również `has($name)`, `names()`, `forget($name)` i `flush()`.

## Źródła z konfiguracji {#config-driven-sources}

Wolisz konfigurację? `config/seo.php` akceptuje źródła modeli i statyczne URL-e:

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info Automatyczne wykrywanie ustępuje rejestrowi
Jeśli model jest objęty nazwanym zarejestrowanym źródłem, automatyczne wykrywanie go pomija — rejestracja `'posts'` nie wygeneruje dodatkowo `sitemap-post.xml`.
:::

## Generowanie {#generating}

```bash
php artisan seo:sitemap
```

Pliki są zapisywane na dysku skonfigurowanym w `seo.sitemap.disk` (domyślnie `public`). Zaplanuj polecenie, aby mapy witryny pozostawały aktualne:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Mapy witryny przekraczające `seo.sitemap.max_urls_per_sitemap` (domyślnie 50 000 — limit specyfikacji XML) są dzielone automatycznie.

## Udostępnianie {#serving}

Trasy pakietu udostępniają wynik polecenia z nagłówkami XML, nagłówkami pamięci podręcznej i `X-Robots-Tag: noindex`:

- `/sitemap.xml` — indeks (lub pojedyncza mapa witryny)
- `/sitemap-posts.xml` — nazwane źródło

Udostępniasz własną statycznie wygenerowaną mapę witryny? Wyłącz trasy:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Stylizowana mapa witryny w przeglądarce {#styled-sitemap-in-the-browser}

Silnik Spatie renderuje ścianę surowego XML. Otwórz mapę witryny Rankbeam w przeglądarce, a zobaczysz czytelną stronę z identyfikacją marki — każdy URL w tabeli z `lastmod`, częstotliwością zmian, priorytetem i liczbą obrazów/alternatyw oraz uwagami walidacji przy wpisach:

![Mapa witryny Rankbeam wyświetlona w przeglądarce jako czytelna tabela z identyfikacją marki](/sitemap-styled.png)

Działa to przez odwołanie do arkusza XSL z każdej wygenerowanej mapy witryny:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

Wyszukiwarki **ignorują** tę instrukcję, więc mapa witryny pozostaje zwykłym dokumentem XML do odczytu maszynowego — zmienia się tylko to, co widzi *człowiek*. Indeks i każda podrzędna mapa witryny mają ten sam styl.

**Domyślnie włączone.** W przeciwieństwie do rozszerzeń obrazów/hreflang arkusz stylów nie dodaje danych i nie wykonuje pracy dla każdego rekordu — to jedna linia instrukcji pomijana przez roboty, więc jest włączony od początku. Wyłącz go, aby generować zwykły XML:

::: warning Wymaga spatie/laravel-sitemap ≥ 8.1
Instrukcja jest zapisywana przez `setStylesheet()` Spatie, dodane w `spatie/laravel-sitemap` **8.1**. Jeśli aplikacja rozwiąże zależności do starszej wersji (zdarza się przy niektórych kombinacjach PHP/Laravel), mapy witryny są generowane jako zwykły XML bez stylu — nic się nie psuje. Użyj `composer update spatie/laravel-sitemap`, aby uzyskać stylizowany widok.
:::

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### Uwagi walidacji {#validation-notes}

Wyrenderowana strona oznacza dwie rzeczy, które może sprawdzić bez opuszczania przeglądarki:

- **URL-e bez `lastmod`** — brak jest pokazywany, nigdy uzupełniany zmyśloną wartością. Google przywiązuje mniejszą wagę do mapy witryny, która podaje nieprawdziwą świeżość, więc arkusz wskazuje lukę zamiast ją uzupełniać.
- **URL-e inne niż bezwzględne adresy HTTP(S)** — `<loc>`, które nie jest bezwzględnym URL-em `http(s)`.

### Samodzielne hostowanie arkusza stylów {#self-hosting-the-stylesheet}

Domyślnie pakiet udostępnia arkusz stylów z własnej trasy `/sitemap.xsl` i kieruje do niego każdą mapę witryny. Przeglądarki stosują tylko XSLT o **tym samym pochodzeniu** co mapa witryny, więc jeśli mapy znajdują się pod innym origin (np. w CDN), opublikuj plik i wskaż w konfiguracji własną kopię:

```bash
php artisan vendor:publish --tag=seo-assets
```

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => [
        'url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
    ],
],
```

::: info Bezpieczeństwo wynikające z konstrukcji
Każda wartość renderowana przez arkusz — również URL-e — przechodzi kodowanie znaków specjalnych wyjścia XSLT, a `<loc>` staje się klikalnym linkiem tylko wtedy, gdy jest URL-em `http(s)`. Dzięki temu złośliwa treść URL-a nie może wstrzyknąć znaczników ani linku `javascript:` do strony. Jeśli dostosowujesz opublikowany `.xsl`, zachowaj tę zasadę: nie dodawaj `disable-output-escaping`.
:::

## Co jest uwzględniane {#what-gets-included}

Źródła modeli uwzględniają rekordy rozstrzygnięte jako możliwe do indeksowania; model, którego robots jest rozstrzygane do `noindex`, nie trafia do mapy witryny. URL-e pochodzą z `getUrlForSEO()` — tej samej metody, która dostarcza kanoniczne URL-e, więc mapa witryny i adres kanoniczny nigdy się nie różnią.

## Rozszerzenia obrazów i hreflang {#image-hreflang-extensions}

Dwa opcjonalne rozszerzenia wzbogacają URL każdego modelu o dane, które pakiet już rozstrzyga dla tego rekordu. Oba są **domyślnie wyłączone** — włącz wybrane w `config/seo.php`:

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

Dotyczą modeli używających traitu `HasSEO` (wartości pochodzą z w pełni rozstrzygniętego `seoData()` modelu):

- **`images`** dodaje wpis [mapy obrazów Google](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps) zbudowany z rozstrzygniętego obrazu OG/treści — *tej samej* wartości renderowanej jako `og:image`, więc mapa witryny nigdy nie przeczy stronie. Gdy rekord nie ma własnego obrazu, jest to wspólne `default_og_image` witryny, dlatego włącz tę opcję tylko wtedy, gdy obraz dla każdego URL-a ma sens dla Twoich treści.
- **`alternates`** dodaje wpisy `<xhtml:link rel="alternate" hreflang="…">` z `getSEOAlternates()` modelu — te same linki hreflang, które są renderowane w sekcji `<head>` strony. Zwracaj bezwzględne URL-e:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning hreflang musi być wzajemny i odwoływać się do samej strony
Google respektuje adnotację tylko wtedy, gdy każda wersja językowa wymienia **siebie i wszystkie pozostałe**, a odwołania są **wzajemne** (każda strona odsyła z powrotem). Dlatego `getSEOAlternates()` musi zwracać **kompletny** zestaw, a każdy wariant językowy musi zwracać ten sam kompletny zestaw. Używaj poprawnych kodów `language[-Script][-REGION]` lub `x-default` i bezwzględnych URL-i `http(s)`. Wpisy bez niepustego `hreflang` lub `href` są pomijane.

Lista przechodzi przez [zasady `seo.hreflang`](/pl/guide/multilingual#hreflang) przed zapisem — kody są normalizowane do BCP 47 (`it_IT` → `it-IT`), a `include_self` / `x_default` mogą dodać za Ciebie odwołanie do siebie i `x-default` — mapa witryny zawsze zawiera tę samą listę co sekcja `<head>` strony. Bezpłatny audyt raportuje `hreflang_invalid_code`, `hreflang_duplicate_code` i `hreflang_missing_self`; wzajemność wymaga skanowania witryny (Pro).
:::

::: info Koszt przy dużej skali
Od **rdzenia 3.20.1** kwalifikowanie modelu i rozszerzenia obrazów/hreflang ponownie używają tego samego rozstrzygniętego `seoData()` podczas budowania URL-a danego modelu. Ponowne użycie kończy się po zakończeniu próby zbudowania URL-a, również przy błędzie; późniejsze budowanie lub inny wariant językowy rozstrzyga świeże dane. W 3.20.0, przy wyłączonej pamięci podręcznej resolvera (domyślnie), kwalifikowanie wraz z rozszerzeniami mogło dwukrotnie przechodzić przez łańcuch pierwszeństwa. Każde rozstrzygnięcie nadal może wykonywać operacje pamięci podręcznej/bazy danych, a własne gettery `getSEO*()` mogą dodawać zapytania. Uruchamiaj polecenie `seo:sitemap` **według harmonogramu** zamiast żądania WWW. Zmierz wydajność w pobliżu limitu 50 000 URL-i i pozostaw rozszerzenia wyłączone, gdy te wpisy są zbędne.
:::

::: tip Masz już opublikowaną konfigurację?
`config/seo.php` jest scalane **płytko**, więc aplikacja, która opublikowała plik konfiguracji przed tym wydaniem, nie otrzyma automatycznie kluczy `sitemap.images` / `sitemap.alternates` — same zmienne środowiskowe `SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES` ich nie włączą. Dodaj oba klucze do opublikowanej tablicy `sitemap` (zobacz blok powyżej) lub ponownie opublikuj konfigurację.
:::

## Pełna kontrola: ręcznie budowane tagi Spatie {#full-control-hand-built-spatie-tags}

Dla wszystkiego, czego rozstrzygnięte dane nie obejmują — podpisów obrazów, wpisów **wideo** lub **wiadomości** albo własnych zestawów `hreflang` — zwracaj z zarejestrowanego źródła w pełni ręcznie zbudowany [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images). Builder przekazuje tagi `Url` dosłownie i nigdy nie dodaje do nich własnych rozszerzeń, więc zachowujesz pełną kontrolę:

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

Ta sama możliwość jest dostępna dla każdego rekordu: model implementujący `Sitemapable`, którego `toSitemapTag()` zwraca `Url`, jest generowany dokładnie w zwróconej postaci.
