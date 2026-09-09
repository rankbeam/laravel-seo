---
description: "Erzeuge individuelle OG-Bilder aus Blade-Templates mit Browsershot und Chrome: Vorabgenerierung, Cache, Schriften und Betriebsgrenzen."
---

# OG-Bilder erzeugen

Ab Core 3.20 deaktiviert der Chrome-Renderer JavaScript und blockiert Asset-Anfragen über HTTP(S), FTP und WebSocket. Eigene Templates müssen wie die mitgelieferten Vorlagen statisches HTML/CSS und eingebettete Assets verwenden.

Ohne individuelles Social-Bild nutzt jede Seite dieselbe `default_og_image`. Diese Funktion erzeugt pro Seite eine **eigene Open-Graph-/Twitter-Karte mit 1200×630 Pixeln** aus einem Blade-Template. Ein echter Headless-Browser über [spatie/browsershot](https://github.com/spatie/browsershot) übernimmt Umbruch, Akzente, Schrift-Fallbacks und Kürzung langer Titel. Für nichtlateinische Zeichen müssen passende Schriften vorhanden sein.

Die Funktion gehört zum kostenlosen Core und ist **standardmäßig deaktiviert**. Ohne Aktivierung bleibt `default_og_image` unverändert und die optionale Browserabhängigkeit ist nicht nötig.

::: info Karten vorab erzeugen
Ein Artisan-Befehl erzeugt Karten vorab, nicht während einer Webanfrage. Eine Seite verlinkt nur eine Datei, die auf dem Datenträger bereits existiert. Besucheranfragen starten keinen Browser. Es gibt keinen Endpunkt zur Live-Erzeugung; siehe [Einschränkungen](#caveats).
:::

## Voraussetzungen {#requirements}

Der Browser-Treiber ist optional. Installiere ihn bei Bedarf in deiner Anwendung:

```bash
composer require spatie/browsershot
```

Zusätzlich benötigt Browsershot:

- **Node.js** auf dem Host.
- **Puppeteer** im **Anwendungsstamm**, damit Node das Modul auflösen kann:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium**. Puppeteer lädt standardmäßig einen Browser herunter. Im Produktionsbetrieb kannst du über [`chrome_path`](#configuration) einen vorhandenen Chrome verwenden.

::: warning Puppeteer unter Windows im Anwendungsstamm installieren
`npm_module_path` wird an Browsershots `setNodeModulePath()` weitergereicht. Dieses verwendet ein POSIX-Präfix `NODE_PATH=…`, das unter Windows wirkungslos ist. Dort löst Node Module entlang der übergeordneten Verzeichnisse auf. Installiere Puppeteer deshalb im Anwendungsstamm; siehe [Einschränkungen](#caveats).
:::

## Aktivieren {#enabling}

Veröffentliche bei Bedarf die Konfiguration mit `php artisan vendor:publish --tag=seo-config` und aktiviere die Funktion:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Erzeuge die Karten anschließend vorab. Vor diesem Schritt wird nichts gerendert:

```bash
php artisan seo:og-images
```

## Bildauflösung {#how-resolution-works}

Generierte Karten überschreiben keine individuell gesetzten Bilder. Der Resolver ergänzt `og:image` nur, wenn der aufgelöste Wert leer ist oder noch der websiteweiten `default_og_image` entspricht. Ein individuelles Bild aus `getSEOImage()`, einer `seo_meta`-Zeile oder einem Inhaltsfeld hat Vorrang.

Die Abfrage berechnet den Speicherpfad und gibt die öffentliche URL **nur bei bereits vorhandener Datei** zurück. Sie rendert selbst nichts:

- Eine Webanfrage startet keinen Browser. Ohne Karte bleibt das statische Standardbild erhalten.
- Die Seite verlinkt keine Karte, die erst noch erzeugt werden muss.

Zwischen einer Inhaltsänderung und der verfügbaren neuen Karte liegt deshalb ein Lauf von [`seo:og-images`](#the-seo-og-images-command), etwa beim Deployment oder nach Zeitplan.

## Der Befehl `seo:og-images` {#the-seo-og-images-command}

Der Befehl erzeugt Karten für die spätere Ausgabe durch den Resolver:

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*`: Eine oder mehrere Modellklassen; mehrfach möglich. Ohne Angabe verwendet der Befehl `seo.og_image.models`, andernfalls die [Sitemap-Modelle](/de/guide/sitemaps) aus `seo.sitemap.models`, ähnlich wie `seo:llms-txt`.
- `--force`: Vorhandene Karten neu rendern, etwa nach Änderungen am Template oder an Farben ohne Erhöhung von `cache_version`.
- `--prune`: Nach der Erzeugung verwaiste Karten im konfigurierten Pfad löschen. Entfernt werden nur Dateinamen im Format generierter Inhaltshashes, keine beliebigen anderen Assets. Bei einem auf `--model` beschränkten Lauf wird diese Option ignoriert, weil die Liste zu erhaltender Karten sonst andere Modelle nicht abdecken würde.

Jedes Modell muss `HasSEO` verwenden. Datensätze ohne Titel werden übersprungen. Die Ausgabe zählt `generated`, `skipped`, `failed` und bei `--prune` auch `pruned`.

### Zeitplan {#scheduling}

Ein geplanter Lauf erzeugt aktualisierte Karten und entfernt nach Titeländerungen zurückgebliebene Dateien:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Cache-Invalidierung {#the-invalidation-model}

Der Dateiname ist ein Hash der darstellungsrelevanten Eingaben: Titel, Website-Name, Template-Name, Treiber, Maße, Verlaufsfarben, `cache_version` und installierte Paketversion.

- **Titeländerung → neuer Hash → neue Datei.** Die alte Karte wird verwaist. Bis zur nächsten Generierung fällt die Seite auf das statische Bild zurück. Der nächste Lauf erzeugt die Karte; `--prune` entfernt die alte.
- **Neue `cache_version` oder Paketversion → neue Hashes.** Erhöhe `cache_version`, wenn eine Änderung am Inhalt eines Templates alle Karten erneuern soll. Eine Paketaktualisierung wird automatisch berücksichtigt, damit Änderungen an mitgelieferten Templates nicht durch alte Karten verdeckt werden.

## Mitgelieferte Templates {#bundled-templates}

Drei Templates verwenden denselben Markenverlauf und standardmäßig 1200×630 Pixel:

| Template | Geeignet für | Inhalt |
|---|---|---|
| `seo::og.default` | Allgemeine Seiten | Titel und Website-Name |
| `seo::og.article` | Blog und Nachrichten | Rubrik, Titel, Autor und Datum |
| `seo::og.product` | Produkte und Angebote | Marke, Kategorie, Titel und Beschreibung |

Wähle das globale Template mit `seo.og_image.template` oder ordne Templates einzelnen Modellklassen zu:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Ein Modell kann außerdem `getOgImageTemplate(): ?string` definieren. Gib einen View-Namen zurück oder `null`, um die Zuordnung beziehungsweise den Standard zu verwenden. Die Priorität lautet: Modell-Hook, `templates`-Zuordnung, globales `template`.

## Templates anpassen {#customizing-the-template}

Die Karte ist eine Blade-View, standardmäßig `seo::og.default`, die ein eigenständiges HTML-Dokument erzeugt. Die mitgelieferte Schrift ist als Data-URI eingebettet und benötigt keinen Netzwerkzugriff.

**Mitgelieferte View veröffentlichen und bearbeiten:**

```bash
php artisan vendor:publish --tag=seo-views
```

Bearbeite anschließend `resources/views/vendor/seo/og/default.blade.php`.

**Oder eine eigene View wählen:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

Das Template erhält folgende Variablen:

| Variable | Typ | Bedeutung |
|---|---|---|
| `$title` | `string` | OG-Titel, andernfalls Seitentitel. |
| `$siteName` | `?string` | Aufgelöstes `og:site_name`. |
| `$fontDataUri` | `string` | Mitgelieferte Fettschrift als `data:`-URI; bei fehlender Schrift leer, dann verwendet der Browser seine Sans-Serif-Schrift. |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Ausgabebreite, standardmäßig `1200`. |
| `$height` | `int` | Ausgabehöhe, standardmäßig `630`. |
| `$locale` | `?string` | Aufgelöste Seitensprache für `<html lang>`. |
| `$author` | `?string` | Artikelautor für `seo::og.article`. |
| `$publishedDate` | `?string` | Veröffentlichungsdatum, vorformatiert als `M j, Y`, für `seo::og.article`. |
| `$section` | `?string` | Inhaltsrubrik oder Kategorie für Artikel und Produkte. |
| `$description` | `?string` | OG-Beschreibung, andernfalls Seitenbeschreibung, für `seo::og.product`. |

::: info Template-Name als Cache-Bestandteil
Template-Name und Verlaufsfarben fließen in den Hash ein. Ihr Wechsel erzeugt neue Hashes. Eine Bearbeitung unter demselben Template-Namen tut das nicht: Erhöhe anschließend `cache_version` oder verwende `--force`.
:::

## Konfiguration {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

Viele skalare Werte haben eine entsprechende Umgebungsvariable, etwa `SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH` oder `SEO_OG_IMAGE_NO_SANDBOX`. Die vollständige Liste steht in der Konfigurationsdatei. Arrays wie `templates`, `models`, `browsershot_args` und `font_stack` werden direkt dort bearbeitet.

Der Datenträger muss **öffentlich erreichbar** sein, weil seine `url()` als `og:image` ausgegeben wird. Beim Datenträger `public` erstellt `php artisan storage:link` einmalig den Verweis `public/storage`.

## Linux und die Browser-Sandbox {#running-on-linux-the-sandbox}

Wenn der Host Chromes Sandbox-Mechanismen einschränkt, kann `php artisan seo:og-images` mit folgender Meldung scheitern:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Eine mögliche Ursache sind eingeschränkte User Namespaces unter Ubuntu 23.10+. Prüfe die tatsächliche Startmeldung und die [Puppeteer-Fehlerbehebung](https://pptr.dev/troubleshooting). Bevorzuge eine Host-Konfiguration, mit der Chrome seine Sandbox behält.

**1. Ausdrückliche Ausweichlösung: `--no-sandbox`.** Diese Option deaktiviert Browserisolation. Verwende sie nur, wenn dein Deployment diese Einschränkung bewusst akzeptiert:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Rankbeam rendert statisches HTML und blockiert entfernte Assets. Diese Maßnahmen ersetzen Chromes Sandbox nicht. Führe den Renderer ohne erhöhte Rechte und getrennt von anderen Anwendungen und deren Geheimnissen aus.

**2. Sandbox beibehalten.** Lasse `no_sandbox` deaktiviert. Wenn AppArmor die Ursache ist, passe ein Profil für die genaue Chrome-Binärdatei an. Die [Chromium-Anleitung](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md) beschreibt die Zusammenhänge. Beispiel:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Lade das Profil mit `sudo apparmor_parser -r /etc/apparmor.d/chrome-og` und prüfe den Start mit aktiver Sandbox.

::: tip Weitere Argumente
Bei Containern mit wenig gemeinsamem Speicher kann Chrome während des Renderns abstürzen. Zusätzliche Argumente lassen sich über `browsershot_args` setzen:

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Eigene Treiber {#custom-drivers}

`browsershot` ist der einzige mitgelieferte Treiber. Hinter dem Renderer steht jedoch der Vertrag `Rankbeam\Seo\Contracts\OgImageRenderer`. Registriere bei Bedarf einen eigenen Canvas- oder Dienst-Renderer und wähle ihn über `seo.og_image.driver`:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

Ein Treiber wandelt nur eine eigenständige HTML-Zeichenfolge in PNG-Bytes der angeforderten Größe um. Layout und Templates gehören nicht zu seiner Aufgabe.

## Schriften und nichtlateinische Zeichen {#fonts-and-non-latin-scripts}

Noto Sans Bold (OFL) wird mitgeliefert und deckt **Lateinisch, Kyrillisch und Griechisch** ab. Chinesisch, Japanisch, Koreanisch, Thai, Arabisch, Hebräisch, Devanagari und Emoji benötigen Schriften auf dem Host, der `seo:og-images` ausführt. Eine CJK-Schrift kann über 16 MB groß sein. Mit einer geeigneten installierten Schrift verwendet Chrome seine zeichenweisen Fallbacks.

Drei Mechanismen unterstützen dies seit 3.15:

1. **Schriftfamilien in jedem mitgelieferten Template.** Der Body beginnt mit `'OGBrand'`, danach folgen `seo.og_image.font_stack` und zuletzt `sans-serif`. Der Standard enthält `Noto Sans`, vier `Noto Sans CJK`-Familien, `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`, `Noto Sans Devanagari` und `Noto Color Emoji`. Fehlende Familien werden übersprungen. Die CJK-Familie der Seitensprache steht vorne: `ja` → JP, `zh-Hans` → SC, `zh-Hant` / `zh-TW` / `zh-HK` → TC, `ko` → KR. Das berücksichtigt nationale Glyphenformen gemeinsamer Han-Codepoints. `<html lang>` trägt die Seitensprache in BCP47-Form. Der Stack gehört zum Cache-Schlüssel; Änderungen erzeugen neue Karten.

2. **Vorprüfung durch `seo:og-images`.** Der Befehl fragt fontconfig (`fc-list :lang=ja`, `th`, `ar`, …), ob Schriften die Schriftsysteme in Titel, Website-Name und Beschreibung abdecken, auch Minderheitsschriften in gemischtem Text. Er warnt einmal je Schriftsystem mit einem Installationshinweis:

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   Ohne fontconfig, etwa unter Windows, macOS oder in kleinen Containern, gibt er keine vermuteten Ergebnisse aus. Eine fehlende Schrift lässt den Renderprozess nicht automatisch fehlschlagen: Chrome kann stattdessen Kästchen zeichnen. Genau deshalb ist die Vorprüfung nötig.

3. **Glyphen-Fixtures im Live-Smoke-Test.** Mit `SEO_OG_IMAGE_LIVE_TEST=1` rendert `tests/Feature/OgImage/BrowsershotSmokeTest.php` Titel in ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he und hi sowie gleich lange Kontrolltexte aus einem nicht zugewiesenen Codepoint. Sind beide PNGs bytegleich, schlägt der Test mit Schrift- und Pakethinweis fehl. Das ist eine Stichprobe, kein Nachweis jeder Glyphe: lateinische Anteile oder anderer Umbruch können unterschiedliche Bilder ergeben, obwohl Zeichen fehlen. Prüfe tatsächliche Bilder und verwendete Schriften auf dem Zielhost. Auch die sprachbezogene FontProbe-Warnung ist keine vollständige Schriftzertifizierung. Im Core gibt es keinen Befehl `seo:doctor`; verwende `seo:og-images` für diese Vorprüfung.

Unter Debian/Ubuntu:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

Eigene vor 3.15 veröffentlichte Templates funktionieren weiter. Sie erhalten die neuen Variablen `$fontFamily` und `$lang`, dürfen sie aber ignorieren.

## Einschränkungen {#caveats}

- **Nur Vorabgenerierung, kein Live-Endpunkt.** Es gibt keine Route, die auf Anfrage eine Karte rendert. Für diese Funktion entsteht daher kein entsprechender öffentlich erreichbarer Render-Endpunkt mit URL-Signierung oder SSRF-/DoS-Schutzbedarf. Dafür musst du [`seo:og-images`](#the-seo-og-images-command) beim Deployment oder nach Zeitplan ausführen.
- **`npm_module_path` ist unter Windows wirkungslos.** Die Browsershot-Methode setzt ein POSIX-Präfix `NODE_PATH=…`. Installiere Puppeteer unter Windows im Anwendungsstamm. Unter Linux/macOS kann diese Einstellung verwendet werden.
- **Nichtlateinische Zeichen brauchen passende Host-Schriften.** Die mitgelieferte Schrift deckt nur Lateinisch, Kyrillisch und Griechisch ab. Beachte die [Schrifthinweise](#fonts-and-non-latin-scripts), Warnungen und tatsächlichen Render-Ergebnisse.
- **Fallback bei Renderfehlern.** Fehlendes Paket, Browserabsturz oder Timeout werden vom Befehl gemeldet. Die Seite behält ihr statisches `default_og_image`; ein defekter Renderer verursacht dadurch keinen Seitenfehler 500.
