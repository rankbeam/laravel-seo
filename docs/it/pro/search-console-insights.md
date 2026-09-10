---
description: "Cinque analisi dei dati Search Console: query vicine alle prime posizioni, opportunità CTR, sovrapposizioni tra pagine, gruppi di query e variazioni nel tempo."
---

# Analisi di Search Console {#search-console-insights}

Cinque report ricavano informazioni sulle parole chiave dai dati Search Console già disponibili: query vicine alle prime posizioni, clic inferiori al riferimento atteso, sovrapposizioni tra pagine, query associate a ogni pagina e variazioni nel tempo. Usano la cronologia sincronizzata e una richiesta live, senza richiedere un servizio esterno di ricerca delle parole chiave o un costo per query.

La funzione si basa sull’[integrazione Search Console in sola lettura](/it/pro/search-console) e sulla sua sincronizzazione storica. Se `seo-pro:gsc-sync` è già attivo, tre delle cinque analisi funzionano **senza richieste API aggiuntive**.

::: tip Prerequisito
Le tre analisi basate su *snapshot* leggono la cronologia salvata in `seo_gsc_metrics`. Pianifica prima `seo-pro:gsc-sync`, come descritto in [Search Console: cronologia](/it/pro/search-console). Più giorni hai sincronizzato, più ampio sarà il confronto disponibile.
:::

## Le cinque analisi {#the-five-surfaces}

### 1. Query vicine alle prime posizioni {#_1-striking-distance-keywords}

Query con **posizione media ponderata per impressioni tra 5 e 20**, ordinate per impressioni. Sono candidate per una revisione di pertinenza e link interni; l’intervallo non garantisce che un intervento porti la pagina tra i primi risultati.

### 2. Opportunità CTR {#_2-ctr-opportunities}

Query che **hanno una buona posizione ma un tasso di clic inferiore al riferimento atteso**. Il CTR reale viene confrontato con una curva di riferimento per posizione. Le query con impressioni effettive e CTR molto inferiore alla curva diventano **candidate per una revisione di titolo e descrizione**, ordinate per stima dei *clic mancanti*. Puoi usare l’elenco con il [generatore di suggerimenti AI](/it/pro/ai-assist) per scegliere quali metadati rivedere.

### 3. Sovrapposizioni tra pagine {#_3-cannibalization}

Query associate a **due o più URL del sito**. La sovrapposizione non è necessariamente dannosa: il report la rende visibile per valutare se le pagine debbano essere accorpate oppure distinguersi meglio per contenuto e intento.

### 4. Gruppi di query {#_4-query-clusters}

Le **query per cui ogni pagina compare nei risultati**, raggruppate per pagina. Aiutano a individuare contenuti che si stanno allontanando dall’argomento previsto oppure compaiono per un termine utile che non avevi considerato.

### 5. Variazioni rispetto al periodo precedente {#_5-trend-vs-previous-period}

Le **variazioni maggiori** di clic, impressioni, posizione e CTR nella finestra corrente rispetto alla finestra immediatamente precedente di uguale durata. La posizione viene confrontata solo quando la query ha traffico in entrambi i periodi: una query nuova o completamente assente non offre due valori confrontabili.

## Origine dei dati: richieste live e snapshot {#where-the-numbers-come-from-live-vs-snapshot}

Ogni analisi usa la sorgente che risponde alla domanda con meno richieste. La cronologia salvata non ricostruisce le coppie **query e pagina**, perché conserva le dimensioni separatamente. Le due analisi che richiedono quelle coppie sono quindi le sole a usare dati live e **condividono una richiesta in cache**.

| Analisi | Sorgente | Motivo |
|---|---|---|
| Query vicine alle prime posizioni | **Snapshot locale** | Posizione e impressioni per query sono già nella cronologia; nessuna richiesta API. |
| Opportunità CTR | **Snapshot locale** | Gli stessi dati locali; la curva CTR è un riferimento statico, non una ricerca esterna. |
| Variazioni nel tempo | **Snapshot locale** | Serve la cronologia giornaliera effettivamente salvata dalla sincronizzazione. |
| Sovrapposizioni | **Live**, query × pagina | Le coppie non sono archiviate; salvarle tutte moltiplicherebbe i dati conservati. |
| Gruppi di query | **Live**, con la stessa richiesta dell’analisi precedente | Stesse coppie, raggruppate per pagina anziché per query. |

Aprire la pagina delle analisi richiede **al massimo una chiamata Search Analytics**, memorizzata per `search_console.cache_ttl` secondi. Le coppie live descrivono la situazione disponibile al momento della richiesta; la cache limita le chiamate ripetute. Il rinnovo del token può richiedere un’ulteriore richiesta di autenticazione e restano applicabili le quote Google. Le analisi basate su snapshot non usano la rete.

## Nella dashboard {#in-the-dashboard}

Con il plugin Filament installato, **Analisi di Search Console** compare nel gruppo di navigazione *SEO* quando l’integrazione è abilitata. È di sola lettura: ogni analisi ha una sezione; quelle basate su snapshot mostrano un invito a sincronizzare la cronologia se mancano dati. Una richiesta live fallita produce un avviso ripulito dai dettagli sensibili, senza bloccare l’intera pagina.

## Configurazione {#configuration}

Le opzioni si trovano in `search_console.insights` dentro `config/seo-pro.php`. Adatta le soglie alle dimensioni del sito.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info Curva del CTR atteso
La curva è un’**euristica** ricavata da medie pubblicate del CTR organico per posizione. È un riferimento, non una previsione specifica per il tuo sito. Una query segnalata è una *candidata da esaminare*, non un difetto accertato. Se hai una curva misurata sul tuo sito, inseriscila in `insights.ctr_curve` come mappa `position => percent`.
:::

## Vedi anche {#see-also}

- [Search Console](/it/pro/search-console): integrazione in sola lettura e sincronizzazione della cronologia.
- [Report personalizzabili](/it/pro/reports): variazioni tra periodi nel PDF con il tuo marchio.
- [Assistenza AI](/it/pro/ai-assist): riscrittura di titoli e descrizioni individuati dall’analisi CTR.
