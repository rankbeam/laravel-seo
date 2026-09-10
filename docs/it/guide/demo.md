---
description: "Avvia la demo Rankbeam con dati di esempio e pacchetti rilasciati, senza repository path, per vedere metadati, grafo JSON-LD e sitemap su pagine reali."
---

# Avviare la demo {#run-the-demo}

La demo permette di vedere Rankbeam su pagine reali prima di integrarlo nella tua applicazione. È un’app Laravel con dati di esempio che installa i pacchetti **rilasciati**, senza repository path né checkout affiancati. Genera alcune pagine con metadati SEO completi, un grafo JSON-LD e una sitemap. Con una licenza esegue anche l’[audit SEO tecnico (EN)](/it/pro/scan-issues) di Pro.

## Un comando per il core gratuito {#one-command-free-core}

La demo è disponibile come immagine Docker nel repository [`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples):

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

Apri `http://localhost:8080`. Visualizza il sorgente di una pagina per controllare il `<head>` risolto e visita `/sitemap.xml` per la sitemap generata. Questa configurazione usa soltanto il core gratuito con licenza MIT, installato da Packagist.

## Con Pro: l’audit {#with-pro-the-audit}

Pro richiede una licenza per progetto e viene installato dal suo repository Composer privato. Passa la licenza tramite `COMPOSER_AUTH`, come segreto di build che non viene scritto nei layer dell’immagine, e avvia la build con l’opzione Pro:

```bash
export COMPOSER_AUTH='{"http-basic":{"laravel-seo-pro.composer.sh":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

All’avvio la demo esegue [`seo:doctor` (EN)](/pro/headless#setup-health-check) e una prima `seo-pro:scan` sulle pagine di esempio. Il report diagnostico, il riepilogo della scansione e il [punteggio da 0 a 100 (EN)](/it/pro/scoring) compaiono nei log di Compose.

## Vedere il flusso di lavoro di Pro {#see-the-pro-workflow}

La guida [scansione → correzione → report (EN)](/it/pro/walkthrough) mostra una demo Merchant in esecuzione: una scansione reale, i dettagli dei problemi, una descrizione salvata in Filament, una nuova scansione e un PDF scaricabile. I contenuti sono identificati come dati di esempio; il confronto prima/dopo deriva da due scansioni effettive.

Non è ancora disponibile una demo interattiva pubblica ospitata online. Usa Docker per eseguire il motore in locale; il [README della demo](https://github.com/rankbeam/rankbeam-examples/tree/main/demo) spiega la configurazione e il passaggio tra pacchetti rilasciati e locali.

::: tip Hai già un’app?
Passa direttamente alla [guida rapida](/it/guide/quickstart): dall’installazione all’head completo, in circa cinque minuti.
:::
