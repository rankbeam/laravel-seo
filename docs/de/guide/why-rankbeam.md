---
title: Was ist Rankbeam? SEO-Infrastruktur für Laravel
description: "Rankbeam ist Open-Core-SEO-Infrastruktur für Laravel: kostenloser MIT-Core für Metadaten, Canonicals, JSON-LD, Sitemaps und Crawler-Regeln, ergänzt um kommerzielles Pro-Monitoring und eine optionale Filament-Oberfläche."
---

# Was ist Rankbeam? {#what-is-rankbeam}

**Rankbeam ist Open-Core-SEO-Infrastruktur für Laravel: ein kostenloser MIT-Core für Metadaten, Canonical-URLs, Social Cards, verknüpftes JSON-LD, Sitemaps und Crawler-Regeln, ergänzt um optionale kommerzielle Pro-Funktionen für Überwachung und Arbeitsabläufe.** Die SEO-Werte werden aus deinen Modellen und deiner Konfiguration aufgelöst. Dieselben typisierten Daten lassen sich in Blade, als Inertia-Head oder über eine JSON-API ausgeben. Mit Pro kommen Prüfungen nach dem Deployment hinzu.

## Die Paketfamilie {#the-package-family}

Rankbeam besteht aus drei Paketen mit einer gemeinsamen Kompatibilitätsmatrix:

| Paket | Lizenz | Inhalt |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, kostenlos** | Core mit Metadatenauflösung, verknüpftem JSON-LD-Schema-Graph, XML-Sitemaps, Crawler-Regeln, kostenlosem `seo:audit` und Importern |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, kostenlos** | Filament-4/5-Formularfelder und Live-Vorschauen, die in `seo_meta` des Cores schreiben |
| `rankbeam/laravel-seo-pro` | **Kommerziell** | Queue-basierte Scans mit Bewertung von 0 bis 100, Weiterleitungsverwaltung, 404-Monitor ohne IP-Speicherung, Broken-Link-Crawler, Search-Console-Auswertungen und KI-Hilfe mit eigenem API-Schlüssel |

Die Grenze ist bewusst gezogen: Die SEO-Ausgabe der gerenderten Seite gehört zum kostenlosen MIT-Core. Bezahlt wird die Ebene für **technisches Audit und laufende Überwachung**. Pro ist ein eigenes Paket und wird nicht im kostenlosen Core mitgeliefert.

## Für wen Rankbeam gedacht ist {#who-it-s-for}

