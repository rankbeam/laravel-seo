---
description: "Indexierungsregeln an die Laravel-Umgebung binden: Außerhalb der Freigabeliste erzwingt der Schutz noindex und gibt eine Disallow-Regel für Crawler aus."
---

# Indexierungsschutz für nicht öffentliche Umgebungen {#indexing-guard-non-production-safety-net}

Wenn eine Staging- oder lokale Kopie deiner Website bei Google landet, kann das erhebliche SEO-Probleme verursachen: doppelte Inhalte neben den echten Seiten, eine private Umgebung im Index und aufwendige Bereinigung mit dem Tool zum Entfernen von URLs. Häufig fehlt ein `noindex` in einer vergessenen `.env`-Einstellung, oder ein Deployment hat eine Robots-Regel überschrieben.

Der **Indexierungsschutz** bindet die Indexierbarkeit an die Laravel-*Umgebung*, statt an einen manuell zu setzenden Schalter. Läuft die App außerhalb deiner Freigabeliste, erzwingt er für jede Seite `noindex,nofollow`, gibt in der verwalteten `robots.txt` eine Sperrregel für alle Crawler aus und weist im `seo:audit` darauf hin.

Die Funktion gehört zum kostenlosen Core.

## Was der aktive Schutz bewirkt {#what-it-does-when-active}

Wenn `app()->environment()` **nicht** in `seo.indexing_guard.allowed_environments` steht und der Schutz aktiviert ist, geschehen vier Dinge automatisch:

1. **Der Resolver erzwingt `noindex,nofollow` auf jeder Seite.** Dies erfolgt *oberhalb* der gesamten [Prioritätskette](/de/concepts/resolver-precedence) und überschreibt sogar einen explizit in `seo_meta` gespeicherten `robots`-Wert.
2. **Ein HTTP-Header `X-Robots-Tag: noindex,nofollow`** wird bei jeder über die App geleiteten Antwort gesendet. Siehe unten [Nicht-HTML-Antworten](#non-html-responses-pdfs-feeds-images).
3. **`SEO::robotsTxt()->build()` erzeugt eine `robots.txt` mit vollständiger Disallow-Regel**, ebenso eine `ai.txt`: `User-agent: *` und `Disallow: /`. Das gilt für den Befehl `seo:robots-txt` und die optionale [dynamische Route](/de/guide/ai-crawlers).
4. **`seo:audit` zeigt einen auffälligen Hinweis**, damit der durchgängige noindex-Zustand im Bericht erkennbar ist.

In freigegebenen Umgebungen, standardmäßig `production`, bleibt der Schutz vollständig **inaktiv**. Die gerenderte Ausgabe bleibt Byte für Byte unverändert.

## Nicht-HTML-Antworten: PDFs, Feeds und Bilder {#non-html-responses-pdfs-feeds-images}

Das erzwungene Robots-**Meta-Tag** erreicht nur Crawler, die HTML auswerten. PDFs, RSS-/Atom-Feeds, Bilder und andere Nicht-HTML-Antworten haben keinen `<head>`. Deshalb sendet der aktive Schutz dieselbe Anweisung über eine globale Middleware auch als HTTP-Header:

```http
X-Robots-Tag: noindex,nofollow
```

Header und Meta-Tag stammen aus derselben Quelle und stimmen daher überein. Der Header ist **innerhalb des Schutzes standardmäßig aktiviert**. Der Schutz selbst ist optional und in freigegebenen Umgebungen inaktiv. Deaktiviere den Header, wenn nur das Meta-Tag ausgegeben werden soll:

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Die Middleware wird **nur bei aktiviertem Schutz registriert**. Bei ausgeschaltetem Schutz kommt nichts zum Middleware-Stack hinzu.

::: warning Statische Dateien umgehen PHP
Eine Datei, die dein Webserver direkt aus `public/` ausliefert, durchläuft Laravel nicht und erhält diesen Header nicht. Schütze solche Dateien über Webserver- oder CDN-Konfiguration. Die Middleware erfasst die Antworten, die tatsächlich durch die App laufen.
:::

## Warum explizite Robots-Werte überschrieben werden {#why-it-overrides-an-explicit-robots-value}

Ansonsten gewinnt in Rankbeam ein explizit gespeicherter Wert; genau das ist die Aufgabe der Prioritätskette. Der Schutz bildet eine bewusste Ausnahme oberhalb der expliziten Ebene, weil hier ein bestimmtes Risiko vermieden werden soll:

- Eine Staging-Datenbank ist oft eine Kopie der Produktion. Ein gespeichertes `index,follow` würde dann auch auf Staging zur Indexierung auffordern.
- **Versehentlich indexiertes Staging ist schädlich; noindex auf Staging entspricht dem gewünschten Zustand.** Deshalb können gespeicherte Werte den Schutz in den Umgebungen, die niemals indexiert werden sollen, nicht aufheben.

## Aktivieren {#enabling-it}

Der Schutz ist bei Auslieferung **ausgeschaltet**. Installation oder Upgrade ändern die Ausgabe einer Nicht-Produktionsumgebung erst nach deiner Aktivierung. Das entspricht dem Verhalten von [`blank_is_unset`](/de/concepts/resolver-precedence) im Resolver und generierten OG-Bildern: Ohne Aktivierung bleibt die Ausgabe identisch. Eine Zeile genügt:

```dotenv
SEO_INDEXING_GUARD=true
```

Mit der Standard-Freigabeliste bleibt `production` unberührt, sodass du den Schutz in gemeinsamer Konfiguration aktiviert lassen kannst. Prüfe, ob jede Umgebung, die indexiert werden soll, auf der Liste steht. Die Aktivierung wird **ausdrücklich empfohlen**; für Core 4 kommt sie als Standard infrage.

Zum Deaktivieren genügt ebenfalls eine Zeile:

```dotenv
SEO_INDEXING_GUARD=false
```

## Freigegebene Umgebungen wählen {#choosing-which-environments-may-index}

Standardmäßig ist nur `production` freigegeben. Du kannst die Liste über eine kommaseparierte Umgebungsvariable ersetzen:

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

Oder in `config/seo.php`:

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

Die Einträge werden mit `Str::is()` abgeglichen. **Platzhalter** funktionieren daher: `'prod*'` passt zu `production` und `prod-eu`.

```php
'allowed_environments' => ['prod*'],
```

Eine **leere Liste** gibt *keine* Umgebung frei; der Schutz ist überall aktiv. Ein leerer oder nur aus Leerzeichen bestehender Wert von `SEO_INDEXING_GUARD_ALLOWED` fällt dagegen auf `['production']` zurück, damit eine versehentlich leere Variable die Produktion nicht still auf noindex setzt. Verwende ausdrücklich `[]` in der Konfiguration, wenn der Schutz wirklich überall gelten soll.

## Prüfen {#verifying-it}

`seo:audit` zeigt den Hinweis an und enthält bei `--json` den maschinenlesbaren Zustand:

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

Die ausgelieferte oder erzeugte `robots.txt` in einer geschützten Umgebung:

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Geltungsbereich {#scope}

Der Schutz steuert **Indexierungsanweisungen**: das Robots-Meta-Tag, den Header `X-Robots-Tag` und `robots.txt`. Titel, Beschreibungen, Canonicals und Schema bleiben unberührt. Er ist unabhängig von der [Robots-Ausgaberegel](/de/concepts/resolver-precedence) `seo.robots.emit_default`: Weil `noindex,nofollow` vom Website-Standard abweicht, wird es immer als Tag ausgegeben.
