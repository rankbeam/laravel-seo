---
description: "Aggiungi campi SEO ai form delle risorse Filament 4 e 5 con il pacchetto gratuito laravel-seo-filament e il trait HasSEO."
---

# Campi SEO per Filament

Il pacchetto gratuito [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) aggiunge una sezione SEO ai form delle risorse Filament con due integrazioni per risorsa. Supporta **Filament 4.x e 5.x**, con Livewire 3 e 4. La modifica dei metadati è gratuita; le scansioni e il punteggio visibile nell'esempio richiedono Pro.

## Prerequisiti {#prerequisites}

Serve un pannello Filament 4 o 5 esistente e un modello con il trait `HasSEO` del core. Completa la [guida rapida](/it/guide/quickstart), comprese migrazioni e generazione dei tag, prima di aggiungere l'editor.

## Installazione {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

Il modello della risorsa deve usare `HasSEO`.

## Aggiungi la sezione alla risorsa {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## Verifica il risultato salvato {#check-the-saved-result}

Apri un record, inserisci una descrizione SEO, salva e ricarica il form. Il valore deve rimanere, comparire nell'anteprima e avere sorgente **Manual** se il pannello è in inglese. Controlla il `<head>` della pagina pubblica per verificare che la stessa descrizione arrivi ai visitatori.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="Campi SEO nella demo Merchant: titolo, descrizione, canonical, immagine social, anteprima e sorgenti dei valori." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Esempio dalla demo Merchant. I campi seguono il tema del pannello; controlli e limiti dipendono dalla versione installata e dalla configurazione.*

La sezione comprende:

