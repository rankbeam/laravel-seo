---
description: "Eine zweite deterministische Bewertung neben dem SEO-Wert: Rankbeams Maß für technische KI-Lesbarkeit und Erreichbarkeit anhand von Crawl-Signalen, getrennt von der SEO-Bewertung."
---

# AI-Readiness: eine zweite deterministische Bewertung {#the-ai-readiness-score-—-a-second-deterministic-axis}

Der Pro-Scan gibt jeder Seite neben der [SEO-Bewertung](/de/pro/scoring) einen **AI-Readiness-Wert von 0 bis 100**. Er bewertet andere technische Signale: Können KI-Crawler und Antwortdienste Inhalte erreichen, lesen und zuordnen? Der Wert wird **nie in die organische SEO-Bewertung eingerechnet**. Beide haben eigene Regeln, Versionen und Datenbankspalten.

::: warning Bedeutung und Grenzen des Werts
AI-Readiness ist ein **von Rankbeam definiertes deterministisches Maß technischer Kompatibilität**. Es prüft, ob bestimmte crawlbasierte Seitensignale vorhanden und korrekt aufgebaut sind. Es prognostiziert weder Ranking noch Indexierung, Aufnahme oder Zitate in Such- oder KI-Systemen. Kein Wert garantiert solche Ergebnisse. `air_llms_txt` vergibt Punkte für eine **optionale** Kompatibilitätsdatei, die manche Tools auswerten können. Google Search benötigt `llms.txt` nicht; die Datei ist kein Ranking-Signal.
:::

Die Bewertung ist **deterministisch und reproduzierbar**. Jeder Punkt gehört zu einer benannten Prüfung; dieselben Signale ergeben unter denselben Regeln denselben Wert. **Bei der Bewertung erfolgen keine KI-Aufrufe.** Sie misst nachvollziehbare technische Signale, statt Antworten eines Sprachmodells stichprobenartig auszuwerten.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip Zwei getrennte Werte
`AI-readiness: 74/100` steht neben `SEO: 82/100`; keiner verändert den anderen. AI-Readiness liegt in eigenen `ai_readiness_*`-Spalten von `seo_scan_results`. Wie der SEO-Wert ist die **Zahl eine Pro-Funktion**. Das kostenlose [`seo:audit`](/de/guide/audit) zeigt keine numerische Bewertung.
:::

## Punkte sammeln statt Abzüge erhalten {#additive-credit-not-penalty}

Die [SEO-Bewertung](/de/pro/scoring) beginnt bei 100 und zieht Punkte ab. AI-Readiness beginnt dagegen bei **0** und **vergibt** das Gewicht jeder erfüllten Prüfung vollständig oder teilweise. Ohne passende Signale liegt der Wert entsprechend nahe 0. Alle Gewichte ergeben zusammen genau **100**.

## Die Bewertungsregel {#the-rubric}

Die veröffentlichte, versionierte Klasse `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric` enthält zehn Prüfungen in vier Kategorien.

### A · Bot-Zugriff und Regeln: 30 Punkte {#a-·-bot-access-control-—-30-points}

Die Prüfung verwendet die **ausgelieferte `/robots.txt`** und wertet sie für den **eigenen Pfad der gescannten Seite** aus. Eine Seite unter `Disallow: /section` gilt als ausgeschlossen, auch wenn die Startseite freigegeben ist. `robots.txt` ist eine Anweisung für regelkonforme Crawler, keine Netzwerksperre. Die Zwecke Training, Suche und Assistent stammen aus dem [KI-Crawler-Katalog](/de/guide/ai-crawlers).

| Prüfung | Gewicht | Punktevergabe |
|---|---|---|
| `air_robots_reachable`: `robots.txt` wird ausgeliefert und ist lesbar | 6 | Vorhanden oder fehlend |
| `air_ai_search_access`: KI-**Suchcrawler** als möglicher Besucherkanal dürfen den Pfad abrufen | 10 | Anteil erlaubter Crawler |
| `air_ai_assistant_access`: KI-**Assistenten-Crawler** dürfen den Pfad abrufen | 8 | Anteil erlaubter Crawler |
| `air_explicit_ai_policy`: ausdrückliche `robots.txt`-Regel für einen bekannten KI-Bot | 6 | Vorhanden oder fehlend |

