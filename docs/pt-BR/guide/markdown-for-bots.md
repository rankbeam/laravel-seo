---
description: "Ofereça uma representação Markdown por negociação de conteúdo, mantendo o HTML normal para visitantes. Função gratuita do núcleo, desativada por padrão."
---

# Markdown para bots {#markdown-for-bots}

O HTML de uma aplicação envolve o conteúdo em navegação, scripts e marcação de layout. Alguns rastreadores de IA e mecanismos de resposta aceitam uma representação mais simples. Esta função oferece **Markdown** aos clientes que o solicitam por negociação de conteúdo, enquanto visitantes comuns continuam recebendo o HTML original. É uma opção de compatibilidade, sem promessa sobre como cada cliente interpreta ou utiliza o resultado.

Ela complementa o [controle de rastreadores de IA](/pt-BR/guide/ai-crawlers): o controle define a política de acesso; esta função determina o conteúdo servido durante a requisição.

É gratuita, faz parte do núcleo e está **desativada por padrão**.

## Como funciona {#how-it-works}

Ao ativá-la, um middleware de negociação de conteúdo é registrado. Depois que a resposta normal é produzida, ele a substitui por Markdown **somente quando as duas condições são atendidas**:

1. **A requisição solicita Markdown**, por `Accept: text/markdown`, `?format=md` ou, após ativação específica, pelo user-agent de um rastreador de IA conhecido.
2. **Uma fonte de Markdown é resolvida para a rota.**

Caso contrário, a resposta permanece inalterada. Navegadores comuns continuam recebendo HTML; apenas uma resposta **HTML bem-sucedida** pode ser substituída, nunca JSON, redirecionamento ou download.

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## De onde vem o Markdown {#where-the-markdown-comes-from}

O middleware tenta primeiro uma **fonte registrada para a rota** e depois os modelos vinculados à rota. Para cada modelo, um método explícito `toSeoMarkdown()` tem prioridade sobre a alternativa gerada. Se esse método retorna nulo ou vazio, desativa a alternativa para aquele modelo.

### 1. Markdown do próprio modelo {#_1-a-model-s-own-markdown}

Quando nenhuma fonte registrada retorna conteúdo, um modelo vinculado à rota com `toSeoMarkdown()` controla sua saída. Implemente o contrato `ProvidesSeoMarkdown` ou apenas adicione o método:

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

### 2. Fonte registrada para a rota {#_2-a-registered-route-source}

Para rotas sem modelo ou para substituir a saída dele, registre uma fonte pelo nome da rota:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. Alternativa gerada pelo pacote {#_3-the-built-fallback}

Quando um modelo `HasSEO` vinculado à rota não tem `toSeoMarkdown()`, o middleware monta um documento básico com o **título** resolvido como H1, a **descrição** e **`getContentForSEO()`**:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning O conteúdo é servido como recebido
A alternativa emite `getContentForSEO()` literalmente. Se o conteúdo é HTML, implemente `toSeoMarkdown()` para controlar a conversão. Para desativar totalmente a alternativa, use `seo.markdown_for_bots.build_from_content = false`.
:::

## Configuração {#configuration}

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

Mantenha `serve_to_known_bots` desativado para negociar apenas por `Accept` ou `?format` explícitos. Ative-o para também fornecer Markdown a GPTBot, ClaudeBot, PerplexityBot e outros identificados pelo [catálogo de rastreadores](/pt-BR/guide/ai-crawlers), mesmo sem uma solicitação explícita de Markdown.
