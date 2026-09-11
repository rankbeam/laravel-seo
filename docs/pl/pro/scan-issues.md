---
description: "Stabilny rejestr kodów wszystkich problemów zgłaszanych przez skanowanie Pro. Każdy kod ma stałą ważność i pole, dzięki czemu panele oraz eksporty odczytują kody, a nie komunikaty."
---

# Problemy skanowania — rejestr kodów {#scan-issues-—-the-issue-code-registry}

Każdy problem zgłaszany przez skanowanie Pro ma **stabilny kod** z jednego rejestru: `Rankbeam\Seo\Pro\Scanning\IssueRegistry`. Skanery nigdy nie tworzą kodów doraźnie. Budują każdy problem przez `IssueRegistry::make()`, które przypisuje ważność i pole z rejestru oraz **odrzuca każdy niezdefiniowany kod**. Poniższy katalog jest więc kontraktem, na którym możesz budować: panele, eksporty i [ocena Pro](/pl/pro/scoring) odczytują kody zamiast analizować tekst komunikatów. Bezpłatny [`seo:audit`](/pl/guide/audit) używa własnego rejestru metadanych rdzenia, z węższym zakresem i częściowo innymi kodami hreflang.

Każdy kod zawiera:

- **id** — stabilny ciąg zapisany jako `seo_scan_issues.issue_type`.
- **severity** — `critical`, `warning` lub `notice`; **stała dla danego kodu**. Zamiast zmieniać ważność, rozdzielamy kod.
- **field** — pole `seo_meta`, którego dotyczy problem, lub _page_ dla problemów całej strony.
- **execution class** — dane potrzebne do wykrycia problemu (poniżej).
- **evidence** — klucze w tablicy `context` problemu.

## Klasy wykonania {#execution-classes}

Każda kontrola należy do dokładnie jednej z trzech klas, zależnie od wymagań wykonania:

| Klasa | Wymagania | Kto ją wykonuje |
|---|---|---|
| **metadata** | model i resolver rdzenia, bez pobierania strony | skanowanie modelu (`PageScanner`); bezpłatny [`seo:audit`](/pl/guide/audit) obejmuje część kontroli metadanych |
| **rendered** | HTML zwracany przez stronę (żądanie do jądra w tym samym procesie lub zewnętrzne pobranie) | skanowanie URL-a (`UrlScanner`) |
| **network** | pobranie **wychodzące** w celu sprawdzenia _osobnego_ adresu docelowego (adres kanoniczny wskazujący gdzie indziej) | skanowanie URL-a, **zawsze przez `SsrfGuard`** |

Dlatego bezpłatny audyt w procesie aplikacji nigdy nie dorówna pełnemu skanowaniu Pro: tylko kody **metadata** można obliczyć bez renderowania strony, a tylko proces Pro pobiera wyrenderowany HTML i sprawdza kanoniczne adresy docelowe przez sieć. Filtruj rejestr według klasy przez `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`.

## Kody metadanych {#metadata-codes}

Wykrywane z modelu i resolvera przez `PageScanner`. `missing_title`, `missing_description` i kody długości są też emitowane przez skanowanie wyrenderowanego URL-a, które mierzy zwracany `<head>`. Kody i znaczenie pozostają takie same.

