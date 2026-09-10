---
description: "Optionale KI-Unterstützung mit eigenem API-Schlüssel: Titel und Meta-Beschreibungen vorschlagen, Scan-Befunde erklären, Beschreibungen umformulieren und schema.org-Daten vorschlagen. Standardmäßig deaktiviert."
---

# KI-Unterstützung {#ai-assist}

Optionale KI-Unterstützung mit **deinem eigenen API-Schlüssel**: Vorschläge für Titel und Meta-Beschreibungen, verständliche Erklärungen von Scan-Befunden, eine **Überarbeitung der Beschreibung** und ein **Vorschlag für strukturierte Daten nach schema.org**. Die Funktion ist **standardmäßig deaktiviert**. Bei deaktiviertem Schalter wird kein KI-Codepfad ausgeführt.

Drei Grundsätze bestimmen die Umsetzung:

- **Dein Schlüssel, dein Anbieter.** Anfragen gehen von *deinem Server* direkt an den von *dir* konfigurierten Anbieter: Anthropic, OpenAI, Google oder einen lokalen beziehungsweise OpenAI-kompatiblen Server. Anfallende Gebühren werden deinem Konto berechnet. Das Paket vermittelt oder verkauft keine API-Nutzung weiter, rechnet sie nicht selbst ab und versendet keine Telemetrie.
- **Interaktive Vorschläge brauchen eine ausdrückliche Übernahme.** Das Modell schlägt vor, du wählst aus. Ein Vorschlag oder eine Korrektur wird nur durch eine *ausdrückliche Aktion* übernommen: Ein Formularfeld wird ausgefüllt oder ein geprüfter Wert beim Klick auf Apply gespeichert. Die übliche Validierung – schriftabhängige Längenzähler, Prüfwarnungen und Schema-Validator – gilt wie bei manuell eingegebenen Werten. Der unten beschriebene Befehl zum massenhaften Ausfüllen ist ein ausdrücklich gestarteter Schreibvorgang. Er verlangt keine Prüfung jedes generierten Feldes vor dem Speichern.
- **Fehler blockieren keine anderen Abläufe.** Ein fehlender oder ungültiger Schlüssel, ein aufgebrauchtes Guthaben, ein Rate-Limit oder ein Timeout erzeugt eine Meldung an der jeweiligen Stelle. Speichern, Rendering und Scans werden dadurch nicht verhindert.

## Anbieter im Überblick {#providers-at-a-glance}

Wähle nach Kontozugang, Anforderungen an die Datenverarbeitung und Kosten. Alle vier Integrationen bieten dieselben Aufgaben an. Modellunterstützung, Ausgabeformat, Geschwindigkeit und Qualität können sich unterscheiden.

| Anbieter | Mitgeliefertes Standardmodell | Strukturierte Ausgabe | Beispielkosten | Einsatz |
|---|---|---|---|---|
| **Local** (Ollama / LM Studio / vLLM) | `llama3.1`, frei konfigurierbar | Best-Effort über `response_format` | **0 $ API-Gebühr** bei selbst gehosteter Inferenz; Infrastrukturkosten bleiben | Kontrolle über das Ziel der Daten |
| **OpenAI** | `gpt-5.5` | Structured Outputs, sofern das gewählte Modell sie unterstützt | Etwa 0,005 $ pro Vorschlag unter den unten genannten Beispielannahmen | Vorhandenes OpenAI-Konto |
| **Anthropic** | `claude-opus-4-8` | `output_config.format`, sofern unterstützt | Etwa 0,015 $ pro Vorschlag unter diesen Annahmen | Vorhandenes Anthropic-Konto |
| **Google** | `gemini-2.5-flash` | `responseSchema`, sofern unterstützt | Etwa 0,0005 $ pro Vorschlag unter diesen Annahmen | Google-Konto; Modellkontingente und Preise prüfen |

Die Namen beschreiben die mitgelieferte Konfiguration. Sie garantieren keine aktuelle Verfügbarkeit in deinem Konto. Die Kosten beruhen auf Beispielannahmen des Pakets, nicht auf verifizierten aktuellen Preisen. Zum Verhalten der Integrationen und den Beobachtungen aus den veröffentlichten Versuchen:

- **Strukturierte Ausgabe.** Unterstützte OpenAI-, Google- und Anthropic-Pfade erhalten ein JSON-Schema. Ungültige Antworten ergeben einen kontrollierten Fehler. Lokale Server erhalten `response_format` nach dem Best-Effort-Prinzip. Ignoriert der Server diese Angabe, liefert ein toleranter Parser entweder eine gültige Liste oder einen Fehler; Teilausgaben werden nicht übernommen.
- **Reasoning verändert den Tokenverbrauch.** Im beschriebenen Gemini-Versuch wurden für eine Beschreibung ungefähr 500 verborgene Reasoning-Tokens und 100 sichtbare Tokens verbraucht. Die getesteten Anthropic-Aufrufe meldeten keine verborgenen Reasoning-Tokens. Das ist keine allgemeine Eigenschaft dieser Modellfamilien. Verborgene Tokens können als Ausgabe abgerechnet werden; deshalb gibt es die unten beschriebene Untergrenze für Reasoning-Budgets.
- **Modelle sind konfigurierbar.** Setze `SEO_PRO_AI_MODEL` auf ein verfügbares Modell, das zur API und den Parametern des Adapters passt. Beispiele sind `claude-haiku-4-5`, `gpt-5.4-mini` und `gemma-3-12b-it`. Prüfe Unterstützung und Ausgabequalität, bevor du ein Modell für eine ganze Sammlung einsetzt.

## Einrichtung {#setup}

