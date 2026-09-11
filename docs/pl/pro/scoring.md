---
description: "Ocena SEO 0–100 ze skanowania Pro: w pełni audytowalna i deterministyczna. Każdy odjęty punkt wynika z konkretnego problemu skanowania, więc te same problemy zawsze dają tę samą liczbę."
---

# Ocena SEO — przejrzysta, wersjonowana, należąca do Pro {#the-seo-score-—-transparent-versioned-pro-owned}

Skanowanie Pro nadaje każdej stronie **ocenę SEO 0–100** — jedną liczbę, której szuka osoba przechodząca z RankMath lub Yoast. W przeciwieństwie do oceny o niejawnych zasadach ta jest **w pełni audytowalna**: każdy odjęty punkt można przypisać do dokładnie jednego [problemu skanowania](/pl/pro/scan-issues), a ten sam zbiór problemów zawsze daje ten sam wynik.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip Jedna liczba, jeden właściciel
Ocena liczbowa jest funkcją **Pro**. Znajduje się w rekordzie `seo_scan_results` Pro, nigdy w `seo_meta` rdzenia. Dawna kolumna `seo_score` została usunięta w Core 3. Bezpłatny [`seo:audit`](/pl/guide/audit) rdzenia podaje dla każdej strony **pass / warn / fail**, **bez liczby**. Ocena punktowa to dodatkowa wartość płatnego pakietu.
:::

## Zasady oceny {#the-rubric}

Ocena jest obliczana według **opublikowanych, wersjonowanych zasad** — `Rankbeam\Seo\Pro\Scanning\ScoreRubric`. Definiują ją dwie rzeczy: jawna **lista uwzględnianych kodów problemów** i stała **kara dla każdego poziomu ważności**.

| Ważność | Kara | Znaczenie |
|---|---|---|
| `critical` | **−40** | Problem o dużym wpływie według tych zasad. |
| `warning` | **−15** | Problem do sprawdzenia wkrótce. |
| `notice` | **−5** | Usprawnienie, które warto rozważyć. |

Ważność każdego kodu jest odczytywana wprost z [rejestru problemów](/pl/pro/scan-issues), będącego jedynym źródłem prawdy. Zasady oceny nie wyznaczają jej ponownie. Każdy kod ma dokładnie jeden poziom ważności, aby wynik był deterministyczny.

### Co uwzględnia ocena {#what-the-score-counts}

Są to deterministyczne kontrole wybrane przez zasady produktu Rankbeam, w tym heurystyki wymagające interpretacji redakcyjnej. Problem krytyczny kosztuje 40 punktów, ostrzeżenie 15, a uwaga 5. Ocena nie prognozuje wyników w wyszukiwarce.

| Kod | Ważność | Kara |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

