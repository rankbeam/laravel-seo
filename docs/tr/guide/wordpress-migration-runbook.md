---
description: "Canlı sitede Yoast veya Rank Math'i değiştirmek için adım adım, riski düşük işlem sırası. İçe aktarıcılar varsayılan olarak boş alanları doldurur; dry-run yazmaz, WordPress değişmez."
---

# WordPress → Rankbeam geçiş uygulama kılavuzu {#wordpress-→-rankbeam-migration-runbook}

WordPress SEO sistemini (Yoast veya Rank Math) Rankbeam ile değiştirmek için adım adım işlem sırası. İçe aktarıcılar varsayılan olarak hedefteki boş alanları doldurur; `--overwrite` mevcut değerleri değiştirmeye açıkça izin verir. Deneme çalıştırmaları hiçbir şey yazmaz ve kaynak WordPress veritabanı değişmeden kalır. İçe aktarmadan önce hem kaynağı hem hedefi yedekleyin.

Bu, alan eşlemesini, şablon belirteçlerinin işlenmesini ve kaynak anahtarlarını ayrıntılı belgeleyen [WordPress'ten geçiş](/tr/guide/migrate-from-wordpress) sayfasının uygulama eşidir. *Ne* aktarıldığını orada, *nasıl ve hangi sırayla* yapılacağını burada okuyun.

::: tip Gerekenler
- Meta veri içe aktarma ve `seo:audit` için **Çekirdek** (`rankbeam/laravel-seo`).
- Yalnızca **yönlendirmeleri** de taşıyorsanız **Pro** (`rankbeam/laravel-seo-pro`); `seo_redirects` tablosu Pro özelliğidir.
- İçeriğinizin Laravel'de zaten modellenmiş olması (örneğin `App\Models\Post`), [`HasSEO`](/tr/guide/quickstart) trait'i ve WordPress slug'ını modele eşleştirme yolu (model rota anahtarı veya `--match-by` ile belirttiğiniz sütun).
:::

## Geçişin yapısı {#the-shape-of-the-migration}

WordPress satırları **URL / yazı** ile anahtarlanır; Rankbeam `seo_meta` satırları **polimorfiktir** (Eloquent modeline bağlıdır). İçe aktarma, her WordPress satırını modellerinizden biriyle eşleştirir. Üç sonuç mümkündür; her çalıştırma dağılımı raporlar:

| Sonuç | Anlam | Yapılacak işlem |
|---|---|---|
| **matched** | Satır modele bağlandı; `seo_meta` yazıldı | Yok |
| **url-only** | Satır hiçbir modelle eşleşmedi (veya `--model` verilmedi) | Sayfanın modele mi, yönlendirmeye mi ihtiyacı olduğunu belirleyin |
| **unmapped** | Satır Core 3'te yeri olmayan veri içeriyordu (özellikle **author**) | Başka yerde tutun (örneğin `getSEOAuthor()` kancası) |

---

## Adım 0 — Birlikte çalıştırın (henüz yayına geçmeyin) {#step-0-—-coexist-no-cutover-yet}

Rankbeam'i çalışan sitenin **yanına** kurun. Modellerinize `HasSEO` trait'ini ekleyip etiketleri facade/yönerge üzerinden üretin; ancak WordPress kurulumunu veya SEO eklentisini henüz **kaldırmayın**. Bu aşamada hiçbir şey içe aktarılmamış, yıkıcı işlem yapılmamıştır; yalnızca yeni sistemin başladığını doğruluyorsunuz.

Yayına geçiş sırasında yeni Laravel uygulaması ile eski WordPress sitesini aynı ana makineden sunuyorsanız Adım 5'e kadar ayrı yollarda tutun.

## Adım 1 — Meta verilerini içe aktarın (önce deneme) {#step-1-—-import-the-metadata-dry-run-first}

Her zaman **hiçbir şey yazmayan** ve neler *olacağını* gösteren tam doğrulama raporunu üreten `--dry-run` ile başlayın.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

Yararlı seçenekler (tam liste için `php artisan seo:import-from --help` çalıştırın):

| Seçenek | Amaç |
|---|---|
| `--model=` | Hedef modelin tam sınıf adı (FQCN); tekrarlanabilir. WordPress içe aktarıcıları çalıştırma başına **bir** model bağlar; içerik türü başına bir kez çalıştırın. |
| `--match-by=` | Slug ile eşleştirilecek model sütunu (varsayılan: rota anahtarı). |
| `--post-type=` | Veritabanı okuyucularını bu yazı türleriyle sınırlar (varsayılan: `post` + `page`). |
| `--connection=` | WordPress tablolarının bulunduğu veritabanı bağlantısı. |
| `--table=` | WordPress tablo **öneki** (varsayılan: `wp_`). |
| `--locale=` | `seo_meta` satırlarının yazılacağı dil kodu. |
| `--redirects-csv=` | Adım 3 için yönlendirme adaylarını da bu dosyaya üretir. |
| `--site-url=` | Mutlak URL'lerden yol türetmek için eski site URL'si. |
| `--overwrite` | Mevcut dolu `seo_meta` değerlerini değiştirir (varsayılan: **yalnızca boşları doldur**). |
| `--limit=` | Kaynak satır sayısını sınırlar (ilk deneme için yararlı). |
| `--json` | Makine tarafından okunabilir rapor. |

Deneme sonucu doğruysa uygulamak için `--dry-run` seçeneğini kaldırın:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

İçe aktarma varsayılan olarak **idempotenttir** ve **yalnızca boşları doldurur**; yeniden çalıştırmak güvenlidir ve Rankbeam'de zaten düzenlediğiniz meta verilerinin üzerine yazmaz.

## Adım 2 — Doğrulama raporunu okuyun ve arşivleyin {#step-2-—-read-and-archive-the-verification-report}

Her çalıştırma **Verification report** üretir: bir şeyi kaldırmadan önce onaylayacağınız sayılar. Kalıcı dosya olarak saklayın:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

Kontrol edilecekler:

- **matched**, SEO meta verisi taşımasını beklediğiniz sayfa sayısına eşit olmalıdır.
- **url-only**, modelle eşleşmeyen sayfaların iş listesidir. Her biri için model, yönlendirme (Adım 3) veya hiçbir işlem gerekip gerekmediğini belirleyin.
- **truncated**, `seo_meta` sütununa sığmak için kısaltılmış alanları listeler; bu başlık/açıklamaları inceleyin.
- **unmapped**, Core 3 sütunu olmayan kaynak verilerini, **her farklı `author` değerini açıkça belirterek** listeler. Yazarlar sütun olarak saklanmaz (`getSEOAuthor()` kapsamında ele alınır); rapor, kaybı aylar sonra fark etmek yerine bunları bilinçli olarak başka yere taşımanız içindir.

## Adım 3 — Yönlendirmeleri Pro'ya aktarın {#step-3-—-import-the-redirects-into-pro}

Çekirdek içe aktarıcı **`seo_redirects` tablosuna asla yazmaz**; o bir Pro tablosudur. Size sabit, sürümlenmiş yapıda CSV verir (**yönlendirme CSV biçimi v1**: `source_path,target_url,status_code,note`). Önce deneme çalıştırmasıyla Pro'ya aktarın:

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Her satır Filament yönlendirme formuyla aynı şekilde doğrulanır: bozuk satırlar, geçersiz durum kodları, **güvensiz dış hedefler**, **yinelenen kaynaklar** ve **yönlendirme döngüsü** oluşturacak kurallar neden belirtilerek atlanır; sessizce yazılmaz. Deneme çalıştırması tüm dosyayı (döngüler ve kopyalar dahil) doğrular ve hiçbir şey yazmaz. Mevcut kuralın hedefini değiştirmek için `--overwrite` iletin.

## Adım 4 — `seo:audit --strict` ile doğrulayın {#step-4-—-verify-with-seo-audit-strict}

Geçişi ücretsiz, işlem içi denetimin sonucuna bağlayın. `--strict`, **herhangi bir** sayfada sorun varsa sıfırdan farklı kodla çıkar; dolayısıyla CI/yayına geçiş kontrolü olarak kullanılabilir:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

Denetim model ve çözümleyici kontrollerini kapsar (başlık/açıklama varlığı ve uzunluğu, OG görseli, robots çakışmaları, kanonik URL biçimi). Üretilen HTML ve canlı kanonik URL kontrolleri ile 0–100 puanı [Pro taramasının](/tr/pro/scan-issues) parçasıdır; Pro'nuz varsa onu da çalıştırın. Bkz. [Ücretsiz SEO denetimi](/tr/guide/audit).

Ardından tarayıcıda birkaç gerçek sayfayı örnekleyin: kaynağı görüntüleyip `<title>`, `<meta name="description">`, kanonik, robots ve OpenGraph etiketlerinin içe aktarılan değerleri ürettiğini doğrulayın.

## Adım 5 — Eski paketi/tabloyu kaldırmadan ÖNCE doğrulayın {#step-5-—-verify-before-removing-the-legacy-package-table}

Aşağıdakilerin **tümü** sağlanana kadar WordPress veritabanını **silmeyin**, SEO eklentisini veya eski paketi **kaldırmayın**:

- [ ] İçe aktarma **her** içerik türü için çalıştırıldı (çalıştırma başına bir `--model`).
- [ ] Arşivlenmiş doğrulama raporu beklenen **matched** sayısını gösteriyor ve beklenmedik **url-only** satırı yok.
- [ ] Önemsediğiniz her **eşlenmemiş author** değeri başka yere taşındı.
- [ ] Yönlendirmeler Pro'ya aktarıldı (`seo-pro:redirects-import`) ve birkaç eski URL gerçekten yeni URL'ye 301 ile yönleniyor.
- [ ] `php artisan seo:audit --strict`, `0` koduyla çıkıyor.
- [ ] (Pro) `php artisan seo:doctor`, geride eski `seo` tablosu veya `config/seo.php` çakışması olmadığını raporluyor.
- [ ] Üretilen sayfalar tarayıcıda örneklenerek kontrol edildi.

Varsayılan ayarlarda, üzerine yazma seçeneği kullanılmadığında içe aktarıcılar yalnızca boş alanları doldurur ve idempotent çalışır. Bu koşullarda, bu kontrol aşamasından önce Adım 1'i mevcut meta verilerini değiştirmeden tekrarlayabilirsiniz; eski veriler hâlâ WordPress'tedir.

## Adım 6 — Kullanımdan kaldırın {#step-6-—-decommission}

Yalnızca Adım 5 kontrol listesi geçtikten sonra WordPress sitesini çevrimdışı bırakın; ardından veritabanını/tablolarını ve eski SEO paketini kaldırın. Yeni sistemin canlı ortamda doğru hizmet verdiğinden emin olana kadar veritabanı yedeğini tutun.

::: tip Geri alma
Varsayılan ayarlarda Adım 1–4, hedefteki `seo_meta` tablosunun boş alanlarını doldurur ve yeni yönlendirmeler ekler; WordPress verileri değişmez. Yeni yönlendirme kuralları silinerek geri alınabilir. Üzerine yazma seçeneğiyle mevcut meta verileri veya yönlendirmeler değiştirilmişse hedefteki önceki değerleri yedekten geri yükleyin. Adım 6'dan önce WordPress tarafındaki geri alma yolu *“WordPress'i sunmaya devam et”*; Adım 6'dan sonra ise *“WordPress yedeğini geri yükle”* olur.
:::

---

Bunun yerine **Laravel** SEO paketinden mi geçiyorsunuz (ralphjsmit, artesaos, Spatie)? [Diğer Laravel paketlerinden geçişe](/tr/guide/migrate-from-other-packages) bakın.
