---
description: "I sei livelli con cui Rankbeam risolve i metadati: il livello superiore prevale e null non sovrascrive i valori inferiori."
---

# Priorità del resolver {#resolver-precedence}

Ogni valore SEO effettivo — titolo, descrizione, canonical, robots e immagini — deriva dall'unione di **sei livelli** operata da `SEOResolver`. Il livello superiore prevale; `null` non sovrascrive mai un valore proveniente da un livello inferiore.

## I sei livelli {#the-six-layers}

Dal livello più basso a quello con la precedenza massima:

| # | Livello | Sorgente | Uso tipico |
|---|---|---|---|
| 1 | **Configurazione del sito** | `config/seo.php`: `site_name`, `title_suffix`, `default_og_image`, `default_robots`, … | Valori comuni al marchio |
| 2 | **Default globali nel database** | Righe `seo_defaults` senza tipo di modello | Valori modificabili per tutto il sito senza deploy |
| 3 | **Default per tipo di modello** | Righe `seo_defaults` associate a una classe | Immagine OG comune a tutti i prodotti |
| 4 | **Default per rotta** | Righe `seo_defaults` associate al nome della rotta | Pagine statiche senza modello, come `home` e `contact` |
| 5 | **Valori calcolati** | Attributi del modello | Titolo da `title`, descrizione da `excerpt` o `body` |
| 6 | **Valori espliciti** | Riga `seo_meta` del modello, tramite `saveSEO()` | Valori inseriti dall'editor |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

Il risultato è un oggetto immutabile `SEOData`, usato da tutti i renderer: Blade, array e Inertia.

## Fallback calcolati: livello 5 {#computed-fallbacks-layer-5}

Quando manca un valore esplicito, il resolver lo ricava dal modello:

- **Titolo:** attributo `title` o `name`.
- **Descrizione:** primo attributo con testo significativo tra quelli di `seo.computed.description_fields`. La sequenza predefinita è `excerpt`, `summary`, `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`, `article`. Il sistema rimuove l'HTML, decodifica le entità e tronca al confine di una parola, senza puntini di sospensione. Il limite è `seo.computed.description_max_length`, pari a 160 per impostazione predefinita.
- **Robots:** hook `getSEORobots()` o attributo `is_indexable`, descritti sotto.
- **Valori derivati dall'URL:** canonical e `og:url` da `getUrlForSEO()`.

## Robots e indicizzabilità {#controlling-robots-and-indexability}

Il resolver gestisce `noindex` per modello. Il trait `HasSEO` non dichiara un metodo robots obbligatorio: l'hook è facoltativo. Le sorgenti hanno questa priorità:

| Priorità | Sorgente | Esempio |
|---|---|---|
| 1 | **`seo_meta.robots` esplicito** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | **Hook `getSEORobots(): ?string`** sul modello | Restituisce `'noindex, nofollow'`, oppure `null` per proseguire con il fallback |
| 3 | **Attributo `is_indexable`**, colonna o accessor | Falso ⇒ `noindex, nofollow`; vero ⇒ `index, follow` |

```php
class Page extends Model
{
    use HasSEO;

    // Option A: let the resolver derive robots from a boolean flag.
    //   Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));

    // Option B: compute it from your own state.
    public function getSEORobots(): ?string
    {
        return $this->status === 'draft' ? 'noindex, nofollow' : null;
    }
}

// Option C: set it explicitly per page (wins over A and B).
$page->saveSEO(['robots' => 'noindex, follow']);
```

### Quali tag vengono generati {#what-actually-renders}

Prima di arrivare al `<head>`, la direttiva risolta passa dalla policy di emissione. Il tag `<meta name="robots">` viene generato **solo quando la direttiva differisce da `default_robots`**, che vale `index,follow` per impostazione predefinita.

- Una pagina **indicizzabile**, risolta come `index, follow`, non genera un tag robots: la sua assenza indica già `index,follow` ai crawler.
- Una pagina **non indicizzabile** genera `<meta name="robots" content="noindex, nofollow">`.
- Le altre direttive diverse dal default, come `noindex`, `max-snippet:-1` e `unavailable_after`, vengono emesse mantenendo anche gli spazi inseriti.

Imposta `seo.robots.emit_default = true` per generare sempre il tag. Consulta la [policy robots](/it/reference/configuration#robots-rendering-policy) per i dettagli.

## Policy applicate dopo la risoluzione {#policies-applied-after-resolution}

Queste trasformazioni si applicano indipendentemente dalla sorgente del valore:

- **Suffisso del titolo:** `title_suffix` viene aggiunto se il titolo non termina già con quel suffisso. Se un template di rotta contiene il marchio, termina il template con il suffisso per evitare risultati come «Brand — X | Brand».
- **Query del canonical:** gli URL canonici derivati dal modello o dalla richiesta perdono la query, tranne le chiavi di [`canonical.query_whitelist`](/it/reference/configuration#canonical-urls), per esempio `page` negli archivi paginati. I canonical impostati esplicitamente restano invariati.
- **Immagini social assolute:** `og:image` e `twitter:image` vengono sempre emessi come URL assoluti, anche se il percorso salvato è relativo.

## Individua il livello che ha prevalso {#inspecting-which-layer-won}

Il [pacchetto Filament](/it/guide/filament) mostra la sorgente accanto a ogni campo: valore manuale, fallback del contenuto, default del tipo di modello, default globale, configurazione del sito o valore derivato dall'URL. `SEOWarningEvaluator` espone la stessa distinzione tra valore manuale e fallback per chi costruisce indicatori personalizzati.
