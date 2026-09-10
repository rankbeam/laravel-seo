---
description: "Procedura per sostituire Yoast o Rank Math su un sito attivo: importazione nei campi vuoti per impostazione predefinita, simulazioni senza scritture e dati WordPress conservati."
---

# Runbook per la migrazione da WordPress a Rankbeam {#wordpress-→-rankbeam-migration-runbook}

Questa procedura accompagna la sostituzione di uno stack SEO WordPress, basato su Yoast o Rank Math, con Rankbeam su un sito attivo. Le verifiche precedono gli interventi distruttivi: per impostazione predefinita gli importatori compilano soltanto i campi vuoti, le simulazioni non scrivono dati e il database o le tabelle WordPress rimangono intatti finché non decidi di eliminarli.

È la guida operativa che affianca [Migrazione da WordPress](/it/guide/migrate-from-wordpress), dove trovi la mappatura dei campi, la gestione dei token dei template e le chiavi sorgente. Qui trovi l’ordine delle operazioni.

::: tip Prerequisiti
- **Core** (`rankbeam/laravel-seo`) per importare i metadati ed eseguire `seo:audit`.
- **Pro** (`rankbeam/laravel-seo-pro`) solo se devi migrare anche i **redirect**: la tabella `seo_redirects` appartiene a Pro.
- Contenuti già rappresentati da modelli Laravel, per esempio `App\Models\Post`, con il trait [`HasSEO`](/it/guide/quickstart) e una corrispondenza tra slug WordPress e modello: la chiave di rotta oppure una colonna indicata con `--match-by`.
:::

## Struttura della migrazione {#the-shape-of-the-migration}

Le righe WordPress sono identificate da **URL o post**; quelle di `seo_meta` sono **polimorfiche**, associate a un modello Eloquent. L’importazione cerca un modello per ogni riga WordPress. Il report distingue tre esiti:

| Esito | Significato | Intervento |
|---|---|---|
| **matched** | Riga associata a un modello, con scrittura in `seo_meta` | Nessuno |
| **url-only** | Nessun modello corrispondente, oppure `--model` non specificato | Decidi se la pagina richiede un modello o un redirect |
| **unmapped** | Dati senza destinazione nel core 3, soprattutto **author** | Ricollocali, per esempio con un hook `getSEOAuthor()` |

---

## Passaggio 0 — Coesistenza, senza cambio di produzione {#step-0-—-coexist-no-cutover-yet}

Prepara Rankbeam **accanto** al sito attivo. Aggiungi `HasSEO` ai modelli e genera i tag con la facade o la direttiva, ma **conserva** per ora l’installazione WordPress e il suo plugin SEO. Non hai ancora importato nulla: stai verificando che il nuovo stack si avvii.

Se durante la migrazione servi l’app Laravel e il sito WordPress dallo stesso host, mantienili su percorsi separati fino al passaggio 5.

## Passaggio 1 — Importare i metadati, iniziando con una simulazione {#step-1-—-import-the-metadata-dry-run-first}

Comincia sempre con `--dry-run`: **non scrive nulla** e mostra il report completo delle operazioni previste.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

Opzioni utili; per l’elenco completo esegui `php artisan seo:import-from --help`:

| Opzione | Funzione |
|---|---|
| `--model=` | FQCN del modello di destinazione. L’opzione è ripetibile, ma gli importatori WordPress associano **un solo** modello per esecuzione: ripeti il comando per ciascun tipo di contenuto. |
| `--match-by=` | Colonna del modello da confrontare con lo slug; il valore predefinito è la chiave di rotta. |
| `--post-type=` | Limita i lettori del database ai tipi indicati; predefiniti: `post` e `page`. |
| `--connection=` | Connessione del database che contiene le tabelle WordPress. |
| `--table=` | **Prefisso** delle tabelle WordPress; predefinito: `wp_`. |
| `--locale=` | Lingua a cui associare le righe di `seo_meta`. |
| `--redirects-csv=` | Esporta anche i candidati redirect nel file da usare al passaggio 3. |
| `--site-url=` | URL del vecchio sito, per ricavare i percorsi dagli URL assoluti. |
| `--overwrite` | Sostituisce i valori non vuoti già presenti in `seo_meta`; il comportamento predefinito **compila solo i campi vuoti**. |
| `--limit=` | Limita le righe sorgente, utile per una prima prova. |
| `--json` | Report leggibile dalle macchine. |

Quando la simulazione corrisponde alle attese, togli `--dry-run` per applicare l’importazione:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

L’importazione è **idempotente** e, senza `--overwrite`, **compila soltanto i campi vuoti**. Puoi ripeterla senza sovrascrivere i metadati già modificati in Rankbeam.

## Passaggio 2 — Leggere e archiviare il report di verifica {#step-2-—-read-and-archive-the-verification-report}

