---
description: "Start de Rankbeam-demo met voorbeeldgegevens via één commando. De app gebruikt uitgebrachte pakketten, zonder path-repositories, en toont metadata, een JSON-LD-schemagraaf en een sitemap op echte pagina's."
---

# De demo starten {#run-the-demo}

De snelste manier om Rankbeam op echte pagina's te zien, zonder het eerst in je
eigen applicatie te integreren, is de uitvoerbare demo. Dit is een Laravel-app
met voorbeeldgegevens die de **uitgebrachte** pakketten installeert, zonder
path-repositories of naastliggende checkouts. Ze rendert enkele pagina's met
volledige SEO-metadata, een JSON-LD-schemagraaf en een sitemap. Voeg een licentie
toe om ook de [technische SEO-audit](/nl/pro/scan-issues) van Pro uit te voeren.

## Eén commando (gratis Core) {#one-command-free-core}

De demo is beschikbaar als Docker-image in de repository
[`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples):

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

Open `http://localhost:8080`. Bekijk de broncode van een pagina om de uiteindelijke
`<head>` te zien; ga naar `/sitemap.xml` voor de gegenereerde sitemap.
Dit gebruikt uitsluitend de gratis Core onder de MIT-licentie, geïnstalleerd via Packagist.

## Met Pro (de audit) {#with-pro-the-audit}

Pro heeft een licentie per project en wordt geïnstalleerd vanuit zijn private
Composer-repository. Geef je licentie mee via `COMPOSER_AUTH`, als buildgeheim dat
nooit in een imagelaag wordt geschreven, en bouw met de Pro-optie:

```bash
export COMPOSER_AUTH='{"http-basic":{"blog.rankbeam.dev":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

Bij het opstarten voert de demo [`seo:doctor`](/nl/pro/headless#setup-health-check)
uit, gevolgd door een eerste `seo-pro:scan` op de pagina's met voorbeeldgegevens.
Het statusrapport, de scansamenvatting en de [score van 0–100](/nl/pro/scoring)
verschijnen in de Compose-logs.

## De Pro-werkwijze bekijken {#see-the-pro-workflow}

De [rondleiding van scan naar correctie en rapport](/nl/pro/walkthrough) toont
een draaiende Merchant-demo: een echte scan, details van bevindingen, een in
Filament opgeslagen beschrijving, de nieuwe scan en een downloadbare PDF.
De content is gemarkeerd als voorbeeldgegevens. De resultaten vóór en na de
correctie komen uit twee nieuwe scans.

Er is nog geen publiek gehoste interactieve demo. Gebruik Docker om de engine
lokaal te draaien. De [README van de demo](https://github.com/rankbeam/rankbeam-examples/tree/main/demo)
beschrijft de installatie en hoe je wisselt tussen uitgebrachte en lokale pakketten.

::: tip Heb je al een applicatie?
Sla de demo over en ga rechtstreeks naar [Snelstart](/nl/guide/quickstart):
van installatie naar een volledig gerenderde `<head>` in vijf minuten.
:::
