---
description: "Conteúdo multilíngue no Rankbeam: limites por escrita, grafemas, caixa, hreflang, inLanguage, buscadores regionais, fontes e URLs Unicode."
---

# Conteúdo multilíngue

As [traduções](/pt-BR/guide/translations) definem o idioma da interface. Esta página trata da **língua do conteúdo**: limites diferentes para japonês, corte de tailandês sem espaços, equivalência turca entre `İstanbul` e `istanbul`, correção de `it_IT` em hreflang e rastreadores como o Naver para a Coreia. Essas regras ficam no núcleo para manter os componentes alinhados.

Os padrões e políticas ficam em `config/seo.php`. Algumas funções precisam de ICU para segmentar palavras ou de fontes instaladas para renderizar caracteres. A aplicação deve fornecer o conteúdo traduzido.

## Idioma do conteúdo e da interface {#content-locale-and-interface-locale}

Core 3.17, Filament 1.11 e Pro 2.36 levam o idioma escolhido aos metadados, hooks calculados, URLs de prévia, palavras-chave e requisições de IA. Um painel em inglês pode editar italiano ou japonês sem mudar seus próprios rótulos.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

As leituras selecionam a linha de metadados daquele idioma e executam `getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()` e `getSEOSchema()` em um contexto temporário. Os idiomas do model chamador e da aplicação são preservados, inclusive se um hook lançar exceção. Models com `setLocale()` e `getTranslatableAttributes()` do Spatie também recebem um idioma de instância isolado. Os hooks precisam retornar traduções; o Rankbeam não traduz atributos comuns automaticamente.

Métodos de IA baseados em models e preenchimento em lote do Pro aceitam `locale:` explícito. Sem ele, o padrão de `seoData()` sobrescrito por um model traduzível determina a língua, com fallback para a aplicação. Ações Filament recebem o idioma do próprio campo, inclusive em edição monolíngue e modo de acompanhamento. Em jobs próprios, serialize a língua escolhida e informe-a na execução. Não dependa do idioma atual do worker.

Para leitores síncronos próprios, `ModelLocale::run($model, $locale, $callback)` passa um model isolado ao callback e restaura a língua da aplicação em `finally`. Termine as leituras dependentes do idioma dentro do callback. Retornar um iterador lazy ou closure não prolonga o contexto.

## Orçamentos de título e descrição por escrita {#title-and-description-budgets-per-script}

