---
description: "laravel-seo-pro installieren: Queue-Scans mit Befundverwaltung, Weiterleitungen und 404-Monitor auf Basis des Cores. Für Laravel 11–13, mit optionaler Filament-Oberfläche."
---

# Pro installieren {#installing-pro}

`rankbeam/laravel-seo-pro` ergänzt den Core um Queue-basierte Website-Scans mit Befundverwaltung, Weiterleitungsverwaltung und einen 404-Monitor. Die Engine läuft in **jeder Laravel-11–13-App**, mit Blade, Inertia oder als reine API. Filament ist eine optionale Oberfläche: Mit Filament erhältst du SEO-Dashboard, Weiterleitungen und 404-Monitor als Panel-Seiten. Ohne Filament verwaltest du sie über [Artisan-Befehle](/de/pro/headless).

## Voraussetzungen {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13) |
| Laravel | 11, 12 oder 13 |
| `rankbeam/laravel-seo` | ^3.20, von Pro 2.40+ automatisch installiert |
| `filament/filament` | **Optional**, 4.x oder 5.x, nur für die Admin-Oberfläche |
| `rankbeam/laravel-seo-filament` | **Optional**, ^1.11 bei Verwendung des SEO-Editors mit Pro 2.36+ |

Beginne mit einer vorhandenen Laravel-App und eingerichteter Datenbank. Schließe zuerst den [Core-Schnellstart](/de/guide/quickstart) ab, damit ein Modell Metadaten rendert und die Core-Tabellen existieren. Die Pro-Lizenz liefert die unten verwendeten Composer-Zugangsdaten.

Ein visuelles Beispiel des Ergebnisses zeigt die Anleitung [Scan → Korrektur → Bericht](/de/pro/walkthrough).

## Paket installieren {#install-the-package}

Pro wird über ein privates, an deine Lizenz gebundenes Composer-Repository verteilt. Füge das Repository einmal hinzu und installiere das Paket. Composer fragt nach der Lizenz-E-Mail als Benutzername und dem Lizenzschlüssel als Passwort:

```bash
composer config repositories.rankbeam-pro composer https://laravel-seo-pro.composer.sh
composer require rankbeam/laravel-seo-pro
```

::: details Composer-Authentifizierung ohne interaktive Eingabe
Hinterlege in CI oder anderen nicht interaktiven Umgebungen die Zugangsdaten vorab:

```bash
composer config http-basic.laravel-seo-pro.composer.sh you@example.com YOUR-LICENSE-KEY
```

:::

Führe anschließend den Installer aus:

```bash
php artisan seo-pro:install
```

Der Installer veröffentlicht `config/seo-pro.php` und die Pro-Migrationen, führt `migrate` aus und zeigt die nächsten Schritte. Die Datenbank deiner App sollte nun die Core- und Pro-Tabellen enthalten.

::: details Manuelle Installation und Installer-Optionen
Pro-Migrationen werden in deine App veröffentlicht und nicht automatisch aus dem Paket geladen. Die entsprechenden manuellen Schritte sind:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Der Installer lässt sich erneut ausführen. `--no-migrate` veröffentlicht Dateien, ohne zu migrieren. Verwende `--force` nur, wenn du veröffentlichte Dateien einschließlich deiner Konfiguration überschreiben möchtest.
:::

## Scan-Ziele registrieren {#register-scan-targets}

Lege in einem Service Provider fest, was der Scanner prüfen soll: Modellklassen, benannte Routen oder alle Einträge deiner [Sitemap-Registry](/de/guide/sitemaps).

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

Ersetze `Post` durch dein eigenes Modell mit `HasSEO`. Für ein Modell-Scan-Ergebnis muss mindestens ein Datensatz vorhanden sein. Routenziele müssen existierende Routen benennen. Lass diese Registrierung weg, wenn du nur Modelle prüfen möchtest.

## Installation prüfen {#verify-your-install}

Führe die Konfigurationsprüfung aus:

```bash
php artisan seo:doctor
```

