---
description: Установите rankbeam/laravel-seo через Composer, опубликуйте конфигурацию и выполните миграции — требования и настройка для Laravel 11, 12 и 13.
---

# Установка {#installation}

## Требования {#requirements}

- Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 или 13
- `spatie/laravel-sitemap` ^7.0 или ^8.0 — необязательно, требуется только для создания карт сайта

## Установка пакета {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

На этом установка завершена. Сервис-провайдер и фасад `SEO` обнаруживаются автоматически; две миграции создают единственные таблицы, которыми владеет пакет:

| Таблица | Назначение |
|---|---|
| `seo_meta` | Явные значения для отдельных моделей (полиморфная связь + локаль) |
| `seo_defaults` | Значения по умолчанию для всего сайта, типов моделей и маршрутов |

## Необязательно: карты сайта {#optional-sitemaps}

Для создания карт сайта используется [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

Источники и процесс создания описаны в [руководстве по реестру карт сайта](/ru/guide/sitemaps).

## Обновляетесь с v1? {#upgrading-from-v1}

Если приложение использовало `fibonoir/laravel-seo` v1, сначала прочитайте [руководство по обновлению с v1](/ru/guide/upgrade-from-v1): изменились вендор, пространство имён и состав API пакета, а опубликованные в v1 файлы могут конфликтовать с конфигурацией v2.

## Дополнительные пакеты {#companion-packages}

| Пакет | Что добавляет | Лицензия |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Раздел SEO в формах ресурсов Filament 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/ru/pro/installation) | Сканирование сайта через очередь, менеджер перенаправлений и монитор 404 — для любого приложения Laravel, с необязательной панелью Filament | Коммерческая |

