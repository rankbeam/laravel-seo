---
description: "Zainstaluj laravel-seo-pro: skanowanie witryny w kolejce ze śledzeniem problemów, menedżer przekierowań i monitor 404 uzupełniające rdzeń. Działa w każdej aplikacji Laravel 11–13; Filament jest opcjonalny."
---

# Instalacja Pro {#installing-pro}

`rankbeam/laravel-seo-pro` uzupełnia pakiet podstawowy o skanowanie witryny w kolejce ze śledzeniem problemów, menedżer przekierowań i monitor 404. Silnik działa w **każdej aplikacji Laravel 11–13** — Blade, Inertia lub samym API. Filament to opcjonalna warstwa interfejsu: po jego instalacji otrzymujesz panel SEO, menedżer przekierowań i monitor 404 jako strony panelu. Bez niego wszystkim zarządzasz za pomocą [poleceń artisan](/pl/pro/headless).

## Wymagania {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13) |
| Laravel | 11, 12 lub 13 |
| `rankbeam/laravel-seo` | ^3.20 (instalowany automatycznie przez Pro 2.40+) |
| `filament/filament` | **opcjonalny** — 4.x lub 5.x, tylko dla interfejsu administracyjnego |
| `rankbeam/laravel-seo-filament` | **opcjonalny** — ^1.11 przy korzystaniu z edytora SEO z Pro 2.36+ |

Zacznij od istniejącej aplikacji Laravel ze skonfigurowaną bazą danych. Najpierw wykonaj [Szybki start rdzenia](/pl/guide/quickstart), aby model renderował metadane i istniały tabele rdzenia. Licencja Pro zapewnia opisane poniżej dane uwierzytelniające Composer.

Przykład efektu znajdziesz w przewodniku [skanowanie → poprawka → raport](/pl/pro/walkthrough).

## Zainstaluj pakiet {#install-the-package}

Pro jest dystrybuowany przez prywatne repozytorium Composer powiązane z Twoją licencją. Dodaj repozytorium raz, a następnie zainstaluj pakiet. Composer poprosi o adres e-mail licencji jako nazwę użytkownika i klucz licencji jako hasło:

Lemon Squeezy obsługuje płatność jako formalny sprzedawca (merchant of record). Po zapłacie prywatna strona potwierdzenia zakupu udostępnia klucz pobierania i instrukcje Composer. Jako nazwy użytkownika użyj adresu e-mail podanego przy zakupie. Repozytorium pakietu jest hostowane przez Rankbeam; konto Anystack nie jest potrzebne. Zachowaj link do potwierdzenia zakupu i `auth.json` w tajemnicy. Pełny zwrot płatności odbiera dostęp do przyszłych pobrań i aktualizacji, ale nie przerywa działania zainstalowanej aplikacji.

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details Nieinteraktywne uwierzytelnianie Composer
W CI lub środowiskach nieinteraktywnych zapisz dane uwierzytelniające wcześniej:

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

Następnie uruchom instalator:

```bash
php artisan seo-pro:install
```

Instalator publikuje `config/seo-pro.php` i migracje Pro, uruchamia `migrate` oraz wypisuje kolejne kroki. Tabele rdzenia i Pro powinny teraz znajdować się w bazie danych aplikacji.

::: details Instalacja ręczna i opcje instalatora
Migracje Pro są publikowane w aplikacji; pakiet nie ładuje ich automatycznie. Odpowiadają temu następujące kroki ręczne:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Instalator można uruchamiać ponownie. `--no-migrate` publikuje pliki bez wykonywania migracji. Używaj `--force` tylko wtedy, gdy chcesz nadpisać opublikowane pliki, w tym swoją konfigurację.
:::

## Zarejestruj cele skanowania {#register-scan-targets}

W dostawcy usług wskaż, co skaner ma sprawdzać: klasy modeli, nazwane trasy lub wszystko z [rejestru map witryny](/pl/guide/sitemaps):

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

Zastąp `Post` własnym modelem korzystającym z `HasSEO`. Aby zobaczyć wynik skanowania modelu, potrzebujesz co najmniej jednego rekordu. Cele tras muszą wskazywać nazwy istniejących tras. Pomiń tę rejestrację, jeśli chcesz skanować tylko modele.

## Sprawdź instalację {#verify-your-install}

Uruchom kontrolę konfiguracji:

```bash
php artisan seo:doctor
```

Sprawdź, czy tabele rdzenia i Pro istnieją, URL aplikacji jest poprawny, a cele skanowania są wymienione. Wykonaj zalecane poprawki. Ostrzeżenie o kolejce `sync` jest spodziewane podczas wypróbowywania poniższych poleceń wykonywanych bezpośrednio. Przed zaplanowaniem skanowania produkcyjnego skonfiguruj proces obsługujący kolejkę.

