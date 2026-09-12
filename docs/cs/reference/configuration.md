---
description: Všechny volby v config/seo.php podle vrstev vyhodnocování, každá s dodanou výchozí hodnotou.
---

# Konfigurace {#configuration}

Zkopírujte konfigurační soubor do aplikace:

```bash
php artisan vendor:publish --tag=seo-config
```

Vše níže je v `config/seo.php`. Uvedené hodnoty jsou výchozí.

## Výchozí hodnoty pro celý web (vrstva 1) {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

`title_suffix` se připojí k vyhodnocenému titulku, pokud jím titulek už nekončí.

`title_suffix_skip_when_contains` je seznam výrazů značky, které potlačí příponu. Pokud vyhodnocený titulek některý obsahuje **jako celé slovo**, bez rozlišení velikosti písmen a s respektováním hranic slov, přípona se vynechá, aby se značka zbytečně neopakovala. `Acmestic` proto neodpovídá `Acme`. Výchozí `[]` zachovává původní chování.

## Pravidla vykreslování robots {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

Vykreslený `<head>` vynechá značku `<meta name="robots">`, pokud vyhodnocená direktiva odpovídá výše uvedenému `default_robots`. Nadbytečné `index,follow` jen přidává šum; nepřítomnost značky robot vyhodnocuje právě jako index,follow. **Odlišná** direktiva, například `noindex`, `nofollow` nebo `max-snippet:-1`, se vždy vypíše beze změny. Nastavte `emit_default` na `true` pro vždy vykreslenou značku, tedy chování před verzí 3.1. Samostatné direktivy `@seoRobots` se to netýká: zapíná se výslovně a vždy se vykreslí. Podporovaný slovník direktiv a jejich přednost popisuje [smlouva vykreslování](/cs/contributing/rendering-contract).

## Ochrana před indexací (pojistka mimo produkci) {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Je-li ochrana zapnutá a aplikace běží v prostředí, které **není** v `allowed_environments`, vynutí na každé stránce `noindex,nofollow`. Stojí nad celým řetězcem přednosti, takže přepíše i uloženou hodnotu stránky. Odešle odpovídající hlavičku `X-Robots-Tag`, poskytne `robots.txt` zakazující vše a přiměje `seo:audit` vypsat upozornění. V povolených prostředích, standardně `production`, nic nemění.

Dodává se **vypnutá**; dokud ji nezapnete, výstup je bajtově totožný. Zapněte ji pomocí `SEO_INDEXING_GUARD=true` a vypněte pomocí `SEO_INDEXING_GUARD=false`, vždy jedním řádkem. Seznam povolených prostředí přepište přes `SEO_INDEXING_GUARD_ALLOWED`: oddělujte čárkami, fungují zástupné vzory `Str::is()` jako `prod*`. Výslovně prázdný seznam v konfiguraci chrání všude; prázdná proměnná prostředí naopak zachová výchozí produkční prostředí.

`send_header`, v rámci ochrany standardně zapnuté, navíc odešle `X-Robots-Tag: noindex,nofollow` s každou odpovědí procházející aplikací. Ochrana tak zahrne i PDF, kanály a obrázky bez `<meta robots>`. Middleware se registruje jen při zapnuté ochraně. Důrazně doporučeno; viz úplný [průvodce ochranou před indexací](/cs/guide/indexing-guard).

## Kanonické URL {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Z kanonické URL, kterou resolver **odvodí** z URL požadavku nebo `getUrlForSEO()` modelu, se ve výchozím nastavení odstraní řetězec dotazu. Parametry sledování, filtrování a řazení by pro tutéž stránku vytvářely kanonické cíle s duplicitním obsahem. Klíče uvedené v `query_whitelist` se v odvozené kanonické URL **zachovají** v uvedeném pořadí; ostatní parametry se stále odstraní. Běžný případ je `page` pro stránkované archivy, protože `/blog?page=2` skutečně není `/blog`.

**Výslovně nastavená** kanonická URL, z administrace nebo vrstvy s vyšší předností, se vždy vypíše beze změny včetně řetězce dotazu. Seznam povolených klíčů řídí pouze odvozenou náhradní hodnotu. Výchozí `[]` zachovává odstranění všech parametrů.

## Přepínače funkcí {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` při vytvoření modelu s `HasSEO` založí prázdný řádek `seo_meta`. Pozor: seedery používající `WithoutModelEvents` tento krok obcházejí.

## Hlavní klíčová slova {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

**Přepínač pracovního postupu** s hlavními klíčovými slovy. Při `false`, což je výchozí stav, se stránka bez hlavního klíčového slova nikde neoznačí: neupozorní na ni [`seo:audit`](/cs/guide/audit) ani sken Pro. Aplikace, která klíčová slova nepoužívá, tak nedostává upozornění na nevyužívanou funkci. Zapněte jej, jakmile začnete klíčová slova zadávat, například přes [pole ve Filamentu](/cs/guide/filament). Bezplatný audit, sken Pro i editor Pro pak začnou u stránek bez klíčového slova hlásit upozornění `missing_focus_keyword`. Čtou stejný přepínač, takže se vždy shodnou.

