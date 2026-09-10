---
description: "Segui una scansione reale di Rankbeam Pro: esamina una descrizione mancante, salvala in Filament, ripeti la scansione e scarica il PDF di esempio generato."
---

# Dalla scansione a una correzione verificata {#from-a-scan-to-a-verified-fix}

Una scansione ha rilevato una descrizione mancante in un articolo della demo. L’abbiamo aggiunta in Filament, abbiamo ripetuto la scansione e generato un report che mostra la correzione.

Le catture originali, con interfaccia in inglese, provengono da una demo Merchant locale eseguita il 9 settembre 2026. I contenuti sono dati di esempio inseriti con un seeder; entrambe le scansioni e il report sono stati generati per questa dimostrazione, senza precompilare andamenti storici. L’app usa Laravel 12 e Filament 4, con core, editor gratuito e motore Pro di Rankbeam.

**[Scarica il report generato, in inglese: PDF, 98 KB](/pro-walkthrough/merchant-demo-report.pdf)**

## Analizzare le pagine registrate {#scan-the-registered-pages}

Dopo aver [installato Pro](/it/pro/installation) e registrato le destinazioni, esegui:

```bash
php artisan seo-pro:scan --sync
```

La demo registra 18 record di contenuto e tre rotte. La prima scansione ha completato tutte le 21 destinazioni senza errori di esecuzione, rilevando 20 problemi: sei avvisi e 14 segnalazioni informative.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="Prima scansione completata: 21 destinazioni, 20 problemi, sei avvisi e 14 segnalazioni informative." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Le schermate sono acquisite a risoluzione 2×. Aprile per esaminarle a grandezza originale.*

## Esaminare un problema {#inspect-one-issue}

In **SEO Dashboard**, apri **Page issues** accanto alla riga interessata. Per “Behind the Scenes: Our Product Photography”, il risultato identifica il campo `description` mancante, l’URL della pagina e la scansione che lo ha rilevato.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Il dialogo Page issues identifica il Post 5, il suo URL e il campo description mancante." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Salvare la descrizione {#save-the-description}

Apri l’articolo in **Posts**, compila **SEO description** e salva. L’[editor Filament gratuito](/it/guide/filament) mostra il testo inserito nell’anteprima di ricerca e ne identifica la sorgente come **Manual**. In questo esempio la descrizione ha 142 caratteri; il titolo continua a provenire dall’articolo.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="Descrizione SEO salvata e contatore di 142 caratteri." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="L’anteprima usa la descrizione inserita, identificata come Manual." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Salvare il campo e verificare la correzione sono due passaggi distinti. Il punteggio si aggiorna dopo la scansione successiva. Senza Filament, salva lo stesso valore tramite `saveSEO()` sul modello.

## Ripetere la scansione e controllare le differenze {#rescan-and-check-what-changed}

Esegui di nuovo lo stesso comando:

```bash
php artisan seo-pro:scan --sync
```

La dashboard identifica ora quel problema come **Fixed**. Gli altri 19 rimangono aperti.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Confronto registrato: zero problemi nuovi, zero regressioni, uno risolto e 19 ancora aperti." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Verifica | Prima | Dopo |
|---|---|---|
| Destinazioni completate | 21 | 21 |
| Problemi aperti | 20 | 19 |
| Avvisi | 6 | 5 |
| Segnalazioni informative | 14 | 14 |
| Punteggio SEO tecnico medio | 92 | 93 |

Il [punteggio](/it/pro/scoring) riflette i controlli tecnici di Rankbeam. Non misura traffico, posizione nei risultati o presenza nelle risposte AI. Superare il controllo sulla descrizione non garantisce che un motore di ricerca mostri proprio quel testo.

## Generare il report {#generate-the-report}

Per questa dimostrazione abbiamo generato un report di riferimento **prima** di modificare l’articolo e un secondo report dopo la nuova scansione:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

Il secondo PDF mostra **un problema risolto**, **zero nuovi** e **19 aperti**. L’andamento contiene soltanto le due scansioni descritte sopra. Search Console e registrazione dei bot AI erano disattivati, quindi le relative sezioni indicano che i dati non sono disponibili.

[![Prima pagina del report di esempio: punteggio 93, un problema risolto e 19 aperti.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

Il primo report stabilisce il riferimento per il confronto. Se generi un solo report dopo aver corretto una pagina, non può mostrare differenze rispetto a un report precedente. Usa `--no-store` per un’anteprima che non deve aggiornare quel riferimento.

L’esempio usa il renderer Browsershot. La guida ai [report personalizzabili](/it/pro/reports) descrive requisiti dei renderer, marchio e invio pianificato.

## Provarlo nella tua applicazione {#run-it-on-your-own-app}

Parti da [Installazione di Pro](/it/pro/installation), poi analizza una pagina di cui puoi controllare l’output. Pro funziona anche [senza Filament](/it/pro/headless). Per provare prima il renderer gratuito dei metadati, usa la [demo Docker](/it/guide/demo).
