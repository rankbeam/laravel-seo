---
description: "Raport PDF pod własną marką: ocena, trend problemów, naprawione i nowe problemy, odzyskane ścieżki 404, zmiany w Search Console i aktywność botów AI. Jedno polecenie, opcjonalnie wysyłka według harmonogramu."
---

# Raporty pod własną marką {#white-label-reports}

**Raport PDF** z Twoją marką dla jednej witryny: ogólna ocena, trend wykrytych problemów, **problemy naprawione i nowe od ostatniego raportu**, odzyskane ścieżki 404 i naprawione linki, zmiany w Search Console oraz aktywność botów AI. Generujesz go jednym poleceniem i opcjonalnie **wysyłasz e-mailem według harmonogramu**. Powstał z myślą o agencjach: dodaj logo, kolor i oznaczenie „przygotowano dla {client}”, a następnie przekaż go klientowi.

[Pobierz wygenerowany przykładowy raport po angielsku (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf) lub przejdź przez [skanowanie → poprawkę → raport](/pl/pro/walkthrough). Przykład korzysta z treści demonstracyjnych Merchant z seedera i dwóch nowych skanowań. Pokazuje jeden naprawiony problem, 19 nadal otwartych i brak danych Search Console.

[![Pierwsza strona wygenerowanego raportu demonstracyjnego Merchant.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## Co zawiera {#what-s-in-it}

- **Ogólna ocena** — średnia najnowszych ocen poszczególnych stron (opublikowane [zasady](/pl/pro/scoring): A ≥ 90 … F), zmiana od ostatniego raportu i **trend ogólnej oceny** z ostatnich skanowań. Każde skanowanie zapisuje teraz ocenę witryny w przebiegu, więc trend jest rzeczywistą historią kolejnych skanowań. Zaczyna się od pierwszego skanowania po aktualizacji; starsze przebiegi bez oceny są pomijane.
- **Problemy wykryte w skanowaniu** — rzeczywisty trend z ostatnich zakończonych skanowań; mniej znaczy lepiej.
- **Problemy naprawione i nowe** — ile defektów usunięto i ile pojawiło się od ostatniego raportu. Dane są odczytywane z rzeczywistej historii problemów, które mają teraz [cykl życia](/pl/pro/scan-issues#issue-lifecycle) z naprawianiem i ponownym otwieraniem, gdy obejmuje on cały okres. W przeciwnym razie używana jest migawka poprzedniego raportu.
- **Odzyskane** — rozwiązane problemy niedziałających linków, **odzyskane** ścieżki 404 (ścieżka ponownie sama zwraca 200; zobacz [`seo-pro:404-recheck`](/pl/pro/production#scheduler)) oraz ścieżki 404 **przekierowane** od ostatniego raportu, a także nadal otwarte problemy. Odzyskana ścieżka 404 to faktyczna poprawka u źródła, zliczana osobno od przekierowania.
- **Search Console** — najważniejsze zapytania i strony oraz **zmiany**: największe różnice liczby kliknięć względem poprzedniego raportu. Sekcja jest pomijana, gdy GSC nie jest skonfigurowane.
- **Aktywność botów AI** — żądania przypisane na podstawie user-agenta (bez zweryfikowanej tożsamości bota), sumy od początku rejestracji oraz, gdy [historia dziennych zestawień](/pl/pro/ai-bot-monitor#period-metrics-daily-buckets) obejmuje dany okres, **rzeczywiste trafienia w tym okresie i unikalne URL-e odwiedzone przez każdego bota**. W przeciwnym razie używana jest różnica sum od początku rejestracji względem migawki.

## „Od ostatniego raportu” {#since-the-last-report}

Raport porównuje **okres względem poprzedniego raportu**, a nie dowolnej daty. Przy każdym generowaniu zapisuje lekką migawkę (`seo_report_runs`): ocenę, tożsamości otwartych problemów, wiersze Search Console i licznik trafień każdego bota. Następny raport porównuje bieżący stan z tą migawką.

To rozwiązanie zastępcze dla sygnałów bez własnej historii, takich jak oceny poszczególnych stron, przechowywane tylko w najnowszej wersji. Migawka przy generowaniu raportu pozwala uczciwie je porównać. Kilka sygnałów ma już **rzeczywistą** historię, którą raport preferuje, używając migawki tylko zastępczo: problemy mają [cykl życia](/pl/pro/scan-issues#issue-lifecycle) obejmujący naprawianie i ponowne otwieranie (rzeczywiste liczby naprawionych i nowych po objęciu pełnego okresu), Search Console przechowuje [metryki dzienne](/pl/pro/search-console#historical-metrics), a trafienia botów AI — [dzienne zestawienia](/pl/pro/ai-bot-monitor#period-metrics-daily-buckets) z rzeczywistymi trafieniami i unikalnymi URL-ami w okresie. Każdy z nich wraca do porównania migawek przy pierwszym raporcie po aktualizacji lub gdy historia nie obejmuje całego okresu.

Dwie konsekwencje:

- **Pierwszy raport stanowi punkt odniesienia.** Pokazuje bieżący stan. Liczby „naprawione”, „nowe”, zmiany w Search Console i wartości „od ostatniego raportu” pojawiają się od *drugiego* raportu.
- **Częstotliwość zależy od Ciebie.** Raporty miesięczne porównują miesiące, a tygodniowe — tygodnie. Użyj `--no-store` do doraźnego podglądu, który nie powinien przesuwać punktu odniesienia.

## Wygeneruj raport {#generate-a-report}

```bash
php artisan seo-pro:report
```

Bez opcji zapisuje PDF w `storage/app/seo-reports/`. Możesz wskazać inne miejsce lub wysłać raport e-mailem:

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### Opcje {#options}

| Opcja | Działanie |
|---|---|
| `--client=` | Nadpisuje etykietę klienta „przygotowano dla” |
| `--agency=` | Nadpisuje nazwę agencji w raporcie |
| `--accent=` | Nadpisuje kolor akcentu (hex, np. `#3D5AFE`) |
| `--logo=` | Nadpisuje ścieżkę obrazu logo |
| `--email=` | Adres odbiorcy (można powtórzyć); wysyła raport e-mailem |
| `--send` | Wysyła e-mail do skonfigurowanych odbiorców |
| `--output=` | Zapisuje PDF w tym pliku lub katalogu |
| `--no-store` | Nie zapisuje migawki (punkt odniesienia porównań nie przesuwa się) |
| `--json` | Zwraca podsumowanie do odczytu maszynowego |

## Zaplanuj wysyłkę e-mail {#schedule-the-e-mail}

Pakiet nie planuje wysyłki samodzielnie; Ty ustalasz częstotliwość. W harmonogramie konsoli aplikacji (`routes/console.php` lub `app/Console/Kernel.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Ustaw domyślnych odbiorców raz, w konfiguracji lub `.env`:

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` korzysta z nich domyślnie; jawne opcje `--email` je nadpisują.

## Oznaczenie marką {#branding}

Dane marki nie są sekretami, więc znajdują się w konfiguracji. Ustaw je raz, a każdy raport będzie ich używał. Każde pole można nadpisać dla konkretnego raportu powyższymi opcjami polecenia, co przydaje się, gdy jedna instalacja raportuje dla kilku klientów.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Uwagi:

- **Logo** — bezwzględna ścieżka do pliku `PNG`/`JPG`/`GIF`/`WEBP`/`SVG`. Po odczytaniu przez aplikację obraz jest osadzany w PDF jako data URI, więc renderer nie musi pobierać go przez sieć. Najbezpieczniejsze są `PNG` lub `JPG`.
- **Kolor akcentu** — walidowany jako dosłowny zapis hex; nieprawidłowa wartość powoduje użycie domyślnej. Zawsze występuje jako kolor, nigdy jako surowy CSS.
- **Nazwa agencji** — domyślnie nazwa aplikacji (`config('app.name')`).

Pełny blok konfiguracji znajduje się pod `reports` w `config/seo-pro.php`. Obejmuje `paper` (domyślnie `a4`), `include_gsc` oraz liczbę przebiegów trendu, wierszy GSC i botów uwzględnianych w raporcie.

## Jedna witryna na instalację {#one-site-per-install}

Pro skanuje aplikację, w której jest zainstalowany, więc raport opisuje **tę instalację**. Agencja prowadząca kilka witryn klientów generuje po jednym raporcie na instalację; `--client` i nadpisania marki oznaczają każdy z nich. Nie ma modelu „sites” do obsługi wielu witryn w jednej instalacji.

## Jak powstaje raport {#how-it-s-built}

PDF jest domyślnie renderowany przez **dompdf**: czyste PHP, bez Node ani Chromium działającego bez interfejsu graficznego. Zaplanowany raport może więc powstawać w procesie kolejki lub cron bez systemowych programów wykonywalnych, a Pro pozostaje niezależny od interfejsu. Pobieranie zdalne jest wyłączone w rendererze. Jedyny obraz, czyli logo, jest osadzony, więc żadna wartość renderowanego pola nie może uruchomić pobierania.

### Raporty we wszystkich systemach pisma (renderer Browsershot) {#reports-in-every-script-browsershot-renderer}

Od Core 3.20 / Pro 2.40 renderery Chrome wyłączają JavaScript i blokują żądania zasobów HTTP(S), FTP i WebSocket. Opublikowane szablony muszą używać statycznego HTML/CSS z osadzonymi zasobami. Te zabezpieczenia dotyczą zasobów strony; Chrome nadal wymaga poprawnie skonfigurowanego hosta i piaskownicy. Renderer PDF zapisuje w logu ostrzeżenie z instrukcją instalacji fontu, gdy Fontconfig zgłasza brak obsługi systemu pisma, również takiego, który stanowi niewielką część mieszanego tekstu. Brak fontu nie zatrzymuje generowania PDF w Chrome, więc sprawdź wynik przed wysłaniem raportu.

dompdf rysuje tylko znaki fontu, który osadza: DejaVu Sans obsługuje alfabet łaciński, cyrylicę i grekę. Raport dla klienta japońskiego, tajskiego lub arabskiego wyświetla więc prostokąty zamiast znaków. Od Pro 2.34 raport można renderować przez **Chrome bez interfejsu** za pomocą `spatie/browsershot`. To ta sama zależność, której rdzeń używa do obrazów OG, więc maszynę konfigurujesz raz:

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chrome korzysta z fontów zainstalowanych na serwerze, a szablon używa wtedy stosu fontów rdzenia dla poszczególnych systemów pisma: `Noto Sans`, najpierw rodzina `Noto Sans CJK` języka strony, następnie tajski, arabski, hebrajski, dewanagari, kolorowe emoji i DejaVu Sans dla alfabetu łacińskiego. Zainstaluj potrzebne rodziny — `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji` w Debianie i Ubuntu — tak samo jak dla [obrazów OG](/pl/guide/multilingual#og-images-in-every-script). `seo:og-images` ostrzega podczas działania, gdy na serwerze nie ma rodziny dla systemu pisma strony; rozwiązanie dla raportów jest takie samo. Szablon Blade, dane i migawka są identyczne w obu silnikach. Zmienia się tylko rasteryzator, a `ReportGenerator::renderer()` wskazuje, który jest powiązany.

### Daty i liczby według ustawień regionalnych odbiorcy {#dates-and-numbers-in-the-reader-s-locale}

Raport przechwytuje `seo-pro.reports.locale` w chwili tworzenia. Null oznacza locale aplikacji. Rozstrzygnięty język tłumaczenia określa etykiety PDF i e-maila, domyślny temat, wybór fontów i HTML `lang`. Regionalne locale bez własnego pliku tłumaczenia korzystają z dołączonego języka bazowego, a następnie z angielskiego. Chiński uproszczony (`zh_CN`) i tradycyjny (`zh_TW`) pozostają odrębne.

Daty i liczby są formatowane według żądanego locale przez ICU, gdy zainstalowane jest `ext-intl`. Ustaw `seo-pro.reports.format_locale`, aby celowo wybrać inny format regionalny: `locale=it` i `format_locale=en_US` dają włoskie etykiety z amerykańskim formatowaniem dat i liczb. Bez `ext-intl` pozostaje zastępczy format angielskich dat i liczb z przecinkami jako separatorami grup.

Poczta w kolejce zachowuje przechwycony język, formatowanie i temat nawet po zmianie konfiguracji procesu roboczego. Wybierz język przed wygenerowaniem PDF; późniejsza zmiana locale obiektu mailable nie przetłumaczy załącznika. Stare dane kolejki sprzed Pro 2.39 używają konfiguracji procesu roboczego, bo nie mają przechwyconych ustawień. Własne tematy, dane marki i zapisane komunikaty problemów pozostają danymi źródłowymi.

Wyświetlanie CLI jest osobne: `php artisan seo-pro:report --display-locale=it` tłumaczy podsumowanie polecenia, a konfiguracja raportu wybiera język PDF/e-maila klienta. CLI domyślnie używa angielskiego, co można zmienić przez `SEO_PRO_CLI_LOCALE`. Klucze JSON i kody pozostają stabilne, a etykiety dla ludzi mogą być tłumaczone. Opublikuj `seo-pro-lang`, aby nadpisać komunikaty raportów i procesów w `lang/vendor/seo-pro/{locale}/seo-pro.php`.

W kodzie pobierz `ReportGenerator` z kontenera:

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```

