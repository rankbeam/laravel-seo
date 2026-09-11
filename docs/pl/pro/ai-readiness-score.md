---
description: "Druga deterministyczna ocena obok oceny SEO: zdefiniowana przez Rankbeam miara technicznej gotowości na AI — czy roboty mogą dotrzeć do strony i ją odczytać — oddzielona od wyniku SEO."
---

# Ocena gotowości na AI — drugi, deterministyczny wymiar {#the-ai-readiness-score-—-a-second-deterministic-axis}

Skanowanie Pro nadaje każdej stronie drugą liczbę obok [oceny SEO](/pl/pro/scoring): **ocenę gotowości na AI 0–100**. Odpowiada ona na inne pytanie: *czy roboty AI i systemy odpowiedzi mogą dotrzeć do tej treści, odczytać ją i przypisać jej autorstwo?* **Nigdy nie jest łączona z oceną organicznego SEO**. To dwa osobne wymiary, każdy z własnymi zasadami, wersją i kolumną.

::: warning Co oznacza ta liczba, a czego nie oznacza
Ocena gotowości na AI jest **zdefiniowaną przez Rankbeam deterministyczną miarą zgodności technicznej**: sprawdza, czy sygnały strony dostępne dla robotów istnieją i mają poprawną postać. **Nie** prognozuje pozycji, indeksowania, uwzględnienia ani cytowania w żadnym systemie wyszukiwania lub AI. Żaden wynik tego nie gwarantuje. Kontrola `air_llms_txt` przyznaje punkty za **opcjonalny** plik zgodności `llms.txt` dla narzędzi, które zdecydują się z niego korzystać. Nie jest on wymagany przez Google Search ani nie jest sygnałem rankingowym.
:::

Tak jak ocena SEO jest **w pełni deterministyczna i odtwarzalna**: każdy punkt wynika z nazwanej kontroli opartej na skanowaniu, a te same sygnały zawsze dają tę samą liczbę. **Obliczanie oceny nie wykonuje żadnych wywołań AI.** Chodzi o przejrzysty, audytowalny pomiar, a nie próbkowanie odpowiedzi LLM jak w produktach SaaS mierzących „widoczność w AI”.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip Dwa wymiary, nigdy połączone
`AI-readiness: 74/100` znajduje się obok `SEO: 82/100`; żaden nie zmienia drugiego. Wynik gotowości na AI ma własne kolumny `ai_readiness_*` w wierszu `seo_scan_results` Pro. Tak jak w ocenie SEO, **liczba jest funkcją Pro**. Bezpłatny [`seo:audit`](/pl/guide/audit) rdzenia nie wypisuje oceny liczbowej.
:::

## Przyznawanie punktów zamiast kar {#additive-credit-not-penalty}

[Ocena SEO](/pl/pro/scoring) zaczyna od 100 i *odejmuje* kary. Gotowość na AI działa odwrotnie: zaczyna od **0** i **przyznaje** wagę każdej kontroli, w całości lub części. Witryna stopniowo osiąga gotowość, więc bez sygnałów AI uczciwie uzyskuje wynik bliski 0 zamiast „100 minus kilka”. Suma wag wynosi dokładnie **100**.

## Zasady oceny {#the-rubric}

Wynik jest obliczany według **opublikowanych, wersjonowanych zasad** — `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric` — obejmujących dziesięć kontroli w czterech kategoriach:

### A · Dostęp botów i sterowanie nimi — 30 punktów {#a-·-bot-access-control-—-30-points}

Czy roboty wyszukiwarek AI i asystentów rzeczywiście mogą dotrzeć do witryny? Ocena opiera się na **udostępnianym `/robots.txt`** witryny, rozstrzyganym dla **własnej ścieżki skanowanej strony**. Strona objęta `Disallow: /section` jest objęta zakazem skanowania, nawet gdy katalog główny jest dostępny dla robotów. robots.txt jest dyrektywą dla robotów, które ją respektują, a nie blokadą dostępu sieciowego. Używana jest klasyfikacja celów z [katalogu robotów AI](/pl/guide/ai-crawlers): trenowanie / wyszukiwanie / asystent.

