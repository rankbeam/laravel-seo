---
description: "I requisiti che l’head di ogni stack frontend deve rispettare quando genera dati SEO Rankbeam: riferimento per i test dei renderer del core e le applicazioni di esempio."
---

# Contratto di rendering {#the-rendering-contract}

Questa è la **checklist di riferimento** per il `<head>` generato da ogni stack frontend che usa i dati SEO di Rankbeam. Definisce i requisiti per:

- i test unitari sulla struttura dell’output nel core, `tests/Unit/Services/RenderingContractTest.php`, che verificano il renderer senza un framework frontend;
- le applicazioni di riferimento in `rankbeam-examples` — Blade, Inertia con Vue, React o Svelte e Livewire — i cui test nel browser e SSR verificano gli stessi requisiti in un DOM reale;
- le guide a Blade, Inertia e JSON e Livewire, che devono descrivere soluzioni conformi al contratto.

Se uno stack non rispetta un requisito, si tratta di un **difetto o di un limite da documentare**. Il requisito rimane valido. Il livello dati, `SEOResolver` → `SEOData` immutabile → `TagRenderer`, non dipende dal framework. Cambia il modo in cui i dati risolti raggiungono il DOM, vengono conservati durante la navigazione lato client e rimangono visibili ai crawler: il contratto definisce proprio questi comportamenti.

> Questa specifica è stata consolidata con una revisione indipendente del design.
> Una nuova revisione serve quando cambia in modo sostanziale.

---

## 1. Valori: cosa contiene un `<head>` conforme {#_1-values-—-what-a-compliant-head-contains}

### Titolo, descrizione e canonical {#title-description-canonical}

- **Esattamente un `<title>`** con il titolo *risolto*, senza doppio suffisso. Il resolver aggiunge `seo.title_suffix` una sola volta, verificando che il titolo non termini già con quel suffisso.
- **Una meta description**, solo quando è stata risolta una descrizione, senza tag vuoti.
- **Un `<link rel="canonical">`**.

### Robots {#robots}

- Genera `<meta name="robots">` **solo quando la direttiva differisce dal valore predefinito del sito**. Un `index,follow` ridondante è superfluo: in assenza del tag il crawler adotta già quel comportamento. Il confronto ignora gli spazi (`index, follow` ≡ `index,follow`); una direttiva diversa viene emessa **così com’è**. `seo.robots.emit_default = true` forza il tag.
- Supporta in modo deterministico le **direttive avanzate** `noindex`, `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`, `max-video-preview`, `notranslate` e `unavailable_after`. Sono valori stringa risolti secondo la **catena di priorità del resolver**, da globale a rotta, modello e valore esplicito. A parità di input, l’output è identico.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`, `og:locale`.
- `article:*` — `published_time`, `modified_time`, `author`, `section`, `tag` — **solo quando `og:type === 'article'` e il valore esiste**. Nessun dato inventato e nessun campo article sulle altre pagine.
- `og:image` con `og:image:width`, `og:image:height`, `og:image:alt` e `og:image:type` **quando disponibili**. Le immagini multiple sono **raggruppate**: ogni `og:image` è seguito immediatamente dalle proprie dimensioni, dal testo alternativo e dal tipo.

### Twitter Card {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` e `twitter:image:alt`, quando è disponibile il testo alternativo.
- `twitter:site` e `twitter:creator` sono **facoltativi e indipendenti**. Uno può esistere senza l’altro; nessuno viene inventato a partire dall’altro.

### Hreflang e lingua {#hreflang-locale}

- Hreflang passa direttamente dal resolver tramite l’hook `getSEOAlternates()` del modello.
- Le versioni alternative hreflang, quando presenti, hanno URL **assoluti, normalizzati e unici per lingua**, con reciprocità quando i dati sono completi. `x-default` compare solo se configurato.
- `og:locale:alternate` include **solo** le lingue con una vera variante social. Converti `en-US` in `en_US` e confronta la forma convertita, senza richiedere uguaglianza letterale tra i due formati.
- `<html lang>` deve corrispondere alla lingua risolta, anche se è l’*applicazione* a generare l’elemento `<html>`.

### JSON-LD per pagina {#per-page-json-ld}

