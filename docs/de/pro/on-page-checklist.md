---
description: "Eine Onpage-Checkliste mit Fokus-Keyword und Ampelstatus: prüfe Titel, URL, Einleitung, Meta-Beschreibung, Textlänge, Bilder und Lesbarkeit."
---

# Die Onpage-Checkliste – Keyword-Prüfungen mit Ampelstatus {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

Die Onpage-Checkliste bietet den redaktionellen Ablauf, den Nutzer von RankMath oder Yoast kennen: Du wählst ein Fokus-Keyword und erhältst Prüfungen mit Ampelstatus zur Frage, wie die Seite darauf abgestimmt ist. Geprüft werden das Keyword in Titel, URL, Einleitung und Meta-Beschreibung sowie Textlänge, Bilder, interne Links und **Lesbarkeit**.

Sie läuft **innerhalb der Anfrage**, ohne Queue und ohne Netzwerkzugriff. Grundlage sind das Modell, der [Resolver](/de/concepts/resolver-precedence) und der Seitentext. Das Ergebnis ist bewusst **keine Punktzahl**.

::: tip Checkliste ≠ Score
Die Checkliste verwendet ausschließlich **pass / warn / fail** und ist vollständig vom [Pro-SEO-Score](/de/pro/scoring) getrennt. Sie verwendet keine Codes aus dessen Bewertungsschema und kann den Score nicht verändern. Redaktionelle Hinweise bleiben von der numerischen Bewertung getrennt. Insbesondere Keyword-Dichte und Lesbarkeit sind **Hinweise**, keine Bewertungsvorgaben; siehe unten.
:::

## Was geprüft wird {#what-it-checks}

