---
description: "Rankbeam'i kurun, mevcut bir modele HasSEO trait'ini ekleyin, SEO alanlarını kaydedin ve Blade'in ürettiği etiketleri doğrulayın."
---

# Hızlı başlangıç {#quickstart}

Mevcut bir Laravel 11, 12 veya 13 uygulaması ve çalışan bir veritabanıyla başlayın.
Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5. Çekirdek paket MIT lisansı kapsamında ücretsizdir; hesap veya Pro lisansı gerekmez.

## Kurulum {#install}

Bu komutları uygulamanızın dizininde çalıştırın:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Servis sağlayıcısı otomatik keşfedilir. Migration, SEO tablolarını oluşturur; uygulamanızın içerik modellerini oluşturmaz.

## Örneğe başlamadan önce {#before-the-example}

Aşağıdaki adımlar, bir `Post` modeliniz, kaydedilmiş bir yazınız ve Blade görünümüne bu yazıyı `$post` olarak ileten bir `posts.show` rotanız olduğunu varsayar. Bu adları uygulamanıza uyarlayın. Kılavuz, bu sayfaya SEO ekler; bir blog oluşturmaz.

`.env` dosyasında `APP_URL` değerini sitenizin herkese açık kök adresi olarak ayarlayın. Diğer sayfa oluşturma yöntemleri için [Inertia ve JSON kılavuzunu](/tr/guide/inertia-json) veya [Livewire kılavuzunu](/tr/guide/livewire) kullanın.

## 1. Modele trait'i ekleyin {#_1-add-the-trait-to-a-model}

```php
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

`getUrlForSEO()`, çözümleyiciye modelin hangi kanonik URL'de bulunduğunu bildirir. Kanonik bağlantılar, `og:url` ve site haritası girdileri bu değeri kullanır.

## 2. Head bölümünü oluşturun {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` başlık, meta açıklaması, kanonik bağlantı, robots, Open Graph ve Twitter Card etiketlerini, ayrıca çözümlenen verilere eklenmiş JSON-LD'yi üretir. Henüz açıkça atanmış bir değer kaydedilmediyse tüm değerler hesaplanan yedek değerlerden (yazının kendi özniteliklerinden) ve yapılandırdığınız varsayılanlardan gelir. Bkz. [çözümleyici öncelik sırası](/tr/concepts/resolver-precedence).

## 3. Değerleri açıkça atayın {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Açıkça atanan değerler tüm yedek değer katmanlarından önceliklidir. Çevrilmiş meta verileri için bir dil kodu iletin: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Modelleri seeder ile mi oluşturuyorsunuz?
Laravel'in varsayılan `DatabaseSeeder` sınıfı, `WithoutModelEvents` trait'ini kullanır. Bu trait, `HasSEO` trait'inin otomatik oluşturma kancasını sessizce devre dışı bırakır. Trait'i kaldırın veya seeder'larda `saveSEO()` metodunu açıkça çağırın.
:::

## 4. Sonucu doğrulayın {#_4-verify-the-result}

Yazının herkese açık sayfasını açın ve **Sayfa kaynağını görüntüle** seçeneğini kullanın. `<head>` içinde başlığın `Custom SEO Title` içerdiğini, açıklamanın `Custom meta description` olduğunu ve kanonik bağlantının yazının herkese açık URL'sini gösterdiğini kontrol edin. Yapılandırdığınız başlık son eki başlığın ardından gelebilir.

`@seo($post)` yönergesini her sayfada bir kez kullanın. Yerleşim zaten başlık veya meta etiketleri üretiyorsa yinelenmelerini önlemek için bunları değiştirin. Beklenmedik bir değer görürseniz kaynağını incelemek için [değer çözümleme kılavuzunu](/tr/guide/explain) kullanın.

## 5. Site haritası ekleyin (isteğe bağlı) {#_5-add-a-sitemap-optional}

```php
// e.g. in AppServiceProvider::boot()
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

SEO::sitemaps()->register('posts', Post::class);
```

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

`/sitemap.xml` artık oluşturulan dizini sunar. Tüm seçenekler [site haritası kaynakları kılavuzunda](/tr/guide/sitemaps) açıklanır.

## Sonraki adımlar {#where-to-go-next}

- [Çözümleyici öncelik sırası](/tr/concepts/resolver-precedence) — değerler nasıl seçilir?
- [Blade kılavuzu](/tr/guide/blade) — yedi yönergenin tamamı
- [Inertia ve JSON](/tr/guide/inertia-json) — bağımsız ön yüzler için çıktı
- [Şema grafı](/tr/guide/schema) — bağlantılı JSON-LD
- [Filament alanları](/tr/guide/filament) — iki satırla yönetim arayüzü
