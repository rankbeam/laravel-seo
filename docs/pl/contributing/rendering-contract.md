---
description: "Wiążąca lista wymagań, które musi spełniać sekcja head każdego stosu frontendowego renderującego dane SEO Rankbeam — źródło prawdy dla testów renderera rdzenia i aplikacji referencyjnych."
---

# Kontrakt renderowania {#the-rendering-contract}

To **jedna wiążąca lista wymagań**, które musi spełniać sekcja `<head>` każdego stosu frontendowego podczas renderowania danych SEO Rankbeam. Jest źródłem prawdy dla:

- testów jednostkowych struktury wyniku renderera w rdzeniu (`tests/Unit/Services/RenderingContractTest.php`) — szybkiej części testów niezależnej od frameworka, objętej CI pakietu;
- aplikacji referencyjnych dla poszczególnych stosów w `rankbeam-examples` (Blade, Inertia + Vue / React / Svelte, Livewire), których testy przeglądarkowe i SSR weryfikują te same asercje w rzeczywistym DOM;
- przewodników po frameworkach (Blade, Inertia i JSON, Livewire), które nigdy nie mogą opisywać rozwiązania naruszającego ten kontrakt.

Jeśli stos nie może spełnić któregoś punktu, jest to **błąd lub udokumentowane ograniczenie** — nie powód do osłabienia kontraktu. Warstwa danych (`SEOResolver` → niezmienny `SEOData` → `TagRenderer`) jest niezależna od frameworka; między stosami różni się tylko to, *jak rozstrzygnięte dane trafiają do DOM, zachowują się podczas nawigacji po stronie klienta i pozostają widoczne dla robotów*. Właśnie to określa ten kontrakt.

> Ta specyfikacja została dopracowana w niezależnym przeglądzie projektu.
> Ponowny przegląd jest potrzebny tylko po istotnych zmianach.

---

## 1. Wartości — co zawiera zgodna sekcja `<head>` {#_1-values-—-what-a-compliant-head-contains}

### Tytuł, opis, kanoniczny URL {#title-description-canonical}

- **Dokładnie jeden `<title>`** zawierający *rozstrzygnięty* tytuł — nigdy z podwójnym sufiksem (resolver dodaje `seo.title_suffix` raz, sprawdzając, czy tytuł już się nim nie kończy).
- **Jeden metaopis**, tylko jeśli udało się rozstrzygnąć opis (bez pustego tagu).
- **Jeden `<link rel="canonical">`**.

### Robots {#robots}

- Generuj `<meta name="robots">` **tylko wtedy, gdy dyrektywa różni się od domyślnej wartości witryny**. Zbędne `index,follow` zaśmieca wynik, a jego *brak* robot interpretuje właśnie jako `index,follow`. Porównanie ignoruje białe znaki (`index, follow` ≡ `index,follow`); odmienna dyrektywa jest generowana **dosłownie**. `seo.robots.emit_default = true` wymusza tag.
- Obsługuj deterministyczne **dyrektywy zaawansowane**: `noindex`, `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`, `max-video-preview`, `notranslate`, `unavailable_after`. To rozstrzygnięte wartości tekstowe; ich **pierwszeństwo wynika z łańcucha resolvera** (globalne → trasa → model → jawne). Te same dane wejściowe ⇒ ten sam wynik.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`, `og:locale`.
- `article:*` (`published_time`, `modified_time`, `author`, `section`, `tag`) **tylko gdy `og:type === 'article'` i wartość jest rzeczywista** — nigdy zmyślona, nigdy na stronie niebędącej artykułem.
- `og:image` z `og:image:width` / `og:image:height` / `og:image:alt` i `og:image:type` **gdy są znane**. Wiele obrazów jest **grupowanych** — po każdym `og:image` bezpośrednio następują jego własne właściwości wymiarów, tekstu alternatywnego i typu.

### Karty Twitter {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` i `twitter:image:alt` (gdy tekst alternatywny obrazu jest znany).
- `twitter:site` i `twitter:creator` są **opcjonalne i niezależne** — jeden może występować bez drugiego i żaden nie jest tworzony na podstawie drugiego.

### hreflang i ustawienia regionalne {#hreflang-locale}

- Hreflang ma dedykowaną ścieżkę w resolverze przez hook modelu `getSEOAlternates()`.
- Alternatywy hreflang, jeśli występują, są **bezwzględne, znormalizowane i unikalne według języka**, a przy kompletnych danych — wzajemne. `x-default` tylko po skonfigurowaniu.
- `og:locale:alternate` odzwierciedla **wyłącznie** ustawienia regionalne mające rzeczywisty wariant społecznościowy (mapuj `en-US` → `en_US`; porównuj formę po mapowaniu, nie wymagaj dosłownej równości).
- Zgodność `<html lang>` z rozstrzygniętymi ustawieniami regionalnymi (ten punkt należy do kontraktu, mimo że element `<html>` generuje *aplikacja*).

### JSON-LD dla poszczególnych stron {#per-page-json-ld}

