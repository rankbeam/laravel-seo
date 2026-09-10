---
description: "Report PDF personalizzabili con punteggio, andamento dei problemi, correzioni e nuovi problemi, 404 recuperati, variazioni Search Console e attività dei bot AI. Generazione da comando e invio email programmabile."
---

# Report personalizzabili {#white-label-reports}

Un **report PDF con il tuo marchio** per un sito: punteggio complessivo, andamento dei problemi, elementi **risolti e nuovi rispetto al report precedente**, 404 e link recuperati, variazioni Search Console e attività dei bot AI. Lo generi con un comando e puoi **inviarlo per email con una cadenza programmata**. Per consegnarlo a un cliente, imposta logo, colore e dicitura “preparato per {cliente}”.

[Scarica un report di esempio in inglese (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf) oppure segui il [percorso scansione → correzione → report](/it/pro/walkthrough). L'esempio usa i contenuti dimostrativi di Merchant e due nuove scansioni: mostra un problema risolto, 19 ancora aperti e nessun dato Search Console.

[![Prima pagina del report dimostrativo Merchant, in inglese.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## Contenuto del report {#what-s-in-it}

- **Punteggio complessivo**: media degli ultimi punteggi delle pagine secondo i [criteri pubblicati](/it/pro/scoring), con fasce da A ≥ 90 a F, variazione dal report precedente e **andamento tra le scansioni recenti**. Ogni scansione salva il punteggio medio sull'esecuzione. Lo storico inizia dalla prima scansione dopo l'aggiornamento che introduce questa registrazione; le esecuzioni precedenti senza punteggio vengono saltate.
- **Problemi trovati per scansione**: andamento nelle ultime scansioni completate. Il conteggio aiuta a seguirne l'evoluzione; va letto insieme alla copertura della scansione.
- **Problemi risolti e nuovi**: quanti problemi hai chiuso e quanti ne sono comparsi dal report precedente. Usa lo storico del [ciclo di vita](/it/pro/scan-issues#issue-lifecycle), con chiusure e riaperture, quando copre un periodo completo; altrimenti confronta lo snapshot del report precedente.
- **Recuperi**: link non funzionanti risolti, 404 **recuperati** perché il percorso restituisce nuovamente 200, tramite [`seo-pro:404-recheck`](/it/pro/production#scheduler), e 404 **reindirizzati** dal report precedente, oltre a quelli ancora aperti. Un percorso tornato disponibile viene contato separatamente da un redirect.
- **Search Console**: query e pagine principali e maggiori variazioni dei clic rispetto al periodo di confronto. La sezione viene saltata quando GSC non è configurata.
- **Attività dei bot AI**: richieste attribuite ai crawler tramite user-agent, totali cumulativi e, quando i [conteggi giornalieri](/it/pro/ai-bot-monitor#period-metrics-daily-buckets) coprono l'intervallo, **richieste effettive nel periodo e URL distinti per bot**. Altrimenti usa la differenza dei contatori cumulativi tra snapshot. L'user-agent non verifica l'identità del richiedente.

## “Dal report precedente” {#since-the-last-report}

Il report confronta lo stato con **il report precedente**, non con una data arbitraria. Ogni generazione salva uno snapshot leggero in `seo_report_runs`: punteggio, identità dei problemi aperti, righe Search Console e contatori dei bot. Il report successivo confronta lo stato corrente con quello snapshot.

Questo è il metodo di ripiego per i dati senza uno storico proprio: i punteggi delle singole pagine conservano solo l'ultimo risultato. Lo snapshot permette di confrontarli nel tempo. Dove disponibile, il report preferisce lo **storico effettivo**: il [ciclo di vita dei problemi](/it/pro/scan-issues#issue-lifecycle) per correzioni e nuovi problemi, le [metriche giornaliere Search Console](/it/pro/search-console#historical-metrics) e i [conteggi giornalieri dei bot AI](/it/pro/ai-bot-monitor#period-metrics-daily-buckets) per richieste e URL distinti. Ogni componente torna al confronto degli snapshot se lo storico non copre l'intervallo, anche nel primo report dopo un aggiornamento.

Ne derivano due comportamenti:

- **Il primo report stabilisce il riferimento.** Mostra lo stato corrente. Correzioni, nuovi problemi e variazioni rispetto al precedente diventano disponibili dal **secondo** report.
- **La cadenza dipende da te.** Con report mensili confronti mesi; con report settimanali confronti settimane. Usa `--no-store` per un'anteprima occasionale che non sposti il riferimento.

## Generare un report {#generate-a-report}

```bash
php artisan seo-pro:report
```

Senza opzioni, il comando scrive un PDF in `storage/app/seo-reports/`. Puoi scegliere la destinazione o inviarlo per email:

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### Opzioni {#options}

| Opzione | Effetto |
| --- | --- |
| `--client=` | Sostituisce il nome del cliente nella dicitura “preparato per” |
| `--agency=` | Sostituisce il nome dell'agenzia |
| `--accent=` | Sostituisce il colore di accento, in formato esadecimale come `#3D5AFE` |
| `--logo=` | Sostituisce il percorso dell'immagine del logo |
| `--email=` | Indirizzo del destinatario, ripetibile; invia il report |
| `--send` | Invia ai destinatari configurati |
| `--output=` | Scrive il PDF nel file o nella directory indicati |
| `--no-store` | Non salva lo snapshot e non aggiorna il riferimento dei confronti |
| `--json` | Produce un riepilogo leggibile da software |

## Programmare l'email {#schedule-the-e-mail}

Il pacchetto non programma autonomamente gli invii. Imposta la cadenza in `routes/console.php` oppure `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Definisci i destinatari predefiniti nella configurazione o in `.env`:

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` usa questi destinatari; le opzioni esplicite `--email` li sostituiscono.

## Marchio e aspetto {#branding}

Questi valori non sono segreti e si trovano nella configurazione. Impostali una volta per applicarli a ogni report; le opzioni del comando consentono di cambiarli per una singola generazione.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Note:

- **Logo**: percorso assoluto a un file `PNG`, `JPG`, `GIF`, `WEBP` o `SVG`. Il file viene letto per incorporarlo nel PDF come data URI; il renderer non deve recuperarlo dalla rete. `PNG` o `JPG` sono le scelte più compatibili.
- **Colore di accento**: viene validato come colore esadecimale. Un valore non valido ripristina il predefinito e non viene inserito come CSS arbitrario.
- **Nome dell'agenzia**: usa per impostazione predefinita `config('app.name')`.

Il blocco completo è `reports` in `config/seo-pro.php`: include `paper`, predefinito `a4`, `include_gsc` e il numero di scansioni, righe GSC e bot da mostrare.

## Un sito per installazione {#one-site-per-install}

Pro scansiona l'applicazione in cui è installato: il report descrive **quell'installazione**. Un'agenzia con più siti genera un report da ogni installazione e usa `--client` e le opzioni grafiche per identificarlo. Non esiste un modello multisito o multi-tenant.

## Come viene generato {#how-it-s-built}

Il renderer predefinito è **dompdf**, in PHP, senza Node o Chromium. Un cron o worker può quindi generare il PDF senza browser di sistema, anche senza Filament. Il recupero remoto è disabilitato; il logo è incorporato, così i campi renderizzati non possono richiedere immagini dalla rete.

### Report in alfabeti diversi con Browsershot {#reports-in-every-script-browsershot-renderer}

Dal core 3.20 e Pro 2.40, i renderer Chrome disabilitano JavaScript e bloccano le richieste di asset HTTP(S), FTP e WebSocket. I template pubblicati devono usare HTML e CSS statici con asset incorporati. Questi controlli riguardano gli asset della pagina: host e sandbox di Chrome richiedono comunque una configurazione corretta. Il renderer PDF registra un avviso con indicazioni di installazione quando Fontconfig rileva un alfabeto senza font, anche se compare solo in parte del testo. Chrome può produrre comunque il PDF: controlla il risultato prima di inviarlo.

dompdf usa il font incorporato, DejaVu Sans, che copre latino, cirillico e greco. Per giapponese, thailandese o arabo il template predefinito non offre una copertura adeguata. Da Pro 2.34 puoi usare **Chrome headless** tramite `spatie/browsershot`, la stessa dipendenza usata dal core per le immagini OG:

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chrome usa i font installati sul server. Il template adotta lo stack per alfabeto del core: `Noto Sans`, famiglia `Noto Sans CJK` della lingua della pagina per prima, font thailandesi, arabi, ebraici, devanagari, emoji a colori e DejaVu Sans come riferimento latino. Su Debian e Ubuntu, `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji` installa le famiglie principali, come per le [immagini OG](/it/guide/multilingual#og-images-in-every-script). Gli avvisi di `seo:og-images` sui font mancanti sono utili anche per configurare i report. Template Blade, dati e snapshot restano gli stessi; cambia il motore di rendering. `ReportGenerator::renderer()` indica quello in uso.

### Date e numeri nella locale del lettore {#dates-and-numbers-in-the-reader-s-locale}

Il report acquisisce `seo-pro.reports.locale` alla generazione. Se è null, usa la locale dell'app. La lingua risolta controlla etichette PDF ed email, oggetto predefinito, font e attributo HTML `lang`. Una locale regionale senza traduzione propria ricade sulla lingua base inclusa, poi sull'inglese. Cinese semplificato `zh_CN` e tradizionale `zh_TW` rimangono distinti.

Con `ext-intl`, ICU formatta date e numeri secondo la locale richiesta. `seo-pro.reports.format_locale` permette di scegliere intenzionalmente una convenzione regionale diversa: `locale=it` e `format_locale=en_US` producono etichette italiane con date e numeri statunitensi. Senza `ext-intl` rimangono date inglesi e numeri raggruppati con virgole.

Le email in coda conservano lingua, formattazione e oggetto acquisiti, anche se cambia la configurazione del worker. Scegli la lingua **prima** di generare il PDF: cambiare in seguito la locale del mailable non traduce l'allegato. I payload precedenti a Pro 2.39 usano la configurazione del worker perché non contengono queste impostazioni. Oggetti personalizzati, marchi e messaggi dei problemi già salvati restano dati di origine.

La visualizzazione CLI è separata: `php artisan seo-pro:report --display-locale=it` traduce il riepilogo del comando; la configurazione del report sceglie la lingua del PDF e dell'email. La CLI usa l'inglese per impostazione predefinita, modificabile con `SEO_PRO_CLI_LOCALE`. Chiavi e codici JSON restano stabili, mentre le etichette leggibili possono essere tradotte. Pubblica `seo-pro-lang` per modificare i messaggi in `lang/vendor/seo-pro/{locale}/seo-pro.php`.

Da codice, risolvi `ReportGenerator` dal container:

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```
