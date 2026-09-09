<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

use Closure;
use Illuminate\Database\Eloquent\Model;

/** Read content hooks in a locale without changing the caller's model or UI. */
final class ModelLocale
{
    public static function forModel(Model $model, ?string $locale = null): string
    {
        if ($locale !== null) {
            return $locale;
        }

        // A separate translation model can declare its own language in seoData().
        $previous = app()->getLocale();
        $previousDisplay = app('translator')->getLocale();
        try {
            $copy = clone $model;

            return (method_exists($copy, 'seoData') ? $copy->seoData()->locale : null) ?: $previous;
        } catch (\Throwable) {
            // Consumers such as the checklist can still inspect raw metadata
            // when a custom resolver fails to provide a language.
            return $previous;
        } finally {
            app()->setLocale($previous);
            app('translator')->setLocale($previousDisplay);
        }
    }

    /**
     * The callback must finish reading content before returning: do not return
     * lazy iterators or closures that depend on the temporary application locale.
     * Models using Spatie's translation API also get an isolated instance locale.
     *
     * @template T
     *
     * @param  Closure(Model): T  $callback
     * @return T
     */
    public static function run(Model $model, string $locale, Closure $callback, bool $withMetadata = true): mixed
    {
        $previous = app()->getLocale();
        $previousDisplay = app('translator')->getLocale();
        $localized = clone $model;

        try {
            app()->setLocale($locale);

            if (method_exists($localized, 'getTranslatableAttributes') && method_exists($localized, 'setLocale')) {
                $localized->setLocale($locale);
            }

            if ($withMetadata && method_exists($localized, 'seoMetaForLocale')) {
                $loaded = $localized->getRelations()['seoMeta'] ?? null;
                if ($loaded === null || $loaded->locale !== $locale) {
                    $localized->setRelation('seoMeta', $localized->seoMetaForLocale($locale)->getResults());
                }
            } else {
                $localized->unsetRelation('seoMeta');
            }

            return $callback($localized);
        } finally {
            app()->setLocale($previous);
            app('translator')->setLocale($previousDisplay);
        }
    }
}