| Kod | Ważność | Pole | Dowody | Znaczenie |
|---|---|---|---|---|
| `missing_title` | critical | title | — | Brak tytułu i możliwej do wyliczenia wartości zastępczej. |
| `missing_description` | warning | description | — | Brak metaopisu i możliwej do wyliczenia wartości zastępczej. |
| `missing_og_image` | notice | og_image | — | Brak obrazu Open Graph i możliwej do wyliczenia wartości zastępczej. |
| `missing_focus_keyword` | notice | focus_keywords | — | Nie ustawiono głównego słowa kluczowego. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | Tytuł jest używany na innych stronach w tym samym locale. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | Opis jest używany na innych stronach w tym samym locale. |
| `title_too_long` | warning | title | `length`, `max`, `script` | Rozstrzygnięty tytuł przekracza zalecenie dla systemu pisma (60 dla alfabetu łacińskiego, około 30 dla CJK). |
| `title_too_short` | notice | title | `length`, `min`, `script` | Rozstrzygnięty tytuł jest poniżej dolnego progu systemu pisma (30 dla alfabetu łacińskiego, około 15 dla CJK). |
| `description_too_long` | warning | description | `length`, `max`, `script` | Rozstrzygnięty opis przekracza zalecenie dla systemu pisma (160 / około 80). |
| `description_too_short` | notice | description | `length`, `min`, `script` | Rozstrzygnięty opis jest poniżej dolnego progu systemu pisma (70 / około 35). |
| `robots_conflict_indexing` | critical | robots | `robots` | Dyrektywa robots zawiera zarówno `index`, jak i `noindex`. |
| `robots_conflict_following` | warning | robots | `robots` | Dyrektywa robots zawiera zarówno `follow`, jak i `nofollow`. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | Strona z adresem kanonicznym wskazującym na siebie jest `noindex`. To heurystyka do sprawdzenia, a nie dowód, że strona musi być indeksowana. Emitowana przy skanowaniu modelu i wyrenderowanego URL-a. |
| `invalid_canonical` | critical | canonical | `canonical` | Wartość adresu kanonicznego nie jest prawidłowym URL-em. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | Adres kanoniczny wskazuje inny host niż strona. |
| `shared_canonical` | notice | canonical | `canonical` | Kilka stron deklaruje ten sam adres kanoniczny. |
| `insecure_canonical` | warning | canonical | `canonical` | Adres kanoniczny `http://` w witrynie `https`. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | Wariant hreflang używa wartości, która nie jest ani `x-default`, ani poprawnym kodem języka BCP-47. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | Zadeklarowano warianty, ale żaden nie wskazuje własnego locale strony (brak hreflang do siebie). |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | Ten sam kod hreflang wskazuje więcej niż jeden URL (niejednoznaczna grupa). |
| `hreflang_missing_x_default` | notice | alternates | `languages` | Wielojęzyczna grupa hreflang nie ma wariantu zastępczego `x-default`. |
| `aeo_missing_author` | notice | schema | — | Artykuł w danych strukturalnych strony nie ma encji autora (schemat nie wskazuje jawnie autorstwa/pochodzenia). |
| `aeo_article_missing_date` | notice | schema | — | Artykuł w danych strukturalnych strony nie ma daty publikacji/modyfikacji (schemat nie wskazuje jawnie chronologii artykułu). |

