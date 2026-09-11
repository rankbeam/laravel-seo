---
description: "Assistência opcional de IA com sua chave: sugestões de título e descrição, explicações de ocorrências, reescrita de descrição e sugestões schema.org. Desativada por padrão."
---

# Assistência de IA {#ai-assist}

Assistência opcional de IA **com sua própria chave**: sugestões de título e meta description, explicações das ocorrências em linguagem simples, **reescrita de descrição** e **sugestões de dados estruturados schema.org**. Vem **desativada**; com a opção desligada, nenhum caminho de IA é executado.

Três características definem o funcionamento:

- **Sua chave, seu provedor.** As requisições saem do **seu servidor** diretamente para Anthropic, OpenAI, Google ou um servidor local ou compatível com OpenAI, conforme sua configuração. Cobranças, quando aplicáveis, vão para sua conta. O pacote não intermedeia nem revende chamadas, e essa integração não envia telemetria ao Rankbeam.
- **Sugestões interativas exigem aceitação explícita.** A seleção preenche o formulário; correções do painel exigem Apply. Desde o Pro 2.42, o comando em massa salva rascunhos privados. `--auto-apply` ativa explicitamente a gravação imediata.
- **Falhas não interrompem o SEO.** Chave ausente ou inválida, falta de crédito, limite de requisições e timeout geram mensagens na interface. Não impedem salvar, renderizar ou executar varreduras.

## Visão geral dos provedores {#providers-at-a-glance}

Escolha conforme acesso à conta, requisitos de dados e custo. As quatro integrações oferecem as mesmas tarefas, mas modelos, formatos de saída, velocidade e qualidade podem variar.

| Provedor | Modelo padrão do pacote | Saída estruturada | Custo ilustrativo | Uso |
|---|---|---|---|---|
| **Local** (Ollama / LM Studio / vLLM) | `llama3.1`, configurável | Melhor esforço por `response_format` | **US$ 0 de tarifa de API** em inferência própria; infraestrutura continua tendo custo | Controle do destino dos dados |
| **OpenAI** | `gpt-5.5` | Structured Outputs quando aceito pelo modelo | Cerca de US$ 0,005 por sugestão nas premissas abaixo | Conta OpenAI existente |
| **Anthropic** | `claude-opus-4-8` | `output_config.format` quando aceito | Cerca de US$ 0,015 por sugestão nas mesmas premissas | Conta Anthropic existente |
| **Google** | `gemini-2.5-flash` | `responseSchema` quando aceito | Cerca de US$ 0,0005 por sugestão nas mesmas premissas | Conta Google, com quotas e preços verificados |

Os nomes representam a **configuração distribuída**, sem garantir disponibilidade atual na sua conta. Os custos usam premissas de exemplo, não preços atuais verificados. Comportamento da integração e observações dos testes publicados:

- **Saída estruturada.** Os caminhos compatíveis de OpenAI, Google e Anthropic recebem um JSON Schema; respostas inválidas falham sem aplicação parcial. Servidores locais recebem `response_format` como melhor esforço. Se o ignorarem, a análise tolerante retorna uma lista válida ou uma falha.
- **Raciocínio altera o consumo.** No teste Gemini descrito, uma descrição consumiu cerca de 500 tokens de raciocínio oculto e 100 visíveis; as chamadas Anthropic testadas não relataram tokens ocultos. Isso não é uma propriedade universal dessas famílias. Tokens ocultos podem ser cobrados como saída, motivo do orçamento mínimo descrito abaixo.
- **Modelos configuráveis.** Defina `SEO_PRO_AI_MODEL` com um modelo disponível e compatível com a API e os parâmetros do adaptador. Exemplos incluem `claude-haiku-4-5`, `gpt-5.4-mini` e `gemma-3-12b-it`. Verifique suporte e qualidade antes de usar em uma coleção inteira.

## Configuração inicial {#setup}

