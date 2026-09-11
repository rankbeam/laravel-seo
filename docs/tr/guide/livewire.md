---
description: "Rankbeam'in framework'ten bağımsız @seo yönergelerini Livewire uygulamalarında kullanın. Head içine düz HTML üretir; tam sayfa bileşenlerde ve Blade yerleşimlerinde Blade'deki gibi çalışırlar."
---

# Livewire {#livewire}

`@seo` Blade yönergeleri framework'ten bağımsızdır: `<head>` içine düz HTML üretirler. Dolayısıyla her Livewire uygulamasında Blade'deki gibi çalışırlar.

## İlk (tam sayfa) çıktı {#initial-full-page-render}

Bir **tam sayfa Livewire bileşeninde** (bileşen döndüren bir rotada) veya Livewire bileşenlerini saran herhangi bir Blade yerleşiminde `@seo`, [Blade kılavuzundaki](/tr/guide/blade) gibi çalışır:

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

İlk HTTP yanıtı, tarayıcı botların görebildiği eksiksiz head bölümünü taşır: başlık, açıklama, kanonik bağlantı, Open Graph, Twitter ve JSON-LD. Arama motoru tarayıcıları ve sosyal paylaşım önizleme araçları bu yolu görür; burada çıktı tamamen doğrudur.

## `wire:navigate` ile ilgili önemli nokta {#the-wire-navigate-caveat}

Livewire'ın [`wire:navigate`](https://livewire.laravel.com/docs/navigate) özelliği, bağlantı tıklamalarını SPA tarzı ziyaretlere dönüştürür. Böyle bir ziyarette Livewire `<body>` bölümünü değiştirir ve **`<head>` bölümünü birleştirir**; ancak bir SEO paketi açısından önemli bir farklılık vardır:

- **`<title>` ve `<meta>`/`<link>`** yeni sayfanın head bölümünden birleştirilir; bu nedenle çözümlenen başlık ve meta verileri genellikle güncellenir.
- **`<script>` kaldırılamayan bir varlık olarak ele alınır.** Livewire, yeniden çalıştırılmalarının JavaScript'inizi bozmaması için gördüğü her `<script>` öğesini tutar. Bu da **JSON-LD `<script>` bloklarının birikmesi** demektir: üç yazı ziyaret edildiğinde üçünün de şeması aynı anda head içinde kalır; yapılandırılmış veriyi okuyan bir araç yanlış varlığı veya birden fazla varlığı görür.

Temizliği mümkün kılmak için renderer, ürettiği **her JSON-LD script öğesini işaretler**:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## JSON-LD temizliğini ekleyin {#ship-the-json-ld-cleanup}

Bunu bir kez ekleyin (örneğin kök yerleşiminizde, `@livewireScripts` sonrasında). Her `wire:navigate` olayında yalnızca **geçerli sayfanın** şemasını tutar ve eskileri kaldırır:

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        // The page we are now on. data-seo-url is the canonical (query-stripped),
        // so compare on the query-stripped location.
        const here = window.location.href.split('#')[0].split('?')[0]

        // Keep only the LAST schema for this page; remove every other-URL
        // (stale) script AND same-URL duplicates Livewire re-adds when a page is
        // revisited — including clearing a lone stale script when this page has
        // none. Iterate from the end so the freshest copy is the one kept.
        const scripts = [...document.querySelectorAll('script[data-seo-schema]')]
        let kept = false
        for (let i = scripts.length - 1; i >= 0; i--) {
            const url = (scripts[i].getAttribute('data-seo-url') || '').split('?')[0]
            if (url === here && !kept) { kept = true; continue }
            scripts[i].remove()
        }
    })
</script>
```

Yalnızca renderer'ın zaten ürettiği `data-seo-schema` işaretine ve URL'ye özel kimliğe dayanır; sayfa başına ek bağlantı kurmanız gerekmez.

::: warning Son eklenen script yerine geçerli URL ile karşılaştırın
Bu kodun önceki bir sürümü, ikiden az şema script'i varsa işlemi bırakıyor ve *son eklenen* script'i geçerli sayfanın şeması sayıyordu. JSON-LD **içeren** bir sayfadan JSON-LD **içermeyen** bir sayfaya geçtiğinizde bu yaklaşım head içinde eski şemayı bırakır (yalnızca eski script vardır; erken dönüş onu korur). Ayrıca bir sayfa yeniden ziyaret edildiğinde Livewire'ın eklediği **aynı URL'ye ait kopyayı** kaldıramaz. Her `data-seo-url` değerini `window.location` ile karşılaştırıp yalnızca **son** eşleşmeyi tutmak, her durumda hem eski şemayı *hem de* kopyaları kaldırır. `rankbeam-examples` Livewire uygulaması ve tarayıcı testi bunu doğrular.
:::

::: tip SPA gezinmesinde tekil meta etiketleri
Livewire'ın head birleştirmesi çoğu durumda tekil `<meta>`/`<link>` etiketlerinin eski kalmasını önler; ancak kesin davranış Livewire sürümünüze ve yerleşiminizin yapısına bağlıdır. Arama motoru tarayıcılarına doğru meta verilerinin sunulmasının kritik olduğu sayfalarda, **tam sayfa yenilemeyi** (`wire:navigate` içermeyen düz bir bağlantı) tercih edin veya ilk HTTP yanıtının esas alınabilmesi için sayfayı **sunucuda oluşturun**. [`rankbeam-examples`](https://github.com/rankbeam) Livewire uygulaması, bunu doğrulamak için tarayıcıda gerçek bir `wire:navigate` akışını çalıştırır.
:::

## Filament {#filament}

Filament altyapıda Livewire kullanır, ancak bir **yönetim ve içerik düzenleme arayüzüdür**: `seo_meta` verilerini düzenler; herkese açık ön yüzünüzün head bölümünü asla üretmez. [Filament kılavuzuna](/tr/guide/filament) bakın; buradaki kurallar yönetim paneli için geçerli değildir.
