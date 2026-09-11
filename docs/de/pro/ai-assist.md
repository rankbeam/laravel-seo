---
description: "Optionale KI-Unterstützung mit eigenem API-Schlüssel: Titel und Meta-Beschreibungen vorschlagen, Scan-Befunde erklären, Beschreibungen umformulieren und schema.org-Daten vorschlagen. Standardmäßig deaktiviert."
---

# KI-Unterstützung {#ai-assist}

Optionale KI-Unterstützung mit **deinem eigenen API-Schlüssel**: Vorschläge für Titel und Meta-Beschreibungen, verständliche Erklärungen von Scan-Befunden, eine **Überarbeitung der Beschreibung** und ein **Vorschlag für strukturierte Daten nach schema.org**. Die Funktion ist **standardmäßig deaktiviert**. Bei deaktiviertem Schalter wird kein KI-Codepfad ausgeführt.

Drei Grundsätze bestimmen die Umsetzung:

- **Dein Schlüssel, dein Anbieter.** Anfragen gehen von *deinem Server* direkt an den von *dir* konfigurierten Anbieter: Anthropic, OpenAI, Google oder einen lokalen beziehungsweise OpenAI-kompatiblen Server. Anfallende Gebühren werden deinem Konto berechnet. Das Paket vermittelt oder verkauft keine API-Nutzung weiter, rechnet sie nicht selbst ab und versendet keine Telemetrie.
- **Interaktive Vorschläge erfordern eine ausdrückliche Übernahme.** Die Auswahl füllt das Formular; Dashboard-Korrekturen benötigen Apply. Seit Pro 2.42 speichert der Massenbefehl private Entwürfe. `--auto-apply` aktiviert ausdrücklich das sofortige Schreiben.
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

**Pro 2.42 ändert den CLI-Standard:** `seo-pro:ai-fill` speichert einen privaten Entwurf pro generiertem Feld. Veröffentlichte Metadaten bleiben bis zur Freigabe unverändert. Vorhandene Werte und berechenbare Ersatzwerte werden übersprungen; ein aktueller offener Entwurf verhindert erneute Generierung.

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review` listet die ersten 100 offenen Entwürfe als JSON. Mit einer ID liest du Wert und private Nachweise. Freigabe und Ablehnung funktionieren bei deaktivierter KI ohne Anbieteraufrufe. Die Freigabe verlangt eine Bedienerkennung und lehnt Entwürfe ab, wenn Quelle oder Zielmetadaten geändert oder der Datensatz gelöscht wurden. Die Kennung ist eine Angabe des Bedieners, kein Nachweis einer inhaltlichen menschlichen Prüfung. `--connection=NAME` wählt eine konfigurierte Datenbankverbindung.

`--auto-apply` veröffentlicht noch fehlende Felder sofort. `--force` überspringt die Bestätigung, **nicht die Prüfung**. `--dry-run` generiert und zeigt Werte ohne Entwürfe oder Metadaten zu speichern, ruft aber den Anbieter auf und kann Kosten verursachen. `--field`, `--limit` und `--locale` begrenzen den Lauf. Prüfe geplante Befehle nach dem Upgrade.

### Große Sammlungen: Taktung, Kostenschätzung und Fortsetzung nach Abbruch {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms` beträgt standardmäßig 200 Millisekunden. Ab `confirm_over`, standardmäßig 100 Datensätzen, zeigt der Befehl die Schätzung aus `seo-pro.ai.pricing` und fragt vor der Generierung. Checkpoints erhalten abgeschlossene Felder bei Unterbrechungen. Ein Timeout nach Annahme durch den Anbieter kann trotzdem doppelte Kosten verursachen; kläre ungewisse Arbeit vor `--fresh`. Starte nur einen passenden Lauf gleichzeitig.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

Eigene Integrationen verwenden `review: true` für Entwürfe. Die PHP-API behält aus Kompatibilitätsgründen `apply: true, review: false`: bestehende Aufrufe schreiben weiterhin sofort. `apply: false` zeigt nur eine Vorschau. `filled` zählt bearbeitete Datensätze einschließlich Entwürfen im Prüfmodus; die CLI nennt diese `staged`.

### Batch-Modus (50 % günstiger) {#batch-mode-50-cheaper}

`--batch` verwendet den unterstützten asynchronen Endpunkt von Anthropic oder OpenAI. Die Schätzung berücksichtigt den dokumentierten Rabatt; prüfe aktuelle Modellpreise. Google- und lokale Adapter arbeiten sequenziell. Sende zuerst und führe denselben Befehl später erneut aus, um Entwürfe abzuholen:

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