Prüfe, ob Core- und Pro-Tabellen vorhanden sind, die Anwendungs-URL stimmt und deine Scan-Ziele aufgeführt sind. Befolge die gemeldeten Korrekturen. Beim Test der folgenden Inline-Befehle ist eine Warnung zur `sync`-Queue erwartbar. Richte einen Worker ein, bevor du Scans in Produktion planst.

::: details Beispielausgabe der Zustandsprüfung
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

`seo:doctor` prüft Konfiguration und letzte Laufhistorie, ohne Netzwerkaufrufe oder Ausgabe geheimer Werte. Ob ein externer Cronjob oder Worker tatsächlich läuft, kann der Befehl nicht beweisen. Kritische Fehler ergeben einen Exit-Code ungleich null, Warnungen nicht. `--json` liefert ein maschinenlesbares Ergebnis.
:::

## Den ersten Scan ausführen {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

Der erste Befehl führt den Scan direkt aus; dieser erste Test benötigt deshalb keinen Queue-Worker. Der zweite zeigt den letzten Lauf und seine Ergebnisse. Erwartet wird ein abgeschlossener Lauf mit verarbeiteten registrierten Zielen. Untersuche fehlgeschlagene Ziele, bevor du den Scan als vollständig wertest.

Korrigiere ein gemeldetes Feld, speichere es und scanne erneut. Die [Schritt-für-Schritt-Anleitung](/de/pro/walkthrough) zeigt dies an einer fehlenden Beschreibung mit anschließendem Änderungsbericht. Eine [technische Bewertung](/de/pro/scoring) ist ein Diagnoseergebnis und keine Ranking-Prognose.

## Verwendung ohne Oberfläche {#path-b-headless}

Die Engine lässt sich ohne Panel verwenden. Mit [Artisan-Befehlen](/de/pro/headless) kannst du scannen, Befunde ansehen, Weiterleitungen erstellen und Berichte erzeugen. Die Middlewares für Weiterleitungen und 404-Monitor registrieren sich standardmäßig automatisch. Ihre Einstellungen stehen in `config/seo-pro.php`.

Für geplante Aufgaben beschreibt [Produktion einrichten](/de/pro/production) Queues, Worker, Scheduler und Aufbewahrung.

## Optional ein Filament-Panel ergänzen {#path-a-with-a-filament-panel}

Registriere in einem vorhandenen Filament-4/5-Panel das folgende Pro-Plugin. Hat deine App noch kein Panel, installiere zuerst die UI-Pakete und lege eines an:

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

Damit erhältst du das **SEO-Dashboard** mit vollständigem Scan, Live-Fortschritt und Befundliste samt erneutem Scan einzelner Seiten, die **Weiterleitungsverwaltung** und den **404-Monitor** mit der Aktion *Create redirect*. `rankbeam/laravel-seo-filament` ergänzt außerdem den [SEO-Feldbereich](/de/guide/filament) in deinen Ressourcenformularen.

## Fehlerbehebung {#troubleshooting}

| Ergebnis | Nächster Schritt |
|---|---|
| Composer lehnt Zugangsdaten ab | Prüfe Lizenz-E-Mail und Schlüssel für `laravel-seo-pro.composer.sh`. Zugangsdaten gehören nicht in die Versionsverwaltung. |
| Doctor meldet fehlende Tabellen | Schließe den Core-Schnellstart ab und führe `seo-pro:install` sowie `migrate` gegen dieselbe Datenbank wie die App aus. |
| Ein Scan verarbeitet keine Ziele | Prüfe die Provider-Registrierung und vorhandene Datensätze des Modells. |
| Ein Queue-Scan bleibt ausstehend | Starte den konfigurierten Worker oder prüfe mit `--sync` direkt im Prozess. |
| Ein Ziel schlägt fehl | Prüfe Laufdetails, Routennamen und Anwendungs-URL vor dem erneuten Scan. |
| Das Dashboard fehlt | Registriere `SeoProPlugin` im tatsächlich verwendeten Panel und prüfe die Zugriffsregeln. |

Worker-Wiederherstellung und laufenden Betrieb beschreibt [Produktion einrichten](/de/pro/production).
