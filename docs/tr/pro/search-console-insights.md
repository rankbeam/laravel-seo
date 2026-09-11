---
description: "Zaten size ait Search Console verilerinden anahtar kelime analizleri: konum aralıkları, CTR inceleme adayları ve birden çok sayfanın paylaştığı sorgular için beş rapor."
---

# Search Console analizleri {#search-console-insights}

Kendi Search Console verilerinizden hesaplanan beş rapor: seçilen konum
aralığındaki sorgular, CTR inceleme adayları, örtüşen sorgu/sayfa sonuçları,
sorgu kümeleri ve dönem değişimleri. Üçü eşitlenmiş geçmişi kullanır; ikisi
önbelleklenmiş canlı isteği paylaşır. Bu belirli analizleri kapsarlar; üçüncü
taraf anahtar kelime platformunun tüm veri kümesini veya yeteneklerini değil.

[Salt okunur Search Console entegrasyonunu](/tr/pro/search-console) ve onun
geçmiş eşitlemesini temel alır. O sayfadaki `seo-pro:gsc-sync` çalışıyorsa beş
analizden üçü **ek API maliyeti olmadan** çalışır.

::: tip Ön koşul
Üç *anlık görüntü* analizi, saklanan `seo_gsc_metrics` geçmişini okur.
Önce `seo-pro:gsc-sync` zamanlayın; bkz.
[Search Console → geçmiş](/tr/pro/search-console). Ne kadar çok gün
eşitlenmişse eğilim karşılaştırması o kadar geçmişe uzanır.
:::

## Beş analiz {#the-five-surfaces}

### 1. Üst sıralara yaklaşan anahtar kelimeler {#_1-striking-distance-keywords}

**Gösterim ağırlıklı ortalama konumu 5–20 arasında olan** sorgular,
gösterim sayısına göre sıralanır. Alaka düzeyini ve dahili bağlantıları
incelemek için kullanın. Bu aralık, küçük bir değişikliğin sorguyu ilk
sayfaya taşıyacağını göstermez.

### 2. CTR fırsatları {#_2-ctr-opportunities}

**İyi sıralandığı hâlde konumuna göre beklenenden az tıklanan** sorgular.
Her sorgunun gerçek CTR değeri, sektördeki konuma göre CTR eğrilerinden
birleştirilmiş bir eğriyle karşılaştırılır. Gerçek gösterimleri olup beklenen
oranın çok altında kalanlar **başlık/açıklama yeniden yazım adayıdır**;
tahmini *kaçırılan tıklamalarına* göre sıralanırlar. Bu liste,
[yapay zekâ meta önericisinin](/tr/pro/ai-assist) doğal girdisidir:
hangi sorgular için yeniden yazımın değerlendirileceğini gösterir.

### 3. Kanibalizasyon {#_3-cannibalization}

Aynı terim için **iki veya daha fazla URL’nizin göründüğü** sorgular.
Örtüşme mutlaka zararlı değildir: birleştirmeden veya farklılaştırmadan
önce sayfaların farklı amaçlara hizmet edip etmediğini inceleyin.

### 4. Sorgu kümeleri {#_4-query-clusters}

**Her sayfanın gerçekten sıralandığı sorgular**, sayfaya göre gruplanır:
Google’ın gözünde sayfanın gerçek konu kapsamı. Amaçlanan konudan uzaklaşan
bir sayfayı veya hiç hedeflemediğiniz değerli bir terimde sessizce sıralanan
bir sayfayı bulmak için yararlıdır.

### 5. Önceki döneme göre eğilim {#_5-trend-vs-previous-period}

Geçerli aralık ile hemen öncesindeki eşit uzunlukta aralık arasında tıklama,
gösterim, konum ve CTR bakımından **en büyük değişimler**. Konum yalnızca
sorgu her iki dönemde de trafik aldıysa karşılaştırılır; tamamen yeni veya
tamamen kaybolmuş bir sorgu için karşılaştırılacak değer yoktur.

