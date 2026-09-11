---
title: Czym jest Rankbeam? Infrastruktura SEO dla Laravel
description: "Czym jest Rankbeam: infrastruktura SEO dla Laravel w modelu open-core — bezpłatny rdzeń na licencji MIT do metadanych, kanonicznych adresów URL, JSON-LD, map witryny i sterowania robotami, a także komercyjny silnik monitorowania Pro i opcjonalny interfejs Filament."
---

# Czym jest Rankbeam? {#what-is-rankbeam}

**Rankbeam to infrastruktura SEO dla Laravel w modelu open-core: bezpłatny rdzeń na licencji MIT do metadanych, kanonicznych adresów URL, kart społecznościowych, powiązanych danych JSON-LD, map witryny i sterowania robotami oraz opcjonalne komercyjne monitorowanie i procesy pracy w Pro.** To nie jest tylko dołączony do aplikacji pomocnik generujący znaczniki podczas obsługi żądania. Rankbeam rozstrzyga dane SEO na podstawie Twoich modeli i konfiguracji, renderuje te same typowane dane przez Blade, sekcję head Inertia lub API JSON, a z Pro monitoruje je również po wdrożeniu.

## Rodzina pakietów {#the-package-family}

Rankbeam składa się z trzech pakietów ze wspólną macierzą obsługiwanych wersji:

| Pakiet | Licencja | Przeznaczenie |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, bezpłatny** | rdzeń — rozstrzyganie metadanych, powiązany graf schematu JSON-LD, mapy witryny XML, sterowanie robotami, bezpłatny `seo:audit` i importery |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, bezpłatny** | pola formularzy i podglądy na żywo dla Filament 4/5, które zapisują dane w `seo_meta` rdzenia |
| `rankbeam/laravel-seo-pro` | **komercyjna** | silnik operacyjny — skanowanie w kolejce z oceną 0–100, menedżer przekierowań, monitor błędów 404 bez adresów IP, robot sprawdzający niedziałające linki, analizy Search Console i pomoc AI z własnym kluczem |

Ten podział jest celowy. Wszystko, co trafia do wyrenderowanej strony, jest i pozostanie bezpłatne na licencji MIT. Płatna jest produkcyjna warstwa **audytu i monitorowania**. Komercyjny Pro to osobny pakiet — nigdy nie jest częścią bezpłatnego rdzenia.

## Dla kogo jest przeznaczony {#who-it-s-for}

