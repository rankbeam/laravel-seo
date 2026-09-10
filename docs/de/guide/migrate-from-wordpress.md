---
description: "Manuell gepflegte Yoast- oder Rank-Math-Metadaten in Laravel-Modelle übernehmen: Titel, Beschreibungen, Canonicals, Robots und Fokus-Keywords. Referenz zur Feldzuordnung des Importers."
---

# Migration von WordPress {#migrating-from-wordpress}

Beim Umzug einer Inhaltswebsite von WordPress nach Laravel kann Rankbeam die von deinem Team gepflegten SEO-Metadaten übernehmen: Titel, Beschreibungen, Canonicals, Robots-Anweisungen, Fokus-Keywords und Social-Overrides aus Yoast oder Rank Math. So bleiben diese redaktionellen Angaben beim Wechsel erhalten.

::: tip Einen echten Umstieg durchführen
Diese Seite ist die *Referenz* für Feldzuordnung, Tokens und Quellschlüssel des Importers. Den schrittweisen **Ablauf** mit parallelem Betrieb, Import, Prüfung und anschließender Abschaltung beschreibt das [WordPress-Migrationshandbuch](/de/guide/wordpress-migration-runbook).
:::

Es gibt zwei Wege, beide über denselben Befehl `seo:import-from`:

