<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

/**
 * Google hreflang compatibility and shared application alternate policies.
 * normalize()/fromLocale() adapt Laravel locales; isValid()/parse() validate
 * served values without repairing them. Use LanguageTag for HTML/BCP47.
 */
final class Hreflang
{
    public const X_DEFAULT = 'x-default';

    /** ISO 639-1 language identifiers for the Google hreflang contract. */
    private const LANGUAGES = 'aa ab ae af ak am an ar as av ay az ba be bg bh bi bm bn bo br bs ca ce ch co cr cs cu cv cy da de dv dz ee el en eo es et eu fa ff fi fj fo fr fy ga gd gl gn gu gv ha he hi ho hr ht hu hy hz ia id ie ig ii ik io is it iu ja jv ka kg ki kj kk kl km kn ko kr ks ku kv kw ky la lb lg li ln lo lt lu lv mg mh mi mk ml mn mr ms mt my na nb nd ne ng nl nn no nr nv ny oc oj om or os pa pi pl ps pt qu rm rn ro ru rw sa sc sd se sg si sk sl sm sn so sq sr ss st su sv sw ta te tg th ti tk tl tn to tr ts tt tw ty ug uk ur uz ve vi vo wa wo xh yi yo za zh zu';

    /**
     * ISO 3166-1 alpha-2 region codes.
     */
    private const REGIONS = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';

    /** Adapt a Laravel locale without concealing repeated/empty separators. */
    public static function normalize(string $code): string
    {
        return LanguageTag::fromLocale($code);
    }

