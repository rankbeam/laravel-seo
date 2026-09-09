<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

/**
 * Strict RFC 5646 structure and registered subtags; never repairs served bytes.
 * Extension namespaces are registered; their payloads are structurally checked,
 * not interpreted as CLDR options. Variant Prefix recommendations are not gates.
 */
final class LanguageTag
{
    public static function isValid(string $tag): bool
    {
        if (preg_match('/^[a-z0-9]{1,8}(?:-[a-z0-9]{1,8})*$/iD', $tag) !== 1) {
            return false;
        }

        $tag = strtolower($tag);
        if (self::registered('grandfathered', $tag)) {
            return true;
        }
        $parts = explode('-', $tag);
        if ($parts[0] === 'x') {
            return count($parts) > 1;
        }
        $language = array_shift($parts);
        if (! self::registered('language', $language)) {
            return false;
        }
        // The registry allows one extlang with its registered primary prefix.
        if (isset($parts[0]) && preg_match('/^[a-z]{3}$/D', $parts[0]) === 1) {
            $prefixes = self::data()['extlang'][array_shift($parts)] ?? [];
            if (! in_array($language, $prefixes, true)) {
                return false;
            }
        }
        if (isset($parts[0]) && preg_match('/^[a-z]{4}$/D', $parts[0]) === 1) {
            if (! self::registered('script', array_shift($parts))) {
                return false;
            }
        }
        if (isset($parts[0]) && preg_match('/^(?:[a-z]{2}|[0-9]{3})$/D', $parts[0]) === 1) {
            if (! self::registered('region', array_shift($parts))) {
                return false;
            }
        }
        $variants = [];
        while (isset($parts[0]) && preg_match('/^(?:[a-z0-9]{5,8}|[0-9][a-z0-9]{3})$/D', $parts[0]) === 1) {
            $variant = array_shift($parts);
            if (isset($variants[$variant]) || ! self::registered('variant', $variant)) {
                return false;
            }
            $variants[$variant] = true;
        }
        $extensions = [];
        while ($parts !== []) {
            $singleton = array_shift($parts);
            if ($singleton === 'x') {
                return $parts !== [];
            }
            if (! in_array($singleton, self::data()['extensions'], true) || isset($extensions[$singleton])) {
                return false;
            }
            $extensions[$singleton] = true;
            $count = 0;
            while (isset($parts[0]) && strlen($parts[0]) >= 2) {
                array_shift($parts);
                $count++;
            }
            if ($count === 0) {
                return false;
            }
        }

        return true;
    }

    /** Empty HTML lang means unknown; x-default is rejected by Rankbeam policy. */
    public static function isValidHtml(string $tag): bool
    {
        return $tag === '' || (strcasecmp($tag, Hreflang::X_DEFAULT) !== 0 && self::isValid($tag));
    }

    /** Actual script subtag or registered default; no guesses from private data. */
    public static function script(string $tag): ?string
    {
        if (! self::isValid($tag)) {
            return null;
        }
        $parts = explode('-', strtolower(self::fromLocale($tag)));
        $language = array_shift($parts);
        if ($language === 'x' || ! self::registered('language', $language)) {
            return null;
        }
        if (isset($parts[0], self::data()['extlang'][$parts[0]])) {
            $language = array_shift($parts);
        }
        if (isset($parts[0]) && preg_match('/^[a-z]{4}$/D', $parts[0]) === 1) {
            return ucfirst($parts[0]);
        }

        return self::data()['suppress_script'][$language] ?? null;
    }

    /** Tolerant application boundary: separators/case and explicit IANA aliases. */
    public static function fromLocale(string $locale): string
    {
        $tag = strtolower(str_replace('_', '-', trim($locale)));
        $preferred = self::data()['preferred'];
        $tag = $preferred['tag'][$tag] ?? $tag;
        $parts = explode('-', $tag);
        $parts[0] = $preferred['language'][$parts[0]] ?? $parts[0];
        $privateOrExtension = $parts[0] === 'x';
        foreach ($parts as $i => $part) {
            if ($i === 0) {
                continue;
            }
            if (strlen($part) === 1) {
                $privateOrExtension = true;
            }
            if ($privateOrExtension) {
                continue;
            }
            if (preg_match('/^[a-z]{4}$/D', $part) === 1) {
                $parts[$i] = ucfirst($preferred['script'][$part] ?? $part);
            } elseif (preg_match('/^[a-z]{2}$/D', $part) === 1) {
                $parts[$i] = strtoupper($preferred['region'][$part] ?? $part);
            } else {
                $parts[$i] = $preferred['variant'][$part] ?? $part;
            }
        }

        return implode('-', $parts);
    }

    /** Registry membership includes RFC 5646 private-use ranges. */
    public static function registered(string $type, string $subtag): bool
    {
        static $sets = [];
        $sets[$type] ??= array_fill_keys(explode(' ', self::data()[$type]), true);
        $subtag = strtolower($subtag);
        if (isset($sets[$type][$subtag])) {
            return true;
        }
        $ranges = match ($type) {
            'language' => [['qaa', 'qtz']],
            'script' => [['qaaa', 'qabx']],
            'region' => [['qm', 'qz'], ['xa', 'xz']],
            default => [],
        };
        foreach ($ranges as [$first, $last]) {
            if (strlen($subtag) === strlen($first) && preg_match('/^[a-z]+$/D', $subtag) === 1 && strcmp($subtag, $first) >= 0 && strcmp($subtag, $last) <= 0) {
                return true;
            }
        }

        return false;
    }

    private static function data(): array
    {
        static $data;

        return $data ??= json_decode(file_get_contents(__DIR__.'/../../resources/data/language-registry.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
