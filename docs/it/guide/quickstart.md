---
description: "Installa Rankbeam, aggiungi HasSEO a un modello esistente, salva i metadati e verifica i tag generati in Blade."
---

# Guida rapida

Parti da un'applicazione Laravel 11, 12 o 13 con un database funzionante. Serve PHP 8.2+ (8.3+ per Laravel 13). Il core è gratuito, con licenza MIT: non richiede account né licenza Pro.

## Installazione {#install}

Esegui questi comandi dalla directory dell'applicazione:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Il service provider viene registrato automaticamente. La migrazione crea le tabelle SEO, ma non i modelli dei contenuti della tua applicazione.

## Prima dell'esempio {#before-the-example}

Gli esempi presuppongono un modello `Post`, un articolo già salvato e una rotta `posts.show` la cui vista Blade riceve l'articolo come `$post`. Adatta i nomi alla tua app. Aggiungeremo i metadati a questa pagina, senza costruire un blog.

Imposta `APP_URL` in `.env` sull'origine pubblica del sito. Per altri sistemi di rendering, consulta le guide [Inertia e JSON (EN)](/it/guide/inertia-json) o [Livewire (EN)](/it/guide/livewire).

## 1. Aggiungi il trait al modello {#_1-add-the-trait-to-a-model}

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

`getUrlForSEO()` indica al resolver l'URL canonico del modello. Lo stesso metodo alimenta canonical, `og:url` e voci della sitemap.

## 2. Genera i tag nel head {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` genera titolo, meta description, canonical, robots, Open Graph, Twitter Card e l'eventuale JSON-LD associato ai dati risolti. Se non hai ancora salvato valori espliciti, usa gli attributi del modello e i valori predefiniti configurati. La [priorità del resolver](/it/concepts/resolver-precedence) descrive l'ordine completo.

## 3. Imposta valori espliciti {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

I valori espliciti hanno la precedenza su ogni fallback. Per metadati tradotti, passa anche la lingua: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Popoli il database con un seeder?
Il `DatabaseSeeder` predefinito di Laravel usa `WithoutModelEvents`, che disabilita anche l'hook di creazione automatica di `HasSEO`. Rimuovi quel trait oppure chiama `saveSEO()` esplicitamente nei seeder.
:::

## 4. Verifica il risultato {#_4-verify-the-result}

Apri la pagina pubblica dell'articolo e scegli **Visualizza sorgente pagina**. Nel `<head>`, verifica che il titolo contenga `Custom SEO Title`, la descrizione sia `Custom meta description` e il canonical punti all'URL pubblico dell'articolo. Al titolo può seguire il suffisso configurato.

Inserisci `@seo($post)` una sola volta per pagina. Se il layout genera già titolo o metatag, sostituiscili per evitare duplicati. Se un valore non è quello previsto, usa la [guida alla risoluzione (EN)](/it/guide/explain) per identificarne la sorgente.

## 5. Aggiungi una sitemap, se serve {#_5-add-a-sitemap-optional}

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

`/sitemap.xml` serve ora l'indice generato. Le opzioni complete sono nella [guida alle sitemap](/it/guide/sitemaps).

## Prossimi passi {#where-to-go-next}

- [Priorità del resolver](/it/concepts/resolver-precedence) — come vengono scelti i valori
- [Blade (EN)](/it/guide/blade) — tutte e sette le direttive
- [Inertia e JSON (EN)](/it/guide/inertia-json) — rendering headless
- [Grafo schema (EN)](/it/guide/schema) — JSON-LD collegato
- [Campi Filament](/it/guide/filament) — interfaccia di amministrazione
