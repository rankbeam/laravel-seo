<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

/**
 * hreflang codes: normalisation, validation and the site-wide policies.
 *
 * Google reads hreflang as `language[-Script][-REGION]` — ISO 639-1 language,
 * optional ISO 15924 script, optional ISO 3166-1 alpha-2 or UN M.49 region —
 * plus the literal `x-default`. Laravel apps tend to hand over their *locale*
 * instead (`it_IT`, `pt_br`, `zh_Hans`): an underscore is not valid there,
 * casing is conventional, and a typo (`en-UK`, `jp`) is silently ignored by
 * every search engine. This class is the one place those rules live:
 *
 * - {@see normalize()} rewrites `it_IT` → `it-IT`, `zh_hans_cn` → `zh-Hans-CN`;
 * - {@see isValid()} checks each subtag against the vendored code lists;
 * - {@see alternatesFor()} applies the `seo.hreflang` policies (normalise,
 *   add the page's own self-reference, add an `x-default`) to a model's
 *   alternates so the `<link>` tags, the sitemap and the audit all see the
 *   same list.
 */
final class Hreflang
{
    public const X_DEFAULT = 'x-default';

    /**
     * ISO 639-1 two-letter language codes, plus the three-letter codes Google
     * lists among its supported interface languages that have no two-letter
     * form (fil, yue, ceb, haw, hmn, ckb).
     */
    private const LANGUAGES = 'aa ab ae af ak am an ar as av ay az ba be bg bh bi bm bn bo br bs ca ce ch co cr cs cu cv cy da de dv dz ee el en eo es et eu fa ff fi fj fo fr fy ga gd gl gn gu gv ha he hi ho hr ht hu hy hz ia id ie ig ii ik io is it iu ja jv ka kg ki kj kk kl km kn ko kr ks ku kv kw ky la lb lg li ln lo lt lu lv mg mh mi mk ml mn mr ms mt my na nb nd ne ng nl nn no nr nv ny oc oj om or os pa pi pl ps pt qu rm rn ro ru rw sa sc sd se sg si sk sl sm sn so sq sr ss st su sv sw ta te tg th ti tk tl tn to tr ts tt tw ty ug uk ur uz ve vi vo wa wo xh yi yo za zh zu fil yue ceb haw hmn ckb';

    /**
     * ISO 15924 script codes a site would plausibly put in an hreflang.
     */
    private const SCRIPTS = 'Adlm Arab Armn Beng Cyrl Deva Ethi Geor Grek Gujr Guru Hang Hani Hans Hant Hebr Hira Hrkt Jpan Kana Khmr Knda Kore Laoo Latn Mlym Mong Mymr Orya Sinh Syrc Taml Telu Tfng Thaa Thai Tibt Vaii Yiii';

    /**
     * ISO 3166-1 alpha-2 region codes.
     */
    private const REGIONS = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW';

    /**
     * UN M.49 macro-region codes accepted as a region subtag (`es-419`).
     */
    private const M49 = '001 002 003 005 009 011 013 014 015 017 018 019 021 029 030 034 035 039 053 054 057 061 142 143 145 150 151 154 155 202 419';

    /**
     * Canonical BCP 47 form of a code: underscores become hyphens, the
     * language is lowercased, a script is Title-cased, a region is uppercased;
     * `x-default` in any casing becomes the literal. Unknown subtags are kept
     * (lowercased), so an invalid code still normalises deterministically and
     * {@see isValid()} can then say why it is wrong.
     */
    public static function normalize(string $code): string
    {
        $code = trim($code);

        if ($code === '') {
            return '';
        }

        if (strtolower($code) === self::X_DEFAULT) {
            return self::X_DEFAULT;
        }

        $parts = preg_split('/[-_]/', $code) ?: [];
        $out = [];

        foreach ($parts as $i => $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if ($i === 0) {
                $out[] = strtolower($part);
            } elseif (preg_match('/^[A-Za-z]{4}$/', $part) === 1) {
                $out[] = ucfirst(strtolower($part));
            } elseif (preg_match('/^[A-Za-z]{2}$/', $part) === 1) {
                $out[] = strtoupper($part);
            } else {
                $out[] = strtolower($part);
            }
        }

        return implode('-', $out);
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
     * Whether a (normalised or raw) code is one search engines accept.
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
        $code = self::normalize($code);

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
            if ($script === null && $region === null && in_array($part, self::list(self::SCRIPTS), true)) {
                $script = $part;

                continue;
            }

            if ($region === null && (in_array($part, self::list(self::REGIONS), true) || in_array($part, self::list(self::M49), true))) {
                $region = $part;

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
                $valid[] = ['hreflang' => trim($hreflang), 'href' => $href];
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
