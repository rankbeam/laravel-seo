---
description: "Instale laravel-seo-pro para adicionar varreduras em fila, acompanhamento de ocorrências, redirecionamentos e monitoramento de 404 ao Laravel 11–13. Filament é opcional."
---

# Instalar Pro {#installing-pro}

`rankbeam/laravel-seo-pro` acrescenta varreduras do site em fila, acompanhamento de ocorrências, gerenciamento de redirecionamentos e monitoramento de 404 ao núcleo. O mecanismo funciona em **qualquer aplicação Laravel 11–13**, com Blade, Inertia ou apenas API. Filament é uma camada de interface opcional: com ele, você recebe painel SEO, redirecionamentos e monitor de 404 como páginas administrativas; sem ele, gerencia tudo por [comandos artisan](/pt-BR/pro/headless).

## Requisitos {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13) |
| Laravel | 11, 12 ou 13 |
| `rankbeam/laravel-seo` | ^3.20, instalado automaticamente por Pro 2.40 ou superior |
| `filament/filament` | **Opcional**, 4.x ou 5.x, apenas para a interface administrativa |
| `rankbeam/laravel-seo-filament` | **Opcional**, ^1.11 ao usar o editor SEO com Pro 2.36 ou superior |

Comece com uma aplicação Laravel existente e um banco de dados configurado. Complete primeiro o [início rápido do núcleo](/pt-BR/guide/quickstart), para que as tabelas existam e um modelo gere metadados. Uma licença Pro fornece as credenciais Composer usadas abaixo.

Para ver o resultado, consulte o [passo a passo de varredura, correção e relatório](/pt-BR/pro/walkthrough).

## Instalar o pacote {#install-the-package}

Pro é distribuído por um repositório Composer privado vinculado à licença. Adicione o repositório uma vez e instale o pacote. O Composer solicitará o e-mail da licença como usuário e a chave como senha:

O Lemon Squeezy processa o pagamento como merchant of record. Após o pagamento, a página privada do recibo fornece a chave de download e as instruções do Composer. Use o e-mail da compra como nome de usuário. A Rankbeam hospeda o repositório; você não precisa de uma conta no Anystack. Mantenha privados o link do recibo e o `auth.json`. Um reembolso integral revoga downloads e atualizações futuros sem interromper um aplicativo instalado.

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details Autenticação Composer sem interação
Para CI ou ambientes não interativos, configure as credenciais antecipadamente:

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

Depois execute o instalador:

```bash
php artisan seo-pro:install
```

O instalador publica `config/seo-pro.php` e as migrações de Pro, executa `migrate` e mostra os próximos passos. As tabelas do núcleo e de Pro devem estar presentes no banco da aplicação.

::: details Instalação manual e opções do instalador
As migrações de Pro são publicadas na aplicação; o pacote não as carrega automaticamente. Os passos manuais equivalentes são:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

O instalador pode ser executado novamente. `--no-migrate` publica os arquivos sem aplicar migrações. Use `--force` apenas quando quiser sobrescrever arquivos publicados, incluindo sua configuração.
:::

## Registrar alvos de varredura {#register-scan-targets}

Em um service provider, indique classes de modelo, rotas nomeadas ou todas as entradas do [registro de sitemaps](/pt-BR/guide/sitemaps):

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

Substitua `Post` pelo seu modelo com `HasSEO`. Ele precisa ter pelo menos um registro para aparecer nos resultados. Os alvos de rota devem indicar rotas existentes; omita esse registro se quiser verificar somente modelos.

## Verificar a instalação {#verify-your-install}

Execute o diagnóstico de configuração:

```bash
php artisan seo:doctor
```

Confirme que as tabelas do núcleo e de Pro existem, a URL da aplicação está correta e os alvos estão listados. Siga as correções indicadas. Um aviso sobre fila `sync` é esperado ao testar os comandos diretos abaixo; configure um worker antes de agendar varreduras em produção.

