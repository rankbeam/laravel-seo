---
description: "Derlenmiş yapay zekâ tarayıcı kataloğuna uygulanan izin/yasak politikasıyla yönetilen robots.txt ve isteğe bağlı ai.txt üretin. Sitenizi hangi botların getirebileceğini seçin. Ücretsiz çekirdek özelliği."
---

# Yapay zekâ tarayıcı denetimi (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

Büyük yapay zekâ işletmecileri web'i adlandırılmış botlarla tarar; çoğu, neleri getirebileceğini belirlemek için **robots.txt** okur. Rankbeam bu botların derlenmiş kataloğunu sunar ve basit bir izin/yasak politikasından yönetilen `robots.txt` (ve isteğe bağlı `ai.txt`) üretir. Böylece **yapay zekâ arama ve asistan tarayıcılarına izin verirken içeriğinizle eğitim yapanların erişimini kısıtlayabilirsiniz.**

Bu, çekirdek paketin ücretsiz bir özelliğidir. Pro diğer yarıyı ekler: hangi yapay zekâ tarayıcılarının gerçekten ziyaret ettiğini gösteren [gözlemlenebilirlik, yani yapay zekâ botu istek günlüğü](/tr/pro/ai-bot-monitor).

## Varsayılan politika {#the-default-policy}

Katalogdaki her bot, temel işlevine göre etiketlenir:

| Amaç | Ne yapar? | Varsayılan |
| --- | --- | --- |
| `ai_search` | Sayfalarınızı **yapay zekâ araması** yanıtları için dizine eklemek üzere getirir (yapay zekâdan gelen yönlendirme kanalı) | **allow** |
| `ai_assistant` | Sohbet içinde **kullanıcı** adına bir sayfayı gerçek zamanlı getirir | **allow** |
| `ai_training` | Model **eğitmek** için içerik toplar | **disallow** |

Bu, birçok yayımcının yapay zekâ dönemine yaklaşımını yansıtır: eğitim verisi olmayı reddederken ChatGPT araması, Perplexity ve benzerlerinin arkasındaki arama ve asistan tarayıcılarına erişilebilir kalmak. Tüm seçimleri yapılandırmada değiştirebilirsiniz.

::: warning Erişim, kaynak gösterilmek anlamına gelmez
Bir tarayıcıya izin vermek, içeriği getirmeyi *mümkün* kılar; keşfedilmeyi, dizine eklenmeyi, sıralamayı, yanıta dahil edilmeyi, alıntılanmayı veya kaynak gösterilmeyi garanti etmez. Bu politika **erişimi**, yani hangi botların sayfalarınızı getirebileceğini kontrol eder; sonrasında olacakları değil.
:::

## Hızlı başlangıç {#quick-start}

Ne yayımlayacağınızı görmek için yapay zekâ tarayıcı bloğunu yazdırın:

```bash
php artisan seo:robots-txt --print
```

İki şekilde kullanabilirsiniz.

### Seçenek A — bloğu mevcut robots.txt dosyanıza yapıştırın {#option-a-—-paste-the-block-into-your-existing-robots-txt}

`public/robots.txt` dosyasını zaten yönetiyorsanız yalnızca yönetilen bloğu alıp içine yapıştırın:

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### Seçenek B — tüm dosyayı Rankbeam yönetsin {#option-b-—-let-rankbeam-manage-the-whole-file}

Eksiksiz bir `robots.txt` oluşturun (genel bölüm + yapay zekâ yönergeleri + `Sitemap:` satırı + [llms.txt](/tr/guide/sitemaps) dosyanıza işaretçi):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Dosyanın politikanızı izlemesi için zamanlayın:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Ya da dinamik sunun: `seo.ai_crawlers.route` değerini `true` yapın; paket `/robots.txt` isteklerini geçerli yapılandırmadan yanıtlasın (oluşturma adımı gerekmez):

::: warning Statik dosya önceliklidir
Çoğu uygulamada `public/robots.txt` zaten bulunur; web sunucunuz bu dosyayı, istek Laravel tarafından yönlendirilmeden önce sunar. Dinamik rota **varsayılan olarak kapalıdır**; böylece unuttuğunuz bir dosyayı sessizce gölgeleyemez veya onun tarafından gölgelenemez. Rotayı yalnızca statik bir `robots.txt` yoksa kullanın.
:::

## Uygulamanın sınırları {#honesty-about-enforcement}

robots.txt bir istektir, engel değildir. Katalogdaki botların çoğunun buna uyduğu belgelenmiştir; ancak bazı kullanıcı tarafından tetiklenen ajanlar (`ChatGPT-User`, `Perplexity-User`) ve bazı eğitim tarayıcıları (`Bytespider`) **uymaz**. Rankbeam, geçerli olmayacak bir engel varmış izlenimi vermek yerine bu satırları `advisory` olarak işaretler. Uymayan bir botu gerçekten durdurmak için sunucu veya ağın dış katmanı düzeyinde engelleme gerekir (güvenlik duvarı, WAF, Cloudflare bot kuralları). [Pro yapay zekâ botu istek günlüğü](/tr/pro/ai-bot-monitor), hangilerine dikkat etmeniz gerektiğini gösterir.

