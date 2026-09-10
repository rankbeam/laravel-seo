---
description: "Mehrsprachige Inhalte in Rankbeam: Textlängen je Schriftsystem, Grapheme, Großschreibung, hreflang, inLanguage, Suchcrawler, Schriften und Unicode-URLs."
---

# Mehrsprachige Inhalte {#multilingual-content}

[Übersetzungen](/de/guide/translations) bestimmen die Sprache der Paketoberfläche. Diese Seite beschreibt, wie Rankbeam die **Sprache deiner Inhalte** berücksichtigt. Japanische Titel brauchen andere Längenbudgets als lateinische, Thai lässt sich nicht an Leerzeichen kürzen, `İstanbul` und `istanbul` entsprechen einander im Türkischen, und `it_IT` ist kein gültiger hreflang-Wert. Diese Regeln liegen im Core, damit alle Pakete dieselbe Grundlage verwenden.

Standardwerte und Richtlinien stehen in `config/seo.php`. Einige Funktionen benötigen Laufzeitabhängigkeiten wie ICU zur Wortsegmentierung oder installierte Schriften. Übersetzte Inhalte muss deine Anwendung liefern.

## Inhaltssprache und Oberflächensprache {#content-locale-and-interface-locale}

Core 3.17, Filament 1.11 und Pro 2.36 reichen die gewählte Inhaltssprache an Metadaten, berechnete Hooks, Vorschau-URLs, Keyword-Prüfungen und KI-Anfragen weiter. Ein englisches Panel kann italienische und japanische Inhalte bearbeiten, ohne seine Beschriftungen zu ändern.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

Diese Aufrufe wählen die Metadatenzeile der jeweiligen Sprache und führen Hooks wie `getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()` und `getSEOSchema()` in einem vorübergehenden Sprachkontext aus. Modell- und Anwendungssprache des Aufrufers bleiben erhalten, auch bei einer Exception. Modelle mit Spaties `setLocale()` und `getTranslatableAttributes()` erhalten ebenfalls eine isolierte Instanzsprache. Deine Hooks müssen weiterhin übersetzte Inhalte zurückgeben: Rankbeam übersetzt gewöhnliche Datenbankattribute nicht automatisch.

Die modellbezogenen KI-Methoden und die Massenbefüllung von Pro akzeptieren ausdrücklich `locale:`. Ohne diesen Parameter bestimmt der überschriebene Standard von `seoData()` eines Übersetzungsmodells die Inhaltssprache; andernfalls gilt die Anwendungssprache. Filament-Aktionen erhalten die Sprache ihres Feldes, auch im einsprachigen Editor und im Folgemodus. Speichere die gewählte Sprache in eigenen Queue-Jobs und übergib sie bei der Ausführung ausdrücklich. Die aktuelle Worker-Sprache ist keine verlässliche Quelle.

Für synchrone eigene Leser übergibt `ModelLocale::run($model, $locale, $callback)` ein isoliertes Modell an den Callback und stellt die Anwendungssprache in `finally` wieder her. Schließe alle sprachabhängigen Lesezugriffe im Callback ab. Ein zurückgegebener Lazy Iterator oder eine Closure verlängert diesen Kontext nicht.

## Budgets für Titel und Beschreibungen je Schriftsystem {#title-and-description-budgets-per-script}

