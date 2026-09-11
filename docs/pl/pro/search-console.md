---
description: "Panel Google Search Console wyłącznie do odczytu: najważniejsze zapytania i strony z wyświetleniami, kliknięciami, CTR i pozycją, połączone ze stronami znanymi skanerowi. Domyślnie wyłączony."
---

# Search Console (tylko odczyt) {#search-console-read-only}

Panel Google Search Console **tylko do odczytu**: najważniejsze zapytania i strony z **wyświetleniami, kliknięciami, CTR i średnią pozycją**, połączone ze stronami, które skaner już zna. Możesz więc zobaczyć w jednym miejscu, że *„ta strona ma problemy **i** traci wyświetlenia”*. Funkcja jest **domyślnie wyłączona**.

Trzy założenia konstrukcyjne:

- **Wyłącznie odczyt.** Integracja żąda jednego zakresu OAuth — `webmasters.readonly` — wpisanego na stałe w pakiecie. Może odczytywać tylko Search Analytics. Nigdy nie zgłasza mapy witryny, nie prosi o indeksowanie i niczego nie zmienia w Search Console. Nie ma opcji konfiguracji rozszerzającej zakres.
- **Twoja usługa, Twoje dane uwierzytelniające.** Żądania idą z *Twojego serwera* bezpośrednio do Google, uwierzytelnione *Twoim* kontem usługi lub danymi OAuth. Nie ma pośrednika, rozliczania zużycia ani odsprzedaży, a pakiet nie wysyła telemetrii.
- **Błędy pozostają w kontekście.** Brak danych uwierzytelniających, 403, błędy limitów lub przekroczenie czasu dają komunikat w sekcji bez przerywania renderowania panelu. Polecenie synchronizacji historii zgłasza błędy i zatrzymuje pobieranie kolejnych dni, jak opisano poniżej.

## Co otrzymujesz {#what-you-get}

- **Strony wymagające uwagi** — najważniejsze połączenie: strony z **otwartymi problemami skanowania**, które **nadal uzyskują ruch z wyszukiwarki**, od największej niewykorzystanej możliwości (najwięcej wyświetleń wśród stron z problemami). Popraw je w pierwszej kolejności.
- **Najważniejsze strony** i **najważniejsze zapytania** — standardowe tabele Search Analytics.

W panelu Filament to strona **Search Console** w grupie nawigacji *SEO*, widoczna tylko przy włączonej integracji. Bez panelu te same metryki udostępniają polecenie `seo-pro:search-console` i `SeoPro::searchConsole()`.

## Konfiguracja {#setup}

Potrzebujesz danych uwierzytelniających Google z prawem odczytu usługi Search Console. Obsługiwane są dwa tryby; na serwerze najprostsze jest **konto usługi**.

### Konto usługi (zalecane) {#service-account-recommended}

1. W Google Cloud włącz **Search Console API**, utwórz **konto usługi** i pobierz jego klucz JSON.
2. W Search Console → *Ustawienia → Użytkownicy i uprawnienia* dodaj adres e-mail konta usługi (`…@….iam.gserviceaccount.com`) jako użytkownika. Uprawnienia ograniczone wystarczają do odczytu.
3. Wskaż pakietowi klucz i usługę:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

Jeśli pominiesz `SEO_PRO_GSC_SITE_URL`, usługa z prefiksem URL zostanie wyprowadzona z `app.url`.

### OAuth (token odświeżania do dostępu offline) {#oauth-offline-refresh-token}

