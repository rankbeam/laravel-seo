---
description: "Jak Rankbeam obsługuje treści w językach innych niż angielski: limity tytułów i opisów według pisma, skracanie bez rozdzielania grafemów, wielkość liter zależna od ustawień regionalnych, normalizacja i zasady hreflang, inLanguage, wyszukiwarki regionalne, weryfikacja witryny, fonty obrazów OG i URL-e Unicode."
---

# Treści wielojęzyczne {#multilingual-content}

[Tłumaczenia](/pl/guide/translations) sprawiają, że *pakiet* mówi Twoim językiem. Ta strona dotyczy drugiej połowy: sprawienia, aby pakiet **rozumiał język Twoich treści**. Limit 60 znaków tytułu jest niewłaściwy dla japońskiego, cięcie na granicy słowa psuje tajski, `İstanbul` i `istanbul` to to samo słowo w tureckim, hreflang `it_IT` jest nieprawidłowy, a koreańskiej witrynie zależy na robocie Naver, nie tylko Google. Żaden z tych przypadków nie dotyczy tłumaczenia — chodzi o poprawność, o której decyduje rdzeń, aby wszystkie interfejsy były zgodne.

Wartości domyślne i nadpisania zasad znajdują się w `config/seo.php`. Niektóre możliwości wymagają zależności środowiska uruchomieniowego, w tym segmentacji słów ICU i zainstalowanych fontów; przetłumaczoną treść musi dostarczać aplikacja.

## Język treści i język interfejsu {#content-locale-and-interface-locale}

Core 3.17, Filament 1.11 i Pro 2.36 przekazują wybrane ustawienia regionalne treści do metadanych, hooków obliczających wartości, URL-i podglądu, słów kluczowych listy kontrolnej i żądań AI. Angielski panel może edytować włoski i japoński bez zmiany etykiet.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

Te odczyty wybierają rekord metadanych danego wariantu językowego i wykonują hooki modelu, takie jak `getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()` i `getSEOSchema()`, w tymczasowym zakresie ustawień regionalnych. Model wywołującego i ustawienia regionalne aplikacji są zachowywane, także gdy hook zgłosi wyjątek. Modele implementujące `setLocale()` i `getTranslatableAttributes()` Spatie otrzymują również odizolowane ustawienia regionalne instancji. Hooki nadal muszą zwracać przetłumaczoną treść; Rankbeam nie tłumaczy automatycznie zwykłych atrybutów bazy danych.

Pro akceptuje jawne `locale:` w metodach AI opartych na modelach i w masowym uzupełnianiu. Bez niego nadpisana wartość domyślna `seoData()` modelu tłumaczenia określa język treści, a w razie jej braku używane są ustawienia aplikacji. Akcje Filament otrzymują ustawienia regionalne własnego pola, także w edytorze jednego języka i trybie podążania za przełącznikiem. We własnym zadaniu kolejki serializuj wybrane ustawienia regionalne i przekazuj je jawnie przy wykonaniu. Nie polegaj na bieżących ustawieniach procesu roboczego.

Dla własnych synchronicznych czytników treści `ModelLocale::run($model, $locale, $callback)` przekazuje odizolowany model do funkcji zwrotnej i przywraca ustawienia regionalne aplikacji w `finally`. Zakończ wszystkie odczyty zależne od języka wewnątrz funkcji zwrotnej; zwrócenie leniwego iteratora lub domknięcia nie przedłuża zakresu.

## Limity tytułu i opisu według systemu pisma {#title-and-description-budgets-per-script}

