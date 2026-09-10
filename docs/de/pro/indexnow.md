---
description: "Suchmaschinen über veröffentlichte oder geänderte URLs informieren. Pro sendet an api.indexnow.org, das Meldungen an teilnehmende Suchmaschinen weitergibt. Standardmäßig aus."
---

# IndexNow: URLs bei Veröffentlichung melden {#indexnow-—-push-on-publish-indexing}

Mit **IndexNow** kannst du Suchmaschinen unmittelbar über veröffentlichte oder geänderte URLs *informieren*, statt auf deren nächsten Crawl zu warten. Pro sendet einmal an den gemeinsamen Endpunkt `api.indexnow.org`, der die Meldung an **alle teilnehmenden Suchmaschinen** weitergibt. Die [offizielle FAQ](https://www.indexnow.org/faq) nennt Amazon, Bing, Naver, Seznam, Yandex und Yep. Eine Meldung garantiert keine Indexierung.

Die Funktion ist **standardmäßig aus**. Netzwerkzugriffe erfolgen erst nach Aktivierung und Übermittlung einer URL.

## Einrichtung {#setup}

### 1. Einen Schlüssel erzeugen {#_1-generate-a-key}

IndexNow prüft die Kontrolle über den Host mit einem **Schlüssel**. Pro akzeptiert 8–128 Zeichen aus `[a-f0-9-]`; eine Hex-Zeichenkette mit 32 Zeichen eignet sich dafür. Erzeuge den Schlüssel einmal, behalte ihn bei und stelle ihn über die Umgebung bereit:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip Der Schlüssel wird über die Konfiguration gelesen und übersteht `config:cache`
Anders als Search-Console-Zugangsdaten ist der IndexNow-Schlüssel **kein Geheimnis**. Er wird öffentlich unter `/{key}.txt` ausgeliefert, um die Host-Kontrolle nachzuweisen. Pro liest ihn deshalb über `indexnow.key`, standardmäßig `env('SEO_PRO_INDEXNOW_KEY')`. Werte, die **nur in `.env`** stehen, sind nach `config:cache` für `env()` nicht mehr verfügbar, weil Laravel diese Datei dann nicht lädt. Echte Prozess-Umgebungsvariablen bleiben lesbar. Über die Konfiguration wird der Schlüssel bereits beim Aufbau des Konfigurationscaches erfasst. **Nach einem Schlüsselwechsel musst du `php artisan config:cache` erneut ausführen.** Der Schlüssel wird nicht protokolliert. Gibt die Schlüsseldatei in Produktion 404 zurück, siehe [Server mit Konfigurationscache](#config-cached-servers).
:::

### 2. Die Schlüsseldatei ausliefern {#_2-serve-the-key-file}

IndexNow ruft `https://{host}/{key}.txt` ab. Die Datei enthält nur den Schlüssel. Bei standardmäßig aktiviertem `route` **liefert Pro sie aus**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Nur der konfigurierte Schlüsselpfad liefert Inhalt. Andere von dieser Route abgefangene Pfade ergeben 404; bei deaktiviertem IndexNow liefert die gesamte Route 404. Möchtest du die Datei selbst oder über ein CDN bereitstellen, schalte `route` aus und setze `key_location` auf die entsprechende URL.

## URLs übermitteln {#submitting-urls}

### Automatisch beim Speichern {#automatically-on-save-the-push-on-publish-path}

Ergänze das Modell um den Trait und aktiviere `auto_submit`. Jeder Speichervorgang reiht eine Meldung für `getUrlForSEO()` des Modells ein:

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

Der Trait berücksichtigt den Veröffentlichungsstatus. Mit `shouldSubmitToIndexNow(): bool` steuerst du ihn vollständig. Andernfalls wird ein vorhandenes Attribut `is_published` verwendet; fehlt auch dieses, wird bei jedem Speichern übermittelt. Die Meldung wird immer **eingereiht**, sodass das Speichern nicht auf die HTTP-Antwort des IndexNow-Endpunkts warten muss.

### Manuell {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` verwendet standardmäßig die Queue. Mit `queue: false` wird direkt im Prozess gesendet.

### Über die Befehlszeile {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Nur URLs desselben Hosts
Jede URL muss `http(s)` verwenden **und** zum konfigurierten `host` gehören. Andere URLs werden verworfen und gezählt, aber nicht gesendet. Übermitteln lassen sich nur URLs des kontrollierten Hosts; abweichende Hosts würde auch der Endpunkt ablehnen. Listen mit mehr als `max_urls_per_request`, standardmäßig der Protokollgrenze 10000, werden automatisch aufgeteilt.
:::

## Konfiguration {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## Wiederholungen {#how-retries-work}

Der eingereihte `SubmitToIndexNowJob` wiederholt vorübergehende Fehler wie `429`, `5xx` und Timeouts mit `backoff` bis zur konfigurierten Anzahl `tries`. Dauerhafte Clientfehler wie `400`, `403` oder `422`, etwa ungültiger Schlüssel oder falscher Host, werden protokolliert und **beenden** den Versuch ohne weitere Wiederholungen. `200` und `202` gelten als Erfolg: empfangen beziehungsweise ausstehende Schlüsselprüfung.

Verwende in Produktion eine **eigene Queue**, damit ein langsamer Endpunkt keine nutzerbezogenen Aufgaben verzögert:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Fehlerbehebung {#troubleshooting}

### Server mit Konfigurationscache {#config-cached-servers}

Wenn `/{key}.txt` in Produktion 404 liefert oder Übermittlungen trotz `indexnow.enabled = true` nichts tun, prüfe einen möglicherweise **nur in `.env`** vorhandenen Schlüssel. Nach `php artisan config:cache` lädt Laravel `.env` nicht mehr. Ein erst zur Laufzeit daraus gelesener Schlüssel kann dann null sein: Die Schlüsselroute wird nicht registriert und Übermittlungen gelten als nicht konfiguriert.

Die Standardkonfiguration liest `indexnow.key` bereits beim Cache-Aufbau aus `env(...)`, womit eine gewöhnliche Einrichtung funktioniert. Probleme entstehen insbesondere, wenn du nach Veröffentlichung der Konfiguration den **`env(...)`-Standard entfernt** oder einen **eigenen `key_env`-Namen verwendet hast, der nur in `.env` existiert**. Zwei Lösungen:

1. **Schlüssel über die Konfiguration beziehen**, empfohlen. Belasse `indexnow.key` bei `env('SEO_PRO_INDEXNOW_KEY')` oder setze einen festen Wert und führe `php artisan config:cache` erneut aus. Nach einem Schlüsselwechsel ist ein neuer Cache nötig.
2. **Eine echte Umgebungsvariable bereitstellen**. Setze `SEO_PRO_INDEXNOW_KEY` für Betriebssystem beziehungsweise Prozess, etwa mit `env[...]` im PHP-FPM-Pool, `Environment=` in systemd oder den Umgebungsvariablen deiner Plattform. Solche Variablen bleiben auch bei gecachter Konfiguration lesbar.

Prüfe mit `php artisan seo:doctor`. Bei diesem Zustand meldet der Befehl **„IndexNow is enabled but no valid key resolves“** samt Korrekturhinweis. Pro protokolliert außerdem einmal pro Prozess eine Warnung, wenn die App mit Konfigurationscache und unlesbarem Schlüssel startet.

::: tip Google
Google nimmt **nicht** an IndexNow teil. Verwende für Google die [Search-Console-Integration](/de/pro/search-console) und eine aktuelle Sitemap.
:::
