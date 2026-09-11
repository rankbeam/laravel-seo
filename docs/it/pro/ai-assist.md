---
description: "Assistenza AI facoltativa con la tua chiave: suggerimenti per titoli e descrizioni, spiegazioni dei problemi, riscritture e proposte schema.org. Disattivata per impostazione predefinita."
---

# Assistenza AI {#ai-assist}

Assistenza AI facoltativa con **la tua chiave API**: suggerimenti per titoli e meta description, spiegazioni dei problemi di scansione, **riscrittura della descrizione** e **proposte di dati strutturati schema.org**. È **disattivata per impostazione predefinita**: con l'interruttore spento, i percorsi di assistenza AI non vengono eseguiti.

La funzione segue tre criteri:

- **La tua chiave, il tuo provider.** Le richieste partono dal **tuo server** e arrivano al provider configurato: Anthropic, OpenAI, Google oppure un server locale o compatibile con OpenAI. Gli eventuali costi sono sul tuo account. Rankbeam non fa da proxy, non rivende le richieste e non invia telemetria.
- **Proposte interattive da accettare esplicitamente.** La scelta riempie il form; le correzioni della dashboard richiedono Apply. Da Pro 2.42 il comando massivo salva bozze private. `--auto-apply` abilita intenzionalmente la scrittura immediata.
- **Errori gestiti nel contesto.** Chiave mancante o non valida, credito esaurito, limiti e timeout producono un messaggio. Le funzioni di assistenza non impediscono il normale salvataggio, rendering o scansione; i comandi AI segnalano invece l'esito negativo come descritto sotto.

## Confronto dei provider {#providers-at-a-glance}

Scegli in base all'account disponibile, ai requisiti sui dati e al costo. Le quattro integrazioni espongono le stesse funzioni, ma supporto del modello, formato delle risposte, tempi e qualità possono variare.

| Provider | Modello predefinito nel pacchetto | Output strutturato | Costo indicativo dell'esempio | Uso |
|---|---|---|---|---|
| **Locale**, Ollama / LM Studio / vLLM | `llama3.1`, sostituibile | Best effort con `response_format` | **$0 di tariffa API** per inferenza ospitata da te; restano i costi dell'infrastruttura | Controllo della destinazione dei dati |
| **OpenAI** | `gpt-5.5` | Structured Outputs, se supportato dal modello | Circa $0.005 per suggerimento con le ipotesi della tabella di costo | Account OpenAI già disponibile |
| **Anthropic** | `claude-opus-4-8` | `output_config.format`, se supportato dal modello | Circa $0.015 per suggerimento con le stesse ipotesi | Account Anthropic già disponibile |
| **Google** | `gemini-2.5-flash` | `responseSchema`, se supportato dal modello | Circa $0.0005 per suggerimento con le stesse ipotesi | Account Google; verifica quote e tariffe del modello |

Questi nomi descrivono la configurazione distribuita, non garantiscono disponibilità attuale sul tuo account. I costi sono esempi basati sui valori del pacchetto, non preventivi alle tariffe correnti. Le note seguenti descrivono il comportamento delle integrazioni e osservazioni delle prove pubblicate:

- **Output strutturato.** OpenAI, Google e Anthropic ricevono uno schema JSON nei percorsi supportati; una risposta non valida produce un errore. Al server locale viene richiesto `response_format` come supporto best effort. Se lo ignora, il pacchetto tenta il parsing tollerante del testo e restituisce una lista valida oppure un errore, senza applicare risposte parziali.
- **I token di ragionamento cambiano il consumo.** Nelle prove descritte, una descrizione Gemini aveva circa 500 token di ragionamento oltre a circa 100 visibili; le chiamate Anthropic provate non riportavano token di ragionamento. Non è una proprietà universale delle famiglie di modelli. I token nascosti possono essere fatturati come output: per questo esiste la soglia minima di budget descritta sotto.
- **Il modello è configurabile.** Imposta `SEO_PRO_AI_MODEL` su un modello disponibile al tuo account o server e compatibile con l'API e i parametri dell'adattatore. Gli esempi includono `claude-haiku-4-5`, `gpt-5.4-mini` e `gemma-3-12b-it`; verifica supporto e qualità prima di usarli su un'intera raccolta.

