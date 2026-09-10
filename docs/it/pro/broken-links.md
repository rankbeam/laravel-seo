---
description: "Un crawler con limiti configurabili e ripresa del lavoro che registra link non funzionanti: percorsi interni correggibili con un redirect e, su richiesta, link esterni. Disattivato per impostazione predefinita."
---

# Crawler dei link non funzionanti {#broken-link-crawler}

Un **crawler con limiti configurabili e ripresa del lavoro** visita il sito, segue i link di ogni pagina e registra quelli che non rispondono correttamente. Controlla i link **interni**, cioè percorsi sul tuo host correggibili creando un redirect, e facoltativamente quelli **esterni**. È **disattivato per impostazione predefinita**.

Il crawler segue tre criteri:

- **Limiti e ripresa del lavoro.** Ogni scansione viene suddivisa in piccoli job in coda, ciascuno limitato a poche pagine. I job accodano una continuazione finché la scansione termina o raggiunge un limite. Anche l'intera esecuzione ha un limite, 2000 pagine per impostazione predefinita. Solo `null` rimuove esplicitamente questo tetto. Mantieni limiti e ritardi adeguati al sito che stai controllando.
- **Impostazioni prudenti.** L'ambito predefinito è `internal_only`: controlla solo link sul tuo host, senza richieste a terzi. Ogni richiesta interna o esterna passa da **SsrfGuard**, che verifica schema consentito, ambito dell'host e rifiuto degli indirizzi privati. Il controllo dei link esterni va abilitato esplicitamente e mantiene queste protezioni.
- **Separato dal punteggio SEO.** I risultati hanno tabelle proprie e non scrivono in `seo_scan_issues` né nel punteggio 0–100. Il punteggio della pagina non cambia in base ai link in uscita non funzionanti: questi vengono gestiti separatamente come problemi operativi.

## Cosa include {#what-you-get}

Nel dashboard Filament, solo quando il crawler è attivo:

- **Broken-link summary**: conteggio dei problemi aperti, distinti tra interni ed esterni, e ultima scansione, con link alla tabella dei risultati.
- **Broken-link crawl**: avanzamento della scansione in corso, con pagine visitate, link controllati e problemi trovati.
- **Broken links per scan**: andamento nelle scansioni recenti.
- **Una risorsa per i risultati**: tutti i link `origine → destinazione` non funzionanti, filtrabili; per quelli interni puoi creare un redirect.

Senza Filament, gli stessi dati sono disponibili dai comandi `seo-pro:broken-links-*`.

## Perché è disattivato per impostazione predefinita {#why-it-s-off-by-default}

A differenza del rendering e del calcolo dei punteggi, il crawler **effettua richieste di rete** e richiede infrastruttura. L'attivazione è quindi esplicita:

- Le due tabelle principali hanno migrazioni **da pubblicare**, come tutte quelle Pro. Esegui le migrazioni prima che l'interfaccia le interroghi; le ispezioni tipizzate usano anche `seo_broken_link_inspections`.
- La scansione usa una **coda dedicata** e richiede un **worker**. Senza worker non avanza.
- La conferma dei problemi avviene **su più scansioni**, come descritto sotto: la funzione è pensata per un uso **programmato** nel tempo.

## Configurazione iniziale {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Esegui quindi le migrazioni. `seo-pro:install` pubblica ed esegue tutte le migrazioni Pro; puoi rieseguirlo senza duplicarle:

```bash
php artisan seo-pro:install
```

Avvia un **worker dedicato** alla coda del crawler, `seo-broken-links`, per evitare che una scansione lunga preceda nella stessa coda i job necessari agli utenti:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Verifica la configurazione con `seo:doctor`: controlla l'attivazione, le tabelle e che la coda usi una connessione effettiva, diversa da `sync`. Ogni segnalazione indica come intervenire:

```bash
php artisan seo:doctor
```

La guida alla [configurazione in produzione](/it/pro/production) descrive Redis, Supervisor, connessioni dedicate e dimensionamento dei batch.