Anbieter, Sprache und Veröffentlichungsmodus müssen beim Senden und Abholen gleich bleiben. Prüfung und `--auto-apply` haben getrennte Checkpoints; die CLI verhindert den anderen Modus bei einem passenden laufenden Batch. Ungewisse Übermittlung verlangt Klärung. Teilerfolge bleiben erhalten, vorübergehende Fehler können wiederholt werden. Abholen prüft fehlende Felder erneut; Freigabe prüft auch den Quellzustand vor dem Senden. `seo-pro.ai.fill.batch.request_timeout` beträgt 120 Sekunden. Geplantes Abholen speichert standardmäßig Entwürfe.

## Herkunft, Migrationen und Datenfilter {#origin-review-and-filtering}

Benötigt **Core 3.21 und Pro 2.42**. Core lädt seine Migration automatisch; veröffentliche Pro-Migrationen und migriere jede Datenbank der SEO-Modelle vor der Generierung gespeicherter Vorschläge:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

`seo_ai_proposals` speichert generierte Werte und Anbieter-/Modell-/Anfragenachweise mit verschlüsselten Laravel-Casts. Sichere `APP_KEY` und dessen Backup: ohne Schlüssel sind die Werte unlesbar. Datensatzkennungen, Status und Entscheidungen bleiben normale Spalten. Alternativen aus verlassenen Formularen können `offered` oder `selected` bleiben. Es gibt keine automatische Löschung: definiere Aufbewahrungsregeln, erhalte offene Entwürfe und durch `seo_meta.ai_provenance` referenzierte Nachweise und beschränke Datenbankexporte sowie Befehlsausgaben.

Übernommene Formularvorschläge, Dashboard-Korrekturen und Massenwerte behalten ihre Feldherkunft. Spätere Eloquent-Änderungen erhalten `origin: ai` und setzen `edited: true`; das bedeutet Änderung, nicht menschliche Prüfung. Leeren entfernt die Markierung. Core-HTML, Arrays, JSON und Inertia geben nur Feld, Herkunft und Änderungsstatus aus, gegebenenfalls im eigenen Meta-Tag `rankbeam:ai-origin`. Generierungs-IDs und Anbieterdetails bleiben privat. Veröffentliche keine rohen `SEOMeta`-Modelle über APIs.

Erfasst werden künftige unterstützte Speicherwege, keine historischen Inhalte oder vollständigen Änderungsversionen. Direktes SQL, Query-Builder-Updates und eigene Renderer können die Kontrollen umgehen. Setze die Herkunft bei einer unabhängig verfassten Neufassung ausdrücklich zurück, wenn gerechtfertigt; normale Änderungen behalten sie. Die Markierung ist weder standardisiertes Wasserzeichen noch manipulationssicherer Nachweis oder Aussage zur Konformität mit Artikel 50. Ausgabequalität und native Anbietermarkierungen benötigen eine gesonderte Bewertung.

Optional kannst du `AiPromptFilter` implementieren und `seo-pro.ai.context_filter` konfigurieren. Der Filter verarbeitet den zusammengesetzten Benutzerprompt vor synchroner oder Batch-Übermittlung; ein Fehler verhindert das Senden mit bereinigter Meldung. Systemanweisungen bleiben unverändert. Standard ist `null`, **ohne automatische Bereinigung sensibler Daten**. Das Beispiel ersetzt nur einen bekannten Wert; implementiere und teste anwendungsspezifische Regeln:

```php
namespace App\Support;

use Rankbeam\Seo\Pro\Ai\AiPromptFilter;

final class RedactAiContext implements AiPromptFilter
{
    public function filter(string $prompt): string
    {
        return str_replace('internal@example.com', '[redacted]', $prompt);
    }
}

// Configure seo-pro.ai.context_filter with this class in config/seo-pro.php.
// Runtime equivalent:
config(['seo-pro.ai.context_filter' => RedactAiContext::class]);
```


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
- *Erklärungen von Befunden*: Typ, Schweregrad, Feld, Meldung und Ziel-URL; bei verfügbarem Modell auch Klasse/Schlüssel, aufgelöster Titel und Beschreibung, kanonische URL sowie begrenzter Klartextauszug.
- *Überarbeitung der Beschreibung*: derselbe Seitenkontext wie bei Vorschlägen, ergänzt um Typ und Meldung eines gegebenenfalls übergebenen Scan-Befunds.
- *Vorschlag strukturierter Daten*: derselbe Seitenkontext wie bei Vorschlägen. Das Modell gibt nur einen Typ und einzelne Feldwerte zurück; das JSON-LD wird lokal zusammengesetzt.

Das Paket sammelt nicht gezielt Besucherdaten, IP-Adressen, Anfrageheader oder Zugangsdaten für Prompts und sendet kein vollständiges HTML. **Deine Inhaltsfelder und Ausschnitte können selbst sensible Informationen enthalten.** Prüfe, was deine Anwendung bereitstellt. Der Anbieterschlüssel dient der Authentifizierung der Anfrage. Referenz für die Datenverarbeitung ist `SECURITY.md` im Pro-Repository.
