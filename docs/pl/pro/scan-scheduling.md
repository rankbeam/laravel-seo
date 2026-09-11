---
description: "Uruchamiaj pełne skanowanie SEO według harmonogramu i sprawdzaj zmiany od poprzedniego przebiegu: nowe, powracające i naprawione problemy, uporządkowane według wpływu, w panelu i opcjonalnej wiadomości e-mail."
---

# Harmonogram skanowania i zmiany między przebiegami {#scan-scheduling-delta}

Uruchamiaj pełne skanowanie SEO **według harmonogramu** i sprawdzaj, **co zmieniło się od poprzedniego skanowania**: które problemy pojawiły się, powróciły lub zostały naprawione. Wyniki są uporządkowane według wpływu i dostępne w panelu oraz opcjonalnie w podsumowaniu e-mail.

Dwie funkcje opisujemy na jednej stronie, ponieważ współpracują ze sobą: to zestawienie zmian sprawia, że warto otrzymywać wyniki zaplanowanego skanowania.

## Co zmieniło się od poprzedniego skanowania {#what-changed-since-the-last-scan}

Każde zakończone skanowanie utrwala **zbiór otwartych problemów** w lekkiej migawce (`seo_scan_run_issues`). Porównanie migawek dwóch przebiegów daje dokładne zestawienie trzech kategorii:

