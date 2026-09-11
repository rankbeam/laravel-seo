---
description: "Gerçek bir Rankbeam Pro taramasını izleyin, eksik açıklamayı inceleyin, Filament'te düzeltip yeniden tarayın ve oluşturulan örnek PDF raporunu indirin."
---

# Taramadan doğrulanmış düzeltmeye {#from-a-scan-to-a-verified-fix}

Bir tarama, demo yazısında eksik açıklama buldu. Açıklamayı Filament'te ekledik, yeniden taradık ve düzeltmeyi gösteren bir rapor oluşturduk.

Bu görüntüler 9 Eylül 2026'da çalışan yerel Merchant demosundan alınmıştır. İçerik, başlangıçta eklenen örnek verilerden oluşur; iki tarama ve rapor da bu anlatım için oluşturulmuştur. Önceden doldurulmuş bir geçmiş eğilimi yoktur. Uygulama Laravel 12 ve Filament 4 ile Rankbeam'in core paketini, ücretsiz düzenleyicisini ve Pro motorunu kullanır.

**[Oluşturulan raporu indirin (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf)**

## Kayıtlı sayfaları tarayın {#scan-the-registered-pages}

[Pro'yu kurup](/tr/pro/installation) tarama hedeflerini kaydettikten sonra çalıştırın:

```bash
php artisan seo-pro:scan --sync
```

Demo, 18 içerik kaydı ve üç rota kaydeder. Bu ilk tarama 21 hedefin tamamını hatasız tamamladı ve 20 sorun buldu: altı uyarı ve 14 bildirim.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="Tamamlanan ilk tarama: 21 hedef, 20 sorun, altı uyarı ve 14 bildirim." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Ekran görüntüleri 2× çözünürlükte alınmıştır. Tam boyutta incelemek için birini açın.*

## Bir sorunu inceleyin {#inspect-one-issue}

**SEO Panosu** içinde etkilenen satırın yanındaki **Sayfa sorunları** eylemini açın (İngilizce ekran görüntüsünde **SEO Dashboard** ve **Page issues**). “Behind the Scenes: Our Product Photography” için bulgu, eksik `description` alanını, sayfa URL'sini ve bunu tespit eden taramayı belirtir.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Sayfa sorunları penceresi Post 5 kaydını, URL’sini ve eksik açıklama alanını gösteriyor." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Açıklamayı kaydedin {#save-the-description}

Yazıyı **Posts** bölümünde açın, **SEO açıklaması** alanını doldurup kaydedin (ekran görüntüsünde **SEO description**). [Ücretsiz Filament düzenleyicisi](/tr/guide/filament), girilen metni arama önizlemesinde gösterir ve kaynağını **Elle girilmiş** olarak belirtir (ekran görüntüsünde **Manual**). Bu örnekte açıklama 142 karakterdir; başlık hâlâ yazıdan gelir.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="Kaydedilmiş SEO açıklaması ve 142 karakterlik sayacı." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="Canlı önizleme, Manual etiketiyle gösterilen elle girilmiş açıklamayı kullanıyor." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Bir alanı kaydetmek ve düzeltmeyi doğrulamak ayrı adımlardır. Tarama puanı bir sonraki taramadan sonra güncellenir. Filament kullanmıyorsanız aynı değeri modelinizin `saveSEO()` metodu üzerinden kaydedin.

## Yeniden tarayın ve değişikliği kontrol edin {#rescan-and-check-what-changed}

Aynı komutu yeniden çalıştırın:

```bash
php artisan seo-pro:scan --sync
```

Pano artık bu sorunu **Düzeltilen** olarak gösterir (ekran görüntüsünde **Fixed**). Diğer 19 sorun açık kalır.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Kaydedilmiş tarama karşılaştırması: sıfır yeni sorun, sıfır gerileme, bir düzeltilen ve 19 hâlâ açık sorun." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Kontrol | Önce | Sonra |
|---|---|---|
| Tamamlanan hedefler | 21 | 21 |
| Açık sorunlar | 20 | 19 |
| Uyarılar | 6 | 5 |
| Bildirimler | 14 | 14 |
| Ortalama teknik SEO puanı | 92 | 93 |

[Puan](/tr/pro/scoring), Rankbeam'in teknik kontrollerini yansıtır. Trafiği, arama konumunu veya yapay zekâ yanıtlarında yer almayı ölçmez. Açıklama kontrolünün geçmesi de arama motorunun o açıklamayı göstereceğini garanti etmez.

## Raporu oluşturun {#generate-the-report}

Bu örnek için yazıyı düzenlemeden **önce** bir başlangıç raporu, yeniden taramadan sonra da ikinci raporu oluşturduk:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

İkinci PDF **bir düzeltilen**, **sıfır yeni** ve **19 açık** sorun gösterir. Eğilim yalnızca yukarıdaki iki taramayı içerir. Search Console ve yapay zekâ botu günlüklemesi kapalı olduğundan bu bölümler verinin bulunmadığını belirtir.

[![Oluşturulan örnek raporun ilk sayfası: puan 93, düzeltilen bir sorun ve 19 açık sorun.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

İlk rapor karşılaştırma temelini oluşturur. Sayfayı düzelttikten sonra yalnızca bir rapor üretirseniz önceki rapora göre değişiklik gösteremez. Bu karşılaştırma temelini ilerletmemesi gereken önizlemeler için `--no-store` kullanın.

Örnek, Browsershot oluşturucusunu kullanır. Oluşturucu gereksinimleri, markalama ve zamanlanmış gönderim için [kendi markanızla raporlar](/tr/pro/reports) sayfasına bakın.

## Kendi uygulamanızda çalıştırın {#run-it-on-your-own-app}

[Pro kurulumu](/tr/pro/installation) ile başlayın, ardından çıktısını kontrol edebileceğiniz bir sayfayı tarayın. Pro [Filament olmadan](/tr/pro/headless) da çalışır. Önce ücretsiz meta veri oluşturucusunu denemek için [Docker demosunu](/tr/guide/demo) kullanın.
