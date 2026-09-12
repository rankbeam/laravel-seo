---
description: "Panel Google Search Console výhradně pro čtení: hlavní dotazy a stránky se zobrazeními, kliknutími, CTR a pozicí propojené se stránkami skeneru. Standardně vypnuto."
---

# Search Console (pouze čtení) {#search-console-read-only}

Panel Google Search Console **pouze pro čtení**: hlavní dotazy a stránky se **zobrazeními, kliknutími, CTR a průměrnou pozicí**, propojené se stránkami, které skener už zná. Na jednom místě tak vidíte, že *„tato stránka má problémy **a** ztrácí zobrazení“*. Ve výchozím nastavení je **vypnutý**.

Návrh určují tři zásady:

- **Výhradně čtení.** Integrace požaduje jediný rozsah OAuth, `webmasters.readonly`, pevně určený v balíčku. Může číst Search Analytics a nic dalšího: nikdy neodesílá mapu webu, nežádá o indexaci ani nic v Search Console nemění. Konfigurace nenabízí rozšíření rozsahu.
- **Vaše služba, vaše přihlašovací údaje.** Požadavky jdou z *vašeho serveru* přímo do Googlu s autentizací *vaším* účtem služby nebo údaji OAuth. Nic neprochází zprostředkovatelem, neměří se pro účtování ani se nepřeprodává a balíček neposílá telemetrii.
- **Chyby zůstávají v kontextu.** Chybějící přihlašovací údaje, 403, překročení kvóty nebo časové limity zobrazí zprávu přímo v panelu bez přerušení vykreslování. Příkaz synchronizace historie hlásí chyby a přestane načítat následující dny, jak je popsáno níže.

## Co získáte {#what-you-get}

- **Stránky vyžadující pozornost**: propojení stránek s **otevřenými problémy skenu**, které **stále přivádějí návštěvnost z vyhledávání**. Největší příležitosti jsou první, tedy problémové stránky s nejvíce zobrazeními. Opravujte je přednostně.
- **Hlavní stránky** a **hlavní dotazy**: obvyklé tabulky Search Analytics.

V přehledu Filamentu jde o stránku **Search Console** v navigační skupině *SEO*, která se zobrazí pouze při zapnuté integraci. Bez panelu poskytují stejné metriky příkaz `seo-pro:search-console` a `SeoPro::searchConsole()`.

## Nastavení {#setup}

Potřebujete přihlašovací údaje Googlu s přístupem ke čtení služby Search Console. Podporované jsou dva režimy; pro server je nejjednodušší **účet služby**.

### Účet služby (doporučeno) {#service-account-recommended}

1. V Google Cloud zapněte **Search Console API**, vytvořte **účet služby** a stáhněte jeho klíč JSON.
2. V Search Console → *Nastavení → Uživatelé a oprávnění* přidejte e-mail účtu služby (`…@….iam.gserviceaccount.com`) jako uživatele. Pro čtení stačí omezené oprávnění.
3. Nastavte v balíčku klíč a službu:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

Pokud `SEO_PRO_GSC_SITE_URL` vynecháte, služba s předponou URL se odvodí z `app.url`.

### OAuth (obnovovací token pro offline přístup) {#oauth-offline-refresh-token}

