---
description: "Tam SEO taramalarını zamanlayın; son taramadan bu yana ortaya çıkan, geri dönen veya düzeltilen sorunları etkiye göre sıralı olarak panoda ve isteğe bağlı e-postada görün."
---

# Tarama zamanlama ve değişim {#scan-scheduling-delta}

Tam SEO taramasını **zamanlayın** ve **son taramadan bu yana neyin değiştiğini** görün: yeni ortaya çıkan, geri dönen veya düzeltilen sorunlar, etkiye göre sıralı olarak panoda ve isteğe bağlı özet e-postasında yer alır.

Birlikte çalıştıkları için iki özellik tek sayfada anlatılıyor: zamanlanmış taramanın sonuçlarını almayı yararlı kılan şey değişim bilgisidir.

## Son taramadan bu yana neler değişti {#what-changed-since-the-last-scan}

Tamamlanan her tarama, **açık sorun kümesini** küçük bir anlık görüntüye (`seo_scan_run_issues`) sabitler. İki çalıştırmanın anlık görüntülerini karşılaştırmak üç grupta kesin bir fark verir:

- **Yeni**: önceden açık olmayan, şimdi açık olan ve daha eski hiçbir taramada açık olmamış bir sorun; ilk kez bulunan bir bulgudur.
- **Gerileyen**: düzeltilmişken **geri dönen** sorun. “Daha ciddi hâle geldi” anlamında değildir; önem derecesi sorun türüne göre sabittir. Anlamlı gerileme, sorun [yaşam döngüsünün](/tr/pro/scan-issues#issue-lifecycle) yeniden açılma dediği geri dönüştür.
- **Düzeltilen**: önceki taramada açık olan ve artık bulunmayan sorun.

Her grup [etkiye göre sıralanır](#impact-ordering); böylece liste önemli sorunlarla başlar.

### Neden sorun tablosu yerine anlık görüntü kullanılır {#why-a-snapshot-not-the-issues-table}

Sorunların düzeltilme / yeniden açılma [yaşam döngüsü](/tr/pro/scan-issues#issue-lifecycle) vardır: aynı satır taramalar boyunca yerinde güncellenir; hâlâ açık olduğu her seferinde `scan_run_id` en yeni çalıştırmaya atanır. Bu, kalıcı sorun geçmişi için uygundur; ancak canlı tablo *N numaralı çalıştırmanın sonunda hangi sorunların açık olduğunu* söyleyemez. Süren bir sorun her zaman yalnızca son çalıştırmayı gösterir.

Bu nedenle her çalıştırma açık kümesinin anlık görüntüsünü, değişmeyen **sorun parmak izi** olan `issue_type | target | field` ile anahtarlayarak saklar. [Markalı raporun](/tr/pro/reports) karşılaştırdığı kimlik de aynıdır. Böylece değişim, sabitlenmiş iki parmak izi kümesi üzerinde basit küme işlemleridir; yalnızca ardışık değil, **herhangi iki** çalıştırma için doğrudur.

### Sınır durumları açıkça nasıl ele alınır {#edge-cases-handled-honestly}

- **Bir sayfa tarama kümesinden çıkar.** Açık sorunları yeniden taranmadığından açık kalır ve her çalıştırmada yeniden anlık görüntüye alınır. Yanlışlıkla “düzeltilen” değil, **hâlâ açık** görünür. Bir sayfaya bakmayı bıraktığımız için düzeldiğini iddia etmeyiz.
- **İki tarama arasında bir kontrol kapatılır.** Sorunları artık üretilmez; yaşam döngüsü onları düzeltilmiş işaretler ve açık kümeden çıkarır, dolayısıyla **düzeltilen** görünürler. Bu, tarayıcının mevcut değerlendirmesini doğru yansıtır; sorun düzeyinde “düzelttiniz” ile “kontrolü kapattınız” ayrımı yapılamaz.
- **Yükseltmeden sonraki ilk tarama.** Bu özellikten önceki çalıştırmalar anlık görüntü taşımaz, dolayısıyla karşılaştırma temeli seçilmez. Anlık görüntü içeren ilk tarama, bütün siteyi “yeni” göstermek yerine **başlangıç** oluşturur: değişim olmadan mevcut durum. İkinci anlık görüntülü taramadan itibaren değişim hesaplanır.

### Panoda {#on-the-dashboard}

[SEO panosundaki](/tr/pro/installation) **“Son taramadan bu yana neler değişti”** bileşeni, tamamlanan en son iki taramayı karşılaştırarak yeni / gerileyen / düzeltilen sayılarını ve her gruptaki en önemli sorunları etkiye göre sıralı gösterir. İki taramanın anlık görüntüsü oluşana kadar kısa bir “başlangıç” notu gösterir.

## Etkiye göre sıralama {#impact-ordering}

En büyük sorunların önce gelmesi için her değişim grubu **etki** puanıyla sıralanır:

```
impact = severity_weight × page_importance
```

- **severity_weight**, yayımlanmış [puanlama ölçütlerini](/tr/pro/scoring) kullanır: kritik sorun `40`, uyarı `15`, bildirim `5` değerindedir. Önem derecesi, ürünün bir kusurun önemine ilişkin açık değerlendirmesidir; sıralama ikinci bir ölçek icat etmek yerine bunu okur.
- **page_importance**, **gerçek arama talebine**, yani sayfanın [Search Console'da](/tr/pro/search-console) aldığı gösterim sayısına dayanır; sayfaları birbirinden gerçekten ayıran sinyal budur:

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  Gösterimler logaritmik ölçeklenir; 10× trafik alan bir sayfa 10× önemli sayılmaz. En yoğun sayfanıza göre normalleştirilir, böylece formül küçük blogda da büyük katalogda da aynı biçimde çalışır. Site haritası `<priority>` değeri yalnızca **zayıf bir ikincil** sinyaldir: varsayılan olarak atanmamıştır ve atandığında bile sabit bir değerdir, dolayısıyla sıralamanın temelini oluşturamaz. `seo.sitemap.models` içinde tür bazında öncelikler yapılandırdıysanız küçük bir etkide bulunur.

**Search Console yoksa da sorun değil.** Senkronize GSC geçmişi ve yapılandırılmış öncelikler yokken `page_importance` her sayfa için `1` olur ve etki yalnızca **önem derecesine göre sıralamadır**. Bu, uydurulmuş değil, makul bir varsayılandır. Talebe göre ağırlıklandırmayı açmak için [GSC geçmişini](/tr/pro/search-console#historical-metrics) (`seo-pro:gsc-sync`) senkronize edin.

Ağırlıkları ve zaman penceresini `seo-pro.scan.delta.impact` altında ayarlayın.

## Bir taramayı zamanlamak {#scheduling-a-scan}

Paket **varsayılan olarak hiçbir şey zamanlamaz**. Açmak için:

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

Tam kontrol için tam bir cron ifadesi de ayarlayabilirsiniz; `frequency` değerine göre önceliklidir:

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

Bu kadar: paket, zamanlanmış **komutun** çakışan yürütmelerini önlemek için `withoutOverlapping` kullanarak `seo-pro:scan` komutunu Laravel zamanlayıcısına kaydeder. Bu zamanlayıcı kilidi kuyruktaki işlerin bütün ömrünü kapsamaz. Kayıt yalnızca zamanlayıcı/konsol bağlamında çalışır; **web isteklerine ek yük getirmez**.

::: warning Çalışan bir zamanlayıcı gerekir
Laravel zamanlayıcısı çalışmıyorsa paket zamanlaması hiçbir işlem yapmaz. Standart tek satırlık cron (`* * * * * php artisan schedule:run`) veya geliştirme ortamında `php artisan
schedule:work` gerekir. [Canlı ortam kurulumu](/tr/pro/production#scheduler) sayfasına bakın.
:::

Kendiniz bağlamak istiyorsanız `schedule.enabled` seçeneğini kapalı bırakıp komutu kendi konsol çekirdeğinizden zamanlayın; değişim ve özet yine çalışır:

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync`, hedef başına bir işi kuyruğa almak yerine taramayı aynı süreçte çalıştırır. Kuyruk işçisi olmayan küçük bir site için uygundur; canlı ortamda kapalı tutun.

## Özet e-postası {#summary-e-mail}

Zamanlanmış tarama bitince **“son taramadan bu yana neler değişti”** e-postası almak için özelliği açın. Yeni / gerileyen / düzeltilen sorunların etkiye göre sıralandığı, markanıza uyarlanmış bir HTML özetidir:

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

[Markalı raporun](/tr/pro/reports) marka ve e-posta kurulumunu kullanır; ajans adınız, logonuz ve vurgu renginiz aktarılır. Ayrı alıcılar ayarlamazsanız rapor alıcıları kullanılır. `only_on_change`, taramada hiçbir şey değişmediyse e-postayı atlar; ilk başlangıç taraması her zaman gönderilir.

Özet yalnızca `--notify` ile başlatılan çalıştırmalarda gönderilir; zamanlayıcı, `notify.enabled` açıkken bunu otomatik ekler. **`--notify` olmadan** elle çalıştırılan `seo-pro:scan` kimseye e-posta göndermez.

::: tip Başka bir kanal mı?
E-posta yerine Slack, webhook veya özel özet istiyorsanız `Rankbeam\Seo\Pro\Events\SeoScanCompleted` olayını dinleyin. Tamamlanan her çalıştırmada bir kez tetiklenir ve çalıştırmayı taşır; böylece değişimi `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` ile oluşturup istediğiniz yere yönlendirebilirsiniz.
:::

## Saklama süresi {#retention}

Anlık görüntüler, bağlı oldukları çalıştırma silindiğinde zincirleme silinir. Dolayısıyla [`seo-pro:scan-prune`](/tr/pro/production#scheduler) bunları otomatik yaşlandırıp kaldırır; zamanlanacak yeni bir iş yoktur. Bir çalıştırma ancak hiçbir açık sorun taşımadığında temizlenir; böylece yakın tarihli bir çalıştırmanın anlık görüntüsü karşılaştırma için her zaman bulunur.

`seo-pro.scan.delta.snapshot => false` ile anlık görüntü almayı tamamen kapatın; değişim ve özet de kapanır.
