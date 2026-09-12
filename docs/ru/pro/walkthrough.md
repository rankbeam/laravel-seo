---
description: "Проследите реальное сканирование Rankbeam Pro: изучите отсутствующее описание, сохраните исправление в Filament, повторите сканирование и скачайте созданный образец PDF-отчёта."
---

# От сканирования до проверенного исправления {#from-a-scan-to-a-verified-fix}

Сканирование обнаружило отсутствующее описание у демонстрационной статьи. Мы добавили описание в Filament, просканировали снова и создали отчёт, показывающий исправление.

Это снимки работающего локального демоприложения Merchant от 9 сентября 2026 года. Контент — демонстрационные данные, добавленные сидером; оба сканирования и отчёт созданы для этого руководства. Историческая динамика заранее не подставлялась. Приложение использует Laravel 12 и Filament 4 с ядром Rankbeam, бесплатным редактором и движком Pro.

**[Скачать созданный отчёт (PDF, 98 КБ)](/pro-walkthrough/merchant-demo-report.pdf)**

## Сканирование зарегистрированных страниц {#scan-the-registered-pages}

После [установки Pro](/ru/pro/installation) и регистрации целей сканирования выполните:

```bash
php artisan seo-pro:scan --sync
```

В демоприложении зарегистрированы 18 записей контента и три маршрута. Первое сканирование завершилось без ошибок: обработана 21 цель и обнаружено 20 проблем: шесть предупреждений и 14 замечаний.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="Первое завершённое сканирование: 21 цель, 20 проблем, шесть предупреждений и 14 замечаний." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Снимки сделаны в разрешении 2×. Откройте изображение, чтобы рассмотреть его в полном размере.*

## Изучение одной проблемы {#inspect-one-issue}

В **SEO Dashboard** откройте **Page issues** рядом с нужной строкой. Для статьи “Behind the Scenes: Our Product Photography” результат указывает на отсутствующее `description`, URL страницы и обнаружившее проблему сканирование.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Диалог Page issues показывает Post 5, его URL и отсутствующее поле описания." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Сохранение описания {#save-the-description}

Откройте статью в **Posts**, заполните **SEO description** и сохраните. [Бесплатный редактор Filament](/ru/guide/filament) показывает введённый текст в поисковом превью и обозначает его источник как **Manual**. В этом примере описание содержит 142 символа; заголовок по-прежнему берётся из статьи.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="Сохранённое SEO-описание и счётчик: 142 символа." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="Живое превью использует введённое описание с пометкой Manual." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Сохранение поля и проверка исправления — отдельные шаги. Оценка обновится после следующего сканирования. Без Filament сохраните то же значение методом `saveSEO()` своей модели.

## Повторное сканирование и проверка изменений {#rescan-and-check-what-changed}

Выполните ту же команду ещё раз:

```bash
php artisan seo-pro:scan --sync
```

Теперь панель отмечает именно эту проблему как **Fixed**. Остальные 19 проблем остаются открытыми.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Сохранённое сравнение сканирований: ноль новых проблем, ноль регрессий, одна исправленная и 19 открытых." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Проверка | До | После |
|---|---|---|
| Завершённые цели | 21 | 21 |
| Открытые проблемы | 20 | 19 |
| Предупреждения | 6 | 5 |
| Замечания | 14 | 14 |
| Средняя техническая SEO-оценка | 92 | 93 |

[Оценка](/ru/pro/scoring) отражает технические проверки Rankbeam. Она не измеряет посещаемость, позиции в поиске или попадание в ответы ИИ. Успешная проверка описания также не гарантирует, что поисковая система покажет именно его.

## Создание отчёта {#generate-the-report}

Для этой демонстрации мы создали исходный отчёт **до** редактирования статьи, а второй — после повторного сканирования:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

Второй PDF показывает **одну исправленную**, **ноль новых** и **19 открытых** проблем. Динамика включает только два сканирования выше. Search Console и журнал ИИ-роботов были отключены, поэтому в этих разделах указано, что данные недоступны.

[![Первая страница созданного образца отчёта: оценка 93, одна исправленная проблема и 19 открытых.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

Первый отчёт задаёт базу сравнения. Если создать только один отчёт после исправления страницы, он не сможет показать изменение относительно предыдущего отчёта. Для предварительного просмотра, который не должен сдвигать базу сравнения, используйте `--no-store`.

В примере используется рендерер Browsershot. Требования к рендереру, оформление под своим брендом и доставку по расписанию см. в [отчётах под своим брендом](/ru/pro/reports).

## Повторите в своём приложении {#run-it-on-your-own-app}

Начните с [установки Pro](/ru/pro/installation), затем просканируйте страницу, чей вывод можете проверить. Pro работает и [без Filament](/ru/pro/headless). Чтобы сначала попробовать бесплатный вывод метаданных, используйте [демо Docker](/ru/guide/demo).