- **Titolo e descrizione** con contatori aggiornati durante la digitazione. La [policy di lunghezza](/it/guide/multilingual#title-and-description-budgets-per-script) usa il sistema di scrittura: 60/160 per il testo latino, circa 30/80 per CJK, contando i grafemi.
- **Parole chiave principali:** un campo a tag salva le parole nella struttura `[{keyword, is_primary}]`; la prima è primaria. `getPrimaryKeyword()` e `SEOData` leggono questa struttura. Attiva `seo.keywords.enabled` per far segnalare le pagine senza parole chiave all'[audit gratuito](/it/guide/audit) e alla scansione Pro. L'opzione è inizialmente disattivata; vedi la [configurazione (EN)](/reference/configuration#focus-keywords).
- **URL canonical:** vuoto significa automatico, con query rimossa.
- **Robots:** vuoto usa il default del sito.
- **Immagine social:** upload per `og:image` e `twitter:image`, nella directory `seo/` del disco predefinito di Filament.
- **Anteprima del risultato di ricerca:** segue i fallback del resolver durante la digitazione.
- **Indicatori della sorgente:** mostrano il livello da cui deriva il valore effettivo, tra manuale, contenuto, default per modello, default globale, configurazione e URL.

## Limita i campi {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

Puoi scegliere qualsiasi sottoinsieme di `title`, `description`, `focus_keywords`, `canonical`, `robots`, `og_image`. Senza il trait, `SEOFields::make(?array $only)` restituisce direttamente la stessa sezione.

## Salvataggio dei valori {#how-values-persist}

La sezione usa il gruppo di stato `seo_meta` e salva tramite la relazione `seoMeta()` del core, creando o aggiornando la riga. Non servono colonne sulle tue tabelle. I valori entrano subito nel livello 6, quello esplicito, del [resolver](/it/concepts/resolver-precedence).

## Più lingue {#several-languages}

Il core mantiene una [riga `seo_meta` per modello e lingua](/it/guide/multilingual). Passa le lingue in cui pubblichi una pagina per ottenere una scheda per lingua, disponibile da Filament 1.9:

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

Oppure configura le lingue una volta per tutte le risorse:

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

Ogni scheda modifica una riga indipendente e ha:

- **Contatori** coerenti con il sistema di scrittura: un titolo giapponese vuoto mostra `0 / 30`, quello inglese `0 / 60`.
- **Anteprima** di ricerca e social basata sui valori risolti in quella lingua.
- **Indicatori dei fallback** relativi a quella riga.
- **Badge** con il numero di campi impostati, per riconoscere le traduzioni vuote.

Con `ext-intl`, il nome della lingua viene mostrato nella lingua del pannello, per esempio `Italiano` o `Italian`; altrimenti compare il codice. Tutte le schede vengono validate e salvate insieme. Una lingua senza valori inseriti non produce una riga segnaposto.

::: details Binding personalizzati dello stato
Con più lingue il percorso è `seo_meta.{locale}.title`; con una sola resta `seo_meta.title`. Usa il percorso corretto nelle azioni personalizzate del form.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Schede inglese, italiano e giapponese nella demo Merchant; il giapponese usa limiti 30 e 80 e non ha una descrizione impostata." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Demo Merchant, 9 settembre 2026, con `locales: ['en', 'it', 'ja']`. La scheda giapponese vuota usa i propri contatori. Il titolo inglese proviene dal contenuto del modello: aggiungere una scheda non traduce i contenuti. Il punteggio Pro sopra i campi è quello dell'ultima scansione del record, non un punteggio per ciascuna lingua.*

### Con un plugin di traduzione {#with-a-translatable-plugin}

Con `lara-zeus/spatie-translatable` **1.x su Filament 4** oppure **2.x su Filament 5**, usa gli adapter di pagina Rankbeam per Edit e Create. Sostituisci solo gli import dei trait delle pagine; mantieni i trait del plugin su risorsa e lista, il plugin del pannello e l'azione `LocaleSwitcher`:

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

Ogni classe di pagina continua a dichiarare `use Translatable;`. Il plugin resta una dipendenza facoltativa dell'applicazione. Usa una versione aggiornata con le correzioni disponibili; la fixture locale verifica plugin 1.0.4 / Filament 4.13.1 e plugin 2.0.1 / Filament 5.8.1.

Il cambio di lingua conserva nell'editor le bozze non salvate di contenuto, metadati SEO e dati strutturati. Salva valida tutte le lingue visitate e le registra insieme in una transazione del database. Se la validazione fallisce, si apre la lingua da correggere. Gli upload vengono memorizzati al salvataggio. Uscire dalla pagina o ricaricarla elimina le bozze non salvate. Salvare non traduce i contenuti mancanti.

Gli adapter conservano i normali hook prima e dopo le operazioni e i mutator dei dati del form. Se la pagina ridefinisce `handleRecordCreation()`, `handleRecordUpdate()`, `callHook()` o i metodi delle transazioni, integra questo comportamento nella personalizzazione e verifica il salvataggio. Le transazioni del database non annullano le scritture su filesystem: mantieni la normale pulizia dei file orfani dell'applicazione.

Per campi di testo live personalizzati su Livewire 3, preferisci `->live()` o `->live(onBlur: true)` a un debounce esplicito. Quest'ultimo ritarda lo stato locale e può perdere gli ultimi caratteri durante un cambio rapido di lingua. Titolo e descrizione Rankbeam usano il debounce predefinito delle richieste.

I soli trait upstream ricaricano il form durante il cambio. Rankbeam impedisce le scritture accidentali dei metadati, ma quei trait non conservano le bozze SEO: migra Edit/Create agli adapter. Le schede esplicite `locales:` restano un editor condiviso e hanno la precedenza sul selettore di pagina.

Senza un elenco esplicito né una lingua della pagina, la sezione modifica la lingua dell'applicazione.

## Dati strutturati schema.org {#structured-data-schema-org}

Una sezione facoltativa consente agli editor di aggiungere JSON-LD senza scrivere codice. Aggiungila accanto alla sezione SEO:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

Senza il trait, usa direttamente `SEOSchemaFields::make()`.

La sezione scrive in `seo_meta.schema_jsonld`, la stessa colonna usata dal [renderer schema (EN)](/it/guide/schema). È un collegamento tra form e core: ogni documento viene costruito da un builder del core e validato da `SchemaValidator` prima del salvataggio. Non aggiunge una seconda logica schema.

La sezione offre:

- **Breadcrumb automatico:** un interruttore genera `BreadcrumbList` dalla catena degli antenati del record, tramite `BreadcrumbSchema::fromModelAncestors()`. Non richiede campi da compilare.
- **Blocchi schema:** un repeater per FAQ, con coppie domanda/risposta convertite in `FAQPage`, oppure Product, con nome, descrizione, immagine, marchio, SKU, prezzo, valuta e disponibilità. I builder sono `FAQSchema` e `ProductSchema` del core.

### Validazione {#validation}

Un blocco con JSON-LD non valido viene rifiutato al salvataggio con il messaggio del validatore: per esempio una FAQ senza risposta o un Product senza immagine o offerta. Per i risultati avanzati Product, Google richiede un'offerta. I blocchi completamente vuoti vengono ignorati.

### Dati memorizzati {#what-it-stores}

`schema_jsonld` contiene un oggetto se il documento è uno solo, un array JSON se sono più di uno: prima il breadcrumb, poi gli altri blocchi. Entrambe le forme sono JSON-LD valido e vengono emesse invariate da `@seo` / `renderSchema()`.

### Schema non gestiti dal form {#schema-it-doesn-t-manage}

Gli schema scritti in codice che il form non sa rappresentare vengono conservati invariati: un `@graph` personalizzato, un altro `@type` o un Product con campi non esposti, come recensioni, valutazioni e GTIN/MPN. Aprire e salvare il form non li sovrascrive.

## Risoluzione dei problemi {#troubleshooting}

- **Il campo salvato manca dalla pagina:** verifica che il template generi `@seo($model)` per lo stesso record e la stessa lingua.
- **Il campo usa ancora un fallback:** verifica che esista un valore esplicito salvato nella lingua attiva. Gli indicatori mostrano il livello risolto.
- **Manca una scheda lingua:** controlla `locales:`, la configurazione del pacchetto e il selettore della pagina, seguendo la priorità descritta sopra.

::: details Pannelli personalizzati in Testbench
Se avvii Filament in orchestra/testbench, registra `SupportServiceProvider` di Filament prima di `LivewireServiceProvider`. Filament sostituisce il `DataStore` di Livewire e l'ordine errato fa fallire i test con `ViewErrorBag::put(): ... null given`. Le app normali usano l'ordine corretto tramite package discovery.
:::
