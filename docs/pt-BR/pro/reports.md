---
description: "Relatório PDF com sua marca: pontuação, tendências, ocorrências novas e resolvidas, 404 recuperados, Search Console e bots de IA. Geração por comando e envio agendado opcional."
---

# Relatórios com sua marca {#white-label-reports}

Um **relatório PDF** de um site com pontuação geral, tendência de ocorrências, problemas **resolvidos e novos desde o relatório anterior**, 404 e links quebrados recuperados, mudanças em Search Console e atividade de bots de IA. Gere por comando e, opcionalmente, **envie por e-mail em um horário programado**. Agências podem usar logo, cor e identificação do cliente.

[Baixe um relatório de exemplo gerado em inglês (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf) ou siga o [passo a passo de varredura, correção e relatório](/pt-BR/pro/walkthrough). O exemplo usa conteúdo de demonstração Merchant e duas varreduras recentes: uma ocorrência resolvida, 19 abertas e nenhum dado de Search Console.

[![Primeira página do relatório original em inglês da demonstração Merchant.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## Conteúdo do relatório {#what-s-in-it}

- **Pontuação geral:** média das pontuações mais recentes por página, conforme os [critérios publicados](/pt-BR/pro/scoring), de A ≥ 90 a F, com a mudança desde o relatório anterior. Inclui uma **tendência da pontuação geral** das varreduras recentes. Cada varredura registra a pontuação do site na execução; o histórico começa na primeira execução após a atualização. Execuções anteriores sem pontuação são ignoradas.
- **Ocorrências por varredura:** histórico das execuções recentes concluídas. Uma redução deve ser interpretada junto das mudanças de escopo e verificações.
- **Resolvidas e novas:** quantidade de problemas corrigidos e surgidos desde o relatório anterior. Usa o [ciclo de vida de resolução e reabertura](/pt-BR/pro/scan-issues#issue-lifecycle) quando há um período completo de histórico; caso contrário, compara o snapshot do relatório anterior.
- **Recuperações:** links quebrados resolvidos, 404 **recuperados**, cujo próprio caminho voltou a responder 200 —consulte [`seo-pro:404-recheck`](/pt-BR/pro/production#scheduler)—, 404 **redirecionados** desde o último relatório e problemas ainda abertos. Uma recuperação na origem é contada separadamente de um redirecionamento.
- **Search Console:** principais consultas e páginas e as maiores mudanças de cliques. A seção é omitida de forma controlada quando a integração não está configurada.
- **Bots de IA:** requisições atribuídas por user-agent, sem verificação de identidade, totais acumulados e, quando os [registros diários](/pt-BR/pro/ai-bot-monitor#period-metrics-daily-buckets) cobrem o intervalo, **acessos no período e URLs distintos por bot**. Sem cobertura, usa a diferença entre totais dos snapshots.

## Desde o relatório anterior {#since-the-last-report}

A comparação usa **o relatório anterior**, não uma data arbitrária. Cada geração salva um snapshot leve em `seo_report_runs`, com pontuação, identidades de ocorrências abertas, linhas de Search Console e contadores de bots. O relatório seguinte compara o estado atual com esse registro.

O snapshot é a alternativa para sinais que não mantêm histórico próprio, como as pontuações por página, armazenadas apenas em sua versão mais recente. Outros sinais têm histórico e recebem prioridade: o [ciclo de vida das ocorrências](/pt-BR/pro/scan-issues#issue-lifecycle), as [métricas diárias de Search Console](/pt-BR/pro/search-console#historical-metrics) e os [registros diários dos bots](/pt-BR/pro/ai-bot-monitor#period-metrics-daily-buckets). No primeiro relatório após uma atualização, ou sem histórico suficiente, cada seção volta à diferença entre snapshots.

Duas consequências:

- **O primeiro relatório é a linha de base.** Mostra o estado atual. Contagens de resolvidas, novas, mudanças e «desde o último relatório» começam no segundo.
- **Você define a frequência.** Relatórios mensais comparam aproximadamente um mês; semanais, uma semana. Use `--no-store` para uma prévia que não deve alterar a referência.

## Gerar um relatório {#generate-a-report}

```bash
php artisan seo-pro:report
```

Sem opções, grava o PDF em `storage/app/seo-reports/`. Para escolher outro destino ou enviar por e-mail:

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### Opções {#options}

| Opção | Efeito |
| --- | --- |
| `--client=` | Substitui a identificação do cliente na indicação «preparado para» |
| `--agency=` | Substitui o nome da agência |
| `--accent=` | Substitui a cor de destaque em hexadecimal, como `#3D5AFE` |
| `--logo=` | Substitui o caminho da imagem do logo |
| `--email=` | Define um destinatário e envia o relatório; pode ser repetida |
| `--send` | Envia aos destinatários configurados |
| `--output=` | Grava o PDF no arquivo ou diretório indicado |
| `--no-store` | Não salva snapshot nem avança a referência da comparação |
| `--json` | Emite um resumo legível por máquina |

## Agendar o e-mail {#schedule-the-e-mail}

O relatório não agenda seu próprio envio. Defina a frequência em `routes/console.php` ou `app/Console/Kernel.php` da aplicação:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Configure os destinatários padrão uma vez, na configuração ou no `.env`:

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` usa esses destinatários. Opções `--email` explícitas os substituem.

## Identidade visual {#branding}

Os dados de marca não são segredos e ficam na configuração. Cada relatório os reutiliza; as opções do comando podem substituir os valores, inclusive quando uma instalação gera relatórios identificados para clientes diferentes.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Observações:

- **Logo:** caminho absoluto de um arquivo `PNG`, `JPG`, `GIF`, `WEBP` ou `SVG`. A aplicação lê o arquivo e o incorpora como URI de dados no PDF, sem exigir que o renderizador busque essa imagem pela rede. PNG ou JPG são as opções mais seguras.
- **Cor de destaque:** validada como hexadecimal. Um valor inválido usa o padrão. A entrada só é usada como cor, nunca como CSS bruto.
- **Nome da agência:** usa `config('app.name')` por padrão.

O bloco completo `reports` em `config/seo-pro.php` inclui `paper`, padrão `a4`, `include_gsc` e os limites de execuções de tendência, linhas GSC e bots.

## Um site por instalação {#one-site-per-install}

Pro verifica a aplicação em que está instalado, e o relatório descreve **essa instalação**. Uma agência com vários sites gera um relatório por instalação e identifica cada cliente por `--client` ou pela marca. Não há um modelo multitenant de sites.

## Como o PDF é gerado {#how-it-s-built}

O renderizador padrão é **dompdf**, em PHP, sem Node ou Chromium. Um relatório pode ser gerado no worker ou cron sem esses binários, mantendo Pro independente de painel. Buscas remotas são desativadas e a imagem do logo é incorporada; campos renderizados não iniciam downloads de recursos.

### Relatórios em outras escritas com Browsershot {#reports-in-every-script-browsershot-renderer}

Desde Core 3.20 e Pro 2.40, os renderizadores Chrome desativam JavaScript e bloqueiam recursos por HTTP(S), FTP e WebSocket. Templates publicados devem usar HTML/CSS estático e recursos incorporados. Essas restrições tratam dos recursos da página; Chrome ainda precisa de host e sandbox configurados corretamente. O renderizador PDF registra uma indicação de instalação de fontes quando Fontconfig identifica uma escrita sem cobertura, inclusive minoritária em texto misto. Uma fonte ausente não impede a criação do PDF: confira o resultado antes de enviá-lo.

dompdf usa a fonte incorporada DejaVu Sans, com cobertura de latim, cirílico e grego. Outras escritas, como japonês, tailandês e árabe, podem aparecer como quadrados de glifos ausentes. Desde Pro 2.34, você pode usar **Chrome sem interface gráfica** por `spatie/browsershot`, a mesma dependência das imagens OG do núcleo:

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chrome usa as fontes instaladas no servidor. O template segue a lista por escrita do núcleo: Noto Sans, a família Noto Sans CJK correspondente ao idioma à frente, tailandês, árabe, hebraico, devanágari, emoji colorido e DejaVu Sans como referência latina. Instale as famílias necessárias; em Debian/Ubuntu, `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`, como nas [imagens OG](/pt-BR/guide/multilingual#og-images-in-every-script). `seo:og-images` avisa quando falta uma família para a escrita de uma página; o diagnóstico também ajuda a configurar relatórios. Os dois motores usam o mesmo template Blade, dados e snapshot; muda o renderizador. `ReportGenerator::renderer()` informa qual está associado.

### Datas e números no formato do leitor {#dates-and-numbers-in-the-reader-s-locale}

O relatório captura `seo-pro.reports.locale` ao ser gerado; nulo usa o idioma da aplicação. O idioma de tradução resolvido controla rótulos de PDF e e-mail, assunto padrão, fontes e HTML `lang`. Locales regionais sem arquivo próprio usam o idioma base e depois inglês. Chinês simplificado `zh_CN` e tradicional `zh_TW` permanecem distintos.

Com `ext-intl`, datas e números seguem o locale solicitado por ICU. Use `seo-pro.reports.format_locale` para escolher outro formato regional explicitamente: `locale=it` com `format_locale=en_US` gera rótulos italianos e formatação dos EUA. Sem `ext-intl`, preserva o formato alternativo de datas em inglês e números agrupados por vírgulas.

E-mails em fila mantêm o idioma, a formatação e o assunto capturados, mesmo que a configuração do worker mude. Escolha o idioma antes de gerar o PDF: alterar depois o locale do mailable não traduz o anexo. Payloads antigos, anteriores a Pro 2.39, usam a configuração do worker porque não capturaram esses ajustes. Assuntos personalizados, marca e mensagens de ocorrências armazenadas continuam sendo dados de origem.

O idioma da CLI é independente. `php artisan seo-pro:report --display-locale=it` traduz o resumo do comando; a configuração do relatório escolhe o idioma do PDF e e-mail do cliente. A CLI usa inglês por padrão, configurável por `SEO_PRO_CLI_LOCALE`. Chaves JSON e códigos permanecem estáveis; rótulos para pessoas podem mudar. Publique `seo-pro-lang` para personalizar mensagens em `lang/vendor/seo-pro/{locale}/seo-pro.php`.

Em código, resolva `ReportGenerator` pelo contêiner:

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```
