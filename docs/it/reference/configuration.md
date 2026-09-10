---
description: "Le opzioni di config/seo.php, raggruppate per livello del resolver, con i valori predefiniti distribuiti."
---

# Configurazione {#configuration}

Pubblica il file di configurazione:

```bash
php artisan vendor:publish --tag=seo-config
```

Le opzioni descritte sotto si trovano in `config/seo.php`. I valori mostrati sono quelli predefiniti.

## Valori predefiniti del sito: livello 1 {#site-wide-defaults-layer-1}

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

`title_suffix` viene aggiunto ai titoli risolti, a meno che non terminino già con quel suffisso.

`title_suffix_skip_when_contains` evita di ripetere il marchio. Se il titolo risolto contiene uno dei termini dell’elenco **come parola intera**, il suffisso viene omesso. Il confronto ignora maiuscole e minuscole e rispetta i confini delle parole: `Acmestic` non corrisponde ad `Acme`. Il valore predefinito `[]` conserva il comportamento precedente.

## Policy di rendering dei robots {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

Il `<head>` omette `<meta name="robots">` quando la direttiva risolta coincide con `default_robots`. Un `index,follow` ridondante è superfluo: l’assenza del tag ha già quel significato per i crawler. Una direttiva **diversa**, come `noindex`, `nofollow` o `max-snippet:-1`, viene sempre emessa così com’è. Imposta `emit_default` a `true` per generare sempre il tag, ripristinando il comportamento precedente alla 3.1. La direttiva specifica `@seoRobots` continua a emetterlo sempre, perché la sua chiamata è esplicita. Il [contratto di rendering](/it/contributing/rendering-contract) descrive direttive supportate e priorità.

## Protezione dall’indicizzazione negli ambienti non di produzione {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Quando la protezione è abilitata e l’applicazione viene eseguita in un ambiente **escluso** da `allowed_environments`, forza `noindex,nofollow` su ogni pagina, sopra l’intera catena di priorità e quindi anche sopra un valore esplicito salvato. Invia l’header `X-Robots-Tag` corrispondente, genera un `robots.txt` che blocca tutto e mostra un avviso in `seo:audit`. Negli ambienti consentiti, per impostazione predefinita `production`, è inattiva.

È **disattivata all’installazione**, quindi l’output rimane identico finché non la abiliti. Usa `SEO_INDEXING_GUARD=true` per attivarla e `SEO_INDEXING_GUARD=false` per disattivarla. `SEO_INDEXING_GUARD_ALLOWED` sostituisce l’elenco consentito con valori separati da virgole; sono accettati i caratteri jolly di `Str::is()`, come `prod*`. Un elenco vuoto nella configurazione protegge tutti gli ambienti.

`send_header`, attivo per impostazione predefinita all’interno della protezione, invia `X-Robots-Tag: noindex,nofollow` su ogni risposta che passa dall’applicazione, comprese quelle PDF, feed e immagini che non hanno un meta tag robots. Il middleware viene registrato solo quando la protezione è abilitata. La funzione è fortemente consigliata; vedi la [guida alla protezione dall’indicizzazione](/it/guide/indexing-guard).

## URL canonical {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Per impostazione predefinita, un canonical **derivato** dal resolver — dall’URL della richiesta o da `getUrlForSEO()` del modello — non include la query string. Parametri di tracciamento, filtro e ordinamento possono infatti produrre diversi URL canonical per lo stesso contenuto. Le chiavi elencate in `query_whitelist` vengono **conservate**, nell’ordine indicato, mentre le altre vengono rimosse. Un caso comune è `page` negli archivi paginati: `/blog?page=2` rappresenta una pagina diversa da `/blog`.

Un canonical **impostato esplicitamente**, dall’amministrazione o da un livello con priorità maggiore, viene sempre emesso così com’è, query string compresa. L’elenco consentito riguarda soltanto il fallback derivato. Il valore predefinito `[]` conserva la rimozione di tutti i parametri.

## Opzioni di attivazione delle funzioni {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` crea una riga vuota in `seo_meta` quando viene creato un modello con `HasSEO`. I seeder che usano `WithoutModelEvents` non eseguono questo hook.

## Parole chiave principali {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

Questa opzione attiva i controlli del **flusso editoriale sulle parole chiave principali**. Quando vale `false`, come per impostazione predefinita, l’assenza di una parola chiave non viene segnalata né da [`seo:audit`](/it/guide/audit) né dalla scansione Pro. Un’applicazione che non usa questa funzione non riceve quindi avvisi relativi alla sua mancata adozione. Attivala quando inizi a impostare parole chiave, per esempio con il [campo Filament](/it/guide/filament): audit gratuito, scansione Pro ed editor Pro segnaleranno `missing_focus_keyword` sulle pagine che ne sono ancora prive. Tutti leggono la stessa opzione.

