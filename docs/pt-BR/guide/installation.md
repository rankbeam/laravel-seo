---
description: "Instale rankbeam/laravel-seo com Composer, publique a configuração e prepare as tabelas no Laravel 11, 12 ou 13."
---

# Instalação {#installation}

## Requisitos {#requirements}

- Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 ou 13.
- `spatie/laravel-sitemap` ^7.0 ou ^8.0: opcional, necessário apenas para gerar sitemaps.

## Instalar o pacote {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

O service provider e a facade `SEO` são descobertos automaticamente. As duas migrations criam as únicas tabelas próprias do pacote:

| Tabela | Finalidade |
|---|---|
| `seo_meta` | Valores explícitos por modelo, relação polimórfica e idioma |
| `seo_defaults` | Padrões globais, por tipo de modelo e por rota |

## Opcional: sitemaps {#optional-sitemaps}

A geração usa [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

Veja fontes e opções no [guia do registro de sitemaps](/pt-BR/guide/sitemaps).

## Atualizar a partir da v1 {#upgrading-from-v1}

Se a aplicação usava `fibonoir/laravel-seo` v1, leia primeiro [Atualizar a partir da v1](/pt-BR/guide/upgrade-from-v1). O fornecedor, o namespace e a API mudaram. Arquivos publicados pela v1 podem conflitar com a configuração v2.

## Pacotes complementares {#companion-packages}

| Pacote | O que acrescenta | Licença |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Seção SEO em formulários de recursos do Filament 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/pt-BR/pro/installation) | Análises em fila, redirecionamentos e monitor de erros 404 em qualquer aplicação Laravel; painel Filament opcional | Comercial |
