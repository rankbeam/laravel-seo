---
description: "Gere imagens Open Graph por página com Blade, Browsershot e Chrome: geração antecipada, cache, templates, fontes e limites de operação."
---

# Gerar imagens OG {#generated-og-images}

Desde Core 3.20, o renderizador Chrome desativa JavaScript e bloqueia requisições de recursos HTTP(S), FTP e WebSocket. Templates próprios precisam usar HTML/CSS estático e recursos incorporados, como os incluídos.

Sem imagem própria, as páginas compartilham `default_og_image`. Esta função gera **um cartão Open Graph / Twitter de 1200×630 pixels por página**, usando uma view Blade renderizada por navegador headless via [spatie/browsershot](https://github.com/spatie/browsershot). O navegador cuida de quebras de linha, acentos, fontes de fallback e títulos longos. Escritas não latinas precisam das fontes corretas no host.

A função é gratuita no núcleo e vem **desativada por padrão**. Desativada, mantém `default_og_image` e não exige a dependência opcional de navegador.

::: info Geração antecipada estática
Um comando Artisan gera os cartões antes das visitas. A página só referencia um arquivo que já existe. A requisição do visitante não inicia um navegador. Não há endpoint de renderização sob demanda; veja [limitações](#caveats).
:::

## Requisitos {#requirements}

Instale o driver opcional na aplicação:

```bash
composer require spatie/browsershot
```

Você também precisa de:

- **Node.js** no host.
- **Puppeteer** na **raiz da aplicação**, para o Node resolver o módulo:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium**. O Puppeteer baixa um navegador por padrão. Em produção, você pode apontar para um Chrome instalado com [`chrome_path`](#configuration).

::: warning Puppeteer na raiz em Windows
`npm_module_path` chama `setNodeModulePath()` do Browsershot, que gera um prefixo POSIX `NODE_PATH=…` sem efeito no Windows. Nesse sistema, o Node encontra módulos subindo pelos diretórios da aplicação. Instale o Puppeteer na raiz; veja [limitações](#caveats).
:::

## Ativar {#enabling}

Publique a configuração, se necessário, com `php artisan vendor:publish --tag=seo-config` e ative:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Depois, gere os cartões. Nada é renderizado antes disso:

```bash
php artisan seo:og-images
```

## Como a imagem é resolvida {#how-resolution-works}

Um cartão gerado não substitui a imagem escolhida para a página. O resolvedor preenche `og:image` apenas quando o valor está vazio ou ainda é `default_og_image`. Uma imagem própria de `getSEOImage()`, de uma linha `seo_meta` ou de um campo de conteúdo tem prioridade.

A consulta calcula o caminho de armazenamento e retorna a URL pública **só quando o arquivo já existe** no disco configurado. Ela não renderiza:

- Uma requisição web nunca inicia o navegador; sem cartão, mantém a imagem estática.
- A página não referencia um cartão que ainda precisa ser gerado.

Após alterar conteúdo, execute [`seo:og-images`](#the-seo-og-images-command) no deploy ou em um agendamento para criar o novo arquivo.

## O comando `seo:og-images` {#the-seo-og-images-command}

Gera os cartões consultados pelo resolvedor:

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*`: uma ou mais classes; repetível. Sem argumento, usa `seo.og_image.models`, com fallback para os [modelos do sitemap](/pt-BR/guide/sitemaps) em `seo.sitemap.models`, como `seo:llms-txt`.
- `--force`: refaz cartões existentes, por exemplo após editar um template sem aumentar `cache_version`.
- `--prune`: depois da geração, remove cartões órfãos no caminho configurado. Só apaga nomes no formato dos hashes gerados, nunca outros arquivos. É ignorado com `--model`, pois uma lista parcial não cobriria os cartões de outros modelos.

Os modelos precisam usar `HasSEO`. Registros sem título são ignorados. A saída conta `generated`, `skipped`, `failed` e, com `--prune`, `pruned`.

### Agendamento {#scheduling}

Atualize cartões e remova os que ficam órfãos após mudanças de título:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Invalidação de cache {#the-invalidation-model}

O nome do arquivo é um hash das entradas que afetam o resultado: título, nome do site, nome do template, driver, dimensões, cores do gradiente, `cache_version` e versão instalada do pacote.

- **Mudar o título gera outro hash e arquivo.** O anterior fica órfão. A página volta ao padrão estático até a nova geração; `--prune` pode remover o cartão antigo.
- **Mudar `cache_version` ou atualizar o pacote renova os hashes.** Aumente `cache_version` após editar o conteúdo de um template para invalidar todos os cartões. A versão do pacote entra automaticamente para evitar cartões antigos após mudanças em templates incluídos.

## Templates incluídos {#bundled-templates}

Os três usam o mesmo gradiente e tamanho inicial de 1200×630:

| Template | Uso | Conteúdo |
|---|---|---|
| `seo::og.default` | Geral | Título e nome do site |
| `seo::og.article` | Posts e notícias | Seção, título, autor e data |
| `seo::og.product` | Produtos e anúncios | Marca, categoria, título e descrição |

Escolha um padrão global em `seo.og_image.template` ou mapeie por classe de modelo:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Um modelo pode definir `getOgImageTemplate(): ?string`. Retorne o nome de uma view ou `null` para usar o mapa ou padrão. Prioridade: hook do modelo, mapa `templates`, `template` global.

## Personalizar o template {#customizing-the-template}

O cartão é uma view Blade, por padrão `seo::og.default`, convertida em HTML autossuficiente. A fonte incluída usa uma URI de dados e dispensa requisição de rede.

**Publicar e editar a view incluída:**

```bash
php artisan vendor:publish --tag=seo-views
```

Edite `resources/views/vendor/seo/og/default.blade.php`.

**Ou escolher uma view própria:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

Variáveis disponíveis:

| Variável | Tipo | Significado |
|---|---|---|
| `$title` | `string` | Título OG ou título da página. |
| `$siteName` | `?string` | `og:site_name` resolvido. |
| `$fontDataUri` | `string` | Fonte negrita incluída como URI `data:`; vazia se indisponível, com fallback sans-serif do navegador. |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Largura, por padrão `1200`. |
| `$height` | `int` | Altura, por padrão `630`. |
| `$locale` | `?string` | Idioma resolvido para `<html lang>`. |
| `$author` | `?string` | Autor para `seo::og.article`. |
| `$publishedDate` | `?string` | Data de publicação para `seo::og.article`: formato médio do ICU na localidade da página, quando disponível; caso contrário, Carbon traduz o mês na ordem `M j, Y`. Null quando não há data. |
| `$section` | `?string` | Seção ou categoria do artigo/produto. |
| `$description` | `?string` | Descrição OG ou da página para `seo::og.product`. |

::: info O nome do template faz parte do cache
Trocar nome de template ou cores invalida cartões. Editar uma view com o mesmo nome não invalida: aumente `cache_version` ou execute `--force`.
:::

## Configuração {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

A maioria dos valores escalares tem variável de ambiente correspondente, como `SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH` e `SEO_OG_IMAGE_NO_SANDBOX`. Consulte a lista no arquivo de configuração. Arrays como `templates`, `models`, `browsershot_args` e `font_stack` são editados diretamente nele.

O disco deve ser **público**, porque seu `url()` vira o valor de `og:image`. Com o disco `public`, execute `php artisan storage:link` uma vez para criar `public/storage`.

## Linux e o sandbox {#running-on-linux-the-sandbox}

Se o host restringe os mecanismos de isolamento do Chrome, o comando pode falhar com:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Uma causa possível são namespaces de usuário restritos no Ubuntu 23.10+. Confira o erro real de inicialização e a [solução de problemas do Puppeteer](https://pptr.dev/troubleshooting). Prefira configurar o host para manter o sandbox.

**1. Alternativa explícita: `--no-sandbox`.** Essa opção desativa o isolamento do navegador. Use apenas se o deploy aceitar deliberadamente essa perda de proteção:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

HTML estático e bloqueio de recursos remotos não substituem o sandbox. Execute o processo sem privilégios e isolado de outras aplicações e seus segredos.

**2. Manter o sandbox.** Deixe `no_sandbox` desativado. Se o AppArmor for a causa, adapte um perfil para o executável exato do Chrome conforme as [orientações do Chromium](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md). Exemplo:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Carregue com `sudo apparmor_parser -r /etc/apparmor.d/chrome-og` e verifique a inicialização com sandbox ativo.

::: tip Outros argumentos
Em contêineres com pouca memória compartilhada, o Chrome pode falhar durante o render. Adicione argumentos por `browsershot_args`:

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Drivers próprios {#custom-drivers}

`browsershot` é o único incluído, mas o renderizador usa o contrato `Rankbeam\Seo\Contracts\OgImageRenderer`. Registre um driver próprio, por exemplo canvas ou um serviço, e selecione em `seo.og_image.driver`:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

O driver só transforma uma string HTML autossuficiente em bytes PNG no tamanho solicitado. Ele não gerencia layout nem templates.

## Fontes e escritas não latinas {#fonts-and-non-latin-scripts}

Noto Sans Bold, incluída sob OFL, cobre **latim, cirílico e grego**. Chinês, japonês, coreano, tailandês, árabe, hebraico, devanagari e emoji dependem de fontes instaladas no host de `seo:og-images`. Uma fonte CJK pode superar 16 MB e não é incluída. O Chrome usa fallback por caractere quando as fontes adequadas existem.

Três mecanismos apoiam isso desde 3.15:

1. **Lista de famílias em cada template.** O body começa com `'OGBrand'`, segue com `seo.og_image.font_stack` e termina em `sans-serif`. O padrão inclui `Noto Sans`, quatro famílias `Noto Sans CJK`, `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`, `Noto Sans Devanagari` e `Noto Color Emoji`. Famílias ausentes são ignoradas. A família CJK da língua vem primeiro: `ja` → JP, `zh-Hans` → SC, `zh-Hant` / `zh-TW` / `zh-HK` → TC, `ko` → KR. Isso escolhe formas nacionais dos caracteres Han compartilhados. `<html lang>` carrega o idioma BCP47. A lista faz parte do cache; alterá-la renova cartões.

2. **Verificação prévia em `seo:og-images`.** O comando consulta fontconfig (`fc-list :lang=ja`, `th`, `ar`, …) para as escritas do título, nome do site e descrição, inclusive minoritárias em texto misto. Avisa uma vez por escrita e sugere um pacote:

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   Sem fontconfig, como no Windows, macOS ou contêiner mínimo, não tenta adivinhar a cobertura e fica silencioso. Uma fonte ausente não necessariamente interrompe o render: o Chrome pode desenhar caixas .notdef, motivo da verificação.

3. **Fixtures de glifos no teste real.** Com `SEO_OG_IMAGE_LIVE_TEST=1`, `tests/Feature/OgImage/BrowsershotSmokeTest.php` renderiza títulos em ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he e hi, junto a controles do mesmo tamanho com um ponto de código não atribuído. PNGs idênticos fazem o teste falhar com indicação da escrita e do pacote necessário. É uma amostra, não prova de todos os glifos: trechos latinos ou quebras diferentes podem distinguir imagens mesmo com caracteres faltando. Inspecione o render real e as fontes no host. FontProbe também é uma verificação preliminar, não certificação de cobertura completa. O núcleo não tem `seo:doctor`; use `seo:og-images` para essa checagem.

Em Debian/Ubuntu:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

Templates publicados antes de 3.15 continuam funcionando. Recebem `$fontFamily` e `$lang`, mas podem ignorá-las.

## Limitações {#caveats}

- **Somente geração antecipada.** Não há rota pública que renderize cartões sob demanda. Essa função não expõe esse tipo de endpoint que exigiria URL assinada ou proteção SSRF/DoS. Execute [`seo:og-images`](#the-seo-og-images-command) no deploy ou por agendamento.
- **`npm_module_path` não funciona no Windows.** O prefixo POSIX do Browsershot é ignorado. Instale o Puppeteer na raiz da aplicação. O ajuste funciona em Linux/macOS.
- **Escritas não latinas exigem fontes no host.** Só latim, cirílico e grego são incluídos. Confira [fontes](#fonts-and-non-latin-scripts), avisos e imagens reais.
- **Fallback em caso de falha.** Dependência ausente, falha do navegador ou timeout são relatados pelo comando. A página conserva `default_og_image`; um navegador quebrado não causa erro 500 na página.