    /**
     * The hreflang form of a Laravel locale (`it_IT` → `it-IT`).
     */
    public static function fromLocale(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        $normalized = self::normalize($locale);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Whether an emitted code meets Google's documented hreflang contract.
     */
    public static function isValid(string $code): bool
    {
        return self::parse($code) !== null;
    }

    /**
     * Split a code into its subtags, or null when it is not a valid hreflang.
     *
     * @return array{language: string, script: string|null, region: string|null, x_default: bool}|null
     */
    public static function parse(string $code): ?array
    {
        if (preg_match('/^[a-z]{2}(?:-[a-z]{4})?(?:-[a-z]{2})?$/iD', $code) !== 1
            && strcasecmp($code, self::X_DEFAULT) !== 0) {
            return null;
        }
        // Casing is insignificant; aliases and malformed separators are not repaired.
        $code = strtolower($code);

        if ($code === self::X_DEFAULT) {
            return ['language' => self::X_DEFAULT, 'script' => null, 'region' => null, 'x_default' => true];
        }

        if ($code === '') {
            return null;
        }

        $parts = explode('-', $code);
        $language = array_shift($parts);

        if (! in_array($language, self::list(self::LANGUAGES), true)) {
            return null;
        }

        $script = null;
        $region = null;

        foreach ($parts as $part) {
            if ($script === null && $region === null && strlen($part) === 4 && LanguageTag::registered('script', $part)) {
                $script = ucfirst($part);

                continue;
            }

            if ($region === null && in_array(strtoupper($part), self::list(self::REGIONS), true)) {
                $region = strtoupper($part);

                continue;
            }

            return null;
        }

        return ['language' => $language, 'script' => $script, 'region' => $region, 'x_default' => false];
    }

    /**
     * The language subtag of a code (`pt-BR` → `pt`), or null for `x-default`
     * and blank input.
     */
    public static function language(string $code): ?string
    {
        $normalized = self::normalize($code);

        if ($normalized === '' || $normalized === self::X_DEFAULT) {
            return null;
        }

        return explode('-', $normalized)[0];
    }

    /**
     * The alternates a page should emit, after the `seo.hreflang` policies.
     *
     * 1. Malformed entries (non-array, missing hreflang/href) are dropped —
     *    unchanged from before.
     * 2. `seo.hreflang.normalize` (default on): every code is rewritten to
     *    its BCP 47 form.
     * 3. `seo.hreflang.include_self` (default off): when the list is not empty
     *    and neither the page's canonical nor its own locale code appears in
     *    it, the page is appended as its own alternate — Google requires each
     *    page to list itself.
     * 4. `seo.hreflang.x_default` (default null): a language code; when the
     *    list is not empty and carries no `x-default`, the alternate for that
     *    language is duplicated as `x-default`.
     *
     * An empty list stays empty: a page with no alternates gets neither a
     * self-reference nor an x-default, because hreflang means nothing without
     * at least one translation.
     *
     * @param  mixed  $alternates  The model's raw alternates
     * @param  string|null  $selfHref  The page's canonical URL, for the self policy
     * @param  string|null  $selfLocale  The page's resolved locale, for the self policy
     * @return array<int, array{hreflang: string, href: string}>
     */
    public static function alternatesFor(mixed $alternates, ?string $selfHref = null, ?string $selfLocale = null): array
    {
        $valid = self::shape($alternates);

        if ($valid === []) {
            return [];
        }

        if ((bool) config('seo.hreflang.normalize', true)) {
            foreach ($valid as $i => $alternate) {
                $valid[$i]['hreflang'] = self::normalize($alternate['hreflang']);
            }
        }

        if ((bool) config('seo.hreflang.include_self', false) && $selfHref !== null && $selfHref !== '') {
            $selfCode = self::fromLocale($selfLocale);

            if ($selfCode !== null && ! self::hasHref($valid, $selfHref) && ! self::hasCode($valid, $selfCode)) {
                $valid[] = ['hreflang' => $selfCode, 'href' => $selfHref];
            }
        }

        $xDefault = config('seo.hreflang.x_default');

        if (is_string($xDefault) && trim($xDefault) !== '' && ! self::hasCode($valid, self::X_DEFAULT)) {
            $wanted = self::normalize($xDefault);
            $href = null;

            // Prefer an exact code match (`en-US`), then the language alone (`en`).
            foreach ($valid as $alternate) {
                if (strcasecmp($alternate['hreflang'], $wanted) === 0) {
                    $href = $alternate['href'];
                    break;
                }
            }

            if ($href === null) {
                foreach ($valid as $alternate) {
                    if (self::language($alternate['hreflang']) === self::language($wanted)) {
                        $href = $alternate['href'];
                        break;
                    }
                }
            }

            if ($href !== null) {
                $valid[] = ['hreflang' => self::X_DEFAULT, 'href' => $href];
            }
        }

        return $valid;
    }

    /**
     * Problems in a list of alternates, for the audit: invalid codes and codes
     * listed more than once. Runs on the already policy-applied list.
     *
     * @param  array<int, array{hreflang: string, href: string}>  $alternates
     * @return array{invalid: array<int, string>, duplicate: array<int, string>}
     */
    public static function issues(array $alternates): array
    {
        $invalid = [];
        $seen = [];
        $duplicate = [];

        foreach ($alternates as $alternate) {
            $code = $alternate['hreflang'];

            if (! self::isValid($code)) {
                $invalid[] = $code;
            }

            $key = strtolower(self::normalize($code));

            if (isset($seen[$key])) {
                $duplicate[] = $code;
            }

            $seen[$key] = true;
        }

        return ['invalid' => array_values(array_unique($invalid)), 'duplicate' => array_values(array_unique($duplicate))];
    }

    /**
     * Drop malformed entries, keeping the tolerant shape guard the renderers
     * always applied.
     *
     * @return array<int, array{hreflang: string, href: string}>
     */
    private static function shape(mixed $alternates): array
    {
        if (! is_array($alternates) || $alternates === []) {
            return [];
        }

        $valid = [];

        foreach ($alternates as $alternate) {
            if (! is_array($alternate)) {
                continue;
            }

            $hreflang = $alternate['hreflang'] ?? null;
            $href = $alternate['href'] ?? null;

            if (is_string($hreflang) && trim($hreflang) !== '' && is_string($href) && $href !== '') {
                $valid[] = ['hreflang' => $hreflang, 'href' => $href];
            }
        }

        return $valid;
    }

    /**
     * @param  array<int, array{hreflang: string, href: string}>  $alternates
     */
    private static function hasHref(array $alternates, string $href): bool
    {
        foreach ($alternates as $alternate) {
            if (rtrim($alternate['href'], '/') === rtrim($href, '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{hreflang: string, href: string}>  $alternates
     */
    private static function hasCode(array $alternates, string $code): bool
    {
        foreach ($alternates as $alternate) {
            if (strcasecmp(self::normalize($alternate['hreflang']), self::normalize($code)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function list(string $spaceSeparated): array
    {
        static $cache = [];

        return $cache[$spaceSeparated] ??= explode(' ', $spaceSeparated);
    }
}
