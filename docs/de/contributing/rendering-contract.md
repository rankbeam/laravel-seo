---
description: "Die verbindliche Checkliste für den Head jedes Frontend-Stacks, der Rankbeam-SEO-Daten rendert. Grundlage der Core-Renderer-Tests und Referenz-Apps."
---

# Der Rendering-Vertrag {#the-rendering-contract}

Dies ist die **maßgebliche gemeinsame Checkliste** für den `<head>` jedes Frontend-Stacks, der Rankbeam-SEO-Daten ausgibt. Sie bildet die Grundlage für:

- die Unit-Tests der Renderer-Struktur im Core, `tests/Unit/Services/RenderingContractTest.php`, als schnellen, frameworkunabhängigen Teil der Paket-CI;
- die Referenz-Apps je Stack in `rankbeam-examples`, also Blade, Inertia mit Vue, React oder Svelte und Livewire, deren Browser- und SSR-Tests dieselben Anforderungen an einem echten DOM prüfen;
- die Framework-Anleitungen für Blade, Inertia/JSON und Livewire, deren Beispiele diesen Vertrag einhalten müssen.

Kann ein Stack eine Anforderung nicht erfüllen, ist das ein **Fehler oder eine dokumentierte Einschränkung**. Der Vertrag wird dafür nicht abgeschwächt. Die Datenschicht `SEOResolver` → unveränderliches `SEOData` → `TagRenderer` ist frameworkunabhängig. Nur der Weg der aufgelösten Daten ins DOM, ihr Erhalt bei clientseitiger Navigation und ihre Sichtbarkeit für Crawler unterscheiden sich je Stack. Genau diese Unterschiede regelt der Vertrag.

> Die Spezifikation wurde durch ein unabhängiges Design-Review geprüft und präzisiert.
> Ein erneutes Review ist bei wesentlichen Änderungen nötig.

---

## 1. Werte: Inhalt eines vertragskonformen `<head>` {#_1-values-—-what-a-compliant-head-contains}

### Titel, Beschreibung und Canonical {#title-description-canonical}

- **Genau ein `<title>`** mit dem *aufgelösten* Titel, niemals mit doppeltem Suffix. Der Resolver hängt `seo.title_suffix` einmal an und prüft, ob der Titel bereits damit endet.
- **Eine Meta-Beschreibung**, sofern eine Beschreibung aufgelöst wurde; kein leeres Tag.
- **Ein `<link rel="canonical">`**.

### Robots {#robots}

- `<meta name="robots">` **nur ausgeben, wenn die Anweisung vom Website-Standard abweicht**. Ohne ein redundantes `index,follow` gilt für den Crawler bereits index,follow. Der Vergleich ignoriert Leerzeichen (`index, follow` ≡ `index,follow`); eine abweichende Anweisung wird **unverändert** ausgegeben. `seo.robots.emit_default = true` erzwingt das Tag.
- Deterministische **erweiterte Anweisungen** unterstützen: `noindex`, `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`, `max-video-preview`, `notranslate`, `unavailable_after`. Dies sind aufgelöste Zeichenketten; ihre **Priorität folgt der Resolver-Kette** von global über Route und Modell bis explizit. Gleiche Eingaben ergeben gleiche Ausgaben.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`, `og:locale`.
- `article:*` mit `published_time`, `modified_time`, `author`, `section` und `tag` **nur bei `og:type === 'article'` und tatsächlich vorhandenem Wert**. Keine erfundenen Werte und keine Ausgabe auf anderen Seitentypen.
- `og:image` mit `og:image:width`, `og:image:height`, `og:image:alt` und `og:image:type`, **soweit bekannt**. Mehrere Bilder werden **gruppiert**: Auf jedes `og:image` folgen unmittelbar seine eigenen Angaben zu Abmessungen, Alternativtext und Typ.

### Twitter Cards {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` und `twitter:image:alt`, sofern ein Bild-Alternativtext bekannt ist.
- `twitter:site` und `twitter:creator` sind **optional und unabhängig**. Eines kann ohne das andere vorhanden sein; keines wird aus dem anderen erfunden.

### hreflang und Locale {#hreflang-locale}

- Hreflang erhält über den Modell-Hook `getSEOAlternates()` einen eigenen Resolver-Pfad.
- Vorhandene Hreflang-Alternativen sind **absolut, normalisiert und pro Sprache eindeutig**. Bei vollständigen Daten verweisen sie gegenseitig aufeinander. `x-default` erscheint nur nach Konfiguration.
- `og:locale:alternate` bildet **nur** Locales mit einer tatsächlichen Social-Variante ab. `en-US` wird auf `en_US` abgebildet; verglichen wird diese Form, nicht wörtliche Gleichheit verlangt.
- `<html lang>` entspricht der aufgelösten Locale. Diese Anforderung gehört zum Vertrag, obwohl die *App* das `<html>`-Element ausgibt.

### JSON-LD pro Seite {#per-page-json-ld}

