---
description: "Pianifica scansioni SEO complete e controlla cosa cambia: problemi nuovi, ricomparsi o risolti, ordinati per impatto nella dashboard e in un’email facoltativa."
---

# Scansioni pianificate e confronto delle differenze {#scan-scheduling-delta}

Esegui una scansione SEO completa **a intervalli programmati** e controlla **cosa è cambiato dall’ultima scansione**: problemi nuovi, ricomparsi o risolti, ordinati per impatto nella dashboard e, se lo desideri, in un’email di riepilogo.

Le due funzioni lavorano insieme: il confronto delle differenze rende leggibile il risultato di una scansione ricorrente.

## Cosa è cambiato dall’ultima scansione {#what-changed-since-the-last-scan}

Ogni scansione completata salva l’**insieme dei problemi aperti** in uno snapshot leggero, `seo_scan_run_issues`. Il confronto tra due snapshot distingue tre categorie:

- **Nuovi:** problemi ora aperti che non lo erano prima e non erano mai comparsi in nessuna scansione precedente.
- **Ricomparsi:** problemi risolti che **si sono ripresentati**. Non indica un aumento della gravità, che è fissa per tipo di problema: corrisponde alla riapertura descritta nel [ciclo di vita](/it/pro/scan-issues#issue-lifecycle).
- **Risolti:** problemi aperti nella scansione precedente e ora assenti.

Ogni categoria è [ordinata per impatto](#impact-ordering).

### Perché usare uno snapshot invece della tabella dei problemi {#why-a-snapshot-not-the-issues-table}

Il [ciclo di vita](/it/pro/scan-issues#issue-lifecycle) aggiorna la stessa riga quando un problema viene risolto o riaperto. Finché rimane aperto, `scan_run_id` viene sostituito con l’ultima scansione. Questo conserva la storia del problema, ma la tabella corrente non può dire *quali problemi fossero aperti alla fine dell’esecuzione N*: un problema persistente punta sempre alla più recente.

Ogni esecuzione salva quindi il proprio insieme aperto usando un’**impronta stabile del problema**, `issue_type | target | field`, la stessa identità usata dai [report personalizzabili](/it/pro/reports). Il confronto opera su due insiemi di impronte congelati e funziona tra **qualsiasi** coppia di esecuzioni, anche non consecutive.

### Casi particolari {#edge-cases-handled-honestly}

- **Una pagina esce dall’insieme delle destinazioni.** I suoi problemi aperti non vengono più controllati, quindi restano aperti e vengono inclusi negli snapshot successivi. Compaiono come **ancora aperti**, senza essere dichiarati risolti solo perché la pagina non viene più analizzata.
- **Un controllo viene disattivato tra due scansioni.** I suoi problemi non vengono più emessi, il ciclo di vita li segna come risolti e li rimuove dall’insieme aperto. Compaiono quindi come **risolti** secondo i controlli attualmente attivi. A livello del singolo problema non si distingue una correzione dalla disattivazione del controllo.
- **Prima scansione dopo l’aggiornamento.** Le esecuzioni precedenti alla funzione non hanno snapshot e non vengono usate come riferimento. La prima scansione con snapshot stabilisce la **base di confronto**, mostrando lo stato attuale senza etichettare tutto come nuovo. Il confronto è disponibile dalla seconda.

### Nella dashboard {#on-the-dashboard}

Il widget **Cosa è cambiato dall’ultima scansione** della [dashboard SEO](/it/pro/installation) confronta le due esecuzioni completate più recenti. Mostra conteggi e problemi principali delle categorie nuovi, ricomparsi e risolti, ordinati per impatto. Finché non esistono due snapshot, indica che è stata stabilita la base di confronto.

## Ordinamento per impatto {#impact-ordering}

Ogni categoria viene ordinata tramite un punteggio di **impatto**:

```
impact = severity_weight × page_importance
```

- **severity_weight** riusa i [criteri pubblici del punteggio](/it/pro/scoring): `40` per un problema critico, `15` per un avviso e `5` per una segnalazione informativa. L’ordinamento usa così la stessa valutazione di gravità del prodotto.
- **page_importance** tiene conto della **domanda di ricerca osservata**, cioè delle impressioni della pagina in [Search Console](/it/pro/search-console):

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  Le impressioni sono trasformate su scala logaritmica e normalizzate rispetto alla pagina con più traffico: una pagina con dieci volte il traffico non riceve dieci volte l’importanza. La formula rimane confrontabile tra un piccolo blog e un grande catalogo. `<priority>` della sitemap è soltanto un **segnale secondario debole**, assente per impostazione predefinita e spesso uniforme quando impostato. Incide se hai configurato priorità per tipo in `seo.sitemap.models`.

**Senza Search Console**, se mancano sia una cronologia GSC sincronizzata sia priorità configurate, `page_importance` vale `1` per tutte le pagine: l’impatto coincide con l’**ordine di gravità**. Sincronizza la [cronologia GSC](/it/pro/search-console#historical-metrics) con `seo-pro:gsc-sync` per aggiungere il peso della domanda osservata.

Modifica pesi e finestra in `seo-pro.scan.delta.impact`.

## Pianificare una scansione {#scheduling-a-scan}

Il pacchetto **non pianifica nulla per impostazione predefinita**. Per attivare la pianificazione:

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

Oppure usa un’espressione cron completa, che prevale su `frequency`:

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

Il pacchetto registra `seo-pro:scan` nello scheduler Laravel con `withoutOverlapping`, evitando di sovrapporre l’esecuzione del comando al ciclo successivo. La registrazione avviene solo nel contesto scheduler/console e non aggiunge lavoro alle richieste web.

::: warning Serve uno scheduler in esecuzione
La pianificazione del pacchetto resta inattiva se lo scheduler Laravel non gira. Usa il cron standard `* * * * * php artisan schedule:run` oppure `php artisan schedule:work` in sviluppo. Vedi [Configurazione in produzione](/it/pro/production#scheduler).
:::

Se preferisci configurarlo direttamente, lascia `schedule.enabled` disattivato e pianifica il comando nel kernel console dell’applicazione. Confronto e riepilogo continuano a funzionare:

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` esegue la scansione nel processo corrente anziché accodare un job per destinazione. Può andare bene per un sito piccolo senza worker; in produzione usa le code.

## Email di riepilogo {#summary-e-mail}

Puoi ricevere un’email con **le differenze dall’ultima scansione** al termine di una scansione pianificata: un riepilogo HTML con il tuo marchio e problemi nuovi, ricomparsi e risolti ordinati per impatto.

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

La funzione riusa marchio e configurazione email dei [report personalizzabili](/it/pro/reports): nome dell’agenzia, logo e colore. Se non specifichi destinatari diversi, usa quelli dei report. `only_on_change` evita l’invio quando non ci sono cambiamenti; la prima scansione che stabilisce il riferimento viene sempre inviata.

Il riepilogo parte solo per un’esecuzione avviata con `--notify`, aggiunto automaticamente dallo scheduler quando `notify.enabled` è attivo. Un normale `seo-pro:scan` senza quella opzione non invia email.

::: tip Un altro canale?
Per Slack, webhook o riepiloghi personalizzati, ascolta `Rankbeam\Seo\Pro\Events\SeoScanCompleted`. L’evento viene emesso una volta per esecuzione conclusa e contiene il run. Puoi costruire il confronto con `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` e inviarlo al canale scelto.
:::

## Conservazione {#retention}

Gli snapshot vengono eliminati a cascata insieme alla loro esecuzione. [`seo-pro:scan-prune`](/it/pro/production#scheduler) applica quindi automaticamente la conservazione, senza nuovi comandi da pianificare. Un’esecuzione viene eliminata solo quando non contiene problemi aperti; lo snapshot di una recente esecuzione rimane disponibile per il confronto.

Per disattivare completamente gli snapshot, il confronto e il riepilogo, imposta `seo-pro.scan.delta.snapshot => false`.
