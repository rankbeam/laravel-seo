---
description: "Checklist della pagina con esiti pass/warn/fail: parola chiave in titolo, URL, apertura e descrizione, più lunghezza, immagini, link interni e leggibilità."
---

# Checklist della pagina: parole chiave ed esiti pass/warn/fail {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

La checklist offre i controlli editoriali familiari a chi usa Rank Math o Yoast: scegli una parola chiave principale e verifica la sua presenza in titolo, URL, apertura e meta description, insieme a lunghezza, immagini, link interni e **leggibilità**.

Viene eseguita **nella richiesta corrente**, senza coda né rete, usando modello, [resolver](/it/concepts/resolver-precedence) e testo della pagina. Il risultato **non è un punteggio numerico**.

::: tip Checklist e punteggio sono separati
La checklist usa solo **pass, warn e fail** ed è indipendente dal [punteggio SEO Pro](/it/pro/scoring). Non condivide i codici dei criteri di punteggio e non può modificarlo. Densità delle parole chiave e leggibilità sono in particolare **indicazioni consultive**, come descritto sotto.
:::

## Controlli inclusi {#what-it-checks}

| Controllo | Gruppo | Cosa verifica |
|---|---|---|
| `keyword_in_title` | keyword | Presenza della parola chiave nel titolo SEO. |
| `keyword_in_description` | keyword | Presenza nella meta description. |
| `keyword_in_url` | keyword | Presenza nello slug dell'URL. |
| `keyword_in_first_paragraph` | keyword | Presenza nel paragrafo iniziale. |
| `keyword_density` | keyword | **Consultivo.** Mostra la densità senza imporre un valore da raggiungere. |
| `title_length` | meta | Finestra condivisa con editor e scansione: 30–60 per il latino, circa 15–30 per CJK, dalla [politica core sulle lunghezze](/it/guide/multilingual#title-and-description-budgets-per-script), adottata da Pro 2.33. |
| `description_length` | meta | Finestra 70–160 per il latino, circa 35–80 per CJK. |
| `content_length` | content | Quantità di testo secondo le fasce di parole configurate. |
| `readability` | content | **Consultivo.** Stima con formula dedicata per dieci lingue, fallback LIX o euristica esplicitamente senza punteggio per giapponese, cinese e coreano. |
| `has_image` | media | Almeno un'immagine nel contenuto. |
| `internal_links` | links | Link a pagine interne. |

Senza parola chiave principale, i relativi controlli vengono **saltati**, senza esito positivo o negativo, e la checklist invita a impostarla. Usa il [campo dedicato](/it/guide/filament) oppure `saveSEO(['focus_keywords' => …])`.

### Corrispondenza delle parole chiave {#keyword-matching}

Il confronto applica **case folding e stemming**. Per esempio, “espresso grinder” può corrispondere a “espresso grinders”; con la locale corretta, “İstanbul” a “istanbul” in turco, “ΟΔΟΣ” a “οδος” in greco e “Straße” a “STRASSE” in tedesco, tramite `CaseFolder` del core. Specifica la lingua di analisi con `SeoPro::checklistFor($post, 'it')` oppure `--locale=it`.

Da Pro 2.36.1, parole chiave, sinonimi e testo dei campi passano dallo stesso tokenizer prima dello stemming. Servono **token interi consecutivi**: `cat` non corrisponde a `education`; le frasi giapponesi usano gli stessi confini ICU del corpo. Apostrofi e trattini separano i token, quindi `meta-tag` corrisponde a `meta tag`, e gli apostrofi dritti e tipografici si comportano allo stesso modo. I segni combinanti restano associati alle lettere. Il case folding conserva gli accenti; lo stemmer della lingua può applicare ulteriori riduzioni.

Il conteggio sceglie a ogni posizione la corrispondenza più lunga tra parola chiave e sinonimi, contandola una sola volta. Sinonimi duplicati e alternative più corte sovrapposte non gonfiano la densità. Con `seo` e sinonimo `seo tools`, `seo tools seo` contiene due occorrenze. Per gli alfabeti senza spazi serve ancora ICU: il fallback regex non fornisce i confini delle parole da dizionario.

Da Pro 2.37, lo stemming usa un **sottoinsieme incluso di Snowball 3.1.1**. Non richiede altri pacchetti Composer e non scarica nulla durante l'esecuzione. PHP 8.2 resta supportato.

| Motore | Quando | Lingue |
| --- | --- | --- |
| `snowball` | Predefinito; anche la configurazione esistente `auto` lo seleziona | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | `seo-pro.checklist.analysis.stemmer = builtin` esplicito | Solo inglese, con lo stemmer flessivo leggero precedente; le altre lingue usano identity |
| `identity` | Lingua non supportata oppure modalità `none` esplicita | Ucraino, giapponese, cinese, coreano, thailandese e lingue fuori dal sottoinsieme |

Entrambi i lati del confronto usano lo stesso motore. Lo stemming riduce suffissi: non è un dizionario di sinonimi né una garanzia di equivalenza linguistica. L'algoritmo greco può, per esempio, far corrispondere forme accentate e non accentate che identity mantiene distinte. I confini dei token continuano a impedire che `cat` corrisponda a `education`.

#### Aggiornamento da Pro 2.36 {#upgrading-from-pro-2-36}

`auto` usa ora gli algoritmi inclusi, indipendentemente dalla presenza di `wamania/php-stemmer`. Ricontrolla i suggerimenti dopo l'aggiornamento: gli algoritmi possono cambiare le corrispondenze e turco, greco, polacco e ceco ora dispongono di stemming. Gli algoritmi aggiuntivi del wrapper opzionale per catalano, danese, finlandese, norvegese, rumeno e svedese sono fuori dal sottoinsieme e passano a identity.

Usa `SEO_PRO_CHECKLIST_STEMMER=builtin` per il precedente fallback solo inglese, oppure `none` per confronti identity con case folding in tutte le lingue. Ricostruisci la cache della configurazione dopo la modifica. Queste opzioni non riproducono i vecchi algoritmi multilingui del wrapper; per conservarne esattamente i risultati occorre mantenere la precedente versione Pro. I metadati SEO salvati non vengono riscritti.

L'adattatore incluso supera 600.395 coppie ufficiali di vocabolario e output fissate nei test su PHP 8.2, 8.3 e 8.4. È una verifica di conformità degli algoritmi, non un'approvazione editoriale madrelingua. Hash delle fonti, adattamento sintattico per PHP 8.2 e licenze sono inclusi nella distribuzione; vedi `THIRD-PARTY-NOTICES.md`.

### Segmentazione delle parole {#word-segmentation}

Conteggio delle parole, densità e statistiche di leggibilità richiedono confini affidabili. Per i testi con spazi, una regex usa sequenze di lettere e cifre. Cinese, giapponese e thailandese richiedono segmentazione da dizionario: una regex può trattare un intero paragrafo come una parola. Con **ext-intl**, il tokenizer passa questi testi a `IntlBreakIterator::createWordInstance` di ICU, che segmenta anche 東京タワーは東京のランドマークです. Se ICU manca, è disabilitato o non si inizializza, Pro salta i controlli interessati su lunghezza del contenuto, leggibilità e parole chiave, indicando come configurarlo. Non trasforma un conteggio inaffidabile in un errore della pagina. I controlli indipendenti, come lunghezza del titolo e corrispondenze nei testi con spazi, continuano. `seo-pro.checklist.analysis.segmenter = regex` rende allo stesso modo indisponibile la segmentazione da dizionario.

Il blocco `analysis` contiene `word_count_status`, `available` oppure `unavailable`, e `segmentation_reason`, `null`, `missing_intl`, `disabled` oppure `initialization_failed`. Il tokenizer di basso livello conserva token di fallback per compatibilità: controlla lo stato prima di interpretarli come parole.

La scansione dell'HTML emette `word_segmentation_unavailable`, senza penalità, anziché un verdetto sul contenuto scarso. Un problema `thin_content` già confermato resta aperto fino a una nuova verifica valida. La scansione incompleta non aggiorna il punteggio: un risultato esistente mantiene `scored_at`, mentre una prima scansione non assegna un numero finché la segmentazione non funziona. Installa `ext-intl`, abilita `auto` e ripeti la scansione.

### Motori usati nell'analisi {#which-engines-analysed-the-page}

Ogni checklist include `analysis` con alfabeto dominante, tokenizer `intl` o `regex`, stemmer `snowball`, `builtin` o `identity` e metodo di leggibilità `formula`, `heuristic` o `lix`. Lo trovi in `toArray()` e `--json`, nel piè di pagina della finestra Filament e nell'ultima riga di `seo-pro:checklist`:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

Il riepilogo identifica i motori effettivi, inclusi regex senza ext-intl e identity quando lo stemming è disabilitato.

### La densità è consultiva {#keyword-density-is-advisory}

La checklist non definisce una densità ideale per il posizionamento. Il controllo è **consultivo**: mostra il dato, non fallisce e **non determina lo stato complessivo**. Usalo per valutare se le ripetizioni risultano naturali, non come percentuale da raggiungere.

### La leggibilità è consultiva {#readability-is-advisory}

Il metodo dipende dalla locale di analisi. Sono implementate queste formule e alternative:

| Locale | Formula | Fonte |
| --- | --- | --- |
| Inglese (`en`) | Flesch Reading Ease | Flesch 1948 |
| Italiano (`it`) | Indice Gulpease | Lucisano e Piemontese 1988 |
| Spagnolo (`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| Francese (`fr`) | Kandel-Moles | Kandel e Moles 1958 |
| Tedesco (`de`) | erste Wiener Sachtextformel | Bamberger e Vanecek 1984 |
| Portoghese (`pt`, `pt_BR`) | Flesch adattato al portoghese brasiliano | Martins et al. 1996 |
| Olandese (`nl`) | Flesch-Douma | Douma 1960 |
| Russo (`ru`) | Adattamento di Flesch di Oborneva | Оборнева 2006 |
| Turco (`tr`) | Ateşman | Ateşman 1997 |
| Polacco (`pl`) | Pisarek, indice di anni di istruzione normalizzato | Pisarek 1969 |
| Giapponese, cinese, coreano (`ja`, `zh`, `ko`) | **Euristica senza punteggio**, descritta sotto | — |
| Greco, ucraino, ceco (`el`, `uk`, `cs`) | LIX come fallback, senza formula dedicata implementata e senza calibrazione per queste lingue | Björnsson 1968 |
| Altre lingue | LIX, Läsbarhetsindex, fallback non calibrato | Björnsson 1968 |

La scala **0–100, con valori maggiori per testi stimati più facili**, è una convenzione del pacchetto. I risultati delle formule Flesch e Gulpease vengono limitati alla scala; Wiener, Pisarek e LIX vengono convertiti. Numeri uguali in lingue diverse **non implicano uguale difficoltà**. I coefficienti provengono dalle pubblicazioni, ma le stime di token, frasi e sillabe di Rankbeam non sono state validate come strumento complessivo. Non prevedono comprensione o posizionamento.

Da Pro 2.37.1, turco e russo contano separatamente vocali adiacenti: `saat` vale 2 e `поэт` vale 2. Gli altri stimatori hanno ancora limiti su iati e vocali mute. L'inglese ha un piccolo elenco di eccezioni, non un dizionario di pronuncia. Parole come lo spagnolo `país` o il francese `monde` possono essere contate male. Valuta manualmente parole inconsuete e nomi propri.

#### Statistiche del testo e limiti dell'API {#text-statistics-and-api-limits}

Tag HTML di blocco e `br` separano il testo; l'enfasi inline rimane unita alla parola. Gli a capo del sorgente HTML ordinario diventano spazi, mentre testo semplice e `pre` mantengono le righe. Script, style e noscript sono esclusi. L'estrazione non valuta visibilità CSS o rendering effettivo. Le entità vengono decodificate una volta. Nelle statistiche, sequenze di lettere e numeri sono parole; la sola punteggiatura no. Trattini e apostrofi separano parole. Le cifre contano come token senza sillabe stimate. Le lettere si contano nel testo originale, senza che stemming o conversione tedesca `ß` → `ss` ne cambino la lunghezza.

La stima delle frasi usa `. ! ? 。 ！ ？`, confini di blocco e righe; include il frammento finale senza punteggiatura e protegge decimali e poche abbreviazioni comuni, come `Dr.`, `Prof.` ed `e.g.`. Titoli ed elementi di lista possono quindi contare come frasi. Altre abbreviazioni, citazioni, numeri, alfabeti misti e testo poco punteggiato richiedono cautela. La locale seleziona il metodo, senza rilevare la lingua di ogni frase.

Il `toArray()` del calcolatore diretto aggiunge `assessment`:

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` distingue `formula`, `lix`, `heuristic` e `unavailable`; vale `unspecified` per risultati costruiti manualmente senza metadati della formula. Input vuoto o solo punteggiatura è `insufficient`, con `isValid()` falso. Il vecchio `score: 0` è in quel caso un segnaposto di indisponibilità, non una misura di difficoltà. Le etichette scolastiche inglesi e italiane sono approssimative; non vengono più assegnate alle altre lingue o ai risultati euristici e LIX. `calculateFleschKincaid()` conserva il nome pubblico per compatibilità ma calcola **Flesch Reading Ease**, non il livello Flesch-Kincaid.

I test fissano input contati indipendentemente e aritmetica attesa per le dieci formule e LIX. Verificano il calcolo, non la qualità editoriale madrelingua. La leggibilità resta separata dal punteggio SEO Pro.

::: warning Giapponese, cinese e coreano: euristica dichiarata, senza numero
Per queste lingue il calcolatore restituisce un **livello** da regole del pacchetto: lunghezza media delle frasi in caratteri, ja ≤ 40/60/80 e zh ≤ 30/45/60, oppure parole, ko ≤ 12/18/25. In giapponese una quota kanji sopra circa il 45% alza di una fascia la difficoltà stimata. Il risultato ha `heuristic: true` e **punteggio null**. Il messaggio dichiara il metodo euristico e il controllo resta **consultivo qualunque sia `readability.advisory`**: non determina lo stato complessivo. I conteggi ja/zh richiedono ICU funzionante; senza, i controlli dipendenti vengono saltati.
:::

La leggibilità è **consultiva per impostazione predefinita**: informa chi scrive ma non determina lo stato della pagina. Sotto il minimo di parole viene **saltata**; la quantità insufficiente è compito di `content_length`. Per le lingue con metodi applicabili puoi far incidere un testo difficile sullo stato:

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## Leggere la checklist {#reading-the-checklist}

### Senza Filament {#headless}

Da Pro 2.36, metadati risolti, `getContentForSEO()` e parole chiave vengono letti nella locale del contenuto richiesta; le etichette restano nella lingua dell'operatore. Senza locale esplicita viene rispettato il valore predefinito di `seoData()` del modello di traduzione. L'azione Filament segue la scheda linguistica del campo o il selettore della pagina.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Ogni `CheckResult` contiene `id`, `group`, `label`, `status`, `message`, una `recommendation` facoltativa e il flag `advisory`.

### Comando {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### Nell'editor Filament, facoltativo {#in-the-editor-filament-optional}

Con [`rankbeam/laravel-seo-filament`](/it/guide/filament), il campo della parola chiave mostra l'azione **On-page checklist**, che apre gli stessi controlli sul contenuto **salvato** del record. Il pacchetto Filament non dipende da Pro: l'azione si collega tramite l'estensione a senso unico usata dai suggerimenti AI, senza modificare le installazioni senza pannello.

## Configurazione {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Scrivere un controllo personalizzato {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Aggiungi la classe a `seo-pro.checklist.rules`. Un controllo **non deve riutilizzare** un [codice dei problemi di scansione](/it/pro/scan-issues): la checklist usa uno spazio di nomi separato che non incide sul punteggio.

## Come viene letto il contenuto {#how-the-content-is-read}

`SeoPro::checklistFor($model)` analizza:

- **Titolo e descrizione**: valori **risolti**, come quelli misurati dai contatori dell'editor e dalla scansione.
- **Contenuto**: `$model->getContentForSEO()`, accessor `HasSEO` del core che usa normalmente `content`, `body` o `text`. Sovrascrivilo per indicare il corpo effettivo.
- **URL**: `$model->getUrlForSEO()`.
- **Parole chiave principali**: `seo_meta.focus_keywords` salvato.

È un'analisi senza recupero della pagina e senza scritture.
