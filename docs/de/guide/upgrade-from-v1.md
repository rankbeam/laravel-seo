---
description: "Von fibonoir/laravel-seo v1 auf rankbeam/laravel-seo v2 wechseln. Das Paket erhält einen neuen Namen; der Core konzentriert sich auf Metadatenauflösung, Rendering, JSON-LD und Sitemaps."
---

# Upgrade von fibonoir/laravel-seo v1 {#upgrading-from-fibonoir-laravel-seo-v1}

Mit v2.0.0 heißt das Paket `rankbeam/laravel-seo`. Der Core konzentriert sich auf Metadatenauflösung, Rendering, JSON-LD und Sitemaps. Analyzer, Scanner, Weiterleitungen, 404-Monitor und Admin-Oberfläche wurden aus dem Core in separate Pakete ausgelagert.

## 1. Das Paket ersetzen {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Namespaces aktualisieren {#_2-update-namespaces}

Die Klassennamen bleiben gleich. Nur der Stamm-Namespace hat sich geändert: `Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`. Eine projektweite Suchen-und-Ersetzen-Aktion deckt die Umbenennung ab. Der Alias der `SEO`-Fassade und die Blade-Direktiven `@seo` bleiben unverändert.

## 3. Veraltete veröffentlichte Dateien entfernen {#_3-delete-stale-published-files}

Sichere vor dem Entfernen von Dateien oder Tabellen die veröffentlichte Konfiguration und exportiere die betroffenen Daten. Prüfe, ob du sie wiederherstellen kannst. Diese Anleitung überträgt keine Weiterleitungs-, 404- oder Scan-Historie aus v1 in das abweichende Pro-Schema. Die unten beschriebene Kompatibilität der Core-Tabellen gilt ausschließlich für `seo_meta` und `seo_defaults`.

::: warning Konflikte ohne Fehlermeldung
`seo:install` aus v1 hat Dateien in deiner App veröffentlicht, die mit dem v2-Paket kollidieren können, ohne eine Fehlermeldung auszulösen.
:::

- **`config/seo.php`**: Eine von v1 oder `ralphjsmit/laravel-seo` veröffentlichte Datei, die der v1-Installer zurückgelassen haben kann, überlagert die Paketkonfiguration. Dadurch können `site_name` und alle `{site_name}`-Vorlagen leer werden. Entferne die alte Datei und veröffentliche die Konfiguration neu: `php artisan vendor:publish --tag=seo-config`.
- **v1-Migrationen** für Tabellen, die nicht mehr dem Core gehören: `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache`, `seo_internal_links_index`. Entferne die Migrationsdateien. Existieren diese Tabellen in Produktion, lösche sie nach der oben beschriebenen Sicherung **vor** der Installation von `rankbeam/laravel-seo-pro`. Pro erstellt sie mit einem anderen Schema neu.
- **Veröffentlichte Vorlagen** unter `app/` und `resources/js` aus dem Filament-3-/Livewire-/Vue-/React-Ablauf von v1 verweisen auf Klassen, die nicht mehr existieren.

Die beiden Core-Tabellen `seo_meta` und `seo_defaults` sind schemakompatibel; ihre Daten bleiben beim Upgrade erhalten.

## 4. Entfernte Funktionen und ihr neuer Ort {#_4-removed-features-and-where-they-went}

| Funktion in v1 | Neuer Ort |
|---|---|
| Filament-SEO-Formularbereich | [`rankbeam/laravel-seo-filament`](/de/guide/filament), kostenlos unter MIT |
| Inhaltsanalyse mit 32 Regeln | Der alte Analyzer wird durch diese Migration nicht übernommen. Technische SEO-Probleme erkennt der Website-Scanner in `rankbeam/laravel-seo-pro`; die numerische SEO-Bewertung ist eine aus Problemen berechnete Pro-Funktion. |
| Website-weiter Scanner | `rankbeam/laravel-seo-pro`: Queue-Pipeline und Dashboard |
| Weiterleitungsverwaltung | `rankbeam/laravel-seo-pro` mit Regex-Prüfung und Schutz vor offenen Weiterleitungen |
| 404-Monitor | `rankbeam/laravel-seo-pro`, standardmäßig ohne Speicherung von IP-Adressen |
| GA4-Analysen und interne Links | Backlog von `rankbeam/laravel-seo-pro` |
| Installer `seo:install` | Entfallen; Installation über require, Veröffentlichung der Konfiguration und Migration |

## 5. Geändertes Verhalten prüfen {#_5-behavior-changes-to-review}

- **`og:image` und `twitter:image` sind immer absolute URLs.** v1 gab manuell gesetzte relative Pfade unverändert aus.
- **Abgeleitete Canonicals verlieren den Query-String.** Explizite Canonicals bleiben unverändert.
- **Die automatische Sitemap-Erkennung gibt registrierten Quellen Vorrang.** Neben einer registrierten `sitemap-posts.xml` entsteht keine zusätzliche `sitemap-post.xml` mehr.
- **JSON-LD wird mit `JSON_HEX_*` maskiert.** Wenn du rohe Script-Ausgabe nachbearbeitest, berücksichtige die Maskierung von Zeichen wie `<`.

## 6. Bekannte Stolperstellen {#_6-known-gotchas}

- Laravels gewöhnlicher `DatabaseSeeder` verwendet `WithoutModelEvents`. Dadurch ist der automatische Erstellungs-Hook von `HasSEO` in Seedern ausgeschaltet.
- Enthält eine Titelvorlage für Routen-Standardwerte bereits deine Marke, beende sie mit dem konfigurierten `title_suffix`. Der Resolver hängt es dann nicht erneut an.
