---
description: "Il registro stabile dei codici usati dalla scansione Pro: gravità e campo sono fissati per codice, così dashboard ed esportazioni non devono interpretare i messaggi."
---

# Problemi di scansione: il registro dei codici {#scan-issues-—-the-issue-code-registry}

Ogni problema della scansione Pro usa un **codice stabile** definito in `Rankbeam\Seo\Pro\Scanning\IssueRegistry`. Gli scanner creano i risultati tramite `IssueRegistry::make()`, che assegna gravità e campo dal registro e **rifiuta i codici non definiti**. Dashboard, esportazioni e [punteggio Pro](/it/pro/scoring) possono quindi leggere i codici senza interpretare messaggi tradotti. L'audit gratuito [`seo:audit`](/it/guide/audit) espone i propri codici di metadati nel core; la sua copertura è più limitata di questo catalogo Pro.

Ogni codice contiene:

- **id**: stringa stabile salvata in `seo_scan_issues.issue_type`.
- **severity**: `critical`, `warning` oppure `notice`, **fissata per codice**. Gravità diverse richiedono codici distinti.
- **field**: campo di `seo_meta` coinvolto, oppure *page* per i problemi della pagina.
- **execution class**: dati necessari per eseguire il controllo, descritti sotto.
- **evidence**: chiavi contenute nell'array `context` del problema.

## Classi di esecuzione {#execution-classes}

Ogni controllo appartiene a una delle tre classi seguenti:

| Classe | Richiede | Esecuzione |
|---|---|---|
| **metadata** | Modello e resolver core, senza recuperare la pagina | Scansione del modello con `PageScanner`; l'audit gratuito [`seo:audit`](/it/guide/audit) copre un sottoinsieme di controlli sui metadati |
| **rendered** | HTML restituito dalla pagina, tramite kernel nel processo o richiesta esterna | Scansione URL con `UrlScanner` |
| **network** | Richiesta **in uscita** per verificare una destinazione separata, come un canonical esterno | Scansione URL, sempre attraverso **`SsrfGuard`** |

L'audit gratuito nel processo non equivale alla scansione Pro completa: i controlli **metadata** non richiedono rendering, mentre Pro può leggere l'HTML e verificare destinazioni canonical e hreflang via rete. Per filtrare il registro Pro usa `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`.

## Codici dei metadati {#metadata-codes}

`PageScanner` li rileva dal modello e dal resolver. La scansione URL può emettere anche `missing_title`, `missing_description` e i codici di lunghezza, leggendo il `<head>` effettivamente restituito.

| Codice | Gravità | Campo | Evidenza | Significato |
|---|---|---|---|---|
| `missing_title` | critical | title | — | Nessun titolo esplicito né fallback calcolabile. |
| `missing_description` | warning | description | — | Nessuna meta description né fallback calcolabile. |
| `missing_og_image` | notice | og_image | — | Nessuna immagine Open Graph né fallback calcolabile. |
| `missing_focus_keyword` | notice | focus_keywords | — | Nessuna parola chiave principale impostata. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | Titolo riutilizzato da altre pagine nella stessa locale. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | Descrizione riutilizzata da altre pagine nella stessa locale. |
| `title_too_long` | warning | title | `length`, `max`, `script` | Titolo risolto sopra la soglia consigliata per l'alfabeto: 60 per il latino, circa 30 per CJK. |
| `title_too_short` | notice | title | `length`, `min`, `script` | Titolo risolto sotto la soglia minima: 30 per il latino, circa 15 per CJK. |
| `description_too_long` | warning | description | `length`, `max`, `script` | Descrizione risolta sopra la soglia consigliata: 160 o circa 80. |
| `description_too_short` | notice | description | `length`, `min`, `script` | Descrizione risolta sotto la soglia minima: 70 o circa 35. |
| `robots_conflict_indexing` | critical | robots | `robots` | Direttiva robots con `index` e `noindex` insieme. |
| `robots_conflict_following` | warning | robots | `robots` | Direttiva robots con `follow` e `nofollow` insieme. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | Pagina con canonical autoreferenziale e `noindex`, da verificare; il canonical da solo non dimostra che debba essere indicizzata. Emesso dalle scansioni modello e URL. |
| `invalid_canonical` | critical | canonical | `canonical` | Canonical non valido come URL. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | Canonical verso un host diverso da quello della pagina. |
| `shared_canonical` | notice | canonical | `canonical` | Più pagine dichiarano lo stesso canonical. |
| `insecure_canonical` | warning | canonical | `canonical` | Canonical `http://` su un sito `https`. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | Valore hreflang diverso da `x-default` e non riconosciuto come codice BCP-47 valido. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | Sono dichiarate alternative, ma nessuna riferisce la locale della pagina stessa. |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | Uno stesso codice hreflang è associato a più URL. |
| `hreflang_missing_x_default` | notice | alternates | `languages` | Gruppo multilingue senza fallback `x-default`. |
| `aeo_missing_author` | notice | schema | — | Articolo nei dati strutturati senza entità autore. |
| `aeo_article_missing_date` | notice | schema | — | Articolo nei dati strutturati senza data di pubblicazione o modifica. |