::: tip Trainingscrawler auszuschließen verringert die Bereitschaft nicht
Trainingsbots wie GPTBot oder CCBot auszuschließen ist eine legitime Entscheidung und verursacht **keinen Abzug**. Training wird nur über `air_explicit_ai_policy` berücksichtigt: Es zählt die ausdrückliche Regel. Eine Website kann Trainingscrawler ausschließen, Such- und Assistenten-Crawler zulassen und trotzdem alle Punkte dieser Kategorie erhalten.
:::

### B · Auffindbarkeit: 20 Punkte {#b-·-discoverability-—-20-points}

| Prüfung | Gewicht | Punktevergabe |
|---|---|---|
| `air_sitemap_discoverable`: XML-Sitemap erreichbar **und** über `Sitemap:` referenziert | 12 | Beides, eines oder keines |
| `air_llms_txt`: gültige `/llms.txt` mit Überschrift und Links wird ausgeliefert | 8 | Gültig, nur vorhanden oder fehlend |

### C · Maschinenlesbare Inhalte: 22 Punkte {#c-·-machine-readable-content-—-22-points}

| Prüfung | Gewicht | Punktevergabe |
|---|---|---|
| `air_server_rendered_content`: ausreichender Text im serverseitigen HTML ohne JavaScript-Ausführung | 14 | Nach Wortzahl |
| `air_markdown_twin`: Markdown-Fassung der Seite über Content Negotiation verfügbar | 8 | Vorhanden oder fehlend |

### D · Strukturierte Daten und Antwortstruktur: 28 Punkte {#d-·-structured-data-answer-readiness-—-28-points}

| Prüfung | Gewicht | Punktevergabe |
|---|---|---|
| `air_schema_completeness`: JSON-LD vorhanden, Hauptentität typisiert, Autor und Datum bei Artikeln vorhanden | 18 | Vollständig, teilweise oder nicht vorhanden |
| `air_answer_structure`: Strukturen für Antwortentnahme wie FAQ-/QA-/HowTo-Schema, Überschriftenhierarchie, Listen und kurzer Einstieg | 10 | Nach Anzahl vorhandener Strukturen |

Jede Prüfung vergibt **volle**, **teilweise** oder **keine** Punkte. **Skipped** bedeutet, dass die benötigten Daten nicht erhoben werden konnten, etwa bei einer Seitenprüfung ohne Seitenabruf. Eine übersprungene Prüfung erhält 0 Punkte, wird aber als solche gekennzeichnet. Nicht geprüft bedeutet damit nicht nachweislich fehlend.

### Reichweite des kostenlosen Audits {#free-audit-reach}

`air_schema_completeness` lässt sich ohne Abruf aus den strukturierten Daten eines Modells ableiten. Diesen Weg verwendet auch das kostenlose Audit für seine AEO-Hinweise. Die übrigen neun Prüfungen benötigen einen Crawl; der vollständige Zahlenwert gehört deshalb zum **Pro-Scan**.

## Was diese Bewertung ausschließt {#honest-scope-—-what-this-axis-excludes}

Bewertet werden **deterministische Inhaltssignale**. Prüfungen von Agenten-Infrastruktur, laufenden Anwendungen oder DNS gehören nicht dazu:

| Ausgeschlossen | Grund |
|---|---|
| **DNS-AID**, DNS-Einträge zur Agenten-Erkennung | DNS-/DNSSEC-Infrastruktur, keine Eigenschaft einer ausgelieferten Seite |
| **Web Bot Auth**, Signaturen je Anfrage | Interaktiver kryptografischer Handshake statt statischem Inhalt |
| **Protokollerkennung**, etwa API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills und WebMCP | Benötigt eine laufende App, API oder einen MCP-Server |
| **Commerce**, etwa x402, MPP, UCP und ACP | Zahlungsprotokolle für Agenten, keine Voraussetzung einer gewöhnlichen Inhaltswebsite |