::: details Exemplo de saída do diagnóstico
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor` verifica configuração e histórico recente sem chamadas de rede nem exibição de segredos. Não comprova que um cron ou worker externo esteja executando. Falhas críticas retornam código de saída diferente de zero; avisos não. Use `--json` para saída legível por máquina.
:::

## Executar a primeira varredura {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

O primeiro comando conclui a varredura no processo atual, sem precisar de worker. O segundo mostra a execução mais recente e seus resultados. Confira se a execução terminou e processou os alvos registrados. Investigue alvos com falha antes de considerar a verificação concluída.

Corrija um campo apontado, salve e execute novamente. O [passo a passo](/pt-BR/pro/walkthrough) mostra uma descrição ausente e o relatório da alteração. A [pontuação técnica](/pt-BR/pro/scoring) é um diagnóstico, não uma previsão de posições nos buscadores.

## Uso sem painel {#path-b-headless}

O mecanismo já pode ser usado sem interface administrativa. Os [comandos artisan](/pt-BR/pro/headless) permitem executar varreduras, consultar ocorrências, criar redirecionamentos e gerar relatórios. Os middlewares de redirecionamento e 404 são registrados automaticamente por padrão; seus ajustes ficam em `config/seo-pro.php`.

Para tarefas agendadas, siga a [configuração de produção](/pt-BR/pro/production): filas, workers, agendador e retenção.

## Adicionar um painel Filament, opcional {#path-a-with-a-filament-panel}

Em um painel Filament 4 ou 5 existente, registre o plugin Pro abaixo. Se a aplicação ainda não tem painel, instale os pacotes de interface e crie um primeiro:

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

Isso adiciona o **painel SEO**, com ação para verificar tudo, progresso em tempo real, lista de ocorrências e nova varredura por página, além do **gerenciador de redirecionamentos** e do **monitor de 404** com a ação de criar redirecionamento. `rankbeam/laravel-seo-filament` acrescenta a [seção de campos SEO](/pt-BR/guide/filament) aos formulários dos recursos.

## Solução de problemas {#troubleshooting}

| Resultado | Próximo passo |
|---|---|
| Composer rejeita as credenciais | Confira o e-mail e a chave da licença para `blog.rankbeam.dev`. Mantenha credenciais fora do controle de versão. |
| Doctor aponta tabelas ausentes | Complete o início rápido do núcleo e execute `seo-pro:install` e `migrate` no mesmo banco da aplicação. |
| A varredura não processa alvos | Confira o registro no provider e se o modelo contém registros. |
| Uma varredura em fila permanece pendente | Inicie o worker configurado ou use `--sync` para verificar no processo atual. |
| Um alvo falha | Confira os detalhes da execução, os nomes das rotas e a URL da aplicação antes de repetir. |
| O painel não aparece | Registre `SeoProPlugin` no painel utilizado e confira as regras de acesso. |

Consulte a [configuração de produção](/pt-BR/pro/production) para recuperação de workers e operação contínua.

## Licença e reembolsos {#license}

A licença de fundador custa 179 € em pagamento único e cobre até cinco projetos em produção, incluindo projetos de clientes, com atualizações vitalícias. As cópias de desenvolvimento e staging desses projetos não contam separadamente. Estão incluídos ajuda com instalação e migração, uma chamada de instalação de 60 minutos e o kit de lançamento anunciado. Você pode pedir um reembolso integral incondicional em até 30 dias pelo recibo ou pelo e-mail valentinogoxhaj@gmail.com. Após o reembolso, deve parar de usar o Pro. Pode modificá-lo para os projetos licenciados, mas não publicar o código-fonte nem revendê-lo como pacote independente ou starter kit. O pacote contém os termos completos da licença.

Você pode usar o Pro em até cinco projetos em produção, incluindo projetos de clientes. As cópias de desenvolvimento, staging e teste desses projetos não contam separadamente. As atualizações vitalícias incluem futuras versões do Pro, mas não trabalho pessoal contínuo na aplicação.

Estão incluídas uma chamada de instalação e configuração de 60 minutos e a migração de metadados para um único projeto inicial. A migração cobre as fontes compatíveis; combinamos o escopo antes de começar. Alterações personalizadas na aplicação recebem um orçamento separado. Para o mesmo projeto, revisamos e configuramos llms.txt, as regras de rastreadores de IA em robots.txt e as respostas markdown para bots, usando funções do Core gratuito. Escreva para hello@rankbeam.dev para organizar a ajuda incluída.

Ao seu pedido se aplica a oferta apresentada no momento da compra.