Ogni esecuzione produce un **Verification report** con i numeri da controllare prima di rimuovere qualsiasi componente. Salvalo in un file:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

Controlla queste voci:

- **matched** deve corrispondere al numero di pagine per cui prevedi metadati SEO.
- **url-only** è l’elenco delle pagine senza modello corrispondente. Decidi per ciascuna se serve un modello, un redirect al passaggio 3 o nessun intervento.
- **truncated** elenca i campi accorciati per rispettare la dimensione delle colonne di `seo_meta`: controlla quei titoli e quelle descrizioni.
- **unmapped** elenca i dati senza colonna nel core 3, **specificando ogni valore distinto di `author`**. Gli autori non sono salvati in una colonna: vanno gestiti con `getSEOAuthor()`. Usa il report per ricollocarli prima del passaggio in produzione.

## Passaggio 3 — Importare i redirect in Pro {#step-3-—-import-the-redirects-into-pro}

L’importatore del core **non scrive mai in `seo_redirects`**, che appartiene a Pro. Produce un CSV con struttura fissa e versionata, il **formato CSV redirect v1**: `source_path,target_url,status_code,note`. Importalo in Pro cominciando con una simulazione:

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Ogni riga viene validata come nel form redirect di Filament. Righe malformate, codici di stato non validi, **destinazioni esterne non sicure**, **sorgenti duplicate** e regole che creerebbero un **ciclo di redirect** vengono saltate con una motivazione, senza scritture silenziose. La simulazione valida l’intero file, inclusi cicli e duplicati, e non scrive nulla. Passa `--overwrite` per sostituire la destinazione di una regola esistente.

## Passaggio 4 — Verificare con `seo:audit --strict` {#step-4-—-verify-with-seo-audit-strict}

Usa l’audit gratuito, eseguito nel processo dell’applicazione, come controllo prima del passaggio. `--strict` termina con codice diverso da zero se **anche una sola** pagina presenta un problema, quindi può bloccare una pipeline CI o il cambio di produzione:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

L’audit controlla modello e resolver: presenza e lunghezza di titolo e descrizione, immagine OG, conflitti robots e formato del canonical. I controlli sull’HTML generato e sui canonical live, insieme al punteggio da 0 a 100, appartengono alla [scansione Pro](/it/pro/scan-issues). Esegui anche quella se hai Pro. Vedi [Audit SEO gratuito](/it/guide/audit).

Controlla poi alcune pagine reali nel browser. Apri il sorgente e verifica che `<title>`, `<meta name="description">`, canonical, robots e tag OpenGraph contengano i valori importati.

## Passaggio 5 — Verificare prima di rimuovere pacchetto o tabelle precedenti {#step-5-—-verify-before-removing-the-legacy-package-table}

**Non** eliminare il database WordPress, il plugin SEO o il pacchetto precedente finché **tutti** questi controlli non sono completati:

- [ ] Importazione eseguita per **ogni** tipo di contenuto, con un solo `--model` per esecuzione.
- [ ] Report archiviato con il conteggio **matched** atteso e nessuna riga **url-only** inattesa.
- [ ] Ogni valore **author non mappato** da conservare è stato ricollocato.
- [ ] Redirect importati in Pro con `seo-pro:redirects-import`; alcuni vecchi URL restituiscono davvero un 301 verso quelli nuovi.
- [ ] `php artisan seo:audit --strict` termina con codice `0`.
- [ ] Con Pro, `php artisan seo:doctor` non segnala tabelle `seo` precedenti residue né conflitti di `config/seo.php`.
- [ ] Pagine generate controllate a campione nel browser.

Con il comportamento predefinito idempotente che compila solo i campi vuoti, puoi ripetere il passaggio 1 prima di questi controlli senza sovrascrivere i metadati già impostati. I dati originali sono ancora in WordPress.

## Passaggio 6 — Dismettere WordPress {#step-6-—-decommission}

Solo dopo aver completato la checklist del passaggio 5, disattiva il sito WordPress e rimuovi database, tabelle e pacchetto SEO precedente. Conserva un backup del database finché non hai verificato che il nuovo stack funzioni correttamente in produzione.

::: tip Ripristino
Con le opzioni predefinite, i passaggi da 1 a 4 non eliminano dati: le scritture in `seo_meta` aggiungono i valori mancanti, i redirect vengono validati e possono essere rimossi, mentre i dati WordPress restano intatti. Prima del passaggio 6 puoi tornare a *servire WordPress*; dopo, devi *ripristinare il suo backup*.
:::

---

Stai passando da un pacchetto SEO **Laravel**, come ralphjsmit, artesaos o Spatie? Consulta [Migrazione da altri pacchetti Laravel](/it/guide/migrate-from-other-packages).
