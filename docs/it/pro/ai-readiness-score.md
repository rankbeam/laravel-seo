---
description: "Un secondo punteggio deterministico, separato da quello SEO: la misura definita da Rankbeam per valutare i segnali tecnici di accesso e lettura dei contenuti da parte dei crawler AI."
---

# Il punteggio AI-Readiness: un secondo indicatore deterministico {#the-ai-readiness-score-—-a-second-deterministic-axis}

La scansione Pro assegna a ogni pagina un **punteggio AI-Readiness da 0 a 100** accanto al [punteggio SEO](/it/pro/scoring). Valuta i segnali tecnici che possono aiutare crawler AI e motori di risposta a raggiungere, leggere e attribuire il contenuto. **Non viene mai combinato con il punteggio SEO organico**: ciascun indicatore ha criteri, versione e colonna propri.

::: warning Come interpretare il numero
AI-Readiness è una **misura deterministica di compatibilità tecnica definita da Rankbeam**: controlla la presenza e la forma dei segnali raccolti dalla scansione. **Non prevede posizionamento, indicizzazione, inclusione o citazioni** nei sistemi di ricerca o AI e non garantisce questi risultati. `air_llms_txt` assegna punti a un file di compatibilità `llms.txt` **facoltativo**, destinato ai sistemi che scelgono di leggerlo. Non è richiesto da Google Search né costituisce un segnale di posizionamento.
:::

Come il punteggio SEO, è **deterministico e riproducibile**: ogni punto deriva da un controllo nominato, e gli stessi segnali, con gli stessi criteri, producono lo stesso numero. **Il calcolo non effettua chiamate AI.** Puoi risalire dal risultato ai controlli che lo compongono, senza dipendere da risposte campionate di un modello linguistico.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip Due indicatori separati
`AI-readiness: 74/100` compare accanto a `SEO: 82/100`; nessuno modifica l'altro. AI-Readiness usa le colonne `ai_readiness_*` di `seo_scan_results`. Come per il punteggio SEO, **il numero è una funzione Pro**: il comando gratuito [`seo:audit`](/it/guide/audit) non assegna punteggi.
:::

## Punti assegnati, non penalità {#additive-credit-not-penalty}

Il [punteggio SEO](/it/pro/scoring) parte da 100 e **sottrae** penalità. AI-Readiness parte da **0** e **assegna** in tutto o in parte il peso di ciascun controllo. Una pagina priva dei segnali valutati ottiene quindi un valore vicino a zero. I pesi sommano esattamente **100**.

## I criteri di valutazione {#the-rubric}

Il calcolo usa criteri **pubblicati e versionati**, definiti da `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric`: dieci controlli in quattro categorie.

### A · Accesso e controllo dei bot: 30 punti {#a-·-bot-access-control-—-30-points}

Le regole consentono l'accesso ai crawler di ricerca AI e agli assistenti? Il controllo legge il **`/robots.txt` restituito dal sito** e valuta il **percorso della pagina scansionata**. Un `Disallow: /section` indica un divieto per le pagine di quella sezione anche se la radice è consentita; è una direttiva per i crawler che la rispettano, non un blocco di rete. Le finalità, training, ricerca e assistente, provengono dal [catalogo dei crawler AI](/it/guide/ai-crawlers).

| Controllo | Peso | Punti assegnati |
|---|---|---|
| `air_robots_reachable`: `robots.txt` disponibile e leggibile | 6 | presente / assente |
| `air_ai_search_access`: accesso consentito ai crawler di **ricerca AI**, che possono portare visite | 10 | quota di crawler consentiti |
| `air_ai_assistant_access`: accesso consentito ai crawler **assistente AI** | 8 | quota di crawler consentiti |
| `air_explicit_ai_policy`: regola esplicita in `robots.txt` per un bot AI noto | 6 | presente / assente |

::: tip Bloccare il training non riduce la valutazione
Vietare l'accesso ai bot di training, come GPTBot e CCBot, è una scelta legittima e **non comporta penalità**. La scelta sul training conta solo per `air_explicit_ai_policy`, che verifica una regola esplicita. Un sito che blocca i trainer e consente ricerca e assistenti può ottenere tutti i punti della categoria.
:::

### B · Individuazione dei contenuti: 20 punti {#b-·-discoverability-—-20-points}

| Controllo | Peso | Punti assegnati |
|---|---|---|
| `air_sitemap_discoverable`: sitemap XML raggiungibile **e** indicata da una direttiva `Sitemap:` | 12 | entrambe / una / nessuna condizione |
| `air_llms_txt`: `/llms.txt` valido, con titolo e link | 8 | valido / presente / assente |

### C · Contenuto leggibile automaticamente: 22 punti {#c-·-machine-readable-content-—-22-points}

| Controllo | Peso | Punti assegnati |
|---|---|---|
| `air_server_rendered_content`: testo sufficiente nell'HTML generato dal server, senza eseguire JavaScript | 14 | in base al numero di parole |
| `air_markdown_twin`: versione Markdown della pagina disponibile tramite content negotiation | 8 | presente / assente |

### D · Dati strutturati e organizzazione delle risposte: 28 punti {#d-·-structured-data-answer-readiness-—-28-points}