O Rankbeam usa orçamentos editoriais de 60/160 grafemas para títulos/descrições latinos e 30/80 para CJK. São aproximações configuráveis, não medidas em pixels nem garantias de exibição completa. O Google não fixa limites de caracteres para [links de título](https://developers.google.com/search/docs/appearance/title-link) ou [descrições](https://developers.google.com/search/docs/appearance/snippet); o texto pode ser cortado conforme a largura do dispositivo.

`Rankbeam\Seo\I18n\LengthPolicy` escolhe o orçamento para o texto:

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

Avisos (`SEOWarningEvaluator`), auditoria gratuita, corte de descrições calculadas, Pro e contadores Filament usam essa política. Avisos avaliam valores resolvidos, incluindo o sufixo; o editor também pode mostrar texto não salvo. O tamanho conta **grupos de grafemas**, não bytes nem pontos de código. As fronteiras dependem da implementação Unicode instalada; não medem sílabas nem pixels dos resultados de busca.

`seo.length_policy` usa os grupos `latin`, `cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew` e `devanagari`, com `default` para os demais. Uma linha pode substituir algumas chaves e herdar o restante:

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Só `cjk` difere por padrão. Uma instalação atualizada com configuração publicada antiga também recebe a linha CJK integrada.

::: tip Títulos mistos
A detecção pondera letras: cada caractere CJK conta duas vezes. “Laravel SEO の完全ガイド” é CJK; “Laravel SEO for the 東京 developer” permanece latino. Sem letras, como em preço ou ano, vale a escrita do idioma da página.
:::

As constantes `SEOWarningEvaluator::TITLE_MAX_LENGTH` e `DESCRIPTION_MAX_LENGTH` continuam disponíveis como padrões latinos.

## Corte seguro por grafemas e escrita {#grapheme-safe-script-aware-truncation}

A política ajusta o orçamento latino `seo.computed.description_max_length`, pela metade para CJK. Depois, `Rankbeam\Seo\I18n\Truncator` faz o corte:

- Com espaços entre palavras, usa a última fronteira dentro do limite se ela atingir pelo menos 60 % do orçamento. Não acrescenta reticências e remove pontuação final; mantém o comportamento latino anterior byte a byte.
- Para Han, Kana e tailandês, prefere a última pontuação de frase ou oração (。！？、，…) dentro do limite; depois um espaço, se houver, como em coreano; por fim, corta no limite.
- Sempre entre grupos de grafemas, sem separar marcas combinantes da base, como sinais vocálicos tailandeses ou modificadores de emoji.

## Maiúsculas e minúsculas por idioma {#locale-aware-casing}

`mb_strtolower()` ignora a língua. `Rankbeam\Seo\I18n\CaseFolder` a considera:

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` produz a forma de exibição. `fold()`, `equals()`, `contains()` e `containsWord()` servem às comparações. O núcleo os usa para evitar repetição de marca no sufixo (`seo.title_suffix_skip_when_contains`), incluindo formas turcas de i e fronteiras Unicode de palavra. As verificações de palavras-chave do Pro usam o mesmo helper.

O case folding preserva acentos. Nem toda forma acentuada equivale à não acentuada. Um stemmer pode aplicar reduções próprias, separadas de `CaseFolder` e da correspondência por identidade.

## hreflang {#hreflang}

O Google aceita `language[-Script][-REGION]`: língua de duas letras ISO 639-1, escrita ISO 15924 opcional e região de duas letras ISO 3166-1 opcional, além de `x-default`. Regiões numéricas como `es-419` são BCP47 válido, mas não fazem parte dos [códigos hreflang aceitos pelo Google](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes).

Aplicações Laravel costumam fornecer uma locale como `it_IT` ou `pt_br`, cujo sublinhado é inválido aqui. Três políticas `seo.hreflang` tratam `getSEOAlternates()` antes de gerar `<link rel="alternate">`, entradas `<xhtml:link>` no sitemap, links `llms.txt` e dados da auditoria. `llms.txt` omite a própria página e `x-default` nos links “Also in”.

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`**, ativo por padrão, adapta separadores, caixa e aliases registrados (`iw_IL` → `he-IL`). Preserva separadores repetidos (`en__US` → `en--US`) para que a auditoria os sinalize. Desative para manter os bytes originais.
- **`include_self`** acrescenta idioma e canonical próprios quando nem a URL nem o código estão na lista. Ative se o hook retorna apenas outras línguas, para que cada versão também referencie a si mesma.
- **`x_default`** escolhe a língua cuja alternativa será duplicada como `x-default` se o marcador estiver ausente.

Uma lista vazia continua vazia: páginas sem traduções não recebem autorreferência nem `x-default`.

A auditoria gratuita verifica a lista após as políticas:

| Código | Gravidade | Significado |
|---|---|---|
| `hreflang_invalid_code` | warning | Código fora do formato Google: `en-UK`, `jp`, `english`, `es-419`, `fil`. |
| `hreflang_duplicate_code` | notice | Mesmo código repetido. |
| `hreflang_missing_self` | warning | URL da página ausente da própria lista. |

A reciprocidade exige rastreamento. Com `check_hreflang_reciprocity`, o Pro busca cada alternativa pelo SsrfGuard e emite `hreflang_not_reciprocal` se o destino não declarar a URL de origem **com o código de idioma correspondente** (Pro 2.38+; [códigos de rede (EN)](/pro/scan-issues#network-codes)). O helper é público:

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### Três contratos para códigos de idioma {#three-language-code-contracts}

Core **3.18+** separa a configuração da aplicação do valor servido no HTML:

| Entrada | Normalização da aplicação | Idioma HTML | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | Inválido como recebido | Inválido como recebido |
| `de-CH-1901` | Preservado | Variante registrada válida | Variante não aceita |
| `es-419` | Preservado | Região numérica válida | Região numérica não aceita |
| `zh-Hant-TW` | Preservado | Válido | Válido |
| `fil` | Preservado | Língua registrada válida | Não possui duas letras |
| `iw_IL` | `he-IL` | Sublinhado inválido; `iw-IL` ainda é uma tag obsoleta válida | Usar `he-IL` normalizado |
| `en__US` | `en--US` | Inválido | Inválido |
| `x-default` | Preservado | Rejeitado pelo Rankbeam como língua do conteúdo | Marcador de fallback válido |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Migração do Core 3.17 e anteriores:** `Hreflang::isValid()` e `parse()` validam estritamente códigos servidos. Para uma locale Laravel, chame `fromLocale()` primeiro. Para o atributo HTML `lang`, use `LanguageTag::isValidHtml()` sem aparar nem normalizar. Tags registradas obsoletas continuam válidas no HTML. Só aliases preferidos explícitos da IANA são normalizados; o pacote não presume que `en-UK` significa `en-GB`. Entradas malformadas não são ocultadas antes da auditoria.

O validador inclui o registro IANA de **2026-08-08**, com hashes e gerador reproduzível. Verifica estrutura RFC 5646, subtags registrados, prefixos extlang e variantes/extensões duplicadas. Aceita tags antigas mantidas e faixas privadas. Recomendações de prefixos de variante não são regras obrigatórias. Namespaces e estrutura de extensões são validados; semântica CLDR e significado de uso privado ficam fora da API. Não exige ICU nem download em execução. Veja [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) e a [definição HTML de lang](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes).

Pro **2.38+** trata `lang` ausente ou vazio como desconhecido/ausente; bytes malformados geram `html_lang_invalid`. A comparação de escrita usa um subtag Script real ou o padrão registrado da IANA. Conteúdo privado, extensões e línguas desconhecidas não implicam escrita latina. Grupos não suportados ficam sem julgamento. Não é um detector completo de língua.

A reciprocidade usa códigos válidos de autorreferência da fonte ou, se ausentes, sua língua HTML válida compatível com o Google. Uma referência à URL sob outro idioma não basta. Se o código da fonte não puder ser determinado, permanece `hreflang_target_unverified`. URLs de destino repetidas são buscadas uma vez dentro dos limites existentes. Proteções SSRF, recusa de redirecionamentos e tratamento de resultados não verificáveis continuam ativos.

## `inLanguage` no grafo de schemas {#inlanguage-in-the-schema-graph}

`WebPage` recebe `inLanguage` da língua resolvida (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` usa a locale de `seo_meta`. `WebSite` lê seus idiomas da configuração:

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Buscadores regionais {#regional-search-engines}

O catálogo de `seo:robots-txt` inclui Yandex, Baidu, Naver (`Yeti`), Seznam, Sogou, 360, Cốc Cốc e DuckDuckGo. Com finalidade `search_engine`, são permitidos por padrão e seguem políticas e exceções individuais:

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` e `match()` continuam limitados à IA. Solicite buscadores com `searchEngines()`, `all(true)` ou `match($ua, true)` para manter o significado do registro e dos contadores de IA. Veja [rastreadores](/pt-BR/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
Suporte ao rastreador e à verificação não garante descoberta, indexação ou posições no Baidu.
:::

## Verificação do site {#site-verification}

Tokens de propriedade geram tags meta em todas as páginas, inclusive a raiz onde Yandex, Baidu e Naver procuram o comprovante. Chaves vazias não produzem saída:

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

O valor pode ser uma lista de tokens, por exemplo para vários proprietários de uma propriedade do Google.

## Imagens OG em diferentes escritas {#og-images-in-every-script}

A fonte incluída cobre latim, cirílico e grego. Outras escritas exigem fontes no host que executa `seo:og-images`; uma fonte CJK pode superar 16 MB. Os templates usam `seo.og_image.font_stack`, priorizando a família Noto CJK do idioma para escolher formas nacionais de caracteres Han. O comando avisa uma vez por escrita quando não detecta uma fonte apropriada:

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Em Debian/Ubuntu: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. Veja [imagens OG](/pt-BR/guide/og-image#fonts-and-non-latin-scripts).

## `llms.txt` em vários idiomas {#llms-txt-in-several-languages}

Com `seo.llms_txt.alternates`, páginas traduzidas acrescentam `Also in: [it](…), [de](…)` à entrada. A lista segue as políticas hreflang, sem `x-default` nem a própria página. Vem desativado por padrão.

## URLs Unicode {#unicode-urls}

O Rankbeam não cria slugs nem reescreve URLs: `/città/` e `/検索` são preservados. A auditoria aceita hosts IDN (`https://münchen.example/`) e caminhos Unicode ou codificados em porcentagem. `Rankbeam\Seo\I18n\Url::isValid()` substitui o `FILTER_VALIDATE_URL` do PHP, limitado a ASCII. Mantenha uma forma por URL para canonical, hreflang e sitemap coincidirem byte a byte.

## Idiomas suportados e alcance {#which-languages-are-supported-and-what-that-means}

Os pacotes oferecem textos e seleção de motores para as 17 locales abaixo. A tabela descreve cobertura técnica, não aprovação editorial nativa nem renderização garantida em um host sem configuração. Análise de palavras japonesas e chinesas exige ICU operacional; sem ele, os julgamentos afetados são ignorados. Renderização não latina depende de fontes adequadas.

`tests/Feature/I18n/SupportedLanguagesTest.php` fixa locales, hreflang e orçamentos no núcleo. `tests/Feature/OnPage/LanguageSupportMatrixTest.php` do Pro fixa os motores de análise.

| Idioma | Locale | Título / descrição | Contagem de palavras | Correspondência de palavras-chave | Legibilidade |
|---|---|---|---|---|---|
| Inglês | `en` | 60 / 160 | Espaços | Snowball | Flesch Reading Ease |
| Italiano | `it` | 60 / 160 | Espaços | Snowball | Gulpease |
| Alemão | `de` | 60 / 160 | Espaços | Snowball | Wiener Sachtextformel |
| Francês | `fr` | 60 / 160 | Espaços | Snowball | Kandel-Moles |
| Espanhol | `es` | 60 / 160 | Espaços | Snowball | Fernández-Huerta |
| Português do Brasil | `pt_BR` | 60 / 160 | Espaços | Snowball | Martins |
| Holandês | `nl` | 60 / 160 | Espaços | Snowball | Flesch-Douma |
| Turco | `tr` | 60 / 160 | Espaços | Snowball | Ateşman |
| Russo | `ru` | 60 / 160 | Espaços | Snowball | Oborneva |
| Polonês | `pl` | 60 / 160 | Espaços | Snowball | Pisarek |
| Japonês | `ja` | 30 / 80 | Dicionário ICU | Exata com case folding | Heurística, **sem score** |
| Chinês simplificado | `zh_CN` | 30 / 80 | Dicionário ICU | Exata com case folding | Heurística, **sem score** |
| Chinês tradicional | `zh_TW` | 30 / 80 | Dicionário ICU | Exata com case folding | Heurística, **sem score** |
| Coreano | `ko` | 30 / 80 | Espaços | Exata com case folding | Heurística, **sem score** |
| Grego | `el` | 60 / 160 | Espaços | Snowball | LIX |
| Ucraniano | `uk` | 60 / 160 | Espaços | Exata com case folding | LIX |
| Tcheco | `cs` | 60 / 160 | Espaços | Snowball | LIX |

Três limites acompanham a tabela:

- **Snowball é incluído desde Pro 2.37.** Doze línguas usam algoritmos fixados em 3.1.1, sem pacotes opcionais. Ucraniano e CJK usam identidade, sem regras de sufixo inventadas. A identidade pode deixar passar flexões; stemming pode aproximar palavras diferentes. Veja [motores e migração (EN)](/pro/on-page-checklist#upgrading-from-pro-2-36).
- **“Heurística, sem score” difere de LIX.** Japonês, chinês e coreano recebem um nível orientativo com base no tamanho de frases e proporção de kanji, com score `null`. Grego, ucraniano e tcheco usam LIX porque não há fórmula específica implementada. LIX dispensa sílabas, mas seus limites não são calibrados para toda língua. Todas as fórmulas usam entradas estimadas; veja [limites estatísticos (EN)](/pro/on-page-checklist#text-statistics-and-api-limits).
- **Traduções dos pacotes são primeiras versões**, salvo revisão nativa registrada em `TRANSLATING.md`. Textos italianos têm revisão creditada; os demais aguardam revisor. Isso não aprova as traduções da documentação.

Locales fora da lista podem usar textos ingleses, limites padrão, correspondência por identidade e LIX ou heurísticas. Esse fallback não é suporte linguístico validado. O bloco `analysis` identifica escrita, segmentador, stemmer e legibilidade; examine disponibilidade e julgamentos ignorados também.

### Alcançar buscadores relevantes localmente {#reaching-the-search-engines-that-matter-locally}

A cobertura técnica inclui robôs e verificação: Naver para a Coreia, Seznam para a Tchéquia e Yandex para sites ucranianos ou russos, entre outros. Veja [buscadores regionais](#regional-search-engines) e [verificação](#site-verification).

## O que os outros pacotes acrescentam {#what-the-other-packages-add}

- **laravel-seo-filament** usa a mesma política em contadores e prévia SERP. Desde 1.9, edita [uma linha `seo_meta` por idioma](/pt-BR/guide/filament#several-languages), com abas e indicadores próprios ou seguindo o seletor de um plugin.
- **laravel-seo-pro** usa a política em `title_length`, `description_length` e prompts de IA. A análise inclui ICU para chinês, japonês e tailandês, Snowball, correspondência com `CaseFolder`, fórmulas publicadas com entradas estimadas para dez línguas, heurísticas CJK identificadas, LIX para grego/ucraniano/tcheco, stop words para 16 línguas, `html lang`, reciprocidade hreflang, prompts com idioma e relatórios Chrome para escritas não renderizadas pelo dompdf. Veja [checklist (EN)](/pro/on-page-checklist#keyword-matching), [problemas (EN)](/pt-BR/pro/scan-issues), [assistência IA (EN)](/pro/ai-assist#output-language) e [relatórios (EN)](/pro/reports#reports-in-every-script-browsershot-renderer).