## Audit gratuito: `seo:audit` {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

Questi sono i modelli analizzati da [`seo:audit`](/it/guide/audit) quando non passi `--model`. Ognuno deve usare `HasSEO`. Se l’elenco è vuoto, il comando usa i modelli registrati in `sitemap.models`.

## Fallback calcolati: livello 5 {#computed-fallbacks-layer-5}

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

Con la strategia facoltativa `best`, il builder valuta un elenco ordinato di candidati: prima `getSEOImage()`, che conserva la priorità maggiore, poi l’hook `getSEOImages()` del modello, i campi immagine comuni, la prima immagine del contenuto e il valore predefinito configurato. Confronta le dimensioni in pixel con quelle ideali ed **esclude le immagini sotto il minimo**. Misura soltanto immagini **locali**: percorsi relativi sotto `public/`, file del disco pubblico o URL assoluti dello stesso host. Un URL remoto non viene scaricato e serve solo come fallback. Se nessun candidato locale raggiunge il minimo, la selezione torna alla prima corrispondenza: `best` non restituisce meno di quanto restituirebbe `first`. Esponi i candidati dal modello:

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

## Sitemap {#sitemaps}

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

Per le sorgenti registrate da codice, consulta la [guida al registro delle sitemap](/it/guide/sitemaps).

## Schema JSON-LD {#schema-json-ld}

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

Questi valori alimentano i nodi del [grafo schema](/it/guide/schema).

## Rotte {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Imposta `enabled => false` quando l’applicazione serve un proprio `/sitemap.xml` statico.

## Cache {#cache}

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

### Cache dei risultati del resolver {#resolver-result-cache}

`SEOResolver` percorre l’intera catena a **ogni** rendering del frontend: configurazione → valori predefiniti globali, per tipo di modello e per rotta → valori calcolati del modello → `seo_meta` esplicito → suffisso del titolo, canonical e schema. Su un sito con molto traffico — l’applicazione di riferimento gestisce circa 20.000 richieste al giorno — questo comporta più letture dal database per pagina.

Abilitando `cache.resolver.enabled`, i dati SEO completamente risolti del modello vengono memorizzati. Quando la cache contiene il risultato, **l’intera catena viene saltata**: nel benchmark del pacchetto una lettura dalla cache già popolata esegue **zero query**, mentre ogni risoluzione senza cache rilegge `seo_meta`. Il payload è un semplice array, ricostruito con `SEOData::fromArray()`, mai un oggetto. Laravel 13 usa infatti `cache.serializable_classes = false`: un oggetto salvato in cache viene restituito come `__PHP_Incomplete_Class`.

Viene usato lo `store` configurato sopra. In produzione scegli una **cache condivisa e persistente**, come `redis` o `memcached`, perché tutti i processi web e queue devono vedere dati e invalidazioni. Lascia la funzione disattivata finché non ne hai una.

**L’invalidazione è automatica** e mantiene coerenti i risultati con la cache attiva o disattivata, entro i limiti descritti sotto. Le voci sono identificate da `(model class, id, locale, route, request URL)` e vengono invalidate quando:

- la riga `seo_meta` della pagina viene **salvata o eliminata**, tramite `saveSEO()`, Filament o una scrittura diretta su `SEOMeta`;
- cambia un **campo di contenuto** del modello elencato da `getSEOContentFields()`. Il valore predefinito include tutti i campi dei fallback calcolati: titolo e headline, excerpt, summary, content, body, text, article e campi immagine comuni come `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` e `hero_image`. Estendi il metodo se il modello calcola dati SEO da altre colonne;
- cambia **qualsiasi riga di `seo_defaults`**. Poiché un valore predefinito può alimentare qualunque modello, viene svuotata l’intera cache di risoluzione.

Negli store che **supportano tag**, come `redis`, `memcached` e `array`, le voci del modello vengono invalidate tramite **tag**. Negli store che **non li supportano**, come `file` e `database`, il pacchetto usa un **numero di versione per modello**. Entrambi i meccanismi funzionano senza cercare tutte le chiavi.

::: tip
Vengono memorizzate solo le risoluzioni associate a un modello. `SEO::render()` o `@seo()` con un `SEOData` costruito manualmente, e `@seoForRoute()` per una rotta senza modello, continuano a risolversi al momento.
:::

::: warning
La cache conserva `updated_at` e il `modified_time` calcolato al momento dell’ultima modifica di un **campo di contenuto**, oppure fino alla scadenza del TTL. Un semplice `touch()` che modifica solo `updated_at`, senza cambiare una colonna di `getSEOContentFields()`, non forza una nuova risoluzione: `article:modified_time` può restare indietro fino alla scadenza del TTL. Aggiungi i campi calcolati specifici dell’applicazione a `getSEOContentFields()` se vuoi invalidarli subito.
:::
