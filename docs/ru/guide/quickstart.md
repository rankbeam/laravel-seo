---
description: "Установите Rankbeam, добавьте трейт HasSEO в существующую модель, сохраните поля SEO и проверьте теги, сформированные в Blade."
---

# Быстрый старт {#quickstart}

Начните с существующего приложения Laravel 11, 12 или 13 с работающей базой данных.
Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5. Ядро бесплатно и распространяется под лицензией MIT; учётная запись и лицензия Pro не требуются.

## Установка {#install}

Выполните эти команды из каталога приложения:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Сервис-провайдер обнаруживается автоматически. Миграция создаёт таблицы SEO, но не модели контента вашего приложения.

## Перед началом примера {#before-the-example}

В следующих шагах предполагается, что у вас уже есть модель `Post`, сохранённая запись и маршрут `posts.show`, чьё Blade-представление получает эту запись как `$post`. Подставьте названия из своего приложения. Руководство добавляет SEO на существующую страницу, а не создаёт блог.

В файле `.env` задайте для `APP_URL` публичный origin сайта. Для других способов вывода используйте [руководство по Inertia и JSON](/ru/guide/inertia-json) или [руководство по Livewire](/ru/guide/livewire).

## 1. Добавьте трейт в модель {#_1-add-the-trait-to-a-model}

```php
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

`getUrlForSEO()` сообщает резолверу канонический URL модели: он используется для канонических ссылок, `og:url` и записей карты сайта.

## 2. Сформируйте head {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` выводит заголовок, метаописание, каноническую ссылку, robots, теги Open Graph и Twitter Card, а также JSON-LD, прикреплённый к полученным данным. Пока явные значения не сохранены, всё берётся из вычисляемых резервных значений (атрибутов самой записи) и настроенных значений по умолчанию — см. [приоритеты резолвера](/ru/concepts/resolver-precedence).

## 3. Задайте явные значения {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Явные значения имеют приоритет над всеми резервными слоями. Для переведённых метаданных передайте локаль: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Заполняете модели через сидеры?
Стандартный `DatabaseSeeder` в Laravel использует трейт `WithoutModelEvents`, который без уведомления отключает обработчик автоматического создания в `HasSEO`. Удалите этот трейт или явно вызывайте `saveSEO()` в сидерах.
:::

## 4. Проверьте результат {#_4-verify-the-result}

Откройте публичную страницу записи и выберите **просмотр исходного кода страницы**. В `<head>` проверьте, что заголовок содержит `Custom SEO Title`, описание равно `Custom meta description`, а каноническая ссылка ведёт на публичный URL записи. После заголовка может следовать настроенный суффикс.

Выводите `@seo($post)` один раз на страницу. Если шаблон уже выводит заголовок или метатеги, замените их, чтобы избежать дубликатов. Если значение не соответствует ожиданиям, выясните его источник с помощью [руководства по разбору итоговых значений](/ru/guide/explain).

## 5. Добавьте карту сайта (необязательно) {#_5-add-a-sitemap-optional}

```php
// e.g. in AppServiceProvider::boot()
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

SEO::sitemaps()->register('posts', Post::class);
```

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

Теперь `/sitemap.xml` отдаёт созданный индекс. Все параметры описаны в [руководстве по реестру карт сайта](/ru/guide/sitemaps).

## Что дальше {#where-to-go-next}

- [Приоритеты резолвера](/ru/concepts/resolver-precedence) — как выбираются значения
- [Руководство по Blade](/ru/guide/blade) — все семь директив
- [Inertia и JSON](/ru/guide/inertia-json) — вывод для отдельного фронтенда
- [Граф schema.org](/ru/guide/schema) — связанный JSON-LD
- [Поля Filament](/ru/guide/filament) — интерфейс администратора в две строки

