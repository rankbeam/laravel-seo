---
description: "Genera sitemap XML con un file per sorgente e un indice su /sitemap.xml. Registra modelli, closure o elenchi di URL con spatie/laravel-sitemap."
---

# Registro delle sitemap {#sitemap-registry}

Il pacchetto genera sitemap XML, una per sorgente più un indice, e le serve su `/sitemap.xml` e `/sitemap-{name}.xml`. Per generarle usa [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

## Registra le sorgenti {#registering-sources}

Registra le sorgenti con un nome nel metodo `boot()` di un service provider:

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

Ogni sorgente produce `sitemap-{name}.xml`; `sitemap.xml` diventa l'indice che le elenca. Il registro offre anche `has($name)`, `names()`, `forget($name)` e `flush()`.

## Sorgenti da configurazione {#config-driven-sources}

Puoi definire modelli e URL statici anche in `config/seo.php`:

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info Il registro ha la precedenza sulla scoperta automatica
Se un modello appartiene già a una sorgente registrata, la scoperta automatica lo salta. Registrare `'posts'` non produce anche un `sitemap-post.xml` duplicato.
:::

## Generazione {#generating}

```bash
php artisan seo:sitemap
```

I file vengono scritti sul disco `seo.sitemap.disk`, che per impostazione predefinita è `public`. Pianifica il comando per mantenere le sitemap aggiornate:

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

Le sitemap che superano `seo.sitemap.max_urls_per_sitemap` vengono suddivise automaticamente. Il valore predefinito è 50.000 URL, il limite previsto dal formato XML.

## Pubblicazione dei file {#serving}

Le rotte del pacchetto servono i file generati con header XML, header di cache e `X-Robots-Tag: noindex`:

- `/sitemap.xml` — indice o sitemap singola
- `/sitemap-posts.xml` — sorgente con nome

Se servi già una sitemap statica, disabilita le rotte:

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## Sitemap leggibile nel browser {#styled-sitemap-in-the-browser}

Aprendo una sitemap Rankbeam nel browser, gli URL vengono mostrati in una tabella con `lastmod`, frequenza di modifica, priorità e conteggi di immagini e lingue alternative. La vista include alcune note di validazione.

![Sitemap Rankbeam mostrata nel browser come tabella leggibile](/sitemap-styled.png)

Ogni sitemap generata fa riferimento a un foglio XSL:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

I motori di ricerca ignorano questa istruzione. Il documento rimane una normale sitemap XML: cambia solo la presentazione nel browser, sia per l'indice sia per i file figli.

La funzione è **attiva per impostazione predefinita**. Non aggiunge dati né lavoro per ogni record: inserisce una sola istruzione che i crawler saltano.

::: warning Richiede spatie/laravel-sitemap ≥ 8.1
L'istruzione usa `setStylesheet()`, introdotto in `spatie/laravel-sitemap` 8.1. Con versioni precedenti, possibili in alcune combinazioni PHP/Laravel, le sitemap restano XML senza stile. Per aggiornare: `composer update spatie/laravel-sitemap`.
:::

Per disattivare lo stile:

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### Note di validazione {#validation-notes}

La vista segnala due condizioni verificabili senza richieste esterne:

- **URL senza `lastmod`:** la data mancante viene segnalata, mai inventata. Le date devono rappresentare modifiche reali.
- **URL non assoluti:** un `<loc>` che non contiene un URL `http(s)` assoluto.

### Ospita il foglio di stile sul tuo dominio {#self-hosting-the-stylesheet}

Il pacchetto serve normalmente lo stile dalla propria rotta `/sitemap.xsl`. Il browser applica XSLT solo se ha la **stessa origine** della sitemap. Se i file si trovano su un'altra origine, per esempio una CDN, pubblica il foglio e indica l'URL della tua copia:

```bash
php artisan vendor:publish --tag=seo-assets
```

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => [
        'url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
    ],
],
```

::: info Escaping dei valori
Ogni valore, compresi gli URL, passa dall'escaping XSLT. Un `<loc>` diventa cliccabile solo se è un URL `http(s)`. Se modifichi il file `.xsl` pubblicato, conserva queste protezioni e non aggiungere `disable-output-escaping`.
:::

## Quali record vengono inclusi {#what-gets-included}

Le sorgenti basate su modelli includono i record risolti come indicizzabili. Un modello con robots `noindex` resta fuori. Gli URL provengono da `getUrlForSEO()`, lo stesso metodo usato per derivare i canonical.

## Estensioni immagini e hreflang {#image-hreflang-extensions}

Due estensioni facoltative arricchiscono gli URL dei modelli con dati già risolti per il record. Sono entrambe **disattivate per impostazione predefinita**. Attiva quelle che servono in `config/seo.php`:

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

Si applicano ai modelli con `HasSEO`, usando il risultato completo di `seoData()`:

- **`images`** aggiunge una voce del formato [Google image sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps) basata sull'immagine OG o del contenuto risolta. Se il record non ha un'immagine propria, può essere usata `default_og_image`: attiva l'estensione quando un'immagine per URL è significativa per i tuoi contenuti.
- **`alternates`** aggiunge le voci `<xhtml:link rel="alternate" hreflang="…">` da `getSEOAlternates()`, come nel `<head>`. Restituisci URL assoluti:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning hreflang deve essere reciproco e includere la pagina stessa
Ogni versione deve elencare se stessa e tutte le altre, con rimandi reciproci. `getSEOAlternates()` deve quindi restituire lo stesso insieme completo per ogni variante localizzata. Usa codici validi `language[-Script][-REGION]` oppure `x-default` e URL `http(s)` assoluti. Le voci con `hreflang` o `href` vuoti vengono saltate.

Prima dell'emissione si applicano le [policy `seo.hreflang`](/it/guide/multilingual#hreflang): i codici vengono normalizzati (`it_IT` → `it-IT`) e `include_self` / `x_default` possono aggiungere i relativi riferimenti. La sitemap usa lo stesso elenco del `<head>`. L'audit gratuito segnala `hreflang_invalid_code`, `hreflang_duplicate_code` e `hreflang_missing_self`; la reciprocità richiede il crawl di Pro.
:::

::: info Costo sui cataloghi grandi
Con almeno un'estensione attiva, il builder risolve l'intero `seoData()` una volta per URL: default, valori calcolati e getter `getSEO*()`. Ogni record può comportare diverse operazioni di cache o database, oltre a quelle dei tuoi getter. Esegui `seo:sitemap` come comando pianificato, non durante una richiesta web. Misura il costo prima di attivare le estensioni su una sitemap vicina ai 50.000 URL; lasciale disattivate se non ti servono.
:::

::: tip Hai già pubblicato la configurazione?
Il merge di `config/seo.php` non è ricorsivo. Un file pubblicato prima dell'aggiunta di `sitemap.images` e `sitemap.alternates` non riceve automaticamente queste chiavi. Le sole variabili `SEO_SITEMAP_IMAGES` e `SEO_SITEMAP_ALTERNATES` non bastano: aggiungi le chiavi all'array `sitemap` oppure ripubblica la configurazione.
:::

## Controllo completo con tag Spatie {#full-control-hand-built-spatie-tags}

Per didascalie, video, notizie o elenchi hreflang personalizzati, restituisci un [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images) già costruito. Il builder lo usa invariato senza aggiungere estensioni proprie:

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

Lo stesso vale per un singolo record: se il modello implementa `Sitemapable` e `toSitemapTag()` restituisce un `Url`, il tag viene emesso così com'è.
