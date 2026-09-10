---
description: "Passa da fibonoir/laravel-seo v1 a rankbeam/laravel-seo v2: nuovo nome del pacchetto e core dedicato a risoluzione dei metadati, rendering, JSON-LD e sitemap."
---

# Aggiornamento da fibonoir/laravel-seo v1 {#upgrading-from-fibonoir-laravel-seo-v1}

La versione 2.0.0 rinomina il pacchetto in `rankbeam/laravel-seo` e concentra il core sulla risoluzione dei metadati, il rendering, il JSON-LD e le sitemap. Analisi, scansioni, redirect, monitoraggio 404 e interfaccia di amministrazione passano a pacchetti separati.

## 1. Sostituire il pacchetto {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Aggiornare i namespace {#_2-update-namespaces}

I nomi delle classi restano invariati; cambia soltanto il namespace radice: `Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`. Puoi aggiornarlo con una ricerca e sostituzione nel progetto. L’alias della facade `SEO` e le direttive Blade `@seo` non cambiano.

## 3. Rimuovere i file pubblicati obsoleti {#_3-delete-stale-published-files}

Prima di rimuovere file o tabelle, salva una copia della configurazione pubblicata ed esporta i dati coinvolti. Verifica di poterli ripristinare. Questa guida non migra redirect, log 404 o cronologia delle scansioni della v1 nel diverso schema di Pro. La compatibilità delle tabelle del core descritta sotto riguarda soltanto `seo_meta` e `seo_defaults`.

::: warning I conflitti possono non produrre errori
Il comando `seo:install` della v1 pubblicava file nell’applicazione che possono entrare in conflitto con il pacchetto v2 senza generare messaggi di errore.
:::

- **`config/seo.php`**: se proviene dalla v1 o da `ralphjsmit/laravel-seo`, che l’installer della v1 poteva lasciare presente, prevale sulla configurazione del pacchetto e può annullare `site_name` e i template `{site_name}`. Rimuovilo e pubblica la configurazione con `php artisan vendor:publish --tag=seo-config`.
- **Migrazioni della v1** per tabelle che il core non gestisce più: `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache` e `seo_internal_links_index`. Rimuovi i file di migrazione. Se le tabelle esistono in produzione, eliminale **prima** di installare `rankbeam/laravel-seo-pro`, che le ricrea con uno schema diverso, dopo aver salvato e verificato gli export descritti sopra.
- **Stub pubblicati** in `app/` e `resources/js` dai percorsi Filament 3, Livewire, Vue e React della v1: fanno riferimento a classi che non esistono più.

Le due tabelle del core, `seo_meta` e `seo_defaults`, hanno uno schema compatibile: i loro dati vengono conservati durante l’aggiornamento.

## 4. Funzioni rimosse e nuova collocazione {#_4-removed-features-and-where-they-went}

| Funzione della v1 | Dove si trova ora |
|---|---|
| Sezione SEO nei form Filament | [`rankbeam/laravel-seo-filament`](/it/guide/filament), gratuito con licenza MIT |
| Analizzatore dei contenuti con 32 regole | Non trasferito: le regole su densità delle parole chiave e parole persuasive non fanno parte di questa migrazione. Il rilevamento dei problemi SEO tecnici è nello scanner di `rankbeam/laravel-seo-pro`; il punteggio SEO numerico è una funzione Pro ricavata dai problemi rilevati. |
| Scanner dell’intero sito | `rankbeam/laravel-seo-pro`, con pipeline in coda e dashboard |
| Gestore dei redirect | `rankbeam/laravel-seo-pro`, con validazione delle regex e protezioni dagli open redirect |
| Monitoraggio 404 | `rankbeam/laravel-seo-pro`, senza memorizzazione degli IP per impostazione predefinita |
| Analisi GA4 e link interni | Backlog di `rankbeam/laravel-seo-pro` |
| Installer `seo:install` | Rimosso: installa con Composer, pubblica la configurazione ed esegui le migrazioni |

## 5. Cambiamenti di comportamento da verificare {#_5-behavior-changes-to-review}

- **`og:image` e `twitter:image` sono sempre URL assoluti.** La v1 emetteva senza conversione i percorsi relativi impostati manualmente.
- **I canonical derivati non includono la query string.** Quelli espliciti vengono conservati così come sono.
- **Il rilevamento automatico delle sitemap dà precedenza alle sorgenti registrate**: non viene più generato un `sitemap-post.xml` duplicato accanto al `sitemap-posts.xml` registrato.
- **Il JSON-LD usa l’escaping `JSON_HEX_*`.** Se elabori l’output grezzo degli script, tieni conto dell’escaping di caratteri come `<`.

## 6. Problemi noti {#_6-known-gotchas}

- Il `DatabaseSeeder` predefinito di Laravel usa `WithoutModelEvents`, che disabilita l’hook di creazione automatica di `HasSEO` nei seeder.
- Se un template del titolo nei valori predefiniti della rotta contiene già il marchio, terminalo con il `title_suffix` configurato: il resolver evita così di aggiungerlo una seconda volta.