| Kontrola | Waga | Przyznawanie punktów |
|---|---|---|
| `air_robots_reachable` — `robots.txt` jest udostępniany i czytelny | 6 | obecny / brak |
| `air_ai_search_access` — roboty **wyszukiwarek AI** (kanał kierujący ruch) mogą dotrzeć do witryny | 10 | proporcja dopuszczonych |
| `air_ai_assistant_access` — roboty **asystentów AI** mogą dotrzeć do witryny | 8 | proporcja dopuszczonych |
| `air_explicit_ai_policy` — jawna reguła `robots.txt` dla znanego bota AI | 6 | obecna / brak |

::: tip Blokowanie botów treningowych nie zmniejsza gotowości
Niedopuszczanie botów treningowych (GPTBot, CCBot…) jest uprawnionym wyborem i **nigdy** nie powoduje kary. Punkty dotyczące trenowania przyznaje wyłącznie `air_explicit_ai_policy` — za świadome, jawne stanowisko. Witryna blokująca boty treningowe, ale dopuszczająca roboty wyszukiwarek i asystentów, może uzyskać pełne punkty w tej kategorii.
:::

### B · Możliwość odnalezienia — 20 punktów {#b-·-discoverability-—-20-points}

| Kontrola | Waga | Przyznawanie punktów |
|---|---|---|
| `air_sitemap_discoverable` — mapa witryny XML jest osiągalna **i** wskazana przez dyrektywę `Sitemap:` | 12 | oba warunki / jeden / żaden |
| `air_llms_txt` — udostępniany jest poprawny `/llms.txt` (nagłówek i linki) | 8 | poprawny / obecny / brak |

### C · Treść czytelna maszynowo — 22 punkty {#c-·-machine-readable-content-—-22-points}

| Kontrola | Waga | Przyznawanie punktów |
|---|---|---|
| `air_server_rendered_content` — istotna ilość tekstu w HTML renderowanym na serwerze (treść istnieje bez wykonywania JS) | 14 | według liczby słów |
| `air_markdown_twin` — odpowiednik strony w Markdown jest udostępniany przez negocjację treści | 8 | obecny / brak |

### D · Dane strukturalne i gotowość do odpowiedzi — 28 punktów {#d-·-structured-data-answer-readiness-—-28-points}

| Kontrola | Waga | Przyznawanie punktów |
|---|---|---|
| `air_schema_completeness` — obecne JSON-LD, typ głównej encji, autorstwo i data (autor i data dla artykułów) | 18 | kompletne / częściowe / brak |
| `air_answer_structure` — elementy ułatwiające wyodrębnienie odpowiedzi: schemat FAQ/QA/HowTo, hierarchia nagłówków, listy, zwięzły wstęp | 10 | według liczby elementów |

Każda kontrola przyznaje **pełne**, **częściowe** lub **zerowe** punkty albo zostaje **pominięta**, gdy nie udało się zebrać wymaganego sygnału, np. kontrola strony dla celu skanowanego bez pobrania strony. Pominięta kontrola daje 0, ale jest oznaczona. Sygnał, którego *nie można było sprawdzić*, nigdy nie jest przedstawiany jako potwierdzony brak.

### Zakres bezpłatnego audytu {#free-audit-reach}

Kompletność schematu (`air_schema_completeness`) można rozstrzygnąć z danych strukturalnych modelu bez pobierania strony. To ta sama ścieżka, której bezpłatny audyt już używa do sygnałów gotowości do odpowiedzi. Pozostałych dziewięć kontroli wymaga skanowania strony, więc pełna ocena należy do **skanowania Pro**.

## Rzetelny zakres — czego ten wymiar nie obejmuje {#honest-scope-—-what-this-axis-excludes}

Ten wymiar ocenia **deterministyczne sygnały treści**. Nie obejmuje kontroli infrastruktury agentów dotyczących działającej aplikacji lub DNS:

| Wyłączone | Powód |
|---|---|
| **DNS-AID** (rekordy DNS do wykrywania agentów) | Infrastruktura DNS / DNSSEC, a nie właściwość udostępnianej strony. |
| **Web Bot Auth** (podpisywanie żądań) | Interaktywne uzgadnianie kryptograficzne, a nie statyczna treść. |
| **Wykrywanie protokołów** (API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills, WebMCP…) | Wymaga działającej aplikacji / API / serwera MCP. |
| **Handel** (x402, MPP, UCP, ACP) | Mechanizmy płatności agentów; witryna treściowa nie ma za co pobierać opłat. |

