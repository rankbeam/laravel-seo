---
description: "Yapay zekâ asistanının Laravel sitesinin SEO verilerini Model Context Protocol üzerinden okumasını ve izin verilirse düzenlemesini sağlayan bağımlılıksız stdio MCP sunucusu. Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5."
---

# MCP sunucusu {#mcp-server}

Rankbeam MCP sunucusu, yapay zekâ asistanının
[Model Context Protocol](https://modelcontextprotocol.io) üzerinden **sitenin
SEO verilerini okumasını ve isteğe bağlı olarak düzenlemesini** sağlar.
Bir MCP istemcisini (Claude Code / Claude Desktop, Cursor, Codex, …) Laravel
uygulamanıza yöneltin. Sayfanın meta verilerini çözümleyebilir, denetim
çalıştırabilir, Pro puanını okuyabilir, yapay zekâ tarayıcısı politikanızı
görebilir ve izin verdiğinizde SEO verilerini geri yazabilir.

**Bağımlılıksız**, kendi kendine yeterli bir stdio sunucusudur; SDK veya yeni
paket gerekmez. PHP 8.2–8.4 (Laravel 11), PHP 8.2–8.5 (Laravel 12),
PHP 8.3–8.5 (Laravel 13) üzerinde çalışır.

::: tip Pro özelliği
MCP sunucusu `rankbeam/laravel-seo-pro` ile gelir. **Varsayılan olarak salt okunurdur**;
düzenlemeler, yapılandırma bayrağı ve model izin listesiyle isteğe bağlı açılır.
:::

## Asistan neler yapabilir? {#what-the-assistant-can-do}

### Analiz araçları (her zaman kullanılabilir) {#analysis-tools-always-available}

| Araç | İşlevi |
| --- | --- |
| `seo_resolve` | Model kaydının tamamen çözümlenmiş SEO meta verileri (başlık, açıklama, kanonik URL, robots, Open Graph, JSON-LD); sayfanın gerçekte üreteceği çıktı. |
| `seo_audit` | Model kaydı veya ilk N kayıt için süreç içi [meta veri denetimi](/tr/guide/audit); aynı `seo:audit` kontrolleri, canlı ve kuyruksuz. |
| `seo_score` | Model kaydının son saklanan [Pro SEO puanı](/tr/pro/scoring) (0–100 + harf notu). |
| `seo_robots_directives` | Yönetilen [yapay zekâ tarayıcısı robots.txt yönergeleri](/tr/guide/ai-crawlers) ve bot başına çözümlenmiş izin verme/vermeme politikası. |
| `validate_schema` | JSON-LD nesnesini veya izin listesindeki modelin çözümlenmiş şema grafını çekirdeğin yapılandırılmış veri doğrulayıcısıyla doğrular (`@type` başına Google zengin sonuç gereksinimleri). |
| `analyze_robots` | **Bilinen her yapay zekâ tarayıcısı için** esas izin verme/vermeme kararı ve bunu belirleyen şey: bot başına açık atama, amaç politikası veya varsayılan. Politika sitenin tamamı için geçerlidir. |
| `debug_social_share` | Sosyal tarayıcının model kaydı için gerçekten göreceği, yedek değerlerden sonra çözümlenmiş Open Graph + Twitter kartı; kart sağlığına ilişkin bilgilendirici notlarla. |
| `check_meta` | Tek model kaydı için odaklı meta sağlık görünümü: uzunlukları ve varlık durumlarıyla çözümlenmiş başlık/açıklama/kanonik URL/robots/og:image ve denetim sorunları. |

### Site içeriği araçları (her zaman kullanılabilir) {#site-content-tools-always-available}

“Sitenizle konuşun” araçları, sunucuyu sayfalarınızı listeleyebilen ve
arayabilen, içeriği dikkate alan bir asistana dönüştürür.

| Araç | İşlevi |
| --- | --- |
| `list_pages` | **İzin listesindeki** modelin SEO ile yönetilen sayfalarını (kayıtlarını), URL ve etkin başlıklarıyla listeler. `limit`/`offset` sayfalamasını destekler. |
| `search_pages` | **İzin listesindeki** modelin sayfalarında tam metin araması. Model aranabilir olduğunda Laravel [Scout](https://laravel.com/docs/scout), aksi hâlde güvenli SQL `LIKE` (title/name/headline + birleştirilen SEO meta verileri). Her eşleşme için URL, başlık ve kısa alıntı döndürür. |

### Operasyon araçları (isteğe bağlı) {#ops-tools-opt-in}

Tarama durumunu okuyan ve site yapılandırmasını değiştiren operasyon araçları.
Düzenleme aracı gibi **`allow_edits` ile sınırlandırılırlar**; varsayılan salt
okunur sunucuda görünmez ve çalışmazlar.

| Araç | İşlevi |
| --- | --- |
| `list_issues` | Geçerli açık SEO tarama sorunları (çalıştırmalar arasında korunan açık küme) ve son [tarama](/tr/pro/scan-issues) çalıştırmasının üst bilgisi. `severity` / `type` ile filtreleyin. |
| `trigger_scan` | Tarama başlatır: izin listesindeki tek kaydın hedefli taraması (çalıştırmayı döndürür) veya tüm hedeflerin tam taraması. Varsayılan olarak kuyrukta; `sync: true` ile aynı süreçte. |
| `create_redirect` | Yönlendirme modelinin kendi doğrulayıcılarını kullanarak yönlendirme kuralı oluşturur (kaynak yol veya regex → hedef, durum `301`/`302`/`307`/`308`/`410`). |

### Düzenleme aracı (isteğe bağlı) {#edit-tool-opt-in}

| Araç | İşlevi |
| --- | --- |
| `seo_save_meta` | SEO meta verilerini (başlık, açıklama, kanonik URL, robots, OG, Twitter, JSON-LD) `saveSEO()` üzerinden **izin listesindeki** model kaydına yazar. |

Düzenlemeleri açmadıkça operasyon araçları ve `seo_save_meta`, **`tools/list`
içinde duyurulmaz ve çalıştırılamaz**; bkz. [Güvenlik](#security).
Salt okunur sunucu, bu araçların varlığını asistana bile söylemez.

## Yapay zekâ istemcisini bağlama {#wiring-an-ai-client}

Sunucu **stdio** üzerinden JSON-RPC kullanır: istemci bir Artisan komutu
başlatır ve onunla boru üzerinden konuşur. Kullandığınız istemcilere kaydedin;
aynı sunucu hepsiyle çalışır.

::: tip Tek komut, her istemci
Aşağıdaki her istemci aynı başlatma komutunu çalıştırır: `php artisan seo-pro:mcp`.
Artisan’ın uygulamayı başlatabilmesi için komut **uygulamanızın kökünden**
çalıştırılır. `php` istemcinin `PATH` değişkeninde yoksa
(Windows’ta veya kabuk ortamını devralmayan GUI uygulamalarında yaygın),
**hem `php` hem `artisan` için mutlak yol** verin. Artisan,
`artisan` betiğinin kendi dizininden açılır; bu yüzden `cwd` gerekmez.
:::

### Claude Code (CLI) {#claude-code-cli}

Tek komutla kaydedilir. **Uygulamanızın kökünden** çalıştırın:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Bağlandığını doğrulayın:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

Windows / Laravel Herd’de, başlatma dizininden bağımsız çalışması için mutlak yolları sabitleyin:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Yapılandırma dosyasını düzenleyin (**Settings → Developer → Edit Config**)
veya doğrudan açın:

- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

**Windows** üzerinde mutlak `php.exe` yolunu kullanın ve JSON’da her
ters eğik çizgiyi çift kaçışla yazın:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "C:\\Users\\you\\.config\\herd\\bin\\php84\\php.exe",
      "args": ["C:\\path\\to\\app\\artisan", "seo-pro:mcp"]
    }
  }
}
```

Claude Desktop’ı tamamen kapatıp yeniden açın. Araçlar, mesaj çubuğundaki
araç/fiş simgesinin altında görünür.

### Cursor {#cursor}

Projenizde `.cursor/mcp.json` dosyasını oluşturun; tüm projeler için `~/.cursor/mcp.json`
kullanın. Her yerden başlaması için mutlak `artisan` yolunu verin:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

Windows’ta, yukarıdaki Claude Desktop örneğinde olduğu gibi mutlak
`php.exe` ve çift kaçışlı ters eğik çizgiler kullanın. Sunucuyu
**Settings → MCP** bölümünden etkinleştirin.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Claude Code’da olduğu gibi Windows/Herd’de mutlak `php` + `artisan` yollarını verin.

Hepsi bu; asistan artık araçları çağırabilir.

## Uygulamalı konuşma {#a-worked-conversation}

Aşağıda demo uygulamayla (900 sayfalık tıp enstitüsü sitesi) yapılmış gerçek
bir konuşma vardır; araçların gerçek çıktıları kısaltılmıştır. İzin
listesindeki iki okuma modeli `App\Models\Page` ve `App\Models\PublicPage`;
`allow_edits` açıktır.

> **Siz:** “Accoglienza” sayfasının (Page id 1) SEO’su nasıl? Belirgin sorunları düzelt.

Asistan önce **denetler**: `seo_audit { model: "App\\Models\\Page", id: 1 }`.

```json
[
  {
    "id": 1,
    "url": "http://localhost/accoglienza",
    "issue_count": 2,
    "issues": [
      { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 17 characters (recommended min 30).", "context": { "length": 17, "min": 30 } },
      { "code": "description_too_short", "severity": "notice", "field": "description", "message": "Description is only 11 characters (recommended min 70).", "context": { "length": 11, "min": 70 } }
    ]
  }
]
```

Gerçekte ne üretildiğini görmek için geçerli meta verileri **çözümler**:
`seo_resolve { model: "App\\Models\\Page", id: 1 }`. Önerilen alt sınırların altında, 17 karakterlik başlık ve
11 karakterlik açıklama bulur. Bunun üzerine **düzeltmeyi yazar**: `seo_save_meta`.

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

Araç yazmayı doğrular ve yeni çözümlenen kaydı geri gösterir:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Son olarak düzeltmeyi doğrulamak için **yeniden denetler**;
`issue_count` artık `0` değerindedir:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Asistan:** Tamamlandı. Başlık (17 karakter) ve açıklama (11 karakter)
> önerilen alt sınırların altındaydı. Daha kapsamlı, açıklayıcı bir başlık ve
> açıklama atadım; yeniden denetim artık temiz dönüyor (0 sorun).

Yazma, programatik kaydetmede kullanılan aynı `saveSEO()` üzerinden geçti.
Değer, kodla atanmış gibi saklanır ve çözümlenir; modelin toplu atanabilir
alanları ve cast’leri üzerinden toplu atanır. Filament formunun uzunluk
doğrulayıcılarından **geçmez**; yeniden denetim yalnızca otomatik kontrollerini
doğrular. Editoryal doğruluk hâlâ inceleme gerektirir; temiz denetim metin
kalitesinin sertifikası değildir.

### Sitenizin içeriğiyle konuşma {#talking-to-your-site-s-content}

Site içeriği araçları, asistanın yalnızca adını verdiğiniz tek kayıt hakkında
değil, **sitenin kendisi hakkında** soruları yanıtlamasını sağlar: “sitenizle konuşun” demosu.

> **Siz:** Hangi sayfalarda “immunoterapia” geçiyor ve başlıkları iyi durumda mı?

Asistan **arar**: `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`.

```json
{
  "model": "App\\Models\\Page",
  "query": "immunoterapia",
  "driver": "like",
  "count": 3,
  "results": [
    { "id": 42, "url": "http://localhost/ricerca/immunoterapia-oncologica", "title": "Immunoterapia oncologica", "snippet": "Il programma di immunoterapia dell'IDI: terapie, sperimentazioni e accesso…" },
    { "id": 87, "url": "http://localhost/reparti/dermatologia-oncologica", "title": "Dermatologia oncologica", "snippet": "…include protocolli di immunoterapia per i tumori cutanei avanzati…" },
    { "id": 91, "url": "http://localhost/news/nuova-immunoterapia-2026", "title": "Nuova", "snippet": "Al via una nuova immunoterapia…" }
  ]
}
```

Üçüncü başlık yalnızca “Nuova” olduğu için **o sayfanın meta verilerini
kontrol eder**: `check_meta { model: "App\\Models\\Page", id: 91 }`.

```json
{
  "url": "http://localhost/news/nuova-immunoterapia-2026",
  "title": { "value": "Nuova", "length": 5, "present": true },
  "description": { "value": null, "length": 0, "present": false },
  "og_image": { "value": null, "present": false },
  "issue_count": 2,
  "issues": [
    { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 5 characters (recommended min 30)." },
    { "code": "missing_description", "severity": "warning", "field": "description", "message": "No meta description is set." }
  ]
}
```

> **Asistan:** Üç sayfada “immunoterapia” geçiyor. İkisi iyi görünüyor, ancak
> **/news/nuova-immunoterapia-2026** sayfasının 5 karakterlik başlığı (“Nuova”)
> var ve meta açıklaması yok. Daha kapsamlı başlık ve açıklama yazmamı ister misiniz?

`allow_edits` açıkken `seo_save_meta` ile hemen düzeltebilir; salt okunur
modda öneride durur. Model Scout dizininde olmadığı için `search_pages`
burada SQL `LIKE` yedek yolunu (`"driver": "like"`) kullandı.
[Laravel Scout](https://laravel.com/docs/scout) eklediğinizde aynı araç,
bunun yerine arama motorunuzu otomatik kullanır.

## Güvenlik {#security}

Üç katman sunucuyu varsayılan olarak güvenli tutar. Salt okunur varsayılan
modda üçü de açıktır; bunları bilinçli olarak gevşetirsiniz.

### 1. Düzenlemeler kısıtlıdır (varsayılan olarak kapalı) {#_1-edits-are-gated-off-by-default}

Bayrağı açana kadar yazma aracı görünmez ve çalışmaz:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

`allow_edits` kapalıyken (varsayılan), `seo_save_meta` **`tools/list`
tarafından döndürülmez** ve ona yönelik `tools/call`, JSON-RPC
`-32602` hatası verir. Asistan yazamaz; yazabileceğini keşfedemez bile.
Yalnızca güvendiğiniz istemci ve veritabanı için açın.

### 2. Model izin listesi {#_2-the-model-allowlist}

Model kapsamındaki her araç, okuma *veya* yazma, yalnızca izin listesindeki
`HasSEO` modeline dokunabilir. Yapay zekâ istemcisi aracı rastgele
sınıfa (`User`, faturalandırma modeli veya başka bir şey) yöneltemez:

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

İzin listesinde olmayan sınıf istendiğinde araç, asistanın okuyabildiği hata
sonucu döndürür (`Model [App\Models\User] is not in the MCP allowlist`); sınıfa hiç dokunmaz. `models` boşsa
yapılandırdığınız `seo.audit.models` / `seo.sitemap.models` kullanılır. Böylece MCP,
paketin geri kalanıyla tam olarak aynı alanda çalışır; daha genişinde değil.

### 3. Yalnızca stdio — ağa hiçbir şey açılmaz {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

Sunucu **yalnızca stdio üzerinden** konuşur. İstemci süreci başlatır ve
JSON-RPC’yi boruyla alıp verir. **HTTP dinleyicisi, port veya soket yoktur.**
Başka makineden erişilecek bir şey yoktur; kimlik doğrulanacak uzak yüzey
olmadığından kimlik doğrulama da yoktur. STDOUT yalnızca protokol trafiğini
taşır. Tüm tanılama STDERR’a gider; istemciniz bunları kaydeder, örneğin
Claude Desktop `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log` içine yazar. Rastgele günlük satırı akışı bozamaz.

::: warning Düzenlemeye açık sunucuyu veritabanına yazma erişimi gibi değerlendirin
`allow_edits`, bağlı asistana komutun kullandığı veritabanında SEO satırlarını
değiştirme yetkisi verir. Denerken yerel/hazırlık ortamına yöneltin, izin
listesini dar tutun ve bitince düzenlemeleri yeniden kapatın. Ana anahtar
`'enabled' => false` komutun başlamasını tamamen reddeder. Yerel stdio aktarımı,
yapay zekâ istemcisinin araç sonuçlarını kendi sağlayıcısına göndermesini
önlemez; istemcinin veri yapılandırmasını da dikkate alın.
:::

## Yapılandırma {#configuration}

```php
// config/seo-pro.php
'mcp' => [
    'enabled'     => true,           // master switch; the command refuses to run when false
    'allow_edits' => false,          // expose + permit the ops tools + seo_save_meta
    'models'      => [],             // allowlist; [] = fall back to audit/sitemap models
    'server_name' => 'rankbeam-seo', // reported in the MCP initialize handshake

    // Optional Server Card discovery route (off by default) — see below.
    'server_card' => [
        'enabled'     => false,      // serve GET {path} with the discovery card
        'path'        => '.well-known/mcp/server-card.json',
        'name'        => null,       // reverse-DNS server name (null = derived from app.url)
        'schema_url'  => 'https://modelcontextprotocol.io/schemas/draft/server-card.json',
        'website_url' => null,       // optional homepage/docs URL stamped on the card
    ],
],
```

## Server Card (keşif) — deneysel, taslak belirtim {#server-card-discovery-—-experimental-draft-spec}

MCP **Server Card**, istemcinin bağlanmadan önce sunucuyu — adı, sürümü ve
sunduklarını — keşfetmesini sağlayan, bilinen bir URL’deki küçük JSON
belgesidir. Rankbeam siteniz için bir kart sunarak ajan araçlarına
*“bu sitede konuşabileceğiniz bir MCP sunucusu var”* bilgisini duyurabilir.
**Varsayılan olarak kapalıdır** ve yalnızca ek bir özelliktir; açmak başka
hiçbir şeyi değiştirmez.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Açıkken `GET /.well-known/mcp/server-card.json` şuna benzer kart döndürür:

```json
{
  "$schema": "https://modelcontextprotocol.io/schemas/draft/server-card.json",
  "name": "com.example/rankbeam-seo",
  "version": "1.0.0",
  "title": "Rankbeam SEO MCP server",
  "description": "Read — and optionally edit — this site's SEO over the Model Context Protocol…",
  "_meta": {
    "io.rankbeam.seo/transport": "stdio",
    "io.rankbeam.seo/launch": "php artisan seo-pro:mcp",
    "io.rankbeam.seo/tool_count": 10,
    "io.rankbeam.seo/tools": [ { "name": "seo_resolve", "description": "…" } ]
  }
}
```

Kart yalnızca **o anda etkin** araçları listeler; salt okunur sunucu,
kısıtlı operasyon/düzenleme araçlarını kart üzerinden de duyurmaz.

::: warning Taslak belirtimi izler
Bu özellik **taslak** MCP Server Card önerisini izler:
[SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127),
10 Eylül 2026 itibarıyla açık, henüz birleştirilmemiş öneri. Bilinen yol,
`$schema` URL’si ve tam alan kümesi **kesinleşmemiştir**; bu yüzden her biri
yapılandırılabilir: `path`, `schema_url`, `name`, `website_url`.
Bu sunucu **stdio** (`php artisan seo-pro:mcp`) üzerinden çalışır; kart HTTP
`remotes` bloğu taşımaz. Bağlanılacak HTTP uç noktası değil, keşif
ipucudur. Güvenmeden önce yolu ve biçimi istemcinizle doğrulayın;
ihtiyacınız yoksa kapalı bırakın.
:::

## Protokol notları {#protocol-notes}

Yalnızca araç sunan MCP sunucusu küçük bir JSON-RPC 2.0 yüzeyidir; bu sunucu
bunu doğrudan uygular: `initialize` (sürüm uzlaşması + yetenek el sıkışması),
`tools/list`, `tools/call` ve `ping`. `2025-06-18` protokol
sürümünü duyurur; `2025-03-26` ve `2024-11-05` sürümlerini de anlar.
Bilinmeyen metotlar için `-32601`, bozuk satırlar için `-32700`
döndürür. **Araç** hatasını aktarım hatası olarak değil, asistanın
okuyabileceği `isError` sonucu olarak döndürür. `id` içermeyen
bildirimlere (ör. `notifications/initialized`) doğru biçimde yanıt verilmez.

## Arayüzden bağımsız kullanım ve genişletme {#headless-extending}

`SeoPro::mcp()` araç kaydını döndürür; böylece sunulan araçları inceleyebilir
veya kendi araçlarınızı kaydedebilirsiniz:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Özel araç `McpTool` arayüzünü uygular: `name`, `description`,
`inputSchema`, `isEnabled`, `handle`. Model izin listesi
çözümlemesini yeniden kullanmak için `AbstractTool` sınıfını genişletin;
aracınız yerleşik araçlarla aynı güvenlik güvencelerini devralır.
