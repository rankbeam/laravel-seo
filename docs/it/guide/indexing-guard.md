---
description: "Collega le direttive di indicizzazione all’ambiente Laravel: negli ambienti esclusi dall’elenco consentito, la protezione forza noindex e blocca i crawler nel robots.txt gestito."
---

# Protezione dall’indicizzazione negli ambienti non di produzione {#indexing-guard-non-production-safety-net}

Una copia locale o di staging che finisce nell’indice di Google può creare contenuti duplicati in concorrenza con le pagine pubbliche, esporre nell’indice un ambiente privato e richiedere interventi di rimozione. Può bastare un `noindex` impostato soltanto in un `.env` dimenticato o una regola robots sovrascritta durante il deploy.

La **protezione dall’indicizzazione** collega le direttive all’*ambiente* Laravel, anziché a un’opzione da ricordare a ogni deploy. Quando l’app viene eseguita in un ambiente escluso dall’elenco consentito, forza `noindex,nofollow` su ogni pagina, blocca tutti i crawler nel `robots.txt` gestito e segnala la situazione in `seo:audit`.

È una funzione del core gratuito.

## Cosa succede quando è attiva {#what-it-does-when-active}

Quando `app()->environment()` **non** è incluso in `seo.indexing_guard.allowed_environments` e la protezione è abilitata, vengono applicate quattro misure:

1. **Il resolver forza `noindex,nofollow` su ogni pagina.** La misura viene applicata *sopra* l’intera [catena di priorità](/it/concepts/resolver-precedence), anche rispetto a un valore `robots` esplicito salvato in `seo_meta`.
2. **L’header HTTP `X-Robots-Tag: noindex,nofollow`** viene inviato su ogni risposta che passa dall’applicazione. Vedi [Risposte non HTML](#non-html-responses-pdfs-feeds-images).
3. **`SEO::robotsTxt()->build()` genera un `robots.txt` che blocca tutto**, e lo stesso vale per `ai.txt`: `User-agent: *` con `Disallow: /`. Questo comportamento si applica sia al comando `seo:robots-txt` sia alla [rotta dinamica](/it/guide/ai-crawlers) facoltativa.
4. **`seo:audit` mostra un avviso evidente**, così il fatto che tutte le pagine siano `noindex` risulta chiaro nel report.

Negli ambienti consentiti, che includono `production` nella configurazione predefinita, la protezione è **inattiva** e il rendering resta identico.

## Risposte non HTML: PDF, feed e immagini {#non-html-responses-pdfs-feeds-images}

Il **meta tag** `robots` raggiunge soltanto i crawler che analizzano HTML. PDF, feed RSS o Atom, immagini e altre risposte non HTML non hanno un `<head>`. Quando è attiva, la protezione invia quindi la stessa direttiva come header HTTP tramite un middleware globale:

```http
X-Robots-Tag: noindex,nofollow
```

Header e meta tag provengono dalla stessa sorgente. L’header è **abilitato per impostazione predefinita all’interno della protezione**, che va invece attivata esplicitamente ed è inattiva negli ambienti consentiti. Per mantenere solo il meta tag:

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Il middleware viene registrato **solo quando la protezione è abilitata**. Se è disattivata, non aggiunge middleware allo stack.

::: warning I file statici non passano da PHP
Un file restituito direttamente dal web server da `public/` non entra in Laravel e non può ricevere questo header. Proteggi quei file nella configurazione del web server o della CDN. Il middleware copre le risposte che passano dall’applicazione.
:::

## Perché prevale su un valore robots esplicito {#why-it-overrides-an-explicit-robots-value}

Nel resto di Rankbeam un valore esplicito salvato ha la precedenza: è il principio della catena di risoluzione. La protezione è un’eccezione intenzionale e si colloca *sopra* il livello esplicito:

- Un database di staging è spesso una copia della produzione. Una pagina con `index,follow` conserverebbe quindi quella direttiva anche in staging.
- **Indicizzare per errore lo staging è un problema; applicarvi `noindex` non impedisce l’indicizzazione del sito di produzione.** Il valore salvato non può aggirare la protezione proprio negli ambienti che non vuoi indicizzare.

## Attivazione {#enabling-it}

La protezione è **disattivata** all’installazione. Installare o aggiornare il pacchetto non cambia quindi l’output degli ambienti non di produzione senza una scelta esplicita, come avviene per [`blank_is_unset`](/it/concepts/resolver-precedence) e per le immagini OG generate. Per attivarla:

```dotenv
SEO_INDEXING_GUARD=true
```

Con l’elenco predefinito, `production` resta escluso dall’intervento: puoi mantenere la protezione abilitata nella configurazione condivisa. È **fortemente consigliata** e candidata all’attivazione predefinita nel core 4.

Per disattivarla:

```dotenv
SEO_INDEXING_GUARD=false
```

## Scegliere gli ambienti indicizzabili {#choosing-which-environments-may-index}

Per impostazione predefinita è consentito soltanto `production`. Puoi sostituire l’elenco con una variabile d’ambiente contenente valori separati da virgole:

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

Oppure in `config/seo.php`:

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

Il confronto usa `Str::is()`, quindi accetta **caratteri jolly**: `'prod*'` corrisponde sia a `production` sia a `prod-eu`.

```php
'allowed_environments' => ['prod*'],
```

Un elenco **vuoto** non consente l’indicizzazione in alcun ambiente: la protezione interviene ovunque. Un valore vuoto o composto da spazi nella variabile `SEO_INDEXING_GUARD_ALLOWED` usa invece il fallback `['production']`, evitando che un errore di configurazione applichi silenziosamente `noindex` in produzione. Per intervenire davvero ovunque, imposta esplicitamente `[]` nella configurazione.

## Verifica {#verifying-it}

`seo:audit` mostra l’avviso e include lo stato leggibile dalle macchine nell’output `--json`:

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

Il `robots.txt` generato o servito in un ambiente protetto:

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Ambito {#scope}

La protezione controlla le **direttive di indicizzazione**: meta tag `robots`, header `X-Robots-Tag` e `robots.txt`. Non modifica titoli, descrizioni, canonical o schema. È indipendente dalla [policy di rendering dei robots](/it/concepts/resolver-precedence), `seo.robots.emit_default`: poiché `noindex,nofollow` differisce dal valore predefinito del sito, il tag viene sempre emesso.
