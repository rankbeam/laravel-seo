---
description: rankbeam/laravel-seo paketini Composer ile kurun, yapılandırmayı yayımlayın ve migration'ları çalıştırın. Laravel 11, 12 ve 13 için gereksinimler ve kurulum adımları.
---

# Kurulum {#installation}

## Gereksinimler {#requirements}

- Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 veya 13
- `spatie/laravel-sitemap` ^7.0 veya ^8.0 — isteğe bağlıdır; yalnızca site haritası oluşturmak için gereklidir

## Paketi kurun {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Kurulum bu kadar. Servis sağlayıcısı ve `SEO` facade'ı otomatik keşfedilir; iki migration, pakete ait olan tek tabloları oluşturur:

| Tablo | Amaç |
|---|---|
| `seo_meta` | Model başına açıkça atanan değerler (morph + dil kodu) |
| `seo_defaults` | Genel, model türüne ve rotaya özel varsayılanlar |

## İsteğe bağlı: site haritaları {#optional-sitemaps}

Site haritası oluşturma işlemi [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap) paketini kullanır:

```bash
composer require spatie/laravel-sitemap
```

Kaynaklar ve oluşturma işlemi için [site haritası kaynakları kılavuzuna](/tr/guide/sitemaps) bakın.

## v1'den mi yükseltiyorsunuz? {#upgrading-from-v1}

Uygulamanız `fibonoir/laravel-seo` v1 kullanıyorsa önce [v1'den yükseltme](/tr/guide/upgrade-from-v1) sayfasını okuyun. Üretici adı, ad alanı ve paketin sunduğu arayüzlerin tümü değişti; v1'in yayımladığı dosyalar v2 yapılandırmasıyla çakışabilir.

## Tamamlayıcı paketler {#companion-packages}

| Paket | Ekledikleri | Lisans |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Filament 4/5 kaynak formları için SEO bölümü | MIT |
| [`rankbeam/laravel-seo-pro`](/tr/pro/installation) | Herhangi bir Laravel uygulamasında kuyruk üzerinden site taramaları, yönlendirme yöneticisi ve 404 izleme; isteğe bağlı Filament panosu | Ticari |