| Weg | Quelle | Geeignet für |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | Eine aus WordPress exportierte Tabelle | Die meisten Agentur-Migrationen mit genauer Kontrolle über die URLs |
| [**Datenbank**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | Die WordPress-Datenbank | Umfangreiche Übernahme einschließlich Open-Graph-/Twitter-Overrides und Rank-Math-Weiterleitungen |

Beide sind **idempotent**: Ein erneuter Lauf aktualisiert dieselben Datensätze, ohne Duplikate anzulegen. Beide unterstützen **`--dry-run`** und füllen standardmäßig nur leere Felder. Bereits in Rankbeam gesetzte SEO-Daten bleiben dabei erhalten. Mit **`--overwrite`** ersetzt du vorhandene Werte durch die importierten.

## Von WordPress-Zeilen zu `seo_meta`-Datensätzen {#how-wordpress-rows-become-seo-meta-rows}

WordPress-Zeilen sind über eine **URL** oder **Beitrags-ID** identifiziert. Rankbeams `seo_meta` ist dagegen polymorph: Jeder Datensatz gehört zu einem tatsächlichen Eloquent-Modell. Der Importer ordnet deshalb jede WordPress-Zeile einem deiner Modelle zu und unterscheidet im Bericht zwischen zugeordneten Zeilen und reinen URL-Einträgen:

- **Modellzuordnung.** Wähle das Zielmodell mit `--model="App\Models\Post"`. Der **Slug** jeder Zeile, also das letzte URL-Pfadsegment oder WordPress-`post_name`, wird mit dem Modell abgeglichen. Standardmäßig dient dessen Route-Key als Vergleichsspalte; `--match-by=` wählt eine andere. Treffer werden in `seo_meta` geschrieben.
- **Nur URL.** Eine Zeile ohne passendes Modell oder ein Lauf ohne `--model` kann keinen `seo_meta`-Datensatz anlegen, weil das zugehörige Modell fehlt. Sie erscheint als übersprungen mit Grund `url-only`. Ihr Canonical kann trotzdem einen [Weiterleitungskandidaten](#redirects) ergeben.

WordPress-Beiträge und -Seiten entsprechen meist *unterschiedlichen* Laravel-Modellen. Führe den Importer deshalb je Inhaltstyp aus und grenze die Zeilen ein:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Eigene Beitragstypen werden nicht standardmäßig gelesen
Die Datenbank-Reader berücksichtigen nur die Beitragstypen **`post`** und **`page`**. Verwendet deine Website eigene Typen wie `product`, `event` oder `pathology`, musst du jeden ausdrücklich angeben. Wiederhole dafür `--post-type=`:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. CSV-Import {#_1-csv-import}

Für die meisten Agentur-Migrationen genügt CSV. Exportiere eine Zeile je URL mit folgender Kopfzeile. Die Spaltenreihenfolge ist frei; unbekannte Spalten werden ignoriert und im Bericht aufgeführt:

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Import ausführen:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Spalte | Ziel in `seo_meta` | Hinweise |
|---|---|---|
| `url` | *Zuordnungsschlüssel* | Der Slug im letzten Pfadsegment wird mit dem Modell abgeglichen. Pflichtfeld. |
| `title` | `title` | Auf 70 Zeichen gekürzt; zu lange Werte werden gemeldet. |
| `description` | `description` | Auf 160 Zeichen gekürzt. |
| `canonical` | `canonical` | Grundlage für [Weiterleitungskandidaten](#redirects). |
| `robots` | `robots` | Unverändert gespeichert, etwa `noindex, nofollow`; auf 50 Zeichen gekürzt. |
| `focus_keyword` | `focus_keywords` | Kommasepariert; das erste Keyword ist das primäre. |

Fehlerhafte Zeilen werden übersprungen und gezählt: Zeilen ohne `url` sowie solche, deren Spaltenanzahl nicht zur Kopfzeile passt.

---

## 2. Datenbankimport aus Yoast oder Rank Math {#_2-database-import-yoast-rank-math}

Ist die WordPress-Datenbank noch vorhanden, kann der Importer die SEO-Metadaten direkt lesen. Dazu gehören Open-Graph-/Twitter-Overrides und bei Rank Math auch Weiterleitungen, die bei CSV-Exporten häufig fehlen.

### Verbindung zur WordPress-Datenbank einrichten {#point-a-connection-at-wordpress}

Ergänze in `config/database.php` eine Verbindung zur WordPress-Datenbank:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Führe danach den Import aus. Das Tabellenpräfix ist standardmäßig `wp_`; mit `--table=` kannst du es ersetzen:

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

Der Reader durchläuft veröffentlichte Beiträge und Seiten in `{prefix}posts`, liest ihre Plugin-Metadaten aus `{prefix}postmeta` und gleicht den Slug `post_name` mit deinem Modell ab.

::: tip Abweichendes Tabellenpräfix
Verwaltete WordPress-Hosts verwenden häufig zufällige Präfixe, etwa `wppg_` statt `wp_`. Prüfe die Namen in den `CREATE TABLE`-Anweisungen deines Dumps und übergib das tatsächliche Präfix, zum Beispiel `--table=wppg_`. So findet der Reader `{prefix}posts` und `{prefix}postmeta`.
:::

::: tip Einen wiederhergestellten Dump unter MySQL 8 lesen
Wenn du einen WordPress-Dump unter MySQL 8+ lokal einlesen möchtest, lockere für diesen Import den strengen SQL-Modus, bevor du die `.sql`-Datei lädst. Die standardmäßigen Modi `STRICT` und `NO_ZERO_DATE` von MySQL 8 lehnen WordPress-Datumsstandards wie `'0000-00-00'` ab. Sonst scheitert bereits das Einlesen des Dumps mit `Invalid default value for 'post_date'`, bevor der SEO-Import beginnt:

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Feldzuordnung {#field-mapping}

Beide Importer ordnen Felder **ausdrücklich** zu. Gibt es für einen Schlüssel keine Spalte in Core 3, wird er als *unmapped* gemeldet; ein Zielfeld wird nicht erfunden.

| Yoast-Metaschlüssel | Rank-Math-Metaschlüssel | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Gespeichert werden nur Abweichungen von den WordPress-Standardwerten. Bei einer gewöhnlichen indexierbaren Seite bleibt `robots` null und übernimmt den Website-Standard. Yoasts getrennte Flags für `noindex`, `nofollow` und erweiterte Anweisungen wie `noarchive`, `nosnippet` und `noimageindex` werden zu einer Zeichenkette zusammengefügt. Rank Maths serialisiertes `robots`-Array wird entsprechend gelesen; `index` und `follow` entfallen als Standardwerte.

**Nicht zugeordnete Schlüssel** werden gemeldet und nicht kopiert: Bild-Anhangs-IDs (`*-image-id`), Keyword-/SEO-Bewertungen (`linkdex`, `content_score`, `rank_math_seo_score`), die Auswahl einer primären Kategorie und Rank Maths Rich-Snippet-Schema-Marker. Für Letztere bietet der [Schema-Graph](/de/guide/schema) einen typisierten Ersatz mit umfangreicheren Strukturen.

::: warning Canonicals werden unverändert importiert
Ein expliziter Canonical in `rank_math_canonical_url` oder `_yoast_wpseo_canonical` wird **genau wie gespeichert** kopiert. Zeigt er auf eine absolute URL der *alten* Domain, etwa `https://oldsite-staging.example.com/page/`, zeigt er auch nach dem Import noch dorthin. Der Importer schreibt den Host nie um. `--site-url` leitet Anfrage-*Pfade* aus absoluten URLs für [Weiterleitungskandidaten](#redirects) und die CSV-Zuordnung ab, ändert aber **keine** gespeicherten Canonical-Werte. Prüfe diese nach einem Domainwechsel und passe den Host an oder leere die Werte, damit der Resolver wieder einen Selbst-Canonical ableitet. Die meisten Seiten ohne expliziten Canonical sind davon nicht betroffen: Yoast und Rank Math erzeugen ihren automatischen Canonical erst beim Rendern.
:::

### Vorlagen-Tokens {#template-tokens}

Yoast und Rank Math speichern Titel und Beschreibungen als **Vorlagen** mit Tokens: Yoast verwendet `%%title%%`, Rank Math `%title%`. Der Importer **löst ableitbare Tokens auf** und **entfernt die übrigen**, sodass keine rohe `%%token%%`-Zeichenkette gespeichert bleibt:

| Token | Aufgelöster Wert |
|---|---|
| `%%title%%` / `%title%` | Titel des WordPress-Beitrags |
| `%%sitename%%` / `%sitename%` | Blogname aus `wp_options` beim Datenbankimport |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *Entfernt*: leerer Wert, angrenzende Trennzeichen bereinigt |

Hat ein Lauf Tokens aufgelöst, steht dies im Bericht. **Prüfe die importierten Titel** und passe diejenigen an, die von nicht ableitbaren Tokens abhingen.

---

## Weiterleitungen {#redirects}

`seo_redirects` gehört zu [Rankbeam **Pro**](/de/pro/installation). Ein Core-Importer schreibt deshalb nie direkt in diese Tabelle. Mit `--redirects-csv=` erzeugt er stattdessen eine **CSV-Datei** mit denselben Spalten wie die Pro-Weiterleitungstabelle: `source_path,target_url,status_code,note`. Diese Datei importierst du anschließend in Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Die Weiterleitungskandidaten stammen aus folgenden Quellen:

- **CSV-Import**: Zeigt `canonical` auf einen **anderen Pfad** als `url`, entsteht ein `301` vom alten Pfad zum Canonical. Ein Selbst-Canonical mit demselben Pfad wird nicht ausgegeben, da er eine Schleife ergäbe.
- **Rank-Math-Datenbank**: Aktive Regeln aus `{prefix}rank_math_redirections`. Ausgegeben werden nur **exakte Übereinstimmungen**. Regex-, contains-, start- und end-Regeln erscheinen als übersprungen, weil sie keinem einzelnen Pfad entsprechen.
- **Yoast in der kostenlosen Fassung** hat keine Weiterleitungstabelle. Diese gehört zu Yoast Premium und ist nicht Teil des hier unterstützten Schemas. Verwende für Yoast-Weiterleitungen den CSV-Weg.

Die Kandidaten sind **Vorschläge**. Prüfe die CSV und importiere sie dann mit [`seo-pro:redirects-import`](/de/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro) in Pro. Der Befehl validiert jede Zeile und weist Schleifen, unsichere Ziele und Duplikate zurück. Die CSV-Struktur ist ein stabiler Vertrag: **Weiterleitungs-CSV-Format v1**, `source_path,target_url,status_code,note`.

---

## Was der Bericht zeigt {#what-the-report-tells-you}

Ohne `--json` erscheinen eine Ergebnistabelle mit created, updated, unchanged, skipped und scanned, ein **Verification report** und folgende Prüfbereiche:

- **Verification report**: Übersicht über **matched**, also einem Modell zugeordnete Zeilen, **url-only**, also Zeilen ohne passendes Modell, sowie die Anzahl gekürzter und nicht zugeordneter Werte.
- **Truncated**: Werte, die auf die Länge einer `seo_meta`-Spalte gekürzt wurden.
- **Not imported**: Quellschlüssel mit Daten, für die Core 3 keinen Speicherort besitzt. Dazu gehört **jeder unterschiedliche `author`-Wert**. Autor ist keine gespeicherte Spalte, sondern wird über [`getSEOAuthor()`](/de/concepts/resolver-precedence) bereitgestellt. Der Bericht zeigt deshalb, welche Werte du anderweitig übernehmen musst.
- **Redirect candidates**: Anzahl der geschriebenen Kandidaten und Zieldatei.
- **Skipped rows by reason**: Reine URL-Zeilen, Beiträge ohne SEO-Metadaten und nicht exakte Weiterleitungsregeln.
- **Warnings**: Etwa der Hinweis auf aufgelöste Vorlagen-Tokens.

`--json` liefert dieselben Informationen maschinenlesbar. Der Block `verification` enthält die matched-/url-only-Zahlen und jeden Autorwert.

### Prüfen {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict` beendet den Befehl mit einem Fehlercode, sobald eine Seite einen Befund hat. Siehe [Kostenloses SEO-Audit](/de/guide/audit). Den vollständigen geordneten Umstieg mit parallelem Betrieb, Import, Prüfung und Abschaltung beschreibt das [WordPress-Migrationshandbuch](/de/guide/wordpress-migration-runbook).

---

Du kommst stattdessen von einem **Laravel**-SEO-Paket wie ralphjsmit, artesaos oder Spatie? Siehe [Migration von anderen Laravel-Paketen](/de/guide/migrate-from-other-packages).