- **Nowe** — problemy, które wcześniej nie były otwarte, a teraz są, i nigdy nie były otwarte w żadnym wcześniejszym skanowaniu: wykryte po raz pierwszy.
- **Powracające** — problemy, które zostały naprawione, ale **wróciły**. Nie chodzi o wzrost ważności: jest ona stała dla danego typu problemu, więc regresją jest jego powrót, czyli ponowne otwarcie w [cyklu życia](/pl/pro/scan-issues#issue-lifecycle).
- **Naprawione** — problemy otwarte we wcześniejszym skanowaniu, które obecnie już nie występują.

Każda kategoria jest [uporządkowana według wpływu](#impact-ordering), aby lista zaczynała się od najważniejszych pozycji.

### Dlaczego migawka zamiast tabeli problemów {#why-a-snapshot-not-the-issues-table}

Problemy mają [cykl życia](/pl/pro/scan-issues#issue-lifecycle) obejmujący naprawianie i ponowne otwieranie. Ten sam wiersz jest aktualizowany między skanowaniami; jego `scan_run_id` za każdym razem wskazuje najnowszy przebieg, w którym problem nadal jest otwarty. To dobre rozwiązanie dla trwałej historii problemu, ale bieżąca tabela nie powie, *które problemy były otwarte na koniec przebiegu N*. Utrzymujący się problem wskazuje tylko ostatni przebieg.

Dlatego każdy przebieg zapisuje migawkę zbioru otwartych problemów z kluczem będącym stabilnym **odciskiem problemu**: `issue_type | target | field`. Tej samej tożsamości używa porównanie [raportu pod własną marką](/pl/pro/reports). Zestawienie zmian jest wtedy prostą operacją na dwóch utrwalonych zbiorach odcisków, poprawną dla **dowolnych** dwóch przebiegów, nie tylko kolejnych.

### Rzetelna obsługa przypadków brzegowych {#edge-cases-handled-honestly}

- **Strona wypada ze zbioru skanowania.** Jej otwarte problemy nie są ponownie sprawdzane, więc pozostają otwarte i trafiają do migawki każdego przebiegu. Są pokazywane jako **nadal otwarte**, nigdy fałszywie jako „naprawione”. Nie uznajemy strony za naprawioną tylko dlatego, że przestaliśmy ją sprawdzać.
- **Kontrola zostaje wyłączona między skanowaniami.** Jej problemy przestają być emitowane, cykl życia oznacza je jako naprawione i opuszczają zbiór otwartych. Są więc pokazane jako **naprawione**. To odzwierciedla bieżący wynik skanera; na poziomie problemu nie da się odróżnić naprawy od wyłączenia kontroli.
- **Pierwsze skanowanie po aktualizacji.** Przebiegi sprzed wprowadzenia tej funkcji nie mają migawki, więc nigdy nie są wybierane jako punkt odniesienia. Pierwsze skanowanie z migawką staje się **punktem odniesienia** (bieżący stan bez zestawienia zmian), zamiast pokazywać całą witrynę jako „nową”. Zmiany są dostępne od drugiego skanowania z migawką.

### W panelu {#on-the-dashboard}

Widżet **„Co się zmieniło od ostatniego skanu”** w [panelu SEO](/pl/pro/installation) pokazuje liczbę nowych, powracających i naprawionych problemów oraz najważniejsze pozycje każdej kategorii według wpływu, porównując dwa ostatnie zakończone skanowania. Dopóki nie ma dwóch migawek, pokazuje krótką informację o punkcie odniesienia.

## Kolejność według wpływu {#impact-ordering}

Każda kategoria zmian jest sortowana według **wpływu**, aby największe problemy znalazły się na początku:

```
impact = severity_weight × page_importance
```

- **severity_weight** wykorzystuje opublikowane [zasady oceny](/pl/pro/scoring): problem krytyczny ma wagę `40`, ostrzeżenie `15`, a uwaga `5`. Ważność wyraża przyjętą w produkcie ocenę znaczenia defektu. Kolejność korzysta z niej, zamiast wprowadzać drugą skalę.
- **page_importance** wynika z **rzeczywistego zainteresowania w wyszukiwarce**, czyli liczby wyświetleń strony w [Search Console](/pl/pro/search-console). Ten sygnał faktycznie różnicuje strony:

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  Wyświetlenia są skalowane logarytmicznie (strona z 10× większym ruchem nie jest 10× ważniejsza) i normalizowane względem najpopularniejszej strony. Wzór zachowuje się więc tak samo w małym blogu i dużym katalogu. `<priority>` mapy witryny jest tylko **słabym sygnałem pomocniczym**: domyślnie nieustawionym, a po ustawieniu o stałej wartości, więc nie może być główną podstawą kolejności. Wpływa na nią nieznacznie, gdy skonfigurujesz priorytety typów w `seo.sitemap.models`.

**Brak Search Console nie jest przeszkodą.** Bez zsynchronizowanej historii GSC i skonfigurowanych priorytetów `page_importance` wynosi `1` dla każdej strony, a wpływ oznacza wyłącznie **kolejność według ważności** — rozsądną wartość domyślną, bez wymyślonych danych. Zsynchronizuj [historię GSC](/pl/pro/search-console#historical-metrics) (`seo-pro:gsc-sync`), aby włączyć ważenie według zainteresowania.

Wagi i zakres czasu dostosujesz w `seo-pro.scan.delta.impact`.

## Planowanie skanowania {#scheduling-a-scan}

Pakiet **domyślnie niczego nie planuje**. Włącz harmonogram:

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

Albo ustaw pełne wyrażenie cron, aby mieć pełną kontrolę. Ma ono pierwszeństwo przed `frequency`:

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

To wszystko. Pakiet rejestruje `seo-pro:scan` w harmonogramie Laravel z `withoutOverlapping`, aby zapobiegać nakładaniu się wykonań zaplanowanego **polecenia**. Ta blokada harmonogramu nie obejmuje całego czasu życia zadań w kolejce. Rejestracja odbywa się tylko w kontekście harmonogramu/konsoli, więc **nie obciąża żądań WWW**.

::: warning Wymagany działający harmonogram
Planowanie pakietu nie działa, dopóki nie uruchomisz harmonogramu Laravel przez standardowy jednoliniowy wpis cron (`* * * * * php artisan schedule:run`) lub `php artisan
schedule:work` w środowisku deweloperskim. Zobacz [konfigurację produkcyjną](/pl/pro/production#scheduler).
:::

Wolisz skonfigurować go samodzielnie? Pozostaw `schedule.enabled` wyłączone i zaplanuj polecenie we własnym jądrze konsoli. Zestawienie zmian i podsumowanie nadal będą działać:

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` wykonuje skanowanie bezpośrednio zamiast dodawać jedno zadanie na cel do kolejki. To rozwiązanie dla małej witryny bez procesu kolejki; na produkcji pozostaw je wyłączone.

## Podsumowanie e-mail {#summary-e-mail}

Włącz wiadomość **„co zmieniło się od poprzedniego skanowania”** wysyłaną po zakończeniu zaplanowanego przebiegu: podsumowanie HTML z Twoją marką, zawierające nowe, powracające i naprawione problemy według wpływu:

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

Wykorzystuje oznaczenie marką i ustawienia poczty [raportów pod własną marką](/pl/pro/reports), więc zachowuje nazwę agencji, logo i kolor akcentu. Jeśli nie ustawisz osobnych odbiorców, używa odbiorców raportów. `only_on_change` pomija wiadomość, gdy skanowanie niczego nie zmieniło; pierwsze skanowanie bazowe jest zawsze wysyłane.

Podsumowanie jest wysyłane tylko dla przebiegu uruchomionego z `--notify`, które harmonogram dodaje automatycznie przy włączonym `notify.enabled`. Doraźne `seo-pro:scan` **bez `--notify`** nie wysyła nikomu wiadomości.

::: tip Inny kanał?
Wolisz Slack, webhook lub własne podsumowanie zamiast e-maila? Subskrybuj zdarzenie `Rankbeam\Seo\Pro\Events\SeoScanCompleted`. Jest emitowane raz na zakończony przebieg i przekazuje ten przebieg, więc możesz zbudować zestawienie zmian za pomocą `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` i wysłać je dowolnym kanałem.
:::

## Retencja {#retention}

Migawki są usuwane kaskadowo wraz z przebiegiem, więc [`seo-pro:scan-prune`](/pl/pro/production#scheduler) automatycznie usuwa przeterminowane. Nie trzeba planować niczego nowego. Przebieg jest usuwany dopiero wtedy, gdy nie ma otwartych problemów, więc migawka niedawnego przebiegu jest zawsze dostępna do porównania.

Całkowicie wyłącz zapisywanie migawek (bez zestawienia zmian i podsumowania) przez `seo-pro.scan.delta.snapshot => false`.