Ponieważ kody metadanych są wykrywane podczas skanowania modelu, a kody renderowania/sieci tylko podczas skanowania URL-a (zobacz [klasy wykonania](/pl/pro/scan-issues#execution-classes)), ocena celu **modelowego** odzwierciedla kontrole metadanych, a celu **URL** — wyrenderowaną stronę. Wynik 100 przy skanowaniu modelu oznacza „brak defektów metadanych”, a nie „wyrenderowana strona jest idealna”. Do jej sprawdzenia przeskanuj URL.

### Czego ocena celowo NIE uwzględnia {#what-the-score-deliberately-does-not-count}

Poniższe kody rejestru są celowo wyłączone. Wyłączenia należą do kontraktu: test sprawdza, czy każdy kod rejestru jest punktowany lub wymieniony tutaj.

| Kod | Dlaczego jest wyłączony |
|---|---|
| `missing_focus_keyword` | **Doradczy.** Dostępny po włączeniu opcjonalnego procesu `seo.keywords.enabled`. Strona nie może tracić punktów za niestosowanie głównych słów kluczowych, a wynik nie może zależeć od opcji konfiguracji. |
| `noindex_page` | **Informacyjny.** `noindex` jest świadomym stanem, a nie defektem jakości metadanych. Heurystykę „noindex z adresem kanonicznym wskazującym na siebie” punktuje zamiast tego `noindex_warning`. |
| `multiple_h1` | **Informacyjny.** Google dopuszcza wiele nagłówków H1, więc nie ma za nie kary. |
| `blocked_url` | **Brak dowodów.** SsrfGuard odmówił pobrania, więc strona nie została sprawdzona. To nie jest defekt strony. |
| `canonical_target_blocked` | **Brak dowodów.** Nie udało się zweryfikować kanonicznego adresu docelowego. To nie jest defekt strony. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Doradcze (na razie).** Te kody hreflang Pro pojawiają się w skanowaniu; bezpłatny audyt ma własne kody hreflang, ale nie zmieniają jeszcze oceny. Ich dodanie wymagałoby podniesienia `VERSION`. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **Doradcze.** Kontrole języka są wyłączone z tych zasad oceny. |
| `hreflang_not_reciprocal` | **Doradczy.** Opcjonalna kontrola wzajemnych odwołań nie jest punktowana. |
| `hreflang_target_unverified` | **Brak dowodów.** Nie udało się sprawdzić wzajemnych odwołań. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Doradcze.** Sygnały gotowości do odpowiedzi (AEO): wskazują w skanowaniu i bezpłatnym audycie artykuł bez encji autora lub daty publikacji, ale nie zmieniają oceny. Wymagałoby to podniesienia `VERSION`. |

Gęstość słowa kluczowego, słowa wzmacniające przekaz i pozostałe elementy [listy kontrolnej treści strony](/pl/pro/on-page-checklist) w ogóle nie wpływają na ocenę. To kontrole doradcze na osobnej liście pass/warn/fail, a nie kody rejestru.

## Wersjonowanie — historyczne oceny nigdy nie zmieniają się po cichu {#versioning-—-historical-scores-never-silently-change}

Każda zapisana ocena jest oznaczona wersją `ScoreRubric::VERSION`, która ją wygenerowała (`rubric_version`). Wynikają z tego dwie rzeczy:

- **Nowy** kod problemu nie zmienia oceny, dopóki nie zostanie celowo dodany do listy uwzględnianych kodów. Wprowadzenie nowej kontroli nigdy nie zmienia więc wstecz zapisanej oceny. Zmiana listy lub wag jest zmianą zasad oceny i podnosi ich wersję.
- Ocena jest **zapisywana, a nie obliczana ponownie przy odczycie**. Dziś widzisz tę samą liczbę co w zeszłym tygodniu oraz zasady, które ją wyjaśniają.

## Gdzie jest przechowywana {#where-it-s-stored}

Każde skanowanie tworzy lub aktualizuje jeden wiersz na cel w `seo_scan_results`:

| Kolumna | Zawartość |
|---|---|
| `scannable_type` / `scannable_id` | Oceniany model (null dla celów URL). |
| `url` | Oceniany URL. |
| `score` | Liczba 0–100. |
| `rubric_version` | Zasady, które wygenerowały ocenę. |
| `penalty_total` | Surowa suma kar **przed** ograniczeniem wyniku do minimum 0. |
| `scored_issues` | Liczba problemów, które wpłynęły na wynik. |
| `breakdown` | `[{code, severity, penalty}, …]` — pełny zapis obliczenia. |
| `keywords_enabled` | Stan `seo.keywords.enabled` w chwili skanowania (zapisany dla przejrzystości; ocena od niego nie zależy). |
| `scan_run_id` | Przebieg, który obliczył ocenę (po usunięciu przebiegu ustawiany na null; ocena nie jest usuwana, bo przedstawia bieżący stan, nie historię przebiegów). |
| `scored_at` | Czas obliczenia oceny. |

## Odczytywanie oceny {#reading-the-score}

**Bez panelu** — ostatnia ocena modelu:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` wypisuje **średnią ocenę witryny** w podsumowaniu. Panel Filament pokazuje ją jako główną statystykę „Śr. wynik SEO”, z kolorem zależnym od oceny literowej.

### Przedziały ocen {#grade-bands}

Prezentacyjna ocena literowa wyprowadzona z liczby. To liczba stanowi kontrakt:

| Wynik | Ocena |
|---|---|
| 90–100 | A |
| 75–89 | B |
| 50–74 | C |
| 25–49 | D |
| 0–24 | F |

## Sygnał do sprawdzenia przed publikacją (`noindex_warning`) {#the-shipping-signal-noindex-warning}

`noindex_warning` pojawia się, gdy strona łączy `noindex` z **adresem kanonicznym wskazującym na siebie**, czyli na własny URL. Rankbeam traktuje to jako sygnał do sprawdzenia przed publikacją. Taki adres kanoniczny **nie dowodzi**, że indeksowanie jest zamierzone: połączenie może być celowe. Adres kanoniczny w innej domenie nie uruchamia tej heurystyki. Problem zawiera `context.shipping_signal` (np. `self_canonical`) oraz porównywane `canonical` i `page_url`.

Kontrolę wykonują oba skanery. Skanowanie modelu (`PageScanner`) porównuje zapisany adres kanoniczny z URL-em modelu. Skanowanie wyrenderowanego URL-a (`UrlScanner`) podnosi problem strony `noindex` z adresem kanonicznym wskazującym na siebie z informacyjnego `noindex_page` do punktowanego `noindex_warning`. Dlatego samo `noindex_page` jest wyłączone: potencjalny konflikt jest obsługiwany przez `noindex_warning` na obu ścieżkach. Sprawdź faktyczne przeznaczenie strony, zanim zmienisz jej dyrektywę indeksowania.

## Konfiguracja {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

Lista uwzględnianych kodów i wagi **nie są** konfigurowalne. Ocena musi być deterministyczna dla danego `rubric_version` w każdej instalacji, więc zmiana sposobu obliczania jest zmianą zasad w kodzie, a nie ustawieniem.
