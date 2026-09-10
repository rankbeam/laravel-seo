---
description: "Genera immagini Open Graph 1200×630 da template Blade con un browser headless. Funzione gratuita del core, disattivata per impostazione predefinita."
---

# Immagini OG generate {#generated-og-images}

Dal core 3.20, il rendering Chrome disabilita JavaScript e blocca le richieste di risorse HTTP(S), FTP e WebSocket. I template personalizzati devono usare HTML/CSS statici e risorse incorporate, come quelli inclusi.

Una pagina senza immagine social usa normalmente la stessa `default_og_image` del sito. Questa funzione genera invece una card Open Graph / Twitter **1200×630 per pagina**, da un template Blade, tramite un browser headless e [spatie/browsershot](https://github.com/spatie/browsershot). Il browser gestisce gli a capo, il troncamento e i font disponibili per il testo.

La funzione appartiene al core gratuito ed è **disattivata per impostazione predefinita**. Quando è disattivata, continua a essere usata `default_og_image`, senza aggiungere dipendenze di rendering.

::: info Generazione anticipata
Le card si generano con un comando Artisan, non durante una richiesta web. La pagina usa il file generato solo quando esiste già sul disco. Non c'è un endpoint che avvia il browser alla visita della pagina; vedi i [limiti](#caveats).
:::

## Requisiti {#requirements}

Il driver browser è facoltativo. Per usarlo, installa nell'applicazione:

```bash
composer require spatie/browsershot
```

Servono inoltre:

- **Node.js** sulla macchina.
- **Puppeteer** nella directory principale dell'applicazione:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium:** Puppeteer scarica normalmente il proprio browser. In produzione puoi indicare il Chrome di sistema con [`chrome_path`](#configuration).

::: warning Su Windows installa Puppeteer nella directory dell'app
Non affidarti a `npm_module_path`: viene passato a `setNodeModulePath()` di Browsershot, che usa il prefisso POSIX `NODE_PATH=…`, inefficace su Windows. Installa `puppeteer` nella directory principale dell'applicazione, da cui Node può risolverlo.
:::

## Attivazione {#enabling}

Pubblica la configurazione, se manca, con `php artisan vendor:publish --tag=seo-config`, quindi attiva la funzione:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Genera le immagini prima di aspettarti che compaiano nelle pagine:

```bash
php artisan seo:og-images
```

## Risoluzione dell'immagine {#how-resolution-works}

Una card generata non sostituisce un'immagine impostata per la pagina. Il resolver la usa solo quando `og:image` è vuoto oppure coincide ancora con il `default_og_image` statico del sito. Un'immagine del modello, da `getSEOImage()`, `seo_meta` o dal contenuto, ha la precedenza.

Il resolver calcola il percorso di archiviazione e restituisce l'URL pubblico **solo se il file esiste sul disco configurato**. Non avvia il rendering:

- La richiesta web non avvia un browser; in mancanza della card resta il default statico.
- La pagina non collega un file generato che ancora non esiste.

Dopo modifiche al contenuto, esegui [`seo:og-images`](#the-seo-og-images-command) durante il deploy o tramite pianificazione.

## Comando `seo:og-images` {#the-seo-og-images-command}

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*`: una o più classi, ripetibile. Senza opzione usa `seo.og_image.models`, con fallback a `seo.sitemap.models`, come descritto nella [guida sitemap](/it/guide/sitemaps).
- `--force`: rigenera anche i file esistenti, utile dopo una modifica al template senza incremento di `cache_version`.
- `--prune`: dopo la generazione elimina le card che non corrispondono più ai contenuti correnti. Rimuove solo nomi con il formato hash generato, non gli altri file nella directory. È ignorato se usi `--model`, perché il comando non conoscerebbe i file da conservare per gli altri modelli.

Ogni modello deve usare `HasSEO`. I record senza titolo vengono saltati. Il comando riporta i conteggi `generated`, `skipped`, `failed` e, con `--prune`, `pruned`.

### Pianificazione {#scheduling}

Aggiorna le card e ripulisci quelle lasciate dai titoli precedenti:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Invalidazione della cache {#the-invalidation-model}

Il nome del file deriva da un hash dei dati usati per la card: titolo, nome del sito, template, driver, dimensioni, colori, `cache_version` e versione installata del pacchetto.

- **Cambio di titolo:** cambia l'hash e quindi il nome del file. La pagina usa il default statico finché non generi la nuova card. Il vecchio file diventa orfano e può essere eliminato con `--prune`.
- **Cambio di `cache_version` o aggiornamento del pacchetto:** cambiano gli hash di tutte le card. Incrementa `cache_version` dopo modifiche al contenuto del template; la versione del pacchetto viene già inclusa automaticamente.

## Template inclusi {#bundled-templates}

I tre template usano dimensioni 1200×630 e lo stesso gradiente configurato:

| Template | Uso | Contenuto |
|---|---|---|
| `seo::og.default` | Generico | Titolo e nome del sito |
| `seo::og.article` | Articoli e notizie | Sezione, titolo, autore e data |
| `seo::og.product` | Prodotti e annunci | Marchio, categoria, titolo e descrizione |

Scegli un template globale con `seo.og_image.template` oppure uno per classe di modello:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Il modello può anche definire `getOgImageTemplate(): ?string`, restituendo il nome della vista oppure `null`. La precedenza è: hook del modello, mappa `templates`, valore globale `template`.

## Personalizza il template {#customizing-the-template}

La vista Blade, normalmente `seo::og.default`, viene renderizzata in un documento HTML autonomo. Il font incluso viene incorporato come URI `data:`, senza richieste di rete.

**Pubblica e modifica la vista inclusa:**

```bash
php artisan vendor:publish --tag=seo-views
```

Modifica poi `resources/views/vendor/seo/og/default.blade.php`.

**Oppure indica una tua vista:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

Il template riceve queste variabili:

| Variabile | Tipo | Significato |
|---|---|---|
| `$title` | `string` | Titolo OG, se impostato, altrimenti titolo della pagina |
| `$siteName` | `?string` | `og:site_name` risolto |
| `$fontDataUri` | `string` | Font grassetto incluso come URI `data:`; stringa vuota se indisponibile, con fallback sans-serif del browser |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from` |
| `$gradientTo` | `string` | `seo.og_image.gradient_to` |
| `$width` | `int` | Larghezza, default `1200` |
| `$height` | `int` | Altezza, default `630` |
| `$locale` | `?string` | Lingua della pagina per `<html lang>` |
| `$author` | `?string` | Autore dell'articolo, usato da `seo::og.article` |
| `$publishedDate` | `?string` | Data di pubblicazione per `seo::og.article`: formato medio ICU nella lingua della pagina, quando disponibile; altrimenti Carbon traduce il mese mantenendo l’ordine `M j, Y`. Null se la data manca. |
| `$section` | `?string` | Sezione o categoria |
| `$description` | `?string` | Descrizione OG o della pagina, usata da `seo::og.product` |

::: info Il nome del template fa parte della chiave di cache
Cambiare il nome del template o i colori modifica l'hash. Modificare il contenuto della stessa vista non cambia il nome: incrementa `cache_version` oppure esegui `--force`.
:::

## Configurazione {#configuration}

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

Molti valori scalari hanno una variabile d'ambiente corrispondente, come `SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH` e `SEO_OG_IMAGE_NO_SANDBOX`. L'elenco completo è nel file di configurazione. Gli array `templates`, `models`, `browsershot_args` e `font_stack` si modificano direttamente nel file.

Il disco deve essere **pubblicamente accessibile**, perché il resolver usa `url()` per `og:image`. Con il disco `public`, esegui una volta `php artisan storage:link` per collegare `public/storage`.

## Linux e sandbox {#running-on-linux-the-sandbox}

Se il sistema limita i meccanismi della sandbox di Chrome, il comando può fallire con:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Una possibile causa sono le restrizioni sui namespace utente di Ubuntu 23.10+. Verifica l'errore effettivo e la [guida Puppeteer](https://pptr.dev/troubleshooting). Preferisci una configurazione che mantenga attiva la sandbox.

**1. Fallback esplicito: `--no-sandbox`.** Disabilita l'isolamento del browser. Usalo solo se la tua configurazione di deploy accetta deliberatamente questo compromesso:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Rankbeam genera HTML statico e blocca le risorse remote, ma questo non sostituisce la sandbox. Mantieni il processo senza privilegi e isolato da segreti e carichi di lavoro non correlati.

**2. Mantieni la sandbox.** Lascia `no_sandbox` disattivato. Se la causa è AppArmor, adatta un profilo per l'eseguibile Chrome effettivamente usato, seguendo le [indicazioni Chromium](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md). Per esempio:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Caricalo con `sudo apparmor_parser -r /etc/apparmor.d/chrome-og` e verifica che Chrome si avvii con la sandbox attiva.

::: tip Memoria condivisa del container
Se Chrome si interrompe durante il rendering in un container con poca memoria condivisa, puoi passare opzioni aggiuntive:

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Driver personalizzati {#custom-drivers}

`browsershot` è l'unico driver incluso. Il contratto `Rankbeam\Seo\Contracts\OgImageRenderer` permette di registrarne altri e sceglierli con `seo.og_image.driver`:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

Il driver converte HTML autonomo in byte PNG alle dimensioni richieste. Non gestisce layout o template.

## Font e sistemi di scrittura non latini {#fonts-and-non-latin-scripts}

Il font incluso, Noto Sans Bold con licenza OFL, copre **latino, cirillico e greco**. Per cinese, giapponese, coreano, thailandese, arabo, ebraico, devanagari ed emoji servono font sulla macchina che esegue `seo:og-images`. I font CJK sono grandi; il pacchetto usa il fallback per carattere di Chrome invece di distribuirli tutti.

Tre strumenti aiutano a verificarne il funzionamento:

1. **Stack `font-family` nei template.** Dopo `'OGBrand'`, il font incluso, viene `seo.og_image.font_stack`: per default Noto Sans, quattro famiglie Noto Sans CJK, Noto Sans Thai, Arabic, Hebrew, Devanagari, Noto Color Emoji e infine `sans-serif`. La famiglia CJK della lingua viene anticipata: JP per `ja`, SC per `zh-Hans`, TC per `zh-Hant` / `zh-TW` / `zh-HK`, KR per `ko`. Serve a scegliere la forma locale dei caratteri Han condivisi. Anche `<html lang>` riceve la lingua BCP 47. Lo stack partecipa alla chiave di cache.
2. **Controllo prima della generazione.** `seo:og-images` interroga fontconfig, per esempio `fc-list :lang=ja`, per i sistemi di scrittura presenti in titolo, nome del sito e descrizione, anche se minoritari nel testo. Segnala una volta per sistema il pacchetto di font da installare. Dove fontconfig manca, come su Windows, macOS o container minimi, non tenta di indovinare. Un font assente non fa necessariamente fallire il rendering: Chrome può disegnare riquadri al posto dei caratteri.

   Il messaggio di font mancante indica che le card potrebbero contenere riquadri e suggerisce, per CJK, `apt-get install fonts-noto-cjk`.

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

3. **Fixture dei glifi nel test live.** Con `SEO_OG_IMAGE_LIVE_TEST=1`, `tests/Feature/OgImage/BrowsershotSmokeTest.php` confronta titoli in ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he e hi con un controllo di caratteri non assegnati. PNG identici fanno fallire il test. È un controllo preliminare, non una certificazione di ogni glifo: testo latino misto o a capo diversi possono produrre immagini differenti anche con caratteri mancanti. Ispeziona il rendering e i font usati sul server di deploy. Anche FontProbe è un controllo preliminare. Il core non ha un comando `seo:doctor`: usa `seo:og-images`.

Su Debian/Ubuntu:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

Le viste pubblicate con `--tag=seo-views` prima della 3.15 continuano a funzionare. Ricevono le variabili `$fontFamily` e `$lang`, che possono anche ignorare.

## Limiti {#caveats}

- **Solo generazione anticipata:** non c'è una rotta di rendering su richiesta. Devi eseguire il comando al deploy o tramite pianificazione perché le card esistano.
- **`npm_module_path` non funziona su Windows:** installa Puppeteer nella directory principale dell'app. Su Linux/macOS l'impostazione è utilizzabile.
- **I font non latini devono essere installati sul server:** il font incluso copre latino, cirillico e greco. Verifica gli altri sistemi di scrittura come descritto sopra.
- **Fallback in caso di errore:** se mancano dipendenze o il browser fallisce o supera il timeout, il comando lo segnala e la pagina continua a usare `default_og_image`. Il rendering della card non avviene nella richiesta web.
