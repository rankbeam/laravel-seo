---
description: "Örnek verileri hazır Rankbeam demosunu tek komutla çalıştırın. Yayımlanmış paketlerle, path deposu olmadan gerçek sayfalarda meta verilerini, JSON-LD şema grafını ve site haritasını görün."
---

# Demoyu çalıştırın {#run-the-demo}

Rankbeam'i önce kendi uygulamanıza bağlamadan gerçek sayfalarda çalışırken görmenin en hızlı yolu, çalıştırılabilir demodur. Örnek verilerle doldurulmuş bu Laravel uygulaması **yayımlanmış** paketleri kurar (path deposu veya komşu checkout gerekmez) ve birkaç sayfayı eksiksiz SEO meta verileri, JSON-LD şema grafı ve site haritasıyla sunar. Lisans eklerseniz Pro'nun [teknik SEO denetimini](/tr/pro/scan-issues) de çalıştırır.

## Tek komut (ücretsiz çekirdek) {#one-command-free-core}

Demo, [`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples) deposunda bir Docker imajı olarak sunulur:

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

`http://localhost:8080` adresini açın. Çözümlenen `<head>` bölümünü görmek için herhangi bir sayfanın kaynağını görüntüleyin; oluşturulan site haritası için `/sitemap.xml` adresini ziyaret edin. Buradaki her şey, Packagist üzerinden kurulan ücretsiz MIT çekirdeğine aittir.

## Pro ile (denetim) {#with-pro-the-audit}

Pro, proje başına lisanslanır ve özel Composer deposundan kurulur. Lisansınızı `COMPOSER_AUTH` aracılığıyla iletin (derleme sırrıdır; hiçbir zaman imaj katmanına yazılmaz) ve Pro bayrağıyla derleyin:

```bash
export COMPOSER_AUTH='{"http-basic":{"blog.rankbeam.dev":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

Açılışta demo, [`seo:doctor`](/tr/pro/headless#setup-health-check) komutunu ve örnek sayfalar üzerinde ilk `seo-pro:scan` işlemini çalıştırır. Sağlık raporu, tarama özeti ve [0–100 puanı](/tr/pro/scoring) compose günlüklerinde görünür.

## Pro iş akışını görün {#see-the-pro-workflow}

[Tarama → düzeltme → rapor rehberi](/tr/pro/walkthrough), çalışan bir Merchant demosunu gösterir: gerçek bir tarama, sorun ayrıntıları, Filament'te kaydedilen açıklama, yeniden tarama ve indirilebilir PDF. İçerik örnek veri olarak etiketlenmiştir; önceki ve sonraki sonuçlar iki yeni taramadan gelir.

Henüz herkese açık, barındırılan etkileşimli bir demo yoktur. Motoru yerelde çalıştırmak için Docker kullanın; [demo README dosyası](https://github.com/rankbeam/rankbeam-examples/tree/main/demo), kurulumu ve yayımlanmış paketlerle yerel paketler arasında geçişi açıklar.

::: tip Zaten bir uygulamanız mı var?
Demoyu atlayıp doğrudan [Hızlı başlangıca](/tr/guide/quickstart) geçin: kurulumdan eksiksiz oluşturulan `<head>` bölümüne beş dakikada ulaşın.
:::