Obejmuje kompletność encji schematu i strukturę bloków odpowiedzi: sygnały treści opisujące organizację i autorstwo, bez gwarancji użycia ich przez wyszukiwarkę lub system odpowiedzi.

## Wersjonowanie — historyczne oceny nigdy nie zmieniają się po cichu {#versioning-—-historical-scores-never-silently-change}

Każda zapisana ocena gotowości na AI jest oznaczona wersją `AiReadinessRubric::VERSION` używaną do jej obliczenia (`ai_readiness_version`). Każda zmiana zbioru kontroli, wagi lub sposobu przyznawania punktów jest zmianą zasad i **podnosi wersję**. Zapisana liczba zawsze wskazuje więc wyjaśniające ją zasady, a wartości historyczne pozostają porównywalne. Ocena jest **zapisywana, a nie przeliczana przy odczycie**. Progi wpływające na wynik, takie jak liczby słów i elementów ułatwiających odpowiedzi, są stałymi w kodzie związanymi z wersją, nigdy konfiguracją. Ustawienie nie może więc po cichu zmienić opublikowanej liczby.

::: warning Jedno wejście, którego wersja nie utrwala
Kontrole dostępu botów odczytują **bieżący** [katalog robotów AI](/pl/guide/ai-crawlers) z rdzenia. Aktualizacja katalogu — nowy bot lub zmiana klasyfikacji celu — faktycznie zmienia dane wejściowe i może przesunąć dwa wyniki cząstkowe dostępu botów bez podnoszenia `AiReadinessRubric::VERSION`. Wersja śledzi *zasady oceny*, nie katalog. To celowe: kontrola jest przydatniejsza z aktualną listą botów niż z zamrożoną. Dla dokładnej porównywalności historycznej przypnij wersję rdzenia obok wersji zasad oceny.
:::

## Gdzie jest przechowywana {#where-it-s-stored}

Każde skanowanie tworzy lub aktualizuje kolumny gotowości na AI w **tym samym** wierszu `seo_scan_results` co ocena SEO:

| Kolumna | Zawartość |
|---|---|
| `ai_readiness_score` | Liczba 0–100 (null, dopóki cel nie zostanie przeskanowany z tym wymiarem włączonym). |
| `ai_readiness_version` | Zasady, które wygenerowały ocenę. |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]` — pełny zapis obliczenia. |

Średnia przebiegu jest zapisywana w `seo_scan_runs.avg_ai_readiness` przy jego zakończeniu, analogicznie do `avg_score`. Tworzy trend gotowości na AI.

## Odczytywanie oceny {#reading-the-score}

**Bez panelu** — ostatni wynik modelu zawiera oba wymiary:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament** — dodaj kolumnę obok oceny SEO w tabeli dowolnego zasobu:

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

Wynik pojawia się też jako dodatkowa odznaka na karcie oceny strony nad polem tytułu SEO oraz jako własna sekcja [raportu pod własną marką](/pl/pro/reports) (PDF i e-mail): liczba, ocena literowa, zmiana względem poprzedniego raportu i trend kolejnych skanowań. Zawsze jest prezentowany obok oceny organicznego SEO, nigdy w nią włączany.

### Przedziały ocen {#grade-bands}

Prezentacyjna ocena literowa wynika z liczby, która stanowi kontrakt. Dla spójności przedziały są takie same jak w ocenie SEO:

| Wynik | Ocena |
|---|---|
| 90–100 | A |
| 75–89 | B |
| 50–74 | C |
| 25–49 | D |
| 0–24 | F |

## Konfiguracja {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

Kontrole i wagi **nie są** konfigurowalne. Ocena musi być deterministyczna dla danego `ai_readiness_version` w każdej instalacji, więc zmiana obliczenia jest zmianą zasad w kodzie, a nie ustawieniem.

::: warning Wykrywanie sygnałów witryny używa żądań w procesie aplikacji
Dla celu na tym samym hoście skanowanie rozstrzyga `/robots.txt`, `/llms.txt` i stronę przez jądro HTTP Laravel w tym samym procesie, tak jak pozostałe kontrole skanowania. `robots.txt` lub `llms.txt` udostępniane jako **plik statyczny**, z pominięciem routingu Laravel, nie są widoczne. Udostępniaj je przez trasy pakietu (zalecana konfiguracja), aby były punktowane.
:::