## Configurazione iniziale {#setup}

Abilita la funzione e imposta la chiave del provider nell'ambiente. Per passare tra provider cloud aggiorna provider e chiave, verificando anche eventuali override del modello. Il provider locale richiede l'URL del server.

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip La chiave API è separata dagli abbonamenti Claude e ChatGPT
Un abbonamento Claude Code, Claude.ai o ChatGPT non finanzia automaticamente l'API. `SEO_PRO_AI_API_KEY` deve essere una chiave del servizio API del provider, oppure di Google AI Studio, con quote o credito propri. Una chiave API senza credito può autenticarsi ma restituire `quota_exceeded`; un valore che non è una chiave API valida può invece fallire l'autenticazione. Vedi [Risoluzione dei problemi](#troubleshooting).
:::

Il blocco `ai` di `config/seo-pro.php` espone `timeout`, `max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models`, `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model` per la [generazione massiva](/it/pro/ai-assist#cheaper-bulk-generation), `retry`, la tabella `pricing` e il blocco `local`. Vedi [Limiti e regolazioni](#limits-and-tuning).

::: warning Chiave e configurazione in cache
La configurazione contiene solo il **nome** della variabile, `api_key_env`, mai la chiave. `php artisan config:cache` non la scrive quindi in `bootstrap/cache/config.php`. Con la configurazione in cache Laravel non carica `.env`: rendi `SEO_PRO_AI_API_KEY` disponibile come variabile d'ambiente effettiva al processo del server e dei worker.
:::

## Inferenza locale e opzioni cloud {#running-at-0-and-the-cheapest-paid-option}

- **Un server locale evita la tariffa API per token.** L'inferenza che ospiti tu usa comunque hardware, energia e risorse operative. I dati restano nella tua rete solo se il server configurato e le sue dipendenze rimangono al suo interno.
- **Google ha quote e tariffe per modello e livello.** Una chiave AI Studio, `aistudio.google.com/apikey`, formato `AIza…`, può permettere prove nel livello gratuito, ma i limiti possono rendere inadatto il volume previsto. Verifica quote e disponibilità, poi abilita la fatturazione se necessaria. `gemini-2.5-flash` è il predefinito del pacchetto. Modelli Gemini e Gemma non hanno tutti le stesse capacità di ragionamento; la configurazione `reasoning_models` applica comunque i propri pattern di budget, senza certificare le capacità del modello scelto.

Per gestire direttamente dove viene eseguita l'inferenza:

- **Locale o compatibile con OpenAI.** Configura `provider=local` con un server che espone l'API OpenAI Chat Completions, come **Ollama**, **LM Studio**, **vLLM** o **LocalAI**, oppure un gateway remoto come **OpenRouter**. `SEO_PRO_AI_LOCAL_BASE_URL` indica la radice API, a cui viene aggiunto `/chat/completions`; `SEO_PRO_AI_MODEL` deve identificare un modello disponibile sul server. Un gateway remoto riceve i dati fuori dalla tua rete e può applicare tariffe: il nome dell'adattatore `local` non implica inferenza locale.

::: warning `base_url` viene validato; localhost richiede consenso esplicito
`base_url` è un'impostazione privilegiata verificata tramite `SsrfGuard`: solo HTTP/HTTPS, senza credenziali nell'URL e, per impostazione predefinita, risoluzione a un **indirizzo pubblico**. Un server su `127.0.0.1` richiede `seo-pro.ai.local.allow_local_addresses`, cioè `SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`. Lascia l'opzione disattivata per gateway pubblici come OpenRouter. Il percorso della richiesta è fisso e i redirect non vengono seguiti, per evitare l'inoltro della chiave a un altro host.
:::

::: tip Controllare il ragionamento nei modelli Ollama compatibili
Un modello locale con ragionamento può superare il timeout predefinito. Se il modello e la versione del server supportano l'opzione, `['think' => false]` in `seo-pro.ai.local.extra_body` può disabilitarlo nelle richieste di suggerimenti. Il supporto varia: verifica la [documentazione Ollama](https://docs.ollama.com/capabilities/thinking). Se necessario aumenta `seo-pro.ai.timeout`. Il pacchetto non invia `temperature`, perché alcuni modelli la rifiutano.
:::

## Costi {#cost}

Rankbeam non aggiunge una maggiorazione. Paghi il provider direttamente; con inferenza ospitata da te non c'è una sua tariffa API per token. Distingui il costo dei **suggerimenti interattivi** da quello della **compilazione massiva**.

La tabella `seo-pro.ai.pricing`, in USD per milione di token, converte i token stimati nell'importo mostrato prima della compilazione massiva. I valori seguenti sono **le ipotesi distribuite nel pacchetto**, non tariffe correnti verificate. Sostituiscili con i prezzi pubblicati dal tuo provider:

| Pattern del modello | Input $/1M | Output $/1M |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

Gli esempi pubblicati per una coppia di suggerimenti, titolo e descrizione, usano queste ipotesi e il consumo della pagina provata. L'ultima colonna applica lo sconto batch ipotizzato del 50% alle integrazioni supportate. Non rappresentano un preventivo attuale:

| Provider e modello dell'esempio | Circa, per coppia | Circa, per 1.000 record | Circa, per 1.000 record con `--batch` |
|---|---|---|---|
| Locale `gemma`/`llama`, Ollama | **$0 di tariffa API** | **$0 di tariffa API** | non supportato dall'adattatore |
| Google `gemini-2.5-flash`, a pagamento | ~$0.001 | ~$0.40 | non supportato dall'adattatore Rankbeam |
| OpenAI `gpt-5.5` | ~$0.008 | ~$5.25 | **~$2.63** |
| Anthropic `claude-opus-4-8` | ~$0.03 | ~$20 | **~$10** |

Il comando presenta la propria stima come approssimativa, con indicazione ±50%; **non è un limite di spesa né un intervallo garantito**. Dimensioni di input, lunghezza delle risposte e prezzi effettivi cambiano il totale. La stima considera l'output visibile: token di ragionamento fatturati possono aumentare il costo oltre il valore mostrato. Per modelli senza voce in `pricing`, mostra solo i token stimati, senza inventare un importo.

### Modello distinto per la compilazione massiva {#cheaper-bulk-generation}

Puoi usare un modello diverso per compilare centinaia di titoli e descrizioni mancanti. Imposta `seo-pro.ai.bulk_model`, tramite `SEO_PRO_AI_BULK_MODEL`: **solo** `seo-pro:ai-fill` e `SeoPro::aiFill()` lo usano. Filament e `seo-pro:ai-suggest` mantengono `model`. Con `null`, anche la compilazione massiva usa `model`; non cambia modello implicitamente. La stima usa il modello scelto per l'esecuzione. Valutane gli output su esempi rappresentativi prima di aumentare il volume.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Gli esempi del pacchetto usano **anthropic** `claude-haiku-4-5`, **openai** `gpt-5.5-mini`, **google** `gemini-2.5-flash` e, per **local**, un modello più piccolo. I pattern di prezzo possono riconoscere un nome anche se il provider non lo rende disponibile: verifica l'ID effettivo, il supporto dell'API e il prezzo prima di configurarlo.

Questo esempio di **100 pagine**, tutte senza titolo e descrizione, ipotizza 200 richieste, 600 token in input e 150 in output per chiamata, con i prezzi distribuiti:

| Provider | Modello interattivo, 100 pagine | Esempio `bulk_model`, 100 pagine |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **$4.05** | `claude-haiku-4-5` ≈ **$0.27** |
| **OpenAI** | `gpt-5.5` ≈ **$1.05** | `gpt-5.5-mini` ≈ **$0.11** |
| **Google** | `gemini-2.5-pro` ≈ **$0.45** | `gemini-2.5-flash` ≈ **$0.04** |
| **Locale**, Ollama / vLLM | qualunque modello, **$0 di tariffa API** | qualunque modello, **$0 di tariffa API** |

Restano stime illustrative, senza garanzia del margine ±50% e senza token di ragionamento nascosti. Aggiorna `seo-pro.ai.pricing` con le tariffe del provider per rendere la stima pertinente al modello scelto.

## Lingua dell'output {#output-language}

Ogni prompt indica nome della lingua e codice BCP-47 della pagina, per esempio “in portoghese brasiliano (pt-BR), la lingua della pagina, indipendentemente da altre lingue nell'estratto”. Il contesto include una riga `Language:` da Pro 2.34. In precedenza il prompt chiedeva la lingua del contenuto, lasciandola dedurre da estratti brevi o misti. La locale è quella con cui vengono risolti i metadati, oppure quella dell'app se manca. La [politica sulle lunghezze](/it/guide/multilingual#title-and-description-budgets-per-script) usa il valore risolto e il suo alfabeto: una pagina giapponese richiede titoli in giapponese con il relativo budget.

Da Pro 2.36, la locale esplicita del contenuto controlla insieme riga dei metadati, hook del contenuto e lingua del prompt. La lingua dell'interfaccia dell'operatore resta separata.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Gli argomenti posizionali esistenti non cambiano. Ometti `locale:` per usare il predefinito di `seoData()` del modello, utile ai modelli di traduzione separati. L'estratto usa `getContentForSEO()` quando non è vuoto, poi i campi configurati. Filament 1.11 passa automaticamente la locale della scheda selezionata, anche con una sola locale o con il selettore della pagina.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

Anche `plan()`, `fill()` e `submitBatchFill()` accettano `locale:` finale. Usa la stessa locale in `FillProgress(..., locale: 'it')` e nell'invio. Ogni elemento batch salva la propria locale; il recupero usa quel valore e ricontrolla la riga dei metadati prima di scrivere. Le esecuzioni con locale esplicita hanno checkpoint separati e marcatori distinti per lingua. Ripeti lo stesso comando per raccogliere i risultati. I job personalizzati devono serializzare e passare la locale del contenuto.

I checkpoint precedenti a Pro 2.36 non registravano la locale. Un batch precedente ancora aperto viene conservato, ma il recupero automatico viene rifiutato. Riconcilia risultati del provider e lingua prevista prima di eliminarne il riferimento con `--fresh`: un nuovo invio può fatturare di nuovo lo stesso lavoro. Anche un checkpoint sequenziale precedente con record elaborati richiede riconciliazione prima del reset.

### Valutazione per lingua {#per-language-evaluation}

Il repository sorgente Pro contiene 170 pagine di input in 17 locali e un sistema di valutazione facoltativo. Le pagine hanno controlli strutturali ed euristici della lingua base; l'approvazione madrelingua indipendente resta aperta.

Il sistema verifica **titoli e descrizioni**, registra lunghezze in grafemi ed evidenze di lingua e alfabeto, e conserva ogni risposta prima delle asserzioni. Titoli brevi, testi misti e caratteri condivisi tra cinese e giapponese possono restare incerti. Riconoscere il portoghese non dimostra un uso brasiliano; controlli parziali sui caratteri cinesi non certificano la qualità regionale.

Le prove reali richiedono `SEO_PRO_AI_EVAL=1`, una selezione esplicita `SEO_PRO_AI_EVAL_LOCALES` e un ID `SEO_PRO_AI_EVAL_RUN`. **Possono comportare costi del provider** e non partono per impostazione predefinita. Ogni esecuzione associa le evidenze a provider, modello richiesto e restituito, hash di fixture, richiesta e codice e timestamp. I tentativi falliti restano disponibili. La ripresa riusa le risposte salvate; una richiesta interrotta richiede un nuovo tentativo esplicito perché potrebbe essere già arrivata al provider.

Le evidenze si trovano in `storage/app/seo-ai-evals/<run-id>/` nell'ambiente di test del sorgente. Il `README.md` delle fixture descrive schema versionato e comandi. I revisori madrelingua valutano hash esatti degli output in record separati. Superare i controlli automatici non equivale ad approvazione madrelingua né garantisce testi pubblicabili.

## Limiti e regolazioni {#limits-and-tuning}

Le opzioni sono nel blocco `ai` di `config/seo-pro.php`:

- **`timeout`**, predefinito `15` secondi, `SEO_PRO_AI_TIMEOUT`: limite della chiamata sincrona usata anche all'apertura dei suggerimenti Filament. Un modello locale o con ragionamento può superarlo. Aumentalo se necessario e verifica l'eventuale supporto di `think => false` descritto sopra. Il timeout mostra un errore dell'assistenza senza impedire il normale salvataggio.
- **`max_input_chars`**, predefinito `6000`: limite del testo della pagina inviato per richiesta, dopo la rimozione dell'HTML, per contenere esposizione e consumo.
- **`max_output_tokens`**, predefinito `1000`: budget base di generazione. Una risposta che raggiunge il limite restituisce `truncated`, senza applicare un risultato incompleto.
- **`token_budgets`**: limiti per attività, `suggestions` 800, `explanation` 600, `rewrite` 300 e `schema_suggestion` 700. L'eventuale soglia del ragionamento viene applicata dopo.
- **`reasoning_models`** e **`reasoning_min_output_tokens`**, predefinito `2000`: i nomi che corrispondono ai pattern, come `*gemma*`, `gemini-2.5-*`, `o1*`, `o3*` e `o4*`, ricevono almeno quel budget. Serve a lasciare spazio ai token nascosti nei modelli che li usano; il pattern non verifica la capacità del modello.
- **`suggestion_count`**, predefinito `3`: alternative di titolo o descrizione da richiedere.
- **`retry`**: nuovi tentativi automatici per alcuni errori transitori; vedi [Gestione delle risposte](#how-replies-are-handled).

## In Filament {#in-filament}

Con i pacchetti Filament facoltativi, `rankbeam/laravel-seo-filament` >= 1.1, l'attivazione aggiunge:

- **Suggest with AI** sui campi titolo e descrizione delle risorse con sezione SEO, nelle pagine di modifica. La finestra mostra alternative e conteggi; selezionarne una riempie il campo da rivedere.
- **Explain (AI)** nella tabella dei problemi: spiegazione in linguaggio semplice e proposta di intervento.
- **Rewrite description (AI)** accanto a Explain: propone una descrizione entro il budget della pagina, 160 grafemi per il latino e circa 80 per CJK secondo la [politica core](/it/guide/multilingual#title-and-description-budgets-per-script). Rivedila nella finestra; **Apply rewrite** la salva in `seo_meta`. Prima dell'applicazione non viene scritta.
- **Suggest structured data (AI)**: propone un tipo supportato, Product, Article o Breadcrumb, e mostra il JSON-LD costruito dal pacchetto. **Apply structured data** lo aggiunge a `seo_meta.schema_jsonld`, la stessa colonna gestita dall'[editor dei dati strutturati](../guide/filament#structured-data-schema-org). Una proposta incompleta, per esempio Article senza autore o immagine richiesti dal validatore, mostra i campi mancanti e **non viene applicata**.

Le ultime due azioni sono descritte nelle [correzioni delimitate](#bounded-fixes-propose-never-auto-apply).

## Correzioni delimitate: proposta e applicazione esplicita {#bounded-fixes-propose-never-auto-apply}

Due azioni producono un singolo valore con vincoli, applicabile dopo revisione. **Propongono soltanto**: il salvataggio richiede accettazione esplicita.

- **Riscrittura della descrizione**, `SeoSuggestionService::rewriteDescription($model, $issue?)`: restituisce una descrizione entro il **budget della politica core per l'alfabeto della pagina**, 160 per il latino e circa 80 per CJK. Il budget viene scelto dal valore risolto della pagina, come nei prompt dei suggerimenti. Se il modello lo supera, il testo viene tagliato in modo deterministico al confine di frase, poi di parola. Passare il problema di scansione orienta la proposta, per esempio verso una descrizione mancante o troppo lunga. Il controllo della lunghezza non garantisce la qualità del testo tagliato: rileggilo prima di applicarlo.
- **Proposta di dati strutturati**, `SeoSuggestionService::suggestSchemaType($model)`: il modello suggerisce solo **tipo e valori dei campi terminali**, non JSON-LD arbitrario. Il codice costruisce il documento con `ProductSchema`, `ArticleSchema` o `BreadcrumbSchema` e lo verifica con `SchemaValidator`. Tipo, contesto e struttura sono quindi controllati dal pacchetto; l'accuratezza dei valori proposti resta da verificare. Se manca un campo richiesto, il risultato è *incompleto* e non applicabile. Nella prova descritta, una proposta Article è stata trattenuta per autore e immagine mancanti; altri provider non hanno suggerito un tipo.

## Senza Filament {#headless}

Le stesse funzioni restituiscono JSON per script e app senza pannello:

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

L'output contiene suggerimenti, oppure tipo, JSON-LD e risultato della validazione, modello usato e **token per richiesta**, input, output e ragionamento quando disponibili. I token aiutano a calcolare il costo alle tariffe del provider, ma non sono una fattura. Il comando termina con codice diverso da zero in caso di errore, descritto nell'oggetto JSON. `seo-pro:suggest-schema` **propone soltanto**: stampa il documento senza salvarlo.

## Compilare in massa i metadati mancanti {#bulk-fill-missing-metadata}

**Pro 2.42 cambia il comportamento predefinito della CLI:** `seo-pro:ai-fill` salva una bozza privata per ogni campo generato. I metadati pubblicati restano invariati fino all’approvazione. Salta valori esistenti e fallback calcolabili; una bozza ancora valida evita una nuova generazione.

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review` elenca le prime 100 bozze in attesa in JSON; specifica un ID per leggere valore ed evidenze private. Approvazione e rifiuto funzionano con AI disattivata, senza chiamate al provider. L’approvazione richiede un identificativo dell’operatore e rifiuta bozze con record sorgente o metadati modificati, oppure record eliminato. L’identificativo è dichiarato dall’operatore, non prova una revisione umana sostanziale. Usa `--connection=NAME` per un database configurato diverso dal predefinito.

`--auto-apply` ripristina esplicitamente la pubblicazione immediata dei campi ancora mancanti. `--force` salta la conferma, **non la revisione**. `--dry-run` genera e stampa senza salvare bozze o metadati, ma chiama il provider e può costare. `--field`, `--limit` e `--locale` delimitano il lavoro. Dopo l’aggiornamento verifica i comandi programmati.

### Volumi elevati: ritmo, stima e ripresa {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms` è 200 millisecondi. Da `confirm_over`, predefinito 100 record, il comando mostra la stima da `seo-pro.ai.pricing` e chiede conferma. I checkpoint conservano i campi completati dopo un’interruzione. Un timeout dopo l’accettazione del provider può comunque causare un doppio addebito: riconcilia il lavoro incerto prima di `--fresh`. Esegui un solo lavoro corrispondente alla volta.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

Nelle integrazioni personalizzate passa `review: true` per creare bozze. L’API PHP mantiene `apply: true, review: false` per compatibilità: le chiamate esistenti scrivono subito. `apply: false` mostra un’anteprima senza persistenza. Nel riepilogo `filled` conta i record gestiti, comprese le bozze in modalità revisione; la CLI li indica come `staged`.

### Modalità batch e sconto del 50% {#batch-mode-50-cheaper}

`--batch` usa l’endpoint asincrono supportato di Anthropic o OpenAI. La stima considera lo sconto documentato; verifica le tariffe correnti del modello. Gli adattatori Google e locale usano il percorso sequenziale. Invia, poi ripeti lo stesso comando per raccogliere le bozze:

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

Mantieni provider, lingua e modalità di pubblicazione tra invio e raccolta. Revisione e `--auto-apply` hanno checkpoint separati; la CLI impedisce l’altra modalità mentre un batch corrispondente è in corso. Un invio incerto richiede riconciliazione. I successi parziali vengono conservati; gli errori transitori possono essere ritentati. La raccolta ricontrolla i campi mancanti; l’approvazione controlla anche lo stato sorgente precedente all’invio. `seo-pro.ai.fill.batch.request_timeout` è 120 secondi. La raccolta programmata salva bozze per impostazione predefinita.

## Provenienza, migrazioni e filtro dei dati {#origin-review-and-filtering}

Richiede **Core 3.21 e Pro 2.42**. Core carica automaticamente la propria migrazione; pubblica quelle Pro ed eseguile su ogni database usato dai modelli SEO prima di generare suggerimenti da salvare:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

`seo_ai_proposals` conserva valori generati ed evidenze provider/modello/richiesta tramite cast cifrati Laravel. Proteggi `APP_KEY` e il suo backup: senza la chiave i valori sono illeggibili. Identificativi dei record, stato e decisioni restano colonne ordinarie. Le alternative di form abbandonati possono restare `offered` o `selected`. Non esiste eliminazione automatica: definisci una politica di conservazione, preserva bozze pendenti ed evidenze ancora referenziate da `seo_meta.ai_provenance`, e limita l’accesso a esportazioni e output del comando.

Suggerimenti accettati nei form, correzioni dashboard e valori massivi conservano la provenienza del campo. Le modifiche Eloquent successive mantengono `origin: ai` e impostano `edited: true`: indica una modifica, non una verifica umana. Svuotare il campo rimuove il marcatore. HTML, array, JSON e Inertia di Core espongono solo campo, origine e stato di modifica, con il meta tag personalizzato `rankbeam:ai-origin` dove applicabile. ID di generazione e provider restano privati. Non esporre modelli `SEOMeta` grezzi nelle API pubbliche.

La funzione copre i futuri salvataggi supportati, non il contenuto storico o ogni revisione. SQL diretto, query builder e renderer personalizzati possono aggirarla. Azzera esplicitamente la provenienza quando una sostituzione indipendente lo giustifica; le modifiche normali la mantengono. Il marcatore non è un watermark standardizzato, un’attribuzione non alterabile o una dichiarazione di conformità all’articolo 50. Qualità effettiva e marcature native dei provider richiedono una valutazione distinta.

Puoi implementare `AiPromptFilter` e configurare `seo-pro.ai.context_filter`. Filtra il prompt utente completo prima dell’invio sincrono o batch; un errore blocca l’invio con messaggio ripulito. Le istruzioni di sistema restano invariate. Il valore predefinito è `null`: **nessuna rimozione automatica dei dati sensibili**. L’esempio sostituisce un solo valore noto; implementa e prova regole adatte alla tua app:

```php
namespace App\Support;

use Rankbeam\Seo\Pro\Ai\AiPromptFilter;

final class RedactAiContext implements AiPromptFilter
{
    public function filter(string $prompt): string
    {
        return str_replace('internal@example.com', '[redacted]', $prompt);
    }
}

// Configure seo-pro.ai.context_filter with this class in config/seo-pro.php.
// Runtime equivalent:
config(['seo-pro.ai.context_filter' => RedactAiContext::class]);
```


## Gestione delle risposte {#how-replies-are-handled}

Ogni chiamata restituisce un oggetto comune ai provider:

- **Output strutturato dove supportato.** OpenAI riceve Structured Outputs, Google `responseSchema` e Anthropic `output_config.format`. Una risposta JSON non valida fallisce senza estrazione arbitraria del testo. Il server locale riceve `response_format` come richiesta best effort; se non lo applica, viene tentato il parsing tollerante. L'esito resta un risultato validato nella forma oppure un errore, non un'applicazione parziale.
- **Troncamento esplicito.** Quando il provider segnala il limite di output, viene restituito `truncated`, con indicazione di aumentare `seo-pro.ai.max_output_tokens`. Anche token di ragionamento possono consumare il budget; ai nomi che corrispondono ai pattern viene applicato `reasoning_min_output_tokens`.
- **Retry automatici selettivi.** Alcuni `429` e `5xx` vengono ritentati con attesa esponenziale limitata, rispettando `Retry-After` entro un massimo. Chiave errata, richiesta malformata, payload troppo grande, **timeout** e **credito o quota esauriti** non vengono ritentati automaticamente nella stessa chiamata. Un timeout può comunque essere incerto; nella ripresa massiva il record può essere riprovato. Configura `retry`; `max_attempts = 0` disabilita i retry automatici.
- **Errori tipizzati e ripuliti.** Codici stabili come `unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`, `content_too_large`, `bad_request`, `truncated`, `content_filtered` e `provider_error`, più `retryable`, descrivono l'esito. Il normale percorso applicativo non mostra o registra il corpo grezzo del provider; usa messaggi brevi e ripuliti. Il sistema di valutazione facoltativo descritto sopra conserva invece le risposte come evidenza. In Filament, la finestra mostra un'indicazione specifica per gli errori comuni.

## Risoluzione dei problemi {#troubleshooting}

Gli errori delle funzioni interattive compaiono nel contesto, con codice e messaggio. I casi comuni:

| Sintomo e codice | Significato | Intervento |
|---|---|---|
| **`quota_exceeded`**, credito o quota esauriti | Account API senza credito o quota disponibile; distinto da un limite transitorio. I messaggi variano tra provider, per esempio saldo basso, `insufficient_quota` o credito prepagato esaurito. | Controlla quote e fatturazione nella console del provider, oppure configura inferenza locale. Gli abbonamenti Claude e ChatGPT non finanziano automaticamente l'API. |
| **`unauthorized`**, autenticazione fallita | Chiave assente, errata o incompatibile con il provider. | Verifica la variabile nominata da `seo-pro.ai.api_key_env`, predefinita `SEO_PRO_AI_API_KEY`, e la corrispondenza con `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`**, limite di richieste | Limite transitorio, dopo gli eventuali retry automatici. | Attendi e riprova; aumenta `seo-pro.ai.fill.throttle_ms` per i volumi elevati. Un server locale ha limiti di capacità propri, anche senza quota del provider cloud. |
| **`timeout`**, richiesta scaduta | Il provider non risponde entro `seo-pro.ai.timeout`, predefinito 15 secondi. | Aumenta `SEO_PRO_AI_TIMEOUT`; per Ollama verifica se modello e server supportano `['think' => false]` in `seo-pro.ai.local.extra_body`. |
| **`truncated`**, limite di output raggiunto | La risposta non è terminata nel budget, anche per eventuale ragionamento nascosto. | Aumenta `seo-pro.ai.max_output_tokens` o verifica i pattern `reasoning_models` e la soglia minima, che può richiedere 2000 token o più. |
| **`content_too_large`**, HTTP 413 | Contesto inviato oltre il limite del provider. | Riduci `seo-pro.ai.max_input_chars`. |
| **`bad_request`** | Richiesta non valida, spesso modello non disponibile o parametro non supportato. | Verifica `SEO_PRO_AI_MODEL`, accesso dell'account e compatibilità con l'adattatore. |
| **`content_filtered`** | Il provider ha rifiutato il contenuto. | Rivedi il contenuto e le indicazioni del provider; non ripetere automaticamente la stessa richiesta rifiutata. |

::: tip Il provider locale richiede comunque una configurazione funzionante
Con `SEO_PRO_AI_PROVIDER=local` e un server ospitato da te puoi evitare i problemi di credito del provider cloud. Restano requisiti di hardware, modello, API, tempi e capacità. Un gateway remoto configurato con lo stesso adattatore può richiedere chiave e pagamento.
:::

## Dati inviati dal server {#what-leaves-your-server}

Solo al provider configurato e in seguito a un'azione esplicita, come un clic o un comando:

- **Suggerimenti**: nome breve della classe e chiave del record, come “Post #3”, titolo e descrizione risolti, canonical ed estratto del contenuto in testo semplice, senza HTML, limitato da `max_input_chars`, predefinito 6000.
- **Spiegazioni dei problemi**: tipo, gravità, campo, messaggio e URL del problema; quando il modello è disponibile, anche classe/chiave, titolo e descrizione risolti, canonical ed estratto testuale limitato.
- **Riscrittura della descrizione**: lo stesso contesto minimo dei suggerimenti, più tipo e messaggio del problema se fornito.
- **Proposta di dati strutturati**: lo stesso contesto minimo; il modello restituisce tipo e valori dei campi, mentre il JSON-LD viene costruito localmente.

Il pacchetto non raccoglie appositamente dati dei visitatori, IP, header delle richieste o credenziali per inserirli nei prompt, e non invia l'HTML completo. **Il testo dei campi e dell'estratto può però contenere dati sensibili presenti nei tuoi contenuti**: verifica ciò che l'app espone al servizio. La credenziale del provider viene usata nell'autenticazione della richiesta. `SECURITY.md` del repository Pro contiene il riferimento sul trattamento dei dati.
