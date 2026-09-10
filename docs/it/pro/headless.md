---
description: "Scansioni, redirect e registrazione dei 404 funzionano senza Filament. Riferimento dei comandi per gestire Pro tramite Artisan."
---

# Uso senza pannello {#headless-usage}

Le funzioni Pro — scansioni, redirect e registrazione dei 404 — appartengono al motore e non richiedono Filament. Il pannello è un’interfaccia di gestione; questi comandi ne sono la controparte senza interfaccia grafica.

## Riferimento dei comandi {#command-reference}

### Installazione e diagnostica {#setup-health-check}

| Comando | Funzione |
|---|---|
| `seo-pro:install` | Pubblica `config/seo-pro.php` e le migrazioni Pro, le esegue e mostra i passaggi successivi; opzioni `--no-migrate` e `--force`. |
| `seo:doctor` | Controlla URL dell’app, tabelle core/Pro, destinazioni, sitemap, code per carico di lavoro, funzioni facoltative e stato operativo. Ogni avviso indica la correzione; `--json` serve per il monitoraggio. |

`seo-pro:install` è il percorso di installazione documentato. Le migrazioni Pro devono essere pubblicate, perché il pacchetto non le carica automaticamente: l’installer completa quindi lo schema dopo `composer require`. È idempotente e può essere ripetuto.

`seo:doctor` non effettua chiamate di rete e non stampa segreti. Il controllo AI indica soltanto se la variabile della chiave configurata è *impostata*. Verifica configurazione e cronologia recente, ma non può dimostrare che un cron esterno o un worker stia funzionando. Termina con codice diverso da zero solo per errori critici, come una tabella obbligatoria mancante; gli avvisi di un ambiente localhost non causano il fallimento. `--json` assegna a ogni controllo un `id` stabile. Eseguilo dopo l’[installazione](/it/pro/installation) e in CI.

### Scansioni {#scanning}

| Comando | Funzione |
|---|---|
| `seo-pro:scan` | Accoda una scansione completa delle destinazioni registrate. `--sync` la esegue nel processo corrente; le opzioni di **blocco CI** `--fail-on-error`, `--fail-on-warning`, `--report=` e `--format=json\|md\|html` richiedono `--sync`. |
| `seo-pro:scan-status` | Riepilogo dell’ultima esecuzione e problemi aperti, dai più gravi; `--limit=20`, `--severity=critical\|warning\|notice`. |
| `seo-pro:scan-recover` | Segna come fallite le esecuzioni abbandonate da un worker terminato. |
| `seo-pro:scan-prune` | Elimina esecuzioni concluse e relativi problemi oltre la finestra di conservazione. |

### Crawler dei link interrotti {#broken-link-crawler}

È disattivato per impostazione predefinita. Abilita `seo-pro.broken_links.enabled` ed esegui le migrazioni delle sue due tabelle, pubblicate da `seo-pro:install`. La scansione procede con job in coda soggetti a limiti; usa un worker dedicato. Le opzioni sono in [Configurazione in produzione](/it/pro/production).

| Comando | Funzione |
|---|---|
| `seo-pro:broken-links-scan` | Accoda una scansione limitata e riprendibile; `--scope=internal_only\|internal_and_external`, seed aggiuntivi con `--url=*`. |
| `seo-pro:broken-links-status` | Riepilogo dell’ultima scansione, problemi aperti e [ispezioni per tipo](/it/pro/broken-links#typed-link-inspections) dell’esecuzione; opzioni di **blocco CI** `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=`. |
| `seo-pro:broken-links-cancel` | Annulla una scansione in corso o in coda; `{run?}` usa per impostazione predefinita l’ultima attiva. |
| `seo-pro:broken-links-recover` | Segna come fallite le scansioni abbandonate da un worker, con lease scaduto. |
| `seo-pro:broken-links-prune` | Applica la conservazione del crawler a vecchie esecuzioni e problemi risolti. |

### Redirect e 404 {#redirects-404s}

| Comando | Funzione |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Crea una regola redirect; `--code=301`, `--regex`, `--no-preserve-query`, `--note=`. |
| `seo-pro:404-list` | Elenca i 404 registrati, dai più richiesti; `--status=new\|ignored\|redirected\|all`, `--limit=20`. |
| `seo-pro:redirects-flush-hits` | Scrive nel database i contatori redirect accumulati in cache quando `redirects.hits.flush_immediately=false`. |
| `seo-pro:404-prune` | Elimina i vecchi 404 e applica il limite di righe. |

### Checklist della pagina {#on-page-checklist}

| Comando | Funzione |
|---|---|
| `seo-pro:checklist {model} {id}` | Checklist pass/warn/fail di un modello, con controlli sulle parole chiave; `--json`, `--strict`, `--locale=`. Vedi [Checklist della pagina](/it/pro/on-page-checklist). |

La stessa checklist è disponibile come `SeoPro::checklistFor($model)`. Controlla aspetti editoriali — presenza delle parole chiave, lunghezza, immagini e link interni — ed è distinta dal [punteggio SEO](/it/pro/scoring).

### Search Console, sola lettura {#search-console-read-only}

| Comando | Funzione |
|---|---|
| `seo-pro:search-console` | Pagine con problemi aperti **e** traffico di ricerca, ordinate per opportunità di intervento; `--view=attention` è il valore predefinito. |
| `seo-pro:search-console --view=pages` | Pagine principali per impressioni, clic, CTR e posizione. |
| `seo-pro:search-console --view=queries` | Query principali; `--days=`, `--limit=`, `--json`. |

Le stesse metriche sono disponibili con `SeoPro::searchConsole()`: vedi [Search Console](/it/pro/search-console). L’integrazione è disattivata per impostazione predefinita e opera esclusivamente in lettura.

### Assistenza AI {#ai-assist}

| Comando | Funzione |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Suggerimenti di titolo e descrizione in JSON; `--field=title\|description\|all`. Vedi [Assistenza AI](/it/pro/ai-assist). |
| `seo-pro:ai-suggest --issue={id}` | Spiegazione testuale della correzione di un problema di scansione, in JSON. |

### Risolvere un 404 in un passaggio {#resolving-a-404-in-one-step}

`--from-404={path}` corrisponde all’azione *Crea redirect* del monitor 404. Crea la regola **e** segna la voce del log come reindirizzata, collegandola alla nuova regola:

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

Il comando usa gli stessi validatori del form Filament. Regex non valide, valori troppo grandi e destinazioni esterne escluse dall’elenco consentito vengono rifiutati prima di scrivere dati.

## Pianificazione consigliata {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

La guida [Configurazione in produzione](/it/pro/production) indica la frequenza consigliata per ogni comando ricorrente, insieme a organizzazione delle code, worker, tentativi, recupero, conservazione e **telemetria** strutturata delle esecuzioni concluse: pagine scaricate, link controllati, URL bloccati, durata e ritardo della coda.

## Cosa richiede l’interfaccia Filament? {#what-needs-the-filament-ui}

Il motore funziona allo stesso modo con o senza Filament: pipeline di scansione, problemi tracciati, corrispondenza dei redirect, registrazione 404, pulizia e recupero. Il pannello aggiunge le *viste*: dashboard con avanzamento e statistiche per gravità, filtri dei problemi e dialoghi per pagina, pulsanti ignora/riapri, form CRUD dei redirect e tabella 404 con azione diretta. Ignorare o riaprire un problema non ha ancora un comando dedicato: usa il pannello oppure `markIgnored()` e `reopen()` sul modello `SEOScanIssue`, in Tinker o nel tuo codice.