Rankbeam lohnt sich bei **gespeichertem, modellgebundenem, mehrsprachigem und geprüftem SEO**, auch mit getrenntem Frontend: etwa in einer produktiven Laravel-App mit dynamischen oder modellbasierten Inhalten. Für wenige statische Seiten mit je einem Titel und einer Beschreibung passt ein kleiner Laufzeit-Tag-Builder besser. Darauf geht auch der Hinweis zu [bestehenden Paketkombinationen](#what-is-honestly-not-in-the-free-core) ein.

## Unterstützte Versionen {#supported-versions}

Eine Matrix für die gesamte Familie:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11, 12 oder 13; Laravel 13 benötigt PHP 8.3+
- **Filament** 4 oder 5, optional

## Was Rankbeam nicht ersetzt {#what-rankbeam-doesn-t-replace}

Rankbeam koordiniert die eigene SEO-Ausgabe einer Laravel-App. Es ist kein gehosteter Rank-Tracker, keine Keyword-Recherche-Suite und kein Analytics-Produkt. Rankings, Indexierung oder KI-Zitate werden nicht versprochen. Für XML-Sitemaps verwendet es [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap). Inhalte, Routing und Analytics bleiben in deiner Anwendung.

Zum Einstieg kannst du den [kostenlosen Core installieren](/de/guide/installation) oder den folgenden Bericht über einen Wechsel in einer produktiven Anwendung lesen. Pro und die Warteliste für das Gründerangebot findest du auf [rankbeam.dev](https://rankbeam.dev/de/).

## Warum nicht drei Pakete mit eigener Integration? {#why-not-three-packages-glue}

Viele Laravel-Apps verwenden einen **SEO-Stack**: ein Paket für Metadaten pro Modell, eines für Filament-Felder, eines für Scans und eigenen Anwendungscode, der diese Teile verbindet. Jedes Paket kann für sich sinnvoll sein. Zusätzliche Arbeit entsteht an den Schnittstellen, deren Integration du selbst warten musst.

Die folgenden Angaben stammen aus dem beschriebenen Wechsel einer produktiven Anwendung von einer solchen Kombination zur Rankbeam-Familie. Sie beziehen sich auf diesen Fall und dessen Tests; sie sind kein allgemeines Leistungsversprechen oder unabhängig veröffentlichter Kundennachweis.

## Die Referenz-App {#the-reference-app}

Die Dokumentation beschreibt eine hier anonymisierte Laravel-Inhaltswebsite in Produktion:

- **Krankenhaus- beziehungsweise institutionelle Website**, seit etwa drei Monaten produktiv.
- **Von WordPress migriert**, ungefähr 900 Seiten laut Sitemap.
- Rund **20.000 Besuche täglich**.
- **Laravel 12**, Administration mit **Filament 4**, Blade-Frontend und MySQL.

Der bisherige SEO-Stack:

| Ebene | Paket |
|---|---|
| Metadatenspeicherung in einer modellbezogenen Tabelle `seo` | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| SEO-Felder in Filament | `ralphjsmit/laravel-filament-seo` |
| Seitenscanner | `backstage/laravel-seo-scanner` |
| Verbindende Anwendungslogik | **Etwa 30 eigene Klassen** |

Im beschriebenen Wechsel wurden die drei Pakete durch Rankbeam **Core, Pro und Filament** ersetzt. Die SEO-Tests liefen durch und die App startete, ohne dass diese Prüfungen SEO-Regressionen meldeten. Die folgenden Abschnitte zeigen, welche eigene Integrationsarbeit dadurch entfiel.

## Welche Klassen durch den Wechsel entfielen {#what-the-swap-deleted}

Beim Ersatz des Scanner-Stacks entfielen **zwölf eigene Klassen** vollständig. Ihre Aufgaben übernahm die Paketfamilie:

| Entfernte Anwendungsklasse | Bisherige Aufgabe | Jetzt bereitgestellt durch |
|---|---|---|
| `Services/SeoService.php` | SEO-Einstiegspunkt der App | Core-Resolver und `SEO`-Fassade |
| `Services/SeoWarningEvaluator.php` | Grenzwerte für Titel, Beschreibung und Bildmaße | `SEOWarningEvaluator` im Core, gemeinsam für Audit, Vorschau und Scan |
| `Services/Seo/SeoAssetInspector.php` | Lokale Bildmaße prüfen | `LocalImageInspector` im Core |
| `Jobs/ScanAllPagesSeo.php` | Website-weite Scan-Jobs einreihen | [Scan-Pipeline](/de/pro/scan-issues) von Pro |
| `Jobs/ScanPageSeo.php` | Einzelne Seite scannen | `PageScanner` von Pro |
| `Jobs/ScanPublicPageSeo.php` | Öffentliche Seite scannen | Pro-Scan-Pipeline |
| `Models/SeoScanBatch.php` | Scan-Läufe verwalten | `seo_scan_runs` von Pro |
| `Filament/Pages/SeoDashboard.php` | SEO-Admin-Dashboard | Pro-Plugin `SeoDashboard` |
| `Filament/Widgets/SeoScanProgressWidget.php` | Scan-Fortschritt anzeigen | Pro-Scan-Widgets |
| `Filament/Widgets/SeoTrendChartWidget.php` | Scan-Trends anzeigen | Pro-Scan-Widgets |
| `Facades/Seo.php` | App-Fassade über dem Speicherpaket | `SEO`-Fassade im Core |
| `Console/Commands/RecoverLegacySeoMetadata.php` | Einmalige Metadatenwiederherstellung | Core-[Importer](/de/guide/migrate-from-wordpress), `seo:import-from` |

::: info Welche Klassen zunächst erhalten blieben
Der eigene Broken-Link-Crawler blieb beim Wechsel bewusst erhalten: etwa 17 Klassen für Scan-Job, Prüfer, Start-URL-Aufbereitung, Quellenauflösung, zwei Modelle, zwei Enums, zwei Events, eine Filament-Ressource mit drei Widgets und zwei Befehle. Dazu kamen Meta-/Schema-Helfer wie `CustomSEO`, `EntitySeoSection`, `DynamicSeoDataResolver`, `SitewideSchema` und `SeoKeywords`, zusammen etwa **22 weitere Klassen**. Ihre Rankbeam-Alternativen kamen erst danach: der [Pro-Broken-Link-Crawler](/de/pro/production), **Zielauflösung für verbundene Modelle** und **SERP-/Social-Vorschau** in Filament sowie der **Schema-Graph** im Core. Bei vollständiger Übernahme der entsprechenden Funktionen kann damit eine eigene Oberfläche von insgesamt ungefähr **drei Dutzend Klassen** entfallen. Das war nicht bereits das Ergebnis des ersten Umstellungstags.
:::

Die Zahl bewertet nicht die Qualität einzelner Pakete. Sie zeigt die eigene *Integration*: Klassen, die Änderungen an Metadaten zwischen Scanner, Dashboard und gerendertem Head abstimmen. Für diesen Code gibt es keinen gemeinsamen Upstream; Tests und Fehlerbehebung liegen bei der App.

## Direkter Vergleich {#side-by-side}

| Funktion | Zusammengesetzter Stack aus drei Paketen und eigener Integration | Rankbeam-Familie |
|---|---|---|
| Metadaten pro Modell speichern | Metadatenpaket | **Core**, `seo_meta`, MIT |
| **Locale-bezogene** Speicherung | Häufig eigene Integration | **Core**, Locale-Spalte in `seo_meta` |
| SEO-Felder in Filament | Filament-SEO-Paket | **`laravel-seo-filament`**, MIT |
| SEO eines **verbundenen Modells** bearbeiten | Feldkomponente selbst umschließen | Eigener `target:`-Resolver |
| Live-**SERP- und Social-Vorschau** | Eigene Blade-/Alpine-Umsetzung | Eingebaute Vorschau mit Tabs |
| Headless-Ausgabe mit Inertia, Livewire oder JSON | Die Referenz-App verwendete Blade; andere Stacks benötigen Integration | **Ein Resolver** für Blade, Inertia, Livewire und JSON, anhand eines [Vertrags geprüft](/de/contributing/rendering-contract) |
| Seitenscanner mit priorisierten Problemen | Scanner-Paket | **Pro** mit [Scan-Pipeline](/de/pro/scan-issues) und `IssueRegistry` |
| Bewertung von 0 bis 100 | Eigene Integration oder nicht vorhanden | **Pro** mit nachvollziehbarer [versionierter Bewertungsregel](/de/pro/scoring) |
| Weiterleitungen und 404-Bearbeitung | Weiteres Paket oder eigener Code | **Pro**, Weiterleitungsverwaltung und 404-Monitor ohne IP-Speicherung |
| Broken-Link-Crawler | Eigene Umsetzung in der Referenz-App | **Pro**, begrenzter und fortsetzbarer Crawler |
| JSON-LD-Schema-**Graph** | Builder und eigene `@id`-Verknüpfung | **Core**, verknüpfter Organization-/WebSite-/WebPage-Graph |
| XML-Sitemaps | Sitemap-Paket | **Core**, Sitemap-Registry auf Basis von `spatie/laravel-sitemap` |
| Import aus WordPress, Yoast und Rank Math | Einmalige Scripts | **Core**, `seo:import-from` und [Migrationshandbuch](/de/guide/wordpress-migration-runbook) |
| **Wartung der Schnittstellen** | **Deine Anwendung** | Die Paketfamilie mit abgestimmten Releases |

## Drei Grenzen eigener Integrationsschichten {#the-three-things-glue-can-t-do-well}

**1. Abgestimmte Familie und Releases.** Drei unabhängige Pakete haben getrennte Maintainer, Changelogs und Upgrade-Zyklen. Die Integration muss Unterschiede auffangen. Rankbeam Core, Pro und Filament werden mit einer gemeinsamen [Kompatibilitätsmatrix](#tested-where-it-runs) und dokumentierten [Konfigurations- und Upgrade-Grenzen](/de/reference/configuration) gepflegt. Verhaltensänderungen lassen sich gemeinsam dokumentieren.

**2. Locale als Bestandteil der Speicherung.** `seo_meta` ist auf Datenbankebene polymorph **und** Locale-bezogen. Mehrsprachiges SEO verwendet einen Datensatz je `(model, locale)`, statt eine zusätzliche serialisierte Struktur oder selbst ergänzte Tabelle zu benötigen. Die [Resolver-Priorität](/de/concepts/resolver-precedence) berücksichtigt die aktive Locale direkt.

**3. Headless-Ausgabe aus einem Resolver.** Rankbeam löst typisiertes `SEOData` auf und rendert dieselben Daten als HTML, Inertia-`Head`-Nutzdaten oder JSON-Array. Ein gemeinsamer [Rendering-Vertrag](/de/contributing/rendering-contract) prüft die Ausgabe für [Blade](/de/guide/blade), [Inertia](/de/guide/inertia-json) mit Vue, React oder Svelte und [Livewire](/de/guide/livewire). Ein Admin-Panel ist nicht erforderlich; Pro-Funktionen lassen sich auch [ohne Oberfläche über Artisan](/de/pro/headless) ausführen.

## Was nicht zum kostenlosen Core gehört {#what-is-honestly-not-in-the-free-core}

Rankbeam ist Open-Core. Vor der Installation mit `composer require` gilt folgende Grenze:

| Paket | Lizenz | Inhalt |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, kostenlos** | Metadatenauflösung, JSON-LD-Schema-Graph, Sitemaps, kostenloses `seo:audit`, Importer |
| `rankbeam/laravel-seo-filament` | **MIT, kostenlos** | Filament-Felder und Formularbereiche zum Schreiben in `seo_meta` |
| `rankbeam/laravel-seo-pro` | **Kommerziell** | Queue-Scans mit priorisierten Problemen und Bewertung von 0 bis 100, Weiterleitungen, 404-Monitor, Broken-Link-Crawler, Search Console, KI-Hilfe und Filament-Dashboard |

Kostenpflichtig sind damit das **technische SEO-Audit** und die **laufende Website-Überwachung** mit Scans, Bewertung, Weiterleitungen, 404-Bearbeitung und Crawler. Metadaten-Engine, Schema-Graph, Sitemaps und das kostenlose Audit innerhalb der App bleiben unter MIT.

Zwei Eigenschaften dieser Trennung:

- **Keine Lizenzprüfung zur Laufzeit.** Pro wird bei der Installation pro Projekt lizenziert. Es meldet sich nicht beim Anbieter und besitzt keinen fernsteuerbaren Abschalter. Pro kann abschaltbare *lokale* Betriebsdaten in deine eigenen Logs schreiben, sendet sie aber nicht an Rankbeam.
- **KI mit eigenem Schlüssel.** Die optionale [KI-Hilfe](/de/pro/ai-assist) verwendet deinen Zugang zu Anthropic, OpenAI, Google oder einem lokalen Modell. Rankbeam leitet Anfragen nicht über einen eigenen Proxy und verkauft keine Token weiter. Die Funktion ist standardmäßig aus.

::: tip Wann die bestehende Paketkombination genügt
Für wenige statische Seiten mit je einem `<title>` und einer Beschreibung reicht ein Laufzeit-Tag-Builder. Rankbeam lohnt sich, sobald SEO gespeichert, mehrsprachig, modellgebunden, headless und geprüft sein soll und die Verbindung zwischen Paketen eigenen wartungsbedürftigen Code erfordert.
:::

## Ein schrittweiser Wechsel von WordPress {#the-lowest-risk-switch-off-wordpress}

Die Referenz-App migrierte ungefähr 900 Seiten aus WordPress. Bei solchen Umzügen müssen über Jahre gepflegte Yoast-/Rank-Math-Angaben erhalten bleiben. Rankbeam bietet dafür diesen Ablauf:

1. **Parallel betreiben.** Rankbeam neben der laufenden Website einrichten, ohne etwas zu entfernen.
2. **Importieren, zuerst als Dry Run.** `seo:import-from yoast`, `rank-math` oder `wordpress-csv` liest Titel, Beschreibungen, Canonicals, Robots, Fokus-Keywords und verfügbare Social-Overrides. Die Importer sind **idempotent** und füllen **standardmäßig nur leere Felder**. Ohne `--overwrite` bleiben vorhandene Metadaten erhalten; `--dry-run` schreibt nichts.
3. **Weiterleitungen übergeben.** Core erzeugt eine versionierte Weiterleitungs-CSV. `seo-pro:redirects-import` validiert jede Zeile vor dem Schreiben und weist Schleifen, unsichere Ziele und Duplikate zurück.
4. **Vor dem Löschen prüfen.** `seo:audit --strict` endet bei jedem Befund mit einem Fehlercode und dient als CI- beziehungsweise Umschaltprüfung. Die alte WordPress-Datenbank bleibt bis zu deiner ausdrücklichen Entfernung unverändert.

Den vollständigen Ablauf beschreibt das [WordPress-Migrationshandbuch](/de/guide/wordpress-migration-runbook), die Feldzuordnung und Token-Behandlung die Referenz [Migration von WordPress](/de/guide/migrate-from-wordpress). Für den Wechsel von einem **Laravel**-SEO-Paket wie ralphjsmit, artesaos oder Spatie gibt es die [Anleitung zur Paketmigration](/de/guide/migrate-from-other-packages).

## Verhalten bei größerem Umfang {#does-it-hold-up-at-scale}

Für zwei Anforderungen der Referenz-App, die Auflösung bei ungefähr 20.000 täglichen Anfragen und einen Crawl von etwa 900 Seiten, enthält die Testsuite Benchmarks. Sie prüfen **deterministische Eigenschaften** wie Abfragezahlen und Job-Grenzen, keine auf einen Rechner abgestimmten Laufzeiten.

**Resolver-Cache: Ein warmer Treffer fragt die Datenbank nicht ab.** Der optionale Cache überspringt bei einem Treffer die gesamte Prioritätskette. Der Benchmark führt 25 Auflösungen desselben Modells aus:

| | Datenbankabfragen |
|---|---|
| Ohne Cache; jede Auflösung liest `seo_meta` erneut | **≥ 25** |
| Warmer Cache-Treffer | **0** |

Der Cache ist **standardmäßig aus**. Seine Invalidierung verwirft passende Einträge, wenn sich `seo_meta`, ein Inhaltsfeld oder Standardwerte ändern. Siehe [Konfiguration und Cache](/de/reference/configuration).

**Broken-Link-Crawler: begrenzte Jobs bei 900 Seiten.** Der Crawler-Benchmark verarbeitet einen erzeugten Korpus von etwa 900 Seiten mit dem tatsächlichen Job:

- Abschluss über **mindestens 18 begrenzte Jobs**, höchstens 50 Seiten pro Job.
- **Kein einzelner Job** überschreitet die 50-Seiten-Grenze.
- **1.800 geprüfte Links**; jedes defekte Ziel wird als dauerhafter, bestätigter Befund gespeichert.

Endliche Grenzen pro Lauf und ein Zeitbudget pro Job begrenzen die Arbeit. SSRF-Prüfungen gelten für die Start-URL und jeden Weiterleitungsschritt. Eine Datenbank-Lease erlaubt nur einen aktiven Lauf je Geltungsbereich. Den Betrieb beschreibt die [Produktionsanleitung](/de/pro/production).

## Getestete Laufzeitumgebungen {#tested-where-it-runs}

Eine gemeinsame Kompatibilitätsmatrix für die Familie:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11, 12 oder 13
- **Filament** 4 oder 5

## Drei Pakete weiter selbst verbinden? {#so-—-why-glue-three-packages-together}

Wenn deine Paketkombination mehr als ein Dutzend eigene Integrationsklassen, getrennte Release-Zyklen, stackabhängige Ausgabe und nachträglich ergänzte Locale-Verarbeitung benötigt, lohnt sich der Vergleich mit der zusammenhängenden Rankbeam-Familie. Der beschriebene Wechsel zeigt, welche Aufgaben sie in einer produktiven Anwendung mit etwa 900 Seiten und 20.000 täglichen Besuchen übernehmen konnte.

Der [Schnellstart](/de/guide/quickstart) führt von `composer require` zu einem vollständig gerenderten `<head>` in etwa fünf Minuten.
