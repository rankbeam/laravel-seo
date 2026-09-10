---
description: "WordPress mit Yoast oder Rank Math schrittweise durch Rankbeam ersetzen. Standardmäßig nur leere Felder ergänzen, zunächst ohne Schreibzugriff prüfen und WordPress unverändert lassen."
---

# Migrationshandbuch: WordPress → Rankbeam {#wordpress-→-rankbeam-migration-runbook}

Dieses Vorgehen ersetzt einen WordPress-SEO-Stack mit Yoast oder Rank Math schrittweise durch Rankbeam. Standardmäßig füllen die Importer leere Zielfelder; erst `--overwrite` erlaubt ausdrücklich das Ersetzen vorhandener Werte. Dry Runs schreiben nichts, die WordPress-Quelldatenbank bleibt unberührt. Sichere vor dem Import sowohl Quelle als auch Ziel.

Die technische Referenz [Migration von WordPress](/de/guide/migrate-from-wordpress) beschreibt Feldzuordnung, Vorlagen-Tokens und Quellschlüssel. Hier steht die Reihenfolge der praktischen Schritte.

::: tip Voraussetzungen
- **Core** (`rankbeam/laravel-seo`) für den Metadatenimport und `seo:audit`.
- **Pro** (`rankbeam/laravel-seo-pro`) nur, wenn du auch **Weiterleitungen** migrierst. Die Tabelle `seo_redirects` gehört zu Pro.
- Bereits in Laravel modellierte Inhalte, etwa `App\Models\Post`, mit dem Trait [`HasSEO`](/de/guide/quickstart). Ein WordPress-Slug muss einem Modell zugeordnet werden können, über dessen Route-Key oder eine mit `--match-by` gewählte Spalte.
:::

## Aufbau der Migration {#the-shape-of-the-migration}

WordPress-Zeilen werden über **URL oder Beitrag** identifiziert. Rankbeams `seo_meta`-Datensätze sind **polymorph** und gehören zu einem Eloquent-Modell. Der Import ordnet jede WordPress-Zeile einem deiner Modelle zu. Jeder Lauf berichtet diese drei Ergebnisse:

| Ergebnis | Bedeutung | Nächster Schritt |
|---|---|---|
| **matched** | Die Zeile wurde einem Modell zugeordnet und `seo_meta` geschrieben. | Keine weitere Zuordnung nötig |
| **url-only** | Kein passendes Modell gefunden oder kein `--model` angegeben. | Entscheiden, ob die Seite ein Modell oder eine Weiterleitung benötigt |
| **unmapped** | Daten ohne Speicherort in Core 3, besonders **author**. | Anderweitig übernehmen, etwa über `getSEOAuthor()` |

---

## Schritt 0: Parallel betreiben, noch nicht umschalten {#step-0-—-coexist-no-cutover-yet}

Richte Rankbeam **neben** der laufenden Website ein. Ergänze deine Modelle um `HasSEO` und rendere Tags über Fassade oder Direktive. Entferne WordPress oder dessen SEO-Plugin **noch nicht**. Bisher werden keine Daten importiert oder gelöscht; du prüfst nur, ob der neue Stack startet.

Wenn neue Laravel-App und alte WordPress-Website während des Umstiegs denselben Host verwenden, halte sie bis Schritt 5 auf getrennten Pfaden.

## Schritt 1: Metadaten importieren, zuerst als Dry Run {#step-1-—-import-the-metadata-dry-run-first}

Beginne immer mit `--dry-run`. Dieser Modus **schreibt nichts** und zeigt den vollständigen Prüfbericht für die erwarteten Änderungen.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

Nützliche Optionen; die vollständige Liste liefert `php artisan seo:import-from --help`:

| Option | Zweck |
|---|---|
| `--model=` | Vollqualifizierter Klassenname des Zielmodells. Wiederholbar; die WordPress-Importer verwenden **ein** Modell je Lauf. Einmal pro Inhaltstyp ausführen. |
| `--match-by=` | Modellspalte zum Abgleich des Slugs; standardmäßig der Route-Key |
| `--post-type=` | Beitragstypen für die Datenbank-Reader; standardmäßig `post` und `page` |
| `--connection=` | Datenbankverbindung mit den WordPress-Tabellen |
| `--table=` | **Präfix** der WordPress-Tabellen; standardmäßig `wp_` |
| `--locale=` | Locale für die geschriebenen `seo_meta`-Datensätze |
| `--redirects-csv=` | Weiterleitungskandidaten für Schritt 3 in diese Datei ausgeben |
| `--site-url=` | URL der alten Website zum Ableiten von Pfaden aus absoluten URLs |
| `--overwrite` | Vorhandene nicht leere `seo_meta`-Werte ersetzen; standardmäßig **nur leere Felder füllen** |
| `--limit=` | Anzahl der Quellzeilen begrenzen, etwa für den ersten Lauf |
| `--json` | Maschinenlesbarer Bericht |

Stimmt das Ergebnis des Dry Runs, entferne `--dry-run`, um den Import auszuführen:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

Der Import ist **idempotent** und füllt standardmäßig **nur leere Felder**. Ohne `--overwrite` kannst du ihn wiederholen, ohne bereits in Rankbeam bearbeitete Metadaten zu ersetzen.

## Schritt 2: Prüfbericht lesen und archivieren {#step-2-—-read-and-archive-the-verification-report}