Jeśli masz klienta OAuth i długotrwały **token odświeżania**, najlepiej autoryzowany wyłącznie dla `webmasters.readonly`, skonfiguruj je poniżej. Każde odświeżenie żąda tego zakresu. Pakiet odrzuca zwrócony token, jeśli odpowiedź nie potwierdza jawnie dokładnie zakresu odczytu; nie zakłada, że Google zawsze zawęzi szersze uprawnienie.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Opublikuj migrację tokenów {#publish-the-token-migration}

Zaszyfrowana pamięć podręczna tokenów dostępu znajduje się w tabeli `seo_gsc_tokens`. Opublikuj migrację i wykonaj ją raz:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Następnie sprawdź konfigurację przez `php artisan seo:doctor`. Informuje, czy Search Console jest włączone i skonfigurowane, bez wywołań sieciowych i wypisywania sekretów.

## Korzystanie bez panelu {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## Metryki historyczne {#historical-metrics}

Powyższy panel i polecenie odczytują **bieżący ruchomy przedział czasu**. Jedynym magazynem pozostaje Search Console. Aby mieć **historię z dokładnością do dnia**, którą można odpytywać dla wcześniejszych okresów, uruchom polecenie synchronizacji. Zapisuje dzienne metryki zapytań i stron w tabeli `seo_gsc_metrics`:

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **Pierwsze uruchomienie uzupełnia** `sync.backfill_days` (domyślnie 90; Search Console przechowuje około 16 miesięcy, więc zwiększ tę wartość, aby pobrać więcej). Kolejne **wznawiają od ostatniej zapisanej daty**, ponownie pobierając końcowe `sync.overlap_days`, aby uwzględnić opóźnione finalizowanie najnowszych danych Search Console. Przedział zawsze kończy się 3 dni wcześniej z powodu opóźnienia danych.
- **Idempotentna.** Wiersze są tworzone lub aktualizowane według `(date, dimension, key)`, więc ponowne uruchomienie jest bezpieczne. Błąd danego dnia, np. przekroczenie limitu, zatrzymuje przebieg w kontrolowany sposób i podaje liczbę zapisanych wierszy. Następny przebieg wznawia od miejsca przerwania.
- **Co zasila.** **Zmiany** Search Console w [raporcie pod własną marką](/pl/pro/reports) przechodzą na rzeczywistą historię okres do okresu (bieżący okres względem równie długiego poprzedniego), gdy tabela obejmuje oba, zamiast porównywać z migawką poprzedniego raportu. To również podstawa szerszej analizy słów kluczowych.

Zapisywane są wyłącznie zagregowane metryki: tekst zapytania, URL strony i cztery wartości dzienne — kliknięcia, wyświetlenia, CTR i pozycja. Dane pojedynczych użytkowników lub żądań nigdy nie są pobierane ani zapisywane.

## Przetwarzanie danych i bezpieczeństwo {#data-handling-security}

- **Sprawdzany zakres tylko do odczytu.** JWT konta usługi żąda tylko `webmasters.readonly`. To samo robią odświeżenia OAuth, a pakiet odrzuca odpowiedź z brakującym lub szerszym zakresem. Używaj danych uwierzytelniających autoryzowanych wyłącznie do odczytu. Pakiet nie zawiera wywołań zmieniających Search Console.
- **Dane uwierzytelniające pozostają w środowisku.** Klucz konta usługi lub sekret OAuth i token odświeżania są odczytywane z **nazwanych** zmiennych środowiskowych w chwili wywołania, tak jak klucz AI. Dzięki temu `php artisan config:cache` nigdy nie zapisuje ich w `bootstrap/cache/config.php`. Udostępnij je w środowisku procesu, gdy pamięć podręczna konfiguracji uniemożliwia ładowanie `.env`.
- **Tokeny są szyfrowane w spoczynku.** Krótkotrwały token dostępu uzyskany z Twoich danych uwierzytelniających jest przechowywany **w postaci zaszyfrowanej** kluczem aplikacji w `seo_gsc_tokens` i używany do chwili zbliżającego się wygaśnięcia. Wymiana tokenu nie odbywa się więc przy każdym wyświetleniu. Długotrwałe dane uwierzytelniające nigdy nie trafiają do bazy, tylko do środowiska.
- **Każde żądanie jest chronione przed SSRF.** Zarówno wymiana tokenu, jak i wywołanie Search Analytics przechodzą przez wspólny `SsrfGuard`: tylko HTTPS, host musi rozstrzygać się na publiczny adres, przekierowania wyłączone. Żądanie nie może więc zostać przekierowane do usługi wewnętrznej.
- **Sekrety nie trafiają do logów.** Tokeny dostępu, klucze i nagłówki uwierzytelniające nigdy nie są zapisywane w logach. Błąd API pokazuje tylko oczyszczony komunikat Google o ograniczonej długości.
- **Metryki są lokalnie buforowane** przez `seo-pro.search_console.cache_ttl` sekund (domyślnie 30 minut), więc panel nie odpytuje API przy każdym renderowaniu. Bieżący panel/polecenie nie utrwala niczego poza tą pamięcią podręczną i zaszyfrowanym tokenem dostępu. Tylko opcjonalnie uruchamiane `seo-pro:gsc-sync` zapisuje metryki trwale: zagregowane dzienne wartości zapytań/stron w `seo_gsc_metrics`, bez danych użytkowników.

## Ustawienia konfiguracji {#configuration-reference}

Wszystkie klucze znajdują się pod `config/seo-pro.php` → `search_console`:

| Klucz | Wartość domyślna | Przeznaczenie |
|---|---|---|
| `enabled` | `false` | Główny przełącznik (`SEO_PRO_GSC_ENABLED`). |
| `connection` | `service_account` | `service_account` lub `oauth`. |
| `site_url` | wyprowadzona z `app.url` | Usługa (`https://example.com/` lub `sc-domain:example.com`). |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Nazwa** zmiennej środowiskowej z kluczem JSON lub jego ścieżką. |
| `oauth.client_id` | — | Identyfikator klienta OAuth (nie jest sekretem). |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Nazwa** zmiennej środowiskowej z sekretem klienta. |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Nazwa** zmiennej środowiskowej z tokenem odświeżania. |
| `default_days` | `28` | Przedział raportowania (kończy się 3 dni wcześniej z powodu opóźnienia danych GSC). |
| `row_limit` | `100` | Pierwsze N wierszy raportu (maksimum API 25000). |
| `cache_ttl` | `1800` | Sekundy przechowywania pobranego raportu w pamięci podręcznej. |
| `sync.backfill_days` | `90` | Dni pobierane przy pierwszym `gsc-sync`, gdy tabela jest pusta. |
| `sync.overlap_days` | `2` | Końcowe dni pobierane ponownie przy każdym przebiegu (opóźniona finalizacja). |
| `sync.row_limit` | `5000` | Maksymalna liczba wierszy na dzień i wymiar żądana przez synchronizację. |
