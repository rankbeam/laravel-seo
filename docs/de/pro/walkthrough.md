---
description: "Einen echten Rankbeam-Pro-Scan nachvollziehen, eine fehlende Beschreibung in Filament speichern, erneut scannen und den erzeugten Beispielbericht als PDF herunterladen."
---

# Vom Scan zur geprüften Korrektur {#from-a-scan-to-a-verified-fix}

Ein Scan fand eine fehlende Beschreibung in einem Demoartikel. Wir ergänzten sie in Filament, scannten erneut und erzeugten einen Bericht mit der Korrektur.

Die Aufnahmen stammen aus einer lokal laufenden Merchant-Demo vom 9. September 2026. Inhalte sind vorbereitete Beispieldaten; beide Scans und der Bericht wurden für diese Anleitung neu erzeugt. Es wurde kein historischer Trend vorgegeben. Die App verwendet Laravel 12 und Filament 4 mit Rankbeam-Core, kostenlosem Editor und Pro-Engine. Screenshots und Beispiel-PDF zeigen die englische Oberfläche.

**[Erzeugten englischen Bericht herunterladen, PDF, 98 KB](/pro-walkthrough/merchant-demo-report.pdf)**

## Registrierte Seiten scannen {#scan-the-registered-pages}

Führe nach der [Pro-Installation](/de/pro/installation) und Registrierung deiner Scan-Ziele Folgendes aus:

```bash
php artisan seo-pro:scan --sync
```

Die Demo registriert 18 Inhaltsdatensätze und drei Routen. Der erste Scan verarbeitete alle 21 Ziele ohne Fehlschlag und fand 20 Befunde: sechs Warnungen und 14 Hinweise.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="Erster abgeschlossener Scan: 21 Ziele, 20 Befunde, sechs Warnungen und 14 Hinweise. Englische Oberfläche." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Die Screenshots haben doppelte Auflösung. Öffne sie zur Prüfung in voller Größe.*

## Einen Befund untersuchen {#inspect-one-issue}

Öffne im **SEO Dashboard** neben der betroffenen Zeile **Page issues**. Beim Artikel „Behind the Scenes: Our Product Photography“ benennt der Befund das fehlende Feld `description`, die Seiten-URL und den Scan, der ihn erkannt hat.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Der Dialog Page issues zeigt Post 5, dessen URL und die fehlende Beschreibung." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Beschreibung speichern {#save-the-description}

Öffne den Artikel unter **Posts**, fülle **SEO description** aus und speichere. Der [kostenlose Filament-Editor](/de/guide/filament) zeigt den Text in der Suchvorschau und bezeichnet seine Quelle als **Manual**. Im Beispiel hat die Beschreibung 142 Zeichen; der Titel stammt weiterhin aus dem Artikel.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="Gespeicherte SEO-Beschreibung mit Zähler für 142 Zeichen." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="Die Live-Vorschau verwendet die eingegebene Beschreibung mit Quellenangabe Manual." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Speichern und Prüfen sind getrennte Schritte. Die Scan-Bewertung aktualisiert sich erst beim nächsten Scan. Ohne Filament speicherst du denselben Wert über `saveSEO()` des Modells.

## Erneut scannen und Änderungen prüfen {#rescan-and-check-what-changed}

Führe denselben Befehl erneut aus:

```bash
php artisan seo-pro:scan --sync
```

Das Dashboard kennzeichnet nun genau diesen Befund als **Fixed**. Die übrigen 19 bleiben offen.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Gespeicherter Scan-Vergleich: keine neuen Befunde, keine Rückfälle, ein behobener und 19 weiterhin offene Befunde." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Prüfung | Vorher | Nachher |
|---|---|---|
| Abgeschlossene Ziele | 21 | 21 |
| Offene Befunde | 20 | 19 |
| Warnungen | 6 | 5 |
| Hinweise | 14 | 14 |
| Durchschnittliche technische SEO-Bewertung | 92 | 93 |

Die [Bewertung](/de/pro/scoring) beschreibt Rankbeams technische Prüfungen. Sie misst weder Traffic noch Suchpositionen oder Aufnahme in KI-Antworten. Eine bestandene Beschreibungsprüfung garantiert außerdem nicht, dass eine Suchmaschine genau diese Beschreibung anzeigt.

## Bericht erzeugen {#generate-the-report}

Für die Demonstration wurde **vor** der Artikelbearbeitung ein Ausgangsbericht und nach dem erneuten Scan ein zweiter Bericht erzeugt:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

Die zweite PDF zeigt **einen behobenen**, **keinen neuen** und **19 offene** Befunde. Ihr Trend enthält ausschließlich diese beiden Scans. Search Console und KI-Bot-Protokollierung waren deaktiviert; die entsprechenden Abschnitte melden deshalb nicht verfügbare Daten.

[![Erste Seite des englischen Beispielberichts: Bewertung 93, ein behobener und 19 offene Befunde.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

Der erste Bericht bildet den Ausgangsstand. Erzeugst du erst nach einer Korrektur einen einzigen Bericht, kann dieser keine Änderung gegenüber einem früheren Bericht zeigen. Verwende `--no-store` für eine Vorschau, die den Ausgangsstand nicht fortschreiben soll.

Das Beispiel verwendet den Browsershot-Renderer. Anforderungen, Branding und geplanten Versand beschreibt [White-Label-Berichte](/de/pro/reports).

## In deiner eigenen App ausführen {#run-it-on-your-own-app}

Beginne mit [Pro installieren](/de/pro/installation) und scanne eine Seite, deren Ausgabe du prüfen kannst. Pro läuft auch [ohne Filament](/de/pro/headless). Den kostenlosen Metadaten-Renderer kannst du zuerst mit der [Docker-Demo](/de/guide/demo) ausprobieren.