## Avviare una scansione {#running-a-crawl}


Usa l'azione **Scan now** nel dashboard oppure, senza Filament:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Entrambi i comandi **accodano** la scansione; il lavoro viene eseguito dal worker.

## Quando un link viene segnalato {#how-a-link-gets-flagged}

Un link viene confermato come non funzionante dopo `seo-pro.broken_links.mark_broken_after_failures` **scansioni consecutive** fallite. Un esito positivo azzera il contatore; la soglia predefinita è **3**. Con questa soglia, un singolo disservizio transitorio non basta a confermare il problema: per questo la scansione va **programmata**, non eseguita solo una volta. Con cadenza settimanale servono tre scansioni fallite, circa due settimane dalla prima osservazione e fino a circa tre dalla comparsa del problema. Aumenta la frequenza o riduci la soglia se serve una conferma più rapida.

## Ispezioni tipizzate dei link {#typed-link-inspections}

Oltre alla raggiungibilità, ogni link viene sottoposto a **ispezioni tipizzate**: controlli su slash finali incoerenti, codifiche anomale, catene di redirect, href `javascript:`, ancore interne mancanti, testi poco descrittivi e altro. Ogni ispezione ha una **gravità** stabilita, `critical`, `warning` o `notice`, come i [problemi di scansione](/it/pro/scan-issues), e viene registrata per esecuzione in `seo_broken_link_inspections`. A differenza di un problema confermato su più scansioni, l'ispezione fotografa **la singola esecuzione** e compare **già alla prima scansione**, anche nei controlli CI.

### Riferimento delle ispezioni {#inspection-reference}

| Ispezione | Gravità | Segnala | Ambito |
| --- | --- | --- | --- |
| `broken_link` | critical | La destinazione ha restituito HTTP ≥ 400 | qualsiasi link |
| `redirect_chain` | notice · warning | La destinazione risponde dopo un redirect; `warning` oltre `redirect_chain_warning_hops` | qualsiasi link |
| `link_unreachable` | notice | Destinazione irraggiungibile in questa scansione: errore di rete, timeout o blocco, anche transitorio | qualsiasi link |
| `insecure_link` | warning | Link `http://` in un sito `https`, con downgrade del trasporto | qualsiasi link |
| `trailing_slash` | notice | Percorso interno diverso dalla convenzione dichiarata per lo slash finale; **disattivata se `trailing_slash` non è impostato** | interni |
| `double_slash_url` | warning | Percorso interno con `//`, cioè un segmento vuoto | interni |
| `duplicate_query_param` | notice | Chiave query ripetuta, come `?a=1&a=2`; esclusa la sintassi array `key[]` | interni |
| `non_ascii_url` | notice | Percorso interno con caratteri non ASCII non codificati | interni |
| `uppercase_url` | notice | Lettere maiuscole nel percorso interno; verifica eventuali URL equivalenti serviti separatamente | interni |
| `underscore_in_url` | notice | Underscore nel percorso interno; per separare le parole negli URL si preferiscono i trattini | interni |
| `javascript_link` | warning | Ancora con href `javascript:`, che non rappresenta una normale destinazione navigabile dai crawler | qualsiasi ancora |
| `missing_fragment` | warning | Frammento `#fragment` della stessa pagina senza `id` o `name` corrispondente | stessa pagina |
| `non_descriptive_anchor` | notice | Testo generico, come “click here” o “read more”, oppure un URL senza testo descrittivo | qualsiasi ancora |
| `absolute_internal_link` | notice | Link interno scritto come URL assoluto anziché percorso relativo alla radice | interni |

I controlli sulla forma degli URL, come slash, maiuscole e codifica, riguardano solo i link **interni**: lo stile degli URL di un sito esterno non dipende da te. Redirect, errori HTTP, irraggiungibilità e trasporto non sicuro vengono controllati per tutti i link nell'ambito scelto. I percorsi del framework e gli asset statici configurati vengono esclusi per ridurre i risultati non utili; vedi `exclude_paths` ed `exclude_extensions` sotto.

