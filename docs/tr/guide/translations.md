---
description: "Rankbeam'in denetim bulguları, editör uyarıları ve Filament etiketleri uygulamanın dilini izler. Bir metni değiştirmek için dil dosyalarını yayımlayın veya yeni bir dil katkısı yapın."
---

# Çeviriler {#translations}

Paketlerin kullanıcıya gösterdiği her metin — denetim bulguları, Filament alanlarının altındaki anlık editör uyarıları, etiketler, önizlemeler ve raporlar — bir Laravel dil satırıdır. Paketler `app()->getLocale()` değerini izler: İtalyanca çalışan bir panel, ek yapılandırma olmadan İtalyanca metin gösterir.

Sorun ve uyarı **kodları** (`missing_title`, `title_too_long`, …) asla değişmez ve çevrilmez. Yalnızca koda bağlı açıklama cümlesi çevrilir.

Paketlerle gelen diller: İngilizce, İtalyanca (önceki metinler incelendi; değişen metinler yeniden incelenmeli) ve Almanca, Fransızca, İspanyolca, Brezilya Portekizcesi, Felemenkçe, Türkçe, Rusça ve Lehçe ilk çevirileri (Tier 1). Core 3.16 / Filament 1.10 / Pro 2.35 itibarıyla Japonca, Basitleştirilmiş Çince (`zh_CN`), Geleneksel Çince (`zh_TW`), Korece, Yunanca, Ukraynaca ve Çekçe de bulunur (Tier 2). Her dilin kesin durumu [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md) dosyasındadır; bir ilk çevirinin desteklenen dil sayılması için anadili konuşuru incelemesi gerekir.

## Bir metni değiştirin {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Ardından `lang/vendor/seo/{locale}/seo.php` dosyasını (ve diğer paketlerin komşu klasörlerini) düzenleyin. Tuttuğunuz anahtarlar paket değerlerinin yerini alır; kalanlar önce paket dosyasına, ardından İngilizceye döner.

## Yeni bir dil katkısı yapın {#contribute-a-language}

`en` dosyasını kendi dilinize kopyalayın, değerleri çevirin, her `:placeholder` yer tutucusunu koruyun, test paketini çalıştırın (anahtar uyumu testi eksik veya fazladan anahtar, boş değer ya da kaybolmuş yer tutucu gördüğünde başarısız olur) ve bir pull request açın. Tüm kurallar ve sözlük [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md) dosyasındadır.

## Bilerek çevrilmeyenler {#what-is-not-translated-on-purpose}

CLI sunumu varsayılan olarak İngilizcedir. Desteklenen mesajları ve denetim özetlerini çevirmek için `seo.cli_locale` / `SEO_CLI_LOCALE` ayarlarını kullanın veya `--display-locale=it` iletin. Pro'nun ayrı bir `seo-pro.cli_locale` ayarı vardır. Görüntüleme dili, `--locale` ile seçilen içerik dilinden ayrıdır.

- Komut yardımı, bakım tanılama mesajları ve `seo:explain` çıktısı İngilizce kalır; PASS/WARN/FAIL etiketleri değişmez.
- Üretilen HTML (`<meta>`, JSON-LD): paketin dili değil, içeriğinizin dili kullanılır.
- Sorun kodları, JSON anahtarları ve durum kodları sabit tanımlayıcılar olarak kalır. `--json` çıktısındaki kullanıcıya yönelik etiketler çevrilebilir; entegrasyonlar anahtarları ve kodları kullanmalıdır.

## Diğer yarısı: içeriğinizin dili {#the-other-half-your-content-s-language}

Bu sayfa *paketin* konuştuğu dili anlatır. *İçeriğinizin* dilini nasıl ele aldığı — yazı sistemine göre başlık uzunluğu bütçeleri, kısaltma, büyük/küçük harf dönüşümü, hreflang politikaları, `inLanguage`, bölgesel arama motorları ve OG görseli yazı tipleri — [çok dilli içerik](/tr/guide/multilingual) sayfasında açıklanır.
