---
description: "Il punteggio SEO Pro da 0 a 100 è deterministico e verificabile: ogni detrazione corrisponde a un problema di scansione e gli stessi problemi producono lo stesso numero."
---

# Punteggio SEO: criteri trasparenti, versionati e gestiti da Pro {#the-seo-score-—-transparent-versioned-pro-owned}

La scansione Pro assegna a ogni pagina un **punteggio SEO da 0 a 100**, familiare a chi proviene da Rank Math o Yoast. Il calcolo è **verificabile**: ogni punto sottratto deriva da un [problema di scansione](/it/pro/scan-issues), e lo stesso insieme di problemi produce sempre lo stesso risultato.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip Un solo responsabile del punteggio
Il punteggio numerico è una funzione **Pro**. Viene salvato in `seo_scan_results`, non nel `seo_meta` del core; la vecchia colonna `seo_score` è stata rimossa nel core 3. L’audit gratuito [`seo:audit`](/it/guide/audit) restituisce **pass, warn o fail** per pagina, **senza numero**.
:::

## I criteri di calcolo {#the-rubric}

Il punteggio usa criteri **pubblici e versionati**, definiti da `Rankbeam\Seo\Pro\Scanning\ScoreRubric`: un **elenco esplicito** dei codici conteggiati e una **penalità fissa per gravità**.

| Gravità | Penalità | Significato |
|---|---|---|
| `critical` | **−40** | Segnalazione di impatto elevato secondo questi criteri. |
| `warning` | **−15** | Segnalazione da esaminare a breve. |
| `notice` | **−5** | Miglioramento consigliato. |

La gravità di ogni codice viene letta dal [registro dei problemi](/it/pro/scan-issues), senza ricalcolarla nei criteri. Ogni codice ha una sola gravità, così il punteggio resta deterministico.

### Problemi conteggiati {#what-the-score-counts}

I codici sotto contribuiscono al punteggio secondo i controlli e le interpretazioni del prodotto: 40 punti per un critico, 15 per un avviso e 5 per una segnalazione informativa.

| Codice | Gravità | Penalità |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

