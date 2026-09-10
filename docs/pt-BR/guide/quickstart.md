---
description: "Instale o Rankbeam, adicione HasSEO a um modelo existente, salve metadados e confira as tags geradas no Blade."
---

# Início rápido {#quickstart}

Comece com uma aplicação Laravel 11, 12 ou 13 existente e um banco de dados funcionando. Você precisa de PHP 8.2 ou superior, ou 8.3 para Laravel 13. O núcleo é gratuito sob licença MIT; não exige conta nem licença Pro.

## Instalar {#install}

Execute os comandos no diretório da aplicação:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

O service provider é descoberto automaticamente. A migration cria as tabelas SEO, não os modelos de conteúdo da aplicação.

## Antes do exemplo {#before-the-example}

Os passos pressupõem um modelo `Post`, um post salvo e uma rota `posts.show` cuja view Blade recebe esse post como `$post`. Adapte os nomes à sua aplicação. O guia adiciona SEO a uma página existente; ele não cria o blog.

Defina `APP_URL` no `.env` com a origem pública do site. Para outras formas de renderização, veja [Inertia e JSON](/pt-BR/guide/inertia-json) ou [Livewire](/pt-BR/guide/livewire).

## 1. Adicionar o trait ao modelo {#_1-add-the-trait-to-a-model}

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

`getUrlForSEO()` informa ao resolvedor a URL canônica do modelo. Ela alimenta canonical, `og:url` e entradas do sitemap.

## 2. Gerar as tags do head {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` gera título, descrição, canonical, robots, Open Graph, Twitter Card e JSON-LD associado aos dados resolvidos. Sem valores explícitos, usa os atributos do modelo e os padrões configurados; veja a [prioridade do resolvedor](/pt-BR/concepts/resolver-precedence).

## 3. Salvar valores explícitos {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Valores explícitos têm prioridade sobre todas as camadas de fallback. Para metadados traduzidos, informe o idioma: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Usando seeders
O `DatabaseSeeder` padrão do Laravel usa `WithoutModelEvents`, que também desativa o hook de criação automática de `HasSEO`. Remova essa trait ou chame `saveSEO()` explicitamente nos seeders.
:::

## 4. Conferir o resultado {#_4-verify-the-result}

Abra a página pública do post e escolha **Exibir código-fonte da página**. No `<head>`, confira se o título contém `Custom SEO Title`, se a descrição é `Custom meta description` e se o canonical aponta para a URL pública do post. O sufixo configurado pode aparecer após o título.

Use `@seo($post)` uma única vez por página. Substitua tags de título e metadados que o layout já produza para evitar duplicatas. Se um valor for inesperado, consulte o [guia de explicação](/pt-BR/guide/explain) para descobrir sua origem.

## 5. Adicionar um sitemap, se necessário {#_5-add-a-sitemap-optional}

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

`/sitemap.xml` agora serve o índice gerado. O [guia de sitemaps](/pt-BR/guide/sitemaps) descreve todas as opções.

## Próximos passos {#where-to-go-next}

- [Prioridade do resolvedor](/pt-BR/concepts/resolver-precedence): como os valores são escolhidos.
- [Blade](/pt-BR/guide/blade): as sete diretivas.
- [Inertia e JSON](/pt-BR/guide/inertia-json): saída sem Blade.
- [Grafo de esquemas](/pt-BR/guide/schema): JSON-LD conectado.
- [Campos Filament](/pt-BR/guide/filament): interface administrativa.
