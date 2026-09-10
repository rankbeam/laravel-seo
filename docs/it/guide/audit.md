---
description: "Esegui php artisan seo:audit per controllare i metadati dei modelli, senza code, licenza o richieste di rete. Incluso nel core gratuito."
---

# Audit SEO gratuito (`seo:audit`) {#free-seo-audit-seo-audit}

`php artisan seo:audit` controlla i modelli `HasSEO` nello stesso processo, **senza code, licenza o richieste di rete**. Mostra una tabella **pass / warn / fail** per pagina e un riepilogo.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## Cosa controlla {#what-it-checks}

L'audit esegue solo i controlli della classe **metadata**, ricavabili dal modello e dal [resolver](/it/concepts/resolver-precedence), senza scaricare la pagina:

| Controllo | Codici |
|---|---|
| Presenza di titolo e descrizione, considerando i fallback | `missing_title`, `missing_description` |
| Presenza dell'immagine OG, considerando i fallback | `missing_og_image` |
| Lunghezza di titolo e descrizione | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Titoli o descrizioni duplicati nel sito | `duplicate_title`, `duplicate_description` |
| Direttive robots in conflitto e noindex da verificare | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Canonical malformati, tra domini, condivisi o non sicuri | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Dati strutturati degli articoli per AEO | `aeo_missing_author`, `aeo_article_missing_date` |
| Parola chiave principale impostata, se attivato | `missing_focus_keyword` |
| Elenco hreflang, quando presente; controlli solo core | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

I codici comuni hanno lo stesso significato nella scansione Pro. I codici hreflang qui elencati e `blank_explicit_override` sono specifici del core. La lunghezza segue i [limiti per sistema di scrittura](/it/guide/multilingual#title-and-description-budgets-per-script): 60/160 per il testo latino, circa 30/80 per CJK, contando i grafemi del valore **risolto**, suffisso incluso. La policy è la stessa dei contatori nell'[editor Filament](/it/guide/filament).

I controlli hreflang usano l'elenco dopo l'applicazione delle policy `seo.hreflang`, come i tag e la sitemap. La reciprocità richiede un crawl e resta una funzione Pro.

I controlli **AEO** si applicano solo alle pagine con JSON-LD di tipo articolo (`Article`, `BlogPosting`, `NewsArticle`, …) prive di `author` oppure di `datePublished` / `dateModified`. Una pagina senza articolo non riceve questi avvisi. Sono indicazioni di livello notice, escluse dal punteggio Pro 0–100.

## Cosa non controlla {#what-it-does-not-check-—-the-capability-boundary}

Il comando dichiara i propri limiti a ogni esecuzione. Non esegue:

- **Controlli sull'HTML servito:** `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content`, `mixed_content`.
- **Controlli di rete sul canonical:** `canonical_target_broken`, `canonical_target_redirect`, `canonical_target_noindex`. Richiedono una richiesta esterna protetta dai controlli di rete.
- **Calcolo del punteggio 0–100:** è una funzione Pro, salvata nel risultato della scansione con una versione della griglia di valutazione. Vedi [punteggio SEO](/it/pro/scoring).

Queste funzioni appartengono alla scansione Pro; consulta il [registro completo dei problemi](/it/pro/scan-issues).

## Scegli cosa controllare {#choosing-what-to-audit}

Il comando usa i modelli di `seo.audit.models`; se non sono indicati, usa `seo.sitemap.models`:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Puoi passarli anche esplicitamente:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Opzioni {#options}

| Opzione | Effetto |
|---|---|
| `--model=` | Classe con `HasSEO`, ripetibile; prevale sulla configurazione |
| `--locale=` | Lingua in cui risolvere i metadati; default: lingua dell'app |
| `--limit=` | Massimo di record per modello; `0` significa tutti |
| `--issues-only` | Mostra solo le pagine con almeno un problema |
| `--strict` | Restituisce un codice di uscita diverso da zero se trova problemi |
| `--json` | Restituisce pagine, riepilogo e copertura in JSON |

### Uso nella CI {#ci-gate}

`--strict` permette di usare l'audit come controllo della build:

```bash
php artisan seo:audit --strict
```

Il codice di uscita è `1` se una pagina ha avvisi o errori, `0` se tutte le pagine controllate passano.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Parole chiave principali {#focus-keywords}

L'avviso `missing_focus_keyword` è **disattivato per impostazione predefinita**. Si attiva quando abiliti il relativo flusso:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

La scansione Pro legge la stessa opzione dell'audit e dell'editor. Imposta le parole chiave tramite il [campo Filament](/it/guide/filament) oppure con `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Quando un valore sorprende: `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` segnala i problemi. [`seo:explain`](/it/guide/explain) spiega come è stato risolto ogni campo: quale livello ha prevalso, cosa ha sovrascritto e quali trasformazioni successive hanno modificato il risultato, per esempio suffisso, query del canonical o protezione dell'indicizzazione.

```bash
php artisan seo:explain "App\Models\Post" 42
```