Ative o recurso e informe a chave do provedor no ambiente. Ao mudar de provedor, atualize provedor, chave e qualquer modelo sobrescrito. O adaptador local também precisa da URL do servidor.

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip Chave de API e assinatura Claude ou ChatGPT são separadas
Uma **assinatura** Claude Code, Claude.ai ou ChatGPT não financia a **API**. `SEO_PRO_AI_API_KEY` deve ser uma chave da plataforma de desenvolvedores do provedor, ou do Google AI Studio, com crédito ou quota próprios. Uma conta sem saldo ou quota disponível pode autenticar e depois retornar erro de **crédito ou quota esgotados**. Veja [Solução de problemas](#troubleshooting).
:::

O bloco `ai` de `config/seo-pro.php` expõe `timeout`, `max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models`, `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model`, `retry`, a tabela `pricing` e o bloco `local`. Consulte [Limites e ajustes](#limits-and-tuning) e [Geração em lote com outro modelo](#cheaper-bulk-generation).

::: warning Chave com configuração em cache
A configuração armazena apenas o **nome** da variável, `api_key_env`, nunca a chave. Assim, `php artisan config:cache` não grava essa chave em `bootstrap/cache/config.php`. Com a configuração em cache, porém, `.env` não é carregado. Defina `SEO_PRO_AI_API_KEY` como variável real do ambiente do servidor.
:::

## Inferência local e opções de nuvem {#running-at-0-and-the-cheapest-paid-option}

- **Inferência própria evita a tarifa de API por token.** Hardware, eletricidade e operação continuam tendo custo. O conteúdo só permanece na sua rede se o servidor configurado e suas dependências também permanecerem nela.
- **Google tem preços e quotas por modelo e modalidade.** Uma chave AI Studio (`aistudio.google.com/apikey`, formato `AIza…`) pode permitir testes gratuitos. Confira os limites para seu uso antes de ativar cobrança quando necessário. `gemini-2.5-flash` é o padrão distribuído. Modelos Gemini e Gemma não têm todos os mesmos recursos de raciocínio; `reasoning_models` usa padrões de nome configurados, sem testar capacidades.

Para escolher onde a inferência será executada:

- **Local ou compatível com OpenAI.** Use `provider=local` com servidores compatíveis com Chat Completions, como **Ollama**, **LM Studio**, **vLLM** ou **LocalAI**, ou com um gateway remoto como **OpenRouter**. `SEO_PRO_AI_LOCAL_BASE_URL` aponta para a raiz da API; o pacote acrescenta `/chat/completions`. Configure um `SEO_PRO_AI_MODEL` disponível. Um gateway remoto recebe dados fora da sua rede e pode cobrar: o nome do adaptador `local` não garante execução local.

::: warning `base_url` é validada; localhost exige ativação explícita
`base_url` é uma configuração privilegiada, validada pelo **SsrfGuard**: apenas HTTP/HTTPS, sem credenciais na URL e, por padrão, resolução para endereço **público**. Isso impede usar uma URL indevida para sondar serviços internos. Para um servidor em `127.0.0.1`, ative `seo-pro.ai.local.allow_local_addresses` com `SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`. Mantenha desativado para gateways públicos como OpenRouter. O caminho da requisição é fixo e redirecionamentos não são seguidos, evitando encaminhar a chave a outro servidor por esse mecanismo.
:::

::: tip Controle de raciocínio em modelos Ollama compatíveis
Um modelo local com raciocínio pode ultrapassar o timeout. Se modelo e versão do servidor aceitarem, `['think' => false]` em `seo-pro.ai.local.extra_body` pode desativar esse recurso nas sugestões. Consulte a [documentação do Ollama](https://docs.ollama.com/capabilities/thinking) e aumente `seo-pro.ai.timeout` quando necessário. O pacote não envia `temperature`, pois alguns modelos rejeitam esse parâmetro.
:::

## Custo {#cost}

O pacote não acrescenta margem às chamadas. Você paga diretamente ao provedor; na inferência própria, não há tarifa de API do provedor, mas há infraestrutura. Considere o custo **por sugestão** e o custo de **preencher uma coleção**.

A tabela `seo-pro.ai.pricing`, em dólares por milhão de tokens, converte uma estimativa de tokens no valor apresentado antes do preenchimento. São **premissas distribuídas para estimativa**, não preços públicos atuais verificados. **Substitua pelos preços atuais do seu provedor**:

| Padrão de modelo | Entrada US$/1M | Saída US$/1M |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

O exemplo publicado usa o consumo de uma página testada, com um título e uma descrição, e essas premissas. A última coluna aplica o desconto ilustrativo de 50% para os adaptadores com lote assíncrono. Não são cotações atuais.

| Provedor / modelo | Aproximado por par de sugestões | Aproximado por 1.000 registros | Aproximado por 1.000 registros com `--batch` |
|---|---|---|---|
| Local `gemma`/`llama` (Ollama) | **US$ 0 de tarifa de API** | **US$ 0 de tarifa de API** | Não implementado pelo adaptador |
| Google `gemini-2.5-flash` pago | ~US$ 0,001 | ~US$ 0,40 | Não implementado pelo adaptador Rankbeam |
| OpenAI `gpt-5.5` | ~US$ 0,008 | ~US$ 5,25 | **~US$ 2,63**, com 50% de desconto |
| Anthropic `claude-opus-4-8` | ~US$ 0,03 | ~US$ 20 | **~US$ 10**, com 50% de desconto |

O comando apresenta a estimativa como aproximadamente ±50%, mas **isso não é limite de gasto nem margem de erro garantida**. Entradas, saídas e preços alteram o total. A estimativa considera saída visível; raciocínio oculto cobrado pode aumentar o custo. Modelos sem preço configurado mostram apenas a estimativa de tokens.

### Geração em lote com outro modelo {#cheaper-bulk-generation}

Defina `seo-pro.ai.bulk_model`, ou `SEO_PRO_AI_BULK_MODEL`, para usar outro modelo **somente no preenchimento** por `seo-pro:ai-fill` e `SeoPro::aiFill()`. Filament e `seo-pro:ai-suggest` continuam usando `model`. Com `null`, todos usam `model`. A estimativa usa o preço do modelo selecionado. Avalie saídas representativas antes de ampliar o volume; um modelo barato não é automaticamente adequado.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Os exemplos do pacote usam **anthropic** `claude-haiku-4-5`, **openai** `gpt-5.5-mini`, **google** `gemini-2.5-flash` ou um modelo **local** menor. Um padrão de preço pode corresponder a um nome que o provedor não oferece; confirme o identificador real, a compatibilidade da API e o preço.

Exemplo de **100 páginas** sem título nem descrição, totalizando 200 chamadas, com os preços distribuídos e 600 tokens de entrada mais 150 de saída por chamada:

| Provedor | Modelo principal: 100 páginas | Modelo econômico `bulk_model`: 100 páginas |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **US$ 4,05** | `claude-haiku-4-5` ≈ **US$ 0,27** |
| **OpenAI** | `gpt-5.5` ≈ **US$ 1,05** | `gpt-5.5-mini` ≈ **US$ 0,11** |
| **Google** | `gemini-2.5-pro` ≈ **US$ 0,45** | `gemini-2.5-flash` ≈ **US$ 0,04** |
| **Local** (Ollama / vLLM) | Qualquer modelo: **US$ 0 de tarifa de API** | Qualquer modelo: **US$ 0 de tarifa de API** |

São estimativas ilustrativas, sem margem ±50% garantida e sem provisão para raciocínio oculto. Atualize `seo-pro.ai.pricing` com os preços publicados do provedor escolhido antes de depender desses valores.

## Idioma da saída {#output-language}

Desde o Pro 2.34, cada prompt informa o idioma da página e o código BCP-47: por exemplo, “em português brasileiro (pt-BR), o idioma da página, independentemente de outros idiomas no trecho”. O contexto também inclui uma linha `Language:`. Antes, o modelo precisava inferir o idioma de um trecho curto ou misto; uma página turca com marca em inglês podia receber saída inglesa. O locale é o usado na resolução dos metadados, ou o da aplicação quando a página não declara um. Ele também determina o [limite de tamanho](/pt-BR/guide/multilingual#title-and-description-budgets-per-script); uma página japonesa pede títulos de cerca de 30 caracteres **em japonês**.

Desde o Pro 2.36, um locale explícito controla em conjunto a linha de metadados, os métodos de conteúdo e o idioma do prompt, mantendo o idioma da interface do operador.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Os argumentos posicionais existentes não mudam. Sem `locale:`, o serviço usa o padrão de `seoData()` do modelo, permitindo que modelos de tradução separados declarem seu idioma. O trecho usa `getContentForSEO()` quando não vazio e depois os campos de conteúdo configurados. O Filament 1.11 passa automaticamente o locale da aba selecionada, inclusive nos modos de locale único e seletor por página.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

`plan()`, `fill()` e `submitBatchFill()` também aceitam `locale:` ao final. Use o mesmo locale ao criar `FillProgress(..., locale: 'it')` e enviar a execução. Cada item do lote registra seu locale; a coleta usa esse valor e verifica novamente a linha de metadados antes de gravar. Execuções com locale explícito têm checkpoints separados e marcadores de processamento que distinguem idiomas. Repita o mesmo comando para coletar. Tarefas próprias devem serializar e passar o locale de conteúdo.

Checkpoints anteriores ao Pro 2.36 não registravam locale. Um lote antigo pendente é preservado, mas sua coleta automática é recusada. Confira os resultados no provedor e o idioma pretendido antes de descartá-lo com `--fresh`, para evitar cobrar o mesmo trabalho novamente. Checkpoints sequenciais antigos com registros processados também exigem essa conferência antes da redefinição.

### Avaliação por idioma {#per-language-evaluation}

O repositório Pro contém 170 páginas de entrada em 17 locales e um sistema opcional de avaliação. As entradas passaram por verificações estruturais e heurísticas de idioma base; a aprovação nativa independente continua pendente.

A avaliação verifica **títulos e descrições**, registra comprimentos em grafemas e evidências de idioma e escrita, e preserva cada resposta antes das asserções. Títulos curtos, textos mistos e caracteres compartilhados entre chinês e japonês podem continuar incertos. Identificar português não comprova uso brasileiro, e verificações parciais de caracteres chineses não certificam qualidade regional.

Execuções reais exigem `SEO_PRO_AI_EVAL=1`, seleção explícita em `SEO_PRO_AI_EVAL_LOCALES` e um identificador `SEO_PRO_AI_EVAL_RUN`. Podem gerar cobranças e não executam por padrão. As evidências registram provedor, modelo solicitado e retornado, hashes da entrada, requisição e código, além de horários. Tentativas com falha são preservadas. Retomar reutiliza respostas salvas; uma requisição interrompida exige nova tentativa explícita porque pode já ter chegado ao provedor.

As evidências ficam em `storage/app/seo-ai-evals/<run-id>/` no ambiente de testes do código-fonte. O `README.md` das entradas documenta comandos e formato versionado. Revisores nativos avaliam hashes exatos em registros separados. Aprovação automática não equivale a revisão nativa nem garante texto pronto para publicação.

## Limites e ajustes {#limits-and-tuning}

As opções ficam no bloco `ai` de `config/seo-pro.php`:

- **`timeout`**, padrão `15` segundos, variável `SEO_PRO_AI_TIMEOUT`: limita também a chamada síncrona ao abrir o modal Filament. Modelos locais ou com raciocínio podem precisar de mais tempo. Aumente conforme necessário e veja a opção Ollama `think => false` acima. Timeout exibe um erro sem impedir salvar.
- **`max_input_chars`**, padrão `6000`: limita custo e volume de conteúdo enviado por requisição, em texto simples sem HTML.
- **`max_output_tokens`**, padrão `1000`: limite base de tokens gerados. Uma resposta cortada gera `truncated`, sem aplicar uma resposta incompleta.
- **`token_budgets`**: limites por tarefa — `suggestions` 800, `explanation` 600, `rewrite` 300 e `schema_suggestion` 700 — sujeitos ao mínimo de raciocínio.
- **`reasoning_models`** e **`reasoning_min_output_tokens`**, padrão `2000`: nomes que correspondem a padrões como `*gemma*`, `gemini-2.5-*`, `o1*`, `o3*` ou `o4*` recebem esse orçamento mínimo, pois podem consumir tokens ocultos antes de produzir texto visível.
- **`suggestion_count`**, padrão `3`: quantidade de alternativas de título ou descrição solicitadas.
- **`retry`**: novas tentativas automáticas para falhas transitórias elegíveis, conforme [Tratamento das respostas](#how-replies-are-handled).

## No Filament {#in-filament}

Com os pacotes Filament opcionais instalados, incluindo `rankbeam/laravel-seo-filament` ≥ 1.1, ativar a assistência acrescenta:

- **Suggest with AI** nos campos de título e descrição das páginas de edição que usam a seção SEO. O modal mostra alternativas e contadores; selecionar uma preenche o campo para revisão.
- **Explain (AI)** na tabela de ocorrências do painel, com explicação curta e correção concreta.
- **Rewrite description (AI)** ao lado de Explain, propondo uma descrição dentro do orçamento da página: 160 caracteres para escrita latina e cerca de 80 para CJK, conforme a [política do Core](/pt-BR/guide/multilingual#title-and-description-budgets-per-script). A revisão acontece no modal; **Apply rewrite** grava em `seo_meta`. Nada é salvo antes dessa ação.
- **Suggest structured data (AI)** na tabela de ocorrências, propondo Product, Article ou Breadcrumb e mostrando o JSON-LD construído. **Apply structured data** acrescenta o resultado a `seo_meta.schema_jsonld`, a mesma coluna do [editor de dados estruturados](../guide/filament#structured-data-schema-org), onde permanece editável. Sugestões incompletas, como Article sem autor ou imagem, mostram os campos ausentes e **não são aplicadas**.

As duas últimas ações são [correções de escopo limitado](#bounded-fixes-propose-never-auto-apply).

## Correções de escopo limitado, com aceitação explícita {#bounded-fixes-propose-never-auto-apply}

Essas duas ações produzem um valor limitado que pode ser aplicado em um clique. Ambas **apenas propõem**; a gravação depende da sua aceitação.

- **Reescrever descrição**, `SeoSuggestionService::rewriteDescription($model, $issue?)`, retorna uma descrição dentro do orçamento por escrita da página, 160 para latina ou cerca de 80 para CJK. O orçamento vem do valor resolvido da própria página, como nos prompts de sugestões. Se a saída ultrapassar o limite, o código a corta deterministicamente em um limite de frase ou palavra, evitando `description_too_long` por excesso de tamanho. A ocorrência opcional orienta a reescrita, distinguindo descrição longa de ausente.
- **Sugerir dados estruturados**, `SeoSuggestionService::suggestSchemaType($model)`, solicita apenas **tipo e valores dos campos**, nunca JSON-LD bruto. O código monta o documento com `ProductSchema`, `ArticleSchema` ou `BreadcrumbSchema` e valida por `SchemaValidator`, evitando que estrutura, `@type` ou `@context` inventados sejam aplicados. Campos obrigatórios ausentes tornam a sugestão *incompleta* e impedem a aplicação. Em um teste real descrito, um provedor propôs Article para uma página curta; a aplicação foi recusada por falta de autor e imagem, enquanto outros provedores não sugeriram tipo.

## Uso sem painel {#headless}

Os mesmos recursos estão disponíveis em JSON para scripts e aplicações sem Filament:

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

A saída inclui sugestões, ou tipo recomendado, JSON-LD construído e resultado de validação, além do modelo usado e **tokens por requisição**, separados em entrada, saída e raciocínio. Esses dados ajudam a estimar custo nas tarifas do provedor, mas não são uma fatura. Falhas retornam código de saída diferente de zero e erro no JSON. `seo-pro:suggest-schema` **apenas propõe**: imprime o documento sem gravar.

## Preenchimento de metadados ausentes {#bulk-fill-missing-metadata}

**O Pro 2.42 muda o padrão da CLI:** `seo-pro:ai-fill` salva um rascunho privado por campo gerado. Os metadados publicados permanecem iguais até a aprovação. Valores existentes e alternativas calculáveis são ignorados; um rascunho pendente ainda válido evita nova geração.

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review` lista os primeiros 100 rascunhos pendentes em JSON. Informe um ID para ler o valor e as evidências privadas. Aprovação e rejeição funcionam com IA desativada, sem chamadas ao provedor. A aprovação exige uma identificação do operador e recusa rascunhos cuja fonte ou metadados mudaram, ou cujo registro foi excluído. A identificação é declarada pelo operador, não prova revisão humana substancial. `--connection=NAME` seleciona uma conexão configurada.

`--auto-apply` restaura explicitamente a publicação imediata dos campos ainda ausentes. `--force` pula a confirmação, **não a revisão**. `--dry-run` gera e mostra sem salvar rascunhos ou metadados, mas chama o provedor e pode custar. `--field`, `--limit` e `--locale` delimitam o trabalho. Revise comandos agendados depois da atualização.

### Volume: intervalos, estimativa de custo e retomada {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms` tem padrão de 200 milissegundos. A partir de `confirm_over`, 100 registros por padrão, o comando mostra a estimativa de `seo-pro.ai.pricing` e pede confirmação. Checkpoints preservam campos concluídos após interrupções. Um timeout após aceitação ainda pode causar cobrança duplicada; reconcilie trabalho incerto antes de `--fresh`. Execute apenas um trabalho correspondente por vez.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

Em integrações próprias, passe `review: true` para criar rascunhos. A API PHP mantém `apply: true, review: false` por compatibilidade: chamadas existentes continuam gravando imediatamente. `apply: false` mostra sem persistir. `filled` conta registros tratados, incluindo rascunhos no modo de revisão; a CLI os chama de `staged`.

### Modo batch com desconto de 50% {#batch-mode-50-cheaper}

`--batch` usa o endpoint assíncrono suportado da Anthropic ou OpenAI. A estimativa considera o desconto documentado; confira preços atuais do modelo. Adaptadores Google e local usam geração sequencial. Envie e repita o mesmo comando depois para coletar rascunhos:

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

Mantenha provedor, idioma e modo de publicação entre envio e coleta. Revisão e `--auto-apply` têm checkpoints separados; a CLI impede o outro modo enquanto existir um lote correspondente em andamento. Envio incerto exige reconciliação. Sucessos parciais são preservados; falhas transitórias podem ser tentadas novamente. A coleta verifica campos ausentes; a aprovação também verifica a fonte anterior ao envio. `seo-pro.ai.fill.batch.request_timeout` tem padrão de 120 segundos. Coleta agendada salva rascunhos por padrão.

## Procedência, migrações e filtragem de dados {#origin-review-and-filtering}

Requer **Core 3.21 e Pro 2.42**. O Core carrega sua migração automaticamente; publique as migrações Pro e migre cada banco usado pelos modelos SEO antes de gerar sugestões salvas:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

`seo_ai_proposals` guarda valores gerados e evidências de provedor/modelo/requisição com casts criptografados Laravel. Proteja `APP_KEY` e seu backup: perder a chave torna os valores ilegíveis. Identificadores, status e decisões continuam em colunas comuns. Alternativas de formulários abandonados podem permanecer `offered` ou `selected`. Não existe limpeza automática: defina retenção, preserve rascunhos pendentes e evidências referenciadas por `seo_meta.ai_provenance`, e restrinja exports e saídas do comando.

Sugestões aceitas, correções do painel e valores em massa preservam a origem por campo. Edições posteriores via Eloquent mantêm `origin: ai` e definem `edited: true`; isso indica alteração, não verificação humana. Esvaziar o campo remove o marcador. HTML, arrays, JSON e Inertia do Core expõem somente campo, origem e estado de edição, usando a meta tag personalizada `rankbeam:ai-origin` quando aplicável. IDs de geração e detalhes do provedor ficam privados. Não exponha modelos `SEOMeta` brutos em APIs públicas.

Cobre futuros salvamentos suportados, não conteúdo histórico nem todas as revisões. SQL direto, updates pelo query builder e renderizadores próprios podem contornar os controles. Redefina explicitamente a procedência quando uma substituição escrita de forma independente justificar; edições comuns a mantêm. O marcador não é marca d’água padronizada, atribuição inviolável nem declaração de conformidade com o artigo 50. Qualidade real e marcações nativas dos provedores exigem avaliação separada.

Você pode implementar `AiPromptFilter` e configurar `seo-pro.ai.context_filter`. Ele filtra o prompt de usuário completo antes do envio síncrono ou batch; uma falha impede o envio com erro sanitizado. Instruções do sistema permanecem iguais. O padrão é `null`: **sem remoção automática de dados sensíveis**. O exemplo substitui só um valor conhecido; implemente e teste regras adequadas à aplicação:

```php
namespace App\Support;

use Rankbeam\Seo\Pro\Ai\AiPromptFilter;

final class RedactAiContext implements AiPromptFilter
{
    public function filter(string $prompt): string
    {
        return str_replace('internal@example.com', '[redacted]', $prompt);
    }
}

// Configure seo-pro.ai.context_filter with this class in config/seo-pro.php.
// Runtime equivalent:
config(['seo-pro.ai.context_filter' => RedactAiContext::class]);
```


## Tratamento das respostas {#how-replies-are-handled}

Cada chamada retorna um envelope comum aos provedores:

- **Saída estruturada quando aceita.** Os caminhos compatíveis de OpenAI, Google e Anthropic usam Structured Outputs, `responseSchema` e `output_config.format`. JSON inválido vira falha, sem extrair fragmentos de texto. O adaptador local pede `response_format` como melhor esforço e, se o servidor o ignorar, usa análise tolerante. O resultado é uma lista válida ou uma falha clara, nunca uma resposta parcialmente aplicada.
- **Truncamento explícito.** Atingir o limite de saída gera `truncated` com orientação para revisar `seo-pro.ai.max_output_tokens`. Modelos com raciocínio podem consumir o orçamento antes do texto visível; nomes correspondentes recebem automaticamente o mínimo `reasoning_min_output_tokens`.
- **Novas tentativas limitadas.** Respostas `429` e `5xx` elegíveis são repetidas com espera exponencial limitada, respeitando `Retry-After` dentro de um teto. Chave inválida, requisição malformada, conteúdo excessivo, **timeout** e **crédito ou quota esgotados** não recebem repetição automática da chamada. Configure o bloco `retry`; `max_attempts: 0` desativa as repetições.
- **Erros tipados e sanitizados.** Falhas têm códigos estáveis, como `unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`, `content_too_large`, `bad_request`, `truncated`, `content_filtered` e `provider_error`, além de `retryable` quando aplicável. A mensagem é curta e sanitizada. **O corpo bruto do provedor não é exibido nem registrado no caminho normal da aplicação**; o sistema opcional de avaliação preserva respostas como evidência. No Filament, o modal apresenta a falha e orientações para os casos comuns descritos em [Solução de problemas](#troubleshooting).

## Solução de problemas {#troubleshooting}

As falhas aparecem na interface sem interromper o restante do sistema, com código e mensagem sanitizada:

| Sintoma ou código | Significado | Correção |
|---|---|---|
| **`quota_exceeded`**, crédito ou quota esgotados | A chave é válida, mas a **conta de API está sem crédito ou quota**. Anthropic pode informar saldo baixo; OpenAI, `insufficient_quota`; Google, créditos pré-pagos esgotados. Não é um rate limit transitório. | Confira crédito e cobrança no console do provedor ou use inferência própria. Assinaturas Claude/ChatGPT não financiam a API. |
| **`unauthorized`**, falha de autenticação | Chave ausente, incorreta ou de outro provedor. | Confira a variável nomeada por `seo-pro.ai.api_key_env`, padrão `SEO_PRO_AI_API_KEY`, e sua correspondência com `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`**, limite de chamadas | Limite **transitório**, após as tentativas automáticas. | Aguarde e tente novamente. Em lotes, aumente `seo-pro.ai.fill.throttle_ms`. Inferência local depende da capacidade do seu servidor. |
| **`timeout`**, tempo esgotado | Resposta não chegou dentro de `seo-pro.ai.timeout`, padrão 15 segundos; comum em modelos locais lentos. | Aumente `SEO_PRO_AI_TIMEOUT`; no Ollama compatível, considere `['think' => false]` em `seo-pro.ai.local.extra_body`. |
| **`truncated`**, limite de saída atingido | Resposta consumiu o orçamento, possivelmente incluindo raciocínio oculto. | Revise `seo-pro.ai.max_output_tokens` e os padrões `reasoning_models`; alguns modelos precisam de 2.000 tokens ou mais. |
| **`content_too_large`**, HTTP 413 | Conteúdo enviado ultrapassou o limite do provedor. | Reduza `seo-pro.ai.max_input_chars`. |
| **`bad_request`** | Requisição malformada, modelo indisponível ou parâmetro incompatível. | Confira `SEO_PRO_AI_MODEL` e a compatibilidade com a conta, o servidor e o adaptador. |
| **`content_filtered`** | O filtro de segurança do provedor recusou a resposta. | Revise o conteúdo e a orientação do provedor; não repita automaticamente a mesma requisição rejeitada. |

::: tip Inferência local ainda exige um servidor funcional
`SEO_PRO_AI_PROVIDER=local` com inferência própria evita problemas de crédito da API em nuvem, mas continua dependendo de hardware, modelo, compatibilidade, timeout e capacidade. Um gateway remoto configurado nesse adaptador pode exigir chave e pagamento.
:::

## O que sai do seu servidor {#what-leaves-your-server}

Somente para o provedor configurado e mediante ação explícita, por clique ou comando:

- **Sugestões**: nome curto da classe e chave do modelo, como “Post #3”, título e descrição resolvidos, canonical e trecho de texto sem HTML limitado por `max_input_chars`, 6.000 caracteres por padrão.
- *Explicações de ocorrências*: tipo, gravidade, campo, mensagem e URL de destino; quando há modelo, também classe/chave, título e descrição resolvidos, URL canônica e trecho de texto limitado.
- **Reescrita de descrição**: contexto mínimo da página usado nas sugestões e, quando fornecidos, tipo e mensagem da ocorrência.
- **Sugestão de dados estruturados**: o mesmo contexto mínimo. O modelo retorna tipo e valores dos campos; o JSON-LD é montado localmente.

O pacote não coleta deliberadamente dados de visitantes, IPs, cabeçalhos ou credenciais para compor prompts, nem envia o HTML completo. **Seus campos de conteúdo e trechos podem conter dados sensíveis**; revise o que a aplicação fornece. A chave do provedor é usada para autenticar a requisição. O `SECURITY.md` do repositório Pro é a referência de tratamento de dados.
