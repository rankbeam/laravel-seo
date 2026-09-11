---
description: "Lista kontrolna treści strony z wynikami pass/warn/fail: wybierz główne słowo kluczowe i sprawdź tytuł, URL, pierwszy akapit, metadane, długość, obrazy i czytelność."
---

# Lista kontrolna treści strony — słowa kluczowe i pass/warn/fail {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

Lista kontrolna treści strony zapewnia bieżącą pomoc redakcyjną znaną użytkownikom RankMath lub Yoast. Wybierasz główne słowo kluczowe i otrzymujesz listę kontroli z sygnalizacją kolorami, które odpowiadają na pytanie, czy strona jest pod nie zoptymalizowana. Sprawdzane są słowo kluczowe w tytule, URL-u, pierwszym akapicie i metaopisie, a także długość, obrazy, linki wewnętrzne oraz **czytelność**.

Lista działa **podczas żądania**, bez kolejki i sieci, na podstawie modelu, [resolvera](/pl/concepts/resolver-precedence) i treści samej strony. Celowo **nie jest oceną liczbową**.

::: tip Lista kontrolna ≠ ocena
Lista daje wyłącznie **pass / warn / fail** i jest całkowicie osobna od [oceny SEO Pro](/pl/pro/scoring). Nie dzieli kodów z zasadami oceny i nigdy na nią nie wpływa. Wskazówki redakcyjne pozostają oddzielone od zasad punktacji. W szczególności gęstość słowa kluczowego i czytelność mają charakter **doradczy** (poniżej).
:::

## Co sprawdza {#what-it-checks}

