---
description: "Wechsle von anderen Laravel-SEO-Paketen zu HasSEO und saveSEO(), einschließlich Import vorhandener SEO-Daten aus ralphjsmit/laravel-seo."
---

# Migration von anderen Laravel-SEO-Paketen

Diese Anleitung ordnet die APIs und Speicherformen gängiger SEO-Pakete den beiden Rankbeam-Bausteinen zu: dem Trait [`HasSEO`](/de/guide/quickstart) und `saveSEO()`. Für SEO-Daten, die pro Modell gespeichert sind, gibt es einen Importbefehl. Der konkrete Aufwand hängt von deinen Anpassungen ab.

::: tip Migration von WordPress
Für Inhaltsseiten mit Yoast oder Rank Math beschreibt [Migration von WordPress (EN)](/guide/migrate-from-wordpress) den CSV-Import und die Leser für bestehende Datenbanken.
:::

| Ausgangspaket | Speicherung | Migrationsweg |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | Polymorphe Tabelle `seo` | **`php artisan seo:import-from ralphjsmit`** und Trait austauschen |
| [`artesaos/seotools`](#from-artesaos-seotools) | Keine Datenbank; Laufzeit und Konfiguration | Aufrufe durch `saveSEO()` oder berechnete Getter ersetzen |
| [`spatie/*`](#from-spatie-packages) | Keine Metadatentabelle; Schema- und Sitemap-Builder | Ergänzende Teile behalten, andere zu Rankbeam übertragen |

Nur **ralphjsmit** speichert hier SEO-Daten in einer eigenen Datenbanktabelle und bietet damit Daten für einen Massenimport. Bei Laufzeit-Tag-Buildern gibt es keine solche Tabelle. Deren Aufrufe ersetzt du durch gespeicherte `seo_meta`-Werte oder berechnete Felder.

## Von `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

Das Paket speichert pro Modell eine polymorphe Zeile in `seo`. Die Struktur ähnelt Rankbeams `seo_meta`, sodass ein wiederholbarer Import möglich ist.

### 1. Rankbeam zusätzlich installieren {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Beide Pakete können während der Migration nebeneinander bestehen: Sie nutzen unterschiedliche Tabellen (`seo` und `seo_meta`) sowie unterschiedliche Trait-Namespaces.

::: warning Gemeinsamer Konfigurationsschlüssel
Eine vorhandene `config/seo.php` von ralphjsmit überschattet Rankbeams Konfiguration, weil beide den Schlüssel `seo` verwenden. Sichere die alte Datei, entferne sie und veröffentliche Rankbeams Konfiguration erneut mit `php artisan vendor:publish --tag=seo-config`.
:::

### 2. Import ausführen {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

Der Importer liest `seo`, löst jede Zeile zum tatsächlichen Eloquent-Modell auf und schreibt die Daten in `seo_meta`.

| Option | Wirkung |
|---|---|
| `--dry-run` | Geplanten Import anzeigen, nichts schreiben. |
| `--model="App\Models\Post"` | Auf eine oder mehrere Modellklassen beschränken; mehrfach möglich. |
| `--locale=fr` | Zielsprachversion; standardmäßig die Anwendungssprache. |
| `--table=legacy_seo` | Anders benannte Quelltabelle lesen. |
| `--connection=legacy` | Quelltabelle über eine andere Datenbankverbindung lesen. |
| `--limit=100` | Höchstens N Zeilen importieren, etwa für eine schrittweise Migration. |
| `--overwrite` | Vorhandene nichtleere Werte ersetzen; standardmäßig werden nur leere Felder ergänzt. |
| `--json` | Maschinenlesbaren Bericht ausgeben. |
| `--force` | Bestätigungsfrage für Skripte oder CI überspringen. |

Der Import ist **idempotent**: Ein erneuter Lauf bearbeitet dieselben Zeilen und erzeugt keine Duplikate. Standardmäßig füllt er nur leere Felder und bewahrt bereits gesetzte Rankbeam-Daten. Verwende `--overwrite` nur, wenn importierte Werte sie ersetzen sollen.

### 3. Trait auf den Modellen austauschen {#_3-swap-the-trait-on-your-models}

Ersetze den Trait von ralphjsmit durch den von Rankbeam. Einige Methodennamen unterscheiden sich; die gelesene Tabelle ist anschließend `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Übertrage Anpassungen aus `getDynamicSEOData()` in die feldbezogenen Getter `getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()` und `getSEOAlternates()`. Der [Schnellstart](/de/guide/quickstart) zeigt sie. Explizite Überschreibungen speicherst du mit `saveSEO()`:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Feldzuordnung {#field-mapping}

Der Importer ordnet Felder ausdrücklich zu. Er kopiert keine Spalten, die das Schema von Core 3 nicht besitzt.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Hinweise |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | Aus dem aktuellen Modell neu ermittelt, nicht unverändert kopiert. |
| `title` | `title` | Auf die Spaltenlänge von 70 Zeichen begrenzt; Kürzungen werden gemeldet. |
| `description` | `description` | Auf 160 Zeichen begrenzt; Kürzungen werden gemeldet. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Auf 50 Zeichen begrenzt. |
| `image` | `og_image` | Der Resolver vererbt es automatisch an `twitter:image`. |
| `author` | Nicht importiert | Core 3 hat hier keine Autorenspalte. Autorenschaft gehört zur Auflösung der Artikeldaten. Betroffene Zeilen werden gezählt und gemeldet, damit du einen geeigneten Speicherort oder berechneten Wert festlegen kannst. |
| `id`, `created_at`, `updated_at` | Nicht importiert | Strukturelle Quellfelder. |

**Warum der Morph-Typ neu ermittelt wird:** Jede Zeile wird zum tatsächlichen Modell aufgelöst. Die `seoable`-Schlüssel stammen aus dessen `getMorphClass()`. Das berücksichtigt die aktuelle [Morph Map](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types) der Anwendung, selbst wenn das alte Paket eine andere Konvention gespeichert hat. Zeilen gelöschter Modelle werden als übersprungen gemeldet und nicht als verwaiste Beziehungen geschrieben.

### Bericht verstehen {#what-the-report-tells-you}

Ohne `--json` zeigt der Befehl eine Ergebnistabelle und drei Prüfbereiche:

- **Truncated:** Werte, die auf die Länge einer `seo_meta`-Spalte gekürzt wurden. Prüfe diese Texte.
- **Not imported:** Befüllte Quellspalten ohne Entsprechung in Core 3, etwa `author`.
- **Skipped rows by reason:** Leere Quellzeilen, gelöschte Modelle und nicht auflösbare Modelltypen.

### Ergebnis prüfen {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Wenn die importierten Daten geprüft sind, kannst du `ralphjsmit/laravel-seo` entfernen und seine Tabelle `seo` löschen.

## Von `artesaos/seotools` {#from-artesaos-seotools}

`artesaos/seotools` erzeugt Tags zur **Laufzeit**. Meist setzt ein Controller Werte über `SEOMeta`, `OpenGraph`, `TwitterCard` und `JsonLd`; Standardwerte kommen aus `config/seotools.php`. Da nichts pro Modell gespeichert wird, gibt es keine Tabelle zu importieren. Übertrage die Aufrufe in gespeicherte oder berechnete Werte.

| Aufruf in artesaos/seotools | Entsprechung in Rankbeam |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` oder `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` oder `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` oder `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | Kein gleichwertiges Keywords-Metatag: Fokus-Keywords dienen internen redaktionellen Prüfungen. `saveSEO(['focus_keywords' => [...]])`, siehe [Audit](/de/guide/audit) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [JSON-LD-Schema-Graph (EN)](/guide/schema) |
| Standardwerte aus `config/seotools.php` | `config/seo.php` und [Resolver-Priorität](/de/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` im Layout | `@seo($model)`, siehe [Blade (EN)](/guide/blade) |

Statt in jedem Controller Tags zu setzen, speicherst du SEO-Daten pro Modell in `seo_meta` und lässt den Resolver sie ausgeben. Websiteweite Fallbacks werden zu [Konfigurationswerten (EN)](/reference/configuration). Statische Seiten pro Route verwenden `@seoForRoute()`.

## Von Spatie-Paketen {#from-spatie-packages}

Es gibt kein Metadatenspeicherpaket `spatie/laravel-seo`. Die häufig mit SEO kombinierten Spatie-Pakete sind ergänzende Builder, die du einzeln behalten oder ersetzen kannst:

- **`spatie/schema-org`:** Ein Fluent-Builder für JSON-LD. Rankbeam bietet einen eigenen [Schema-Graphen (EN)](/guide/schema) mit typisierten Buildern für `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` und `Organization`. Sie speichern in `seo_meta.schema_jsonld` und vermeiden doppelte Ausgabe. Bestehende Objekte kannst du mit `->toArray()` an `saveSEO(['schema_jsonld' => $array])` übergeben oder mit Rankbeams Buildern neu ausdrücken.
- **`spatie/laravel-sitemap`:** Rankbeams [Sitemap-Registry](/de/guide/sitemaps) baut darauf auf. Registriere Modelle als Quellen einer gemeinsamen Sitemap oder behalte deine vorhandene Spatie-Sitemap und deaktiviere Rankbeams Route.

Für [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO), einen weiteren Laufzeit-Builder, gilt dasselbe Muster wie bei artesaos: `setTitle`-/`addMeta`-Aufrufe werden zu `saveSEO()` oder berechneten Gettern.

## Importer erweitern {#extending-the-importer}

Hinter `seo:import-from` steht eine kleine Registry für Implementierungen von `Rankbeam\Seo\Importing\Contracts\Importer`. Neue Quellen benötigen keine Änderung am Befehl. Enthalten sind `ralphjsmit` und die [WordPress-Importer (EN)](/guide/migrate-from-wordpress) `wordpress-csv`, `yoast` und `rank-math`. Eigene Quellen registrierst du in einem Service Provider:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```