## İçerik sinyalleri (kullanım tercihleri) {#content-signals-usage-preferences}

`Allow` / `Disallow` **erişimi**, yani botun sayfayı getirip getiremeyeceğini kontrol eder. Cloudflare'ın desteklediği [Content signals](https://contentsignals.org) standardı diğer boyutu ifade eder: getirilen içeriğin nasıl **kullanılabileceğini** belirtir. `User-agent: *` grubundaki tek bir `Content-Signal:` satırı üç tercih taşır:

| Sinyal | Türetildiği politika amacı | Anlam |
| --- | --- | --- |
| `search` | `ai_search` | Arama dizini oluşturma (bağlantılar + kısa alıntılar) |
| `ai-input` | `ai_assistant` | Sayfayı gerçek zamanlı bir yapay zekâ modeline sağlama (RAG / kaynakla temellendirme) |
| `ai-train` | `ai_training` | Yapay zekâ modeli eğitme veya ince ayar yapma |

**Varsayılan olarak kapalıdır** (etkinleştirene kadar dosya bayt düzeyinde aynı kalır). Açtığınızda Rankbeam satırı doğrudan mevcut `policy` politikanızdan türetir: `allow`, `yes` olur; `disallow`, `no` olur:

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Bir amacı `policy` içinden tamamen kaldırırsanız sinyali **atlanır**. Bu, belirtimdeki “tercih belirtilmedi” durumudur ve açık `yes`/`no` değerinden farklıdır.

::: warning robots.txt gibi tavsiye niteliğindedir
İçerik sinyalleri bir tercih belirtir; teknik kontrol **değildir**. Tarayıcı bunları görmezden gelebilir. Yukarıdaki erişim kuralları ve ağın dış katmanındaki engellemelerin yerine değil, yanında yer alırlar.
:::

## Yapılandırma {#configuration}

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose. A purpose left out is allowed.
    'policy' => [
        'ai_training'   => 'disallow',
        'ai_search'     => 'allow',
        'ai_assistant'  => 'allow',
        'search_engine' => 'allow',   // Yandex, Baidu, Naver, Seznam, … (3.15)
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
        'baiduspider' => 'disallow',  // e.g. keep a search engine you don't serve off your bandwidth
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

Katalog **kimliğini** anahtar olarak kullanan `overrides` ile bir botun politikasını amacından bağımsız değiştirebilirsiniz (örneğin `gptbot`, `claudebot`, `perplexitybot`, `google-extended`).

## Katalog {#the-catalog}

Asıl kaynak `SEO::aiCrawlers()` kataloğudur. Pro istek günlüğü de ziyaretçileri tanımak için aynı kataloğu kullanır; böylece botu kontrol eden dosya ile onu gözlemleyen panel çelişmez.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Başlıca işletmecileri kapsar: OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User), Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended), Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon, ByteDance ve diğerleri. Her birinin belgelenmiş amacı ve robots.txt belirteci bulunur.

### Bölgesel arama motorları {#regional-search-engines}

3.15 itibarıyla katalog, Google/Bing dışındaki dünyada önemli olan klasik web arama tarayıcılarını da içerir. `search_engine` amacıyla etiketlenirler ve **varsayılan olarak izinlidirler**:

| Kimlik | Belirteç | İşletmeci |
|---|---|---|
| `yandex` | `Yandex` | Yandex (Rusya) — yalın belirteç tüm botlarını kapsar |
| `baiduspider` | `Baiduspider` | Baidu (Çin) |
| `yeti` | `Yeti` | Naver (Kore) |
| `seznambot` | `SeznamBot` | Seznam (Çekya) |
| `sogou` | `Sogou web spider` | Sogou (Çin) |
| `360spider` | `360Spider` | Qihoo 360 (Çin) |
| `coccocbot` | `coccocbot-web` | Cốc Cốc (Vietnam) |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Diğer botlar gibi `policy` ve `overrides` içinde yer alırlar. Böylece `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']`, hizmet vermediğiniz iki tarayıcının bant genişliğinizi kullanmasını önler; `'list' => 'all'` her birine açık bir satır verir. İstenmedikçe `all()` ve `match()` **dışında** tutulurlar. Bunları almak için `searchEngines()`, `all(true)` veya `match($ua, true)` kullanın; böylece Pro yapay zekâ botu günlüğü ve tüm “N yapay zekâ tarayıcısı” sayıları anlamını korur:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Bir tarayıcıyı tanımak, o arama motorunda görünürlüğü veya sıralamayı garanti etmez. İlgili site doğrulama etiketleri (`yandex-verification`, `baidu-site-verification`, `naver-site-verification`, `seznam-wmt`), `seo.verification` altında bulunur; bkz. [çok dilli içerik](/tr/guide/multilingual#site-verification).
