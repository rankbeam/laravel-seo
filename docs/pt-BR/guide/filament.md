---
description: "Adicione uma seção SEO a formulários de resources do Filament 4 ou 5 com laravel-seo-filament e HasSEO."
---

# Campos administrativos do Filament

O pacote gratuito [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) acrescenta uma seção SEO com **duas linhas por resource**. Ele suporta Filament **4.x e 5.x**, com Livewire 3 e 4. Editar metadados é gratuito; as análises e o score do exemplo são funções do Pro.

## Pré-requisitos {#prerequisites}

Use um painel Filament 4 ou 5 existente e um model com `HasSEO`. Conclua o [início rápido do núcleo](/pt-BR/guide/quickstart), incluindo migrations e renderização, antes de adicionar o editor.

## Instalar {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

O model do resource precisa usar `HasSEO`.

## Adicionar a seção ao resource {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## Conferir o valor salvo {#check-the-saved-result}

Abra um registro existente, preencha a descrição SEO, salve e recarregue. A descrição deve persistir, aparecer na prévia e mostrar **Manual** como origem. Confira o `<head>` público para confirmar que os visitantes recebem o mesmo valor.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="Campos SEO do Merchant: título, descrição, canonical, imagem social, prévia de busca e origem dos valores." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Exemplo do Merchant. Os campos seguem o tema do painel; controles e limites dependem da versão e configuração instaladas.*

A seção inclui:

