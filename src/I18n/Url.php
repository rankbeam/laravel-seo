<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

/**
 * URL validation that accepts internationalised URLs.
 *
 * `filter_var(FILTER_VALIDATE_URL)` rejects every non-ASCII byte, so a
 * perfectly good canonical such as `https://münchen.example/straße` or
 * `https://example.jp/検索` — the natural form on a site that keeps Unicode
 * paths — was reported as *invalid*. This validator understands the two forms
 * an internationalised URL takes: an IDN host (validated through its
 * Punycode form when ext-intl is present, structurally otherwise) and a path,
 * query or fragment carrying either raw Unicode or percent-encoding.
 */
final class Url
{
    /**
     * Whether $url is an absolute http(s) URL a search engine can follow.
     */
    public static function isValid(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        $url = trim($url);

        if ($url === '' || preg_match('/[\s\x00-\x1F\x7F]/u', $url) === 1) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        return self::isValidHost($parts['host']);
    }

    /**
     * Whether a host is a syntactically valid hostname, IDN or IP literal.
     */
    public static function isValidHost(string $host): bool
    {
        $host = rtrim($host, '.');

        if ($host === '') {
            return false;
        }

        // IPv6 literal (parse_url keeps the brackets) or IPv4.
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            return filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        $ascii = self::toAscii($host);

        if ($ascii === null) {
            return false;
        }

        if (strlen($ascii) > 253) {
            return false;
        }

        foreach (explode('.', $ascii) as $label) {
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * The Punycode (ASCII) form of a host: via ext-intl when available, else
     * a structural check that each Unicode label is made of letters, marks,
     * digits and hyphens (returned with a placeholder ASCII label so the
     * length and label rules above still apply).
     */
    private static function toAscii(string $host): ?string
    {
        if (preg_match('/^[\x21-\x7E]+$/', $host) === 1) {
            return $host;
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

            return $ascii === false ? null : $ascii;
        }

        $labels = [];

        foreach (explode('.', $host) as $label) {
            if ($label === '' || preg_match('/^[\p{L}\p{M}\p{N}-]+$/u', $label) !== 1) {
                return null;
            }

            $labels[] = preg_match('/^[\x21-\x7E]+$/', $label) === 1 ? $label : 'xn--'.str_repeat('a', min(59, max(1, mb_strlen($label))));
        }

        return implode('.', $labels);
    }
}
