---
description: "İçerik uzlaşmasıyla yapay zekâ tarayıcılarına sayfanın temiz Markdown gösterimini sunun; normal ziyaretçiler değişmeyen HTML'i alsın. Ücretsiz çekirdek özelliği, varsayılan olarak kapalıdır."
---

# Botlar için Markdown {#markdown-for-bots}

Uygulamanın HTML sayfası, içeriği gezinme öğeleri, script'ler ve yerleşim işaretlemesiyle sarar. Bazı yapay zekâ tarayıcıları ve yanıt motorları, sunulduğunda daha temiz bir gösterimi kabul eder. Bu özellik, içerik uzlaşması yoluyla isteyen istemcilere sayfanın **Markdown gösterimini** sunabilir; normal ziyaretçiler ise değişmeyen HTML'inizi almaya devam eder. Bu, isteğe bağlı bir uyumluluk tercihidir; belirli bir istemcinin sonucu nasıl ayrıştıracağına veya kullanacağına dair bir vaat değildir.

[Yapay zekâ tarayıcı denetimiyle](/tr/guide/ai-crawlers) birlikte çalışır: o özellik erişim politikasını belirler; bu özellik ise istek sırasında *hangi* içeriğin sunulacağını seçer.

Çekirdek paketin ücretsiz bir özelliğidir ve **varsayılan olarak kapalıdır**.

## Nasıl çalışır? {#how-it-works}

Etkinleştirdiğinizde bir içerik uzlaşması middleware'i kaydedilir. Normal yanıtınız üretildikten sonra, **yalnızca şu iki koşul birlikte sağlanırsa** yanıtın yerine Markdown koyar:

1. **İstek Markdown ister:** açık bir `Accept: text/markdown` başlığı, `?format=md` sorgusu veya (isteğe bağlı) user-agent üzerinden tanınan bir yapay zekâ tarayıcısı.
2. **Rota için bir Markdown kaynağı çözümlenir.**

Aksi halde yanıt değişmeden geçer; normal tarayıcı etkilenmez ve yalnızca başarılı bir **HTML** yanıtı değiştirilir (JSON, yönlendirme veya indirme yanıtı asla değiştirilmez).

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Markdown nereden gelir? {#where-the-markdown-comes-from}

Aşağıdaki kaynaklar eşleşen rota için Markdown sağlayabilir. Middleware **önce kayıtlı rota kaynağını**, ardından rotaya bağlanmış modelleri dener. Her modelde açıkça tanımlanan `toSeoMarkdown()` metodu, yerleşik yedek üretimden önceliklidir; bu metodun null veya boş sonuç döndürmesi, o model için yedek üretimi devre dışı bırakır.

### 1. Modelin kendi Markdown çıktısı {#_1-a-model-s-own-markdown}

Kayıtlı hiçbir rota kaynağı içerik döndürmediğinde, `toSeoMarkdown()` uygulayan bir rota bağlı model kendi çıktısını kontrol eder (`ProvidesSeoMarkdown` sözleşmesini uygulayın veya yalnızca metodu ekleyin):

```php
use Rankbeam\Seo\Contracts\ProvidesSeoMarkdown;

class Post extends Model implements ProvidesSeoMarkdown
{
    use HasSEO;

    public function toSeoMarkdown(): ?string
    {
        return $this->body_markdown; // your already-clean markdown
    }
}
```

### 2. Kayıtlı rota kaynağı {#_2-a-registered-route-source}

Modelsiz rotalar için veya modelin çıktısını geçersiz kılmak amacıyla rota adına göre bir kaynak kaydedin:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. Yerleşik yedek üretim {#_3-the-built-fallback}

Rotaya bağlanmış bir `HasSEO` modelinde `toSeoMarkdown()` yoksa middleware, çözümlenen **başlığı** (H1 olarak), **açıklamayı** ve modelin **`getContentForSEO()`** değerini kullanarak temel bir belge oluşturur:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning İçerik olduğu gibi sunulur
Yedek üretim, `getContentForSEO()` değerini aynen çıktı olarak verir. İçeriğiniz Markdown yerine HTML ise dönüşümü kontrol etmek için `toSeoMarkdown()` uygulayın. Yedek üretimi tamamen kapatmak için `seo.markdown_for_bots.build_from_content = false` kullanın.
:::

## Yapılandırma {#configuration}

```php
// config/seo.php
'markdown_for_bots' => [
    'enabled'            => false,    // off by default; the middleware isn't registered until true
    'auto_register_middleware' => true,
    'serve_to_known_bots' => false,   // also serve to known AI crawlers by user-agent
    'query_param'        => 'format', // the ?format=md trigger
    'query_value'        => 'md',
    'build_from_content' => true,     // build from getContentForSEO() when no toSeoMarkdown()
],
```

Yalnızca açık `Accept` / `?format` sinyaline göre içerik uzlaşması yapmak için `serve_to_known_bots` kapalı kalsın. GPTBot, ClaudeBot, PerplexityBot ve diğerlerine istemeseler bile Markdown sunmak için açın; bunlar [yapay zekâ tarayıcı kataloğu](/tr/guide/ai-crawlers) üzerinden tanınır.