| Controllo | Peso | Punti assegnati |
|---|---|---|
| `air_schema_completeness`: JSON-LD presente, entità principale tipizzata, autore e data presenti quando previsti, in particolare per gli articoli | 18 | completo / parziale / assente |
| `air_answer_structure`: elementi che facilitano l'estrazione, come schema FAQ/QA/HowTo, gerarchia dei titoli, liste e apertura concisa | 10 | in base al numero di elementi |

Ogni controllo assegna punti **completi**, **parziali** o **zero**, oppure viene **saltato** quando non è stato possibile raccogliere il segnale necessario, per esempio un controllo sulla pagina senza recupero della pagina. Un controllo saltato vale zero ma viene indicato come tale: un dato non verificato non viene presentato come assenza confermata.

### Copertura dell'audit gratuito {#free-audit-reach}

La completezza dello schema, `air_schema_completeness`, può essere valutata dai dati strutturati del modello senza richieste HTTP, attraverso lo stesso percorso usato dall'audit gratuito per i controlli sulla struttura delle risposte. Gli altri nove controlli richiedono una scansione: il punteggio completo appartiene quindi alla **scansione Pro**.

## Ambito della valutazione ed esclusioni {#honest-scope-—-what-this-axis-excludes}

Questo indicatore valuta **segnali deterministici rilevabili nei contenuti di un sito**. Esclude i controlli sull'infrastruttura degli agenti, che riguardano l'applicazione in esecuzione o il DNS:

| Esclusione | Motivo |
|---|---|
| **DNS-AID**, record DNS per individuare agenti | Riguarda DNS e DNSSEC, non il contenuto di una pagina. |
| **Web Bot Auth**, firma delle richieste | Richiede uno scambio crittografico interattivo. |
| **Protocol Discovery**, come API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills e WebMCP | Richiede un'app, un'API o un server MCP in esecuzione. |
| **Commerce**, come x402, MPP, UCP e ACP | Riguarda protocolli di pagamento per agenti, fuori dall'ambito di questa valutazione dei contenuti. |

L'indicatore include invece la completezza delle entità schema e la struttura dei blocchi di risposta: segnali del contenuto utili a descriverne l'organizzazione e l'attribuzione, senza garantire che un motore li usi.

## Versionamento: lo storico non cambia in lettura {#versioning-—-historical-scores-never-silently-change}

Ogni punteggio salvato registra `AiReadinessRubric::VERSION` in `ai_readiness_version`. Modificare controlli, pesi o modalità di assegnazione richiede una **nuova versione**, così puoi ricostruire i criteri di ogni risultato. Il punteggio viene **salvato, non ricalcolato in lettura**. Le soglie che incidono sul numero, come parole ed elementi strutturali, sono costanti nel codice associate alla versione; non opzioni modificabili in configurazione.

::: warning Un input non fissato dalla versione
I controlli di accesso leggono il [catalogo dei crawler AI](/it/guide/ai-crawlers) **corrente** del pacchetto core. Un nuovo bot o una riclassificazione cambia gli input e può modificare i due sottopunteggi di accesso senza cambiare `AiReadinessRubric::VERSION`, che identifica i criteri, non il catalogo. Per confronti storici esatti, registra e fissa anche la versione del core.
:::

## Dove viene salvato {#where-it-s-stored}

Ogni scansione aggiorna le colonne AI-Readiness sulla **stessa riga** di `seo_scan_results` che contiene il punteggio SEO:

| Colonna | Contenuto |
|---|---|
| `ai_readiness_score` | Numero da 0 a 100; null finché il target non viene scansionato con l'indicatore attivo. |
| `ai_readiness_version` | Versione dei criteri usati. |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]`: dettaglio completo. |

Al termine della scansione, la media viene salvata in `seo_scan_runs.avg_ai_readiness`, come `avg_score`, per costruire l'andamento nel tempo.

## Leggere il punteggio {#reading-the-score}

**Senza Filament**: l'ultimo risultato di un modello contiene entrambi gli indicatori:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Con Filament**: aggiungi la colonna dedicata accanto al punteggio SEO in una tabella della risorsa:

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

Il valore compare anche come badge nella scheda del punteggio nella pagina, sopra il campo del titolo SEO, e in una sezione del [report personalizzabile](/it/pro/reports), PDF ed email, con numero, fascia, variazione dal report precedente e andamento per scansione. Rimane sempre separato dal punteggio organico.

### Fasce {#grade-bands}

La lettera è una rappresentazione del numero, che resta il valore di riferimento. Le fasce sono le stesse del punteggio SEO:

| Punteggio | Fascia |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Configurazione {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

Controlli e pesi **non sono configurabili**. A parità di input e `ai_readiness_version`, il risultato deve essere deterministico tra installazioni: modificare il calcolo richiede una modifica versionata nel codice.

::: warning I segnali del sito passano dal kernel HTTP dell'app
Per un target sullo stesso host, la scansione risolve `/robots.txt`, `/llms.txt` e la pagina tramite il kernel HTTP di Laravel nel processo corrente. Un `robots.txt` o `llms.txt` servito come **file statico**, senza passare dalle route Laravel, non viene rilevato. Per includerli nella valutazione usa le route del pacchetto, che sono la configurazione consigliata.
:::