Le soglie di lunghezza usano la [politica del core per alfabeto](/it/guide/multilingual#title-and-description-budgets-per-script), adottata da Pro 2.33: 60/160 per il latino, circa 30/80 per CJK, contati in grafemi come nell'editor. Le soglie inferiori sono 30 per il titolo e 70 per la descrizione in latino, circa metà per CJK. Sono indicazioni del pacchetto, non limiti fissi dei risultati di ricerca. La chiave `script` identifica il gruppo usato. Si misura il valore **risolto**, inclusi fallback e suffisso del titolo.

I codici `hreflang_*` dei metadati controllano l'elenco `alternates` dichiarato dal resolver: codici non validi o duplicati, autoreferenza mancante e assenza di `x-default` in un gruppo multilingue. Si attivano solo quando esistono alternative. La **reciprocità tra pagine** richiede invece il controllo di rete facoltativo descritto sotto; non viene verificata da questi controlli sui metadati.

I codici `aeo_*` controllano la rappresentazione degli articoli nel JSON-LD risolto. Si attivano **solo** per tipi articolo, come `Article`, `BlogPosting` e `NewsArticle`, senza entità `author` o data `datePublished` / `dateModified`. Non segnalano pagine senza articoli. Dipendono da `seo-pro.scan.checks.aeo`, attivo per impostazione predefinita, e corrispondono ai controlli disponibili anche in [`seo:audit`](/it/guide/audit).

::: tip `missing_focus_keyword` richiede l'attivazione del campo
La segnalazione compare solo con il flusso delle parole chiave del **core** attivo: `seo.keywords.enabled`, predefinito `false`. Se è disattivato, la scansione non segnala la parola chiave mancante. L'audit gratuito e l'editor Filament leggono **lo stesso interruttore**.
:::

## Codici dell'HTML renderizzato {#rendered-codes}

`UrlScanner` li rileva nell'HTML restituito. Per target sullo stesso host usa il kernel nel processo, senza traffico in uscita; per quelli esterni usa una richiesta protetta.

| Codice | Gravità | Campo | Evidenza | Significato |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | Risposta HTTP 4xx o 5xx. |
| `empty_response` | critical | page | — | Corpo della risposta vuoto. |
| `missing_canonical` | notice | canonical | — | Nessun `<link rel="canonical">` nel head renderizzato. |
| `noindex_page` | notice | robots | `robots` | Pagina `noindex`, segnalazione informativa. Se ha anche un canonical **autoreferenziale**, viene emesso invece `noindex_warning`, che incide sul punteggio. |
| `missing_h1` | notice | page | — | Nessun titolo `<h1>`. |
| `multiple_h1` | notice | page | `count` | Più di un `<h1>`, segnalazione informativa. |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | Immagini del contenuto senza attributo `alt`; `alt=""` esplicito viene trattato come decorativo e non segnalato. |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter` | Testo sotto la soglia configurata. Il conteggio usa il tokenizer della checklist e ICU per cinese, giapponese e thailandese, con `segmenter: intl` ed ext-intl. Se la segmentazione necessaria non è disponibile, il verdetto viene sospeso, come descritto nella guida alla checklist. |
| `mixed_content` | warning | page | `count`, `sample` | Sottorisorse `http://` in una pagina `https`. |
| `html_lang_missing` | notice | page | — | `<html lang>` assente o vuoto; può impedire alle tecnologie assistive di scegliere la pronuncia corretta. |
| `html_lang_invalid` | notice | page | `declared` | `lang` non valido come tag BCP-47, per esempio `english`, `en_US` o `jp`. |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script` | Alfabeto del testo visibile incompatibile con la lingua dichiarata, come `lang="en"` su testo giapponese. Controllo solo per alfabeto, senza indovinare la lingua tra lingue latine; richiede almeno 40 lettere. |

## Codici delle verifiche di rete {#network-codes}

`UrlScanner` esegue queste verifiche solo con l'opzione corrispondente attiva: `seo-pro.scan.url_checks.check_canonical_target` per il canonical e `check_hreflang_reciprocity` per le alternative hreflang. Ogni destinazione passa da **`SsrfGuard`**, con schemi e host consentiti, rifiuto degli IP privati e limiti di tempo e dimensione. I redirect non vengono seguiti, così restano rilevabili. Canonical e alternative autoreferenziali vengono saltati perché la pagina è già stata recuperata.

| Codice | Gravità | Campo | Evidenza | Significato |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | Destinazione rifiutata da `SsrfGuard` prima della richiesta HTTP. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | Destinazione canonical con errore HTTP. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | Canonical verso un redirect; valuta l'URL finale. |
| `canonical_target_noindex` | warning | canonical | `canonical` | Destinazione canonical a sua volta `noindex`. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | Destinazione non verificabile per rifiuto della protezione o mancata risoluzione. |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status` | L'alternativa non dichiara il link di ritorno. La coppia hreflang può essere ignorata; ciò non rende di per sé la traduzione non indicizzabile. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason` | Destinazione non recuperabile per protezione, errore, redirect o dimensione; reciprocità non verificata, non difetto confermato. |

La verifica recupera al massimo `hreflang_max_alternates` destinazioni per pagina, 10 per impostazione predefinita, includendo `x-default` ed escludendo duplicati e pagina corrente. I codici dei metadati verificano l'elenco **dichiarato**; solo questo controllo legge l'altra pagina.

Tutti i percorsi di rete riusano `SsrfGuard`. [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md) del repository Pro descrive il modello di minaccia e il rischio residuo TOCTOU.

## Come i codici incidono sul punteggio {#how-codes-feed-the-score}

Il [punteggio SEO Pro](/it/pro/scoring) parte da 100 e sottrae una penalità fissa per ogni problema incluso nei criteri. Sono esclusi `missing_focus_keyword`, consultivo, `noindex_page` e `multiple_h1`, informativi, e `blocked_url`, `canonical_target_blocked` e `hreflang_target_unverified`, che indicano verifiche non riuscite. Anche `hreflang_*`, `html_lang_*` e `aeo_*` restano esclusi dal punteggio. La [guida al calcolo](/it/pro/scoring) contiene l'elenco completo e le penalità.

## Ciclo di vita dei problemi {#issue-lifecycle}

La scansione **riconcilia** i problemi del target anziché cancellarli e ricrearli. L'identità stabile combina target, `scannable_type` e `scannable_id` per un modello oppure `url` per route e sitemap, e `issue_type`. Ogni codice viene emesso al massimo una volta per target e scansione. I controlli con più occorrenze, come `missing_image_alt`, `mixed_content` e `hreflang_*`, le raccolgono in una riga con conteggi, campioni o elenchi di evidenze.

A ogni scansione del target:

- Un risultato **senza riga precedente** viene creato `open` con `detected_at`.
- Un risultato che **corrisponde a una riga aperta** aggiorna le evidenze e conserva il primo `detected_at`.
- Un problema aperto che la scansione **non rileva più**, quando il controllo è stato completato, diventa **`fixed`** con `resolved_at`. La riga **rimane salvata**.
- Un problema **`fixed` che ricompare** viene **riaperto** sulla stessa riga, aggiornando `detected_at`.
- Un problema segnato **`ignored`** dall'utente nel dashboard viene lasciato invariato.

| Stato | Significato | Impostato da |
|---|---|---|
| `open` | Presente al controllo. | Scansione, per risultati nuovi o ancora presenti |
| `fixed` | Presente in precedenza, non più rilevato. | Scansione successiva che completa il controllo senza ritrovarlo |
| `ignored` | Silenziato dall'utente; escluso da conteggi aperti e punteggio. | Azione Ignore nel dashboard |

Lo storico permette al [report](/it/pro/reports) di mostrare **correzioni e nuovi problemi nel periodo**, senza affidarsi solo alla differenza tra snapshot. Dashboard, [`seo-pro:scan-status`](/it/pro/headless) e [punteggio](/it/pro/scoring) considerano i problemi `open`, quindi le righe `fixed` non ne aumentano i conteggi. Le righe risolte vengono associate alla scansione che le ha chiuse e seguono la normale [conservazione delle esecuzioni](/it/pro/production).

## Configurazione {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

Le richieste protette usano `seo-pro.http.max_response_bytes`, predefinito 2 MB. La scansione dello stesso host attraverso il kernel nel processo non applica questo limite.

## Compatibilità: rinomina dei codici {#compatibility-note-issue-code-rename}

Il precedente `robots_conflict`, che poteva avere due gravità, è stato diviso affinché ogni codice abbia una sola gravità:

| Vecchio codice | Nuovo codice | Gravità |
|---|---|---|
| `robots_conflict`, index + noindex | `robots_conflict_indexing` | critical |
| `robots_conflict`, follow + nofollow | `robots_conflict_following` | warning |

Se salvi o filtri `robots_conflict`, passa ai due nuovi codici.