::: details Przykładowy wynik kontroli stanu
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor` sprawdza konfigurację i historię ostatnich przebiegów bez wywołań sieciowych i wypisywania sekretów. Nie potwierdza, że zewnętrzny cron lub proces kolejki rzeczywiście działa. Krytyczne błędy powodują niezerowy kod zakończenia; ostrzeżenia nie. Użyj `--json`, aby otrzymać wynik do odczytu maszynowego.
:::

## Uruchom pierwsze skanowanie {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

Pierwsze polecenie wykonuje skanowanie bezpośrednio, więc ta początkowa kontrola nie wymaga procesu kolejki. Drugie pokazuje ostatni przebieg i jego wyniki. Oczekiwany wynik to zakończony przebieg z przetworzonymi zarejestrowanymi celami. Zanim uznasz skanowanie za kompletne, wyjaśnij wszystkie nieudane cele.

Popraw jedno zgłoszone pole, zapisz je i ponownie uruchom skanowanie. [Przewodnik krok po kroku](/pl/pro/walkthrough) pokazuje ten proces na brakującym opisie i raporcie ze zmiany. [Ocena techniczna](/pl/pro/scoring) jest wynikiem diagnostycznym, a nie prognozą pozycji w wyszukiwarce.

## Użycie bez panelu {#path-b-headless}

Silnik jest gotowy do użycia bez panelu. [Polecenia artisan](/pl/pro/headless) pozwalają skanować, przeglądać problemy, tworzyć przekierowania i generować raporty. Middleware przekierowań i 404 domyślnie rejestrują się automatycznie. Ich ustawienia znajdują się w `config/seo-pro.php`.

Aby zaplanować pracę, przejdź do [konfiguracji produkcyjnej](/pl/pro/production) i ustaw kolejki, procesy robocze, harmonogram oraz retencję.

## Dodaj panel Filament (opcjonalnie) {#path-a-with-a-filament-panel}

W istniejącym panelu Filament 4 lub 5 zarejestruj poniższą wtyczkę Pro. Jeśli aplikacja nie ma jeszcze panelu, najpierw zainstaluj pakiety interfejsu i utwórz panel:

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

Dodaje to **panel SEO** (akcję skanowania wszystkich celów, postęp na żywo, listę problemów i ponowne skanowanie jednym kliknięciem), **menedżer przekierowań** oraz **monitor 404** z akcją *Utwórz przekierowanie* wykonywaną jednym kliknięciem. `rankbeam/laravel-seo-filament` udostępnia dodatkowo [sekcję pól SEO](/pl/guide/filament) w formularzach zasobów.

## Rozwiązywanie problemów {#troubleshooting}

| Wynik | Kolejny krok |
|---|---|
| Composer odrzuca dane uwierzytelniające | Sprawdź adres e-mail licencji i klucz dla `blog.rankbeam.dev`. Nie zapisuj danych uwierzytelniających w kontroli wersji. |
| Doctor zgłasza brak tabel | Wykonaj Szybki start rdzenia, a następnie uruchom `seo-pro:install` i `migrate` na tej samej bazie danych, z której korzysta aplikacja. |
| Skanowanie nie przetwarza żadnych celów | Sprawdź rejestrację w dostawcy usług i czy model zawiera rekordy. |
| Skanowanie w kolejce pozostaje oczekujące | Uruchom skonfigurowany proces kolejki lub użyj `--sync` do kontroli wykonywanej bezpośrednio. |
| Cel kończy się niepowodzeniem | Przed ponownym skanowaniem sprawdź szczegóły przebiegu, nazwy tras i URL aplikacji. |
| Brakuje panelu SEO | Zarejestruj `SeoProPlugin` w rzeczywiście używanym panelu i sprawdź bramki dostępu. |

Odzyskiwanie po awarii procesu kolejki i bieżącą obsługę opisuje [konfiguracja produkcyjna](/pl/pro/production).

## Licencja i zwroty {#license}

Licencja dla pierwszych klientów kosztuje jednorazowo 179 € i obejmuje do pięciu projektów produkcyjnych, w tym projekty klientów, oraz dożywotnie aktualizacje. Kopie deweloperskie i stagingowe tych projektów nie są liczone osobno. Cena obejmuje pomoc przy instalacji/migracji, 60-minutową rozmowę dotyczącą instalacji i reklamowany pakiet startowy. Możesz poprosić o bezwarunkowy pełny zwrot w ciągu 30 dni przez stronę potwierdzenia zakupu lub pisząc na valentinogoxhaj@gmail.com. Po pełnym zwrocie przestań korzystać z Pro. Możesz modyfikować Pro na potrzeby projektów objętych licencją, ale nie możesz publikować jego kodu źródłowego ani odsprzedawać go jako osobnego pakietu lub startera. Pełne warunki licencji znajdują się w pakiecie.

Używaj Pro w maksymalnie pięciu projektach produkcyjnych, również dla klientów. Kopie deweloperskie, stagingowe i testowe tych projektów nie są liczone osobno. Dożywotnie aktualizacje obejmują przyszłe wydania Pro; nie obejmują stałych, indywidualnych prac wdrożeniowych.

W cenie zawarta jest jedna 60-minutowa rozmowa dotycząca instalacji i konfiguracji oraz migracja metadanych dla jednego początkowego projektu. Migracja obejmuje obsługiwane źródła. Zakres potwierdzamy przed rozpoczęciem, a niestandardowe zmiany w aplikacji wyceniamy osobno. Konfiguracja na start obejmuje przegląd i ustawienie llms.txt, reguł robotów AI w robots.txt oraz odpowiedzi Markdown dla botów w tym samym projekcie, z użyciem funkcji dostępnych w bezpłatnym Core. Aby umówić pomoc objętą ceną, napisz na hello@rankbeam.dev.

Do Twojego zamówienia stosuje się oferta widoczna w chwili zakupu.