Rankbeam sprawdza się, gdy dane SEO są **przechowywane, powiązane z modelami, wielojęzyczne, niezależne od interfejsu i poddawane audytowi** — w produkcyjnej aplikacji Laravel z treścią dynamiczną lub opartą na modelach. Do kilku statycznych stron, które potrzebują tylko tytułu i opisu, lepiej pasuje niewielki pomocnik do metadanych działający w czasie obsługi żądania. Poniższa uwaga o tym, [kiedy zestaw osobnych pakietów nadal wystarcza](#what-is-honestly-not-in-the-free-core), mówi o tym wprost.

## Obsługiwane wersje {#supported-versions}

Jedna macierz dla całej rodziny:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13 (Laravel 13 wymaga PHP 8.3+)
- **Filament** 4 / 5 (opcjonalnie)

## Czego Rankbeam nie zastępuje {#what-rankbeam-doesn-t-replace}

Rankbeam koordynuje dane SEO generowane przez aplikację Laravel. Nie jest hostowanym narzędziem do śledzenia pozycji, zestawem do badania słów kluczowych ani produktem analitycznym. Nie obiecuje określonych pozycji, indeksowania ani cytowań przez AI. Do generowania map witryny XML wykorzystuje [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap), zamiast tworzyć ten mechanizm od nowa. Twoje treści, routing i analityka pozostają tam, gdzie są.

Zaczynasz? [Zainstaluj bezpłatny rdzeń](/pl/guide/installation) albo czytaj dalej, aby poznać wyniki rzeczywistej wymiany pakietów na produkcji. Pro i licencja dla pierwszych klientów są dostępne na [rankbeam.dev](https://rankbeam.dev/pl/).

## Dlaczego nie trzy pakiety i kod integrujący {#why-not-three-packages-glue}

Większość aplikacji Laravel nie ma „pakietu SEO”. Ma **stos SEO**: jeden pakiet zapisuje metadane dla modeli, drugi dodaje pola do Filament, trzeci skanuje strony, a warstwa kodu specyficznego dla aplikacji sprawia, że wszystkie trzy działają zgodnie. Każdy z tych elementów osobno spełnia swoją rolę. Koszt powstaje na styku między nimi, a kod integrujący musisz utrzymywać samodzielnie przez cały czas.

Ta strona przedstawia wyniki jednej rzeczywistej wymiany na produkcji, w której taki zestaw zastąpiono rodziną Rankbeam. Poniższe liczby pochodzą z pomiarów, nie z materiałów marketingowych.

## Aplikacja referencyjna {#the-reference-app}

Rzeczywisty serwis treściowy w Laravel działający na produkcji (tutaj zanonimizowany):

- **Serwis szpitala / instytucji**, działający na produkcji od około 3 miesięcy.
- **Przeniesiony z WordPressa**, około 900 stron według mapy witryny.
- Około **20 000 wizyt dziennie**.
- **Laravel 12**, panel administracyjny **Filament 4**, frontend Blade, MySQL.

Stos SEO przed wymianą:

| Warstwa | Pakiet |
|---|---|
| Przechowywanie metadanych (tabela `seo` dla modeli) | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Pola SEO w Filament | `ralphjsmit/laravel-filament-seo` |
| Skaner stron | `backstage/laravel-seo-scanner` |
| Wszystko pomiędzy | **około 30 własnych klas aplikacji** |

Usunęliśmy trzy pakiety, zainstalowaliśmy Rankbeam **core + Pro + Filament**, uruchomiliśmy zestaw testów SEO i aplikacja wystartowała z **zerową liczbą regresji SEO**. Poniżej pokazujemy rzeczywisty koszt warstwy integracji i to, co można było usunąć.

## Co usunięto po wymianie {#what-the-swap-deleted}

Zastąpienie stosu skanera Rankbeam pozwoliło **całkowicie usunąć 12 własnych klas**. Aplikacja nie musi już realizować ich zadań, ponieważ odpowiedniki zapewnia rodzina pakietów:

| Usunięta klasa aplikacji | Jej zadanie | Obecny odpowiednik |
|---|---|---|
| `Services/SeoService.php` | opakowanie punktu wejścia SEO aplikacji | resolver rdzenia + fasada `SEO` |
| `Services/SeoWarningEvaluator.php` | progi długości tytułu/opisu i wymiarów obrazu | `SEOWarningEvaluator` rdzenia (wspólny dla audytu, podglądu i skanowania) |
| `Services/Seo/SeoAssetInspector.php` | sprawdzanie wymiarów lokalnych obrazów | `LocalImageInspector` rdzenia |
| `Jobs/ScanAllPagesSeo.php` | wysyłanie skanowania całej witryny do kolejki | kolejkowany [proces skanowania](/pl/pro/scan-issues) Pro |
| `Jobs/ScanPageSeo.php` | skanowanie pojedynczej strony | `PageScanner` Pro |
| `Jobs/ScanPublicPageSeo.php` | skanowanie pojedynczej strony publicznej | proces skanowania Pro |
| `Models/SeoScanBatch.php` | ewidencja przebiegów skanowania | `seo_scan_runs` Pro |
| `Filament/Pages/SeoDashboard.php` | panel administracyjny SEO | wtyczka `SeoDashboard` Pro |
| `Filament/Widgets/SeoScanProgressWidget.php` | widżet postępu skanowania | widżety skanowania Pro |
| `Filament/Widgets/SeoTrendChartWidget.php` | widżet trendów skanowania | widżety skanowania Pro |
| `Facades/Seo.php` | fasada aplikacji nad pakietem przechowującym dane | fasada `SEO` rdzenia |
| `Console/Commands/RecoverLegacySeoMetadata.php` | jednorazowe odzyskiwanie metadanych | [importery](/pl/guide/migrate-from-wordpress) rdzenia (`seo:import-from`) |

::: info Pełne rozliczenie pozostałego kodu
Podczas wymiany celowo **zachowano** własnego robota aplikacji sprawdzającego niedziałające linki (około 17 klas: zadanie skanowania, moduł sprawdzający, moduł budujący adresy początkowe, resolver źródeł, dwa modele, dwa typy wyliczeniowe, dwa zdarzenia, zasób Filament i trzy widżety oraz dwa polecenia) i kilka pomocników do metadanych/schematu (`CustomSEO`, `EntitySeoSection`, `DynamicSeoDataResolver`, `SitewideSchema`, `SeoKeywords`). To około **22 dodatkowe klasy**. Nie usunięto ich pierwszego dnia, ponieważ ich odpowiedniki w Rankbeam pojawiły się później: [robot Pro sprawdzający niedziałające linki](/pl/pro/production) zastępuje własnego robota, **wskazanie powiązanego modelu** i **podgląd SERP/kart społecznościowych** w Filament zastępują `CustomSEO`/`EntitySeoSection`, a **graf schematu** rdzenia zastępuje `SitewideSchema`. Przyjęcie całej rodziny sprawia, że utrzymanie tego własnego kodu — łącznie około **trzech tuzinów klas** — staje się zadaniem pakietu, a nie Twoim.
:::

Nie chodzi o to, że któryś z tych pakietów jest zły. Chodzi o *integrację*: kilkanaście klas łączących je tak, aby zmiana metadanych była widoczna w skanerze, panelu i wyrenderowanej sekcji head. To własny kod bez nadrzędnego projektu, bez testów poza Twoimi i bez zgłoszeń błędów od innych użytkowników.

## Porównanie {#side-by-side}

| Możliwość | Zestaw 3 pakietów i kod integrujący | Rodzina Rankbeam |
|---|---|---|
| Przechowywanie metadanych dla modeli | pakiet metadanych | **rdzeń** (`seo_meta`, MIT) |
| Przechowywanie **z uwzględnieniem locale** | zwykle zadanie kodu integrującego | **rdzeń** — kolumna określa locale rekordów `seo_meta` |
| Pola SEO w Filament | pakiet Filament-SEO | **`laravel-seo-filament`** (MIT) |
| Edycja SEO **powiązanego** modelu | własne opakowanie komponentu pola | wbudowany resolver `target:` |
| Podgląd **SERP i kart społecznościowych** na żywo | własny kod Blade/Alpine | wbudowany podgląd redakcyjny z kartami |
| Renderowanie w trybie headless (Inertia / Livewire / JSON) | aplikacja referencyjna używała Blade; inne stosy wymagają integracji | **jeden resolver** → Blade, Inertia, Livewire, JSON ([testy kontraktu](/pl/contributing/rendering-contract)) |
| Skaner stron i problemy według priorytetów | pakiet skanera | [proces skanowania](/pl/pro/scan-issues) **Pro** + `IssueRegistry` |
| Ocena 0–100 | kod integrujący / brak | **Pro** — przejrzyste, [wersjonowane zasady oceny](/pl/pro/scoring) |
| Przekierowania i obsługa błędów 404 | kolejny pakiet / własny kod | **Pro** — menedżer przekierowań i monitor 404 bez adresów IP |
| Robot sprawdzający niedziałające linki | własny kod (aplikacja miała własnego robota) | **Pro** — robot z limitami i możliwością wznowienia |
| **Graf** schematu JSON-LD | builder i własne powiązania `@id` | **rdzeń** — wzajemnie powiązany graf Organization/WebSite/WebPage |
| Mapy witryny XML | pakiet map witryny | **rdzeń** — rejestr map witryny (wykorzystuje `spatie/laravel-sitemap`) |
| Import z WordPress / Yoast / Rank Math | jednorazowe skrypty | **rdzeń** — `seo:import-from` i [procedura migracji](/pl/guide/wordpress-migration-runbook) |
| **Kto utrzymuje integrację** | **Ty** | rodzina pakietów, jedna linia wydań |

## Trzy rzeczy, z którymi kod integrujący radzi sobie słabo {#the-three-things-glue-can-t-do-well}

**1 — Jedna spójna rodzina, jedna linia wydań.** Trzy pakiety mają trzech opiekunów, trzy dzienniki zmian i trzy harmonogramy aktualizacji. Kod integrujący ma kompensować rozbieżności między nimi. Rankbeam core, Pro i Filament są wersjonowane wspólnie, mają jedną [macierz obsługiwanych wersji](#tested-where-it-runs) i udokumentowane [granice aktualizacji](/pl/reference/configuration). O zmianie zachowania dowiadujesz się z jednego miejsca, zamiast odkrywać ją, gdy dwa pakiety działają niezgodnie.

**2 — Locale jako kolumna w danych, a nie umowna konwencja.** `seo_meta` jest polimorficzny **i** uwzględnia locale na poziomie przechowywania danych. SEO dla wielu locale oznacza wiersz dla każdej pary `(model, locale)`, a nie zserializowany blok danych czy dodatkową tabelę integracyjną, o której trzeba było pamiętać. Mechanizm [priorytetów resolvera](/pl/concepts/resolver-precedence) odczytuje aktywne locale natywnie.

**3 — Renderowanie w trybie headless z jednego resolvera.** Rankbeam rozstrzyga typowane `SEOData` i renderuje *te same* dane jako HTML, dane komponentu `Head` Inertia lub tablicę JSON. Potwierdzają to testy wspólnego [kontraktu renderowania](/pl/contributing/rendering-contract) dla [Blade](/pl/guide/blade), [Inertia](/pl/guide/inertia-json) (Vue/React/Svelte) i [Livewire](/pl/guide/livewire). Panel administracyjny nie jest wymagany: każda funkcja Pro działa też [bez interfejsu, z artisan](/pl/pro/headless).

## Czego rzeczywiście *nie ma* w bezpłatnym rdzeniu {#what-is-honestly-not-in-the-free-core}

Rankbeam działa w modelu open-core, a podział jest celowy, aby przed wykonaniem `composer require` było jasne, co otrzymujesz:

| Pakiet | Licencja | Zawartość |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, bezpłatny** | rozstrzyganie metadanych, graf schematu JSON-LD, mapy witryny, bezpłatny `seo:audit`, importery |
| `rankbeam/laravel-seo-filament` | **MIT, bezpłatny** | pola/sekcje formularzy Filament zapisujące dane w `seo_meta` |
| `rankbeam/laravel-seo-pro` | **komercyjna** | skanowanie w kolejce, problemy według priorytetów, ocena 0–100, przekierowania, monitor 404, robot sprawdzający niedziałające linki, Search Console, pomoc AI, panel Filament |

Płatne są więc **audyt technicznego SEO** i zestaw narzędzi do **monitorowania witryny**: skanowanie, ocena, przekierowania, obsługa błędów 404 i robot sprawdzający linki. Silnik metadanych, graf schematu, mapy witryny i bezpłatny audyt wykonywany w procesie aplikacji pozostają bezpłatne na licencji MIT.

Dwie właściwości, które można sprawdzić:

- **Brak sprawdzania licencji podczas działania.** Licencję Pro przypisuje się do projektu przy instalacji. Pakiet nie kontaktuje się z serwerem licencji i nie ma zdalnego wyłącznika, który mógłby unieruchomić aplikację. (Pro emituje *lokalną* telemetrię operacyjną do Twoich logów. Można ją wyłączyć; nigdy nie trafia do nas.)
- **AI z własnym kluczem.** Opcjonalna [pomoc AI](/pl/pro/ai-assist) korzysta z *Twojego* klucza Anthropic, OpenAI, Google lub modelu lokalnego. Nie pośredniczymy w wywołaniach, nie rozliczamy ich zużycia ani ich nie odsprzedajemy. Funkcja jest domyślnie wyłączona.

::: tip Kiedy zestaw osobnych pakietów nadal wystarcza
Jeśli na kilku statycznych stronach potrzebujesz tylko jednego `<title>` i opisu, builder znaczników działający podczas obsługi żądania w zupełności wystarczy. Rankbeam sprawdza się, gdy dane SEO są **przechowywane**, **wielojęzyczne**, **powiązane z modelami**, **niezależne od interfejsu** i **poddawane audytowi** — gdy integracja między pakietami staje się rzeczywistym kodem, który musisz utrzymywać.
:::

## Migracja o najmniejszym ryzyku: z WordPressa {#the-lowest-risk-switch-off-wordpress}

Aplikacja referencyjna powstała w wyniku migracji około 900 stron z WordPressa. To przypadek, w którym można stracić najwięcej: lata optymalizacji w Yoast/Rank Math. Rankbeam traktuje taką migrację jako bezpieczną ścieżkę:

1. **Współistnienie.** Uruchom Rankbeam obok działającej witryny. Na tym etapie niczego nie usuwasz.
2. **Import (najpierw próba bez zapisu).** `seo:import-from yoast` / `rank-math` / `wordpress-csv` odczytuje tytuły, opisy, kanoniczne adresy URL, dyrektywy robots, główne słowa kluczowe i nadpisania dla serwisów społecznościowych. Importery są **idempotentne** i **domyślnie uzupełniają tylko puste pola**. Bez `--overwrite` zachowują już ustawione metadane, a `--dry-run` niczego nie zapisuje.
3. **Przekazanie przekierowań.** Rdzeń generuje wersjonowany plik CSV z przekierowaniami. `seo-pro:redirects-import` w Pro sprawdza każdy wiersz przed zapisem, odrzucając pętle, niebezpieczne adresy docelowe i duplikaty.
4. **Weryfikacja przed usuwaniem.** `seo:audit --strict` stanowi warunek przejścia CI lub przełączenia ruchu: każdy problem powoduje zakończenie z niezerowym kodem. Stara baza WordPressa pozostaje nietknięta, dopóki nie zdecydujesz się jej usunąć.

Pełny proces opisuje [procedura migracji z WordPressa](/pl/guide/wordpress-migration-runbook). Mapowanie poszczególnych pól i obsługę tokenów znajdziesz w [Migracji z WordPressa](/pl/guide/migrate-from-wordpress). Przechodzisz z pakietu SEO dla **Laravel** (ralphjsmit, artesaos, Spatie)? [Zobacz przewodnik migracji z innych pakietów](/pl/guide/migrate-from-other-packages).

## Czy sprawdza się przy większej skali? {#does-it-hold-up-at-scale}

Dwa najtrudniejsze wymagania aplikacji referencyjnej — resolver obsługujący każde z około 20 tys. żądań dziennie i sprawdzanie linków na około 900 stronach — mają osobne benchmarki w zestawie testów. Potwierdzają one **deterministyczne** korzyści (liczbę zapytań i ograniczony zakres zadań), zamiast ręcznie dostrajanych pomiarów czasu wykonania:

**Pamięć podręczna resolvera — trafienie w rozgrzaną pamięć nie odwołuje się do bazy danych.** Po włączeniu opcjonalnej pamięci podręcznej rozstrzygania trafienie w cache pomija *cały* łańcuch priorytetów. Benchmark wykonuje 25 rozstrzygnięć dla tego samego modelu:

| | Zapytania do bazy danych |
|---|---|
| Bez pamięci podręcznej (każde rozstrzygnięcie ponownie odczytuje `seo_meta`) | **≥ 25** |
| Trafienie w rozgrzaną pamięć podręczną | **0** |

Pamięć podręczna jest **domyślnie wyłączona** i opisana jako narzędzie do skalowania. Unieważnianie usuwa odpowiednie wpisy, gdy zmienia się `seo_meta`, pole treści lub ustawienia domyślne. Zobacz [Konfiguracja → pamięć podręczna](/pl/reference/configuration).

**Robot sprawdzający niedziałające linki — ograniczony przebieg obejmujący 900 stron.** Benchmark robota przetwarza wygenerowany zbiór około 900 stron przez rzeczywiste zadanie:

- Kończy pracę w **≥ 18 zadaniach o ograniczonym zakresie** (limit 50 stron na zadanie).
- **Żadne pojedyncze zadanie** nie odwiedza więcej niż przewidziane 50 stron.
- Sprawdza **1800 linków**. Każdy niedziałający adres docelowy daje trwały wpis z potwierdzonym problemem.

Robot ma skończone limity na przebieg i ścisły budżet czasu na zadanie. Sprawdza zagrożenia SSRF przy pobieraniu adresu początkowego **i na każdym etapie przekierowania**. Dzierżawa w bazie danych zapewnia tylko jeden aktywny przebieg dla danego zakresu. Obsługę operacyjną opisuje [przewodnik konfiguracji produkcyjnej](/pl/pro/production).

## Testowany w obsługiwanych środowiskach {#tested-where-it-runs}

Jedna macierz obsługiwanych wersji dla całej rodziny zamiast trzech:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13
- **Filament** 4 / 5

## Po co więc łączyć trzy pakiety własnym kodem? {#so-—-why-glue-three-packages-together}

Gdy integracja zestawu wymaga kilkunastu własnych klas, harmonogram aktualizacji jest poza Twoją kontrolą, renderowanie trzeba integrować osobno dla każdego stosu, a obsługę locale dopisywać ręcznie — spójna rodzina niezależna od interfejsu i natywnie obsługująca locale pozwala usunąć ten kod. Jej działanie potwierdzono w rzeczywistej aplikacji produkcyjnej z 900 stronami i około 20 tys. żądań dziennie. W takich warunkach zestaw osobnych pakietów przestaje być bezpiecznym wyborem domyślnym.

Zacznij od [Szybkiego startu](/pl/guide/quickstart): od `composer require` do w pełni wyrenderowanego `<head>` w pięć minut.
