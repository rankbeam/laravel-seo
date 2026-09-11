---
description: "Przejdź z fibonoir/laravel-seo v1 na rankbeam/laravel-seo v2: nowa nazwa i pakiet podstawowy skupiony na rozstrzyganiu metadanych, renderowaniu, JSON-LD i mapach witryny."
---

# Aktualizacja z fibonoir/laravel-seo v1 {#upgrading-from-fibonoir-laravel-seo-v1}

Wersja v2.0.0 zmienia nazwę pakietu na `rankbeam/laravel-seo` i ogranicza go do konkretnych zadań: rozstrzygania metadanych, renderowania, JSON-LD i map witryny. Analizator, skaner, przekierowania, monitor błędów 404 i interfejs administracyjny przeniesiono do osobnych pakietów.

## 1. Zamień pakiet {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Zaktualizuj przestrzenie nazw {#_2-update-namespaces}

Nazwy klas nie zmieniły się; zmieniła się tylko główna przestrzeń nazw: `Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`. Wystarczy wyszukiwanie i zamiana w całym projekcie. Alias fasady `SEO` i dyrektywy Blade `@seo` pozostają bez zmian.

## 3. Usuń stare opublikowane pliki {#_3-delete-stale-published-files}

Przed usunięciem plików lub tabel wykonaj kopię opublikowanej konfiguracji i wyeksportuj objęte zmianą dane. Sprawdź, czy możesz je przywrócić. Ten przewodnik nie przenosi historii przekierowań, błędów 404 ani skanów z v1 do innego schematu Pro; opisana poniżej zgodność tabel pakietu podstawowego dotyczy wyłącznie `seo_meta` i `seo_defaults`.

::: warning Problem bez komunikatu o błędzie
Polecenie `seo:install` z v1 publikowało w aplikacji pliki, które będą kolidować z pakietem v2 bez ani jednego komunikatu o błędzie.
:::

- **`config/seo.php`** — jeśli plik opublikowała wersja v1 (lub `ralphjsmit/laravel-seo`, który instalator v1 mógł pozostawić), przesłania on konfigurację pakietu i może wyzerować `site_name` oraz każdy szablon `{site_name}`. Usuń go, a następnie opublikuj ponownie: `php artisan vendor:publish --tag=seo-config`.
- **Migracje v1** dla tabel, za które pakiet podstawowy już nie odpowiada: `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache`, `seo_internal_links_index`. Usuń pliki migracji. Jeśli tabele istnieją na produkcji, usuń je **przed** instalacją `rankbeam/laravel-seo-pro` — Pro odtworzy je z innym schematem.
- **Opublikowane pliki szablonowe** w `app/` i `resources/js` z integracji v1 z Filament 3 / Livewire / Vue / React — odwołują się do klas, które już nie istnieją.

Dwie tabele pakietu podstawowego (`seo_meta`, `seo_defaults`) mają zgodny schemat; aktualizacja zachowuje twoje dane.

## 4. Usunięte funkcje i ich nowe miejsce {#_4-removed-features-and-where-they-went}

| Funkcja v1 | Gdzie znajduje się teraz |
|---|---|
| Sekcja SEO formularza Filament | [`rankbeam/laravel-seo-filament`](/pl/guide/filament) (bezpłatna, MIT) |
| Analizator treści (32 reguły) | Ta migracja nie przenosi starego analizatora. Wykrywanie problemów technicznego SEO jest częścią skanera witryny w `rankbeam/laravel-seo-pro`; liczbowa ocena SEO to funkcja Pro wyliczana na podstawie problemów. |
| Skaner całej witryny | `rankbeam/laravel-seo-pro` — proces oparty na kolejce + panel |
| Zarządzanie przekierowaniami | `rankbeam/laravel-seo-pro` — wzmocnione zabezpieczenia (walidacja regex, ochrona przed otwartymi przekierowaniami) |
| Monitor błędów 404 | `rankbeam/laravel-seo-pro` — z naciskiem na prywatność (domyślnie bez zapisu adresów IP) |
| Analityka GA4, linki wewnętrzne | Lista planowanych prac `rankbeam/laravel-seo-pro` |
| Instalator `seo:install` | Usunięty — instalacja obejmuje dodanie zależności, publikację konfiguracji i migracje |

## 5. Zmiany zachowania do sprawdzenia {#_5-behavior-changes-to-review}

- **`og:image` / `twitter:image` zawsze są bezwzględnymi URL-ami.** v1 zwracała ręcznie ustawione ścieżki względne bez zmian.
- **Wyprowadzane adresy kanoniczne usuwają parametry zapytania.** Jawnie ustawione adresy kanoniczne są zachowywane dokładnie.
- **Automatyczne wykrywanie map witryny ustępuje zarejestrowanym źródłom** — nie powstaje już duplikat `sitemap-post.xml` obok zarejestrowanej mapy `sitemap-posts.xml`.
- **JSON-LD używa kodowania `JSON_HEX_*`** — jeśli dalej przetwarzasz surową treść skryptu, spodziewaj się sekwencji kodujących znaki takie jak `<`.

## 6. Znane pułapki {#_6-known-gotchas}

- Domyślny `DatabaseSeeder` w Laravel używa `WithoutModelEvents`, co wyłącza automatyczne tworzenie rekordu przez `HasSEO` w seederach.
- Jeśli domyślny szablon tytułu trasy zawiera już markę, zakończ go skonfigurowanym `title_suffix` — mechanizm rozstrzygania nie dopisze wtedy przyrostka ponownie.