- Deve essere analizzabile e protetto dalle sequenze `</script>`. La codifica `JSON_HEX_TAG` impedisce ai valori di chiudere anticipatamente l’elemento script, proteggendo da XSS memorizzato.
- Sono validi sia **più blocchi `<script>` sia un `@graph` combinato**.
- Un `@id` stabile serve **dove le entità sono effettivamente collegate**, come Organization ↔ WebSite ↔ WebPage. Non è obbligatorio sui nodi autonomi.

---

## 2. Normalizzazione e invarianti {#_2-normalization-invariants}

- URL **assoluti `http(s)`** per `canonical`, `og:url`, `og:image` e `twitter:image`. **Nessun tag vuoto o nullo** deve arrivare nel DOM.
- **`canonical` e `og:url` devono risolversi nello stesso URL normalizzato.** Una divergenza è un **errore bloccante**, non un avviso.
- La **normalizzazione del canonical è coerente**: schema, host, porta, maiuscole nel percorso, elenco dei parametri query consentiti e slash finale seguono sempre le stesse regole. Le pagine indicizzabili fanno riferimento a **sé stesse**; una pagina `noindex` non eredita la strategia canonical di un’altra pagina.
- **L’escaping dipende dalla destinazione**: attributi HTML, testo e JSON usano ciascuno la codifica appropriata. Le asserzioni confrontano **valori semantici decodificati**, non byte.
- **La parità tra renderer è semantica.** `render()` in HTML ≡ `toArray()` ≡ `toInertiaHead()` *dopo la normalizzazione*. Ordine e forma dei tag possono differire. Le regole per proprietà singole e ripetibili sono esplicite: un solo `og:title`, più `article:tag`.
- **Proprietà dei tag:** un renderer lato client sostituisce i tag del pacchetto, identificati dalle chiavi descritte nella sezione 4, senza eliminare i tag estranei gestiti dall’applicazione.

---

## 3. Comportamento durante la navigazione lato client {#_3-behaviour-—-client-side-navigation}

Dopo ogni visita Inertia o `wire:navigate` di Livewire:

- esiste **esattamente un’istanza di ogni proprietà singola** — `<title>`, descrizione, canonical e ogni `og:*` o `twitter:*` non ripetibile — senza valori obsoleti;
- **il JSON-LD non si accumula**: lo schema della pagina precedente viene rimosso. Livewire considera `<script>` una risorsa da conservare, quindi gli script schema ricevono `data-seo-schema` e un ID per URL; quelli precedenti vengono rimossi su `livewire:navigated`, come descritto nella guida a Livewire;
- passando da una **pagina ricca di metadati a una priva di quei dati**, i tag aggiuntivi vengono rimossi: la seconda pagina non conserva descrizione, OG o schema della prima;
- non compaiono **avvisi di hydration** e i metadati restano semanticamente identici prima e dopo l’hydration.

---

## 4. Head-key di Inertia e proprietà dei tag {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` aggiunge un **`head-key`** stabile a ogni voce meta o link. Inertia usa l’attributo per eliminare i duplicati nell’head: un tag del `<Head>` della pagina con lo stesso `head-key` di un tag del layout lo *sostituisce*.

- Chiave di base: `name ?? property` per i meta, `rel` per i link.
- **I tag ripetibili hanno chiavi distinte**: `article:tag` → `article:tag`, `article:tag:1`, …; hreflang → `alternate:en-US`, `alternate:fr-FR`.

Nei template collega **`:head-key`**. Il `:key` di Vue è la chiave di riconciliazione di `v-for` e non gestisce i duplicati nell’head di Inertia.

---

## 5. Visibilità ai crawler: modalità esplicite {#_5-crawler-visibility-explicit-modes}

- **SSR o prerendering** devono emettere l’intero contratto nell’**HTML grezzo della risposta HTTP**. Questo viene verificato separatamente dal DOM dopo l’hydration, con JavaScript disabilitato.
- **Il solo rendering lato client non dimostra conformità per i crawler.** Inertia senza SSR inserisce i metadati *lato client*: l’HTML iniziale ricevuto da un crawler non li contiene. Il limite va dichiarato: **i metadati visibili ai crawler richiedono Inertia SSR o prerendering**, e il JSON-LD destinato ai crawler dovrebbe essere generato sul server.

---

## 6. Aspetti esclusi dal contratto {#_6-out-of-scope-non-goals}