- Parsebar und gegen vorzeitiges `</script>` abgesichert. Die Nutzdaten werden mit `JSON_HEX_TAG` kodiert, damit kein Wert das Script-Element vorzeitig beenden kann; dies schützt vor gespeicherten XSS-Payloads an dieser Ausgabestelle.
- **Mehrere `<script>`-Blöcke oder ein zusammengefasster `@graph`** sind gleichermaßen zulässig.
- Eine stabile `@id` wird **dort verwendet, wo Entitäten tatsächlich verknüpft sind**, etwa Organization ↔ WebSite ↔ WebPage. Eigenständige Knoten benötigen keine stabile `@id`.

---

## 2. Normalisierung und Invarianten {#_2-normalization-invariants}

- **Absolute `http(s)`-URLs** für `canonical`, `og:url`, `og:image` und `twitter:image`. **Keine leeren oder nullwertigen Tags** gelangen ins DOM.
- **`canonical` und `og:url` MÜSSEN dieselbe normalisierte URL ergeben.** Eine Abweichung ist ein **harter Fehler**, keine Warnung.
- Die **Canonical-Normalisierung ist durchgängig konsistent**: Schema, Host, Port, Groß-/Kleinschreibung im Pfad, Query-Freigabeliste und abschließender Schrägstrich werden immer gleich behandelt. Indexierbare Seiten verweisen auf **sich selbst**. Eine `noindex`-Seite übernimmt **nicht** die Canonical-Strategie einer anderen Seite.
- **Escaping richtet sich nach dem Ausgabeziel**: HTML-Attribute, Text und JSON verwenden jeweils den passenden Encoder. Assertions vergleichen **dekodierte Bedeutungswerte, keine Bytes**.
- **Renderer-Parität ist semantisch, nicht byteweise.** `render()` als HTML ≡ `toArray()` ≡ `toInertiaHead()` *nach Normalisierung*. Reihenfolge und Form der Tags dürfen sich zwischen den Darstellungen unterscheiden. Regeln für einmalige und wiederholbare Eigenschaften sind ausdrücklich definiert: ein `og:title`, mehrere `article:tag`.
- **Zuständigkeit für Tags**: Ein Client-Renderer ersetzt die vom *Paket verwalteten* Tags anhand ihrer Schlüssel aus §4, ohne fremde Tags der App zu löschen.

---

## 3. Verhalten bei clientseitiger Navigation {#_3-behaviour-—-client-side-navigation}

Nach jedem Inertia-Besuch oder Livewire-`wire:navigate` gilt:

- Von jedem einmaligen Element, also `<title>`, Beschreibung, Canonical und den entsprechenden `og:*`-/`twitter:*`-Eigenschaften, existiert **genau eines und keines ist veraltet**.
- **JSON-LD sammelt sich nicht an**. Das Schema der vorherigen Seite wird entfernt. Da Livewire `<script>` als nicht entfernbares Asset behandelt, erhalten Schema-Scripts `data-seo-schema` und eine URL-bezogene ID. Die Scripts der vorherigen Seite werden bei `livewire:navigated` entfernt; siehe Livewire-Anleitung.
- Beim Wechsel von einer **metadatenreichen zu einer schlichten Seite werden zusätzliche Tags entfernt**. Die schlichte Seite behält weder Beschreibung noch Open Graph oder Schema der vorherigen Seite.
- **Keine Hydration-Warnungen**; die Metadaten bleiben vor und nach der Hydration semantisch identisch.

---

## 4. Inertia-Head-Keys und Tag-Zuständigkeit {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` versieht jeden Meta- und Link-Eintrag mit einem stabilen **`head-key`**. Inertia dedupliziert Head-Elemente anhand dieses Attributs: Ein Tag im Seiten-`<Head>` mit demselben `head-key` wie ein Layout-Tag **ersetzt** dieses, statt ein Duplikat hinzuzufügen.

- Basisschlüssel: `name ?? property` für Meta-Tags, `rel` für Links.
- **Wiederholbare Tags erhalten unterscheidbare Schlüssel**, damit jeder eindeutig bleibt: `article:tag` → `article:tag`, `article:tag:1`, …; Hreflang → `alternate:en-US`, `alternate:fr-FR`.

Binde ihn in Templates als **`:head-key`**. Vues `:key` dient dem unabhängigen Abgleich von `v-for` und bewirkt keine Head-Deduplizierung in Inertia.

---

## 5. Sichtbarkeit für Crawler nach Rendering-Modus {#_5-crawler-visibility-explicit-modes}

- **SSR und Prerendering MÜSSEN den vollständigen Vertrag im rohen HTML der HTTP-Antwort erfüllen**. Dies wird getrennt vom hydrierten DOM mit deaktiviertem JavaScript geprüft.
- **Reines CSR darf keine Crawler-Konformität behaupten.** Eine Inertia-App ohne SSR fügt Meta-Tags clientseitig ein. Das zuerst abgerufene HTML enthält keine SEO-Meta-Tags. Diese Einschränkung wird dokumentiert: **Für Crawler sichtbare Meta-Tags benötigen Inertia SSR oder Prerendering**. Auch JSON-LD für Crawler sollte serverseitig gerendert werden.

