---
description: "Ergänze Ressourcenformulare in Filament 4 und 5 um SEO-Felder mit dem kostenlosen Paket laravel-seo-filament und dem HasSEO-Trait."
---

# SEO-Felder für Filament {#filament-admin-fields}

Das kostenlose Paket [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) ergänzt Ressourcenformulare um einen SEO-Bereich mit zwei Einbindungen je Ressource. Es unterstützt **Filament 4.x und 5.x**, also Livewire 3 und 4. Metadaten bearbeiten ist kostenlos; Scans und der im Beispiel sichtbare Score gehören zu Pro.

## Voraussetzungen {#prerequisites}

Du benötigst ein bestehendes Filament-4- oder -5-Panel und ein Modell mit dem Core-Trait `HasSEO`. Schließe zuerst den [Schnellstart](/de/guide/quickstart) einschließlich Migrationen und Tag-Ausgabe ab.

## Installation {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

Das Modell der Ressource muss `HasSEO` verwenden.

## Bereich zur Ressource hinzufügen {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## Gespeichertes Ergebnis prüfen {#check-the-saved-result}

Öffne einen vorhandenen Datensatz, trage eine SEO-Beschreibung ein, speichere und lade das Formular neu. Der Wert muss erhalten bleiben, in der Vorschau erscheinen und im englischen Panel die Quelle **Manual** zeigen. Prüfe auch den `<head>` der öffentlichen Seite.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="SEO-Felder der Merchant-Demo: Titel, Beschreibung, Canonical, Social-Bild, Vorschau und Wertquellen." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Beispiel aus der Merchant-Demo. Die Felder übernehmen dein Panel-Design. Verfügbare Bedienelemente und Textbudgets hängen von Version und Konfiguration ab.*

Der Bereich enthält:

