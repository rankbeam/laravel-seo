---
description: "fibonoir/laravel-seo v1'den rankbeam/laravel-seo v2'ye yükseltin. Yeni adla çekirdek; meta çözümleme, çıktı üretimi, JSON-LD ve site haritalarına odaklanır."
---

# fibonoir/laravel-seo v1'den yükseltme {#upgrading-from-fibonoir-laravel-seo-v1}

v2.0.0 paketin adını `rankbeam/laravel-seo` olarak değiştirir ve kapsamını odaklı bir çekirdeğe indirir: meta çözümleme, çıktı üretimi, JSON-LD ve site haritaları. Analiz aracı, tarayıcı, yönlendirmeler, 404 izleme ve yönetim arayüzü ayrı paketlere taşınmıştır.

## 1. Paketi değiştirin {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Ad alanlarını güncelleyin {#_2-update-namespaces}

Sınıf adları değişmemiştir; yalnızca kök ad alanı taşınmıştır: `Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`. Proje genelinde bul ve değiştir yeterlidir. `SEO` facade takma adı ve `@seo` Blade yönergeleri değişmemiştir.

## 3. Eski yayımlanmış dosyaları silin {#_3-delete-stale-published-files}

Dosyaları veya tabloları kaldırmadan önce yayımlanmış yapılandırmayı yedekleyin ve etkilenen verileri dışa aktarın. Geri yükleyebildiğinizi doğrulayın. Bu kılavuz, v1 yönlendirme, 404 veya tarama geçmişini Pro'nun farklı şemasına taşımaz; aşağıdaki çekirdek tablo uyumluluğu yalnızca `seo_meta` ve `seo_defaults` için geçerlidir.

::: warning Sessizce sorun çıkarır
v1'in `seo:install` komutu, uygulamanıza tek bir hata mesajı üretmeden v2 paketiyle çakışacak dosyalar yayımlamıştır.
:::

- **`config/seo.php`** — dosyanız v1 tarafından (veya v1 yükleyicisinin geride bırakabildiği `ralphjsmit/laravel-seo` tarafından) yayımlandıysa paket yapılandırmasını gölgeler; `site_name` değerini ve tüm `{site_name}` şablonlarını null yapabilir. Silin, ardından yeniden yayımlayın: `php artisan vendor:publish --tag=seo-config`.
- **v1 migration'ları** — çekirdeğin artık sahip olmadığı tablolar için: `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache`, `seo_internal_links_index`. Migration dosyalarını kaldırın. Tablolar canlı ortamda varsa `rankbeam/laravel-seo-pro` kurmadan **önce** silin; Pro bunları farklı bir şemayla yeniden oluşturur.
- **Yayımlanmış taslak şablonlar** — v1'in Filament 3 / Livewire / Vue / React akışından kalan, `app/` ve `resources/js` altındaki dosyalar; artık var olmayan sınıflara başvururlar.

İki çekirdek tablo (`seo_meta`, `seo_defaults`) şema bakımından uyumludur; verileriniz yükseltmeden sonra korunur.

## 4. Kaldırılan özellikler ve yeni yerleri {#_4-removed-features-and-where-they-went}

| v1 özelliği | Şimdi bulunduğu yer |
|---|---|
| Filament SEO form bölümü | [`rankbeam/laravel-seo-filament`](/tr/guide/filament) (ücretsiz, MIT) |
| İçerik analiz aracı (32 kural) | Bu geçiş eski analiz aracını taşımaz. Teknik SEO sorunlarını belirleme, `rankbeam/laravel-seo-pro` site tarayıcısındadır; sayısal SEO puanı, sorunlardan türetilen bir Pro özelliğidir. |
| Site geneli tarayıcı | `rankbeam/laravel-seo-pro` — kuyruklu işlem hattı + pano |
| Yönlendirme yöneticisi | `rankbeam/laravel-seo-pro` — güçlendirilmiş güvenlik (regex doğrulaması, açık yönlendirme korumaları) |
| 404 izleme | `rankbeam/laravel-seo-pro` — gizlilik odaklı (varsayılan olarak IP saklanmaz) |
| GA4 analitiği, iç bağlantılar | `rankbeam/laravel-seo-pro` yapılacaklar listesi |
| `seo:install` yükleyicisi | Kaldırıldı; kurulum artık require, yapılandırmayı yayımlama ve migrate adımlarından oluşur |

## 5. İncelenecek davranış değişiklikleri {#_5-behavior-changes-to-review}

- **`og:image` / `twitter:image` her zaman mutlak URL'dir.** v1, elle atanan göreli yolları aynen çıktı olarak veriyordu.
- **Türetilen kanonik URL'lerden sorgu dizesi kaldırılır.** Açıkça atanan kanonik URL'ler aynen korunur.
- **Site haritası otomatik keşfi, kayıtlı kaynaklara öncelik verir.** Kayıtlı `sitemap-posts.xml` yanında yinelenen bir `sitemap-post.xml` oluşmaz.
- **JSON-LD, `JSON_HEX_*` ile kaçışlanır.** Ham script çıktısına sonradan işlem uyguluyorsanız `<` tarzı kaçışlar bekleyin.

## 6. Bilinen önemli noktalar {#_6-known-gotchas}

- Laravel'in varsayılan `DatabaseSeeder` sınıfı, seeder'larda `HasSEO` trait'inin otomatik oluşturma kancasını devre dışı bırakan `WithoutModelEvents` kullanır.
- Bir rota varsayılanı başlık şablonu markanızı zaten içeriyorsa şablonu yapılandırılmış `title_suffix` ile bitirin; böylece çözümleyici onu tekrar eklemez.