---

## 6. Nicht abgedeckt {#_6-out-of-scope-non-goals}

- **Aufgaben der App**: `charset`, `viewport` und Favicons. `<meta charset>` muss vor Metadaten mit Nicht-ASCII-Zeichen stehen; die App ist für diese Reihenfolge im Head zuständig.
- **End-to-End-Tests prüfen nur die ausgegebene Darstellung.** Sie bestätigen weder Google-Indexierung noch Canonical-*Auswahl*, Rich-Result-Berechtigung oder Ranking. Auch MIME-Typ und Verfügbarkeit externer Bilder werden nicht bestätigt. Solche Prüfungen gehören in optionale Integrations- oder HTTP-Tests, nicht in die Browser-Matrix.

---

## 7. Stand der Konformität {#_7-conformance-status}

Nachweise je Anforderung: **Unit** steht für `RenderingContractTest` im Core und die Paket-CI. **Browser/SSR** bezeichnet `rankbeam-examples` mit geplanter Testmatrix. **App** bedeutet, dass die Host-Anwendung zuständig ist. **Geplant** kennzeichnet ein Ziel des Vertrags, dessen Daten `SEOData` noch nicht modelliert; der Renderer gibt deshalb nur die verfügbare Teilmenge aus.

| Anforderung | Stand |
|---|---|
| Genau ein aufgelöstes `<title>`, kein doppeltes Suffix | **Unit** + Browser |
| Meta-Beschreibung nur bei vorhandenem Wert | **Unit** + Browser |
| Ein `<link rel="canonical">`, niemals leer | **Unit** + Browser |
| Robots nur bei Abweichung vom Standard, unverändert; Schalter `emit_default` | **Unit** + Browser |
| Erweiterte Robots-Anweisungen über die Resolver-Priorität | **Unit** (Resolver) |
| `og:title/description/type/url/site_name/locale`; Locale `en-US`→`en_US` | **Unit** + Browser |
| `article:*` nur bei `og:type=article` und vorhandenem Wert | **Unit** + Browser |
| `og:image` vorhanden und absolut | **Unit** + Browser |
| `og:image:width/height/alt`, `og:image:type`, Gruppierung mehrerer Bilder | **Geplant**: `SEOData` enthält eine einzelne Zeichenkette `ogImage`, noch keine Abmessungen, Alternativtexte oder Typen. Der Renderer gibt ein absolutes `og:image` aus. |
| `twitter:card/title/description/image`; `site` und `creator` unabhängig | **Unit** + Browser |
| `twitter:image:alt` | **Geplant**: Noch kein Feld für den Bild-Alternativtext modelliert. |
| Hreflang absolut und pro Sprache eindeutig | **Unit** + Browser |
| Gegenseitige Hreflang-Verweise, `x-default` nach Konfiguration | Browser, abhängig von den Daten |
| `og:locale:alternate` entspricht tatsächlichen Social-Varianten | **Geplant**: Noch keine Zuordnung von Social-Varianten je Locale modelliert. |
| Übereinstimmendes `<html lang>` | **App**, zusätzlich im Browser geprüft |
| JSON-LD parsebar und gegen vorzeitiges `</script>` abgesichert | **Unit** + Browser |
| Mehrere Scripts oder `@graph`; stabile `@id` bei verknüpften Entitäten | **Unit** (Merchant-Graph) + Browser |
| Absolute URLs; keine leeren oder nullwertigen Tags | **Unit** + Browser |
| `canonical` ≡ `og:url`; harter Fehler bei Abweichung | **Unit** + Browser |
| Konsistente Canonical-Normalisierung, Selbstverweis, getrennte noindex-Behandlung | Browser |
| Escaping je Ausgabeziel; dekodierte semantische Parität | **Unit** |
| Semantische Renderer-Parität: `render()` ≡ `toArray()` ≡ `toInertiaHead()` | **Unit** |
| Stabiler Inertia-`head-key`; unterscheidbare wiederholbare Tags | **Unit** + Browser |
| Client-Navigation: einzelne aktuelle Tags, kein angesammeltes JSON-LD, Entfernung alter Tags | Browser; der Renderer liefert die nötigen `data-seo-schema`-Hooks |
| Keine Hydration-Warnungen; Parität vor und nach Hydration | Browser |
| SSR erfüllt den Vertrag im rohen HTML; reines CSR als nicht konform dokumentiert | Browser + Dokumentation |

**Geplante Anforderungen** sind bewusst dokumentierte Lücken. Der Vertrag bleibt das dauerhafte Ziel; die Erweiterungen sollen additiv und abwärtskompatibel in einer späteren Aufgabe erfolgen. Sie benötigen neue `SEOData`-Felder beziehungsweise Spalten und eine SemVer-Minor-Version. Bis dahin gibt der Renderer die verfügbare Teilmenge aus und erfindet keine fehlenden Werte.