## Sayılar nereden gelir: canlı veri ve anlık görüntü {#where-the-numbers-come-from-live-vs-snapshot}

Her analiz, doğru yanıtı en düşük maliyetle veren kaynağı okur. Saklanan
geçmiş, hangi **sorgunun** hangi **sayfayla** eşleştiğini yeniden kuramaz;
boyutları ayrı saklar. Bu çifte ihtiyaç duyan iki analiz, canlı veri alan
yegâne analizlerdir ve **tek bir önbelleklenmiş isteği paylaşırlar**.

| Analiz | Kaynak | Neden |
|---|---|---|
| Üst sıralara yaklaşanlar | **Yerel anlık görüntü** | Sorgu başına konum + gösterim gerekir; eşitlenmiş geçmişte zaten vardır, API maliyeti yoktur |
| CTR fırsatları | **Yerel anlık görüntü** | Size ait aynı veriler; beklenen CTR eğrisi sorgulanan veri değil, sabit karşılaştırma ölçütüdür |
| Eğilim farkları | **Yerel anlık görüntü** | Gerçek günlük geçmiş gerekir; eşitlemenin sakladığı tam olarak budur |
| Kanibalizasyon | **Canlı** (sorgu × sayfa) | Sorgu→sayfa eşleştirmesi saklanmaz; her çifti saklamak depolamayı katlar |
| Sorgu kümeleri | **Canlı** — *3. analizin isteğini paylaşır* | Aynı çift verileri, sorgu yerine sayfaya göre gruplanır |

Dolayısıyla Analizler sayfasına ziyaret, **en fazla bir** Search Analytics
isteği yapar; sonuç `search_console.cache_ttl` saniye önbelleklenir. Çift kullanan analizler
bilinçli olarak canlıdır: kanibalizasyon ve kümeleme, güncel görünümü
istediğiniz *belirli bir ana* ilişkin sorulardır. Ortak önbellek tekrar
isteklerini sınırlar. Belirteç yenileme ek kimlik doğrulama isteği
gerektirebilir ve Google kotaları geçerliliğini korur. Anlık görüntü
analizleri ağa hiç erişmez.

## Gösterge panelinde {#in-the-dashboard}

Filament eklentisi kuruluysa *SEO* gezinme grubu altında **Search Console
Analizleri** görünür; yalnızca entegrasyon açıkken. Tamamen salt okunurdur:
her analiz bir bölüm olarak görüntülenir. Anlık görüntü bölümleri boşken
“geçmişinizi eşitleyin” ipucu gösterir. Çift analizlerinin canlı veri isteği
başarısız olursa temizlenmiş satır içi bildirim görünür; sayfa engellenmez.

## Yapılandırma {#configuration}

Her şey `config/seo-pro.php` içindeki `search_console.insights` altındadır. Varsayılanlar makuldür;
eşikleri sitenizin ölçeğine göre ayarlayın.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info Beklenen CTR eğrisi
CTR fırsatı eğrisi, yayınlanmış organik konuma göre CTR ortalamalarından
birleştirilmiş **sezgisel** bir ölçüdür. Sitenize ilişkin bir iddia değil,
karşılaştırma ölçütüdür. Burada işaretlenen sorgu kanıtlanmış kusur değil,
*incelenecek adaydır*. Kendi ölçülmüş eğriniz varsa `insights.ctr_curve` içine
`position => percent` eşlemesi olarak koyun.
:::

## Ayrıca bakın {#see-also}

- [Search Console](/tr/pro/search-console) — bu analizlerin okuduğu salt okunur entegrasyon ve geçmiş eşitlemesi
- [Markanıza özel raporlar](/tr/pro/reports) — markalı PDF’de dönemler arası değişimler
- [Yapay zekâ desteği](/tr/pro/ai-assist) — CTR analizinin işaretlediği başlıkları/açıklamaları yeniden yazın
