---
description: "Execute a demonstração Rankbeam com os pacotes publicados, sem repositórios locais, para ver metadados, grafo JSON-LD e sitemap em páginas reais."
---

# Executar a demonstração {#run-the-demo}

A demonstração permite ver o Rankbeam em páginas reais antes de integrá-lo à sua aplicação. É uma aplicação Laravel com dados de exemplo que instala os pacotes **publicados**, sem repositórios do tipo path ou checkouts vizinhos. Ela renderiza páginas com metadados SEO, grafo JSON-LD e sitemap. Com uma licença, também executa a [auditoria técnica de Pro](/pt-BR/pro/scan-issues).

## Um comando para o núcleo gratuito {#one-command-free-core}

A demonstração é distribuída como imagem Docker no repositório [`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples):

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

Abra `http://localhost:8080`. Veja o código-fonte de qualquer página para conferir o `<head>` resolvido e acesse `/sitemap.xml` para consultar o sitemap. Tudo nesse modo pertence ao núcleo gratuito MIT, instalado pelo Packagist.

## Com Pro: a auditoria {#with-pro-the-audit}

Pro é licenciado por projeto e instalado pelo repositório Composer privado. Passe a licença por `COMPOSER_AUTH`, um segredo de build que não é gravado em uma camada da imagem, e construa com a opção Pro:

```bash
export COMPOSER_AUTH='{"http-basic":{"laravel-seo-pro.composer.sh":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

Na inicialização, a demonstração executa [`seo:doctor`](/pt-BR/pro/headless#setup-health-check) e um primeiro `seo-pro:scan` nas páginas de exemplo. O diagnóstico, o resumo da varredura e a [pontuação de 0 a 100](/pt-BR/pro/scoring) aparecem nos logs do compose.

## Ver o fluxo de Pro {#see-the-pro-workflow}

O [passo a passo de varredura, correção e relatório](/pt-BR/pro/walkthrough) mostra a demonstração Merchant em execução: uma varredura real, detalhes da ocorrência, descrição salva no Filament, nova varredura e PDF para download. O conteúdo é identificado como exemplo, e a comparação usa duas varreduras recém-executadas.

Ainda não há uma demonstração interativa pública hospedada. Use Docker para executar localmente. O [README da demonstração](https://github.com/rankbeam/rankbeam-examples/tree/main/demo) explica a configuração e a troca entre pacotes publicados e locais.

::: tip Já tem uma aplicação?
Você pode seguir diretamente para o [início rápido](/pt-BR/guide/quickstart), da instalação à renderização completa do `<head>` em cerca de cinco minutos, conforme o ambiente.
:::
