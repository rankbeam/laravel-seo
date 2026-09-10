---
description: "Ocorrências da auditoria, avisos do editor e textos do Filament seguem o idioma da aplicação. Publique os arquivos de idioma para personalizar textos ou contribuir com uma tradução."
---

# Traduções {#translations}

Os textos apresentados pelos pacotes — ocorrências da auditoria, avisos abaixo dos campos Filament, rótulos, prévias e relatórios — são mensagens de idioma do Laravel. Os pacotes seguem `app()->getLocale()`: um painel em italiano mostra textos em italiano, sem configuração adicional.

Os **códigos** de ocorrências e avisos, como `missing_title` e `title_too_long`, são estáveis e nunca traduzidos. Apenas a mensagem destinada à pessoa muda de idioma.

O pacote inclui inglês e italiano, cujos textos anteriores foram revisados e cujas alterações precisam de nova revisão, além de versões iniciais em alemão, francês, espanhol, português brasileiro, neerlandês, turco, russo e polonês, no grupo Tier 1. Desde Core 3.16, Filament 1.10 e Pro 2.35, inclui também japonês, chinês simplificado (`zh_CN`), chinês tradicional (`zh_TW`), coreano, grego, ucraniano e tcheco, no grupo Tier 2. O status exato por idioma está em [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md). A revisão por um falante nativo é o critério do projeto para transformar uma versão inicial em idioma com suporte editorial. Esse status dos textos dos pacotes é separado da revisão desta documentação.

## Personalizar uma mensagem {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Edite `lang/vendor/seo/{locale}/seo.php` e as pastas correspondentes dos outros pacotes. As chaves mantidas no arquivo substituem os textos originais; as demais usam o arquivo do pacote e, depois, o inglês como alternativa.

## Contribuir com um idioma {#contribute-a-language}

Copie o arquivo `en` para seu idioma, traduza os valores e preserve todos os `:placeholder`. Execute os testes: a verificação de paridade rejeita chaves ausentes ou excedentes, valores vazios e marcadores perdidos. Abra uma pull request. As regras e o glossário estão em [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md).

## O que permanece sem tradução por opção {#what-is-not-translated-on-purpose}

A apresentação da CLI usa inglês por padrão. Configure `seo.cli_locale` / `SEO_CLI_LOCALE` ou passe `--display-locale=it` para traduzir mensagens compatíveis e resumos de auditoria. Pro tem seu próprio ajuste `seo-pro.cli_locale`. O idioma de exibição é independente do idioma de conteúdo escolhido por `--locale`.

- A ajuda dos comandos, os diagnósticos de manutenção e a saída de `seo:explain` permanecem em inglês. PASS/WARN/FAIL continuam estáveis.
- O HTML gerado, incluindo `<meta>` e JSON-LD, usa o idioma do seu conteúdo, não o dos textos do pacote.
- Códigos de ocorrências, chaves JSON e códigos de estado permanecem identificadores estáveis. Rótulos destinados a pessoas podem ser traduzidos em `--json`; integrações devem consumir chaves e códigos.

## O idioma do seu conteúdo {#the-other-half-your-content-s-language}

Esta página trata dos textos apresentados pelo pacote. A interpretação do idioma do conteúdo — limites de título por escrita, truncamento, maiúsculas, políticas hreflang, `inLanguage`, buscadores regionais e fontes de imagens OG — está em [conteúdo multilíngue](/pt-BR/guide/multilingual).
