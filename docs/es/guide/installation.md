---
description: "Instala rankbeam/laravel-seo con Composer, publica la configuración y prepara las tablas en Laravel 11, 12 o 13."
---

# Instalación {#installation}

## Requisitos {#requirements}

- PHP 8.2 o superior; PHP 8.3 o superior para Laravel 13.
- Laravel 11, 12 o 13.
- `spatie/laravel-sitemap` ^7.0 o ^8.0: opcional, necesario solo para generar sitemaps.

## Instalar el paquete {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

El proveedor de servicios y la fachada `SEO` se descubren automáticamente. Las dos migraciones crean las únicas tablas propias del paquete:

| Tabla | Uso |
|---|---|
| `seo_meta` | Valores explícitos por modelo, relación polimórfica e idioma |
| `seo_defaults` | Valores predeterminados globales, por tipo de modelo y por ruta |

## Opcional: sitemaps {#optional-sitemaps}

La generación utiliza [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

Consulta las fuentes y opciones en la [guía del registro de sitemaps](/es/guide/sitemaps).

## Actualizar desde la versión 1 {#upgrading-from-v1}

Si usabas `fibonoir/laravel-seo` v1, lee primero [Actualizar desde v1](/es/guide/upgrade-from-v1). Han cambiado el proveedor, el espacio de nombres y la API. Los archivos publicados por v1 pueden entrar en conflicto con la configuración v2.

## Paquetes complementarios {#companion-packages}

| Paquete | Qué añade | Licencia |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Sección SEO para formularios de recursos Filament 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/es/pro/installation) | Análisis en cola, redirecciones y seguimiento de errores 404 en cualquier aplicación Laravel; panel Filament opcional | Comercial |
