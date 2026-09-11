---
description: "Installa laravel-seo-pro per aggiungere scansioni in coda, problemi tracciati, gestione redirect e monitor 404 al core. Funziona su Laravel 11–13; Filament è facoltativo."
---

# Installazione di Pro {#installing-pro}

`rankbeam/laravel-seo-pro` aggiunge al core scansioni del sito in coda con tracciamento dei problemi, gestione redirect e monitoraggio 404. Il motore funziona su **qualsiasi applicazione Laravel 11–13**, con Blade, Inertia o una sola API. Filament è un’interfaccia facoltativa: se lo installi, dashboard SEO, redirect e monitor 404 diventano pagine del pannello; altrimenti gestisci le funzioni tramite [comandi Artisan](/it/pro/headless).

## Requisiti {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13) |
| Laravel | 11, 12 o 13 |
| `rankbeam/laravel-seo` | ^3.20, installato automaticamente da Pro 2.40+ |
| `filament/filament` | **Facoltativo**, 4.x o 5.x, solo per l’interfaccia amministrativa |
| `rankbeam/laravel-seo-filament` | **Facoltativo**, ^1.11 se usi l’editor SEO con Pro 2.36+ |

Parti da un’app Laravel esistente con database configurato. Completa prima la [guida rapida del core](/it/guide/quickstart), in modo che le sue tabelle esistano e un modello generi metadati. La licenza Pro fornisce le credenziali Composer descritte sotto.

Per vedere il risultato, consulta [scansione → correzione → report](/it/pro/walkthrough).

## Installare il pacchetto {#install-the-package}

Pro viene distribuito tramite un repository Composer privato associato alla licenza. Aggiungi il repository una volta e richiedi il pacchetto: Composer chiederà l’email della licenza come nome utente e la chiave come password.

Lemon Squeezy gestisce il pagamento come merchant of record. Dopo il pagamento, la pagina privata della ricevuta mostra la chiave di download e le istruzioni Composer. Usa come nome utente l’email dell’acquisto. Il repository è ospitato da Rankbeam; non serve un account Anystack. Mantieni privati il link della ricevuta e `auth.json`. Un rimborso totale revoca i download e gli aggiornamenti futuri, senza interrompere un’applicazione già installata.

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details Autenticazione Composer non interattiva
Per CI o ambienti non interattivi, configura prima le credenziali:

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

Esegui poi l’installer:

```bash
php artisan seo-pro:install
```

L’installer pubblica `config/seo-pro.php` e le migrazioni Pro, esegue `migrate` e mostra i passaggi successivi. Nel database dell’applicazione dovrebbero ora essere presenti le tabelle del core e di Pro.

::: details Installazione manuale e opzioni dell’installer
Le migrazioni Pro vengono pubblicate nell’applicazione; il pacchetto non le carica automaticamente. I passaggi manuali equivalenti sono:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Puoi ripetere l’installer. `--no-migrate` pubblica i file senza eseguire le migrazioni. Usa `--force` solo quando vuoi sovrascrivere i file pubblicati, configurazione compresa.
:::

## Registrare le destinazioni di scansione {#register-scan-targets}

In un service provider indica cosa analizzare: classi di modello, rotte con nome oppure tutte le sorgenti del [registro sitemap](/it/guide/sitemaps).

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

Sostituisci `Post` con un tuo modello che usa `HasSEO`. Serve almeno un record per vedere un risultato di scansione del modello. Le destinazioni di rotta devono indicare rotte esistenti; ometti quella registrazione se vuoi analizzare solo modelli.

## Verificare l’installazione {#verify-your-install}

Esegui il controllo della configurazione:

```bash
php artisan seo:doctor
```

Conferma la presenza delle tabelle del core e di Pro, l’URL corretto dell’applicazione e le destinazioni registrate. Segui le correzioni suggerite. Un avviso sulla coda `sync` è previsto durante la prova dei comandi sincroni sotto; configura un worker prima di pianificare scansioni in produzione.

