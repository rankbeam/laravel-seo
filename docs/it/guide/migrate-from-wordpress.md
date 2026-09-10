---
description: "Trasferisci nei modelli Laravel i metadati SEO scritti in Yoast o Rank Math: titoli, descrizioni, canonical, robots e parole chiave principali. Riferimento per la mappatura dei campi dell’importatore."
---

# Migrazione da WordPress {#migrating-from-wordpress}

Se stai trasferendo un sito di contenuti da WordPress, Rankbeam può importare nei modelli Laravel i metadati SEO scritti dal tuo team in Yoast o Rank Math: titoli, descrizioni, canonical, direttive robots, parole chiave principali e valori personalizzati per i social. Puoi così conservare quel lavoro durante il passaggio.

::: tip Devi effettuare il passaggio in produzione?
Questa pagina è il *riferimento* dell’importatore: mappatura dei campi, token e chiavi di origine. Per la **procedura** completa — coesistenza, importazione, verifica e dismissione — segui il [runbook per la migrazione da WordPress](/it/guide/wordpress-migration-runbook).
:::

Il comando `seo:import-from` offre due percorsi:

| Percorso | Sorgente | Quando usarlo |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | Un foglio di calcolo esportato da WordPress | Nella maggior parte delle migrazioni gestite da agenzie, quando vuoi controllare gli URL esatti |
| [**Database**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | Il database WordPress | Per includere anche i valori OpenGraph/Twitter personalizzati e i redirect di Rank Math |

Entrambi sono **idempotenti**: una nuova esecuzione aggiorna le stesse righe senza duplicarle. Supportano **`--dry-run`** e, per impostazione predefinita, compilano soltanto i campi vuoti, senza sovrascrivere i dati SEO già impostati in Rankbeam. Passa **`--overwrite`** per sostituire invece i valori esistenti con quelli importati.

## Come le righe WordPress diventano righe di `seo_meta` {#how-wordpress-rows-become-seo-meta-rows}

Una riga WordPress è identificata da un **URL** o dall’**ID di un post**. `seo_meta` è invece polimorfica: ogni riga deve appartenere a un vero modello Eloquent. L’importatore cerca quindi un modello per ogni riga WordPress e distingue nel report le righe associate da quelle che contengono soltanto un URL:

- **Associate a un modello.** Indica il modello di destinazione con `--model="App\Models\Post"`. Lo **slug** di ogni riga, cioè l’ultimo segmento del percorso nell’URL oppure `post_name` di WordPress, viene cercato nel modello usando la sua chiave di rotta predefinita o la colonna scelta con `--match-by=`. Le righe corrispondenti vengono scritte in `seo_meta`.
- **Solo URL.** Una riga senza corrispondenza, oppure un’esecuzione senza `--model`, non può creare una riga in `seo_meta`: manca il modello a cui associarla. Il report la indica come saltata con motivo `url-only`. Il suo canonical può comunque produrre un [candidato redirect](#redirects).

Post e pagine WordPress di solito corrispondono a modelli Laravel *diversi*. Esegui l’importatore una volta per tipo di contenuto, limitando le righe selezionate:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning I tipi di post personalizzati non vengono letti automaticamente
I lettori del database esaminano soltanto i tipi **`post`** e **`page`** per impostazione predefinita. Se il sito usa tipi personalizzati, come `product`, `event` o `pathology` definiti da un tema, indica ciascuno esplicitamente ripetendo `--post-type=`:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. Importazione CSV {#_1-csv-import}

Il percorso CSV copre la maggior parte delle migrazioni gestite da agenzie. Esporta una riga per URL con questa intestazione. L’ordine delle colonne è libero; quelle non riconosciute vengono ignorate e segnalate:

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Esegui l’importazione:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Colonna | Destinazione in `seo_meta` | Note |
|---|---|---|
| `url` | *(chiave di corrispondenza)* | Lo slug, ultimo segmento del percorso, viene confrontato con il modello. Obbligatorio. |
| `title` | `title` | Troncato a 70 caratteri; i valori troppo lunghi vengono segnalati. |
| `description` | `description` | Troncata a 160 caratteri. |
| `canonical` | `canonical` | Alimenta anche i [candidati redirect](#redirects). |
| `robots` | `robots` | Conservato così com’è, per esempio `noindex, nofollow`, e troncato a 50 caratteri. |
| `focus_keyword` | `focus_keywords` | Parole chiave separate da virgole; la prima è quella principale. |

Le righe malformate vengono saltate e conteggiate: quelle prive di `url` e quelle con un numero di colonne diverso dall’intestazione.

---

## 2. Importazione dal database: Yoast e Rank Math {#_2-database-import-yoast-rank-math}

Se hai ancora il database WordPress, l’importatore può leggere direttamente i metadati SEO, compresi i valori OpenGraph/Twitter personalizzati e i redirect di Rank Math, che un’esportazione CSV spesso non include.

### Configurare una connessione a WordPress {#point-a-connection-at-wordpress}

Aggiungi una connessione al database WordPress in `config/database.php`:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Importa poi i dati. Il prefisso predefinito delle tabelle è `wp_`; puoi cambiarlo con `--table=`:

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

Il lettore esamina `{prefix}posts`, selezionando post e pagine pubblicati, recupera i metadati del plugin da `{prefix}postmeta` e cerca lo slug `post_name` nel modello di destinazione.

::: tip Prefisso delle tabelle diverso da quello predefinito
Gli hosting WordPress gestiti usano spesso un prefisso casuale, per esempio `wppg_` anziché `wp_`. Controlla i nomi nei `CREATE TABLE` del dump e passa il prefisso effettivo, come `--table=wppg_`, per individuare `{prefix}posts` e `{prefix}postmeta`.
:::

::: tip Leggere un dump ripristinato su MySQL 8
Se ripristini un dump WordPress su MySQL 8+ per leggerlo in locale, allenta la modalità SQL strict nella sessione prima di caricare il file `.sql`. Le modalità predefinite `STRICT`/`NO_ZERO_DATE` di MySQL 8 rifiutano i valori datetime predefiniti `'0000-00-00'` di WordPress: il ripristino si interrompe con `Invalid default value for 'post_date'` prima ancora dell’importazione SEO.

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Mappatura dei campi {#field-mapping}

Entrambi gli importatori mappano i campi **esplicitamente**. Una chiave senza colonna corrispondente nel core 3 viene segnalata come *non mappata*; non viene creata una destinazione inesistente.

| Chiave meta Yoast | Chiave meta Rank Math | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Vengono conservate solo le differenze rispetto ai valori predefiniti di WordPress. Una normale pagina indicizzabile lascia quindi `robots` nullo ed eredita il valore predefinito del sito. I flag separati di Yoast — `noindex`, `nofollow` e quelli avanzati `noarchive`, `nosnippet`, `noimageindex` — vengono uniti in una stringa. L’array serializzato `robots` di Rank Math viene letto allo stesso modo, escludendo i valori predefiniti `index` e `follow`.

**Chiavi non mappate**, segnalate ma non copiate: ID degli allegati immagine (`*-image-id`), punteggi delle parole chiave o SEO (`linkdex`, `content_score`, `rank_math_seo_score`), categorie principali e marcatori rich snippet di Rank Math. Per questi ultimi il [grafo schema](/it/guide/schema) offre una struttura tipizzata più articolata.

::: warning I canonical vengono importati senza modificarli
Un canonical esplicito, `rank_math_canonical_url` o `_yoast_wpseo_canonical`, viene copiato **esattamente come è memorizzato**. Se punta a un URL assoluto del *vecchio* dominio, come `https://oldsite-staging.example.com/page/` su un hosting gestito o di staging, continuerà a puntare lì: l’importatore non riscrive l’host. `--site-url` ricava i *percorsi* dagli URL assoluti per i [candidati redirect](#redirects) e la corrispondenza delle righe CSV, ma **non** modifica i canonical salvati. Dopo un cambio di dominio, controllali e aggiorna l’host, oppure cancellali per usare il canonical della pagina stessa generato dal resolver. La maggior parte delle pagine non ha un canonical esplicito e non è coinvolta: Yoast e Rank Math lo generano durante il rendering.
:::

### Token dei template {#template-tokens}

Yoast e Rank Math salvano titoli e descrizioni come **template**: Yoast usa token come `%%title%%`, Rank Math `%title%`. L’importatore **risolve i token ricavabili** ed **elimina gli altri**, evitando di salvare stringhe grezze `%%token%%`:

| Token | Valore risolto |
|---|---|
| `%%title%%` / `%title%` | Titolo del post WordPress |
| `%%sitename%%` / `%sitename%` | Nome del blog da `wp_options`, nell’importazione dal database |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *Eliminati*: restano vuoti e i separatori circostanti vengono ripuliti |

Se vengono risolti token, il report lo segnala. **Controlla i titoli importati** e correggi quelli che dipendevano da token non ricavabili.

---

## Redirect {#redirects}

`seo_redirects` appartiene a [Rankbeam **Pro**](/it/pro/installation), quindi un importatore del core non scrive direttamente in quella tabella. Passa `--redirects-csv=` per **generare un CSV** con le colonne della tabella redirect di Pro — `source_path,target_url,status_code,note` — da importare poi in Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

I candidati redirect provengono da queste sorgenti:

- **Importazione CSV:** se il `canonical` di una riga punta a un **percorso diverso** dal suo `url`, viene proposto un `301` dal vecchio percorso al canonical. Un canonical con lo stesso percorso non viene esportato, perché creerebbe un ciclo.
- **Database Rank Math:** regole attive nella tabella `{prefix}rank_math_redirections`. Vengono esportate solo quelle con **corrispondenza esatta**; regex e regole contains/start/end vengono segnalate come saltate perché non identificano un solo percorso.
- **Yoast gratuito:** non ha una tabella redirect. Quella di Yoast Premium ha uno schema esterno al pacchetto gratuito. Per i redirect Yoast usa il percorso CSV.

I candidati sono **proposte da verificare**. Controlla il CSV e importalo con [`seo-pro:redirects-import`](/it/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro), che valida ogni riga rifiutando cicli, destinazioni non sicure e duplicati. La struttura del file è un contratto stabile, il **formato CSV redirect v1**: `source_path,target_url,status_code,note`.

---

## Cosa mostra il report {#what-the-report-tells-you}

Senza `--json`, il comando stampa una tabella degli esiti — created, updated, unchanged, skipped, scanned — un **Verification report** e le sezioni da controllare:

- **Verification report:** riepilogo di **matched**, righe associate a un modello, **url-only**, righe senza corrispondenza, e conteggi dei valori troncati o non mappati.
- **Truncated:** valori accorciati per rispettare la dimensione delle colonne di `seo_meta`.
- **Not imported:** chiavi sorgente valorizzate ma prive di destinazione nel core 3, **compreso ogni valore distinto di `author`**. L’autore non è una colonna salvata: va gestito con [`getSEOAuthor()`](/it/concepts/resolver-precedence). Il report elenca quindi i dati da ricollocare.
- **Redirect candidates:** numero di candidati scritti e file di destinazione.
- **Skipped rows by reason:** righe solo URL, post senza metadati SEO e regole redirect non esatte.
- **Warnings:** avvisi, per esempio sulla risoluzione dei token dei template.

Aggiungi `--json` per ottenere gli stessi dati in un formato leggibile dalle macchine. Il blocco `verification` contiene i conteggi matched/url-only e ogni valore di autore.

### Verifica {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict` termina con codice diverso da zero se almeno una pagina ha un problema. Vedi [Audit SEO gratuito](/it/guide/audit). Per la procedura completa e ordinata — coesistenza → importazione → verifica → dismissione — segui il [runbook per la migrazione da WordPress](/it/guide/wordpress-migration-runbook).

---

Stai passando da un pacchetto SEO **Laravel**, come ralphjsmit, artesaos o Spatie? Consulta [Migrazione da altri pacchetti Laravel](/it/guide/migrate-from-other-packages).
