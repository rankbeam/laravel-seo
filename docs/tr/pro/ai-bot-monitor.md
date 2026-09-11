---
description: "Yapay zekâ tarayıcılarının sitenizde gerçekte ne yaptığını kaydedin: hangi botların ne sıklıkla geldiği, son URL ve durumları. Tarayıcı kontrolünün gözlemlenebilirlik tarafı."
---

# Yapay zekâ botu izleyicisi {#ai-bot-monitor}

İstekler, doğrulanmış bot kimliğiyle değil **user-agent eşleştirmesiyle** ilişkilendirilir.
İzleyici gözlemlenen istekleri kaydeder; user-agent taklit edilebilir.

Çekirdekteki [yapay zekâ tarayıcısı kontrolü](/tr/guide/ai-crawlers),
`robots.txt` dosyasının yapay zekâ tarayıcılarına ne *söylediğini* belirler.
Pro **yapay zekâ botu izleyicisi** diğer yarıdır: gerçekte ne *yaptıklarını* kaydeder.
Hangi yapay zekâ tarayıcılarının sitenizi ne sıklıkla getirdiğini ve her birinin
eriştiği son URL ile HTTP durumunu gösterir.

404 izleyicisinin altyapısını yeniden kullanır: sonlandırılabilir genel ara katman,
istek sayan upsert modeli ve aynı gizlilik yaklaşımı. Ancak yol yerine **botu**
anahtar olarak kullanır ve **her** yanıt durumunda kayıt tutar; bunlar, 404
izleyicisinin bilinçli olarak dışarıda bıraktığı yapay zekâ tarayıcılarıdır.
Bot tanımlama, çekirdeğin `AiCrawlerRegistry` bileşenini yeniden kullanır; böylece
robots.txt politikanız ve gözlemlediğiniz trafik tek bir doğruluk kaynağını paylaşır.

::: tip Çekirdek ≥ 3.3 gerekir
İzleyici, botları çekirdekteki yapay zekâ tarayıcısı kataloğuyla tanımlar
([`SEO::aiCrawlers()`](/tr/guide/ai-crawlers)). Eski çekirdek sürümlerinde etkinleşmez.
:::

## Etkinleştirme {#enabling-it}

Varsayılan olarak kapalıdır. Açtığınızda genel ara katman, eşleşen tarayıcıları
her yanıttan sonra kaydeder; sayfayı asla geciktirmez:

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

Hepsi bu. Ara katman otomatik kaydedilir; `ai_bots.auto_register_middleware` ile bundan
vazgeçebilirsiniz. Bilinen her bot için bir satır eklenir veya güncellenir;
bu yüzden tablonun büyüklüğü katalogla sınırlıdır.

## Günlüğü okuma {#reading-the-log}

### Arayüzden bağımsız kullanım {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Her satır `bot`, `label`, `operator`, `purpose`, `hit_count`,
`last_path`, `last_status`, `first_seen_at` ve `last_seen_at` alanlarını sunar.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Pro eklentisi kaydedildiğinde SEO gezinme grubu altında **Yapay zekâ botları**
tablosu görünür: bot, işleten kuruluş, amaç, istekler, son durum, son yol ve son
görülme zamanı. Amaca göre filtrelenebilir ve salt okunurdur.

## Gizlilik {#privacy}

404 izleyicisiyle aynı yaklaşım geçerlidir: **varsayılan olarak IP saklanmaz.**
İsteğe bağlı `ai_bots.hash_ip`, yalnızca anahtarlı sha256 (`ip_hash`) saklar;
ham IP hiçbir zaman yazılmaz.

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

## Dönem ölçümleri (günlük bölmeler) {#period-metrics-daily-buckets}

Tüm zamanları kapsayan tablo bot başına bir satır tutar. Bu, sıralama için
yararlıdır; ancak bir botun **belirli bir zaman aralığında** *kaç istek* yaptığını
veya *kaç farklı URL’ye* eriştiğini söyleyemez. `daily_enabled` açıkken (varsayılan),
her istek ayrıca günlük ve yol bazında bir bölmeye (`seo_ai_bot_daily`) kaydedilir.
Böylece [markanıza özel rapor](/tr/pro/reports), tüm zamanların farkı yerine
**gerçek** dönem rakamlarını gösterir: son rapordan bu yana istekler ve bu dönemdeki farklı URL’ler.

Sınırlılık korunur; tüm zamanlar günlüğünün bot başına tek satır olmasının nedeni de buydu:

- Bot ve gün başına **farklı yol sınırı** (`daily_max_paths`): sınırdan sonraki yeni
  yollar tek bir taşma bölmesinde toplanır. Günün toplam istek sayısı tam kalır,
  satır sayısı ise kontrolsüz büyüyemez. Sınıra ulaşan farklı URL sayısı "N+" olarak gösterilir.
- `seo-pro:ai-bots-prune` ile temizlenen bir **saklama süresi** (`daily_retention_days`).

Yalnızca tüm zamanlar sıralamasını tutmak için `daily_enabled` değerini `false`
yapın. Rapor bu durumda “son rapordan bu yana” için önceki raporun anlık görüntüsüyle
farka döner; mevcut bölmeler yok sayılır, böylece eski tablo asla okunmaz.

Dönem rakamları **gün çözünürlüğündedir**: “son rapordan bu yana”, önceki raporun
gününden başlayarak tam günleri sayar. O günkü bir istek, raporun tam üretim
zamanının öncesinde veya sonrasında olabilir. Normal günlük, haftalık veya aylık
aralıklarda bu sınır esnekliği ihmal edilebilir.

## Gözlemi kontrole dönüştürme {#turning-observation-into-control}

İzleyici *kimin* taradığını söyler; çekirdekteki
[yapay zekâ tarayıcısı kontrolü](/tr/guide/ai-crawlers) *ne alabileceklerini*
belirler. Kısıtlamak istediğiniz bir eğitim tarayıcısı mı göründü?

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Bazı botların `robots.txt` kurallarına uymadığının belgelendiğini unutmayın.
İzleyici, bunları fark edip uç katmanda (güvenlik duvarı / WAF / Cloudflare)
engelleyip engellemeyeceğinize karar vermenizi sağlar.
