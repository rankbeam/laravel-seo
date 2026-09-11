---
description: "Installeer Rankbeam, voeg de HasSEO-trait toe aan een bestaand model, sla SEO-velden op en controleer de gerenderde tags in Blade."
---

# Snelstart {#quickstart}

Begin met een bestaande Laravel 11-, 12- of 13-applicatie en een werkende database.
Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5. De core is gratis onder de MIT-licentie;
je hebt geen account of Pro-licentie nodig.

## Installeren {#install}

Voer deze opdrachten uit vanuit de map van je applicatie:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

De serviceprovider wordt automatisch ontdekt. De migratie maakt de SEO-tabellen aan;
ze maakt geen contentmodellen voor je applicatie aan.

## Voordat je het voorbeeld gebruikt {#before-the-example}

De onderstaande stappen gaan ervan uit dat je al een `Post`-model, een opgeslagen bericht en een
`posts.show`-route hebt waarvan de Blade-view dat bericht ontvangt als `$post`. Pas deze
namen aan je app aan. Deze gids voegt SEO toe aan die pagina; hij bouwt geen blog.

Stel `APP_URL` in `.env` in op de openbare origin van je site. Gebruik voor andere
renderingstacks de [gids voor Inertia en JSON](/nl/guide/inertia-json) of de
[Livewire-gids](/nl/guide/livewire).

## 1. De trait aan een model toevoegen {#_1-add-the-trait-to-a-model}

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

`getUrlForSEO()` vertelt de resolver op welke canonieke URL het model staat —
de methode levert de basis voor canonieke URL's, `og:url` en sitemapvermeldingen.

## 2. De head renderen {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` genereert de titel, metabeschrijving, canonieke URL, robots-, Open Graph-
en Twitter Card-tags en eventuele JSON-LD die aan de uiteindelijke gegevens is gekoppeld. Zolang er
geen expliciete waarden zijn opgeslagen, komt alles uit berekende terugvalwaarden (de
eigen attributen van het bericht) en je ingestelde standaarden — zie de
[voorrangsvolgorde van de resolver](/nl/concepts/resolver-precedence).

## 3. Expliciete waarden instellen {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Expliciete waarden gaan voor op elke terugvallaag. Geef een locale mee voor vertaalde
metadata: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Modellen vullen met seeders?
De standaard-`DatabaseSeeder` van Laravel gebruikt de `WithoutModelEvents`-trait, die
de hook voor automatisch aanmaken van `HasSEO` stilzwijgend uitschakelt. Verwijder de trait of roep
`saveSEO()` expliciet aan in seeders.
:::

## 4. Het resultaat controleren {#_4-verify-the-result}

Open de openbare pagina van het bericht en gebruik **Paginabron weergeven**. Controleer in de `<head>`
of de titel `Custom SEO Title` bevat, de beschrijving
`Custom meta description` is en de canonieke URL naar de openbare URL van het bericht verwijst.
Het ingestelde titelachtervoegsel kan na de titel staan.

Render `@seo($post)` één keer per pagina. Als de layout al een titel of metatags
genereert, vervang die tags dan om duplicaten te voorkomen. Als een waarde niet klopt, gebruik dan de
[uitleg van de waardebepaling](/nl/guide/explain) om te zien waar ze vandaan komt.

## 5. Een sitemap toevoegen (optioneel) {#_5-add-a-sitemap-optional}

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

`/sitemap.xml` levert nu de gegenereerde index. Alle opties staan in de
[gids voor het sitemapregister](/nl/guide/sitemaps).

## Verder lezen {#where-to-go-next}

- [Voorrangsvolgorde van de resolver](/nl/concepts/resolver-precedence) — hoe waarden worden gekozen
- [Blade-gids](/nl/guide/blade) — alle zeven directives
- [Inertia en JSON](/nl/guide/inertia-json) — headless rendering
- [Schemagraaf](/nl/guide/schema) — gekoppelde JSON-LD
- [Filament-velden](/nl/guide/filament) — een beheerinterface in twee regels
