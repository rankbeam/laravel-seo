---
description: "Oznamte vyhledávačům publikaci nebo aktualizaci URL. Pro odesílá na společný endpoint api.indexnow.org, který upozorní zapojené vyhledávače. Ve výchozím nastavení vypnuto."
---

# IndexNow — oznámení při publikaci {#indexnow-—-push-on-publish-indexing}

Místo čekání, až robot najde změněnou stránku, můžete pomocí **IndexNow** vyhledávačům *oznámit*, že URL byla právě publikována nebo aktualizována. Pro odesílá na společný endpoint `api.indexnow.org`, který oznámení **předá všem zapojeným vyhledávačům** jediným voláním, bez samostatného požadavku pro každý vyhledávač. [Oficiální FAQ](https://www.indexnow.org/faq) uvádí Amazon, Bing, Naver, Seznam, Yandex a Yep. Oznámení nezaručuje indexaci.

Funkce je **ve výchozím nastavení vypnutá**. Síť se nepoužije, dokud ji nezapnete a neodešlete URL.

## Nastavení {#setup}

### 1. Vygenerujte klíč {#_1-generate-a-key}

IndexNow používá **klíč** k ověření kontroly nad hostitelem. Pro přijímá 8–128 znaků z `[a-f0-9-]`; ideální je 32znakový hexadecimální řetězec. Vygenerujte jej jednou, ponechte stabilní a zpřístupněte přes prostředí:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip Klíč se čte přes konfiguraci, takže přežije `config:cache`
Na rozdíl od přihlašovacích údajů Search Console **není klíč IndexNow tajný**. Je veřejně dostupný na `/{key}.txt` jako důkaz kontroly nad hostitelem. Pro jej proto vyhodnocuje přes konfigurační vrstvu, konkrétně `indexnow.key` s výchozím `env('SEO_PRO_INDEXNOW_KEY')`. Je to záměrné: hodnoty definované **pouze v `.env`** nejsou po `config:cache` dostupné přes `env()`, protože Laravel už tento soubor nenačítá. Skutečné proměnné prostředí procesu zůstávají dostupné. Při čtení přes konfiguraci se klíč zachytí v `config:cache` a je vždy dostupný. Nevýhoda: **změna klíče vyžaduje nové spuštění `php artisan config:cache`.** Klíč se nikdy neloguje. Pokud soubor klíče na produkci vrací 404, viz [Servery s konfigurací v mezipaměti](#config-cached-servers).
:::

### 2. Poskytujte soubor klíče {#_2-serve-the-key-file}

IndexNow načte `https://{host}/{key}.txt` obsahující pouze klíč, aby ověřilo vlastnictví. Se zapnutým `route`, což je výchozí stav, **soubor poskytuje Pro**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Funguje jen cesta jediného nastaveného klíče. Ostatní cesty zachycené touto trasou vracejí 404 a při vypnutém IndexNow vrací 404 celá trasa. Chcete soubor hostovat sami nebo na CDN? Vypněte `route` a nastavte `key_location` na svou URL.

## Odesílání URL {#submitting-urls}

### Automaticky při uložení (oznámení při publikaci) {#automatically-on-save-the-push-on-publish-path}

Přidejte trait na model a zapněte `auto_submit`. Každé uložení zařadí odeslání `getUrlForSEO()` modelu do fronty:

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

Trait respektuje podmínku publikace. Pro plnou kontrolu implementujte `shouldSubmitToIndexNow(): bool`. Jinak použije atribut `is_published`, a pokud ani ten neexistuje, odesílá při každém uložení. Odeslání jde vždy **přes frontu**, takže uložení modelu nečeká na síť.

### Ručně {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` ve výchozím nastavení zařazuje do fronty. Pro přímé provedení předejte `queue: false`.

### Z příkazové řádky {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Jen stejný hostitel
Každá URL musí být `http(s)` **a zároveň** patřit nastavenému `host`. Ostatní se **zahodí**, započítají, ale neodešlou. Odesílat můžete jen vlastní URL a endpoint by odlišného hostitele stejně odmítl. Seznamy větší než `max_urls_per_request`, tedy 10 000 podle limitu protokolu, se automaticky rozdělí.
:::

## Konfigurace {#configuration}

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

## Jak funguje opakování {#how-retries-work}

Úloha `SubmitToIndexNowJob` ve frontě opakuje jen chyby, u kterých to má smysl. `429` při omezení četnosti, `5xx` nebo časový limit se opakují s odstupem `backoff` a nejvýše tolika pokusy, kolik určuje `tries`. `400`/`403`/`422`, tedy trvalá chyba klienta jako chybný klíč nebo jiný hostitel, se zaloguje a **ukončí** bez zbytečného opakování. `200` i `202`, tedy přijetí nebo čekání na ověření klíče, jsou úspěchy.

Na produkci přidělte úloze **vyhrazenou frontu**, aby pomalý endpoint nezdržoval práci pro uživatele:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Řešení potíží {#troubleshooting}

### Servery s konfigurací v mezipaměti {#config-cached-servers}

Pokud `/{key}.txt` na produkci vrací 404 nebo odesílání tiše nic nedělá, přestože je `indexnow.enabled` zjevně `true`, příčinou bývá téměř vždy klíč uložený **pouze v `.env`** na serveru používajícím `php artisan config:cache`. Laravel po uložení konfigurace do mezipaměti nezpracovává `.env`, takže `env('SEO_PRO_INDEXNOW_KEY')` vrátí `null`, trasa souboru klíče se nezaregistruje a každé odeslání se odmítne jako „nenakonfigurované“.

Výchozí konfigurace vyhodnocuje `indexnow.key` z `env(...)`, takže běžné nastavení se zachytí při vytvoření mezipaměti a funguje. Problém nastává pouze tehdy, když jste **zkopírovali konfiguraci a odstranili výchozí `env(...)`** nebo nastavili klíč pod **vlastním názvem `key_env` existujícím jen v `.env`**. Dvě možnosti opravy:

1. **Ponechte klíč v konfiguraci**, doporučený postup. Nechte `indexnow.key` jako `env('SEO_PRO_INDEXNOW_KEY')` nebo nastavte doslovnou hodnotu a znovu spusťte `php artisan config:cache`. Při pozdější změně klíče znovu vytvořte mezipaměť.
2. **Předejte skutečnou proměnnou prostředí.** Nastavte `SEO_PRO_INDEXNOW_KEY` jako skutečnou proměnnou OS nebo procesu: `env[...]` poolu PHP-FPM, `Environment=` v systemd nebo nastavení proměnných vaší platformy, **nejen v `.env`**. Skutečné proměnné OS lze číst i s konfigurací v mezipaměti.

Pro potvrzení spusťte `php artisan seo:doctor`. Při zjištění tohoto stavu hlásí **„IndexNow is enabled but no valid key resolves“**, tedy že IndexNow je zapnuté, ale není dostupný platný klíč, spolu s přesnou opravou. Pro také jednou za proces zaloguje upozornění při startu aplikace s konfigurací v mezipaměti a nečitelným klíčem.

::: tip Google
Google se IndexNow **neúčastní**. Pro Google používejte integraci [Search Console](/cs/pro/search-console) a aktuální mapu webu.
:::