Ogni link viene richiesto con **l'URL scritto nella pagina**, rimuovendo solo il `#fragment`, senza normalizzarlo prima. Un redirect del server come `/about/ → /about` viene così osservato e segnalato come `redirect_chain`. Ogni forma distinta viene ispezionata: `/page#ok` e `/page#missing`, oppure `/a//b` e `/a/b`, ricevono ciascuna una valutazione. Il *problema* di link non funzionante riunisce invece gli alias in un'unica identità. Le ispezioni vengono salvate per `(page, target, inspection)`: più ancore mancanti verso la stessa destinazione producono una riga `missing_fragment` con un esempio, non una riga per ancora.

### Regolare i controlli {#tuning-the-taxonomy}

La configurazione si trova in `seo-pro.broken_links.inspections`:

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

Per **disattivare una regola**, rimuovi la sua classe da `rules`. Per **disattivare tutte le ispezioni tipizzate**, imposta `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`. Due regole richiedono particolare attenzione:

- `trailing_slash` è **disattivata finché non dichiari una convenzione**, `'always'` oppure `'never'`. Se `/x` e `/x/` rispondono entrambi `200`, il controllo non sceglie uno stile al posto tuo. Un redirect tra le due forme viene già segnalato da `redirect_chain`.
- `absolute_internal_link` segnala **ogni** link interno assoluto. Se il sito adotta questa convenzione, può produrre molte righe `notice` prive di utilità per il progetto. Rimuovi la regola da `rules` per escluderle.

## Integrazione continua {#continuous-integration}

La scansione dei link e l'[audit SEO](/it/pro/scan-issues) possono **far fallire una build** e **scrivere un report** da conservare tra gli artefatti. `--fail-on-error` corrisponde al livello `critical`; `--fail-on-warning` fallisce con risultati `critical` **o** `warning`. Non esiste un livello separato chiamato “error”.

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` scrive l'artefatto e ricava il nome del file se passi una directory. `--format` accetta `json`, predefinito, `md` oppure `html`. Usa JSON per elaborare i risultati nella pipeline; HTML produce una pagina autonoma da allegare all'esecuzione.

### GitHub Actions {#github-actions}

Il crawler legge le pagine via HTTP: in CI deve poter raggiungere i contenuti. Usa un'app servita localmente, come sotto, oppure un URL di staging tramite `SEO_PRO_BROKEN_LINKS_BASE_URL`. Registra modelli o sitemap per fornire gli URL iniziali della scansione.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## Programmazione {#scheduling}

Registra la scansione e la manutenzione in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Riferimento dei comandi {#command-reference}

| Comando | Funzione |
| --- | --- |
| `seo-pro:broken-links-scan` | Accoda una scansione con limiti e ripresa (`--scope=internal_only\|internal_and_external`, `--url=*` per URL iniziali aggiuntivi) |
| `seo-pro:broken-links-status` | Riepilogo dell'ultima scansione, problemi aperti e conteggi delle ispezioni; **controllo CI** con `--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html` |
| `seo-pro:broken-links-cancel` | Annulla una scansione attiva o in coda; `{run?}` indica l'esecuzione, altrimenti usa l'ultima attiva |
| `seo-pro:broken-links-recover` | Segna come fallite le scansioni abbandonate da un worker, con lease scaduta |
| `seo-pro:broken-links-prune` | Applica la conservazione del crawler a vecchie esecuzioni e problemi risolti |

## Regolare i limiti {#tuning}

Pagine per esecuzione, link per pagina, limiti per job, tempo massimo e ritardi tra richieste allo stesso host si configurano in `seo-pro.broken_links`. I valori predefiniti sono finiti e prudenti. Prima di aumentarli, consulta la [tabella dei batch nella guida alla produzione](/it/pro/production).
