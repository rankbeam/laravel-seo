---
description: "Nainstalujte Rankbeam, přidejte trait HasSEO ke stávajícímu modelu, uložte SEO pole a ověřte vykreslené značky v Blade."
---

# Rychlý start {#quickstart}

Začněte existující aplikací Laravelu 11, 12 nebo 13 s funkční databází.
Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5. Jádro je zdarma pod licencí MIT a nevyžaduje účet ani licenci Pro.

## Instalace {#install}

Spusťte tyto příkazy z adresáře aplikace:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Service provider se objeví automaticky. Migrace vytvoří SEO tabulky, nikoli obsahové modely vaší aplikace.

## Předpoklady příkladu {#before-the-example}

Následující kroky předpokládají existující model `Post`, uložený příspěvek a routu `posts.show`, jejíž šablona Blade dostává příspěvek jako `$post`. Názvy přizpůsobte aplikaci. Průvodce přidává SEO k této stránce, nevytváří blog.

Nastavte `APP_URL` v `.env` na veřejný origin webu. Pro jiné způsoby vykreslování použijte [průvodce Inertia a JSON](/cs/guide/inertia-json) nebo [průvodce Livewire](/cs/guide/livewire).

## 1. Přidejte trait k modelu {#_1-add-the-trait-to-a-model}

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

`getUrlForSEO()` říká resolveru, na jaké kanonické URL model žije. Poskytuje podklad pro kanonické adresy, `og:url` a záznamy v mapě webu.

## 2. Vykreslete hlavičku {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` vypíše titulek, meta popis, kanonickou adresu, robots, značky Open Graph a Twitter Card a JSON-LD připojená k vyhodnoceným datům. Pokud zatím nejsou uložené explicitní hodnoty, vše pochází z vypočtených náhradních hodnot — vlastních atributů příspěvku — a nastavených výchozích hodnot. Viz [pořadí přednosti resolveru](/cs/concepts/resolver-precedence).

## 3. Nastavte explicitní hodnoty {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Explicitní hodnoty mají přednost před všemi náhradními vrstvami. Pro přeložená metadata předejte jazykovou verzi: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Plníte modely pomocí seederů?
Výchozí `DatabaseSeeder` Laravelu používá trait `WithoutModelEvents`, který bez upozornění vypíná automatické vytváření z `HasSEO`. Trait odeberte nebo v seederech výslovně volejte `saveSEO()`.
:::

## 4. Ověřte výsledek {#_4-verify-the-result}

Otevřete veřejnou stránku příspěvku a zvolte **Zobrazit zdrojový kód stránky**. V `<head>` ověřte, že titulek obsahuje `Custom SEO Title`, popis je `Custom meta description` a kanonická adresa odkazuje na veřejnou URL příspěvku. Za titulkem může následovat nakonfigurovaná přípona.

Vykreslujte `@seo($post)` jednou na stránku. Pokud layout už obsahuje titulek nebo metatagy, nahraďte je, aby nevznikaly duplicity. Původ neočekávané hodnoty zjistíte podle [průvodce vyhodnocováním](/cs/guide/explain).

## 5. Přidejte mapu webu (volitelně) {#_5-add-a-sitemap-optional}

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

`/sitemap.xml` nyní poskytuje vygenerovaný index. Všechny možnosti popisuje [průvodce registrem map webu](/cs/guide/sitemaps).

## Kam dál {#where-to-go-next}

- [Pořadí přednosti resolveru](/cs/concepts/resolver-precedence) — jak se vybírají hodnoty
- [Průvodce Blade](/cs/guide/blade) — všech sedm direktiv
- [Inertia a JSON](/cs/guide/inertia-json) — headless vykreslování
- [Graf strukturovaných dat](/cs/guide/schema) — propojená JSON-LD
- [Pole Filament](/cs/guide/filament) — administrační rozhraní ve dvou řádcích
