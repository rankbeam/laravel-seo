---
description: "Volg een echte Rankbeam Pro-scan: bekijk een ontbrekende beschrijving, sla de correctie op in Filament, scan opnieuw en download het gegenereerde PDF-voorbeeldrapport."
---

# Van scan naar gecontroleerde correctie {#from-a-scan-to-a-verified-fix}

Een scan vond een ontbrekende beschrijving bij een demoartikel. We voegden
de beschrijving toe in Filament, scanden opnieuw en genereerden een rapport
dat de correctie toont.

Dit zijn beelden van een draaiende lokale Merchant-demo op 9 september 2026.
De content bestaat uit vooraf ingevulde voorbeeldgegevens. Beide scans en het
rapport zijn voor deze rondleiding gegenereerd; er is geen historische trend
vooraf ingevuld. De app gebruikt Laravel 12 en Filament 4, met Core, de gratis
editor en de Pro-engine van Rankbeam. De screenshots en het voorbeeldrapport
tonen de Engelstalige demo; interfacelabels hieronder verwijzen naar die beelden.

**[Download het gegenereerde rapport (PDF, 98 KB, Engels)](/pro-walkthrough/merchant-demo-report.pdf)**

## De geregistreerde pagina's scannen {#scan-the-registered-pages}

Voer na het [installeren van Pro](/nl/pro/installation) en het registreren van
scandoelen het volgende uit:

```bash
php artisan seo-pro:scan --sync
```

De demo registreert 18 contentrecords en drie routes. Deze eerste scan voltooide
alle 21 doelen zonder fouten en vond 20 bevindingen: zes waarschuwingen en
14 informatieve meldingen.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="De eerste voltooide scan: 21 doelen, 20 bevindingen, zes waarschuwingen en 14 informatieve meldingen." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Screenshots zijn vastgelegd op 2× resolutie. Open een afbeelding om die op volledig formaat te bekijken.*

## Eén bevinding bekijken {#inspect-one-issue}

Open in **SEO Dashboard** naast de betreffende rij **Page issues**
(paginabevindingen). Bij “Behind the Scenes: Our Product Photography” vermeldt
de bevinding de ontbrekende `description`, de pagina-URL en de scan die dit detecteerde.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Het dialoogvenster Page issues toont Post 5, de URL en het ontbrekende beschrijvingsveld." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## De beschrijving opslaan {#save-the-description}

Open het artikel onder **Posts** (berichten), vul **SEO description**
(SEO-beschrijving) in en sla op. De [gratis Filament-editor](/nl/guide/filament)
toont de ingevoerde tekst in het zoekresultaatvoorbeeld en markeert de bron
als **Manual** (handmatig). In dit voorbeeld is de beschrijving 142 tekens lang;
de titel komt nog steeds uit het artikel.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="De opgeslagen SEO-beschrijving en de teller van 142 tekens." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="Het live voorbeeld gebruikt de ingevoerde beschrijving met het label Manual (handmatig)." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Een veld opslaan en de correctie controleren zijn afzonderlijke stappen.
De scanscore wordt na de volgende scan bijgewerkt. Zonder Filament sla je
dezelfde waarde op via de methode `saveSEO()` van je model.

## Opnieuw scannen en de verandering controleren {#rescan-and-check-what-changed}

Voer hetzelfde commando opnieuw uit:

```bash
php artisan seo-pro:scan --sync
```

Het dashboard markeert precies deze bevinding nu als **Fixed** (opgelost).
De andere 19 bevindingen blijven open.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Vastgelegde scanvergelijking: nul nieuwe bevindingen, nul regressies, één opgelost en 19 nog open." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Controle | Voor | Na |
|---|---|---|
| Voltooide doelen | 21 | 21 |
| Open bevindingen | 20 | 19 |
| Waarschuwingen | 6 | 5 |
| Informatieve meldingen | 14 | 14 |
| Gemiddelde technische SEO-score | 92 | 93 |

De [score](/nl/pro/scoring) weerspiegelt de technische controles van Rankbeam.
Ze meet geen verkeer, zoekpositie of opname in AI-antwoorden. Een geslaagde
beschrijvingscontrole garandeert ook niet dat een zoekmachine die beschrijving toont.

## Het rapport genereren {#generate-the-report}

Voor deze demonstratie genereerden we **vóór** het bewerken van het artikel
een nulmetingsrapport en na de nieuwe scan een tweede rapport:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

De tweede PDF toont **één opgeloste**, **nul nieuwe** en **19 open** bevindingen.
De trend bevat alleen de twee scans hierboven. Search Console en
AI-botregistratie stonden uit. Die onderdelen vermelden daarom dat er geen
gegevens beschikbaar zijn.

[![De eerste pagina van het gegenereerde voorbeeldrapport: score 93, één opgeloste en 19 open bevindingen.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

Het eerste rapport legt de uitgangssituatie voor de vergelijking vast.
Genereer je alleen één rapport nadat je een pagina hebt gecorrigeerd, dan kan
het geen verschil met een eerder rapport tonen. Gebruik `--no-store` voor
een voorbeeld dat die vergelijkingsbasis niet moet bijwerken.

Het voorbeeld gebruikt de Browsershot-renderer. Zie [rapporten in eigen huisstijl](/nl/pro/reports)
voor renderervereisten, huisstijl en ingeplande bezorging.

## Uitvoeren in je eigen app {#run-it-on-your-own-app}

Begin bij [Pro installeren](/nl/pro/installation) en scan daarna een pagina
waarvan je de uitvoer kunt controleren. Pro werkt ook [zonder Filament](/nl/pro/headless).
Wil je eerst de gratis metadatarenderer proberen, gebruik dan de [Docker-demo](/nl/guide/demo).