I codici dei metadati vengono rilevati nelle scansioni di modello, quelli dell’HTML e della rete nelle scansioni URL: vedi le [classi di esecuzione](/it/pro/scan-issues#execution-classes). Il punteggio di una destinazione **modello** riflette quindi i controlli sui metadati; quello di una destinazione **URL** riflette la pagina generata. Un modello con 100 indica che quei controlli non hanno rilevato difetti nei metadati, non che l’HTML sia perfetto. Per verificarlo, analizza anche l’URL.

### Problemi esclusi dal punteggio {#what-the-score-deliberately-does-not-count}

Questi codici del registro sono esclusi intenzionalmente. Le esclusioni fanno parte del contratto: un test verifica che ogni codice sia conteggiato oppure elencato qui.

| Codice | Motivo dell’esclusione |
|---|---|
| `missing_focus_keyword` | **Consiglio editoriale.** Dipende dal flusso facoltativo `seo.keywords.enabled`. Una pagina non deve perdere punti perché non adotta le parole chiave principali, né il punteggio deve dipendere da quell’opzione. |
| `noindex_page` | **Informativo.** `noindex` può essere intenzionale. La combinazione con un canonical autoreferenziale viene conteggiata da `noindex_warning`; è un’euristica da verificare, non la prova che la pagina debba essere indicizzata. |
| `multiple_h1` | **Informativo.** Più H1 non comportano una penalità in questi criteri; Google può gestire pagine con più H1. |
| `blocked_url` | **Verifica non eseguita.** `SsrfGuard` ha rifiutato la richiesta, quindi non è stato accertato un difetto della pagina. |
| `canonical_target_blocked` | **Verifica non eseguita.** La destinazione canonical non è stata controllata; non è un difetto accertato della pagina. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Consigli, per ora.** I controlli hreflang compaiono nella scansione e nell’audit gratuito, ma non modificano il punteggio. Includerli richiederebbe un incremento di `VERSION`. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **Consultivi.** I controlli della lingua non incidono su questi criteri. |
| `hreflang_not_reciprocal` | **Consultivo.** Il controllo facoltativo della reciprocità non incide sul punteggio. |
| `hreflang_target_unverified` | **Evidenza assente.** La reciprocità non è stata verificata. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Consigli.** I segnali AEO indicano un articolo senza entità autore o data di pubblicazione nella scansione e nell’audit gratuito, ma non modificano il punteggio. Includerli richiederebbe un incremento di `VERSION`. |

Densità delle parole chiave, parole persuasive e altri controlli della [checklist della pagina](/it/pro/on-page-checklist) non entrano nel punteggio. Sono consigli in un elenco pass/warn/fail separato, non codici del registro.

## Versionamento: i risultati salvati non cambiano silenziosamente {#versioning-—-historical-scores-never-silently-change}

Ogni punteggio salvato riporta il `ScoreRubric::VERSION` che lo ha prodotto, nella colonna `rubric_version`. Ne derivano due regole:

- Un **nuovo codice** non incide sul punteggio finché non viene aggiunto esplicitamente all’elenco. Un nuovo controllo non cambia retroattivamente i risultati salvati. Modificare l’elenco o i pesi richiede una nuova versione dei criteri.
- Il punteggio viene **salvato e non ricalcolato in lettura**. Il numero di una scansione resta quello, accompagnato dalla versione che ne spiega il calcolo.

## Dove viene salvato {#where-it-s-stored}

Ogni scansione esegue un upsert di una riga per destinazione in `seo_scan_results`:

| Colonna | Contenuto |
|---|---|
| `scannable_type` / `scannable_id` | Modello valutato; nulli per destinazioni URL. |
| `url` | URL valutato. |
| `score` | Numero da 0 a 100. |
| `rubric_version` | Versione dei criteri usati. |
| `penalty_total` | Somma delle penalità **prima** di applicare il limite minimo di zero. |
| `scored_issues` | Numero di problemi che incidono sul risultato. |
| `breakdown` | Traccia completa: `[{code, severity, penalty}, …]`. |
| `keywords_enabled` | Valore di `seo.keywords.enabled` al momento della scansione, registrato per trasparenza senza influire sul punteggio. |
| `scan_run_id` | Esecuzione che ha prodotto il risultato. Diventa nullo quando il run viene eliminato, senza cancellare il punteggio: la tabella conserva lo stato corrente, non la cronologia dei run. |
| `scored_at` | Data del calcolo. |

## Leggere il punteggio {#reading-the-score}

**Senza pannello**, per l’ultimo punteggio di un modello:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` mostra il **punteggio medio del sito** nel riepilogo. La dashboard Filament lo presenta nella statistica del punteggio SEO medio, con colore associato alla fascia.

### Fasce di valutazione {#grade-bands}

La lettera è una rappresentazione del numero; il contratto rimane il valore numerico:

| Punteggio | Fascia |
|---|---|
| 90–100 | A |
| 75–89 | B |
| 50–74 | C |
| 25–49 | D |
| 0–24 | F |

## Segnale di pubblicazione: `noindex_warning` {#the-shipping-signal-noindex-warning}

`noindex_warning` compare quando una pagina dichiara `noindex` **e** presenta un segnale che Rankbeam interpreta come intenzione di indicizzarla. Il segnale è ricavabile dai metadati: un **canonical autoreferenziale**, che indica l’URL della pagina stessa. Un canonical *su un altro dominio* delega invece il riferimento altrove e non attiva questo avviso. Il problema contiene `context.shipping_signal`, per esempio `self_canonical`, e i valori `canonical` e `page_url` confrontati. È un’euristica da verificare: un canonical autoreferenziale non dimostra da solo che la pagina debba essere indicizzata.

Entrambi gli scanner applicano questa regola. `PageScanner` confronta il canonical salvato con l’URL del modello; `UrlScanner` passa da `noindex_page` informativo a `noindex_warning` conteggiato quando la pagina noindex ha un canonical autoreferenziale. Per questo `noindex_page` è escluso dal punteggio: il possibile conflitto viene gestito da `noindex_warning` in entrambi i percorsi.

## Configurazione {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

Elenco dei codici e pesi **non sono configurabili**. A parità di `rubric_version`, il calcolo deve essere deterministico in ogni installazione: per cambiarlo serve una modifica ai criteri nel codice, non un’impostazione.
