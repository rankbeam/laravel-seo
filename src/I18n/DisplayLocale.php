<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

use Closure;

/** Scope translated presentation without changing the application's content locale. */
final class DisplayLocale
{
    public static function normalize(string $locale): string
    {
        return str_replace('-', '_', LanguageTag::fromLocale($locale));
    }

    /** @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(string $locale, Closure $callback): mixed
    {
        $translator = app('translator');
        $previous = $translator->getLocale();
        try {
            $translator->setLocale(self::normalize($locale));

            return $callback();
        } finally {
            $translator->setLocale($previous);
        }
    }
}
