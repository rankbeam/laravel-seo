---
description: "Lingua dei contenuti, limiti per sistema di scrittura, grafemi, hreflang, inLanguage, font OG e URL Unicode in Rankbeam."
---

# Contenuti multilingua {#multilingual-content}

Le [traduzioni dell'interfaccia](/it/guide/translations) cambiano i messaggi del pacchetto. Questa pagina riguarda la lingua dei **contenuti**: come influisce su limiti editoriali, confronti del testo, hreflang, font e altre funzioni.

Default e opzioni si trovano in `config/seo.php`. Alcune capacità richiedono dipendenze di runtime, tra cui ICU per la segmentazione e font installati. I contenuti tradotti devono essere forniti dall'applicazione.

## Lingua dei contenuti e dell'interfaccia {#content-locale-and-interface-locale}

Core 3.17, Filament 1.11 e Pro 2.36 trasmettono la lingua selezionata a metadati, hook calcolati, URL di anteprima, parole chiave e richieste AI. Un pannello inglese può modificare contenuti italiani e giapponesi senza cambiare le etichette.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

Queste letture scelgono la riga di metadati della lingua richiesta ed eseguono `getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()` e `getSEOSchema()` in un contesto temporaneo. Modello e lingua dell'app chiamante vengono preservati, anche in caso di eccezioni. I modelli Spatie con `setLocale()` e `getTranslatableAttributes()` ricevono anche una lingua di istanza isolata.

Gli hook devono restituire i contenuti tradotti: Rankbeam non traduce automaticamente gli attributi ordinari del database.

Pro accetta `locale:` nei metodi AI basati su modelli e nel riempimento in blocco. Se manca, usa la lingua determinata dal `seoData()` ridefinito sul modello di traduzione, con fallback alla lingua dell'app. Le azioni Filament ricevono la lingua del proprio campo, anche nell'editor a lingua singola e quando seguono il selettore di pagina. Nei job personalizzati, serializza la scelta e passala esplicitamente all'esecuzione; non dipendere dalla lingua corrente del worker.

Per letture sincrone personalizzate, `ModelLocale::run($model, $locale, $callback)` passa un modello isolato al callback e ripristina la lingua dell'app in `finally`. Completa le letture dipendenti dalla lingua nel callback: restituire un iteratore lazy o una closure non prolunga quel contesto.

## Limiti di titolo e descrizione per sistema di scrittura {#title-and-description-budgets-per-script}

Rankbeam usa limiti editoriali di 60/160 grafemi per titoli e descrizioni latini, 30/80 per CJK. Sono approssimazioni configurabili, non misure in pixel né garanzie che il motore di ricerca mostri tutto il testo. Google non impone un limite fisso di caratteri per [titoli](https://developers.google.com/search/docs/appearance/title-link) o [meta description](https://developers.google.com/search/docs/appearance/snippet); il testo visualizzato può essere troncato in base alla larghezza del dispositivo.

`Rankbeam\Seo\I18n\LengthPolicy` sceglie i limiti per il testo:

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

La stessa policy viene usata da `SEOWarningEvaluator`, `seo:audit`, dal troncamento delle descrizioni calcolate, dalla scansione Pro e dai contatori Filament. Gli avvisi valutano il valore risolto, compreso il suffisso del titolo; l'editor può mostrare anche testo non salvato.

Il conteggio usa **cluster di grafemi**, non byte o punti di codice. I confini seguono l'implementazione Unicode installata: non sono un conteggio di sillabe né una misura dei pixel nei risultati di ricerca.

Le righe in `seo.length_policy` usano le chiavi `latin`, `cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew`, `devanagari`, con `default` per i gruppi non elencati. Ogni riga può ridefinire solo alcuni valori:

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Solo `cjk` differisce dal default. Anche un'installazione aggiornata con un file di configurazione precedente riceve la riga CJK incorporata.

::: tip Titoli misti
Il rilevamento pesa i caratteri CJK il doppio: «Laravel SEO の完全ガイド» viene classificato CJK, mentre «Laravel SEO for the 東京 developer» resta latino. Un valore senza lettere, come un anno o un prezzo, usa il sistema di scrittura della lingua della pagina.
:::

Le costanti `SEOWarningEvaluator::TITLE_MAX_LENGTH` e `DESCRIPTION_MAX_LENGTH` restano disponibili come default latini.

## Troncamento rispettoso dei grafemi {#grapheme-safe-script-aware-truncation}

Il limite `seo.computed.description_max_length`, espresso per testo latino, viene adattato dalla policy: per CJK si dimezza. `Rankbeam\Seo\I18n\Truncator` applica queste regole:

- Nei testi con spazi, preferisce l'ultimo confine di parola entro il limite, purché raggiunga almeno il 60% del limite. Non aggiunge puntini di sospensione e rimuove la punteggiatura finale.
- Per Han, Kana e thailandese, preferisce l'ultimo segno di frase o proposizione entro il limite, come `。！？、，`; poi uno spazio, se presente, per esempio in coreano; infine un taglio diretto.
- Il taglio avviene sui cluster di grafemi, senza spezzare una sequenza combinata.

## Maiuscole e confronti per lingua {#locale-aware-casing}

`mb_strtolower()` non considera la lingua. `Rankbeam\Seo\I18n\CaseFolder` sì:

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` produce la forma da mostrare; `fold()`, `equals()`, `contains()` e `containsWord()` servono per i confronti. Il core li usa per evitare un suffisso duplicato quando il titolo contiene già il marchio, tramite `seo.title_suffix_skip_when_contains`; i controlli sulle parole chiave Pro usano lo stesso helper.

Il case folding conserva gli accenti. Non rende equivalenti tutte le grafie accentate e non accentate. Uno stemmer può applicare riduzioni proprie: è un passaggio separato da `CaseFolder` e dal confronto senza stemming.

## hreflang {#hreflang}

Google usa `language[-Script][-REGION]`: codice lingua ISO 639-1 di due lettere, eventuale script ISO 15924, eventuale regione ISO 3166-1 alpha-2, oppure `x-default`. Regioni numeriche come `es-419` sono BCP 47 valide ma fuori dal [contratto hreflang di Google](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes).

Le locale Laravel possono avere underscore, come `it_IT` e `pt_br`, non validi nel valore HTML. Tre policy `seo.hreflang` si applicano a `getSEOAlternates()` prima di generare tag, sitemap, collegamenti llms e dati per l'audit. I collegamenti «Also in» di llms omettono la pagina corrente e `x-default`.

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`**, attivo per default, adatta separatori, maiuscole e alias registrati, per esempio `iw_IL` → `he-IL`. Conserva separatori ripetuti (`en__US` → `en--US`) per permettere all'audit di segnalarli. Disattivalo per mantenere i byte forniti.
- **`include_self`** aggiunge lingua e canonical della pagina quando né il suo URL né il suo codice compaiono nell'elenco. Serve quando l'hook restituisce solo le altre versioni.
- **`x_default`** indica quale alternativa duplicare come `x-default`, se manca.

Un elenco vuoto resta vuoto: non vengono inventate traduzioni, autoreferenze o `x-default`.

L'audit gratuito controlla l'elenco dopo le policy:

| Codice | Gravità | Significato |
|---|---|---|
| `hreflang_invalid_code` | warning | Codice fuori dal contratto Google, come `en-UK`, `jp`, `english`, `es-419`, `fil` |
| `hreflang_duplicate_code` | notice | Codice ripetuto |
| `hreflang_missing_self` | warning | L'URL della pagina non compare nell'elenco |

La reciprocità richiede richieste alle altre pagine ed è una funzione Pro. L'opzione `check_hreflang_reciprocity` recupera le alternative tramite `SsrfGuard`; dalla 2.38 segnala `hreflang_not_reciprocal` quando manca il rimando all'URL sorgente **con il suo codice lingua**. Vedi i [codici di rete](/it/pro/scan-issues#network-codes).

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### Tre contratti distinti per i codici lingua {#three-language-code-contracts}

Dal core 3.18, normalizzazione dell'applicazione, lingua HTML e hreflang sono verifiche separate:

| Input | Normalizzazione app | Lingua HTML | hreflang Google |
|---|---|---|---|
| `pt_BR` | `pt-BR` | Non valido così com'è | Non valido così com'è |
| `de-CH-1901` | Invariato | Variante registrata valida | Variante non supportata |
| `es-419` | Invariato | Regione numerica valida | Regione numerica non supportata |
| `zh-Hant-TW` | Invariato | Valido | Valido |
| `fil` | Invariato | Lingua registrata valida | Fuori dal contratto a due lettere |
| `iw_IL` | `he-IL` | Underscore non valido; `iw-IL` resta un tag deprecato valido | Usa `he-IL` normalizzato |
| `en__US` | `en--US` | Non valido | Non valido |
| `x-default` | Invariato | Rifiutato dalla policy Rankbeam per la lingua del contenuto | Marcatore di fallback valido |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Migrazione dal core 3.17 o precedente:** `Hreflang::isValid()` e `parse()` validano rigorosamente il codice servito. Se ricevi una locale Laravel, usa prima `fromLocale()`. Per controllare un attributo HTML `lang`, usa `LanguageTag::isValidHtml()` senza trim o normalizzazione. I tag deprecati ma registrati restano validi in HTML; la normalizzazione usa solo gli alias preferiti espliciti IANA e non presume che `en-UK` significhi `en-GB`. Le voci malformate non vengono eliminate prima che l'audit possa segnalarle.

Il validatore incorpora il registro IANA datato **2026-08-08**, con hash delle sorgenti e generatore riproducibile. Controlla struttura RFC 5646, sottotag registrati, prefissi extlang e duplicati di varianti/estensioni. Supporta tag grandfathered e intervalli per uso privato. Le raccomandazioni sui prefissi delle varianti non sono vincoli obbligatori di validità; namespace e struttura delle estensioni vengono verificati, ma non il significato delle opzioni CLDR o degli usi privati. Non richiede ICU né download a runtime. Riferimenti: [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) e [definizione HTML della lingua](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes).

Pro 2.38+ distingue `lang` assente o vuoto, considerato sconosciuto/mancante, da un valore malformato, che genera `html_lang_invalid`. I controlli sul sistema di scrittura usano un sottotag script reale oppure il default registrato IANA. Non deducono il latino da contenuti privati, estensioni o lingue sconosciute. I gruppi non supportati restano senza giudizio: non è un rilevatore completo di lingua.

La reciprocità usa i codici validi dell'autoreferenza sorgente oppure, se assenti, il suo `lang` HTML compatibile con Google. Lo stesso URL sotto un'altra lingua non basta. Se il codice sorgente non è determinabile, l'esito resta `hreflang_target_unverified`. Gli URL duplicati vengono recuperati una sola volta entro i limiti esistenti; restano attivi protezioni SSRF, rifiuto dei redirect e gestione esplicita degli esiti non verificati.

## `inLanguage` nel grafo schema {#inlanguage-in-the-schema-graph}

Il nodo `WebPage` usa la lingua risolta della pagina, per esempio `it_IT` → `it-IT`. `ArticleSchema::fromModel()` usa quella salvata in `seo_meta`. Le lingue del nodo `WebSite` vengono dalla configurazione:

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Motori di ricerca regionali {#regional-search-engines}

Il catalogo di `seo:robots-txt` include Yandex, Baidu, Naver (`Yeti`), Seznam, Sogou, 360, Cốc Cốc e DuckDuckGo, con scopo `search_engine` e accesso consentito per default. Partecipano alla policy e agli override:

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` e `match()` restano limitati ai bot AI, per mantenere il significato del registro Pro. Per includere i motori tradizionali usa `searchEngines()`, `all(true)` o `match($ua, true)`. Vedi [controllo crawler](/it/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
Il supporto al crawler e al tag di verifica non garantisce scoperta, indicizzazione o posizionamento in Baidu.
:::

## Verifica del sito {#site-verification}

Rankbeam genera un metatag per ogni motore configurato, su tutte le pagine, inclusa la home. Non genera tag per chiavi vuote:

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

Un valore può essere anche un elenco di token, per esempio per più proprietari di una proprietà Google.

## Immagini OG per diversi sistemi di scrittura {#og-images-in-every-script}

Il font incluso copre latino, cirillico e greco. Gli altri sistemi richiedono un font sulla macchina che esegue `seo:og-images`. I template usano `seo.og_image.font_stack`, anticipando la famiglia Noto CJK adatta alla lingua per scegliere la forma locale dei caratteri Han. Il comando può segnalare i font mancanti prima del rendering:

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Su Debian/Ubuntu: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. I limiti del controllo e la verifica dei font effettivi sono nella [guida alle immagini OG](/it/guide/og-image#fonts-and-non-latin-scripts).

## `llms.txt` in più lingue {#llms-txt-in-several-languages}

Con `seo.llms_txt.alternates` attivo, una pagina con altre versioni termina la propria voce con `Also in: [it](…), [de](…)`. Usa le alternative dopo le policy, escludendo `x-default` e la pagina stessa. L'opzione è disattivata per default.

## URL Unicode {#unicode-urls}

Rankbeam non genera slug né riscrive gli URL: percorsi come `/città/` e `/検索` vengono preservati negli output. L'audit accetta host IDN come `https://münchen.example/` e percorsi Unicode o percent-encoded tramite `Rankbeam\Seo\I18n\Url::isValid()`. Usa una sola rappresentazione per URL, Unicode oppure percent-encoded, perché canonical, hreflang e sitemap coincidano anche a livello di byte.

## Copertura delle lingue e limiti {#which-languages-are-supported-and-what-that-means}

I pacchetti includono stringhe e selezione degli algoritmi per le diciassette locale seguenti. La tabella descrive la copertura tecnica, non un'approvazione editoriale madrelingua né il rendering garantito su un server non configurato. L'analisi delle parole in giapponese/cinese richiede ICU funzionante; se manca, i controlli interessati vengono saltati. Per i caratteri non latini servono font adatti.

`tests/Feature/I18n/SupportedLanguagesTest.php` nel core controlla locale, hreflang e limiti; `tests/Feature/OnPage/LanguageSupportMatrixTest.php` in Pro controlla la selezione dei motori.

| Lingua | Locale | Titolo / descrizione | Conteggio parole | Parole chiave | Leggibilità |
|---|---|---|---|---|---|
| Inglese | `en` | 60 / 160 | Spazi | Snowball | Flesch Reading Ease |
| Italiano | `it` | 60 / 160 | Spazi | Snowball | Gulpease |
| Tedesco | `de` | 60 / 160 | Spazi | Snowball | Wiener Sachtextformel |
| Francese | `fr` | 60 / 160 | Spazi | Snowball | Kandel-Moles |
| Spagnolo | `es` | 60 / 160 | Spazi | Snowball | Fernández-Huerta |
| Portoghese brasiliano | `pt_BR` | 60 / 160 | Spazi | Snowball | Martins |
| Olandese | `nl` | 60 / 160 | Spazi | Snowball | Flesch-Douma |
| Turco | `tr` | 60 / 160 | Spazi | Snowball | Ateşman |
| Russo | `ru` | 60 / 160 | Spazi | Snowball | Oborneva |
| Polacco | `pl` | 60 / 160 | Spazi | Snowball | Pisarek |
| Giapponese | `ja` | 30 / 80 | Dizionario ICU | Esatto, con case folding | Euristica, **senza punteggio** |
| Cinese semplificato | `zh_CN` | 30 / 80 | Dizionario ICU | Esatto, con case folding | Euristica, **senza punteggio** |
| Cinese tradizionale | `zh_TW` | 30 / 80 | Dizionario ICU | Esatto, con case folding | Euristica, **senza punteggio** |
| Coreano | `ko` | 30 / 80 | Spazi | Esatto, con case folding | Euristica, **senza punteggio** |
| Greco | `el` | 60 / 160 | Spazi | Snowball | LIX |
| Ucraino | `uk` | 60 / 160 | Spazi | Esatto, con case folding | LIX |
| Ceco | `cs` | 60 / 160 | Spazi | Snowball | LIX |

Tre distinzioni da mantenere:

- **Snowball è incluso da Pro 2.37.** Dodici lingue usano gli algoritmi 3.1.1 fissati dal pacchetto, indipendentemente da dipendenze opzionali. Ucraino e CJK usano confronti senza stemming. Questi possono non riconoscere forme flesse; lo stemming può invece accorpare parole distinte. Vedi [controlli dei motori e migrazione](/it/pro/on-page-checklist#upgrading-from-pro-2-36).
- **Euristica senza punteggio e LIX sono diversi.** Giapponese, cinese e coreano restituiscono un livello, basato su lunghezza delle frasi e, per il giapponese, quota di kanji, con punteggio `null` e indicazioni sempre consultive. Greco, ucraino e ceco usano LIX perché qui non è implementata una formula specifica. LIX non richiede sillabe, ma le soglie non sono calibrate per ogni lingua. Anche le altre formule usano input stimati; vedi il [contratto delle statistiche](/it/pro/on-page-checklist#text-statistics-and-api-limits).
- **Le traduzioni sono prime stesure** salvo revisione registrata in `TRANSLATING.md`. Le stringhe italiane hanno il credito di revisione del maintainer; questo non approva automaticamente questa documentazione o gli output AI.

Le locale non elencate possono usare stringhe inglesi, limiti del sistema di scrittura o di default, confronti senza stemming e LIX o euristiche. Non equivale a supporto linguistico validato. Il blocco `analysis` della checklist identifica sistema di scrittura, segmentatore, stemmer e metodo di leggibilità: verifica anche disponibilità e giudizi saltati.

### Motori rilevanti per il pubblico locale {#reaching-the-search-engines-that-matter-locally}

Il catalogo riconosce anche crawler regionali e `seo.verification` genera i relativi tag di verifica. Queste integrazioni vanno distinte dai risultati di posizionamento. Consulta [motori regionali](#regional-search-engines) e [verifica del sito](#site-verification).

## Funzioni dei pacchetti aggiuntivi {#what-the-other-packages-add}

- **laravel-seo-filament** usa la stessa policy nei contatori e nelle anteprime. Dalla 1.9 modifica [una riga `seo_meta` per lingua](/it/guide/filament#several-languages), con schede indipendenti o seguendo il selettore del plugin. La guida Filament descrive gli adapter Edit/Create.
- **laravel-seo-pro** usa la policy nei controlli di lunghezza e nei prompt AI. L'analisi include segmentazione ICU per cinese, giapponese e thailandese, stemming Snowball, confronto con `CaseFolder`, formule di leggibilità con input stimati per dieci lingue, euristiche CJK, LIX per greco/ucraino/ceco, stop word per sedici lingue, controlli `html lang` e reciprocità hreflang, prompt con lingua esplicita e report Chrome per i sistemi che dompdf non riesce a disegnare. Riferimenti: [checklist](/it/pro/on-page-checklist#keyword-matching), [problemi di scansione](/it/pro/scan-issues), [AI assist](/it/pro/ai-assist#output-language) e [report](/it/pro/reports#reports-in-every-script-browsershot-renderer).
