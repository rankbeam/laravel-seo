---
description: "Checklist on-page com estados de aprovação, aviso e falha: verifique a palavra-chave no título, URL, introdução e descrição, além de tamanho, imagens e legibilidade."
---

# Checklist on-page: palavra-chave, aprovação, aviso e falha {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

O checklist on-page oferece o fluxo editorial familiar a quem usa RankMath ou Yoast: escolha uma palavra-chave de foco e receba verificações com estados visuais sobre seu uso no título, URL, primeiro parágrafo e meta description. A análise também cobre tamanho do conteúdo, imagens, links internos e **legibilidade**.

Ele é executado **durante a requisição**, sem fila nem rede, usando o modelo, o [resolvedor](/pt-BR/concepts/resolver-precedence) e o texto da página. O resultado **não é uma pontuação numérica**.

::: tip Checklist e pontuação são separados
O checklist usa apenas os estados **pass / warn / fail** e é independente da [pontuação de SEO Pro](/pt-BR/pro/scoring). Seus identificadores não pertencem aos critérios numéricos e não alteram a pontuação. Densidade de palavras-chave e legibilidade são orientações **consultivas**, conforme explicado abaixo.
:::

## O que ele verifica {#what-it-checks}

| Verificação | Grupo | O que procura |
|---|---|---|
| `keyword_in_title` | keyword | Palavra-chave de foco no título de SEO. |
| `keyword_in_description` | keyword | Palavra-chave de foco na meta description. |
| `keyword_in_url` | keyword | Palavra-chave de foco no slug da URL. |
| `keyword_in_first_paragraph` | keyword | Palavra-chave de foco no primeiro parágrafo. |
| `keyword_density` | keyword | **Consultiva.** Informa a densidade para revisão da naturalidade, sem percentual ideal. |
| `title_length` | meta | Título dentro da mesma faixa usada pelo editor e pela varredura: 30–60 para escrita latina e cerca de 15–30 para CJK, conforme a [política de tamanho](/pt-BR/guide/multilingual#title-and-description-budgets-per-script) do Core, usada desde o Pro 2.33. |
| `description_length` | meta | Descrição dentro da mesma faixa: 70–160 para escrita latina e cerca de 35–80 para CJK. |
| `content_length` | content | Quantidade suficiente de texto, conforme as faixas de palavras configuradas. |
| `readability` | content | **Consultiva.** Estimativa de legibilidade por fórmula em dez idiomas, fallback LIX ou heurística identificada e sem pontuação para japonês, chinês e coreano. |
| `has_image` | media | Pelo menos uma imagem no conteúdo. |
| `internal_links` | links | Links para páginas internas relacionadas. |

As verificações de palavra-chave são **ignoradas**, sem aprovação nem falha, quando nenhuma palavra-chave de foco foi definida. O checklist pede que você adicione uma pelo [campo de palavra-chave de foco](/pt-BR/guide/filament) ou por `saveSEO(['focus_keywords' => …])`.

### Correspondência de palavras-chave {#keyword-matching}

A comparação aplica **normalização de caixa e stemming**, isto é, redução algorítmica das terminações. Assim, `espresso grinder` pode corresponder a `espresso grinders`. Com o locale de análise, o `CaseFolder` do Core também faz `İstanbul` corresponder a `istanbul` em turco, `ΟΔΟΣ` a `οδος` em grego e `Straße` a `STRASSE` em alemão. Informe o locale em `SeoPro::checklistFor($post, 'it')` ou `--locale=it`.

Desde o Pro 2.36.1, palavras-chave, sinônimos e campos de texto passam pelo mesmo tokenizador antes do stemming. A correspondência exige **tokens inteiros e consecutivos**: `cat` não corresponde a `education`. Expressões japonesas usam os mesmos limites de palavras ICU que o corpo do texto. Apóstrofos e hífens separam tokens; `meta-tag` corresponde a `meta tag`, e apóstrofos retos e curvos se comportam da mesma forma. Marcas combinantes continuam ligadas às letras. A normalização de caixa preserva acentos, embora o stemmer de um idioma possa aplicar reduções adicionais.

A contagem escolhe a palavra-chave ou o sinônimo mais longo em cada posição e conta esse trecho uma vez. Sinônimos duplicados e alternativas menores sobrepostas não aumentam a densidade. Por exemplo, a palavra-chave `seo`, com o sinônimo `seo tools`, aparece duas vezes em `seo tools seo`. A ICU continua necessária para encontrar limites de palavras por dicionário em escritas sem espaços; o fallback por regex não fornece esses limites.

Desde o Pro 2.37, o stemming usa um **subconjunto do Snowball 3.1.1 incluído no pacote**. Não exige outro pacote Composer nem baixa arquivos durante a execução. PHP 8.2 continua aceito.

| Mecanismo | Quando é usado | Idiomas |
| --- | --- | --- |
| `snowball` | Padrão; configurações existentes com `auto` usam o mesmo mecanismo incluído | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | Com `seo-pro.checklist.analysis.stemmer = builtin` explícito | Apenas inglês, com o stemmer flexional leve anterior; os demais idiomas usam identidade |
| `identity` | Idioma não coberto ou modo `none` explícito | Ucraniano, japonês, chinês, coreano, tailandês e outros idiomas fora do subconjunto |

Os dois lados da comparação usam o mesmo mecanismo. Stemming reduz terminações; não é um dicionário de sinônimos nem garante equivalência linguística. O algoritmo grego, por exemplo, pode aproximar formas acentuadas e não acentuadas que a comparação por identidade mantém distintas. Os limites de tokens inteiros continuam impedindo que `cat` corresponda a `education`.

#### Atualização a partir do Pro 2.36 {#upgrading-from-pro-2-36}

A configuração `auto` agora usa sempre os algoritmos incluídos, com ou sem `wamania/php-stemmer` instalado. Revise as sugestões editoriais depois da atualização: os novos algoritmos podem alterar correspondências, e turco, grego, polonês e tcheco passam a ter stemming. Os algoritmos de catalão, dinamarquês, finlandês, norueguês, romeno e sueco oferecidos pelo wrapper opcional estão fora deste subconjunto e agora usam comparação por identidade.

Defina `SEO_PRO_CHECKLIST_STEMMER=builtin` para usar o fallback anterior, restrito ao inglês, ou `none` para usar identidade com normalização de caixa em todos os idiomas. Reconstrua o cache de configuração após a mudança. Essas opções não reproduzem os antigos algoritmos multilíngues do wrapper opcional; para preservar exatamente aqueles resultados, seria necessário manter a versão anterior do Pro. Nenhum metadado de SEO salvo é reescrito.

O adaptador incluído passa por 600.395 pares oficiais fixados de vocabulário e saída no PHP 8.2, 8.3 e 8.4. Isso verifica conformidade algorítmica, não aprovação editorial por falantes nativos. Os hashes das fontes, a adaptação apenas sintática para PHP 8.2 e as licenças originais acompanham o pacote. Consulte `THIRD-PARTY-NOTICES.md` na distribuição.

### Segmentação de palavras {#word-segmentation}

Contagem de palavras, densidade e estatísticas de legibilidade dependem de limites de palavras. Em escritas com espaços, uma regex usa limites estáveis de letras e dígitos. Chinês, japonês e tailandês precisam de segmentação por dicionário; uma regex pode tratar um parágrafo como uma única “palavra”. Com **ext-intl** carregada, o tokenizador usa o iterador de palavras da ICU (`IntlBreakIterator::createWordInstance`) para esses trechos, segmentando textos como 東京タワーは東京のランドマークです. Se a ICU estiver ausente, desativada ou não inicializar, o Pro ignora as verificações afetadas de tamanho, legibilidade e palavra-chave e informa como corrigir a instalação ou configuração. Uma contagem não confiável não vira falha. Verificações independentes, como tamanho do título e correspondência em escritas com espaços, continuam funcionando. `seo-pro.checklist.analysis.segmenter = regex` força o mesmo estado indisponível para textos que precisam de dicionário.

O bloco `analysis` inclui `word_count_status` (`available` ou `unavailable`) e `segmentation_reason` (`null`, `missing_intl`, `disabled` ou `initialization_failed`). O tokenizador de baixo nível mantém tokens de fallback por compatibilidade; confira o status antes de interpretá-los como palavras.

A varredura do HTML emite o aviso sem pontuação `word_segmentation_unavailable` em vez de concluir que o conteúdo é insuficiente. Uma ocorrência de conteúdo insuficiente já confirmada permanece aberta até poder ser verificada novamente. Essa varredura incompleta não atualiza a pontuação: um resultado existente mantém seu `scored_at`, e a primeira varredura fica sem pontuação até a segmentação funcionar. Instale a extensão PHP `ext-intl`, ative o segmentador `auto` e execute nova varredura.

### Quais mecanismos analisaram a página {#which-engines-analysed-the-page}

Cada checklist traz um bloco `analysis` com a escrita predominante, o tokenizador (`intl` / `regex`), o stemmer (`snowball` / `builtin` / `identity`) e o método de legibilidade (`formula` / `heuristic` / `lix`). Ele aparece em `toArray()` e `--json`, no rodapé do modal Filament e na última linha de `seo-pro:checklist`:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

O rodapé identifica os mecanismos realmente usados, inclusive regex sem ext-intl e identidade quando o stemming está desativado.

### Densidade de palavras-chave é consultiva {#keyword-density-is-advisory}

O checklist não define uma densidade ideal para posicionamento. Esta verificação é **consultiva**: mostra a contagem, nunca falha e **nunca determina o estado geral da página**. Avalie se a repetição soa natural, sem perseguir um percentual.

### Legibilidade é consultiva {#readability-is-advisory}

A estimativa usa o método escolhido pelo locale da análise. Estas são as fórmulas e os fallbacks implementados:

| Locale | Fórmula | Fonte |
| --- | --- | --- |
| Inglês (`en`) | Flesch Reading Ease | Flesch 1948 |
| Italiano (`it`) | Índice Gulpease | Lucisano & Piemontese 1988 |
| Espanhol (`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| Francês (`fr`) | Kandel-Moles | Kandel & Moles 1958 |
| Alemão (`de`) | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| Português (`pt`, `pt_BR`) | Flesch adaptado ao português brasileiro | Martins et al. 1996 |
| Holandês (`nl`) | Flesch-Douma | Douma 1960 |
| Russo (`ru`) | Adaptação de Flesch de Oborneva | Оборнева 2006 |
| Turco (`tr`) | Ateşman | Ateşman 1997 |
| Polonês (`pl`) | Pisarek, índice de anos de escolaridade normalizado | Pisarek 1969 |
| Japonês, chinês e coreano (`ja`, `zh`, `ko`) | **Heurística sem pontuação**, descrita abaixo | — |
| Grego, ucraniano e tcheco (`el`, `uk`, `cs`) | LIX como fallback, pois não há fórmula específica implementada; sem calibração para esses idiomas | Björnsson 1968 |
| Demais idiomas | LIX (Läsbarhetsindex), fallback sem calibração | Björnsson 1968 |

A escala exibida de **0 a 100, em que valores maiores indicam leitura mais fácil**, é uma convenção do pacote. Resultados da família Flesch e de Gulpease são limitados à escala; índices Wiener, Pisarek e LIX são convertidos para ela. Valores iguais em idiomas diferentes **não** indicam a mesma dificuldade. Os coeficientes vêm de trabalhos publicados, mas as estimativas de tokens, frases e sílabas do Rankbeam não foram validadas como um instrumento completo. Elas não preveem compreensão nem posicionamento em buscas.

Desde o Pro 2.37.1, as estimativas de sílabas em turco e russo contam vogais adjacentes separadamente (`saat`: 2; `поэт`: 2). Os demais estimadores ainda têm limitações: grupos vocálicos podem ocultar hiatos e vogais mudas. O inglês tem uma pequena lista de exceções, não um dicionário de pronúncia. Por exemplo, `país` em espanhol e `monde` em francês podem ser contados incorretamente. Revise manualmente palavras pouco familiares e nomes próprios.

#### Estatísticas do texto e limites da API {#text-statistics-and-api-limits}

Tags HTML de bloco e elementos `br` separam o texto; a ênfase inline continua ligada à palavra. Quebras de linha no código HTML comum viram espaços; texto simples e `pre` preservam os limites de linha. Conteúdo de `script`, `style` e `noscript` é excluído. A extração não avalia visibilidade por CSS nem a página renderizada. Entidades são decodificadas uma vez. Nas estatísticas das fórmulas, sequências de letras e números contam como palavras; pontuação isolada não. Hífens e apóstrofos separam palavras. Dígitos contam como tokens, mas não recebem sílabas inferidas. Letras são contadas no texto original, sem alteração de tamanho pelo stemming ou pela normalização alemã `ß` → `ss`.

A estimativa de frases separa pontuação final `. ! ? 。 ！ ？` e limites de blocos ou linhas, inclui o fragmento final sem pontuação e preserva decimais e uma pequena lista de abreviações comuns, como `Dr.`, `Prof.`, `e.g.` e formas semelhantes em inglês. Por isso, títulos e itens de lista podem contar como frases. Outras abreviações, citações, números, escritas mistas e textos pouco pontuados exigem cuidado. O locale seleciona o método; não verifica se cada frase está naquele idioma.

O `toArray()` do calculador direto inclui um bloco `assessment`:

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` distingue `formula`, `lix`, `heuristic` e `unavailable`; resultados construídos manualmente sem metadados de fórmula usam `unspecified`. Entradas vazias ou compostas apenas por pontuação recebem `insufficient`, com `isValid()` falso. O antigo `score: 0` é um marcador de indisponibilidade, não uma nota de dificuldade. Os rótulos de escolaridade existentes em inglês e italiano são aproximados; os demais idiomas e resultados heurísticos ou LIX não recebem mais esses rótulos. `calculateFleschKincaid()` mantém o nome público por compatibilidade, mas calcula **Flesch Reading Ease**, não o nível escolar Flesch-Kincaid.

Os testes usam entradas contadas independentemente e resultados aritméticos esperados para as dez fórmulas e LIX. Eles verificam o cálculo, não qualidade editorial nativa. A legibilidade continua separada da pontuação de SEO Pro.

::: warning Japonês, chinês e coreano: heurística identificada, sem número
Para esses idiomas, o calculador retorna um **nível** baseado em regras específicas do pacote: média de caracteres por frase (ja ≤ 40/60/80, zh ≤ 30/45/60) ou palavras (ko ≤ 12/18/25). Em japonês, uma proporção de kanji acima de aproximadamente 45% eleva a dificuldade em uma faixa. O resultado tem `heuristic: true` e **pontuação nula**. A mensagem identifica a heurística, e a verificação permanece **consultiva nesses idiomas, qualquer que seja `readability.advisory`**. Ela nunca determina o estado geral do checklist. As contagens de palavras para `ja` e `zh` exigem segmentação ICU funcional; as verificações são ignoradas quando ela está indisponível.
:::

Assim como a densidade, a legibilidade é **consultiva por padrão**: orienta a escrita, mas **não** determina o estado geral da página, mantendo a separação entre análise de legibilidade e SEO também encontrada no Yoast. A verificação é **ignorada** abaixo do mínimo de palavras; conteúdo insuficiente cabe a `content_length`. Para que a dificuldade de leitura possa causar falha nos métodos que aceitam essa opção, altere a configuração:

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## Como ler o checklist {#reading-the-checklist}

### Sem painel {#headless}

O Pro 2.36 lê os metadados resolvidos, `getContentForSEO()` e as palavras-chave de foco no locale de conteúdo solicitado; os rótulos continuam no idioma do operador. Sem locale explícito, respeita o padrão de `seoData()` do modelo de tradução. No Filament, a ação acompanha a aba de idioma do campo ou o seletor de locale da página.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Cada `CheckResult` contém `id`, `group`, `label`, `status`, `message`, uma `recommendation` opcional e o indicador `advisory`.

### Comando {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### No editor (Filament opcional) {#in-the-editor-filament-optional}

Com [`rankbeam/laravel-seo-filament`](/pt-BR/guide/filament) instalado, a ação **On-page checklist** aparece no campo de palavra-chave de foco. Ela abre um modal com as mesmas verificações de aprovação, aviso e falha para o conteúdo salvo do registro. O pacote Filament não depende do Pro; a ação se conecta pelo mesmo ponto de extensão unidirecional usado pelas sugestões de IA, sem afetar instalações sem painel.

## Configuração {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Criar uma verificação própria {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Registre a classe em `seo-pro.checklist.rules`. Uma verificação **não pode** reutilizar um identificador de [ocorrência da varredura](/pt-BR/pro/scan-issues): o checklist usa um conjunto separado de identificadores, sem pontuação.

## Como o conteúdo é lido {#how-the-content-is-read}

`SeoPro::checklistFor($model)` analisa:

- **Título e descrição**: os valores *resolvidos*, efetivamente usados, iguais aos medidos pelos contadores do editor e pela varredura.
- **Conteúdo**: `$model->getContentForSEO()`, accessor do trait `HasSEO` do Core que usa `content`, `body` ou `text` por padrão. Sobrescreva-o para apontar ao texto real do seu modelo.
- **URL**: `$model->getUrlForSEO()`.
- **Palavras-chave de foco**: `seo_meta.focus_keywords` salvo.

A análise não busca páginas nem grava dados.
