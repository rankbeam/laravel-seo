---
description: "Offri una rappresentazione Markdown della pagina ai client che la richiedono, tramite negoziazione del contenuto. L’HTML normale resta invariato. Funzione gratuita, disattivata per impostazione predefinita."
---

# Markdown per i bot {#markdown-for-bots}

L’HTML di un’applicazione contiene navigazione, script e markup del layout oltre ai contenuti. Alcuni crawler AI e motori di risposta accettano una rappresentazione più semplice, quando disponibile. Questa funzione può fornire una **versione Markdown** ai client che la richiedono tramite negoziazione del contenuto, lasciando invariato l’HTML ricevuto dagli altri visitatori. È una scelta di compatibilità da attivare esplicitamente, non una promessa su come un client leggerà o userà il risultato.

Si affianca al [controllo dei crawler AI](/it/guide/ai-crawlers): quello stabilisce la policy di accesso; questa funzione stabilisce quale contenuto fornire durante la richiesta.

È una funzione del core gratuito ed è **disattivata per impostazione predefinita**.

## Come funziona {#how-it-works}

Quando la attivi, viene registrato un middleware di negoziazione del contenuto. Dopo la generazione della risposta normale, il middleware la sostituisce con Markdown **solo se entrambe le condizioni sono soddisfatte**:

1. **La richiesta chiede Markdown**, tramite un header esplicito `Accept: text/markdown`, il parametro `?format=md` oppure, se abilitato, lo user-agent di un crawler AI noto.
2. **È disponibile una sorgente Markdown per la rotta.**

Negli altri casi la risposta resta invariata. Un browser che non richiede questo formato continua a ricevere HTML. Vengono sostituite solo risposte **HTML riuscite**, mai JSON, redirect o download.

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Da dove proviene il Markdown {#where-the-markdown-comes-from}

Le sorgenti descritte sotto possono fornire Markdown per la rotta corrispondente. Il middleware prova **prima una sorgente registrata per la rotta**, poi i modelli associati. Per ciascun modello, un metodo `toSeoMarkdown()` esplicito prevale sul fallback generato; se restituisce un valore nullo o vuoto, il fallback viene disabilitato per quel modello.

### 1. Markdown fornito dal modello {#_1-a-model-s-own-markdown}

Se nessuna sorgente registrata per la rotta restituisce contenuto, un modello associato che implementa `toSeoMarkdown()` controlla il proprio output. Puoi implementare il contratto `ProvidesSeoMarkdown` oppure aggiungere soltanto il metodo:

```php
use Rankbeam\Seo\Contracts\ProvidesSeoMarkdown;

class Post extends Model implements ProvidesSeoMarkdown
{
    use HasSEO;

    public function toSeoMarkdown(): ?string
    {
        return $this->body_markdown; // your already-clean markdown
    }
}
```

### 2. Una sorgente registrata per la rotta {#_2-a-registered-route-source}

Per rotte senza modello, o per sostituire l’output del modello, registra una sorgente usando il nome della rotta:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. Il fallback generato {#_3-the-built-fallback}

Se un modello associato alla rotta usa `HasSEO` ma non ha `toSeoMarkdown()`, il middleware costruisce un documento di base con il **titolo** risolto come H1, la **descrizione** e il contenuto restituito da **`getContentForSEO()`**:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning Il contenuto viene servito così com’è
Il fallback emette `getContentForSEO()` senza modificarlo. Se il contenuto è HTML, implementa `toSeoMarkdown()` per controllare la conversione. Per disattivare del tutto il fallback, imposta `seo.markdown_for_bots.build_from_content = false`.
:::

## Configurazione {#configuration}

```php
// config/seo.php
'markdown_for_bots' => [
    'enabled'            => false,    // off by default; the middleware isn't registered until true
    'auto_register_middleware' => true,
    'serve_to_known_bots' => false,   // also serve to known AI crawlers by user-agent
    'query_param'        => 'format', // the ?format=md trigger
    'query_value'        => 'md',
    'build_from_content' => true,     // build from getContentForSEO() when no toSeoMarkdown()
],
```

Lascia `serve_to_known_bots` disattivato per usare soltanto i segnali espliciti `Accept` e `?format`. Attivalo per fornire Markdown anche a GPTBot, ClaudeBot, PerplexityBot e agli altri user-agent identificati dal [catalogo dei crawler AI](/it/guide/ai-crawlers), anche quando non lo richiedono.
