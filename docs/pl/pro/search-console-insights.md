---
description: "Analiza słów kluczowych na podstawie własnych danych Search Console: pięć raportów o zakresach pozycji, zapytaniach do przeglądu CTR i zapytaniach wspólnych dla kilku stron."
---

# Analizy Search Console {#search-console-insights}

Pięć raportów obliczanych z Twoich danych Search Console: zapytania w wybranym zakresie pozycji, kandydaci do przeglądu CTR, nakładające się wyniki zapytań i stron, grupy zapytań oraz zmiany między okresami. Trzy korzystają ze zsynchronizowanej historii, a dwa współdzielą bieżące żądanie z pamięcią podręczną. Obejmują te konkretne analizy, nie pełny zbiór danych i możliwości zewnętrznej platformy słów kluczowych.

Funkcja opiera się na [integracji Search Console tylko do odczytu](/pl/pro/search-console) i synchronizacji historii. Jeśli opisane tam `seo-pro:gsc-sync` już działa, trzy z pięciu analiz nie wymagają **żadnych dodatkowych wywołań API**.

::: tip Wymaganie wstępne
Trzy analizy oparte na *migawkach* odczytują zapisaną historię `seo_gsc_metrics`. Najpierw zaplanuj `seo-pro:gsc-sync` (zobacz [Search Console → historia](/pl/pro/search-console)). Im więcej zsynchronizowanych dni, tym głębsze porównanie trendów.
:::

## Pięć analiz {#the-five-surfaces}

### 1. Słowa kluczowe blisko pierwszej strony {#_1-striking-distance-keywords}

Zapytania, których **średnia pozycja ważona wyświetleniami mieści się w zakresie 5–20**, uporządkowane według wyświetleń. Wykorzystaj je do przeglądu trafności i linków wewnętrznych. Sam zakres nie dowodzi, że mała zmiana przeniesie zapytanie na pierwszą stronę wyników.

### 2. Możliwości poprawy CTR {#_2-ctr-opportunities}

Zapytania, które **mają dobrą pozycję, ale są klikane rzadziej, niż oczekiwano** dla tej pozycji. Rzeczywisty CTR każdego zapytania jest porównywany z uśrednioną branżową krzywą CTR według pozycji. Te, które mają rzeczywiste wyświetlenia i wyraźnie niższy CTR od oczekiwanego, są **kandydatami do przeredagowania tytułu/opisu**, posortowanymi według szacowanych *utraconych kliknięć*. Lista stanowi naturalny punkt wyjścia dla [propozycji metadanych AI](/pl/pro/ai-assist), wskazując konkretne zapytania, które warto uwzględnić przy zmianach.

### 3. Kanibalizacja {#_3-cannibalization}

Zapytania, dla których **pojawiają się co najmniej dwa Twoje URL-e**. Nakładanie się wyników nie musi być szkodliwe. Przed scaleniem lub zróżnicowaniem stron sprawdź, czy służą różnym intencjom.

### 4. Grupy zapytań {#_4-query-clusters}

**Zapytania, na które każda strona faktycznie pojawia się w wynikach**, zgrupowane według strony: rzeczywisty zakres tematyczny strony w Google. Pomaga to zauważyć stronę odchodzącą od zamierzonego tematu albo pojawiającą się na wartościowe hasło, na które nigdy jej nie kierowano.

### 5. Trend względem poprzedniego okresu {#_5-trend-vs-previous-period}

**Największe zmiany** kliknięć, wyświetleń, pozycji i CTR w bieżącym okresie względem bezpośrednio poprzedzającego okresu o tej samej długości. Pozycję porównuje się tylko wtedy, gdy zapytanie miało ruch w obu okresach. Dla zupełnie nowego lub całkowicie znikającego zapytania nie ma czego porównać.

## Skąd pochodzą liczby: bieżące dane i migawki {#where-the-numbers-come-from-live-vs-snapshot}

Każda analiza odczytuje źródło, które odpowiada na jej pytanie poprawnie i przy najmniejszym koszcie. Zapisana historia nie pozwala odtworzyć, które **zapytanie** było powiązane z którą **stroną**, ponieważ przechowuje te wymiary osobno. Dlatego tylko dwie analizy wymagające par korzystają z bieżącego pobrania i **współdzielą jedno żądanie z pamięcią podręczną**.

| Analiza | Źródło | Powód |
|---|---|---|
| Słowa blisko pierwszej strony | **Lokalna migawka** | Potrzebuje pozycji i wyświetleń zapytania, które są już w historii; bez kosztu API |
| Możliwości poprawy CTR | **Lokalna migawka** | Te same własne dane; krzywa oczekiwanego CTR jest statycznym punktem odniesienia, nie zewnętrznym odczytem |
| Zmiany trendu | **Lokalna migawka** | Potrzebuje rzeczywistej historii dzień po dniu, którą zapisuje synchronizacja |
| Kanibalizacja | **Bieżące dane** (zapytanie × strona) | Pary zapytanie→strona nie są zapisywane, a utrwalanie każdej pary zwielokrotniłoby rozmiar danych |
| Grupy zapytań | **Bieżące dane** — *współdzieli pobranie analizy 3* | Te same pary, zgrupowane według strony zamiast zapytania |

Odwiedzenie strony analiz kosztuje więc **najwyżej jedno** żądanie Search Analytics, przechowywane w pamięci podręcznej przez `search_console.cache_ttl` sekund. Analizy par celowo korzystają z bieżących danych: kanibalizacja i grupowanie dotyczą obrazu *w danej chwili*, a wspólna pamięć podręczna ogranicza powtarzane żądania. Odświeżenie tokenu może wymagać dodatkowego żądania uwierzytelniającego, a limity Google nadal obowiązują. Analizy migawek nigdy nie korzystają z sieci.

## W panelu {#in-the-dashboard}

Po zainstalowaniu wtyczki Filament **Analizy Search Console** pojawiają się w grupie nawigacji *SEO*, tylko przy włączonej integracji. Strona jest wyłącznie do odczytu. Każda analiza ma osobną sekcję; puste analizy migawek proszą o synchronizację historii, a błąd bieżącego pobrania par pokazuje oczyszczony komunikat w sekcji, nigdy nie blokując całej strony.

## Konfiguracja {#configuration}

Wszystko znajduje się pod `search_console.insights` w `config/seo-pro.php`. Wartości domyślne są rozsądne; dostosuj progi do skali witryny.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info Krzywa oczekiwanego CTR
Krzywa możliwości poprawy CTR jest **heurystyką** złożoną z opublikowanych średnich organicznego CTR według pozycji. To punkt odniesienia, nie twierdzenie o Twojej konkretnej witrynie. Wskazane zapytanie jest *kandydatem do przeglądu*, a nie udowodnionym defektem. Jeśli masz własną zmierzoną krzywą, umieść ją w `insights.ctr_curve` jako mapę `position => percent`.
:::

## Zobacz też {#see-also}

- [Search Console](/pl/pro/search-console) — integracja tylko do odczytu i synchronizacja historii używane przez analizy
- [Raporty pod własną marką](/pl/pro/reports) — zmiany między okresami w PDF z Twoją marką
- [Pomoc AI](/pl/pro/ai-assist) — przeredagowanie tytułów i opisów wskazanych w analizie CTR
