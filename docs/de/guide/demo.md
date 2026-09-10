---
description: "Die Rankbeam-Demo mit vorbereiteten Daten per Befehl starten: veröffentlichte Pakete ohne Path-Repositories, gerenderte Metadaten, JSON-LD-Graph und Sitemap auf echten Seiten."
---

# Die Demo starten {#run-the-demo}

Die ausführbare Demo zeigt Rankbeam auf echten Seiten, ohne dass du es zuerst in deine eigene App einbauen musst. Die Laravel-App enthält vorbereitete Daten, installiert die **veröffentlichten** Pakete ohne Path-Repositories oder benachbarte Checkouts und rendert einige Seiten mit vollständigen SEO-Metadaten, JSON-LD-Graph und Sitemap. Mit einer Lizenz führt sie auch das [technische SEO-Audit](/de/pro/scan-issues) von Pro aus.

## Ein Befehl für den kostenlosen Core {#one-command-free-core}

Die Demo steht als Docker-Image im Repository [`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples) bereit:

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

Öffne `http://localhost:8080`. Im Seitenquelltext siehst du den aufgelösten `<head>`, unter `/sitemap.xml` die erzeugte Sitemap. Dieser Teil verwendet ausschließlich den kostenlosen MIT-Core, installiert über Packagist.

## Mit Pro das Audit ausführen {#with-pro-the-audit}

Pro wird pro Projekt lizenziert und aus seinem privaten Composer-Repository installiert. Übergib deine Lizenz über `COMPOSER_AUTH` als Build-Secret, das niemals in eine Image-Schicht geschrieben wird, und baue mit dem Pro-Schalter:

```bash
export COMPOSER_AUTH='{"http-basic":{"laravel-seo-pro.composer.sh":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

Beim Start führt die Demo [`seo:doctor`](/de/pro/headless#setup-health-check) und einen ersten `seo-pro:scan` über die vorbereiteten Seiten aus. Zustandsbericht, Scan-Zusammenfassung und [Bewertung von 0 bis 100](/de/pro/scoring) erscheinen in den Compose-Logs.

## Den Pro-Ablauf ansehen {#see-the-pro-workflow}

Die [Anleitung Scan → Korrektur → Bericht](/de/pro/walkthrough) zeigt eine laufende Merchant-Demo: einen tatsächlichen Scan, Problemdetails, eine in Filament gespeicherte Beschreibung, den erneuten Scan und eine herunterladbare PDF. Die Inhalte sind als Beispieldaten gekennzeichnet; die Vorher-/Nachher-Ergebnisse stammen aus zwei neu ausgeführten Scans.

Eine öffentlich gehostete interaktive Demo gibt es derzeit nicht. Starte die Engine lokal mit Docker. Die [Demo-README](https://github.com/rankbeam/rankbeam-examples/tree/main/demo) beschreibt die Einrichtung und den Wechsel zwischen veröffentlichten und lokalen Paketen.

::: tip Du hast bereits eine App?
Gehe direkt zum [Schnellstart](/de/guide/quickstart): von der Installation zum vollständig gerenderten `<head>` in etwa fünf Minuten.
:::