::: details Esempio di output diagnostico
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor` controlla configurazione e cronologia recente senza chiamate di rete né stampa di segreti. Non può dimostrare che un cron esterno o un worker sia in esecuzione. Gli errori critici producono un codice di uscita diverso da zero; gli avvisi no. Usa `--json` per il risultato leggibile dalle macchine.
:::

## Eseguire la prima scansione {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

Il primo comando completa la scansione nello stesso processo, senza bisogno di un worker. Il secondo mostra l’ultima esecuzione e i suoi risultati. Verifica che sia completata e che abbia elaborato le destinazioni registrate; risolvi le destinazioni fallite prima di considerarla conclusa.

Correggi un campo segnalato, salvalo ed esegui di nuovo la scansione. La [dimostrazione](/it/pro/walkthrough) mostra questo percorso con una descrizione mancante e un report della modifica. Il [punteggio tecnico](/it/pro/scoring) è un risultato diagnostico, non una previsione di posizionamento.

## Uso senza pannello {#path-b-headless}

Il motore è già utilizzabile senza interfaccia amministrativa. I [comandi Artisan](/it/pro/headless) consentono di eseguire scansioni, esaminare problemi, creare redirect e generare report. I middleware per redirect e 404 vengono registrati automaticamente per impostazione predefinita; le opzioni si trovano in `config/seo-pro.php`.

Per il lavoro pianificato, segui [Configurazione in produzione](/it/pro/production): code, worker, scheduler e conservazione dei dati.

## Aggiungere un pannello Filament, facoltativo {#path-a-with-a-filament-panel}

In un pannello Filament 4 o 5 esistente, registra il plugin Pro mostrato sotto. Se l’app non ha ancora un pannello, installa prima i pacchetti dell’interfaccia e creane uno:

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

Il plugin aggiunge la **dashboard SEO**, con scansione globale, avanzamento dal vivo, elenco dei problemi e nuova scansione con un clic, il **gestore dei redirect** e il **monitor 404** con l’azione *Crea redirect*. `rankbeam/laravel-seo-filament` aggiunge anche la [sezione dei campi SEO](/it/guide/filament) ai form delle risorse.

## Risoluzione dei problemi {#troubleshooting}

| Risultato | Passaggio successivo |
|---|---|
| Composer rifiuta le credenziali | Controlla email e chiave della licenza per `blog.rankbeam.dev`. Non inserire le credenziali nel controllo versione. |
| Doctor segnala tabelle mancanti | Completa la guida rapida del core, poi esegui `seo-pro:install` e `migrate` sullo stesso database dell’applicazione. |
| La scansione non elabora destinazioni | Controlla la registrazione nel provider e la presenza di record nel modello. |
| La scansione resta in attesa nella coda | Avvia il worker configurato oppure usa `--sync` per una verifica nello stesso processo. |
| Una destinazione fallisce | Controlla dettagli dell’esecuzione, nomi delle rotte e URL dell’applicazione prima di riprovare. |
| La dashboard non compare | Registra `SeoProPlugin` sul pannello effettivamente usato e controlla i gate di accesso. |

Per recupero dei worker e gestione continuativa, consulta [Configurazione in produzione](/it/pro/production).

## Licenza e rimborsi {#license}

La licenza fondatori costa 179 € una tantum e copre fino a cinque progetti in produzione, anche per clienti, con aggiornamenti a vita. Le copie di sviluppo e staging di quei progetti non si contano separatamente. Sono inclusi l’aiuto per installazione e migrazione, una chiamata di installazione di 60 minuti e il kit di lancio descritto nell’offerta. Puoi chiedere un rimborso totale incondizionato entro 30 giorni tramite la ricevuta o scrivendo a valentinogoxhaj@gmail.com. Dopo il rimborso totale devi smettere di usare Pro. Puoi modificarlo per i progetti coperti dalla licenza, ma non pubblicarne il sorgente né rivenderlo come pacchetto o starter kit. Il pacchetto contiene i termini completi della licenza.