Rankbeam verwendet redaktionelle Budgets von 60/160 Graphemen für lateinische Titel/Beschreibungen und 30/80 für CJK. Das sind konfigurierbare Näherungen, keine Pixelmessungen oder Garantien für die vollständige Anzeige in Suchmaschinen. Google schreibt weder für [Titellinks](https://developers.google.com/search/docs/appearance/title-link) noch für [Meta-Beschreibungen](https://developers.google.com/search/docs/appearance/snippet) eine feste Zeichengrenze vor; die Anzeige kann an die Gerätebreite angepasst und gekürzt werden.

`Rankbeam\Seo\I18n\LengthPolicy` bestimmt das Budget für den jeweiligen Text:

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

Editor-Warnungen (`SEOWarningEvaluator`), `seo:audit`, das Kürzen berechneter Beschreibungen, Pro-Scan und Filament-Zähler lesen dieselbe Richtlinie. Warnungen prüfen aufgelöste Werte einschließlich Titelsuffix; der Editor kann zugleich noch ungespeicherten Text zeigen. Gezählt werden **Graphemcluster**, nicht Bytes oder Codepoints. Ihre Grenzen folgen der installierten Unicode-Implementierung. Sie messen weder Silben noch die Pixelbreite eines Suchergebnisses.

Die Einträge in `seo.length_policy` sind nach Schriftsystem gruppiert: `latin`, `cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew`, `devanagari`. Nicht angegebene Gruppen verwenden `default`. Einzelne Schlüssel können andere Werte überschreiben und den Rest erben:

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Standardmäßig weicht nur `cjk` ab. Auch eine aktualisierte Installation mit älterer veröffentlichter Konfiguration erhält den eingebauten CJK-Eintrag.

::: tip Gemischte Titel
Die Erkennung gewichtet Buchstaben; ein CJK-Zeichen zählt doppelt. „Laravel SEO の完全ガイド“ wird CJK zugeordnet, „Laravel SEO for the 東京 developer“ bleibt lateinisch. Texte ohne Buchstaben, etwa Jahreszahlen oder Preise, verwenden das Schriftsystem der Seitensprache.
:::

`SEOWarningEvaluator::TITLE_MAX_LENGTH` und `DESCRIPTION_MAX_LENGTH` bleiben als lateinische Standardwerte für bestehenden Code erhalten.

## An Graphemgrenzen kürzen {#grapheme-safe-script-aware-truncation}

Das lateinische Budget einer berechneten Beschreibung (`seo.computed.description_max_length`) wird durch die Richtlinie skaliert; CJK erhält die Hälfte. `Rankbeam\Seo\I18n\Truncator` kürzt anschließend:

- Bei Text mit Wortzwischenräumen an der letzten Wortgrenze innerhalb des Limits, sofern sie mindestens 60 % des Limits erreicht. Keine Auslassungspunkte; abschließende Satzzeichen werden entfernt. Das bisherige Verhalten für lateinischen Text bleibt bytegleich.
- Bei Han, Kana und Thai bevorzugt an der letzten Satz- oder Teilsatzgrenze (。！？、，…) innerhalb des Limits, dann an einem vorhandenen Leerzeichen, etwa bei Koreanisch, andernfalls direkt am Limit.
- Immer an Graphemcluster-Grenzen, damit kombinierende Zeichen wie Thai-Vokalzeichen oder Emoji-Modifikatoren nicht von ihrer Basis getrennt werden.

## Sprachabhängige Groß- und Kleinschreibung {#locale-aware-casing}

`mb_strtolower()` berücksichtigt keine Sprache. `Rankbeam\Seo\I18n\CaseFolder` tut dies:

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` liefert die Anzeigeform. `fold()`, `equals()`, `contains()` und `containsWord()` dienen Vergleichen. Der Core nutzt sie, um ein bereits enthaltenes Markensuffix zu erkennen (`seo.title_suffix_skip_when_contains`), einschließlich türkischer i-Formen und Unicode-Wortgrenzen. Pro baut seine Keyword-Prüfung darauf auf.

Case Folding erhält Akzente. Es setzt nicht jede akzentuierte Schreibweise mit einer unakzentuierten gleich. Ein sprachspezifischer Stemmer kann eigene Reduktionen vornehmen; das ist von `CaseFolder` und exaktem Wortvergleich getrennt.

## hreflang {#hreflang}

Google akzeptiert `language[-Script][-REGION]`: eine zweistellige Sprache nach ISO 639-1, optional ein Schriftsystem nach ISO 15924 und eine zweistellige Region nach ISO 3166-1 sowie `x-default`. Numerische Regionen wie `es-419` sind gültiges BCP47, liegen aber außerhalb der [von Google unterstützten hreflang-Codes](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes).

Laravel-Anwendungen liefern oft ihre Locale (`it_IT`, `pt_br`), deren Unterstrich hier ungültig ist. Drei Richtlinien unter `seo.hreflang` bearbeiten die Liste aus `getSEOAlternates()`, bevor sie als `<link rel="alternate">`, `<xhtml:link>` in der Sitemap, `llms.txt`-Verweis oder Audit-Eingabe verwendet wird. `llms.txt` lässt die Seite selbst und `x-default` in seinen „Also in“-Links weg.

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`**, standardmäßig aktiv, passt Trennzeichen, Schreibweise und registrierte Aliasse an (`iw_IL` → `he-IL`). Wiederholte Trennzeichen bleiben erkennbar (`en__US` → `en--US`), damit der Audit sie melden kann. Deaktiviert bleiben die gelieferten Bytes unverändert.
- **`include_self`** ergänzt eigene Sprache und Canonical, wenn weder URL noch Code in der Liste stehen. Aktiviere dies, wenn dein Hook nur andere Sprachen zurückgibt, damit jede Sprachfassung auch sich selbst nennt.
- **`x_default`** nennt die Sprache, deren Alternative zusätzlich als `x-default` ausgegeben wird, sofern dieser Eintrag noch fehlt.

Eine leere Liste bleibt leer. Seiten ohne Übersetzungen erhalten weder einen Selbstverweis noch `x-default`.

Der kostenlose Audit prüft die bereits bearbeitete Liste:

| Code | Schweregrad | Bedeutung |
|---|---|---|
| `hreflang_invalid_code` | warning | Code außerhalb des Google-Formats, etwa `en-UK`, `jp`, `english`, `es-419`, `fil`. |
| `hreflang_duplicate_code` | notice | Derselbe Code steht mehrfach in der Liste. |
| `hreflang_missing_self` | warning | Die eigene URL fehlt in der Liste. |

Gegenseitige Verweise erfordern einen Crawl. Pro ruft bei aktiviertem `check_hreflang_reciprocity` jede Alternative durch den SsrfGuard ab. Fehlt dort die Quell-URL **mit ihrem Sprachcode**, entsteht `hreflang_not_reciprocal` (Pro 2.38+; [Netzwerkcodes](/de/pro/scan-issues#network-codes)). Der Helfer ist öffentlich:

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### Drei unterschiedliche Regeln für Sprachcodes {#three-language-code-contracts}

Core **3.18+** trennt die Anwendungseinstellung vom tatsächlich ausgelieferten HTML-Wert:

| Eingabe | Anwendungsnormalisierung | HTML-Sprache | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | Unverändert ungültig | Unverändert ungültig |
| `de-CH-1901` | Bleibt erhalten | Gültige registrierte Variante | Variante nicht unterstützt |
| `es-419` | Bleibt erhalten | Gültige numerische Region | Numerische Region nicht unterstützt |
| `zh-Hant-TW` | Bleibt erhalten | Gültig | Gültig |
| `fil` | Bleibt erhalten | Gültige registrierte Sprache | Nicht zweistellig |
| `iw_IL` | `he-IL` | Unterstrich ungültig; `iw-IL` bleibt ein gültiger veralteter Tag | Normalisiertes `he-IL` verwenden |
| `en__US` | `en--US` | Ungültig | Ungültig |
| `x-default` | Bleibt erhalten | Von Rankbeams Inhaltssprachrichtlinie abgelehnt | Gültiger Fallback-Marker |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Migration von Core 3.17 und älter:** `Hreflang::isValid()` und `parse()` validieren ausgelieferte Codes streng. Wandle Laravel-Locales zuerst mit `fromLocale()` um. Für ein HTML-Attribut `lang` verwende `LanguageTag::isValidHtml()` ohne vorheriges Trimmen oder Normalisieren. Veraltete registrierte Tags bleiben für HTML gültig. Die Normalisierung verwendet nur ausdrückliche bevorzugte IANA-Aliasse und rät nicht, dass `en-UK` eigentlich `en-GB` heißen soll. Fehlerhafte Einträge bleiben für den Audit sichtbar.

Der Validator enthält Daten aus der IANA-Registry vom **2026-08-08**, mit Quellhashes und reproduzierbarem Generator. Er prüft RFC-5646-Struktur, registrierte Subtags, Extlang-Präfixe und doppelte Varianten oder Erweiterungen. Übernommene ältere Tags und private Bereiche werden unterstützt. Empfehlungen für Variantenpräfixe sind keine zwingenden Gültigkeitsregeln. Erweiterungsnamensräume und Struktur werden geprüft; CLDR-Optionssemantik und die Bedeutung privater Tags liegen außerhalb der API. ICU oder Laufzeitdownloads sind nicht erforderlich. Siehe [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) und die [HTML-Sprachdefinition](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes).

Pro **2.38+** behandelt fehlendes oder leeres `lang` als unbekannt/fehlend. Fehlerhaft ausgelieferte Werte erzeugen `html_lang_invalid`. Schriftsystemprüfungen verwenden ein vorhandenes Script-Subtag oder den registrierten IANA-Standard. Private Werte, Erweiterungen und unbekannte Sprachen implizieren kein lateinisches Schriftsystem. Nicht unterstützte Gruppen bleiben unbewertet. Dies ist keine vollständige Spracherkennung.

Die Prüfung auf Gegenseitigkeit verwendet gültige Selbstverweis-Codes der Quelle, andernfalls ihre gültige Google-kompatible HTML-Sprache. Ein Rückverweis unter einem anderen Sprachcode reicht nicht aus. Lässt sich der Quellcode nicht bestimmen, bleibt das Ergebnis `hreflang_target_unverified`. Doppelte Ziel-URLs werden innerhalb der bestehenden Grenzen einmal abgerufen. SSRF-Schutz, abgelehnte Weiterleitungen und die Kennzeichnung nicht verifizierbarer Fehler bleiben erhalten.

## `inLanguage` im Schema-Graphen {#inlanguage-in-the-schema-graph}

Der Knoten `WebPage` übernimmt `inLanguage` aus der aufgelösten Seitensprache (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` verwendet die gespeicherte `seo_meta`-Sprache. `WebSite` liest seine Sprachen aus der Konfiguration:

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Regionale Suchmaschinen {#regional-search-engines}

Der Katalog hinter `seo:robots-txt` enthält neben KI-Bots Yandex, Baidu, Naver (`Yeti`), Seznam, Sogou, 360, Cốc Cốc und DuckDuckGo. Diese klassischen Suchcrawler haben den Zweck `search_engine` und sind standardmäßig erlaubt. Richtlinien und individuelle Überschreibungen gelten auch für sie:

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` und `match()` bleiben auf KI-Bots beschränkt. Für Suchmaschinen verwende `searchEngines()`, `all(true)` oder `match($ua, true)`. So ändern sich KI-Bot-Protokoll und KI-Crawler-Zähler nicht. Siehe [KI-Crawler steuern](/de/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
Unterstützte Crawler und Verifizierungstags garantieren keine Entdeckung, Indexierung oder Rankings in Baidu.
:::

## Website-Verifizierung {#site-verification}

Eigentumsnachweise erscheinen auf jeder Seite als Meta-Tags für die konfigurierten Suchmaschinen. Die Startseite ist damit ebenfalls abgedeckt, auf der Yandex, Baidu und Naver nach dem Nachweis suchen. Leere Schlüssel erzeugen keinen Tag:

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

Ein Wert darf eine Liste von Tokens sein, etwa für mehrere Google-Property-Inhaber.

## OG-Bilder für unterschiedliche Schriftsysteme {#og-images-in-every-script}

Die mitgelieferte Schrift deckt Lateinisch, Kyrillisch und Griechisch ab. Andere Schriftsysteme benötigen eine installierte Schrift auf dem Host, der `seo:og-images` ausführt. CJK-Schriften sind häufig über 16 MB groß und werden nicht mitgeliefert. Die Templates enthalten einen Fallback-Stack (`seo.og_image.font_stack`); die Noto-CJK-Familie der Seitensprache steht zuerst, damit Han-Zeichen zur Sprache passende Formen erhalten. Der Befehl warnt einmal je betroffenem Schriftsystem, wenn er keine geeignete installierte Schrift erkennt:

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Unter Debian/Ubuntu: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. Einzelheiten: [OG-Bilder erzeugen](/de/guide/og-image#fonts-and-non-latin-scripts).

## Mehrsprachige `llms.txt` {#llms-txt-in-several-languages}

Mit `seo.llms_txt.alternates` endet ein Eintrag mit vorhandenen Übersetzungen auf `Also in: [it](…), [de](…)`. Die Liste berücksichtigt die hreflang-Richtlinien, ohne `x-default` oder die Seite selbst. Standardmäßig deaktiviert.

## Unicode-URLs {#unicode-urls}

Rankbeam erzeugt keine Slugs und schreibt URLs nicht um. Pfade wie `/città/` oder `/検索` bleiben in den Ausgaben erhalten. Der Audit akzeptiert Canonicals mit IDN-Host (`https://münchen.example/`) sowie Unicode- oder prozentkodierten Pfaden. Dafür ersetzt `Rankbeam\Seo\I18n\Url::isValid()` PHPs auf ASCII beschränktes `FILTER_VALIDATE_URL`. Wähle je URL eine einheitliche Form, damit Canonical, hreflang und Sitemap bytegenau übereinstimmen.

## Unterstützte Sprachen und ihre Grenzen {#which-languages-are-supported-and-what-that-means}

Die Pakete enthalten Oberflächentexte und Analysezuordnungen für die folgenden 17 Locales. Die Tabelle beschreibt die technische Abdeckung, keine muttersprachliche Freigabe oder garantierte Darstellung auf einem unkonfigurierten Host. Japanische und chinesische Wortanalysen benötigen nutzbares ICU. Fehlt es, werden betroffene wortbasierte Prüfungen übersprungen. Nichtlateinische Darstellung benötigt passende Schriften.

`tests/Feature/I18n/SupportedLanguagesTest.php` im Core prüft Sprachliste, hreflang-Codes und Budgets. `tests/Feature/OnPage/LanguageSupportMatrixTest.php` in Pro prüft die zugeordneten Analyseverfahren.

| Sprache | Locale | Titel / Beschreibung | Wortzählung | Keyword-Abgleich | Lesbarkeit |
|---|---|---|---|---|---|
| Englisch | `en` | 60 / 160 | Leerzeichen | Snowball | Flesch Reading Ease |
| Italienisch | `it` | 60 / 160 | Leerzeichen | Snowball | Gulpease |
| Deutsch | `de` | 60 / 160 | Leerzeichen | Snowball | Wiener Sachtextformel |
| Französisch | `fr` | 60 / 160 | Leerzeichen | Snowball | Kandel-Moles |
| Spanisch | `es` | 60 / 160 | Leerzeichen | Snowball | Fernández-Huerta |
| Portugiesisch, Brasilien | `pt_BR` | 60 / 160 | Leerzeichen | Snowball | Martins |
| Niederländisch | `nl` | 60 / 160 | Leerzeichen | Snowball | Flesch-Douma |
| Türkisch | `tr` | 60 / 160 | Leerzeichen | Snowball | Ateşman |
| Russisch | `ru` | 60 / 160 | Leerzeichen | Snowball | Oborneva |
| Polnisch | `pl` | 60 / 160 | Leerzeichen | Snowball | Pisarek |
| Japanisch | `ja` | 30 / 80 | ICU-Wörterbuch | Exakt mit Case Folding | Heuristik, **kein Score** |
| Chinesisch, vereinfacht | `zh_CN` | 30 / 80 | ICU-Wörterbuch | Exakt mit Case Folding | Heuristik, **kein Score** |
| Chinesisch, traditionell | `zh_TW` | 30 / 80 | ICU-Wörterbuch | Exakt mit Case Folding | Heuristik, **kein Score** |
| Koreanisch | `ko` | 30 / 80 | Leerzeichen | Exakt mit Case Folding | Heuristik, **kein Score** |
| Griechisch | `el` | 60 / 160 | Leerzeichen | Snowball | LIX |
| Ukrainisch | `uk` | 60 / 160 | Leerzeichen | Exakt mit Case Folding | LIX |
| Tschechisch | `cs` | 60 / 160 | Leerzeichen | Snowball | LIX |

Drei Einschränkungen gehören zu dieser Tabelle:

- **Snowball wird ab Pro 2.37 mitgeliefert.** Zwölf Sprachen verwenden festgelegte Algorithmen aus 3.1.1, unabhängig von optionalen Paketen. Ukrainisch und CJK verwenden exakte Vergleiche mit Case Folding statt erfundener Suffixregeln. Solche Vergleiche können gebeugte Formen übersehen; Stemming kann unterschiedliche Wörter zusammenführen. Siehe [Verfahren und Migration](/de/pro/on-page-checklist#upgrading-from-pro-2-36).
- **„Heuristik, kein Score“ unterscheidet sich von LIX.** Japanisch, Chinesisch und Koreanisch liefern in diesem Paket eine beratende Stufe aus Satzlänge und Kanji-Anteil mit `null` als Score. Griechisch, Ukrainisch und Tschechisch verwenden LIX, weil kein eigenes Verfahren implementiert ist. LIX benötigt keine Silbenzählung, seine Schwellen sind aber nicht für jede Sprache kalibriert. Alle Formeleingaben enthalten Schätzungen; siehe [Statistikgrenzen](/de/pro/on-page-checklist#text-statistics-and-api-limits).
- **Paketübersetzungen sind Erstfassungen**, sofern `TRANSLATING.md` keine muttersprachliche Prüfung nennt. Italienische Pakettexte haben eine solche Prüfung; für die übrigen wird sie noch benötigt. Das ist keine Freigabe dieser Dokumentationsübersetzung.

Nicht aufgeführte Locales können auf englische Texte, Standardbudgets, exakten Keyword-Abgleich sowie LIX oder Heuristiken zurückfallen. Dieser Fallback ist keine validierte Sprachunterstützung. Der Block `analysis` der Checkliste nennt Schriftsystem, Segmentierer, Stemmer und Lesbarkeitsverfahren. Prüfe auch Verfügbarkeit und übersprungene Bewertungen.

### Lokal relevante Suchmaschinen erreichen {#reaching-the-search-engines-that-matter-locally}

Zur technischen Sprachunterstützung gehören auch Crawler und Verifizierungstags: etwa Naver für koreanische, Seznam für tschechische und Yandex für ukrainische oder russische Websites. Siehe [regionale Suchmaschinen](#regional-search-engines) und [Website-Verifizierung](#site-verification).

## Ergänzungen der anderen Pakete {#what-the-other-packages-add}

- **laravel-seo-filament** verwendet dieselbe Längenrichtlinie für Zähler und SERP-Vorschau. Seit 1.9 bearbeitet es [eine `seo_meta`-Zeile je Sprache](/de/guide/filament#several-languages): über eigene Tabs mit Zählern, Vorschau und Fallback-Hinweisen oder durch Folgen des Sprachwechslers eines Übersetzungsplugins.
- **laravel-seo-pro** verwendet sie für `title_length`, `description_length` und KI-Prompts. Die sprachbezogene Analyse umfasst ICU-Segmentierung für Chinesisch, Japanisch und Thai, Snowball, Keyword-Abgleich mit `CaseFolder`, veröffentlichte Lesbarkeitsformeln mit geschätzten Eingaben für zehn Sprachen, gekennzeichnete CJK-Heuristiken und LIX für Griechisch, Ukrainisch und Tschechisch, Stoppwörter für 16 Sprachen, `html lang`, gegenseitige hreflang-Verweise, sprachbezogene KI-Prompts und Chrome-Berichte für Schriftsysteme, die dompdf nicht darstellen kann. Siehe [Checkliste](/de/pro/on-page-checklist#keyword-matching), [Scan-Befunde](/de/pro/scan-issues), [KI-Hilfe](/de/pro/ai-assist#output-language) und [Berichte](/de/pro/reports#reports-in-every-script-browsershot-renderer).