- Poprawny składniowo i odporny na `</script>` (dane są kodowane z `JSON_HEX_TAG`, aby żadna wartość nie mogła przedwcześnie zamknąć elementu script — ochrona przed trwałym XSS).
- Akceptowane są zarówno **wiele bloków `<script>`, JAK I wspólny `@graph`**.
- Stabilne `@id` stosuje się **tylko tam, gdzie encje rzeczywiście się łączą** (Organization ↔ WebSite ↔ WebPage); stabilne `@id` *nie* jest obowiązkowe dla samodzielnych węzłów.

---

## 2. Normalizacja i niezmienniki {#_2-normalization-invariants}

- **Bezwzględne URL-e `http(s)`** dla `canonical`, `og:url`, `og:image`, `twitter:image`. **Żadne tagi puste ani z wartością null** nie trafiają do DOM.
- **`canonical` i `og:url` MUSZĄ być rozstrzygane do tego samego znormalizowanego URL-a.** Niezgodność jest **BŁĘDEM BEZWZGLĘDNYM**, a nie ostrzeżeniem.
- **Zasady normalizacji kanonicznych URL-i są spójne** we wszystkich miejscach: schemat / host / port / wielkość liter w ścieżce / lista dozwolonych parametrów zapytania / końcowy ukośnik są zawsze obsługiwane tak samo. Strony możliwe do indeksowania **odwołują się do siebie**; strona `noindex` **nie** dziedziczy strategii kanonicznej innej strony.
- **Kodowanie znaków specjalnych zależy od miejsca użycia**: atrybut HTML, tekst i JSON używają właściwych koderów. Asercje porównują **zdekodowane wartości semantyczne, a nie bajty**.
- **Zgodność między rendererami jest semantyczna, a nie bajtowa.** `render()` (HTML) ≡ `toArray()` ≡ `toInertiaHead()` *po normalizacji* — te trzy reprezentacje mogą zasadnie różnić się kolejnością i formą tagów. Zasady właściwości pojedynczych i powtarzalnych są jawne (jedno `og:title`; wiele `article:tag`).
- **Własność tagów**: renderer klienta zastępuje tagi *należące do pakietu* (z kluczami, zobacz §4), nie usuwając niezwiązanych z nimi tagów aplikacji.

---

## 3. Zachowanie — nawigacja po stronie klienta {#_3-behaviour-—-client-side-navigation}

Po każdym przejściu Inertia lub `wire:navigate` Livewire:

- istnieje **dokładnie jeden egzemplarz każdego tagu pojedynczego** (`<title>`, opis, canonical, każde `og:*`/`twitter:*`), **żaden nie jest nieaktualny**;
- **JSON-LD się nie kumuluje** — dane strukturalne poprzedniej strony są usuwane, a nie nakładane na kolejne (Livewire traktuje `<script>` jako nieusuwalny zasób, dlatego skrypty danych strukturalnych są oznaczane `data-seo-schema` i identyfikatorem dla danego URL-a, a te z poprzedniej strony są usuwane przy `livewire:navigated` — zobacz przewodnik po Livewire);
- przejście ze **strony z bogatymi metadanymi na stronę bez nich usuwa dodatkowe tagi** (druga strona nie zachowuje opisu/og/danych strukturalnych pierwszej);
- **zero ostrzeżeń hydratacji**, a metadane są semantycznie identyczne przed hydratacją i po niej.

---

## 4. Klucze head-key w Inertia (własność tagów) {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` nadaje każdemu wpisowi meta/link stabilny **`head-key`**. Inertia usuwa duplikaty elementów head według tego atrybutu: tag `<Head>` strony z tym samym `head-key` co tag układu *zastępuje* go zamiast dodawać duplikat.

- Klucz bazowy = `name ?? property` dla meta, `rel` dla linków.
- **Tagi powtarzalne są rozróżniane**, aby każdy zachował unikalny klucz: `article:tag` → `article:tag`, `article:tag:1`, …; hreflang → `alternate:en-US`, `alternate:fr-FR`.

W szablonach wiąż go jako **`:head-key`** — *nie* jako `:key` Vue (to niezwiązany z nim klucz uzgadniania `v-for`, który nie wpływa na usuwanie duplikatów head w Inertia).

---

## 5. Widoczność dla robotów (jawne tryby) {#_5-crawler-visibility-explicit-modes}

- **SSR / prerendering** MUSZĄ generować pełny kontrakt w **surowym HTML odpowiedzi HTTP** — jest to testowane oddzielnie od DOM po hydratacji (z wyłączonym JS).
- **Sam CSR nie pozwala deklarować zgodności dla robotów.** Domyślne Inertia (bez SSR) wstrzykuje metadane *po stronie klienta*: początkowy HTML pobierany przez robota nie zawiera metadanych SEO. Jest to udokumentowane, nie ukrywane — **metadane widoczne dla robotów wymagają Inertia SSR lub prerenderingu** (a JSON-LD dla robotów powinien być renderowany po stronie serwera).

---

## 6. Poza zakresem / co nie jest celem {#_6-out-of-scope-non-goals}

