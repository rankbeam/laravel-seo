---
description: "Genera i tag SEO in Laravel con le direttive Blade del pacchetto: @seo risolve un modello e produce metadati, Open Graph, Twitter Card e JSON-LD."
---

# Guida a Blade {#blade-guide}

Il pacchetto offre sette direttive Blade per le applicazioni con rendering sul server. Di solito ne basta una: `@seo`.

## La direttiva completa {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` risolve il modello attraverso la [catena di priorità](/it/concepts/resolver-precedence) e genera l’intero blocco head: `<title>`, meta description, link canonical, robots, tag Open Graph, tag Twitter Card e JSON-LD associato. Il tag robots viene emesso **solo quando differisce dal valore predefinito del sito**: `index,follow` viene omesso perché la sua assenza ha già quel significato. Imposta `seo.robots.emit_default` per generarlo sempre. Il [contratto di rendering](/it/contributing/rendering-contract) descrive il comportamento completo.

Firme delle chiamate:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` accetta un `Model`, un `SEOData` costruito manualmente oppure `null`. Gli argomenti per rotta e lingua si applicano solo a `Model` e `null`: un `SEOData` costruito manualmente contiene già i propri valori.

## Pagine di rotta senza modello {#route-pages-no-model}

Per pagine statiche, archivi e altre pagine associate a una rotta:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

I valori della rotta provengono dalle righe di `seo_defaults` associate al suo nome.

## Pagine senza modello: costruire un `SEOData` {#model-less-pages-hand-built-seodata}

Elenchi, risultati di ricerca e pagine composte nel controller spesso non hanno un unico modello di riferimento. Costruisci un `SEOData` e passalo direttamente a `@seo` o alla facade `SEO`, senza dover chiamare `app(TagRenderer::class)->render(...)`:

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

Un `SEOData` costruito manualmente rappresenta una **scelta esplicita**. Ogni valore impostato viene conservato; durante il rendering vengono completati solo i dati mancanti:

- `canonical` e `og:url` vengono ricavati dall’URL corrente quando sono assenti; un `canonical` esplicito viene mantenuto così com’è, query string compresa;
- `title_suffix` viene aggiunto solo se manca nel titolo e viene ignorato del tutto se il titolo contiene già un termine del marchio: vedi [`title_suffix_skip_when_contains`](/it/reference/configuration);
- i percorsi relativi di `og:image` e `twitter:image` vengono convertiti in URL assoluti con `url()`, che rispetta lo schema corrente e **non** forza HTTPS;
- `og:site_name` e `locale` vengono completati dalla configurazione e dalla lingua dell’applicazione.

La catena di priorità del database — valori globali, per tipo di modello, per rotta e di `seo_meta` — **non** viene applicata a un `SEOData` costruito manualmente. Il rendering usa i valori passati, completando soltanto i dati indicati sopra.

Lo stesso oggetto funziona tramite la facade:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Un layout per diversi tipi di pagina {#a-layout-pattern-that-scales}

Un unico layout può gestire pagine di modello, pagine di rotta e tutti gli altri casi:

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

I controller passano quindi `'seoModel' => $post` oppure `'seoRoute' => 'blog.index'`, senza intervenire sul markup.

## Direttive per i singoli tag {#granular-directives}

Quando devi controllare i tag separatamente, per esempio per combinarli con l’output di un altro pacchetto:

| Direttiva | Output |
|---|---|
| `@seoTitle($post)` | Solo `<title>` |
| `@seoMeta($post)` | Solo la meta description |
| `@seoCanonical($post)` | Solo il link canonical; in assenza di un valore usa l’URL corrente |
| `@seoRobots($post)` | Solo il meta tag robots, sempre emesso: la chiamata è esplicita, quindi **non** applica la soppressione del valore predefinito usata da `@seo` |
| `@seoSchema($post)` | Solo lo `<script>` JSON-LD, utilizzabile nell’head o nel body |

Tutte accettano la stessa espressione `($model, $route, $locale)` di `@seo`, oppure nessun argomento per la pagina corrente.

## Versioni alternative con hreflang {#hreflang-alternates}

I modelli che usano `HasSEO` possono fornire i link hreflang direttamente al resolver:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Usa URL assoluti. `@seo($post)` risolve queste voci e le genera come `<link rel="alternate" hreflang="..." href="...">`. Prima converte i codici nel formato BCP 47 (`it_IT` → `it-IT`). Le policy di `seo.hreflang` possono aggiungere il riferimento alla pagina stessa e una voce `x-default`; l’audit gratuito segnala voci non valide, duplicate o prive del riferimento alla pagina corrente. Vedi [Contenuti multilingua](/it/guide/multilingual#hreflang).

## Escaping e sicurezza {#escaping-and-safety}

I valori testuali passano attraverso `e()`. Il JSON-LD viene codificato con `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`, quindi un `</script>` nei contenuti dell’utente non può chiudere l’elemento script.