Rankbeam stosuje redakcyjne limity 60/160 grafemów dla łacińskich tytułów/opisów i 30/80 dla CJK. To konfigurowalne przybliżenia, a nie pomiary pikseli ani gwarancja, że wyszukiwarka pokaże całą wartość. Google nie określa stałego limitu znaków dla [linków tytułowych](https://developers.google.com/search/docs/appearance/title-link) ani [metaopisów](https://developers.google.com/search/docs/appearance/snippet); wyświetlany tekst może zostać skrócony do szerokości urządzenia.

`Rankbeam\Seo\I18n\LengthPolicy` ustala limit dla otrzymanego tekstu:

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

Korzystają z niego ostrzeżenia edytora (`SEOWarningEvaluator`), bezpłatny `seo:audit`, skracanie obliczanego opisu, skan Pro i liczniki Filament, więc używają tych samych zasad. Ostrzeżenia oceniają rozstrzygnięte wartości wraz z sufiksem tytułu, podczas gdy edytor może pokazywać także niezapisany tekst. Długości liczą **klastry grafemów**, a nie bajty ani punkty kodowe. Granice klastrów wynikają z zainstalowanej implementacji Unicode; nie są licznikiem sylab ani pomiarem pikseli wyniku wyszukiwania.

Wiersze znajdują się w `seo.length_policy`, z kluczami grup pisma (`latin`, `cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew`, `devanagari`) i `default` dla każdej niewymienionej grupy. Wiersz może ustawiać tylko część kluczy i dziedziczyć pozostałe:

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Domyślnie różni się tylko `cjk`. Zaktualizowana instalacja ze starszą opublikowaną konfiguracją otrzymuje wbudowany wiersz `cjk` bez żadnych zmian.

::: tip Tytuły mieszane
Wykrywanie opiera się na ważonej liczbie liter: glif CJK liczy się podwójnie, więc „Laravel SEO の完全ガイド” jest rozstrzygane jako CJK, a „Laravel SEO for the 東京 developer” pozostaje łacińskie. Wartość bez jakichkolwiek liter (rok, cena) przyjmuje system pisma ustawień regionalnych strony.
:::

Stałe `SEOWarningEvaluator::TITLE_MAX_LENGTH` / `DESCRIPTION_MAX_LENGTH` nadal istnieją jako łacińskie wartości domyślne dla kodu, który je odczytuje.

## Skracanie bez rozdzielania grafemów, z uwzględnieniem pisma {#grapheme-safe-script-aware-truncation}

Obliczany opis (`seo.computed.description_max_length`, limit łaciński) jest skalowany zgodnie z zasadami — opis CJK otrzymuje połowę — i skracany przez `Rankbeam\Seo\I18n\Truncator`:

- tekst ze spacjami między słowami zachowuje dotychczasową regułę: ostatnia granica słowa mieszcząca się w limicie (jeśli wypada co najmniej na 60% limitu), bez wielokropka, z usuniętą końcową interpunkcją — dla tekstu łacińskiego wynik jest bajtowo identyczny z wcześniejszym;
- Han, Kana i tajski nie mają spacji między słowami, więc preferowane jest cięcie na ostatnim znaku końca zdania lub członu (。！？、，…) wewnątrz limitu, następnie na spacji, jeśli tekst ją zawiera (koreański), a ostatecznie — dokładnie na granicy limitu;
- cięcie odbywa się na klastrach grafemów, więc nigdy nie może wypaść wewnątrz sekwencji łączonej — tajski znak samogłoski lub modyfikator emoji nigdy nie zostaje oddzielony od podstawy.

## Wielkość liter z uwzględnieniem ustawień regionalnych {#locale-aware-casing}

`mb_strtolower()` nie uwzględnia ustawień regionalnych. `Rankbeam\Seo\I18n\CaseFolder` je uwzględnia:

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` jest formą do wyświetlania; `fold()`, `equals()`, `contains()` i `containsWord()` służą do porównań. Rdzeń używa tego do pomijania sufiksu tytułu z uwzględnieniem marki (`seo.title_suffix_skip_when_contains`), więc turecka marka jest dopasowywana w obu formach `i`, a marka ze znakami diakrytycznymi otrzymuje rzeczywistą granicę słowa; kontrole słów kluczowych Pro opierają się na tym samym helperze.

Ujednolicanie wielkości liter zachowuje znaki diakrytyczne. Nie sprawia, że każda pisownia ze znakami i bez nich jest równoważna. Stemmer właściwy dla języka może stosować własne redukcje; to oddzielny mechanizm od `CaseFolder` i dopasowywania tożsamościowego.

## hreflang {#hreflang}

Google odczytuje `language[-Script][-REGION]` — dwuliterowy język ISO 639-1, opcjonalny system pisma ISO 15924 i opcjonalny region ISO 3166-1 alpha-2 — oraz `x-default`. Regiony liczbowe takie jak `es-419` są poprawne w BCP47, ale wykraczają poza [kontrakt hreflang Google](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes). Aplikacje Laravel często przekazują zamiast tego swoje *ustawienia regionalne* (`it_IT`, `pt_br`), a podkreślenie nie jest tam poprawne. Trzy zasady w `seo.hreflang` są stosowane do listy `getSEOAlternates()` modelu **zanim** stanie się tagami `<link rel="alternate">`, wpisami `<xhtml:link>` mapy witryny, linkami `llms.txt` i wejściem audytu. Wszystkie używają tych samych zasad; `llms.txt` pomija samą stronę i `x-default` w linkach „Dostępne także w”:

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`** (domyślnie włączone) dostosowuje separatory, wielkość liter i zarejestrowane aliasy (`iw_IL` → `he-IL`). Zachowuje powtórzone separatory (`en__US` → `en--US`), aby audyt mógł je oznaczyć. Wyłącz je, aby zachować dostarczone bajty.
- **`include_self`** dodaje własne ustawienia regionalne i kanoniczny URL strony, gdy ani jej URL, ani kod nie znajdują się na liście. Google wymaga, aby każda wersja językowa wymieniała samą siebie; włącz to, gdy hook zwraca tylko *inne* języki.
- **`x_default`** wskazuje język, którego alternatywa jest powielana jako `x-default`, jeśli lista go nie zawiera.

Pusta lista pozostaje pusta — strona bez tłumaczeń nie otrzymuje ani odwołania do siebie, ani `x-default`.

Bezpłatny audyt dodaje trzy kontrole listy po zastosowaniu zasad:

| Kod | Waga | Znaczenie |
|---|---|---|
| `hreflang_invalid_code` | ostrzeżenie | Kod spoza kontraktu Google (`en-UK`, `jp`, `english`, `es-419`, `fil`). |
| `hreflang_duplicate_code` | uwaga | Ten sam kod wymieniony dwa razy. |
| `hreflang_missing_self` | ostrzeżenie | Własnego URL-a strony nie ma na jej liście. |

Wzajemność (czy druga strona odsyła z powrotem?) wymaga skanowania witryny; to zadanie skanu Pro — jego opcjonalne `check_hreflang_reciprocity` pobiera każdą alternatywę przez SsrfGuard i zgłasza `hreflang_not_reciprocal`, gdy druga strona nie deklaruje URL-a tej strony **z jej kodem języka** (Pro 2.38+, zobacz [problemy skanu](/pl/pro/scan-issues#network-codes)). Helper jest publiczny, jeśli go potrzebujesz:

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### Trzy kontrakty kodów języka {#three-language-code-contracts}

Core **3.18+** oddziela ustawienie aplikacji od wartości udostępnianej w HTML:

| Wejście | Normalizacja aplikacji | Język HTML | Hreflang Google |
|---|---|---|---|
| `pt_BR` | `pt-BR` | Nieprawidłowe w tej postaci | Nieprawidłowe w tej postaci |
| `de-CH-1901` | Zachowane | Poprawny zarejestrowany wariant | Nieobsługiwany wariant |
| `es-419` | Zachowane | Poprawny region liczbowy | Nieobsługiwany region liczbowy |
| `zh-Hant-TW` | Zachowane | Poprawne | Poprawne |
| `fil` | Zachowane | Poprawny zarejestrowany język | Poza kontraktem dwuliterowym |
| `iw_IL` | `he-IL` | Podkreślenie jest nieprawidłowe; `iw-IL` pozostaje poprawnym przestarzałym tagiem | Użyj znormalizowanego `he-IL` |
| `en__US` | `en--US` | Nieprawidłowe | Nieprawidłowe |
| `x-default` | Zachowane | Odrzucone przez zasady języka treści Rankbeam | Poprawny znacznik wersji domyślnej |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Migracja z rdzenia 3.17 i wcześniejszych:** `Hreflang::isValid()` i `parse()` ściśle walidują udostępniane kody. Jeśli wywołujący podaje ustawienia regionalne Laravel, najpierw wywołaj `fromLocale()`. Jeśli sprawdza atrybut `lang` HTML, użyj `LanguageTag::isValidHtml()` bez przycinania ani normalizacji. Przestarzałe zarejestrowane tagi pozostają poprawne w HTML; normalizacja stosuje tylko jawne preferowane aliasy IANA i nie zgaduje, że `en-UK` oznacza `en-GB`. Błędne wpisy nie są odfiltrowywane, zanim audyt będzie mógł je zgłosić.

Walidator zawiera dane z rejestru IANA z dnia **2026-08-08**, z hashami źródeł i odtwarzalnym generatorem. Sprawdza strukturę RFC 5646, zarejestrowane podtagi, prefiksy extlang i zduplikowane warianty/rozszerzenia. Obsługuje historyczne tagi zachowane dla zgodności i zakresy użytku prywatnego. Zalecenia dotyczące prefiksów wariantów nie są obowiązkowymi regułami poprawności; sprawdzane są przestrzenie nazw rozszerzeń i struktura, natomiast semantyka opcji CLDR i znaczenie użytku prywatnego pozostają poza API. Nie wymaga ICU ani pobierania podczas działania. Zobacz [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) i [definicję języka HTML](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes).

Pro **2.38+** raportuje nieobecne lub puste `lang` jako nieznane/brakujące, a błędne udostępniane bajty powodują `html_lang_invalid`. Kontrole niezgodności pisma używają rzeczywistego podtagu pisma lub domyślnej wartości zarejestrowanej w IANA; dane prywatne/rozszerzeń i nieznane języki nie implikują pisma łacińskiego. Nieobsługiwane grupy pisma pozostają bez oceny. Te kontrole nie są pełnym detektorem języka.

Wzajemność korzysta z poprawnych kodów odwołań strony źródłowej do siebie lub z jej poprawnego języka HTML zgodnego z Google, gdy kodów odwołania do siebie brakuje. Zwrotny URL pod innym kodem języka nie przechodzi kontroli. Gdy nie można ustalić kodu źródłowego, wynik pozostaje `hreflang_target_unverified`. Zduplikowane docelowe URL-e są pobierane raz w ramach istniejących limitów alternatyw/treści odpowiedzi; ochrona SSRF, odmowa przekierowań i obsługa niezweryfikowanych niepowodzeń pozostają w mocy.

## `inLanguage` w grafie danych strukturalnych {#inlanguage-in-the-schema-graph}

Węzeł `WebPage` otrzymuje `inLanguage` z rozstrzygniętych ustawień regionalnych strony (`it_IT` → `it-IT`), a `ArticleSchema::fromModel()` pobiera je z zapisanych ustawień regionalnych `seo_meta`. Węzeł `WebSite` pobiera języki z konfiguracji:

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Wyszukiwarki regionalne {#regional-search-engines}

Katalog robotów stojący za `seo:robots-txt` zawiera teraz klasyczne roboty wyszukiwarek istotne poza światem Google/Bing — Yandex, Baidu, Naver (`Yeti`), Seznam, Sogou, 360, Cốc Cốc i DuckDuckGo — oznaczone zastosowaniem `search_engine`, domyślnie dozwolone. Biorą udział w zasadach i nadpisaniach dla poszczególnych botów, więc sklep nieobsługujący Chin może ograniczyć zużycie pasma przez dwa roboty:

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` i `match()` pozostają wyłącznie dla AI (dziennik botów AI Pro i każda liczba „N robotów AI” nie zmieniają się); poproś o wyszukiwarki przez `searchEngines()`, `all(true)` lub `match($ua, true)`. Zobacz [Kontrola robotów AI](/pl/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
Obsługa robota i tagu weryfikacyjnego nie gwarantuje odkrycia, indeksowania ani pozycji w Baidu.
:::

## Weryfikacja witryny {#site-verification}

Tokeny własności są renderowane jako jeden metatag na każdą skonfigurowaną wyszukiwarkę, na każdej stronie (Google akceptuje tag w dowolnym miejscu; Yandex, Baidu i Naver szukają go na stronie głównej, która jest objęta tym zakresem). Nic nie jest generowane dla pustego klucza:

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

Wartość może być listą tokenów (Google wydaje po jednym na właściciela usługi).

## Obrazy OG w każdym systemie pisma {#og-images-in-every-script}

Dołączony font kart obsługuje pismo łacińskie, cyrylicę i grekę. Każde inne pismo zależy od fontu zainstalowanego na maszynie uruchamiającej `seo:og-images` — żadne inne nie są dołączone, ponieważ font CJK ma ponad 16 MB. Szablony zawierają teraz stos fontów zastępczych dla poszczególnych systemów pisma (`seo.og_image.font_stack`, z rodziną Noto CJK języka strony przesuniętą na początek, aby znaki Han przyjmowały właściwe krajowe formy glifów), a polecenie ostrzega raz na system pisma, gdy host nie ma fontu dla tytułu, który ma wyrenderować:

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Na Debian/Ubuntu: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. Szczegóły w [Generowanych obrazach OG](/pl/guide/og-image#fonts-and-non-latin-scripts).

## `llms.txt` w kilku językach {#llms-txt-in-several-languages}

Po włączeniu `seo.llms_txt.alternates` strona istniejąca w innych językach kończy swój punkt listy przez `Also in: [it](…), [de](…)` — alternatywy po zastosowaniu zasad, bez `x-default` i samej strony. Domyślnie wyłączone.

## URL-e Unicode {#unicode-urls}

Rankbeam nigdy nie tworzy slugów ani nie przepisuje Twoich URL-i, więc ścieżka taka jak `/città/` lub `/検索` pozostaje niezmieniona w każdym wygenerowanym zasobie. Kanoniczne URL-e z hostem IDN (`https://münchen.example/`) albo ścieżką Unicode lub kodowaną procentowo są akceptowane przez audyt (`Rankbeam\Seo\I18n\Url::isValid()` zastępuje obsługujące tylko ASCII `FILTER_VALIDATE_URL` PHP). Zachowuj jedną postać każdego URL-a — surowy Unicode *albo* kodowanie procentowe, nie oba — aby adres kanoniczny, hreflang i wpisy mapy witryny były zgodne bajt po bajcie.

## Które języki są obsługiwane i co to oznacza {#which-languages-are-supported-and-what-that-means}

Pakiety zawierają teksty i wybór mechanizmów analizy dla siedemnastu poniższych ustawień regionalnych. Tabela opisuje zakres techniczny, a nie zatwierdzenie redakcyjne przez rodzimego użytkownika języka ani gwarantowane renderowanie na nieskonfigurowanym hoście. Analiza słów japońskich/chińskich wymaga działającego ICU; zależne od niej kontrole słów są pomijane, gdy jest niedostępne. Renderowanie pism innych niż łacińskie wymaga odpowiednich fontów. Wybór mechanizmów jest sprawdzany testami w obu repozytoriach: `tests/Feature/I18n/SupportedLanguagesTest.php` w rdzeniu ustala listę ustawień regionalnych, kody hreflang i limity, a `tests/Feature/OnPage/LanguageSupportMatrixTest.php` w Pro — silniki analizy, więc wiersz, który przestaje być prawdziwy, powoduje niepowodzenie CI.

| Język | Ustawienia regionalne | Tytuł / opis | Liczenie słów | Dopasowanie słów kluczowych | Czytelność |
|---|---|---|---|---|---|
| Angielski | `en` | 60 / 160 | spacje | Snowball | Flesch Reading Ease |
| Włoski | `it` | 60 / 160 | spacje | Snowball | Gulpease |
| Niemiecki | `de` | 60 / 160 | spacje | Snowball | Wiener Sachtextformel |
| Francuski | `fr` | 60 / 160 | spacje | Snowball | Kandel-Moles |
| Hiszpański | `es` | 60 / 160 | spacje | Snowball | Fernández-Huerta |
| Portugalski (Brazylia) | `pt_BR` | 60 / 160 | spacje | Snowball | Martins |
| Niderlandzki | `nl` | 60 / 160 | spacje | Snowball | Flesch-Douma |
| Turecki | `tr` | 60 / 160 | spacje | Snowball | Ateşman |
| Rosyjski | `ru` | 60 / 160 | spacje | Snowball | Oborneva |
| Polski | `pl` | 60 / 160 | spacje | Snowball | Pisarek |
| Japoński | `ja` | 30 / 80 | słownik ICU | dokładne, z ujednoliceniem wielkości liter | heurystyka, **bez oceny liczbowej** |
| Chiński (uproszczony) | `zh_CN` | 30 / 80 | słownik ICU | dokładne, z ujednoliceniem wielkości liter | heurystyka, **bez oceny liczbowej** |
| Chiński (tradycyjny) | `zh_TW` | 30 / 80 | słownik ICU | dokładne, z ujednoliceniem wielkości liter | heurystyka, **bez oceny liczbowej** |
| Koreański | `ko` | 30 / 80 | spacje | dokładne, z ujednoliceniem wielkości liter | heurystyka, **bez oceny liczbowej** |
| Grecki | `el` | 60 / 160 | spacje | Snowball | LIX |
| Ukraiński | `uk` | 60 / 160 | spacje | dokładne, z ujednoliceniem wielkości liter | LIX |
| Czeski | `cs` | 60 / 160 | spacje | Snowball | LIX |

Trzy kwestie, które tabela celowo przedstawia uczciwie:

- **Snowball jest dołączony od Pro 2.37.** Dwanaście języków używa przypiętych algorytmów 3.1.1 niezależnie od opcjonalnych pakietów. Ukraiński i CJK używają dopasowywania tożsamościowego; pakiet nie wymyśla dla nich reguł sufiksów. Dopasowanie tożsamościowe może pomijać formy fleksyjne, a stemming może utożsamiać różne słowa. Zobacz [ustawienia silników i uwagi o migracji](/pl/pro/on-page-checklist#upgrading-from-pro-2-36).
- **„Heurystyka, bez oceny liczbowej” i „LIX” to nie to samo.** Japoński, chiński i koreański używają w tym pakiecie metody bez oceny liczbowej: lista kontrolna raportuje *poziom* na podstawie długości zdań i udziału kanji, z oceną `null`, i pozostaje zaleceniem niezależnie od konfiguracji. Grecki, ukraiński i czeski używają LIX, ponieważ nie zaimplementowano tu dedykowanego wzoru. LIX nie wymaga sylab, ale jego progi nie są skalibrowane dla każdego języka. Dane wejściowe wszystkich wzorów zawierają oszacowania; zobacz [kontrakt statystyk](/pl/pro/on-page-checklist#text-statistics-and-api-limits).
- **Tłumaczenia interfejsu pakietów są wersjami wstępnymi**, chyba że `TRANSLATING.md` podaje, że sprawdził je rodzimy użytkownik języka. Włoski został sprawdzony; pozostałe czekają na recenzenta, a przegląd tłumaczenia to najprostszy sposób, by Twój wkład w obsługę danego języka został odnotowany w pakiecie.

Niewymienione ustawienia regionalne mogą korzystać z angielskich tekstów zastępczych, limitów pisma/domyślnych, tożsamościowego dopasowywania słów kluczowych oraz LIX lub heurystycznej oceny czytelności. Takie wartości zastępcze nie oznaczają zweryfikowanej obsługi języka. Blok `analysis` listy kontrolnej wskazuje pismo, segmenter, stemmer i metodę oceny czytelności; sprawdzaj ich dostępność i pominięte oceny, a nie tylko etykiety.

### Docieranie do wyszukiwarek ważnych lokalnie {#reaching-the-search-engines-that-matter-locally}

Wdrożenie języka nie dotyczy tylko tekstu. Katalog robotów zawiera Yandex, Baidu, Yeti Naver, Seznam, Sogou, 360 i Cốc Cốc obok Google i Bing, a `seo.verification` renderuje ich tagi weryfikacji witryny — Naver dla witryny koreańskiej, Seznam dla czeskiej, Yandex dla ukraińskiej lub rosyjskiej. Zobacz [Wyszukiwarki regionalne](#regional-search-engines) i [Weryfikacja witryny](#site-verification).

## Co dodają pozostałe pakiety {#what-the-other-packages-add}

- **laravel-seo-filament** odczytuje te same zasady długości dla liczników na żywo i podglądu SERP oraz (1.9) edytuje [jeden rekord `seo_meta` na język](/pl/guide/filament#several-languages) — jedna karta na wariant językowy z własnymi licznikami, podglądem i wskaźnikami wartości zastępczych albo podążanie za przełącznikiem ustawień regionalnych wtyczki tłumaczeń.
- **laravel-seo-pro** odczytuje je dla kontroli `title_length` / `description_length` skanu i promptów asystenta AI oraz (2.34) analizuje stronę w jej własnym języku: segmentacja słów ICU dla chińskiego, japońskiego i tajskiego, stemming Snowball, dopasowanie słów kluczowych z uwzględnieniem ustawień regionalnych przez ten `CaseFolder`, opublikowane wzory czytelności z szacowanymi danymi wejściowymi dla dziesięciu języków, oznaczone heurystyki dla CJK i LIX (opisany jako taki) dla greckiego, ukraińskiego i czeskiego, słowa pomijane dla szesnastu języków, kontrole skanu `html lang` i wzajemności hreflang, prompty AI wskazujące język strony oraz raport renderowany przez Chrome dla pism, których dompdf nie potrafi narysować. Zobacz [listę kontrolną SEO strony](/pl/pro/on-page-checklist#keyword-matching), [problemy skanu](/pl/pro/scan-issues), [asystenta AI](/pl/pro/ai-assist#output-language) i [raporty](/pl/pro/reports#reports-in-every-script-browsershot-renderer).