Progi długości pochodzą z [zasad długości](/pl/guide/multilingual#title-and-description-budgets-per-script) rdzenia uwzględniających system pisma (Pro 2.33): 60/160 dla alfabetu łacińskiego, około 30/80 dla CJK, liczone w grafemach. Skanowanie nie przeczy więc licznikom znaków w edytorze. Dolne progi (tytuł 30, opis 70 dla alfabetu łacińskiego, około połowy tych wartości dla CJK) oznaczają przyjęty przez skaner dolny poziom optymalizacji, a klucz kontekstu `script` wskazuje zastosowaną kategorię. Długość jest mierzona dla **rozstrzygniętego** tytułu/opisu, czyli wartości faktycznie renderowanej, wraz z wartościami zastępczymi i sufiksem tytułu.

Kody `hreflang_*` sprawdzają zadeklarowane warianty hreflang strony, odczytane z `alternates` resolvera: nieprawidłowe lub powielone kody, brak odwołania do siebie i brak `x-default` w grupie wielojęzycznej. Działają tylko wtedy, gdy strona deklaruje warianty. Te kontrole metadanych nie sprawdzają **wzajemności** odwołań między stronami („return tags”). Opcjonalna kontrola sieciowa poniżej pobiera drugą stronę.

Kody `aeo_*` są sygnałami **gotowości do odpowiedzi (AEO)**: czy treść artykułu jest czytelna w danych strukturalnych? Odczytują rozstrzygnięty graf JSON-LD i uruchamiają się **tylko** wtedy, gdy deklaruje dane strukturalne typu artykuł (`Article`, `BlogPosting`, `NewsArticle`, …), którym brakuje encji `author` (jawnego autorstwa/pochodzenia) lub `datePublished` / `dateModified` (jawnej chronologii). Strona bez artykułu nigdy nie jest oznaczana. Steruje nimi `seo-pro.scan.checks.aeo`, domyślnie włączone, a ich odpowiednik znajduje się w bezpłatnym [`seo:audit`](/pl/guide/audit).

::: tip `missing_focus_keyword` wymaga włączenia
Uwaga o głównym słowie kluczowym pojawia się tylko wtedy, gdy w **rdzeniu** włączono proces słów kluczowych (`seo.keywords.enabled`, domyślnie `false`). Gdy jest wyłączony, skanowanie nie zgłasza braku słowa kluczowego. Bezpłatne polecenie [`seo:audit`](/pl/guide/audit) i edytor Filament odczytują **tę samą** opcję rdzenia, więc skanowanie, audyt i przypomnienie edytora zawsze są zgodne. Istnieje tylko jeden warunek włączenia.
:::

## Kody wyrenderowanej strony {#rendered-codes}

Wykrywane przez `UrlScanner` ze zwracanego HTML. Dla celów na tym samym hoście używane jest żądanie do jądra w tym samym procesie, bez ruchu wychodzącego, a dla celów zewnętrznych — pobranie objęte zabezpieczeniami.

| Kod | Ważność | Pole | Dowody | Znaczenie |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | URL zwrócił status 4xx/5xx. |
| `empty_response` | critical | page | — | URL zwrócił pustą treść odpowiedzi. |
| `missing_canonical` | notice | canonical | — | Brak `<link rel="canonical">` w wyrenderowanej sekcji head. |
| `noindex_page` | notice | robots | `robots` | Wyrenderowana strona jest `noindex` (informacja). Strona `noindex`, która ma też **adres kanoniczny wskazujący na siebie**, otrzymuje zamiast tego punktowany `noindex_warning`. |
| `missing_h1` | notice | page | — | Brak nagłówka `<h1>`. |
| `multiple_h1` | notice | page | `count` | Więcej niż jeden `<h1>` (informacja). |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | Obrazy treści bez atrybutu `alt`. Jawne `alt=""` oznacza obraz dekoracyjny i nie jest zgłaszane. |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter` | Treść poniżej skonfigurowanej liczby słów. Liczy je tokenizer listy kontrolnej: białe znaki dla systemów pisma ze spacjami, segmentacja słownikowa ICU (`segmenter: intl`, wymaga ext-intl) dla chińskiego, japońskiego i tajskiego. Japoński artykuł z 400 słowami nie jest więc jednym „słowem”. |
| `mixed_content` | warning | page | `count`, `sample` | Zasoby podrzędne `http://` na stronie `https`. |
| `html_lang_missing` | notice | page | — | Brak `<html lang>` lub pusta wartość. Technologie asystujące mogą wybrać niewłaściwy głos. |
| `html_lang_invalid` | notice | page | `declared` | Wartość `lang` nie jest znacznikiem BCP-47 (`english`, `en_US` z podkreśleniem, `jp`). |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script` | Widoczna treść jest zapisana innym systemem pisma niż zadeklarowany język: `lang="en"` na stronie japońskiej, `lang="ru"` przy tekście łacińskim. Kontrola działa tylko na poziomie systemu pisma; wskazanie niewłaściwego języka zapisanego tym samym alfabetem łacińskim byłoby zgadywaniem, czego skaner nie robi. Wymaga ≥ 40 liter treści. |

## Kody sieciowe {#network-codes}

Wykrywane przez `UrlScanner` tylko po włączeniu odpowiedniej opcji: `seo-pro.scan.url_checks.check_canonical_target` dla adresu kanonicznego, `check_hreflang_reciprocity` dla wariantów hreflang. Każdy cel jest pobierany **przez `SsrfGuard`** (dozwolone schematy, zakres hostów, odrzucanie prywatnych IP, budżety przekierowań/czasu/rozmiaru), **bez** podążania za przekierowaniami, aby przekierowujący adres kanoniczny był widoczny. Adres kanoniczny lub wariant wskazujący na samą stronę jest pomijany, bo właśnie ją pobrano.

| Kod | Ważność | Pole | Dowody | Znaczenie |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | Cel został odrzucony przez `SsrfGuard` przed jakimkolwiek żądaniem HTTP. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | Adres kanoniczny wskazuje stronę zwracającą błąd HTTP. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | Adres kanoniczny wskazuje stronę z przekierowaniem; ustaw końcowy URL. |
| `canonical_target_noindex` | warning | canonical | `canonical` | Adres kanoniczny wskazuje stronę, która sama jest `noindex`. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | Nie udało się zweryfikować kanonicznego celu (odmowa zabezpieczenia / brak możliwości osiągnięcia). |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status` | Zadeklarowany wariant nie deklaruje odwołania zwrotnego do strony. Para hreflang może zostać pominięta; samo to nie wyklucza tłumaczenia z indeksowania. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason` | Nie udało się pobrać wariantu (odmowa zabezpieczenia, status błędu, przekierowanie, przekroczenie limitu rozmiaru), więc wzajemność nie została sprawdzona. To brak dowodów, a nie defekt. |

Kontrola wzajemności pobiera najwyżej `hreflang_max_alternates` celów na stronę (domyślnie 10), w tym `x-default`, pomijając duplikaty i samą stronę. Powyższe kody metadanych `hreflang_*` na poziomie modelu sprawdzają *zadeklarowaną* listę; ta kontrola jako jedyna potrzebuje drugiej strony.

Każda ścieżka sieciowa tutaj korzysta ze wspólnego `SsrfGuard`. Model zagrożeń i uwagę o pozostałym ryzyku TOCTOU opisuje [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md).

## Jak kody wpływają na ocenę {#how-codes-feed-the-score}

[Ocena SEO Pro](/pl/pro/scoring) to `100 −` stała kara za każdy punktowany problem, ważona według powyższych poziomów. Większość kodów jest uwzględniana; kilka wyłączono celowo: `missing_focus_keyword` (doradczy), `noindex_page` i `multiple_h1` (informacyjne), `blocked_url` / `canonical_target_blocked` / `hreflang_target_unverified` („nie udało się sprawdzić” ≠ defekt) oraz kody `hreflang_*`, `html_lang_*` i `aeo_*` (sygnały doradcze obecnie nieuwzględniane w ocenie). [Strona oceny](/pl/pro/scoring) zawiera pełną listę uwzględnianych kodów i karę dla każdego z nich.

## Cykl życia problemu {#issue-lifecycle}

Problem nie jest tylko wierszem istniejącym, dopóki występuje defekt. Ma cykl życia, a skanowanie **uzgadnia** problemy celu zamiast je kasować i tworzyć od nowa. Każdy problem ma stabilną tożsamość: cel (`scannable_type` + `scannable_id` dla modelu lub `url` dla celu trasy/mapy witryny) oraz `issue_type`. Każdy kod jest emitowany najwyżej raz na cel na skanowanie. Kody obejmujące wiele naruszeń (`missing_image_alt`, `mixed_content`, `hreflang_*`, …) łączą je w jeden wiersz z `count` / `sample`, dzięki czemu tożsamość jest unikalna.

W każdym skanowaniu dla każdego celu:

- wynik **bez istniejącego wiersza** jest tworzony jako `open` z ustawieniem `detected_at`;
- wynik **pasujący do otwartego wiersza** odświeża dowody i zachowuje pierwotne `detected_at` — stały moment *pierwszego wykrycia*, już niezerowany przy każdym skanowaniu;
- otwarty problem, którego zakończona kontrola **już nie wykrywa**, otrzymuje stan **`fixed`** i czas `resolved_at`. Wiersz jest **zachowany, nie usunięty**, więc rzeczywista poprawka zostaje zapisana;
- problem **`fixed`**, który **wraca**, jest **ponownie otwierany** w tym samym wierszu (regresja), z ponownym ustawieniem `detected_at`;
- problem oznaczony przez użytkownika w panelu jako **`ignored`** pozostaje nietknięty.

| Stan | Znaczenie | Ustawiany przez |
|---|---|---|
| `open` | Obecnie występuje. | skanowanie (nowy lub nadal wykrywany) |
| `fixed` | Występował, ale nie jest już wykrywany. | skanowanie automatycznie, przy następnym przebiegu, który go nie wykryje |
| `ignored` | Wyciszony przez użytkownika; wyłączony z liczby otwartych problemów i oceny. | akcję Ignoruj w panelu |

Ponieważ poprawki są teraz zapisywane zamiast odrzucane, [raport pod własną marką](/pl/pro/reports) może pokazywać **rzeczywiste liczby naprawionych i nowych problemów** w okresie zamiast różnicy migawek raportów. Odbiorcy liczby otwartych problemów — panel, polecenie [`seo-pro:scan-status`](/pl/pro/headless) i [ocena](/pl/pro/scoring) — filtrują do `open`, więc zachowane wiersze `fixed` nigdy nie zawyżają wyników. Naprawione wiersze są przypisane do przebiegu, który je rozwiązał, i usuwane według zwykłego okresu [retencji](/pl/pro/production) przebiegów skanowania.

## Konfiguracja {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

Budżet rozmiaru odpowiedzi dla pobrań objętych zabezpieczeniami wynosi `seo-pro.http.max_response_bytes` (domyślnie 2 MB). Skanowanie tego samego hosta w procesie aplikacji nie ma tego limitu.

## Uwaga o zgodności (zmiana nazwy kodu problemu) {#compatibility-note-issue-code-rename}

Poprzedni pojedynczy kod `robots_conflict`, który miał dwa poziomy ważności, został rozdzielony, aby każdy kod odpowiadał dokładnie jednemu poziomowi:

| Stary kod | Nowy kod | Ważność |
|---|---|---|
| `robots_conflict` (index + noindex) | `robots_conflict_indexing` | critical |
| `robots_conflict` (follow + nofollow) | `robots_conflict_following` | warning |

Jeśli zapisywałeś lub filtrowałeś po `robots_conflict`, przejdź na dwa nowe kody.