- **Titel und Beschreibung** mit Live-Zählern. Die [Längenregel](/de/guide/multilingual#title-and-description-budgets-per-script) berücksichtigt die Schrift: 60/160 für lateinische Texte, ungefähr 30/80 für CJK, gezählt in Graphemen.
- **Fokus-Keywords:** Das Tag-Feld speichert Wörter in der Struktur `[{keyword, is_primary}]`; das erste Keyword ist primär. `getPrimaryKeyword()` und `SEOData` lesen diese Struktur. Mit `seo.keywords.enabled` melden das [kostenlose Audit](/de/guide/audit) und Pro fehlende Keywords. Standardmäßig ist dies deaktiviert; siehe [Konfiguration](/de/reference/configuration#focus-keywords).
- **Canonical-URL:** Leer bedeutet automatisch, mit entfernter Query.
- **Robots-Auswahl:** Leer verwendet die Website-Vorgabe.
- **Social-Bild:** Upload für `og:image` und `twitter:image` in `seo/` auf Filaments Standard-Disk.
- **Suchvorschau:** Folgt beim Tippen der Fallback-Kette des Resolvers.
- **Quellenanzeige:** Zeigt je Feld, ob der Wert manuell gesetzt, aus dem Inhalt, aus Modelltyp- oder globalen Vorgaben, aus der Website-Konfiguration oder aus der URL abgeleitet wurde.

## Felder beschränken {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

Erlaubt ist jede Teilmenge von `title`, `description`, `focus_keywords`, `canonical`, `robots` und `og_image`. Ohne Trait liefert `SEOFields::make(?array $only)` direkt denselben Bereich.

## Speicherung {#how-values-persist}

Der Bereich verwendet die Zustandsgruppe `seo_meta` und speichert über die Core-Beziehung `seoMeta()` per Update oder Create. Auf deinen eigenen Tabellen sind keine weiteren Spalten nötig. Die Werte werden sofort zur expliziten Ebene 6 des [Resolvers](/de/concepts/resolver-precedence).

## Mehrere Sprachen {#several-languages}

Der Core speichert [eine `seo_meta`-Zeile je Modell und Sprache](/de/guide/multilingual). Seit Filament 1.9 kannst du die veröffentlichten Sprachen angeben, um je Sprache einen Tab zu erhalten:

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

Oder einmal in der Paketkonfiguration für alle Ressourcen:

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

Jeder Tab bearbeitet eine eigene Zeile und hat eigene:

- **Zähler** für die Schrift der Sprache: ein leerer japanischer Titel zeigt `0 / 30`, ein englischer `0 / 60`.
- **Vorschauen** für Suchergebnisse und Social Cards aus den aufgelösten Werten dieser Sprache.
- **Fallback-Anzeigen** für diese Zeile.
- **Badges** mit der Zahl gesetzter Felder, damit leere Übersetzungen auffallen.

Mit `ext-intl` erscheint der Sprachname in der Panel-Sprache, sonst der Code. Alle Tabs werden gemeinsam validiert und gespeichert. Für eine Sprache ohne Eingaben wird keine Platzhalterzeile angelegt.

::: details Eigene Zustandsbindungen
Bei mehreren Sprachen lautet der Pfad `seo_meta.{locale}.title`, bei einer Sprache weiterhin `seo_meta.title`. Verwende in eigenen Formularaktionen den passenden Pfad.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Englische, italienische und japanische Tabs in der Merchant-Demo; der leere japanische Tab zeigt Budgets von 30 und 80." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Merchant-Demo vom 9. September 2026 mit `locales: ['en', 'it', 'ja']`. Der japanische Tab verwendet eigene Zähler. Der englische Titel stammt aus dem Modellinhalt: Ein Sprach-Tab übersetzt keine Inhalte. Der Pro-Score oberhalb der Felder stammt aus dem letzten Scan des Datensatzes, nicht aus einem Scan je Sprache.*

### Mit einem Übersetzungsplugin {#with-a-translatable-plugin}

Für `lara-zeus/spatie-translatable` **1.x mit Filament 4** oder **2.x mit Filament 5** verwende Rankbeams Seitenadapter für Edit und Create. Ersetze nur die Trait-Imports der Seiten. Behalte die Resource- und List-Traits, das Panel-Plugin und die Aktion `LocaleSwitcher` bei:

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

Jede Seitenklasse deklariert weiterhin `use Translatable;`. Das Plugin bleibt eine optionale Anwendungsabhängigkeit. Verwende eine aktuelle gepatchte Version. Die lokale Integrationsfixture prüft Plugin 1.0.4 / Filament 4.13.1 sowie Plugin 2.0.1 / Filament 5.8.1.

Beim Sprachwechsel bleiben ungespeicherte Entwürfe für Inhalt, SEO und strukturierte Daten im Editor erhalten. Speichern validiert alle besuchten Sprachen und speichert sie in einer Datenbanktransaktion. Bei einem Validierungsfehler öffnet sich die betroffene Sprache. Uploads werden beim Speichern abgelegt. Verlassen oder Neuladen der Seite verwirft ungespeicherte Entwürfe. Fehlende Inhalte werden nicht automatisch übersetzt.

Die Adapter erhalten die üblichen Vorher-/Nachher-Hooks und Formulardaten-Mutatoren. Überschreibt deine Seite `handleRecordCreation()`, `handleRecordUpdate()`, `callHook()` oder Transaktionsmethoden, integriere das Adapterverhalten in diese Anpassung und teste den Speichervorgang. Datenbanktransaktionen machen Dateisystemschreibvorgänge nicht rückgängig; behalte deine Bereinigung verwaister Dateien bei.

Für eigene Live-Textfelder unter Livewire 3 sind `->live()` oder `->live(onBlur: true)` einem expliziten Debounce vorzuziehen. Letzterer verzögert den lokalen Zustand und kann bei schnellem Sprachwechsel die letzten Zeichen verlieren. Rankbeams Titel- und Beschreibungsfelder nutzen den Standard-Debounce für Requests.

Die ursprünglichen Plugin-Seiten-Traits befüllen Formulare beim Wechsel neu. Rankbeam verhindert versehentliche Metadaten-Schreibvorgänge, aber diese Traits bewahren keine SEO-Entwürfe. Stelle Edit/Create deshalb auf die Adapter um. Explizite `locales:`-Tabs bleiben ein gemeinsamer Editor und haben Vorrang vor dem Seiten-Sprachwechsel.

Ohne explizite Sprachenliste oder Seitensprache bearbeitet der Bereich die Anwendungssprache.

## Strukturierte Daten: schema.org {#structured-data-schema-org}

Ein optionaler Bereich ermöglicht JSON-LD-Eingaben ohne eigenen Code. Ergänze ihn neben dem SEO-Bereich:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

Ohne Trait kannst du `SEOSchemaFields::make()` direkt verwenden.

Der Bereich schreibt in `seo_meta.schema_jsonld`, dieselbe Spalte wie der [Schema-Renderer](/de/guide/schema). Er bindet nur das Formular an den Core: Dokumente werden von dessen Buildern erstellt und vor dem Speichern durch `SchemaValidator` geprüft.

Enthalten sind:

- **Automatischer Breadcrumb:** Ein Schalter erzeugt `BreadcrumbList` aus der Vorfahrenkette des Modells über `BreadcrumbSchema::fromModelAncestors()`, ohne weitere Eingaben.
- **Schema-Blöcke:** Ein Repeater für FAQ-Paare als `FAQPage` oder Product mit Name, Beschreibung, Bild, Marke, SKU, Preis, Währung und Verfügbarkeit. Er verwendet `FAQSchema` und `ProductSchema` des Core.

### Validierung {#validation}

Ein ungültiger Block wird beim Speichern mit der Core-Validierungsmeldung abgewiesen, etwa eine FAQ ohne Antwort oder ein Product ohne Bild oder Angebot. Dies sind Anforderungen des hier verwendeten Builders, keine vollständige Beschreibung der Google-Anforderungen für alle Product-Suchfunktionen. Vollständig leere Blöcke werden ignoriert.

### Gespeicherte Daten {#what-it-stores}

`schema_jsonld` enthält bei einem Dokument ein Objekt, bei mehreren ein JSON-Array: zuerst Breadcrumb, dann weitere Blöcke. Beide Formen sind gültiges JSON-LD und werden unverändert durch `@seo` oder `renderSchema()` ausgegeben.

### Nicht vom Formular verwaltete Schemas {#schema-it-doesn-t-manage}

Selbst geschriebene Schemas, die das Formular nicht abbildet, bleiben unverändert: ein eigener `@graph`, ein anderer `@type` oder ein Product mit nicht angebotenen Feldern wie Rezensionen, Bewertungen und GTIN/MPN. Öffnen und Speichern überschreibt diese Dokumente nicht.

## Fehlerbehebung {#troubleshooting}

- **Gespeichertes Feld fehlt auf der Seite:** Prüfe `@seo($model)` für denselben Datensatz und dieselbe Sprache.
- **Das Feld verwendet weiterhin einen Fallback:** Prüfe, ob in der aktiven Sprache ein expliziter Wert gespeichert ist. Die Quellenanzeige nennt die wirksame Ebene.
- **Ein Sprach-Tab fehlt:** Prüfe `locales:`, Paketkonfiguration und Seitenauswahl anhand der oben beschriebenen Priorität.

::: details Eigenes Panel in Testbench prüfen
Registriere bei Filament in orchestra/testbench den `SupportServiceProvider` vor dem `LivewireServiceProvider`. Filament bindet Livewires `DataStore` neu; die falsche Reihenfolge führt zu `ViewErrorBag::put(): ... null given`. Normale Anwendungen erhalten durch Package Discovery die richtige Reihenfolge.
:::
