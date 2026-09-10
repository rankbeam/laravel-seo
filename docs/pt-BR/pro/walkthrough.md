---
description: "Acompanhe uma varredura real de Pro: identifique uma descrição ausente, salve a correção no Filament, execute novamente e baixe o PDF de exemplo."
---

# Da varredura à correção verificada {#from-a-scan-to-a-verified-fix}

Uma varredura encontrou uma descrição ausente em um artigo de demonstração. Adicionamos a descrição no Filament, executamos outra varredura e geramos um relatório mostrando a correção.

As capturas originais, em inglês, são de uma demonstração Merchant local executada em 9 de setembro de 2026. O conteúdo é composto por dados de exemplo; as duas varreduras e o relatório foram gerados para este guia. Nenhuma tendência histórica foi preenchida antecipadamente. A aplicação usa Laravel 12 e Filament 4, com o núcleo Rankbeam, o editor gratuito e Pro.

**[Baixar o relatório gerado, original em inglês (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf)**

## Verificar as páginas registradas {#scan-the-registered-pages}

Depois de [instalar Pro](/pt-BR/pro/installation) e registrar os alvos, execute:

```bash
php artisan seo-pro:scan --sync
```

A demonstração registra 18 registros de conteúdo e três rotas. A primeira varredura concluiu os 21 alvos sem falhas e encontrou 20 ocorrências: seis avisos e 14 notas.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="Captura original em inglês da primeira varredura: 21 alvos, 20 ocorrências, seis avisos e 14 notas." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*As capturas têm resolução 2×. Abra uma imagem para examiná-la no tamanho completo.*

## Examinar uma ocorrência {#inspect-one-issue}

Em **SEO Dashboard**, abra **Page issues** ao lado da linha afetada. No artigo «Behind the Scenes: Our Product Photography», a ocorrência identifica `description` ausente, a URL e a varredura que a detectou.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Diálogo original em inglês identificando Post 5, sua URL e o campo de descrição ausente." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Salvar a descrição {#save-the-description}

Abra o artigo em **Posts**, preencha **SEO description** e salve. O [editor gratuito de Filament](/pt-BR/guide/filament) mostra o texto na prévia de busca e identifica sua origem como **Manual**. Neste exemplo, a descrição tem 142 caracteres e o título continua vindo do conteúdo do artigo.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="Descrição SEO salva, no original em inglês, com contador de 142 caracteres." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="Prévia original usando a descrição digitada e mostrando a origem Manual." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Salvar o campo e verificar a correção são etapas separadas. A pontuação só é atualizada na próxima varredura. Sem Filament, salve o mesmo valor pelo método `saveSEO()` do modelo.

## Executar novamente e conferir a mudança {#rescan-and-check-what-changed}

Repita o comando:

```bash
php artisan seo-pro:scan --sync
```

O painel identifica essa ocorrência específica como **Fixed**, resolvida. As outras 19 continuam abertas.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Comparação original em inglês: zero ocorrências novas, zero reaberturas, uma resolvida e 19 ainda abertas." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Verificação | Antes | Depois |
|---|---|---|
| Alvos concluídos | 21 | 21 |
| Ocorrências abertas | 20 | 19 |
| Avisos | 6 | 5 |
| Notas | 14 | 14 |
| Pontuação média de SEO técnico | 92 | 93 |

A [pontuação](/pt-BR/pro/scoring) representa as verificações técnicas do Rankbeam. Não mede tráfego, posição de busca ou presença em respostas de IA. Aprovar a verificação de descrição também não garante que o buscador mostre essa descrição.

## Gerar o relatório {#generate-the-report}

Nesta demonstração, geramos um relatório de referência **antes** de editar o artigo e outro após a nova varredura:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

O segundo PDF mostra **uma ocorrência resolvida**, **zero novas** e **19 abertas**. Sua tendência contém apenas as duas varreduras acima. Search Console e o registro de bots estavam desativados, então essas seções informam que os dados não estão disponíveis.

[![Primeira página do relatório original em inglês: pontuação 93, uma ocorrência resolvida e 19 abertas.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

O primeiro relatório estabelece a referência da comparação. Gerar apenas um relatório depois de corrigir a página não permite mostrar uma mudança em relação a um relatório anterior. Use `--no-store` para uma prévia que não deve avançar essa referência.

O exemplo usa o renderizador Browsershot. Consulte [relatórios com sua marca](/pt-BR/pro/reports) para requisitos, personalização e envio agendado.

## Executar na sua aplicação {#run-it-on-your-own-app}

Comece pela [instalação de Pro](/pt-BR/pro/installation) e verifique uma página cuja saída você possa conferir. Pro também funciona [sem Filament](/pt-BR/pro/headless). Para experimentar primeiro o renderizador gratuito, use a [demonstração Docker](/pt-BR/guide/demo).