Máte-li klienta OAuth a dlouhodobý **obnovovací token**, nejlépe autorizovaný pouze pro `webmasters.readonly`, nastavte jej níže. Každé obnovení požaduje tento rozsah. Balíček vrácený token odmítne, pokud odpověď výslovně nepotvrdí přesně rozsah pouze pro čtení. Nepředpokládá, že Google vždy zúží širší udělené oprávnění.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Zkopírování migrace tokenů {#publish-the-token-migration}

Šifrovaná mezipaměť přístupových tokenů je v tabulce `seo_gsc_tokens`. Jednou zkopírujte migraci do aplikace a spusťte ji:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Potom potvrďte zapojení pomocí `php artisan seo:doctor`. Hlásí, zda je Search Console zapnutá a nakonfigurovaná, bez síťového volání a vypisování tajných údajů.

## Použití bez panelu {#headless-usage}

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

## Historické metriky {#historical-metrics}

Panel a příkaz výše čtou **aktuální klouzavé období**; jediným úložištěm je samotná Search Console. Pro **denní historii**, ze které se můžete ptát na minulé období, spusťte synchronizační příkaz. Ukládá denní metriky podle dotazu a stránky do tabulky `seo_gsc_metrics`:

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

- **První běh doplní historii** za `sync.backfill_days`, standardně 90 dní. Search Console uchovává přibližně 16 měsíců, takže hodnotu můžete zvýšit. Další běhy **navazují od posledního uloženého data** a znovu načítají posledních `sync.overlap_days`, aby zachytily pozdní dokončování nedávných dat v Search Console. Období vždy končí před 3 dny kvůli zpoždění dat.
- **Idempotentní.** Řádky se vkládají nebo aktualizují podle `(date, dimension, key)`, takže opakování je bezpečné. Chyba některého dne, například kvóta, řádně zastaví běh a oznámí počet uložených řádků. Příští běh naváže tam, kde předchozí skončil.
- **Co historie umožňuje.** Část změn Search Console v [reportu s vlastní značkou](/cs/pro/reports) přejde na skutečné porovnání období s bezprostředně předcházejícím obdobím stejné délky, jakmile tabulka pokryje obě období. Nahradí tak porovnání se snímkem předchozího reportu. Historie je také podkladem pro podrobnější analýzy klíčových slov.

Ukládají se jen agregované metriky: text dotazu, URL stránky a čtyři denní metriky, tedy kliknutí, zobrazení, CTR a pozice. Data jednotlivých uživatelů nebo požadavků se nikdy nenačítají ani nezapisují.

## Zpracování dat a bezpečnost {#data-handling-security}

- **Kontrola rozsahu pouze pro čtení.** JWT účtu služby požaduje jen `webmasters.readonly`. Totéž platí pro obnovení OAuth a balíček odmítá odpověď s chybějícím nebo širším rozsahem. Použijte údaje autorizované pouze pro čtení. Balíček neobsahuje žádné volání měnící Search Console.

- **Přihlašovací údaje zůstávají v prostředí.** Klíč účtu služby nebo tajný údaj OAuth a obnovovací token se při volání čtou z **pojmenovaných** proměnných prostředí stejně jako klíč AI. `php artisan config:cache` je tak nikdy nezapíše do `bootstrap/cache/config.php`. Když konfigurace v mezipaměti zabrání načtení `.env`, zpřístupněte je v prostředí procesu.
- **Uložené tokeny jsou šifrované.** Krátkodobý přístupový token získaný z vašich údajů se ukládá **šifrovaně** aplikačním klíčem do `seo_gsc_tokens` a používá se, dokud se neblíží expiraci. Výměna tokenu proto neprobíhá při každém zobrazení. Dlouhodobé přihlašovací údaje se do databáze nikdy neukládají, zůstávají pouze v prostředí.
- **Každý požadavek chrání kontrola SSRF.** Výměna tokenu i volání Search Analytics procházejí sdíleným `SsrfGuard`: pouze HTTPS, hostitel se musí přeložit na veřejnou adresu a přesměrování jsou vypnutá. Požadavek tak nelze přesměrovat na interní službu.
- **Tajné údaje se nelogují.** Přístupové tokeny, klíče ani autentizační hlavičky se nikdy nezapisují do logů. Chyba API zobrazí pouze vlastní očištěnou zprávu Googlu s omezenou délkou.
- **Metriky mají místní mezipaměť** na `seo-pro.search_console.cache_ttl` sekund, standardně 30 minut, aby panel nevolal API při každém vykreslení. Aktuální panel a příkaz kromě této mezipaměti a šifrovaného přístupového tokenu nic neukládají. Pouze volitelný příkaz `seo-pro:gsc-sync` zapisuje metriky trvale: agregované denní údaje dotazů a stránek do `seo_gsc_metrics`, bez dat jednotlivých uživatelů.

## Přehled konfigurace {#configuration-reference}

Všechny klíče jsou v `config/seo-pro.php` → `search_console`:

| Klíč | Výchozí hodnota | Účel |
| --- | --- | --- |
| `enabled` | `false` | Hlavní přepínač (`SEO_PRO_GSC_ENABLED`). |
| `connection` | `service_account` | `service_account` nebo `oauth`. |
| `site_url` | odvozeno z `app.url` | Služba: `https://example.com/` nebo `sc-domain:example.com`. |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Název** proměnné prostředí s klíčem JSON nebo cestou k němu. |
| `oauth.client_id` | — | ID klienta OAuth; není tajné. |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Název** proměnné prostředí s tajným údajem klienta. |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Název** proměnné prostředí s obnovovacím tokenem. |
| `default_days` | `28` | Období reportu; končí před 3 dny kvůli zpoždění dat GSC. |
| `row_limit` | `100` | Nejvýše N řádků na report; maximum API je 25 000. |
| `cache_ttl` | `1800` | Počet sekund uchovávání načteného reportu v mezipaměti. |
| `sync.backfill_days` | `90` | Dny načtené při prvním `gsc-sync` s prázdnou tabulkou. |
| `sync.overlap_days` | `2` | Poslední dny načítané při každém běhu znovu kvůli pozdní finalizaci. |
| `sync.row_limit` | `5000` | Nejvyšší počet řádků, který synchronizace požaduje na den a dimenzi. |
