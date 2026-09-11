---
description: "Nadaj każdej stronie własny obraz Open Graph 1200×630, renderowany z szablonu Blade przez przeglądarkę headless, aby tytuły poprawnie się zawijały i skracały. Bezpłatna funkcja rdzenia, domyślnie wyłączona."
---

# Generowane obrazy OG {#generated-og-images}

Od wersji rdzenia 3.20 renderowanie przez Chrome wyłącza JavaScript i blokuje żądania zasobów HTTP(S), FTP i WebSocket. Własne szablony muszą używać statycznego HTML/CSS i osadzonych zasobów, tak jak szablony dołączone do pakietu.

Strona bez własnej karty społecznościowej korzysta z jednego wspólnego `default_og_image` — tego samego obrazu przy każdym udostępnieniu. Ta funkcja daje każdej stronie **własną** kartę Open Graph / Twitter 1200×630, renderowaną z szablonu Blade przez rzeczywistą przeglądarkę headless (przez [spatie/browsershot](https://github.com/spatie/browsershot)). Dzięki temu tytuł zawija się w wierszach, znaki diakrytyczne są renderowane, CJK korzysta z właściwego fontu zastępczego, a zbyt długie tytuły są poprawnie skracane — samodzielnie napisana biblioteka obrazów nie rozwiązuje sama tych problemów.

To bezpłatna funkcja rdzenia, **domyślnie wyłączona**. Gdy jest wyłączona, `default_og_image` jest używane bez zmian, a pakiet pozostaje bez dodatkowych zależności.

::: info Statyczne generowanie z wyprzedzeniem — świadome założenie
Karty są generowane z wyprzedzeniem poleceniem Artisan, a nie na bieżąco podczas żądania WWW. Strona odsyła tylko do karty, która już istnieje na dysku — żądanie odwiedzającego nigdy nie uruchamia przeglądarki i nigdy nie odsyła do brakującego obrazu (404). **Nie ma endpointu renderowania na żywo** (zobacz [Ograniczenia](#caveats)).
:::

## Wymagania {#requirements}

Sterownik przeglądarki jest opcjonalną zależnością, więc bezpłatny rdzeń instaluje się bez niego. Aby włączyć funkcję, potrzebujesz w aplikacji:

```bash
composer require spatie/browsershot
```

Oraz środowiska uruchamianego przez Browsershot:

- **Node.js** na hoście.
- **Puppeteer**, zainstalowanego w **katalogu głównym aplikacji**, aby Node go odnalazł:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium** — Puppeteer domyślnie pobiera własny Chromium; w produkcji zwykle wskazujesz systemowy Chrome (zobacz [`chrome_path`](#configuration)).

::: warning Na Windows zainstaluj puppeteer w katalogu głównym aplikacji
Na Windows zainstaluj `puppeteer` w katalogu głównym aplikacji, zamiast polegać na `npm_module_path`. Ten klucz konfiguracji odpowiada `setNodeModulePath()` Browsershot, które generuje prefiks POSIX `NODE_PATH=…` i **nie działa na Windows** — Node rozwiązuje tam moduły, przechodząc w górę katalogów od aplikacji, więc działa instalacja w jej katalogu głównym. Zobacz [Ograniczenia](#caveats).
:::

## Włączanie {#enabling}

Opublikuj konfigurację, jeśli jeszcze tego nie zrobiłeś (`php artisan vendor:publish --tag=seo-config`), i włącz opcję:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Następnie **wygeneruj karty z wyprzedzeniem** (dopóki tego nie zrobisz, nic nie jest renderowane):

```bash
php artisan seo:og-images
```

## Jak działa rozstrzyganie wartości {#how-resolution-works}

Generowanie nigdy nie nadpisuje ustawionego przez Ciebie obrazu. Gdy funkcja jest włączona, resolver uzupełnia `og:image` **tylko wtedy, gdy strona nie ma własnego obrazu** — czyli gdy rozstrzygnięte `og:image` jest puste lub nadal wskazuje wspólne statyczne `default_og_image` witryny. Jawny obraz modelu (z `getSEOImage()`, rekordu `seo_meta`, pola treści, …) zawsze ma pierwszeństwo przed wygenerowaną kartą.

Aby ustalić wartość, resolver wywołuje wyszukiwanie generatora **uzależnione od istnienia pliku**: oblicza ścieżkę karty w magazynie i zwraca jej publiczny URL **tylko wtedy, gdy plik już istnieje na skonfigurowanym dysku**. Nigdy nie renderuje. Na tym opiera się zabezpieczenie:

- Żądanie WWW **nigdy nie uruchamia przeglądarki** — w najgorszym razie wskazuje statyczne `default_og_image`, dokładnie tak jak przed dodaniem funkcji.
- Strona **nigdy nie odsyła do niewygenerowanego obrazu**, więc nie ma okresu, w którym udostępnienia wskazują 404.

Lukę między „treść się zmieniła” a „karta istnieje” zamykasz, uruchamiając polecenie [`seo:og-images`](#the-seo-og-images-command) — przy wdrożeniu lub zgodnie z harmonogramem.

## Polecenie `seo:og-images` {#the-seo-og-images-command}

Generuje karty z wyprzedzeniem, aby resolver miał co udostępnić.

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*` — jedna lub kilka klas modeli, dla których przygotować karty. Można powtarzać. Po pominięciu polecenie korzysta z `seo.og_image.models`, a w razie braku — z [modeli mapy witryny](/pl/guide/sitemaps) (`seo.sitemap.models`), zgodnie z tym samym podejściem współdzielenia źródeł mapy witryny co `seo:llms-txt`.
- `--force` — ponownie renderuje istniejące karty (użyj po zmianie szablonu lub kolorów marki bez zwiększenia `cache_version`).
- `--prune` — po przygotowaniu kart usuwa zapisane karty ze skonfigurowanej ścieżki, które nie odpowiadają już treści żadnego bieżącego modelu (zobacz poniżej). Dla bezpieczeństwa usuwa tylko pliki o nazwach będących wygenerowanymi skrótami treści (nigdy innych zasobów w tym samym katalogu) i jest **ignorowane przy uruchomieniu ograniczonym przez `--model`** (którego zestaw zachowywanych plików nie obejmowałby innych modeli) — uruchom bez `--model`.

Każdy model musi używać traitu `HasSEO`. Rekord bez tytułu jest pomijany (nie ma czego umieścić na karcie); polecenie raportuje liczby `generated`, `skipped`, `failed` oraz (z `--prune`) `pruned`.

### Harmonogram {#scheduling}

Przygotowuj karty zgodnie z harmonogramem, aby nadążały za treścią, i usuwaj osierocone pliki pozostające po zmianach tytułów:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Model unieważniania pamięci podręcznej {#the-invalidation-model}

Nazwa pliku karty jest **skrótem wszystkiego, co wpływa na jej piksele** — tytułu, nazwy witryny, nazwy szablonu, sterownika, wymiarów, kolorów gradientu marki, numeru `cache_version` **i zainstalowanej wersji pakietu**.

Ten skrót jest kluczem pamięci podręcznej i ma dwie konsekwencje, które warto rozumieć:

- **Zmiana tytułu → nowy skrót → nowy plik.** Stara karta staje się *osieroconym* plikiem na dysku, a strona wraca do statycznej wartości domyślnej, dopóki ponownie nie przygotujesz kart. Uruchomienie polecenia generuje nową kartę; `--prune` usuwa osieroconą. Tak działa unieważnianie — nie ma osobnego kroku „wyczyść jedną stronę”.
- **Zwiększenie `cache_version` lub aktualizacja pakietu → zmiana wszystkich skrótów.** Użyj `cache_version` po edycji szablonu lub kolorów marki, aby unieważnić wszystkie karty naraz; aktualizacja pakietu jest uwzględniana automatycznie, więc nowe wydanie zmieniające dołączony szablon nie może udostępniać nieaktualnych kart.

## Dołączone szablony {#bundled-templates}

Pakiet zawiera trzy szablony, wszystkie 1200×630 z tym samym gradientem marki:

| Szablon | Najlepszy do | Pokazuje |
| --- | --- | --- |
| `seo::og.default` | Dowolnej treści | Tytuł + nazwa witryny |
| `seo::og.article` | Wpisów blogowych, wiadomości | Nadtytuł sekcji + tytuł + podpis autor · data |
| `seo::og.product` | Produktów, ogłoszeń | Znak marki + etykieta kategorii + tytuł + opis |

Wybierz jeden globalnie przez `seo.og_image.template` lub mapuj szablony **według typu modelu**, aby artykuł i produkt automatycznie otrzymywały różne karty:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Model może też nadpisać własny szablon podczas działania, definiując `getOgImageTemplate(): ?string` (zwróć nazwę widoku albo `null`, aby użyć mapy/wartości domyślnej). Pierwszeństwo: hook modelu, następnie mapa `templates`, a potem globalne `template`.

## Dostosowywanie szablonu {#customizing-the-template}

Karta jest widokiem Blade (domyślnie `seo::og.default`) renderowanym do samowystarczalnego dokumentu HTML — dołączony font jest osadzony jako data URI, więc przeglądarka nie potrzebuje sieci. Są dwa sposoby zmiany:

**Opublikuj i edytuj dołączony widok:**

```bash
php artisan vendor:publish --tag=seo-views
```

Następnie edytuj `resources/views/vendor/seo/og/default.blade.php`.

**Lub wskaż własny widok:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

Szablon otrzymuje te zmienne:

| Zmienna | Typ | Uwagi |
| --- | --- | --- |
| `$title` | `string` | Tytuł OG, jeśli ustawiony, w przeciwnym razie tytuł strony. |
| `$siteName` | `?string` | Rozstrzygnięte `og:site_name`. |
| `$fontDataUri` | `string` | Dołączony pogrubiony font jako URI `data:` (pusty ciąg, jeśli niedostępny — przeglądarka używa wtedy własnego fontu bezszeryfowego). |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Szerokość wyniku (domyślnie `1200`). |
| `$height` | `int` | Wysokość wyniku (domyślnie `630`). |
| `$locale` | `?string` | Rozstrzygnięte ustawienia regionalne strony dla atrybutu `<html lang>`. |
| `$author` | `?string` | Autor artykułu (używany przez `seo::og.article`). |
| `$publishedDate` | `?string` | Data publikacji dla `seo::og.article`: średni format ICU w ustawieniach regionalnych strony, gdy dostępny; w przeciwnym razie Carbon tłumaczy miesiąc w kolejności `M j, Y`. Null, gdy nie podano daty. |
| `$section` | `?string` | Sekcja / kategoria treści (nadtytuł artykułu, etykieta produktu). |
| `$description` | `?string` | Opis OG, w przeciwnym razie opis strony (używany przez `seo::og.product`). |

::: info Nazwa szablonu jest częścią klucza pamięci podręcznej
Zarówno **nazwa** szablonu, jak i kolory gradientu wchodzą do skrótu treści, więc zmiana szablonu lub kolorów automatycznie unieważnia istniejące karty. Edycja szablonu *w miejscu* tego nie robi (nazwa się nie zmienia) — po edycji zwiększ `cache_version` (lub uruchom `--force`).
:::

## Konfiguracja {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

Większość wartości skalarnych ma odpowiadającą zmienną środowiskową (`SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH`, `SEO_OG_IMAGE_NO_SANDBOX`, …) — pełną listę znajdziesz w pliku konfiguracji. Klucze tablicowe (`templates`, `models`, `browsershot_args`, `font_stack`) edytuje się bezpośrednio w pliku konfiguracji.

Dysk musi być **publicznie udostępniany**, ponieważ resolver używa jego `url()` jako wartości `og:image`. Dla dysku `public` uruchom raz `php artisan storage:link`, aby `public/storage` na niego wskazywało.

## Uruchamianie w systemie Linux (sandbox) {#running-on-linux-the-sandbox}

Na hostach ograniczających mechanizmy sandboxa Chrome `php artisan seo:og-images` może zakończyć się błędem:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Jedną z możliwych przyczyn są ograniczone przestrzenie nazw użytkownika w Ubuntu 23.10+. Sprawdź [przewodnik rozwiązywania problemów Puppeteer](https://pptr.dev/troubleshooting) i rzeczywisty błąd uruchomienia przeglądarki. Preferuj naprawę konfiguracji hosta, aby Chrome mógł zachować sandbox.

**1. Jawne rozwiązanie awaryjne: uruchom Chrome z `--no-sandbox`.** Wyłącza to izolację przeglądarki. Używaj tylko wtedy, gdy świadomie akceptujesz ten kompromis we wdrożeniu:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Rankbeam renderuje statyczny wygenerowany HTML i blokuje żądania zdalnych zasobów, ale te mechanizmy nie zastępują sandboxa Chrome. Uruchamiaj proces renderowania bez podwyższonych uprawnień i izoluj go od niezwiązanych zadań i sekretów.

**2. Zachowaj sandbox.** Pozostaw `no_sandbox` wyłączone. Gdy przyczyną jest AppArmor, dostosuj profil do konkretnego pliku wykonywalnego Chrome; zobacz [wskazówki Chromium](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md). Na przykład:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Następnie załaduj profil przez `sudo apparmor_parser -r /etc/apparmor.d/chrome-og` i sprawdź, czy Chrome uruchamia się z włączonym sandboxem.

::: tip Inne flagi
Dla kontenera z małą ilością pamięci współdzielonej (drugi częsty problem na Linux — awaria Chrome podczas renderowania) dodaj flagi przez `browsershot_args`:

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Własne sterowniki {#custom-drivers}

`browsershot` jest jedynym dołączonym sterownikiem, ale renderer implementuje kontrakt (`Rankbeam\Seo\Contracts\OgImageRenderer`). Zarejestruj własny — np. renderer oparty na canvas lub usłudze — i wybierz go przez `seo.og_image.driver`:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

Sterownik wyłącznie zamienia samowystarczalny ciąg HTML na bajty PNG o podanym rozmiarze; nie odpowiada za układ ani szablony.

## Fonty i pisma inne niż łacińskie {#fonts-and-non-latin-scripts}

Dołączony font kart (Noto Sans Bold, OFL) obsługuje **pismo łacińskie, cyrylicę i grekę**. Każde inne pismo — chińskie, japońskie, koreańskie, tajskie, arabskie, hebrajskie, dewanagari, emoji — pochodzi z fontów **zainstalowanych na maszynie uruchamiającej `seo:og-images`**. Celowo nie dołączono innych: jeden font CJK ma ponad 16 MB, a własny mechanizm zastępczych fontów Chrome dla każdego znaku działa poprawnie, gdy tylko odpowiedni font istnieje na hoście.

Trzy rzeczy zapewniają niezawodność (3.15):

1. **Stos `font-family` według pisma w każdym dołączonym szablonie.** Body deklaruje najpierw `'OGBrand'` (dołączony krój), potem `seo.og_image.font_stack` — domyślnie `Noto Sans`, cztery rodziny `Noto Sans CJK`, `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`, `Noto Sans Devanagari`, `Noto Color Emoji` — a następnie `sans-serif`. Chrome wybiera zastępczo dla każdego znaku pierwszą zainstalowaną rodzinę, więc lista tylko pomaga; brakująca rodzina jest pomijana. **Rodzina CJK języka strony jest przesuwana na początek** (`ja` → JP, `zh-Hans` → SC, `zh-Hant` / `zh-TW` / `zh-HK` → TC, `ko` → KR), ponieważ ten sam punkt kodowy Han jest rysowany inaczej w każdym foncie narodowym (unifikacja Han), a atrybut `<html lang>` zawiera ustawienia regionalne strony w formie BCP 47. Stos jest częścią klucza pamięci podręcznej, więc jego zmiana ponownie renderuje każdą kartę.

2. **Kontrola wstępna w `seo:og-images`.** Przed renderowaniem polecenie pyta fontconfig (`fc-list :lang=ja`, `th`, `ar`, …), czy font obsługuje pisma w tytule, nazwie witryny i opisie, w tym mniej licznie reprezentowane pismo w tekście mieszanym, i ostrzega **raz na system pisma**, wskazując pakiet do zainstalowania:

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   Tam, gdzie nie ma fontconfig (Windows, macOS, minimalny kontener), nie zgłasza niczego, zamiast zgadywać. Samo renderowanie nigdy nie kończy się błędem z powodu brakującego fontu — Chrome rysuje prostokąty .notdef — i właśnie dlatego istnieje ostrzeżenie.

3. **Próbka glifów dla każdego pisma w rzeczywistym teście dymnym.** Z `SEO_OG_IMAGE_LIVE_TEST=1`, `tests/Feature/OgImage/BrowsershotSmokeTest.php` renderuje tytuł w ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he i hi obok próbki kontrolnej tej samej długości złożonej z nieprzypisanego punktu kodowego (gwarantowane prostokąty) i zgłasza błąd z nazwą pisma i pakietu, gdy oba PNG są bajtowo identyczne. To test dymny, a nie dowód obsługi każdego glifu: mieszany tekst łaciński lub inne zawijanie mogą powodować różnice obrazów nawet przy brakujących glifach. Sprawdź rzeczywisty render i fonty używane na hoście wdrożeniowym. Ostrzeżenie FontProbe dla języka również jest kontrolą wstępną, a nie pełnym certyfikatem pokrycia fontów. W rdzeniu nie ma polecenia `seo:doctor`; do tej kontroli użyj `seo:og-images`.

Na Debian/Ubuntu:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

Własne szablony opublikowane przez `--tag=seo-views` przed 3.15 nadal działają: otrzymują nowe zmienne `$fontFamily` i `$lang` i mogą je ignorować.

## Ograniczenia {#caveats}

Opisane wprost, ponieważ powodują problemy na produkcji:

- **Wyłącznie generowanie z wyprzedzeniem — brak endpointu renderowania na żywo (v1).** Nie ma trasy renderującej kartę na żądanie. Ponieważ nic nie jest renderowane podczas żądania WWW, **nie ma powierzchni podpisanych URL-i / SSRF / DoS do skonfigurowania ani ochrony** — kompromisem jest konieczność uruchamiania [`seo:og-images`](#the-seo-og-images-command) (przy wdrożeniu lub zgodnie z harmonogramem), aby karty istniały.
- **`npm_module_path` nie działa na Windows.** Odpowiada `setNodeModulePath()` Browsershot, które poprzedza polecenie prefiksem POSIX `NODE_PATH=…` — ignorowanym przez Windows. Na Windows zainstaluj `puppeteer` w **katalogu głównym aplikacji**, aby Node odnalazł go, przechodząc w górę katalogów. (Na Linux/macOS ustawienie działa zgodnie z oczekiwaniami).
- **Pisma inne niż łacińskie wymagają fontu na hoście.** Zobacz [Fonty i pisma inne niż łacińskie](#fonts-and-non-latin-scripts) poniżej: dołączony font obsługuje pismo łacińskie, cyrylicę i grekę; pozostałe pochodzą z fontów zainstalowanych w obrazie wdrożeniowym, a polecenie informuje o brakach.
- **Błąd nie blokuje działania strony.** Jeśli renderowanie nie powiedzie się (brak pakietu, awaria przeglądarki, przekroczenie czasu), polecenie to zgłasza, a strona po prostu zachowuje statyczne `default_og_image` — uszkodzona przeglądarka nigdy nie powoduje błędu 500 strony.
