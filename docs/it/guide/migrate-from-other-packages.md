---
description: "Passa a Rankbeam da un altro pacchetto SEO Laravel: mappa API e dati su HasSEO e saveSEO(), con un importatore per i metadati salvati per modello."
---

# Migrazione da altri pacchetti SEO Laravel

Questa guida collega le API e i dati dei pacchetti più comuni al trait [`HasSEO`](/it/guide/quickstart) e a `saveSEO()`. Per i metadati salvati per modello da ralphjsmit è disponibile un importatore da riga di comando.

::: tip Arrivi da WordPress?
Per siti con Yoast o Rank Math, consulta la [migrazione da WordPress (EN)](/it/guide/migrate-from-wordpress): include l'importatore CSV e i lettori del database.
:::

| Pacchetto di partenza | Dove salva i dati | Percorso |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | Tabella polimorfica `seo` | `php artisan seo:import-from ralphjsmit` e sostituzione del trait |
| [`artesaos/seotools`](#from-artesaos-seotools) | Nessuna tabella: runtime e configurazione | Sostituzione delle chiamate con `saveSEO()` o getter calcolati |
| [`spatie/*`](#from-spatie-packages) | Nessuna tabella SEO: builder schema-org e sitemap | Mantieni i componenti complementari e migra gli altri |

Tra questi, solo ralphjsmit salva i metadati in una tabella da importare. Gli altri costruiscono tag durante la richiesta: trasferisci le relative chiamate in valori salvati o calcolati.

## Da `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

Il pacchetto salva una riga polimorfica per modello nella tabella `seo`, con una struttura vicina a `seo_meta`. L'importazione può quindi essere eseguita in modo idempotente.

### 1. Installa Rankbeam accanto al pacchetto esistente {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

I due pacchetti possono coesistere durante la migrazione: usano tabelle e namespace dei trait diversi.

::: warning La chiave di configurazione è condivisa
Un `config/seo.php` pubblicato da ralphjsmit può nascondere la configurazione Rankbeam. Fanne una copia, rimuovilo e pubblica quello Rankbeam con `php artisan vendor:publish --tag=seo-config`.
:::

### 2. Esegui l'importatore {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

L'importatore legge la tabella `seo`, risolve ogni riga nel relativo modello Eloquent e scrive in `seo_meta`.

| Opzione | Effetto |
|---|---|
| `--dry-run` | Mostra cosa verrebbe importato senza scrivere |
| `--model="App\Models\Post"` | Limita l'importazione a una o più classi, ripetibile |
| `--locale=fr` | Lingua delle righe importate; default: lingua dell'app |
| `--table=legacy_seo` | Nome alternativo della tabella sorgente |
| `--connection=legacy` | Connessione da cui leggere la sorgente |
| `--limit=100` | Massimo di righe, utile per procedere per lotti |
| `--overwrite` | Sostituisce anche i valori non vuoti; senza questa opzione riempie solo quelli vuoti |
| `--json` | Report in formato elaborabile |
| `--force` | Salta la conferma interattiva, per script e CI |

Ripetere l'importazione aggiorna le stesse righe senza crearne di duplicate. Per impostazione predefinita vengono riempiti solo i campi vuoti: i valori già impostati in Rankbeam restano invariati. Usa `--overwrite` solo se vuoi sostituirli.

### 3. Sostituisci il trait sui modelli {#_3-swap-the-trait-on-your-models}

I nomi dei metodi differiscono in parte. Il trait Rankbeam legge `seo_meta`:

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Se usavi `getDynamicSEOData()`, trasferisci la logica nei getter per campo di Rankbeam: `getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()` e `getSEOAlternates()`. Consulta la [guida rapida](/it/guide/quickstart). I valori espliciti si salvano con:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Mappatura dei campi {#field-mapping}

L'importatore mappa i campi esplicitamente: non copia colonne assenti nello schema del core 3.

| `seo` di ralphjsmit | `seo_meta` di Rankbeam | Note |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | Ricalcolati dal modello corrente, non copiati direttamente |
| `title` | `title` | Troncato a 70 caratteri, limite della colonna; il report segnala le riduzioni |
| `description` | `description` | Troncata a 160 caratteri, con segnalazione |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Troncato a 50 caratteri |
| `image` | `og_image` | Il resolver lo usa anche come fallback di `twitter:image` |
| `author` | Non importato | Il core 3 non ha questa colonna in `seo_meta`. L'autore dell'articolo si gestisce nella risoluzione; il report conta le righe con autore per consentirti di decidere dove trasferirlo |
| `id`, `created_at`, `updated_at` | Non importati | Campi strutturali |

**Perché il tipo polimorfico viene ricalcolato:** l'importatore risolve il modello reale e usa il suo `getMorphClass()`. La relazione segue così la [morph map corrente dell'app](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types), anche se la sorgente usava un'altra convenzione. Le righe di modelli eliminati vengono saltate e segnalate, senza creare record orfani.

### Come leggere il report {#what-the-report-tells-you}

Senza `--json`, il comando mostra gli esiti e tre sezioni da controllare:

- **Truncated:** valori accorciati per rispettare la colonna di destinazione.
- **Not imported:** colonne con dati, come `author`, senza una destinazione nel core 3.
- **Skipped rows by reason:** righe vuote, modelli eliminati o tipi non risolvibili.

### Verifica {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Dopo aver verificato il risultato, puoi rimuovere `ralphjsmit/laravel-seo` e la sua tabella `seo`.

## Da `artesaos/seotools` {#from-artesaos-seotools}

Questo pacchetto costruisce i tag a runtime tramite `SEOMeta`, `OpenGraph`, `TwitterCard` e `JsonLd`, spesso nel controller, con default in `config/seotools.php`. Non salva dati per modello: non c'è una tabella da importare.

| Chiamata artesaos/seotools | Equivalente Rankbeam |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` oppure `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` oppure `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` oppure `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | `saveSEO(['focus_keywords' => [...]])`; vedi [audit](/it/guide/audit) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [Grafo JSON-LD](/it/guide/schema) |
| Default di `config/seotools.php` | Default di `config/seo.php` e [priorità del resolver](/it/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` nel layout | `@seo($model)`; vedi [Blade](/it/guide/blade) |

Invece di impostare i tag in ogni controller, salva i dati una volta per modello e lascia che il resolver li emetta. I fallback del sito passano alla [configurazione Rankbeam (EN)](/reference/configuration); per pagine statiche associate a rotte usa `@seoForRoute()`.

## Da pacchetti Spatie {#from-spatie-packages}

I builder Spatie comunemente usati per SEO non costituiscono una tabella di metadati da importare. Puoi mantenere o sostituire i singoli componenti:

- **`spatie/schema-org`:** builder JSON-LD fluente. Rankbeam offre un [grafo schema](/it/guide/schema) con builder `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` e `Organization`, salvati in `seo_meta.schema_jsonld` e deduplicati nel rendering. Puoi passare il risultato `->toArray()` degli oggetti Spatie a `saveSEO(['schema_jsonld' => $array])`, oppure ricostruirli con i builder Rankbeam.
- **`spatie/laravel-sitemap`:** il [registro Rankbeam](/it/guide/sitemaps) lo usa già. Registra i modelli come sorgenti oppure mantieni la tua sitemap Spatie e disabilita la rotta Rankbeam.

Per [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO), che costruisce metatag a runtime, segui lo stesso criterio di artesaos: sposta le chiamate `setTitle` e `addMeta` in `saveSEO()` o nei getter calcolati.

## Estendi l'importatore {#extending-the-importer}

`seo:import-from` usa un registro di implementazioni `Rankbeam\Seo\Importing\Contracts\Importer`. Le sorgenti incluse sono `ralphjsmit` e quelle WordPress: `wordpress-csv`, `yoast`, `rank-math`, descritte nella [guida WordPress (EN)](/it/guide/migrate-from-wordpress). Aggiungi una sorgente nel service provider:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```
