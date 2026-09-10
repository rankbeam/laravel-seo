---
description: "Osserva le richieste attribuite ai crawler AI: bot riconosciuti dallo user-agent, frequenza, ultimo URL e stato HTTP. Il monitoraggio affianca le policy dei crawler."
---

# Monitor dei bot AI {#ai-bot-monitor}

Il [controllo dei crawler AI](/it/guide/ai-crawlers) del core stabilisce cosa `robots.txt` *comunica* ai crawler. Il **monitor dei bot AI** di Pro registra invece le richieste ricevute: quali user-agent corrispondono a crawler AI noti, quante volte compaiono e l’ultimo URL con il relativo stato HTTP. L’attribuzione avviene tramite user-agent e non autentica l’identità del bot.

Riusa l’infrastruttura del monitor 404 — middleware globale terminabile, modello con upsert e conteggio delle richieste, stesse impostazioni di privacy — ma raggruppa per **bot** anziché per percorso e registra **qualsiasi** stato di risposta. Riconosce proprio i crawler AI esclusi dal monitor 404. L’identificazione usa `AiCrawlerRegistry` del core, così policy robots.txt e traffico osservato condividono lo stesso catalogo.

::: tip Richiede core ≥ 3.3
Il monitor identifica i bot con il catalogo del core, [`SEO::aiCrawlers()`](/it/guide/ai-crawlers). Con versioni precedenti resta inattivo.
:::

## Attivazione {#enabling-it}

La funzione è disattivata per impostazione predefinita. Quando la abiliti, il middleware globale registra i crawler corrispondenti dopo la risposta, senza aggiungere il lavoro di registrazione alla sua generazione:

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

Il middleware viene registrato automaticamente; puoi disabilitare questa registrazione con `ai_bots.auto_register_middleware`. L’upsert mantiene una riga per bot noto, quindi la dimensione della tabella è limitata dal catalogo.

## Leggere il log {#reading-the-log}

### Senza pannello {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Ogni riga espone `bot`, `label`, `operator`, `purpose`, `hit_count`, `last_path`, `last_status`, `first_seen_at` e `last_seen_at`.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Con il plugin Pro registrato, nel gruppo SEO compare la tabella **Bot AI**: bot, operatore, finalità, richieste, ultimo stato, ultimo percorso e ultima visita. È di sola lettura e filtrabile per finalità.

## Privacy {#privacy}

Come per il monitor 404, **nessun IP viene memorizzato per impostazione predefinita**. L’opzione `ai_bots.hash_ip` salva soltanto un hash SHA-256 con chiave, `ip_hash`; l’IP originale non viene scritto.

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## Metriche per periodo: aggregazioni giornaliere {#period-metrics-daily-buckets}

La tabella complessiva mantiene una riga per bot, utile per una classifica totale ma insufficiente per sapere quante richieste o quanti URL distinti abbia visitato **in una finestra specifica**. Con `daily_enabled` attivo, come per impostazione predefinita, ogni richiesta viene registrata anche in un’aggregazione per giorno e percorso, `seo_ai_bot_daily`. Il [report personalizzabile](/it/pro/reports) può così mostrare dati effettivi del periodo — richieste dall’ultimo report e URL distinti — anziché una semplice differenza dei totali storici.

Due limiti contengono il numero di righe:

- **Numero massimo di percorsi distinti per bot e giorno**, `daily_max_paths`. Oltre il limite, i nuovi percorsi vengono raccolti in un’unica voce di eccedenza. Il totale delle richieste resta esatto; il conteggio degli URL distinti che ha raggiunto il limite viene mostrato come “N+”.
- **Finestra di conservazione**, `daily_retention_days`, applicata da `seo-pro:ai-bots-prune`.

Imposta `daily_enabled` a `false` per conservare soltanto la classifica complessiva. Il report torna allora alla differenza rispetto allo snapshot del report precedente per i dati “dall’ultimo report” e ignora le aggregazioni esistenti, evitando di leggere una tabella non più aggiornata.

Le metriche hanno **risoluzione giornaliera**: il confronto parte dal giorno del report precedente, includendo giorni interi. Una richiesta di quel giorno può quindi precedere o seguire l’ora esatta di generazione. Tieni conto di questo confine quando confronti report giornalieri, settimanali o mensili.

## Dall’osservazione alla policy {#turning-observation-into-control}

Il monitor mostra le richieste attribuite ai crawler; il [controllo dei crawler AI](/it/guide/ai-crawlers) del core imposta la policy da comunicare loro. Per modificare l’accesso dichiarato a un crawler di addestramento:

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Alcuni bot sono documentati come non rispettosi di `robots.txt`. Il monitor può aiutarti a individuare richieste inattese e valutare un blocco nel firewall, nel WAF o in Cloudflare; lo user-agent, da solo, non verifica chi le invia.
