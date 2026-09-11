---
description: "Powiadamiaj wyszukiwarki o publikacji lub aktualizacji URL-a. Pro wysyła zgłoszenia do wspólnego punktu api.indexnow.org, który przekazuje je uczestniczącym wyszukiwarkom. Domyślnie wyłączone."
---

# IndexNow — zgłaszanie do indeksowania przy publikacji {#indexnow-—-push-on-publish-indexing}

Zamiast czekać, aż robot znajdzie zmienioną stronę, **IndexNow** pozwala *powiadomić* wyszukiwarki w chwili publikacji lub aktualizacji URL-a. Pro wysyła zgłoszenie do wspólnego punktu `api.indexnow.org`, który **przekazuje je wszystkim uczestniczącym wyszukiwarkom** w jednym wywołaniu, bez osobnej wysyłki do każdej z nich. [Oficjalne FAQ](https://www.indexnow.org/faq) wymienia Amazon, Bing, Naver, Seznam, Yandex i Yep. Powiadomienie nie gwarantuje indeksowania.

Funkcja jest **domyślnie wyłączona**. Nie ma żadnego ruchu sieciowego, dopóki jej nie włączysz i nie zgłosisz URL-a.

## Konfiguracja {#setup}

### 1. Wygeneruj klucz {#_1-generate-a-key}

IndexNow używa **klucza** do weryfikacji kontroli nad hostem. Pro przyjmuje 8–128 znaków z `[a-f0-9-]`; dobrym wyborem jest 32-znakowy ciąg szesnastkowy. Wygeneruj go raz, zachowaj stałą wartość i udostępnij przez środowisko:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip Klucz jest odczytywany przez konfigurację, więc działa po `config:cache`
W przeciwieństwie do danych uwierzytelniających Search Console klucz IndexNow **nie jest sekretem**. Jest publicznie udostępniany pod `/{key}.txt`, aby potwierdzić kontrolę nad hostem. Dlatego Pro rozstrzyga go przez warstwę konfiguracji (`indexnow.key`, domyślnie `env('SEO_PRO_INDEXNOW_KEY')`). To celowe: wartości zdefiniowane **tylko w `.env`** nie są dostępne dla `env()` po `config:cache`, ponieważ Laravel przestaje ładować ten plik. Rzeczywiste zmienne środowiska procesu pozostają dostępne. Odczyt przez konfigurację przechwytuje klucz w `config:cache` i zapewnia jego dostępność. Kompromis: **zmiana klucza wymaga ponownego `php artisan config:cache`.** Klucz nigdy nie trafia do logów. Jeśli plik klucza zwraca 404 na produkcji, zobacz [serwery z pamięcią podręczną konfiguracji](#config-cached-servers).
:::

### 2. Udostępnij plik klucza {#_2-serve-the-key-file}

IndexNow pobiera `https://{host}/{key}.txt`, zawierający wyłącznie klucz, aby zweryfikować własność. Gdy opcja `route` jest włączona (domyślnie), **Pro udostępnia go za Ciebie**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Działa tylko jedna skonfigurowana ścieżka klucza. Pozostałe ścieżki przechwycone przez tę trasę zwracają 404, a po wyłączeniu IndexNow cała trasa zwraca 404. Wolisz hostować plik samodzielnie lub w CDN? Wyłącz `route` i ustaw `key_location` na swój URL.

## Zgłaszanie URL-i {#submitting-urls}

### Automatycznie przy zapisie — ścieżka publikacji {#automatically-on-save-the-push-on-publish-path}

Dodaj trait do modelu i włącz `auto_submit`. Każdy zapis dodaje do kolejki zgłoszenie `getUrlForSEO()` modelu:

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

Trait respektuje warunek publikacji. Zaimplementuj `shouldSubmitToIndexNow(): bool`, aby mieć pełną kontrolę; w przeciwnym razie używany jest atrybut `is_published`, a jeśli go brak — zgłoszenie następuje przy każdym zapisie. Zgłoszenia zawsze trafiają **do kolejki**, więc zapis modelu nigdy nie czeka na sieć.

### Ręcznie {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` domyślnie dodaje do kolejki. Przekaż `queue: false`, aby wykonać zgłoszenie bezpośrednio.

### Z wiersza poleceń {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Tylko ten sam host
Każdy URL jest sprawdzany pod kątem `http(s)` **i** przynależności do skonfigurowanego `host`. Pozostałe są **odrzucane**: zliczane, ale nigdy niewysyłane. Możesz zgłaszać tylko własne URL-e, a punkt końcowy i tak odrzuciłby niezgodny host. Listy większe niż `max_urls_per_request` (10000, limit protokołu) są automatycznie dzielone na partie.
:::

## Konfiguracja {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## Jak działają ponowienia {#how-retries-work}

Kolejkowany `SubmitToIndexNowJob` ponawia tylko sytuacje, które *warto* ponowić: `429` (limit żądań), `5xx` lub przekroczenie czasu są ponawiane z `backoff` do `tries` razy. `400`/`403`/`422` (trwały błąd klienta, np. błędny klucz lub niezgodny host) trafiają do logu i **kończą** zadanie zamiast marnować ponowienia. Zarówno `200`, jak i `202` (przyjęto / oczekuje na sprawdzenie klucza) oznaczają sukces.

Na produkcji przydziel zadaniu **osobną kolejkę**, aby wolny punkt końcowy nie opóźniał pracy obsługującej użytkowników:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Rozwiązywanie problemów {#troubleshooting}

### Serwery z pamięcią podręczną konfiguracji {#config-cached-servers}

Jeśli `/{key}.txt` zwraca 404 na produkcji lub zgłoszenia nic nie robią mimo ustawienia `indexnow.enabled` na `true`, przyczyną jest prawie zawsze klucz istniejący **tylko w `.env`** na serwerze używającym `php artisan config:cache`. Po zapisaniu konfiguracji w pamięci podręcznej Laravel nie odczytuje `.env`, więc `env('SEO_PRO_INDEXNOW_KEY')` zwraca `null`, trasa pliku klucza nie rejestruje się, a każde zgłoszenie jest odrzucane jako nieskonfigurowane.

Domyślna konfiguracja rozstrzyga `indexnow.key` z `env(...)`, więc standardowe ustawienie zostaje przechwycone przy budowaniu pamięci podręcznej i działa poprawnie. Problem pojawia się tylko wtedy, gdy **opublikowano konfigurację i usunięto domyślne `env(...)`** albo ustawiono klucz pod **własną nazwą `key_env` istniejącą tylko w `.env`**. Są dwa rozwiązania:

1. **Zachowaj klucz w konfiguracji** (zalecane). Pozostaw `indexnow.key` jako `env('SEO_PRO_INDEXNOW_KEY')` lub ustaw wartość dosłowną, a następnie ponownie uruchom `php artisan config:cache`. Późniejsza zmiana klucza wymaga ponownego zapisania konfiguracji w pamięci podręcznej.
2. **Wstrzyknij rzeczywistą zmienną środowiskową**. Ustaw `SEO_PRO_INDEXNOW_KEY` jako zmienną systemu/procesu: w puli PHP-FPM `env[...]`, systemd `Environment=` lub ustawieniach platformy, **nie tylko w `.env`**. Zmienne systemu są dostępne również przy konfiguracji w pamięci podręcznej.

Uruchom `php artisan seo:doctor`, aby potwierdzić stan. Po wykryciu problemu zgłasza **„IndexNow is enabled but no valid key resolves”** z dokładnym rozwiązaniem. Pro zapisuje też ostrzeżenie raz na proces, gdy aplikacja startuje z konfiguracją w pamięci podręcznej i nieczytelnym kluczem.

::: tip Google
Google **nie** uczestniczy w IndexNow. Dla Google używaj integracji [Search Console](/pl/pro/search-console) i aktualnej mapy witryny.
:::