| Kontrola | Grupa | Co sprawdza |
|---|---|---|
| `keyword_in_title` | keyword | Główne słowo kluczowe występuje w tytule SEO. |
| `keyword_in_description` | keyword | Główne słowo kluczowe występuje w metaopisie. |
| `keyword_in_url` | keyword | Główne słowo kluczowe występuje w slugu URL-a. |
| `keyword_in_first_paragraph` | keyword | Główne słowo kluczowe występuje w pierwszym akapicie. |
| `keyword_density` | keyword | **Doradcza.** Naturalne nasycenie słowem kluczowym, bez wartości docelowej (poniżej). |
| `title_length` | meta | Tytuł mieści się w tym samym przedziale co w edytorze i skanowaniu: 30–60 dla tekstu łacińskiego, około 15–30 dla CJK, według [zasad długości](/pl/guide/multilingual#title-and-description-budgets-per-script) rdzenia (Pro 2.33). |
| `description_length` | meta | Opis mieści się w tym samym przedziale: 70–160 dla tekstu łacińskiego, około 35–80 dla CJK. |
| `content_length` | content | Wystarczająca ilość treści (przedziały liczby słów z konfiguracji). |
| `readability` | content | **Doradcza.** Szacowany poziom czytelności według wybranego wzoru (dziesięć języków), zastępczego LIX lub oznaczonej, niepunktowanej heurystyki dla japońskiego, chińskiego i koreańskiego. |
| `has_image` | media | Treść zawiera co najmniej jeden obraz. |
| `internal_links` | links | Treść zawiera linki do powiązanych stron wewnętrznych. |

Kontrole słów kluczowych są **pomijane** (ani pass, ani fail), gdy nie ustawiono głównego słowa kluczowego. Lista prosi wtedy o jego dodanie. Użyj [pola głównego słowa kluczowego](/pl/guide/filament) lub `saveSEO(['focus_keywords' => …])`.

### Dopasowywanie słów kluczowych {#keyword-matching}

Słowo kluczowe i tekst są porównywane po **ujednoliceniu wielkości liter i stemmingu**, więc „espresso grinder” nadal pasuje do „espresso grinders”. Przy odpowiednim locale analizy tureckie „İstanbul” pasuje do „istanbul”, greckie „ΟΔΟΣ” do „οδος”, a niemieckie „Straße” do „STRASSE” (`CaseFolder` rdzenia). Przekaż locale analizy: `SeoPro::checklistFor($post, 'it')` lub `--locale=it`.

Od Pro 2.36.1 słowa kluczowe, synonimy i tekst pól używają tego samego tokenizera przed stemmingiem. Dopasowanie wymaga kolejnych **całych tokenów**: `cat` nie pasuje do `education`, a japońskie frazy używają tych samych granic słów ICU co treść. Apostrofy i łączniki rozdzielają tokeny, więc `meta-tag` pasuje do `meta tag`, a apostrofy proste i typograficzne zachowują się tak samo. Znaki łączące pozostają przy swoich literach. Ujednolicanie wielkości liter zachowuje akcenty; stemmer danego języka może wykonywać dalsze redukcje.

Zliczanie wystąpień wybiera w każdej pozycji najdłuższe pasujące słowo kluczowe lub synonim i liczy ten fragment raz. Powielone synonimy i nakładające się krótsze warianty nie zawyżają gęstości. Na przykład słowo kluczowe `seo` z synonimem `seo tools` występuje dwa razy w `seo tools seo`. ICU nadal jest potrzebne do słownikowych granic słów w systemach pisma bez spacji; zastępcze wyrażenie regularne ich nie zapewni.

Od Pro 2.37 stemming używa **dołączonego podzbioru Snowball 3.1.1**. Nie wymaga dodatkowego pakietu Composer i niczego nie pobiera podczas działania. PHP 8.2 pozostaje obsługiwane.

| Silnik | Kiedy | Języki |
|---|---|---|
| `snowball` | Domyślnie; istniejące ustawienia `auto` wybierają ten sam dołączony silnik | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | Jawne `seo-pro.checklist.analysis.stemmer = builtin` | Tylko angielski, ze starszym lekkim stemmerem fleksyjnym; inne języki używają dopasowania bez redukcji |
| `identity` | Nieobsługiwany język lub jawny tryb `none` | Ukraiński, japoński, chiński, koreański, tajski i inne języki poza dołączonym podzbiorem |

Obie strony porównania używają tego samego silnika. Stemming jest algorytmem redukcji przyrostków, a nie słownikiem synonimów ani gwarancją równoważności językowej. Na przykład algorytm grecki może dopasować formy z akcentem i bez niego, które dopasowanie bez redukcji rozróżnia. Granice całych tokenów nadal uniemożliwiają dopasowanie `cat` do `education`.

#### Aktualizacja z Pro 2.36 {#upgrading-from-pro-2-36}

Istniejąca konfiguracja `auto` używa teraz zawsze dołączonych algorytmów, niezależnie od instalacji `wamania/php-stemmer`. Po aktualizacji sprawdź ponownie wskazówki redakcyjne: nowe algorytmy mogą zmienić dopasowania, a turecki, grecki, polski i czeski mają teraz stemming. Dodatkowe algorytmy katalońskiego, duńskiego, fińskiego, norweskiego, rumuńskiego i szwedzkiego z opcjonalnej nakładki nie należą do tego podzbioru i obecnie używają dopasowania bez redukcji.

Ustaw `SEO_PRO_CHECKLIST_STEMMER=builtin`, aby wrócić do poprzedniego rozwiązania zastępczego tylko dla angielskiego, lub `none`, aby we wszystkich językach porównywać tekst bez redukcji, po ujednoliceniu wielkości liter. Po zmianie ustawienia odbuduj pamięć podręczną konfiguracji. Te opcje nie odtwarzają wielojęzycznych algorytmów dawnej opcjonalnej nakładki; zachowanie dokładnie tych wyników wymaga pozostania przy poprzednim wydaniu Pro. Zapisane metadane SEO nie są przepisywane.

Dołączony adapter przechodzi 600 395 przypiętych oficjalnych par słownictwo/wynik w PHP 8.2, 8.3 i 8.4. Potwierdza to zgodność algorytmu, a nie redakcyjną akceptację rodzimego użytkownika języka. Pakiet zawiera hashe źródeł, adaptację składniową dla PHP 8.2 i licencje projektów źródłowych. Zobacz `THIRD-PARTY-NOTICES.md` w dystrybucji źródłowej.

### Segmentacja słów {#word-segmentation}

Liczba słów, gęstość słów kluczowych i statystyki czytelności wymagają podziału na słowa. Dla systemów pisma ze spacjami wyrażenie regularne stosuje stabilne granice tokenów literowo-cyfrowych. Chiński, japoński i tajski wymagają segmentacji słownikowej, więc wyrażenie regularne może uznać akapit za jedno „słowo”. Gdy załadowano **ext-intl**, tokenizer przekazuje takie fragmenty iteratorowi granic ICU opartemu na słowniku (`IntlBreakIterator::createWordInstance`), który dzieli 東京タワーは東京のランドマークです na słowa. Jeśli ICU brakuje, jest wyłączone lub nie może się zainicjalizować, Pro pomija zależne kontrole długości treści, czytelności i słów kluczowych, podając komunikat o instalacji/konfiguracji. Nie zamienia niewiarygodnego zliczenia w niepowodzenie. Niezależne kontrole, w tym długość tytułu i dopasowanie w systemach pisma ze spacjami, nadal działają. `seo-pro.checklist.analysis.segmenter = regex` wymusza ten sam stan niedostępności dla tekstu wymagającego segmentacji słownikowej.

Blok `analysis` zawiera `word_count_status` (`available` lub `unavailable`) i `segmentation_reason` (`null`, `missing_intl`, `disabled` lub `initialization_failed`). Tokenizer niższego poziomu zachowuje tokeny zastępcze dla zgodności. Sprawdź ten stan, zanim uznasz je za słowa.

Skanowanie wyrenderowanej strony emituje niepunktowaną uwagę `word_segmentation_unavailable` zamiast orzeczenia o zbyt małej ilości treści. Wcześniej potwierdzony problem ubogiej treści pozostaje otwarty, dopóki nie można go ponownie sprawdzić. Takie niepełne skanowanie nie odświeża oceny strony: istniejąca ocena zachowuje pierwotne `scored_at`, a przy pierwszym skanowaniu oceny nie ma, dopóki segmentacja nie zadziała. Zainstaluj PHP `ext-intl`, włącz segmenter `auto` i przeskanuj ponownie, aby wznowić te kontrole.

### Jakie silniki analizowały stronę {#which-engines-analysed-the-page}

Każda lista kontrolna zawiera blok `analysis`: dominujący system pisma tekstu, tokenizer (`intl` / `regex`), stemmer (`snowball` / `builtin` / `identity`) i metodę czytelności (`formula` / `heuristic` / `lix`). Jest on dostępny w `toArray()` / `--json`, jako wiersz stopki okna Filament i ostatni wiersz `seo-pro:checklist`:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

Stopka wskazuje rzeczywiście użyty silnik, w tym segmentację wyrażeniem regularnym przy braku ext-intl i dopasowanie bez redukcji przy wyłączonym stemmingu.

### Gęstość słowa kluczowego ma charakter doradczy {#keyword-density-is-advisory}

Lista nie definiuje idealnej gęstości słowa kluczowego dla pozycji w wyszukiwarce. Ta kontrola jest **doradcza**: pokazuje liczbę wystąpień informacyjnie, nigdy nie daje fail i **nigdy nie decyduje o ogólnym stanie strony**. Sprawdź, czy powtórzenia brzmią naturalnie, zamiast dążyć do określonego procentu.

### Czytelność ma charakter doradczy {#readability-is-advisory}

Lista szacuje czytelność metodą wybraną dla locale analizy. Obecnie zaimplementowano następujące wzory i rozwiązania zastępcze:

| Locale | Wzór | Źródło |
|---|---|---|
| Angielski (`en`) | Flesch Reading Ease | Flesch 1948 |
| Włoski (`it`) | Gulpease Index | Lucisano & Piemontese 1988 |
| Hiszpański (`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| Francuski (`fr`) | Kandel-Moles | Kandel & Moles 1958 |
| Niemiecki (`de`) | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| Portugalski (`pt`, `pt_BR`) | Flesch w adaptacji do portugalskiego brazylijskiego | Martins i in. 1996 |
| Niderlandzki (`nl`) | Flesch-Douma | Douma 1960 |
| Rosyjski (`ru`) | Adaptacja Flescha autorstwa Obornewej | Оборнева 2006 |
| Turecki (`tr`) | Ateşman | Ateşman 1997 |
| Polski (`pl`) | Pisarek (znormalizowany wskaźnik lat nauki) | Pisarek 1969 |
| Japoński, chiński, koreański (`ja`, `zh`, `ko`) | **heurystyka bez oceny liczbowej** — poniżej | — |
| Grecki, ukraiński, czeski (`el`, `uk`, `cs`) | LIX jako rozwiązanie zastępcze, ponieważ nie zaimplementowano tu osobnego wzoru; nieskalibrowany dla tych języków | Björnsson 1968 |
| Pozostałe | LIX (Läsbarhetsindex) — nieskalibrowane rozwiązanie zastępcze | Björnsson 1968 |

Wyświetlana **ocena 0–100 (wyżej = łatwiej)** jest konwencją pakietu. Wyniki rodziny Flescha i Gulpease są ograniczane do tego zakresu, a wskaźniki Wiener, Pisarka i LIX są na niego przeliczane. Równe wyniki w różnych językach **nie oznaczają** równej trudności czytania. Wzory współczynników pochodzą z opublikowanych prac; szacunki tokenów, zdań i sylab Rankbeam nie zostały zwalidowane jako kompletne narzędzie pomiarowe. Nie przewidują zrozumienia tekstu ani pozycji w wyszukiwarce.

Od Pro 2.37.1 sąsiednie samogłoski w tureckim i rosyjskim liczą się jako osobne sylaby (`saat`: 2; `поэт`: 2). Pozostałe estymatory sylab nadal mają ograniczenia: grupowanie samogłosek pomija część rozziewów i niemych samogłosek. Angielski ma niewielką mapę wyjątków, która nie jest słownikiem wymowy. Na przykład hiszpańskie `país` i francuskie `monde` mogą zostać policzone błędnie. Nieznane słowa i nazwy własne sprawdzaj ręcznie.

#### Statystyki tekstu i ograniczenia API {#text-statistics-and-api-limits}

Blokowe znaczniki HTML i elementy `br` rozdzielają tekst, a wyróżnienie wewnątrz wiersza pozostaje częścią słowa. Łamanie wierszy źródła w zwykłym HTML jest zwijane do spacji, natomiast zwykły tekst i `pre` zachowują granice wierszy. Treść script, style i noscript jest wykluczana. Ekstrakcja nie ocenia widoczności CSS ani wyrenderowanej strony. Encje są dekodowane raz. W statystykach wzorów słowami są ciągi liter/cyfr, nie sama interpunkcja. Łączniki i apostrofy rozdzielają słowa. Cyfry liczą się jako tokeny, ale bez szacowanych sylab. Litery są liczone w oryginalnym tekście, bez zmiany długości przez stemming lub niemieckie ujednolicenie wielkości liter `ß` → `ss`.

Szacowanie zdań dzieli tekst po końcowych `. ! ? 。 ！ ？` i granicach bloków/wierszy, uwzględnia końcowy fragment bez znaku zamykającego oraz chroni liczby dziesiętne i niewielką listę częstych skrótów (`Dr.`, `Prof.`, `e.g.` i podobne formy angielskie). Nagłówki i elementy list mogą więc liczyć się jako zdania. Inne skróty, cytaty, liczby, mieszane systemy pisma i tekst z niewielką ilością interpunkcji wymagają szczególnej ostrożności. Wybrane locale kieruje do metody; nie wykrywa, czy każde zdanie jest w tym języku.

`toArray()` bezpośredniego kalkulatora dodaje blok `assessment`:

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` rozróżnia `formula`, `lix`, `heuristic` i `unavailable` (`unspecified` dla ręcznie utworzonych wyników bez metadanych wzoru). Pusty tekst lub sama interpunkcja daje `insufficient` i false w `isValid()`. Dotychczasowe `score: 0` jest znacznikiem niedostępności, a nie oceną trudności. Istniejące etykiety poziomów szkolnych dla angielskiego i włoskiego są przybliżone; inne języki oraz wyniki heurystyk/LIX już ich nie otrzymują. `calculateFleschKincaid()` zachowuje publiczną nazwę metody dla zgodności, ale oblicza **Flesch Reading Ease**, a nie poziom Flesch-Kincaid.

Testy wzorów utrwalają niezależnie policzone dane wejściowe i oczekiwane wyniki arytmetyczne dla wszystkich dziesięciu nazwanych wzorów oraz LIX. Sprawdzają zachowanie obliczeń, a nie jakość redakcyjną ocenioną przez rodzimych użytkowników języka. Czytelność pozostaje oddzielona od oceny SEO Pro.

::: warning Japoński, chiński i koreański: oznaczona heurystyka, nigdy liczba
Rankbeam stosuje dla tych języków metodę bez punktacji. Kalkulator zwraca **poziom** na podstawie reguł orientacyjnych pakietu: średniej długości zdania w znakach (ja ≤ 40/60/80, zh ≤ 30/45/60) lub słowach (ko ≤ 12/18/25), a dla japońskiego także udziału kanji (powyżej około 45% podnosi poziom trudności pakietu o jeden stopień). Wynik jest oznaczony `heuristic: true` i ma **ocenę null**. Komunikat listy wskazuje „heurystykę”, a kontrola pozostaje **doradcza dla tych języków niezależnie od `readability.advisory`**. Reguła orientacyjna informuje, ale nigdy nie decyduje o ogólnym stanie listy. Zliczanie słów listy dla `ja`/`zh` wymaga działającej segmentacji ICU; przy jej braku kontrole są pomijane.
:::

Podobnie jak gęstość słowa kluczowego, czytelność jest **domyślnie doradcza**: informuje autora, ale **nie** decyduje o ogólnym stanie strony. To takie samo rozdzielenie jak między analizami Readability i SEO w Yoast. Jest **pomijana** poniżej minimalnej liczby słów; ubogą treścią zajmuje się kontrola `content_length`, nie czytelność. Włącz jej wiążący wpływ, jeśli trudna do czytania strona ma otrzymywać fail:

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## Odczytywanie listy kontrolnej {#reading-the-checklist}

### Bez panelu {#headless}

Pro 2.36 odczytuje rozstrzygnięte metadane, `getContentForSEO()` i główne słowa kluczowe w żądanym locale treści, a etykiety listy pozostają w języku operatora. Bez jawnego locale respektowana jest domyślna wartość `seoData()` modelu tłumaczeń. Akcja Filament korzysta z karty językowej pola lub przełącznika locale strony.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Każdy `CheckResult` zawiera `id`, `group`, `label`, `status`, `message`, opcjonalne `recommendation` i flagę `advisory`.

### Polecenie {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### W edytorze (Filament, opcjonalnie) {#in-the-editor-filament-optional}

Po zainstalowaniu [`rankbeam/laravel-seo-filament`](/pl/guide/filament) przy polu głównego słowa kluczowego pojawia się akcja **Lista kontrolna on-page**. Otwiera okno z tymi samymi kontrolami pass/warn/fail dla zapisanej treści rekordu. Pakiet Filament nigdy nie zależy od Pro. Akcja dołącza przez ten sam jednokierunkowy punkt rozszerzeń, którego używają propozycje AI, więc instalacje bez panelu pozostają bez zmian.

## Konfiguracja {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Własna kontrola {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Zarejestruj kontrolę, dodając jej klasę do `seo-pro.checklist.rules`. Kontrola **nie może** ponownie używać identyfikatora [kodu problemu skanowania](/pl/pro/scan-issues). Lista kontrolna ma osobną przestrzeń nazw bez punktacji.

## Jak odczytywana jest treść {#how-the-content-is-read}

`SeoPro::checklistFor($model)` analizuje:

- **Tytuł / opis** — wartości *rozstrzygnięte*, czyli wynikowe, te same, które mierzą liczniki edytora i skanowanie. Lista nigdy im więc nie przeczy.
- **Treść** — `$model->getContentForSEO()` (akcesor `HasSEO` rdzenia, domyślnie `content` / `body` / `text`). Nadpisz go w modelu, aby wskazywał rzeczywistą treść.
- **URL** — `$model->getUrlForSEO()`.
- **Główne słowa kluczowe** — zapisane `seo_meta.focus_keywords`.

To czysta analiza: żadna strona nie jest pobierana i nic nie jest zapisywane.