Aktiviere die Funktion und hinterlege deinen Anbieterschlüssel in der Umgebung. Zum Wechsel zwischen Cloud-Anbietern änderst du Anbieter und Schlüssel und prüfst auch einen eventuell ausdrücklich gesetzten Modellnamen. Der lokale Adapter benötigt zusätzlich die URL seines Servers.

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip Ein Anbieter-API-Schlüssel ist unabhängig von einem Claude-/ChatGPT-Abonnement
Ein **Abonnement** für Claude Code, Claude.ai oder ChatGPT finanziert nicht die **API-Nutzung**. `SEO_PRO_AI_API_KEY` muss ein API-Schlüssel aus der Entwicklerkonsole des Anbieters beziehungsweise ein Google-AI-Studio-Schlüssel mit eigenem nutzbarem Kontingent oder Guthaben sein. Ein API-Konto ohne verfügbares Guthaben oder Kontingent kann sich authentifizieren und anschließend trotzdem einen **Guthaben-/Kontingentfehler** zurückgeben. Siehe [Fehlerbehebung](#troubleshooting).
:::

Die Konfigurationsdatei `config/seo-pro.php` enthält im Block `ai` die Einstellungen `timeout`, `max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models` und `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model` für günstigere Massengenerierung (siehe [Kosten](#cheaper-bulk-generation)), `retry`, die Tabelle `pricing` und den Unterblock `local`. Sie werden unter [Grenzen und Anpassungen](#limits-and-tuning) erläutert.

::: warning Schlüssel bei gecachter Konfiguration
Die Konfiguration speichert nur den **Namen** der Umgebungsvariable (`api_key_env`), niemals den Schlüssel. `php artisan config:cache` schreibt ihn deshalb nicht nach `bootstrap/cache/config.php`. Bei gecachter Konfiguration wird `.env` jedoch nicht geladen. Setze `SEO_PRO_AI_API_KEY` daher als echte Umgebungsvariable auf dem Server.
:::

## Lokale Inferenz und Cloud-Optionen {#running-at-0-and-the-cheapest-paid-option}

- **Selbst gehostete Inferenz vermeidet die tokenabhängige API-Gebühr eines Anbieters.** Hardware, Strom und Betrieb kosten weiterhin Geld. Inhalte bleiben nur dann in deinem Netzwerk, wenn auch der konfigurierte Inferenzserver und seine Abhängigkeiten dort bleiben.
- **Google verwendet modell- und tarifabhängige Kontingente und Preise.** Ein AI-Studio-Schlüssel von `aistudio.google.com/apikey` im Format `AIza…` kann Tests im kostenlosen Kontingent erlauben. Prüfe, ob dessen Grenzen zu deinem Umfang passen, bevor du bei Bedarf die Abrechnung aktivierst. `gemini-2.5-flash` ist das mitgelieferte Standardmodell. Gemini- und Gemma-Modelle unterstützen nicht alle dieselben Thinking-Funktionen. `reasoning_models` gleicht konfigurierte Namensmuster ab und führt keinen Fähigkeitstest durch.

So legst du fest, wo die Inferenz läuft:

- **Local / OpenAI-kompatibel.** Verwende `provider=local` mit einem zu OpenAI Chat Completions kompatiblen Server wie **Ollama**, **LM Studio**, **vLLM** oder **LocalAI**, oder mit einem externen Gateway wie **OpenRouter**. Setze `SEO_PRO_AI_LOCAL_BASE_URL` auf dessen API-Basis-URL; `/chat/completions` wird angehängt. Wähle ein verfügbares `SEO_PRO_AI_MODEL`. Ein externes Gateway empfängt die Daten außerhalb deines Netzwerks und kann Gebühren verlangen. Der Adaptername `local` bedeutet nicht automatisch lokale Inferenz.

::: warning Lokale `base_url` wird geprüft – localhost ausdrücklich freigeben
`base_url` ist eine privilegierte Einstellung. Sie wird mit demselben `SsrfGuard` geprüft wie andere ausgehende Abrufe: nur HTTP/HTTPS, keine Zugangsdaten in der URL und standardmäßig Auflösung zu einer **öffentlichen** Adresse. Eine versehentliche oder manipulierte `base_url` soll damit keine internen Dienste ausforschen können. Ein tatsächlich lokaler Server unter `127.0.0.1` verwendet eine private Adresse und braucht die ausdrückliche Freigabe `seo-pro.ai.local.allow_local_addresses` (`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`). Lass sie für öffentliche Gateways wie OpenRouter deaktiviert. Der Anfragepfad ist festgelegt; Weiterleitungen werden nie verfolgt, sodass der Schlüssel darüber nicht an einen anderen Host weitergereicht werden kann.
:::

::: tip Thinking bei unterstützten Ollama-Modellen steuern
Ein lokales Thinking-Modell kann den Standard-Timeout überschreiten. Unterstützen Modell und Serverversion die Option, kann `['think' => false]` in `seo-pro.ai.local.extra_body` Thinking für Vorschläge deaktivieren. Die Unterstützung variiert; prüfe die [Ollama-Dokumentation](https://docs.ollama.com/capabilities/thinking). Erhöhe bei Bedarf `seo-pro.ai.timeout`. Das Paket sendet kein `temperature`, weil manche Modelle diesen Parameter ablehnen.
:::

## Kosten {#cost}

Das Paket erhebt keinen Aufschlag. Du bezahlst den Anbieter direkt; bei selbst gehosteter Inferenz fällt keine Anbieter-API-Gebühr an, Infrastrukturkosten bleiben. Relevant sind die Kosten **pro interaktivem Vorschlag** und die Kosten für das **massenhafte Ausfüllen** einer ganzen Sammlung.

Die Tabelle `seo-pro.ai.pricing` mit USD pro 1.000.000 Tokens wandelt eine Token-Schätzung in den Dollarbetrag der Bestätigungsabfrage um. Es sind **mitgelieferte Schätzannahmen**, keine verifizierten aktuellen Listenpreise. **Ersetze sie durch die aktuell veröffentlichten Preise deines Anbieters**, wenn du eine belastbare Schätzung brauchst:

| Modellmuster | Eingabe $/1 Mio. | Ausgabe $/1 Mio. |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

Das veröffentlichte Beispiel kombiniert den gemessenen Tokenverbrauch einer Testseite für einen Titel und eine Beschreibung mit diesen Annahmen. Die letzte Spalte berücksichtigt den beispielhaften Batch-Rabatt von 50 % für unterstützte Adapter. Die Beträge sind keine aktuellen Preisangebote.

| Anbieter / Modell | Ungefähr pro Vorschlagspaar | Ungefähr pro 1.000 Datensätze (Massenausfüllung) | Ungefähr pro 1.000 Datensätze (`--batch`) |
|---|---|---|---|
| Local `gemma`/`llama` (Ollama) | **0 $ API-Gebühr** | **0 $ API-Gebühr** | Nicht verfügbar; vom Adapter nicht implementiert |
| Google `gemini-2.5-flash` (bezahlt) | ~0,001 $ | ~0,40 $ | Nicht verfügbar; vom Rankbeam-Adapter nicht implementiert |
| OpenAI `gpt-5.5` | ~0,008 $ | ~5,25 $ | **~2,63 $** (50 % Rabatt) |
| Anthropic `claude-opus-4-8` | ~0,03 $ | ~20 $ | **~10 $** (50 % Rabatt) |

Der Befehl kennzeichnet seine Schätzung mit ungefähr ±50 %. **Das ist weder eine Ausgabenobergrenze noch ein garantierter Fehlerbereich.** Tatsächliche Ein- und Ausgaben sowie Preise verändern die Summe. Die Schätzung berücksichtigt sichtbare Ausgabe; abgerechnetes verborgenes Reasoning kann die Kosten darüber hinaus erhöhen. Für Modelle ohne Preiseintrag erscheint nur eine Token-Schätzung.

### Günstigere Massengenerierung {#cheaper-bulk-generation}

Mit `seo-pro.ai.bulk_model` (`SEO_PRO_AI_BULK_MODEL`) verwendest du ein anderes Modell **ausschließlich für die Massenausfüllung** über `seo-pro:ai-fill` beziehungsweise `SeoPro::aiFill()`. Filament und `seo-pro:ai-suggest` behalten `model`. Bei null verwendet auch die Massenausfüllung `model`. Die Kostenschätzung nutzt das Preismuster des gewählten Modells. Prüfe repräsentative Ausgaben, bevor du den Umfang erhöhst; ein günstigeres Modell ist nicht automatisch geeignet.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Die Paketbeispiele verwenden **anthropic** `claude-haiku-4-5`, **openai** `gpt-5.5-mini`, **google** `gemini-2.5-flash` oder ein kleineres **local**-Modell. Ein Preismuster kann zu einem Namen passen, den der Anbieter gar nicht anbietet. Prüfe deshalb vor der Konfiguration die tatsächliche Modell-ID, API-Kompatibilität und den Preis.

Eine **Ausfüllung von 100 Seiten**, denen jeweils Titel und Beschreibung fehlen, benötigt 200 Anbieteraufrufe. Mit den mitgelieferten `pricing`-Standardwerten und dem Tokenmodell des Schätzers von 600 Eingabe- und 150 Ausgabe-Tokens pro Aufruf ergibt sich folgender Vergleich zwischen Qualitätsmodell und günstigerem `bulk_model`:

| Anbieter | Qualitätsmodell – 100 Seiten | Günstigeres `bulk_model` – 100 Seiten |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **4,05 $** | `claude-haiku-4-5` ≈ **0,27 $** |
| **OpenAI** | `gpt-5.5` ≈ **1,05 $** | `gpt-5.5-mini` ≈ **0,11 $** |
| **Google** | `gemini-2.5-pro` ≈ **0,45 $** | `gemini-2.5-flash` ≈ **0,04 $** |
| **Local** (Ollama / vLLM) | Beliebiges Modell – **0 $ API-Gebühr** | Beliebiges Modell – **0 $ API-Gebühr** |

Das sind Beispielschätzungen ohne garantierten ±50-%-Bereich und ohne Zuschlag für verborgenes Reasoning. Aktualisiere `seo-pro.ai.pricing` mit den veröffentlichten Preisen des gewählten Anbieters, bevor du dich auf die Schätzung verlässt.

## Ausgabesprache {#output-language}

Jeder Prompt nennt die Seitensprache und ihren BCP-47-Code, beispielsweise „in brasilianischem Portugiesisch (pt-BR), der Sprache der Seite, unabhängig von anderen Sprachen im Ausschnitt“. Der an das Modell gesendete Seitenkontext enthält außerdem eine Zeile `Language:` (Pro 2.34). Zuvor verlangten die Prompts nur dieselbe Sprache wie der Ausgangsinhalt. Das Modell musste sie aus kurzen oder gemischtsprachigen Ausschnitten ableiten: Eine türkische Seite mit englischem Markennamen konnte etwa eine englische Antwort erhalten. Verwendet wird die Sprache, mit der die Metadaten der Seite aufgelöst wurden, beziehungsweise die Anwendungssprache, wenn die Seite keine eigene angibt. Dieselbe Sprache bestimmt das [Längenbudget](/de/guide/multilingual#title-and-description-budgets-per-script). Eine japanische Seite fordert daher ungefähr 30 Zeichen lange Titel *auf Japanisch* an.

Seit Pro 2.36 steuert eine ausdrückliche Inhaltssprache gemeinsam den Metadatensatz, die Inhalts-Hooks und die Prompt-Sprache. Die Oberflächensprache des Bedieners bleibt unverändert.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Bestehende Positionsargumente bleiben unverändert. Ohne `locale:` gilt der Standard von `seoData()` des Modells. Separate Übersetzungsmodelle können damit ihre eigene Sprache angeben. Der Ausschnitt verwendet `getContentForSEO()`, sofern es nichtleeren Inhalt liefert, und fällt andernfalls auf die konfigurierten Inhaltsfelder zurück. Filament 1.11 übergibt die Sprache des ausgewählten Tabs automatisch, auch im Einzelsprachenmodus und beim Sprachumschalter der Seite.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

Auch `plan()`, `fill()` und `submitBatchFill()` für die Massenausfüllung akzeptieren ein abschließendes `locale:`. Verwende dieselbe Sprache beim Erstellen von `FillProgress(..., locale: 'it')` und beim Einreichen des Laufs. Jedes Batch-Element speichert seine Inhaltssprache. Beim Abholen wird diese Sprache verwendet und der zugehörige Metadatensatz vor dem Schreiben erneut geprüft. Läufe mit ausdrücklicher Sprache haben getrennte Checkpoint-Dateien; die Verarbeitungsmarkierungen unterscheiden Sprachen. Führe denselben Befehl erneut aus, um Ergebnisse abzuholen. Eigene Jobs sollten die Inhaltssprache serialisieren und weitergeben.

Checkpoints vor Pro 2.36 speicherten keine Inhaltssprache. Ein ausstehender alter Batch bleibt erhalten, wird aber nicht automatisch abgeholt. Gleiche seine Anbieterergebnisse und die beabsichtigte Sprache ab, bevor du ihn mit `--fresh` verwirfst. Eine neue Einreichung könnte dieselbe Arbeit sonst erneut kostenpflichtig ausführen. Auch ein alter sequenzieller Checkpoint mit bereits verarbeiteten Datensätzen muss vor dem Zurücksetzen abgeglichen werden.

### Prüfung je Sprache {#per-language-evaluation}

Das Pro-Quellrepository enthält 170 Eingabeseiten in 17 Sprachen und ein optional aktivierbares Evaluationswerkzeug. Die Eingabeseiten haben Strukturprüfungen und heuristische Prüfungen der Basissprache durchlaufen. Eine unabhängige muttersprachliche Freigabe steht noch aus.

Das Werkzeug prüft **Titel und Beschreibungen**, erfasst Graphemlängen sowie Hinweise zu Sprache und Schrift und speichert jede Anbieterantwort vor den Assertions. Kurze Titel, gemischte Texte und gemeinsame chinesisch-japanische Zeichen können unklar bleiben. Eine Erkennung als Portugiesisch belegt keine brasilianische Sprachverwendung. Die Teilprüfungen chinesischer Zeichen bescheinigen ebenfalls keine regionale Schreibqualität.

Live-Läufe benötigen `SEO_PRO_AI_EVAL=1`, eine ausdrückliche Auswahl über `SEO_PRO_AI_EVAL_LOCALES` und eine Lauf-ID `SEO_PRO_AI_EVAL_RUN`. Sie können Anbietergebühren verursachen und laufen niemals standardmäßig. Jeder Lauf verknüpft seine Belege mit Anbieter, angefordertem und zurückgegebenem Modell, Hashes für Testdaten, Anfrage und Code sowie Zeitstempeln. Fehlgeschlagene Versuche bleiben erhalten. Eine Fortsetzung verwendet gespeicherte Antworten erneut. Eine unterbrochene Anfrage muss ausdrücklich wiederholt werden, weil sie den Anbieter bereits erreicht haben könnte.

Die Belege liegen in der Quelltestumgebung unter `storage/app/seo-ai-evals/<run-id>/`. Die `README.md` der Testdaten beschreibt das versionierte Schema und die Befehle. Muttersprachliche Prüfer bewerten konkrete Ausgabe-Hashes in eigenen Prüfdatensätzen. Eine bestandene automatische Prüfung ist weder muttersprachliche Freigabe noch eine Garantie für veröffentlichungsfähige Texte.

## Grenzen und Anpassungen {#limits-and-tuning}

Alle Einstellungen liegen im Block `ai` von `config/seo-pro.php`:

- **`timeout`**: standardmäßig `15` Sekunden, Umgebungsvariable `SEO_PRO_AI_TIMEOUT`. Das begrenzt auch den synchronen Aufruf beim Öffnen des Filament-Vorschlagsdialogs und bleibt deshalb für eine kurze Wartezeit niedrig. Ein **langsames Reasoning- oder lokales Modell kann 15 Sekunden überschreiten**. Erhöhe dann `SEO_PRO_AI_TIMEOUT`; beachte auch den Ollama-Hinweis zu `think => false` weiter oben. Ein Timeout erzeugt eine Fehlermeldung, blockiert aber niemals das Speichern.
- **`max_input_chars`**: standardmäßig `6000`. Begrenzt aus Kosten- und Datenschutzgründen den pro Anfrage gesendeten Seiteninhalt als Klartext ohne HTML.
- **`max_output_tokens`**: standardmäßig `1000`. Grundlegende Obergrenze generierter Tokens. Wird sie erreicht und die Antwort abgeschnitten, entsteht der eigene Fehler `truncated`, keine unbemerkt unvollständige Antwort.
- **`token_budgets`**: Ausgabelimits pro Aufgabe: `suggestions` 800, `explanation` 600, `rewrite` 300 und `schema_suggestion` 700. Sie benötigen nicht das volle Standardbudget; zusätzlich wird jedoch die Reasoning-Untergrenze angewendet.
- **`reasoning_models`** und **`reasoning_min_output_tokens`**: Untergrenze standardmäßig `2000`. Passt der Modellname zu einem Muster wie `*gemma*`, `gemini-2.5-*` oder `o1*`/`o3*`/`o4*`, wird sein Ausgabebudget mindestens auf diesen Wert angehoben. Thinking-Modelle können vor sichtbarer Ausgabe verborgene Tokens verbrauchen und bei einem kleinen Budget vorzeitig abgeschnitten werden.
- **`suggestion_count`**: standardmäßig `3`. Anzahl angeforderter Alternativen für Titel beziehungsweise Beschreibung.
- **`retry`**: automatische Wiederholungen nur für ausgewählte *vorübergehende* Fehler. Siehe [Verarbeitung von Antworten](#how-replies-are-handled).

## In Filament {#in-filament}

Mit den optionalen Filament-Paketen (`rankbeam/laravel-seo-filament` >= 1.1) ergänzt die aktivierte KI-Unterstützung folgende Aktionen:

- **Suggest with AI** an SEO-Titel- und Beschreibungsfeldern auf Bearbeitungsseiten jeder Resource mit SEO-Bereich. Der Dialog zeigt generierte Alternativen mit Zeichenzahlen. Die Auswahl füllt das Feld zur Prüfung aus.
- **Explain (AI)** in der Befundtabelle des Dashboards: eine kurze verständliche Erklärung des Befunds und seiner konkreten Behebung.
- **Rewrite description (AI)** neben Explain in der Befundtabelle: schlägt eine verbesserte Meta-Beschreibung innerhalb des Beschreibungsbudgets der Seite vor. Es gilt die [Längenrichtlinie](/de/guide/multilingual#title-and-description-budgets-per-script) des Core-Pakets: 160 Zeichen für lateinischen Text, ungefähr 80 für CJK. Prüfe den Text im Dialog. Erst **Apply rewrite** speichert ihn im `seo_meta`-Datensatz der Seite.
- **Suggest structured data (AI)** in der Befundtabelle: empfiehlt einen schema.org-Rich-Result-Typ aus Product, Article oder Breadcrumb und zeigt das dafür erzeugte JSON-LD. **Apply structured data** ergänzt es in `seo_meta.schema_jsonld`, derselben Spalte, die der optionale [Editor für strukturierte Daten](../guide/filament#structured-data-schema-org) verwaltet. Die Daten bleiben dort les- und bearbeitbar. Ein unvollständiger Vorschlag, etwa ein Article ohne Autor oder Bild, wird mit den fehlenden Feldern angezeigt und **nicht** übernommen.

Die letzten beiden Aktionen sind begrenzte Korrekturen; siehe [Begrenzte Korrekturen](#bounded-fixes-propose-never-auto-apply).

## Begrenzte Korrekturen – vorschlagen, nie automatisch übernehmen {#bounded-fixes-propose-never-auto-apply}

Zwei Aktionen erzeugen über allgemeine Vorschläge hinaus jeweils einen einzelnen, *begrenzten* Wert, den du mit einem Klick übernehmen kannst. Beide **schlagen weiterhin nur vor**. Ohne ausdrückliche Annahme wird nichts gespeichert.

- **Beschreibung überarbeiten** (`SeoSuggestionService::rewriteDescription($model, $issue?)`) liefert eine Meta-Beschreibung **innerhalb des Core-Längenbudgets für die Schrift der Seite: 160 bei lateinischer Schrift, ungefähr 80 bei CJK**. Es ist dasselbe Budget, das die Prompts für Titel- und Beschreibungsvorschläge verwenden, gewählt anhand des aufgelösten Seitenwerts. Überschreitet das Modell die Grenze, kürzt deterministischer Code den Text zunächst an einer Satz-, dann an einer Wortgrenze. Die übernommene Überarbeitung löst dadurch nicht selbst `description_too_long` aus. Ein übergebener Scan-Befund lenkt die Überarbeitung, etwa bei *zu lang* oder *fehlend*.
- **Strukturierte Daten vorschlagen** (`SeoSuggestionService::suggestSchemaType($model)`) fragt das Modell nur nach einer **Typempfehlung und einzelnen Feldwerten**, niemals nach rohem JSON-LD. Deterministischer Code baut daraus mit `ProductSchema`, `ArticleSchema` oder `BreadcrumbSchema` des Core-Pakets das Dokument und prüft es mit dem Core-`SchemaValidator`. Erfundenes `@type`, `@context` oder eine erfundene Struktur gelangen dadurch nicht auf die Seite. Die sachliche Richtigkeit der Feldwerte muss trotzdem geprüft werden. Fehlt im zusammengesetzten Dokument ein Pflichtfeld, wird es als *unvollständig* angezeigt und zurückgehalten. In einem Live-Test schlug ein Anbieter für eine inhaltsarme Seite einen `Article` vor. Wegen fehlendem Autor und Bild wurde er korrekt zurückgehalten; andere Anbieter verzichteten auf eine Typempfehlung.

## Ohne Oberfläche {#headless}

Dieselben Funktionen stehen als JSON für Skripte und Anwendungen ohne Filament bereit:

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

Die Ausgabe enthält die Vorschläge beziehungsweise empfohlenen Typ, aufgebautes JSON-LD und Validierungsergebnis, außerdem das verwendete Modell und den **Tokenverbrauch pro Anfrage** für Eingabe, Ausgabe und Reasoning. Damit kannst du Kosten anhand deiner Anbieterpreise berechnen; Tokenzahlen sind keine Rechnung. Bei jedem Fehler endet der Befehl mit einem von null verschiedenen Exitcode und einem Fehler im JSON-Ergebnis. `seo-pro:suggest-schema` ist wie die anderen interaktiven Unterstützungsfunktionen **rein vorschlagend**: Es gibt das Dokument aus und schreibt nichts.

## Fehlende Metadaten massenhaft ausfüllen {#bulk-fill-missing-metadata}

Die bisher beschriebenen Generierungsfunktionen schlagen Werte zur Übernahme vor. Die Funktion, die eine ganze Sammlung direkt **beschreibt**, ist `seo-pro:ai-fill` beziehungsweise `SeoPro::aiFill()`.

```bash
# preview what would be written (no changes saved)
php artisan seo-pro:ai-fill "App\Models\Post" --dry-run

# fill missing descriptions across all configured models
php artisan seo-pro:ai-fill --field=description

# all configured (seo.audit.models / seo.sitemap.models) models, all fields
php artisan seo-pro:ai-fill --force
```

Sie durchläuft die Datensätze und sucht nach **fehlendem Titel oder fehlender Beschreibung**. Fehlend bedeutet: weder ausdrücklicher Wert *noch* berechneter Fallback – dieselbe Definition wie im [Audit](/de/guide/audit). Für solche Felder generiert sie einen Vorschlag und speichert ihn.

Dabei gelten folgende Grenzen:

- **Nur Lücken werden gefüllt.** Ist ein Feld vorhanden oder ableitbar, wird es übersprungen. Bestehende Werte werden **niemals überschrieben**.
- **`--dry-run`** generiert und zeigt Werte, ohne sie zu speichern. Du kannst die Ausgabe damit ohne Datenbankschreibvorgänge prüfen. **Auch ein Dry-Run ruft den Anbieter auf und kann Gebühren verursachen.**
- **Der Befehl schreibt Daten.** Deshalb fragt er in Produktion nach Bestätigung, sofern du nicht `--force` übergibst. `--field` mit title, description oder all und `--limit` begrenzen den Lauf.
- Jedes ausgefüllte Feld benötigt einen Vorschlagsaufruf, der über deinen Schlüssel abgerechnet wird. Die Funktion läuft nur bei aktivierter KI-Unterstützung.

### Große Sammlungen: Taktung, Kostenschätzung und Fortsetzung nach Abbruch {#at-scale-pacing-a-cost-estimate-and-crash-resume}

Hunderte oder Tausende Modelle auszufüllen ist ein langer, möglicherweise **kostenpflichtiger** Vorgang. Der Befehl bietet dafür folgende Vorkehrungen:

- **Getaktete Aufrufe.** `seo-pro.ai.fill.throttle_ms`, standardmäßig `200`, fügt zwischen Anbieteraufrufen eine Pause ein. Ein großer Lauf soll dadurch nicht schlagartig das Rate-Limit erreichen. Setze den Wert für einen entsprechend leistungsfähigen lokalen oder kostenlosen Anbieter auf `0` oder erhöhe ihn bei einem niedrigen Kontingent.
- **Kostenschätzung vor dem ersten Aufruf.** Soll ein Lauf mindestens `seo-pro.ai.fill.confirm_over` Datensätze bearbeiten, standardmäßig `100`, zeigt er eine Schätzung und fragt vor dem ersten Anbieteraufruf nach Bestätigung:

  ```text
  About to fill ~890 missing fields via anthropic (claude-opus-4-8) across 948 records.
  Estimated ~667,500 tokens ≈ $18.02 (rough, ±50%).
  Continue? (yes/no) [no]
  ```

  Der Dollarbetrag stammt aus `seo-pro.ai.pricing`; siehe [Kosten](#cost). Bei einem Modell ohne Preiseintrag, etwa einem lokalen Modell, erscheint eine Token-Schätzung ohne erfundenen Dollarbetrag. `--force` überspringt die Abfrage für automatisierte Abläufe. Auch ein Dry-Run fragt nach, weil er dieselben möglicherweise kostenpflichtigen Aufrufe ausführt.
- **Fortsetzung mit Checkpoints.** Nach **jedem Datensatz** wird der Fortschritt gespeichert. Abgeschlossene, erfasste Felder werden beim Fortsetzen übersprungen; vorübergehende Fehler lassen einen Datensatz für einen weiteren Versuch offen. **Doppelte Gebühren sind damit nicht ausgeschlossen:** Eine Anfrage kann den Anbieter erreichen, bevor ein Timeout oder Abbruch das Speichern des Ergebnisses verhindert. Ein sauber abgeschlossener Lauf entfernt seinen Checkpoint. Gleiche unklare Vorgänge ab, bevor du mit `--fresh` den vorherigen Checkpoint ignorierst.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, apply: true);
// ['processed' => 120, 'filled' => 18, 'skipped' => 102, 'failed' => 0, 'resumed' => 0, 'errors' => [], 'records' => [...]]
// On a provider failure, 'errors' maps each distinct error code to its human
// message (e.g. 'quota_exceeded' => 'OpenAI: the provider account is out of
// credit or quota…'), and the seo-pro:ai-fill command prints those reasons —
// so a run never fails silently.
```

### Batch-Modus (50 % günstiger) {#batch-mode-50-cheaper}

Wenn die Ergebnisse einer großen Ausfüllung nicht sofort benötigt werden, leitet `--batch` den Lauf über den **asynchronen Batch-Endpunkt** des Anbieters: [Anthropic Message Batches](https://docs.anthropic.com/en/docs/build-with-claude/batch-processing) oder die [OpenAI Batch API](https://platform.openai.com/docs/guides/batch). Diese rechnen mit **halbierten** Tokenpreisen ab. Die Rankbeam-Adapter für Google und Local **implementieren diesen Batch-Pfad nicht**. Dort gibt `--batch` einen Hinweis aus und arbeitet sequenziell. Google bietet eine eigene [Batch API](https://ai.google.dev/gemini-api/docs/batch-api), die diese Integration nicht nutzt. Prüfe jeweils die aktuelle Modellunterstützung und die Preise des Anbieters.

Ein Batch wird **jetzt eingereicht und später abgeholt**. Dafür führst du denselben Befehl zweimal aus; dazwischen kann der Prozess beendet werden:

```bash
# 1) Submit: builds one request per missing field, sends the whole batch in a
#    single call, prints the discounted estimate, and exits. Nothing is written yet.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch

#    → Batch submitted: msgbatch_01Hkc… — 890 requests across 890 records via anthropic.
#      Most batches finish within an hour (max 24h, then they expire).
#      Re-run the SAME command to poll and apply the results:
#        php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch

# 2) Collect: re-run the same command. While the batch is still processing it
#    just says so and exits; once results are ready it writes them and prints
#    the usual summary.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

- **Gleiche Aufgabe zum halben Tokenpreis.** Jedes Batch-Element verwendet denselben Prompt, dasselbe Schema für strukturierte Ausgabe und dieselbe Modellkonfiguration wie der synchrone Aufruf, einschließlich eines gesetzten günstigeren [`bulk_model`](#cheaper-bulk-generation). Anfragehülle und Ausführungszeitpunkt unterscheiden sich.
- **Die Schätzung berücksichtigt den Rabatt.** Ab dem Schwellenwert `seo-pro.ai.fill.confirm_over` zeigt die Einreichung den um **50 % reduzierten** Betrag und fragt vor dem Senden nach. `--force` überspringt die Abfrage für Automatisierung.
- **Der Prozess darf beendet werden.** Anbieter-Batch-ID und Zuordnung von Anfragen zu Datensätzen werden mit derselben Fortschrittsverwaltung gespeichert wie beim sequenziellen Lauf. Das Abholen funktioniert deshalb in einem neuen Prozess, etwa bei einem späteren Cron-Aufruf oder nach einem Deployment. Wird die Einreichung genau zwischen Annahme durch den Anbieter und Speicherung der ID beendet, **stoppt der nächste Lauf vorsorglich**. Er weist auf einen möglicherweise laufenden Batch und die Prüfung im Anbieter-Dashboard hin, statt den gesamten kostenpflichtigen Batch stillschweigend erneut einzureichen.
- **Kein Überschreiben.** Die Regel gilt über die *gesamte* Batch-Laufzeit. Beim Abholen wird jeder Datensatz erneut geprüft. Nur **weiterhin fehlende** Felder werden geschrieben. Ein inzwischen manuell ergänzter Titel oder eine Beschreibung bleibt erhalten.
- **Teilergebnisse werden zugeordnet verarbeitet.** Ergebnisse werden unabhängig von ihrer Reihenfolge anhand der ID dem Datensatz zugeordnet. Ein erfolgreich generiertes Feld wird gespeichert und als erledigt markiert. Ein **vorübergehend** fehlgeschlagener Datensatz, etwa bei Rate-Limit, Timeout, Anbieterfehler oder abgelaufenem Batch-Element, bleibt offen. Ein erneutes `--batch` reicht dafür einen kleineren Batch ein. Eine **endgültige Ablehnung** wie Inhaltsfilter oder ungültige Anfrage wird erfasst und nicht wiederholt, damit aussichtslose Datensätze keine kostenpflichtige Schleife erzeugen.
- **Ein Anbieterwechsel während des Batches wird abgelehnt.** Änderst du zwischen Einreichung und Abholung `SEO_PRO_AI_PROVIDER`, stoppt das Abholen mit einer verständlichen Meldung. Wechsle zum Abholen zurück oder verwirf den Zustand nach Abgleich mit `--fresh`. Der Befehl fragt nicht versehentlich die falsche API ab. Führe immer nur eine `--batch`-Einreichung gleichzeitig aus; starte keine konkurrierenden Einreichungen für dieselben Modelle und Felder.
- **Eine zusätzliche Einstellung:** `seo-pro.ai.fill.batch.request_timeout`, standardmäßig `120` Sekunden, Umgebungsvariable `SEO_PRO_AI_FILL_BATCH_TIMEOUT`. Das ist der HTTP-Timeout für Einreichen, Statusabfrage und Abholen. Er ist länger als der synchrone `timeout`, weil die Einreichung alle Anfragen hochlädt und das Abholen die gesamte Ergebnisdatei streamt. Erhöhe ihn für sehr große Läufe.

::: tip Abholung einplanen
Da Einreichen und Abholen getrennt sind, kannst du einen Batch über einen Deployment-Hook oder einmaligen Befehl einreichen. Ein geplanter Aufruf von `seo-pro:ai-fill … --batch` alle 15–30 Minuten fragt den Status ab und übernimmt die Ergebnisse nach Abschluss. Ein dauerhaft laufender Prozess ist dafür nicht erforderlich.
:::

## Verarbeitung von Antworten {#how-replies-are-handled}

Jeder Aufruf liefert ein anbieterneutrales Ergebnisformat. Damit bleibt das Verhalten über Anbieter hinweg einheitlich:

- **Strukturierte Ausgabe bei Anbieterunterstützung.** Unterstützte OpenAI-Pfade mit Structured Outputs, Google mit Gemini `responseSchema` und Anthropic mit `output_config.format` lassen die API das JSON-Format vorgeben. Ungültiges JSON führt zu einem kontrollierten Fehler und wird nicht durch Textextraktion zurechtgerückt. Auch lokale beziehungsweise OpenAI-kompatible Server erhalten eine entsprechende Anfrage über `response_format`, jedoch nach dem Best-Effort-Prinzip. Ignoriert der Server dieses Feld, kann ein toleranter Fallback-Parser noch verwertbaren Text auslesen. Das Ergebnis ist entweder eine vollständige gültige Liste oder ein eindeutiger Fehler, niemals eine halb geparste Antwort.
- **Abgeschnittene Ausgaben sind ein ausdrücklicher Fehler.** Wird die Antwort am Ausgabetokenlimit abgeschnitten, liefert der Aufruf `truncated` mit dem Hinweis, `seo-pro.ai.max_output_tokens` zu erhöhen. Er liefert keinen unbemerkt verkürzten Titel. Das tritt besonders bei **Reasoning-/Thinking-Modellen** auf. Modelle mit passendem Namensmuster erhalten automatisch die höhere Untergrenze `reasoning_min_output_tokens`.
- **Ausgewählte vorübergehende Fehler werden automatisch wiederholt.** Ein Rate-Limit `429` oder ein `5xx` wird mit begrenztem exponentiellem Backoff wiederholt. Ein vorhandener `Retry-After`-Header wird innerhalb fester Grenzen berücksichtigt, damit ein manipulierter Wert die Anfrage nicht unbegrenzt aufhält. **Nicht automatisch wiederholt** werden ein falscher Schlüssel, eine fehlerhafte Anfrage, zu große Nutzdaten, ein **Timeout** oder ein Konto **ohne Guthaben beziehungsweise Kontingent**. Passe Wiederholungen im Block `retry` an; `max_attempts` auf `0` deaktiviert sie.
- **Fehler sind typisiert und bereinigt.** Jeder Fehler enthält einen stabilen Code wie `unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`, `content_too_large`, `bad_request`, `truncated`, `content_filtered` oder `provider_error` sowie bei vorübergehenden Fehlern ein Flag `retryable`. Die Meldung ist kurz und bereinigt. **Der normale Anwendungspfad zeigt oder protokolliert den rohen Anbieter-Antwortinhalt nicht. Das oben beschriebene optionale Evaluationswerkzeug bewahrt Antworten dagegen als Belege auf.** In Filament erscheint der Fehler im gestalteten Dialogbereich mit einem passenden nächsten Schritt für häufige Fälle; siehe [Fehlerbehebung](#troubleshooting).

## Fehlerbehebung {#troubleshooting}

Jeder Fehler erscheint an der jeweiligen Stelle, mit typisiertem Code und bereinigter Meldung, ohne andere Abläufe zu blockieren. Häufige Fälle und die passende Abhilfe:

| Symptom / Fehlercode | Bedeutung | Abhilfe |
|---|---|---|
| **`quota_exceeded`** – Guthaben oder Kontingent aufgebraucht | Der Schlüssel ist gültig, aber das **API-Konto hat kein verfügbares Guthaben oder Kontingent**. Es ist kein Rate-Limit; Wiederholen hilft nicht. Typische Anbietermeldungen sind bei Anthropic „credit balance is too low“, bei OpenAI ein Hinweis auf überschrittenes Kontingent und Abrechnung (`insufficient_quota`) und bei Google auf verbrauchtes Vorauszahlungsguthaben. | Lade Guthaben auf oder aktiviere die Abrechnung in der Anbieter-Konsole. Alternativ verwende ein selbst gehostetes **local**-Modell ohne Anbieter-API-Gebühr. Ein Claude-/ChatGPT-**Abonnement** finanziert nicht die **API**. |
| **`unauthorized`** – Authentifizierung fehlgeschlagen | Der Schlüssel fehlt, ist falsch oder gehört nicht zum konfigurierten Anbieter. | Prüfe die durch `seo-pro.ai.api_key_env` benannte Umgebungsvariable, standardmäßig `SEO_PRO_AI_API_KEY`: gesetzt, gültig und passend zu `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`** – Rate-Limit erreicht | Ein tatsächliches **vorübergehendes** Rate-Limit; automatische Wiederholungen wurden bereits versucht. | Warte und versuche es erneut oder verwende ein **lokales** Modell innerhalb der Kapazität deines Servers. Erhöhe bei großen Läufen mit niedrigem Kontingent `seo-pro.ai.fill.throttle_ms`. |
| **`timeout`** – Zeitlimit überschritten | Der Anbieter antwortete nicht innerhalb von `seo-pro.ai.timeout`, standardmäßig 15 Sekunden. Häufig bei **langsamen lokalen Reasoning-Modellen**. | Erhöhe `SEO_PRO_AI_TIMEOUT`. Bei unterstützten Ollama-Modellen kannst du außerdem `['think' => false]` in `seo-pro.ai.local.extra_body` setzen. |
| **`truncated`** – `max_output_tokens` erreicht | Die Antwort erreichte ihr Ausgabebudget, möglicherweise einschließlich verborgenem Reasoning. | Erhöhe `seo-pro.ai.max_output_tokens`; Reasoning-Modelle können 2000 oder mehr benötigen. Prüfe alternativ, ob der Modellname zu `reasoning_models` passt und die Untergrenze dadurch greift. |
| **`content_too_large`** (HTTP 413) | Der gesendete Seiteninhalt überschreitet das Anbieterlimit. | Senke `seo-pro.ai.max_input_chars`, um einen kürzeren Ausschnitt zu senden. |
| **`bad_request`** | Fehlerhafte Anfrage, häufig wegen eines nicht zugänglichen **Modellnamens** oder nicht unterstützten Parameters. | Prüfe, ob dein Schlüssel beziehungsweise Server das unter `SEO_PRO_AI_MODEL` konfigurierte Modell beim gewählten Anbieter erreichen kann. |
| **`content_filtered`** | Der Sicherheitsfilter des Anbieters hat eine Antwort abgelehnt. | Prüfe den Inhalt und die Hinweise des Anbieters. Wiederhole eine abgelehnte Anfrage nicht automatisch. |

::: tip Lokale Inferenz benötigt weiterhin einen funktionierenden Server
`SEO_PRO_AI_PROVIDER=local` mit selbst gehosteter Inferenz vermeidet Guthabenprobleme bei Cloud-Anbietern. Hardware, Modell, API-Kompatibilität, Timeout und Kapazität bleiben entscheidend. Ein über diesen Adapter konfiguriertes externes Gateway kann einen Schlüssel und Bezahlung verlangen.
:::

## Welche Daten deinen Server verlassen {#what-leaves-your-server}

Nur folgende Daten werden an deinen konfigurierten Anbieter gesendet, und nur nach einer ausdrücklichen Aktion wie einem Klick oder einem gestarteten Befehl:

- *Vorschläge*: der Klassenbasisname und Schlüssel des Modells, etwa „Post #3“, aktuell aufgelöster Titel und Beschreibung, die Canonical-URL sowie ein Klartextausschnitt ohne HTML, begrenzt durch `max_input_chars`, standardmäßig 6000 Zeichen.
- *Erklärungen von Befunden*: Typ, Schweregrad, Feld, Meldung und Ziel-URL des Befunds sowie aufgelöster Titel und Beschreibung des betroffenen Modells.
- *Überarbeitung der Beschreibung*: derselbe Seitenkontext wie bei Vorschlägen, ergänzt um Typ und Meldung eines gegebenenfalls übergebenen Scan-Befunds.
- *Vorschlag strukturierter Daten*: derselbe Seitenkontext wie bei Vorschlägen. Das Modell gibt nur einen Typ und einzelne Feldwerte zurück; das JSON-LD wird lokal zusammengesetzt.

Das Paket sammelt nicht gezielt Besucherdaten, IP-Adressen, Anfrageheader oder Zugangsdaten für Prompts und sendet kein vollständiges HTML. **Deine Inhaltsfelder und Ausschnitte können selbst sensible Informationen enthalten.** Prüfe, was deine Anwendung bereitstellt. Der Anbieterschlüssel dient der Authentifizierung der Anfrage. Referenz für die Datenverarbeitung ist `SECURITY.md` im Pro-Repository.