- **Zadania aplikacji, nie renderera**: `charset`, `viewport`, favicony. (Uwaga: `<meta charset>` musi poprzedzać wszelkie metadane spoza ASCII, dlatego za kolejność tych elementów head odpowiada aplikacja).
- **Testy e2e sprawdzają wyłącznie generowany wynik.** **Nie** sprawdzają indeksowania przez Google, *wyboru* adresu kanonicznego, kwalifikacji do wyników rozszerzonych ani pozycji; **nie** sprawdzają też MIME ani dostępności zdalnych obrazów. To zadania opcjonalnych testów integracyjnych/HTTP, nigdy macierzy przeglądarek.

---

## 7. Stan zgodności {#_7-conformance-status}

Co obecnie potwierdza każdy punkt. **Jednostkowe** = `RenderingContractTest` (rdzeń, CI pakietu). **Przeglądarka/SSR** = `rankbeam-examples` (macierz uruchamiana według harmonogramu). **Aplikacja** = odpowiada za to aplikacja hostująca. **Planowane** = cel zapisany w kontrakcie, ale dane nie są jeszcze modelowane w `SEOData`, więc renderer generuje bezpieczny podzbiór.

| Punkt | Stan |
|---|---|
| Dokładnie jeden rozstrzygnięty `<title>`, bez podwójnego sufiksu | **Jednostkowe** + przeglądarka |
| Metaopis tylko wtedy, gdy istnieje | **Jednostkowe** + przeglądarka |
| Jeden `<link rel="canonical">`, nigdy pusty | **Jednostkowe** + przeglądarka |
| Robots generowany tylko przy różnicy względem wartości domyślnej; dosłownie; przełącznik `emit_default` | **Jednostkowe** + przeglądarka |
| Zaawansowane dyrektywy robots według pierwszeństwa resolvera | **Jednostkowe** (resolver) |
| `og:title/description/type/url/site_name/locale`; ustawienia regionalne `en-US`→`en_US` | **Jednostkowe** + przeglądarka |
| `article:*` tylko gdy `og:type=article` i wartość jest rzeczywista | **Jednostkowe** + przeglądarka |
| `og:image` obecny i bezwzględny | **Jednostkowe** + przeglądarka |
| `og:image:width/height/alt`, `og:image:type`, grupowanie wielu obrazów | **Planowane** — `SEOData` zawiera pojedynczy ciąg `ogImage`; wymiary/tekst alternatywny/typ nie są jeszcze modelowane. Renderer generuje jeden bezwzględny `og:image`. |
| `twitter:card/title/description/image`; `site`/`creator` niezależne | **Jednostkowe** + przeglądarka |
| `twitter:image:alt` | **Planowane** — pole tekstu alternatywnego obrazu nie jest jeszcze modelowane. |
| hreflang bezwzględny, unikalny według języka | **Jednostkowe** + przeglądarka |
| Wzajemność hreflang, `x-default` po skonfigurowaniu | Przeglądarka (zależne od danych) |
| `og:locale:alternate` odzwierciedla rzeczywiste warianty społecznościowe | **Planowane** — mapa wariantów społecznościowych dla ustawień regionalnych nie jest jeszcze modelowana. |
| Zgodność `<html lang>` | **Aplikacja** (+ asercje przeglądarkowe) |
| JSON-LD poprawny składniowo i odporny na `</script>` | **Jednostkowe** + przeglądarka |
| Wiele skryptów LUB `@graph`; stabilne `@id` tam, gdzie encje się łączą | **Jednostkowe** (graf sprzedawcy) + przeglądarka |
| Bezwzględne URL-e; bez tagów pustych/null | **Jednostkowe** + przeglądarka |
| `canonical` ≡ `og:url` (bezwzględny błąd przy niezgodności) | **Jednostkowe** + przeglądarka |
| Spójna normalizacja kanonicznych URL-i; odwołania do siebie; izolacja noindex | Przeglądarka |
| Kodowanie znaków specjalnych według miejsca użycia; zgodność zdekodowanych wartości semantycznych | **Jednostkowe** |
| Zgodność semantyczna między rendererami (`render()` ≡ `toArray()` ≡ `toInertiaHead()`) | **Jednostkowe** |
| Stabilne `head-key` Inertia i rozróżnione tagi powtarzalne | **Jednostkowe** + przeglądarka |
| Nawigacja klienta: jeden tag pojedynczy, brak nieaktualnych, brak kumulacji JSON-LD, usuwanie | Przeglądarka — renderer dostarcza hooki `data-seo-schema` potrzebne do czyszczenia |
| Zero ostrzeżeń hydratacji; zgodność przed hydratacją i po niej | Przeglądarka |
| SSR generuje pełny kontrakt w surowym HTML; sam CSR opisany jako niezgodny | Przeglądarka + dokumentacja |

**Planowane punkty** to świadome, udokumentowane braki — kontrakt jest trwałym celem, a te rozszerzenia będą addytywne i zgodne wstecz, przeznaczone do przyszłego zadania (wymagają nowych pól / kolumn `SEOData`, wydania minor według SemVer). Renderer generuje dziś bezpieczny podzbiór; nigdy nie wymyśla wartości, której nie posiada.