## Bezplatný audit (`seo:audit`) {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

Modely, které bezplatný příkaz [`seo:audit`](/cs/guide/audit) audituje bez volby `--model`. Každý musí používat trait `HasSEO`. Pokud je seznam prázdný, příkaz použije modely registrované v `sitemap.models`.

## Vypočítané náhradní hodnoty (vrstva 5) {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

Při výslovně zapnuté strategii `best` sestavovací nástroj hodnotí uspořádaný seznam kandidátů podle blízkosti pixelových rozměrů ideálu a **přeskakuje všechny pod minimem**. Nejprve jde o `getSEOImage()`, které zůstává kandidátem s nejvyšší prioritou, pak o hook `getSEOImages()` modelu, běžná obrazová pole, první obrázek v obsahu a nastavenou výchozí hodnotu. Měří se pouze **místní** obrázky: relativní cesta pod `public/`, veřejný disk nebo absolutní URL na vlastním hostiteli. Vzdálená URL se nikdy nestahuje a funguje jen jako náhrada. Pokud žádný místní kandidát nesplní minimum, výběr se vrátí k první shodě, takže `best` nikdy nevrátí méně než `first`. Zpřístupněte kandidáty z modelu:

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## Mapy webu {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

Zdroje zadávané v kódu popisuje [průvodce registrem mapy webu](/cs/guide/sitemaps).

## Schéma (JSON-LD) {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

Tyto hodnoty vstupují do uzlů [grafu schématu](/cs/guide/schema).

## Trasy {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Pokud aplikace poskytuje vlastní statický `/sitemap.xml`, nastavte `enabled => false`.

## Mezipaměť {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Mezipaměť výsledků resolveru {#resolver-result-cache}

`SEOResolver` při **každém** vykreslení frontendu projde celý řetězec přednosti: konfigurace → globální výchozí hodnoty / typ modelu / trasa → vypočítané hodnoty modelu → výslovné `seo_meta` → přípona titulku / kanonická URL / schéma. Na vytíženém webu, jako je referenční aplikace s přibližně 20 tisíci požadavků denně, to znamená několik čtení databáze na stránku.

Zapněte `cache.resolver.enabled` a plně vyhodnocené SEO modelu se uloží do mezipaměti. **Zásah mezipaměti zcela přeskočí řetězec přednosti.** V benchmarku balíčku už naplněná mezipaměť vyvolá **nula** databázových dotazů, zatímco každé vyhodnocení bez ní znovu čte `seo_meta` modelu. Uložená data jsou prosté pole, obnovené pomocí `SEOData::fromArray()`, nikdy objekt. Laravel 13 totiž dodává `cache.serializable_classes = false`, takže uložený objekt se vrátí jako `__PHP_Incomplete_Class`.

Používá výše nastavené `store`. Na produkci jej proto nasměrujte na **sdílenou trvalou mezipaměť**, `redis` / `memcached`. Mezipaměť i její zneplatnění musí být viditelné pro každý webový worker i worker fronty. Dokud takové úložiště nemáte, ponechte funkci vypnutou.

**Zneplatnění je automatické a zachovává správnost**: zapnutá mezipaměť vyhodnocuje stejně jako vypnutá. Položky používají klíč `(model class, id, locale, route, request URL)` a mažou se, když:

- se řádek `seo_meta` stránky **uloží nebo odstraní** jakýmkoli postupem: `saveSEO()`, Filament nebo přímý zápis přes `SEOMeta`;
- se změní **pole obsahu** modelu, tedy sloupce z `getSEOContentFields()`. Výchozí seznam obsahuje všechna pole vestavěných vypočítaných náhradních hodnot: pole title/headline, excerpt/summary/content/body/text/article a běžná obrazová pole jako `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` a `hero_image`. Přepište jej, pokud model počítá SEO z dalších sloupců;
- se změní **libovolný řádek `seo_defaults`**. Výchozí hodnota může vstupovat do každého modelu, proto se vyprázdní celá mezipaměť vyhodnocování.

V úložišti **s podporou štítků**, `redis`, `memcached` nebo `array`, se položky modelu odstraní přes **štítky** mezipaměti. V úložišti **bez štítků**, `file` nebo `database`, balíček použije **značku verze** jednotlivého modelu. Oba postupy fungují bez procházení klíčů.

::: tip
Do mezipaměti se ukládají jen vyhodnocení navázaná na model. `SEO::render()`/`@seo()` pro ručně sestavené `SEOData` a `@seoForRoute()` pro trasu bez modelu se stále vyhodnocují přímo.
:::

::: warning
Mezipaměť odpovídá `updated_at` / vypočítanému `modified_time` modelu při poslední změně **pole obsahu**, případně do vypršení TTL. Samotné `touch()`, které posune pouze `updated_at` bez změny sloupce z `getSEOContentFields()`, nevynutí nové vyhodnocení. `article:modified_time` se tak může opozdit až o TTL. Pokud potřebujete okamžité zneplatnění, přidejte každý vlastní sloupec používaný při výpočtu do `getSEOContentFields()`.
:::