- **Título e descrição** com contadores. A [política de tamanho](/pt-BR/guide/multilingual#title-and-description-budgets-per-script) considera a escrita: 60/160 para texto latino, aproximadamente 30/80 para CJK, em grafemas.
- **Palavras-chave de foco:** entrada de tags salva em `[{keyword, is_primary}]`. A primeira é principal; `getPrimaryKeyword()` e `SEOData` leem essa estrutura. Ative `seo.keywords.enabled` para que [`seo:audit`](/pt-BR/guide/audit) e Pro sinalizem páginas sem palavras-chave. Desativado por padrão; veja [configuração (EN)](/reference/configuration#focus-keywords).
- **URL canônica:** vazia para derivação automática, sem parâmetros de consulta.
- **Robots:** vazio para o padrão do site.
- **Imagem social:** upload para `og:image` e `twitter:image`, salvo em `seo/` no disco padrão do Filament.
- **Prévia de busca:** acompanha a cadeia de fallback do resolvedor enquanto você digita.
- **Indicadores de origem:** manual, conteúdo, tipo de model, padrão global, configuração ou URL.

## Limitar os campos {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

Aceita qualquer subconjunto de `title`, `description`, `focus_keywords`, `canonical`, `robots` e `og_image`. Sem a trait, `SEOFields::make(?array $only)` retorna diretamente a mesma seção.

## Como os valores persistem {#how-values-persist}

A seção usa o grupo de estado `seo_meta` e salva pela relação `seoMeta()` do núcleo, atualizando ou criando a linha. Não acrescenta colunas às tabelas de conteúdo. Os valores passam à camada 6, explícita, do [resolvedor](/pt-BR/concepts/resolver-precedence).

## Vários idiomas {#several-languages}

O núcleo mantém [uma linha `seo_meta` por model e idioma](/pt-BR/guide/multilingual). Desde Filament 1.9, informe os idiomas publicados para gerar uma aba por idioma:

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

Ou configure uma vez para todos os resources:

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

Cada aba edita sua própria linha e possui:

- contadores da escrita correspondente: um título japonês vazio mostra `0 / 30`, enquanto o inglês mostra `0 / 60`;
- prévia de busca e cartão social dos valores resolvidos naquele idioma;
- indicadores de fallback próprios;
- um indicador do número de campos preenchidos, para identificar versões vazias.

Com `ext-intl`, o nome do idioma aparece na língua do painel; sem ele, aparece o código. Todas as abas são validadas e salvas juntas. Um idioma sem nenhum valor não gera uma linha vazia.

::: details Caminhos de estado personalizados
Com vários idiomas, o caminho é `seo_meta.{locale}.title`. Com um só, continua `seo_meta.title`. Use o caminho correspondente nas ações próprias.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Abas em inglês, italiano e japonês no Merchant, com limites japoneses de 30 e 80 grafemas e descrição não preenchida." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Merchant, 9 de setembro de 2026, com `locales: ['en', 'it', 'ja']`. A aba japonesa vazia usa seus próprios contadores. O título inglês vem do fallback de conteúdo do model: adicionar uma aba não traduz o conteúdo. O score Pro é o último resultado do registro, não um score por aba.*

### Com um plugin de tradução {#with-a-translatable-plugin}

Com `lara-zeus/spatie-translatable` **1.x no Filament 4** ou **2.x no Filament 5**, use os adaptadores Rankbeam para Edit e Create. Substitua apenas os imports das traits de página. Mantenha as traits de resource e listagem do plugin, sua integração ao painel e a ação `LocaleSwitcher`:

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

Cada classe continua declarando `use Translatable;`. O plugin é uma dependência opcional da aplicação. Use sua versão corrigida mais recente; a fixture local cobre 1.0.4 com Filament 4.13.1 e 2.0.1 com Filament 5.8.1.

Trocar de idioma preserva rascunhos do conteúdo principal, metadados SEO e dados estruturados. Salvar valida todos os idiomas visitados e persiste o conjunto em uma transação. Um erro de validação abre o idioma afetado. Uploads são armazenados ao salvar; sair ou recarregar descarta rascunhos não salvos. Salvar não traduz conteúdo ausente.

Os adaptadores preservam hooks e mutadores de formulário. Se você sobrescreve `handleRecordCreation()`, `handleRecordUpdate()`, `callHook()` ou métodos de transação, integre o comportamento do adaptador e teste o fluxo. Transações do banco não desfazem gravações de arquivos: mantenha a limpeza habitual de arquivos órfãos.

Em campos de texto próprios no Livewire 3, prefira `->live()` ou `->live(onBlur: true)` a um debounce explícito. Ele atrasa o estado local e pode perder as últimas teclas em uma troca rápida de idioma. Os campos do Rankbeam usam o debounce padrão de requisições.

As traits de página originais do plugin preenchem novamente o formulário na troca. O Rankbeam protege contra gravações acidentais de metadados, mas essas traits não mantêm rascunhos SEO. Migre Edit/Create para os adaptadores. Abas explícitas `locales:` continuam sendo um editor compartilhado e têm prioridade sobre o seletor da página.

Sem lista explícita nem idioma da página, a seção edita a língua da aplicação.

## Dados estruturados, schema.org {#structured-data-schema-org}

Uma seção opcional permite adicionar JSON-LD pela interface. Coloque-a ao lado da seção SEO:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

Você também pode usar `SEOSchemaFields::make()` sem trait. A seção grava em `seo_meta.schema_jsonld`, lido pelo [renderizador de schemas](/pt-BR/guide/schema). Ela apenas conecta a interface: os builders do núcleo produzem os documentos e `SchemaValidator` os valida antes de salvar.

Opções disponíveis:

- **Breadcrumb automático:** um interruptor gera `BreadcrumbList` pela cadeia de ancestrais do model, com `BreadcrumbSchema::fromModelAncestors()`, sem campos adicionais.
- **Blocos de schema:** um repeater para FAQ com perguntas e respostas e Product com nome, descrição, imagem, marca, SKU, preço, moeda e disponibilidade. Usa `FAQSchema` e `ProductSchema` do núcleo.

### Validação {#validation}

Um bloco que produziria JSON-LD inválido é rejeitado ao salvar com a mensagem do validador, como uma FAQ sem resposta ou um produto sem imagem ou oferta exigida pelo builder. Blocos totalmente vazios são ignorados.

### O que é armazenado {#what-it-stores}

`schema_jsonld` contém um objeto para um documento, ou um array JSON para vários: breadcrumb primeiro, depois os blocos. As duas formas são emitidas sem alteração por `@seo` e `renderSchema()`.

### Schemas fora do alcance do editor {#schema-it-doesn-t-manage}

Schemas que o formulário não representa são preservados literalmente: `@graph` manual, `@type` específico ou produto com avaliações, notas ou GTIN/MPN. Abrir e salvar o formulário não os substitui.

## Solução de problemas {#troubleshooting}

- **Um valor salvo não aparece na página:** confirme que `@seo($model)` usa o mesmo registro e idioma.
- **O fallback continua aparecendo:** confira se há um valor explícito salvo na língua ativa. O indicador mostra a camada escolhida.
- **Uma aba está ausente:** verifique `locales:`, a configuração e o seletor da página conforme a prioridade descrita.

::: details Testar um painel com Testbench
No orchestra/testbench, registre `SupportServiceProvider` do Filament antes de `LivewireServiceProvider`. O Filament substitui o `DataStore` do Livewire; a ordem inversa causa `ViewErrorBag::put(): ... null given`. A descoberta automática ordena os providers corretamente em aplicações normais.
:::