| Prüfung | Gruppe | Geprüfter Sachverhalt |
|---|---|---|
| `keyword_in_title` | keyword | Das Fokus-Keyword kommt im SEO-Titel vor. |
| `keyword_in_description` | keyword | Das Fokus-Keyword kommt in der Meta-Beschreibung vor. |
| `keyword_in_url` | keyword | Das Fokus-Keyword kommt im URL-Slug vor. |
| `keyword_in_first_paragraph` | keyword | Das Fokus-Keyword kommt im ersten Absatz vor. |
| `keyword_density` | keyword | **Hinweis.** Die Wiederholung sollte natürlich wirken; es gibt keinen Zielwert, siehe unten. |
| `title_length` | meta | Der Titel liegt im selben Längenbereich wie im Editor und Scan: 30–60 für lateinischen Text, ungefähr 15–30 für CJK. Grundlage ist die [Längenrichtlinie](/de/guide/multilingual#title-and-description-budgets-per-script) des Core-Pakets (Pro 2.33). |
| `description_length` | meta | Die Beschreibung liegt ebenfalls im gemeinsamen Bereich: 70–160 für lateinischen Text, ungefähr 35–80 für CJK. |
| `content_length` | content | Der Text erreicht die konfigurierten Wortzahlbereiche. |
| `readability` | content | **Hinweis.** Geschätzte Lesbarkeit mit der gewählten Formel für zehn Sprachen, LIX als Fallback oder einer gekennzeichneten Heuristik ohne Punktzahl für Japanisch, Chinesisch und Koreanisch. |
| `has_image` | media | Der Inhalt enthält mindestens ein Bild. |
| `internal_links` | links | Der Inhalt verlinkt verwandte interne Seiten. |

Ohne Fokus-Keyword werden die Keyword-Prüfungen **übersprungen**. Sie bestehen weder noch schlagen sie fehl; die Checkliste fordert dich auf, ein Keyword hinzuzufügen. Verwende dazu das [Fokus-Keyword-Feld](/de/guide/filament) oder `saveSEO(['focus_keywords' => …])`.

### Keyword-Abgleich {#keyword-matching}

Keyword und Text werden nach **Case-Folding und Stemming** verglichen. Dadurch passt „espresso grinder“ auch zu „espresso grinders“. Mit der passenden Analysesprache stimmen außerdem das türkische „İstanbul“ und „istanbul“, das griechische „ΟΔΟΣ“ und „οδος“ sowie das deutsche „Straße“ und „STRASSE“ überein (über den `CaseFolder` des Core-Pakets). Gib die Analysesprache mit `SeoPro::checklistFor($post, 'it')` oder `--locale=it` an.

Seit Pro 2.36.1 verwenden Keywords, Synonyme und Feldtexte vor dem Stemming denselben Tokenizer. Treffer müssen aus aufeinanderfolgenden **vollständigen Tokens** bestehen: `cat` passt nicht zu `education`. Japanische Ausdrücke verwenden dieselben ICU-Wortgrenzen wie der Text. Apostrophe und Bindestriche trennen Tokens; deshalb passt `meta-tag` zu `meta tag`, und gerade sowie typografische Apostrophe verhalten sich gleich. Kombinierende Zeichen bleiben an ihren Buchstaben. Case-Folding erhält Akzente; der jeweilige sprachspezifische Stemmer kann zusätzliche Kürzungen vornehmen.

Die Zählung wählt an jeder Position den längsten passenden Keyword- oder Synonym-Ausdruck und zählt diesen Abschnitt einmal. Doppelte Synonyme und überlappende kürzere Alternativen erhöhen die Dichte nicht. Beispiel: Das Keyword `seo` mit Synonym `seo tools` kommt in `seo tools seo` zweimal vor. Für wörterbuchbasierte Wortgrenzen in Schriften ohne Leerzeichen bleibt ICU erforderlich; der Regex-Fallback kann diese Grenzen nicht bestimmen.

Seit Pro 2.37 verwendet das Stemming eine **mitgelieferte Teilmenge von Snowball 3.1.1**. Dafür ist kein zusätzliches Composer-Paket nötig, und zur Laufzeit wird nichts heruntergeladen. PHP 8.2 wird weiterhin unterstützt.

| Engine | Verwendung | Sprachen |
| --- | --- | --- |
| `snowball` | Standard; vorhandene Einstellungen mit `auto` wählen dieselbe mitgelieferte Engine | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | Ausdrücklich über `seo-pro.checklist.analysis.stemmer = builtin` | Nur Englisch mit dem bisherigen einfachen Flexions-Stemmer; andere Sprachen verwenden Identitätsabgleich |
| `identity` | Nicht unterstützte Sprache oder ausdrücklich gewählter Modus `none` | Ukrainisch, Japanisch, Chinesisch, Koreanisch, Thai und weitere Sprachen außerhalb der mitgelieferten Teilmenge |

Beide Seiten eines Vergleichs verwenden dieselbe Engine. Stemming reduziert Endungen; es ist weder ein Synonymwörterbuch noch eine Garantie sprachlicher Gleichwertigkeit. Der griechische Algorithmus kann beispielsweise Formen mit und ohne Akzent zusammenführen, die der Identitätsabgleich getrennt hält. Die Grenzen vollständiger Tokens verhindern weiterhin, dass `cat` zu `education` passt.

#### Upgrade von Pro 2.36 {#upgrading-from-pro-2-36}

Eine vorhandene `auto`-Konfiguration verwendet jetzt immer die mitgelieferten Algorithmen – unabhängig davon, ob `wamania/php-stemmer` installiert ist. Prüfe redaktionelle Empfehlungen nach dem Upgrade erneut: Aktualisierte Algorithmen können Treffer verändern; Türkisch, Griechisch, Polnisch und Tschechisch erhalten jetzt Stemming. Die zusätzlichen Algorithmen des optionalen Wrappers für Katalanisch, Dänisch, Finnisch, Norwegisch, Rumänisch und Schwedisch gehören nicht zur mitgelieferten Teilmenge. Diese Sprachen verwenden jetzt Identitätsabgleich.

Setze `SEO_PRO_CHECKLIST_STEMMER=builtin` für den bisherigen rein englischen Fallback oder `none` für Identitätsabgleich nach Case-Folding in allen Sprachen. Erstelle nach der Änderung den Konfigurationscache neu. Diese Einstellungen bilden die mehrsprachigen Algorithmen des früheren optionalen Wrappers nicht nach. Wenn du exakt dessen Ergebnisse beibehalten musst, ist dafür die vorherige Pro-Version erforderlich. Gespeicherte SEO-Metadaten werden nicht umgeschrieben.

Der mitgelieferte Adapter besteht auf PHP 8.2, 8.3 und 8.4 die Prüfung von 600.395 festgeschriebenen offiziellen Wort-/Ausgabe-Paaren. Das belegt Algorithmuskonformität, keine muttersprachliche redaktionelle Freigabe. Quell-Hashes, die rein syntaktische Anpassung an PHP 8.2 und die Upstream-Lizenzen werden mitgeliefert. Siehe `THIRD-PARTY-NOTICES.md` in der Quelldistribution.

### Wortsegmentierung {#word-segmentation}

Wortzahl, Keyword-Dichte und Lesbarkeitsstatistiken benötigen Wortgrenzen. Bei Schriften mit Leerzeichen verwendet ein regulärer Ausdruck stabile Grenzen zwischen Buchstaben-/Ziffernfolgen. Chinesisch, Japanisch und Thai brauchen wörterbuchbasierte Segmentierung; ein regulärer Ausdruck kann dort einen ganzen Absatz als ein einziges „Wort“ erfassen. Ist **ext-intl** geladen, übergibt der Tokenizer diese Textabschnitte an den wörterbuchbasierten ICU-Break-Iterator (`IntlBreakIterator::createWordInstance`). Dieser zerlegt etwa 東京タワーは東京のランドマークです in Wörter. Fehlt ICU, ist es deaktiviert oder lässt es sich nicht initialisieren, überspringt Pro die betroffenen Prüfungen zu Inhaltslänge, Lesbarkeit und Keywords mit einem Installations- oder Konfigurationshinweis. Eine unzuverlässige Zählung wird nicht als Fehler gewertet. Unabhängige Prüfungen wie Titellänge und Keyword-Abgleich in Schriften mit Leerzeichen laufen weiter. `seo-pro.checklist.analysis.segmenter = regex` erzeugt bei Text, der Wörterbuchsegmentierung braucht, denselben Nichtverfügbarkeitsstatus.

Der Block `analysis` enthält `word_count_status` (`available` oder `unavailable`) und `segmentation_reason` (`null`, `missing_intl`, `disabled` oder `initialization_failed`). Der zugrunde liegende Tokenizer behält aus Kompatibilitätsgründen Fallback-Tokens bei. Prüfe diesen Status, bevor du sie als Wörter interpretierst.

Der Scan gerenderter Seiten gibt den unbewerteten Hinweis `word_segmentation_unavailable` aus, statt dünnen Inhalt festzustellen. Ein zuvor bestätigter Thin-Content-Befund bleibt offen, bis er erneut geprüft werden kann. Dieser unvollständige Scan aktualisiert den Seiten-Score nicht: Ein vorhandener Score behält sein ursprüngliches `scored_at`; bei einem ersten Scan bleibt die Seite ohne Score, bis die Segmentierung funktioniert. Installiere PHP `ext-intl`, aktiviere den Segmentierer `auto` und starte einen neuen Scan, um die Prüfungen fortzusetzen.

### Welche Engines die Seite analysiert haben {#which-engines-analysed-the-page}

Jede Checkliste enthält einen Block `analysis`: die vorherrschende Schrift des Textes, den Tokenizer (`intl` / `regex`), den Stemmer (`snowball` / `builtin` / `identity`) und die Lesbarkeitsmethode (`formula` / `heuristic` / `lix`). Du findest ihn in `toArray()` und `--json`, als Fußzeile im Filament-Dialog und als letzte Zeile von `seo-pro:checklist`:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

Die Fußzeile nennt die tatsächlich verwendete Engine, einschließlich Regex-Segmentierung ohne ext-intl und Identitätsabgleich bei deaktiviertem Stemming.

### Keyword-Dichte dient als Hinweis {#keyword-density-is-advisory}

Die Checkliste definiert keine ideale Keyword-Dichte für das Ranking. Diese Prüfung ist ein **Hinweis**: Sie zeigt die Anzahl zur Orientierung, schlägt nie fehl und **bestimmt niemals den Gesamtstatus der Seite**. Prüfe, ob Wiederholungen natürlich klingen, statt auf einen Prozentsatz hinzuarbeiten.

### Lesbarkeit dient als Hinweis {#readability-is-advisory}

Die Checkliste schätzt die Lesbarkeit mit der für die Analysesprache gewählten Methode. Implementiert sind derzeit folgende Formeln und Fallbacks:

| Sprache | Formel | Quelle |
| --- | --- | --- |
| Englisch (`en`) | Flesch Reading Ease | Flesch 1948 |
| Italienisch (`it`) | Gulpease-Index | Lucisano & Piemontese 1988 |
| Spanisch (`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| Französisch (`fr`) | Kandel-Moles | Kandel & Moles 1958 |
| Deutsch (`de`) | Erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| Portugiesisch (`pt`, `pt_BR`) | An brasilianisches Portugiesisch angepasste Flesch-Formel | Martins et al. 1996 |
| Niederländisch (`nl`) | Flesch-Douma | Douma 1960 |
| Russisch (`ru`) | Obornevas Flesch-Anpassung | Оборнева 2006 |
| Türkisch (`tr`) | Ateşman | Ateşman 1997 |
| Polnisch (`pl`) | Pisarek (normalisierter Index der Schuljahre) | Pisarek 1969 |
| Japanisch, Chinesisch, Koreanisch (`ja`, `zh`, `ko`) | **Heuristik ohne Punktzahl**, siehe unten | — |
| Griechisch, Ukrainisch, Tschechisch (`el`, `uk`, `cs`) | LIX als Fallback, weil hier keine eigene Formel implementiert ist; nicht für diese Sprachen kalibriert | Björnsson 1968 |
| Alle übrigen Sprachen | LIX (Läsbarhetsindex), unkalibrierter Fallback | Björnsson 1968 |

Die angezeigte **Skala von 0–100 mit höheren Werten für leichtere Texte** ist eine Konvention des Pakets. Ergebnisse der Flesch-Familie und Gulpease werden auf diesen Bereich begrenzt; Wiener Schulstufen sowie Pisarek- und LIX-Indizes werden darauf abgebildet. Gleiche Werte in verschiedenen Sprachen bedeuten **nicht** dieselbe Leseschwierigkeit. Die Formelkoeffizienten stammen aus veröffentlichten Arbeiten. Rankbeams Schätzungen von Tokens, Sätzen und Silben wurden jedoch nicht als vollständiges Messinstrument validiert. Sie sagen weder Textverständnis noch Suchrankings voraus.

Seit Pro 2.37.1 werden benachbarte Vokale im Türkischen und Russischen als getrennte Silben gezählt (`saat`: 2; `поэт`: 2). Andere Silbenschätzer haben weiterhin Grenzen: Vokalgruppen erfassen nicht jeden Hiatus und jeden stummen Vokal korrekt. Für Englisch gibt es eine kleine Ausnahmeliste, aber kein Aussprachewörterbuch. Beispielsweise können das spanische `país` und das französische `monde` falsch gezählt werden. Prüfe ungewöhnliche Wörter und Eigennamen selbst.

#### Textstatistiken und API-Grenzen {#text-statistics-and-api-limits}

HTML-Blockelemente und `br` trennen Text; Inline-Hervorhebungen bleiben mit ihrem Wort verbunden. Zeilenumbrüche im Quelltext gewöhnlichen HTMLs werden zu Leerzeichen, während Klartext und `pre` ihre Zeilengrenzen behalten. Inhalte von script, style und noscript werden ausgeschlossen. Die Extraktion wertet weder CSS-Sichtbarkeit noch die gerenderte Seite aus. Entities werden einmal dekodiert. Für die Formelstatistik zählen Buchstaben-/Ziffernfolgen als Wörter, reine Satzzeichen dagegen nicht. Bindestriche und Apostrophe trennen Wörter. Ziffern zählen als Tokens, erhalten aber keine geschätzten Silben. Buchstaben werden im Originaltext gezählt; Stemming oder deutsches Case-Folding von `ß` zu `ss` verändern diese Länge nicht.

Die Satzschätzung trennt an abschließenden `. ! ? 。 ！ ？` sowie Block- und Zeilengrenzen. Sie berücksichtigt auch ein letztes Fragment ohne Schlusszeichen und schützt Dezimalzahlen sowie eine kleine Liste üblicher Abkürzungen (`Dr.`, `Prof.`, `e.g.` und ähnliche englische Formen). Überschriften und Listeneinträge können deshalb als Sätze zählen. Andere Abkürzungen, Zitate, Zahlen, gemischte Schriften und Texte mit wenigen Satzzeichen verlangen besondere Aufmerksamkeit. Die gewählte Sprache bestimmt die Methode; sie erkennt nicht, ob jeder Satz tatsächlich in dieser Sprache geschrieben ist.

`toArray()` des direkt verwendeten Rechners ergänzt einen Block `assessment`:

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` unterscheidet `formula`, `lix`, `heuristic` und `unavailable`. Manuell erstellte Ergebnisse ohne Formelmetadaten verwenden `unspecified`. Leere Eingaben oder reine Satzzeichen erhalten `insufficient`; `isValid()` ist dann false. Ihr aus Kompatibilitätsgründen beibehaltener `score: 0` bedeutet „nicht verfügbar“, keine Leseschwierigkeit. Die vorhandenen englischen und italienischen Schulstufenbezeichnungen sind Näherungen. Andere Sprachen sowie Heuristik- und LIX-Ergebnisse erhalten diese Schulstufen nicht mehr. `calculateFleschKincaid()` behält aus Kompatibilitätsgründen seinen öffentlichen Methodennamen, berechnet aber **Flesch Reading Ease**, nicht die Flesch-Kincaid-Schulstufe.

Die Formeltests verwenden unabhängig gezählte Eingaben und festgelegte Rechenergebnisse für alle zehn benannten Formeln sowie LIX. Sie prüfen das Berechnungsverhalten, keine muttersprachliche redaktionelle Qualität. Die Lesbarkeit bleibt vom Pro-SEO-Score getrennt.

::: warning Japanisch, Chinesisch und Koreanisch: gekennzeichnete Heuristik ohne Punktzahl
Für diese Sprachen verwendet Rankbeam eine Methode ohne Punktzahl. Der Rechner liefert eine **Stufe** anhand paketeigener Faustregeln: durchschnittliche Satzlänge in Zeichen (ja ≤ 40/60/80, zh ≤ 30/45/60) beziehungsweise Wörtern (ko ≤ 12/18/25). Bei Japanisch kommt der Kanji-Anteil hinzu; über ungefähr 45 % wird der Text in den Schwierigkeitsstufen des Pakets eine Stufe höher eingeordnet. Das Ergebnis ist mit `heuristic: true` und einer **Punktzahl von null** gekennzeichnet. Die Checklistenmeldung nennt die Heuristik ausdrücklich. Für diese Sprachen bleibt die Prüfung **unabhängig von `readability.advisory` ein Hinweis** und beeinflusst nie den Gesamtstatus. Checklisten-Wortzahlen für `ja` und `zh` erfordern funktionierende ICU-Segmentierung; ohne sie werden diese Prüfungen übersprungen.
:::

Wie die Keyword-Dichte ist Lesbarkeit **standardmäßig ein Hinweis**. Sie informiert den Autor, bestimmt aber **nicht** den Gesamtstatus der Seite – ähnlich der Trennung zwischen Lesbarkeits- und SEO-Analyse bei Yoast. Unter einer Mindestwortzahl wird die Prüfung **übersprungen**. Zu wenig Inhalt ist Aufgabe von `content_length`, nicht der Lesbarkeitsprüfung. Wenn schwer lesbare Seiten die Checkliste nicht bestehen sollen, kannst du die Prüfung verbindlich machen:

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## Die Checkliste auslesen {#reading-the-checklist}

### Ohne Oberfläche {#headless}

Pro 2.36 liest aufgelöste Metadaten, `getContentForSEO()` und Fokus-Keywords in der angeforderten Inhaltssprache. Die Beschriftungen der Checkliste bleiben in der Sprache des Bedieners. Ohne ausdrückliche Sprachangabe gilt der Standard von `seoData()` des Übersetzungsmodells. Die Filament-Aktion folgt dem Sprach-Tab ihres Feldes oder dem Sprachumschalter der Seite.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Jedes `CheckResult` enthält `id`, `group`, `label`, `status`, `message`, eine optionale `recommendation` und das Flag `advisory`.

### Befehl {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### Im Editor (Filament, optional) {#in-the-editor-filament-optional}

Wenn [`rankbeam/laravel-seo-filament`](/de/guide/filament) installiert ist, erscheint am Fokus-Keyword-Feld die Aktion **On-page checklist**. Ein Klick öffnet einen Dialog mit denselben pass/warn/fail-Prüfungen für den gespeicherten Inhalt des Datensatzes. Das Filament-Paket hängt niemals von Pro ab. Die Aktion wird über denselben einseitigen Erweiterungs-Hook eingebunden wie die KI-Vorschläge; Installationen ohne Oberfläche sind davon unberührt.

## Konfiguration {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Eine eigene Prüfung schreiben {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Registriere die Klasse in `seo-pro.checklist.rules`. Eine Prüfung **darf keine ID eines [Scan-Befundcodes](/de/pro/scan-issues) wiederverwenden**. Die Checkliste verwendet einen eigenen Namensraum ohne Einfluss auf den Score.

## Wie der Inhalt gelesen wird {#how-the-content-is-read}

`SeoPro::checklistFor($model)` analysiert:

- **Titel und Beschreibung**: die *aufgelösten*, effektiven Werte. Es sind dieselben Werte, die Editor-Zähler und Scan messen; die Checkliste verwendet damit dieselbe Grundlage.
- **Inhalt**: `$model->getContentForSEO()`, der `HasSEO`-Accessor des Core-Pakets. Standardmäßig liest er `content`, `body` oder `text`. Überschreibe ihn auf deinem Modell, um den tatsächlichen Haupttext zu liefern.
- **URL**: `$model->getUrlForSEO()`.
- **Fokus-Keywords**: die gespeicherten Werte aus `seo_meta.focus_keywords`.

Die Analyse ruft keine Seite ab und schreibt keine Daten.