Jeder Lauf zeigt einen **Verification report**. Prüfe dessen Zahlen, bevor du etwas entfernst, und speichere ihn dauerhaft:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

Prüfpunkte:

- **matched** sollte der erwarteten Anzahl von Seiten mit SEO-Metadaten entsprechen.
- **url-only** ist die Liste der Seiten ohne Modellzuordnung. Entscheide je Seite, ob sie ein Modell, eine Weiterleitung aus Schritt 3 oder keine weitere Bearbeitung benötigt.
- **truncated** zeigt Felder, die auf die Länge einer `seo_meta`-Spalte gekürzt wurden. Prüfe diese Titel und Beschreibungen.
- **unmapped** listet Quelldaten ohne Core-3-Spalte auf, einschließlich **jedes unterschiedlichen `author`-Werts**. Autoren werden über `getSEOAuthor()` bereitgestellt, nicht in einer solchen Spalte gespeichert. Übernimm sie bewusst an ihren neuen Ort.

## Schritt 3: Weiterleitungen in Pro importieren {#step-3-—-import-the-redirects-into-pro}

Der Core-Importer **schreibt nie in `seo_redirects`**, da diese Tabelle zu Pro gehört. Er liefert eine CSV mit fester, versionierter Struktur: **Weiterleitungs-CSV-Format v1**, `source_path,target_url,status_code,note`. Beginne auch den Pro-Import mit einem Dry Run:

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Jede Zeile wird wie im Filament-Weiterleitungsformular geprüft. Fehlerhafte Zeilen, ungültige Statuscodes, **unsichere externe Ziele**, **doppelte Quellen** und **Weiterleitungsschleifen** werden mit Begründung übersprungen und nicht still gespeichert. Der Dry Run validiert die gesamte Datei einschließlich Schleifen und Duplikaten und schreibt nichts. Mit `--overwrite` kannst du das Ziel einer bestehenden Regel ersetzen.

## Schritt 4: Mit `seo:audit --strict` prüfen {#step-4-—-verify-with-seo-audit-strict}

Verwende das kostenlose Audit innerhalb der Anwendung als Prüfschritt für die Migration. `--strict` beendet den Befehl mit einem Fehlercode, wenn **irgendeine** Seite einen Befund hat, und eignet sich damit als CI- oder Umschaltprüfung:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

Das Audit prüft Modell und Resolver: vorhandene Titel und Beschreibungen samt Länge, OG-Bild, Robots-Konflikte und Canonical-Format. Gerendertes HTML, Live-Canonicals und die Bewertung von 0 bis 100 gehören zum [Pro-Scan](/de/pro/scan-issues). Führe diesen bei vorhandener Pro-Lizenz ebenfalls aus. Siehe [Kostenloses SEO-Audit](/de/guide/audit).

Prüfe anschließend einige echte Seiten im Browser. Im Quelltext müssen `<title>`, `<meta name="description">`, Canonical, Robots und Open-Graph-Tags die importierten Werte enthalten.

## Schritt 5: Vor dem Entfernen alter Pakete und Tabellen prüfen {#step-5-—-verify-before-removing-the-legacy-package-table}

Lösche WordPress-Datenbank, SEO-Plugin oder altes Paket **erst**, wenn **alle** Punkte erfüllt sind:

- [ ] Der Import wurde für **jeden** Inhaltstyp ausgeführt, mit einem `--model` je Lauf.
- [ ] Der archivierte Prüfbericht enthält die erwartete **matched**-Anzahl und keine unerwarteten **url-only**-Zeilen.
- [ ] Alle benötigten **unmapped-Autorwerte** wurden anderweitig übernommen.
- [ ] Die Weiterleitungen wurden mit `seo-pro:redirects-import` in Pro importiert; einige alte URLs führen tatsächlich per 301 zu den neuen.
- [ ] `php artisan seo:audit --strict` endet mit `0`.
- [ ] Bei Pro meldet `php artisan seo:doctor` weder eine verbliebene alte Tabelle `seo` noch eine Kollision in `config/seo.php`.
- [ ] Gerenderte Seiten wurden stichprobenartig im Browser geprüft.

Mit dem standardmäßigen Füllen leerer Felder und ohne `--overwrite` lässt sich Schritt 1 vor dieser Prüfung wiederholen. Die alten Daten bleiben in WordPress erhalten.

## Schritt 6: WordPress außer Betrieb nehmen {#step-6-—-decommission}

Erst nach erfolgreicher Prüfung in Schritt 5: Nimm die WordPress-Website offline und entferne anschließend ihre Datenbank beziehungsweise Tabellen sowie das alte SEO-Paket. Behalte eine Datenbanksicherung, bis der neue Stack zuverlässig in Produktion läuft.

::: tip Rückkehr zum vorherigen Stand
Mit den Standardoptionen ergänzen Schritte 1–4 `seo_meta`, prüfen Weiterleitungen und lassen WordPress unverändert. Neu angelegte Weiterleitungsregeln lassen sich wieder entfernen. Bei ausdrücklich gewähltem `--overwrite` benötigst du zur Wiederherstellung ersetzter Zielwerte die vorherige Sicherung. Vor Schritt 6 kannst du WordPress weiter ausliefern; nach Schritt 6 musst du dafür die WordPress-Sicherung wiederherstellen.
:::

---

Du kommst stattdessen von einem **Laravel**-SEO-Paket wie ralphjsmit, artesaos oder Spatie? Siehe [Migration von anderen Laravel-Paketen](/de/guide/migrate-from-other-packages).