- **Responsabilità dell’applicazione:** `charset`, `viewport` e favicon. `<meta charset>` deve precedere i metadati con caratteri non ASCII, quindi anche l’ordine di quegli elementi nell’head spetta all’applicazione.
- **I test end-to-end verificano soltanto l’output generato.** Non dimostrano indicizzazione su Google, *scelta* del canonical, idoneità ai risultati avanzati o posizionamento. Non verificano neppure disponibilità e MIME delle immagini remote: questi aspetti appartengono a test HTTP o di integrazione facoltativi, non alla matrice del browser.

---

## 7. Stato di conformità {#_7-conformance-status}

Queste sono le verifiche associate a ciascun requisito. **Unitari** indica `RenderingContractTest` nel core. **Browser/SSR** indica la matrice pianificata di `rankbeam-examples`. **App** indica una responsabilità dell’applicazione ospitante. **Pianificato** indica un obiettivo del contratto per cui `SEOData` non rappresenta ancora tutti i dati: il renderer emette il sottoinsieme disponibile senza inventare valori.

| Requisito | Stato |
|---|---|
| Un solo `<title>` risolto, senza doppio suffisso | **Unitari** + Browser |
| Meta description solo quando presente | **Unitari** + Browser |
| Un solo `<link rel="canonical">`, mai vuoto | **Unitari** + Browser |
| Robots solo se diverso dal valore predefinito, senza modifiche, con opzione `emit_default` | **Unitari** + Browser |
| Direttive robots avanzate tramite priorità del resolver | **Unitari**, resolver |
| `og:title/description/type/url/site_name/locale`; conversione `en-US`→`en_US` | **Unitari** + Browser |
| `article:*` solo con `og:type=article` e dati esistenti | **Unitari** + Browser |
| `og:image` presente e assoluto | **Unitari** + Browser |
| `og:image:width/height/alt`, `og:image:type` e raggruppamento di immagini multiple | **Pianificato**: `SEOData` contiene una sola stringa `ogImage`; dimensioni, alt e tipo non sono ancora rappresentati. Il renderer genera un solo `og:image` assoluto. |
| `twitter:card/title/description/image`; `site` e `creator` indipendenti | **Unitari** + Browser |
| `twitter:image:alt` | **Pianificato**: manca ancora un campo per il testo alternativo dell’immagine. |
| Hreflang assoluti e unici per lingua | **Unitari** + Browser |
| Reciprocità hreflang e `x-default` quando configurato | Browser, in base ai dati |
| `og:locale:alternate` corrisponde alle varianti social reali | **Pianificato**: manca una mappa delle varianti social per lingua. |
| Corrispondenza di `<html lang>` | **App**, verificata anche nel browser |
| JSON-LD analizzabile e protetto da `</script>` | **Unitari** + Browser |
| Più script oppure `@graph`, con `@id` stabile per le entità collegate | **Unitari**, grafo Merchant, + Browser |
| URL assoluti, nessun tag vuoto o nullo | **Unitari** + Browser |
| `canonical` ≡ `og:url`, errore bloccante in caso di divergenza | **Unitari** + Browser |
| Normalizzazione canonical coerente, autoreferenzialità e isolamento delle pagine noindex | Browser |
| Escaping per destinazione e parità semantica dopo decodifica | **Unitari** |
| Parità semantica tra renderer: `render()` ≡ `toArray()` ≡ `toInertiaHead()` | **Unitari** |
| `head-key` Inertia stabile e chiavi distinte per i tag ripetibili | **Unitari** + Browser |
| Navigazione client: un’istanza per proprietà singola, nessun valore obsoleto, JSON-LD senza accumulo e rimozione dei tag non più presenti | Browser; il renderer fornisce gli hook `data-seo-schema` necessari alla pulizia |
| Nessun avviso di hydration, parità prima e dopo | Browser |
| SSR emette il contratto nell’HTML grezzo; limite del solo CSR documentato | Browser + documentazione |

I **requisiti pianificati** sono lacune dichiarate. Il contratto resta l’obiettivo da raggiungere; queste estensioni aggiuntive e compatibili richiederanno nuovi campi o colonne di `SEOData` e una versione minor secondo SemVer. Il renderer attuale emette il sottoinsieme disponibile senza inventare dati.