Enthalten sind dagegen Vollständigkeit von Schema-Entitäten und Antwortblock-Struktur. Sie beschreiben Organisation und Zuordnung von Inhalten, ohne deren Verwendung durch eine Such- oder Antwortmaschine zu garantieren.

## Versionierung gespeicherter Bewertungen {#versioning-—-historical-scores-never-silently-change}

Jeder gespeicherte Wert trägt die erzeugende `AiReadinessRubric::VERSION` in `ai_readiness_version`. Änderungen an Prüfungen, Gewichten oder Punktevergabe **erhöhen die Version**. So bleibt erkennbar, welche Regel einen gespeicherten Wert erklärt. Der Wert wird **gespeichert und beim Lesen nicht neu berechnet**. Bewertungsrelevante Schwellen wie Wortzahl und Anzahl von Antwortstrukturen sind versionierte Konstanten im Code, keine Konfiguration. Einstellungen verändern deshalb keine gespeicherte Zahl still beim Lesen.

::: warning Eine Eingabe wird nicht durch die Regelversion festgeschrieben
Die Bot-Zugriffsprüfungen verwenden den **aktuellen** [KI-Crawler-Katalog](/de/guide/ai-crawlers) des Cores. Neue Bots oder geänderte Zweckzuordnungen können die beiden Teilwerte bei einer neuen Berechnung verändern, ohne `AiReadinessRubric::VERSION` zu erhöhen. Die Version beschreibt die Bewertungsregel, nicht den Katalog. Für exakt vergleichbare historische Berechnungen musst du deshalb auch die Core-Paketversion festhalten.
:::

## Speicherort {#where-it-s-stored}

Jeder Scan schreibt die AI-Readiness-Spalten in **denselben** Datensatz von `seo_scan_results` wie die SEO-Bewertung:

| Spalte | Inhalt |
|---|---|
| `ai_readiness_score` | Wert von 0 bis 100; null, bis das Ziel mit aktivierter Bewertung gescannt wurde |
| `ai_readiness_version` | Verwendete Bewertungsregel |
| `ai_readiness_breakdown` | Vollständige Aufschlüsselung: `[{code, category, credit, weight, points, status, message, evidence}, …]` |

Beim Abschluss wird der Durchschnitt des Laufs in `seo_scan_runs.avg_ai_readiness` gespeichert, entsprechend `avg_score`. Daraus entsteht der AI-Readiness-Trend.

## Bewertung lesen {#reading-the-score}

**Ohne Oberfläche** enthält das letzte Modellergebnis beide Werte:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament**: Ergänze die Begleitspalte neben der SEO-Bewertung in einer Ressourcentabelle:

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

Der Wert erscheint außerdem als eigenes Badge auf der Onpage-Bewertungskarte über dem SEO-Titelfeld und als Abschnitt im [White-Label-Bericht](/de/pro/reports), in PDF und E-Mail. Dieser enthält Zahl, Stufe, Änderung zum letzten Bericht und Scan-Trend. AI-Readiness steht immer neben der organischen SEO-Bewertung und wird nie mit ihr vermischt.

### Bewertungsstufen {#grade-bands}

Die Buchstabennote dient der Darstellung; maßgeblich ist die Zahl. Zur einheitlichen Darstellung gelten dieselben Bereiche wie bei der SEO-Bewertung:

| Wert | Stufe |
|---|---|
| 90–100 | A |
| 75–89 | B |
| 50–74 | C |
| 25–49 | D |
| 0–24 | F |

## Konfiguration {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

Prüfungen und Gewichte sind **nicht konfigurierbar**. Für eine bestimmte `ai_readiness_version` soll die Regel auf jeder Installation gleich arbeiten. Änderungen an der Berechnung sind deshalb Änderungen der Bewertungsregel im Code.

::: warning Website-Signale werden über den internen Anfrageweg erkannt
Bei Zielen desselben Hosts löst der Scan `/robots.txt`, `/llms.txt` und die Seite über Laravels HTTP-Kernel im selben Prozess auf, wie die übrigen Scan-Anfragen. **Statische Dateien**, die Laravel-Routing umgehen, werden dabei nicht gesehen. Liefere `robots.txt` und `llms.txt` über die Paketrouten aus, wenn der Scan sie bewerten soll.
:::
