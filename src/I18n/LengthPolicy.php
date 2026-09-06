<?php

declare(strict_types=1);

namespace Rankbeam\Seo\I18n;

/**
 * The title / description length limits for a given script.
 *
 * Google shows roughly 600 px of title and 920 px of description on a desktop
 * result, and the package's historical "60 / 160 characters" is that budget
 * expressed for Latin text. A full-width CJK glyph is about twice as wide, so
 * the same budget is ~30 / ~80 characters of Japanese — a 45-character Japanese
 * title is already cut, while a 45-character English one is fine. Every place
 * that used to read the bare constants (the editor warnings, the free audit,
 * the computed-description truncation, the Pro scan and the Filament counters)
 * now asks this policy for the limits that apply to the text in front of it.
 *
 * Limits come from `config('seo.length_policy')`, one row per {@see Script}
 * bucket with a `default` row for everything not listed; a row may override
 * only some keys and inherit the rest from `default`. The built-in defaults
 * apply when the config is unpublished or a row is missing, so an upgraded
 * install gets the CJK limits without touching its config.
 *
 * ```php
 * $policy = LengthPolicy::for($title, $locale);
 * $policy->script;                 // 'cjk'
 * $policy->titleMax;               // 30
 * $policy->length($title);         // graphemes, not bytes or codepoints
 * $policy->titleTooLong($title);   // bool
 * ```
 */
final class LengthPolicy
{
    /**
     * The key of the row every unlisted script falls back to.
     */
    public const DEFAULT = 'default';

    /**
     * Built-in limits. `default` is the Latin budget the package always had;
     * `cjk` halves it (full-width glyphs). Every other script uses `default`
     * unless the app configures a row for it.
     *
     * @var array<string, array{title_min: int, title_max: int, description_min: int, description_max: int}>
     */
    public const DEFAULTS = [
        self::DEFAULT => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
        Script::CJK => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    ];

    private function __construct(
        public readonly string $script,
        public readonly int $titleMin,
        public readonly int $titleMax,
        public readonly int $descriptionMin,
        public readonly int $descriptionMax,
        public readonly bool $isDefault,
    ) {}

    /**
     * The policy for a piece of text — its dominant script decides, with the
     * locale as the hint for text that carries no letters.
     */
    public static function for(?string $text, ?string $locale = null): self
    {
        return self::forScript(Script::detect($text, $locale));
    }

    /**
     * The policy for a locale, before any text exists (an empty editor field).
     */
    public static function forLocale(?string $locale): self
    {
        return self::forScript(Script::fromLocale($locale) ?? Script::LATIN);
    }

    /**
     * The policy for a {@see Script} bucket.
     */
    public static function forScript(string $script): self
    {
        $rows = self::rows();
        $isDefault = $script === self::DEFAULT || ! isset($rows[$script]);
        $row = $rows[$script] ?? $rows[self::DEFAULT];

        return new self(
            script: $script,
            titleMin: $row['title_min'],
            titleMax: $row['title_max'],
            descriptionMin: $row['description_min'],
            descriptionMax: $row['description_max'],
            isDefault: $isDefault,
        );
    }

    /**
     * The `default` row — the limits for Latin text and every unlisted script.
     */
    public static function default(): self
    {
        return self::forScript(self::DEFAULT);
    }

    /**
     * Grapheme length of a value, the unit every limit here is expressed in.
     */
    public function length(?string $text): int
    {
        return Script::length($text);
    }

    public function titleTooLong(?string $title): bool
    {
        return $this->length($title) > $this->titleMax;
    }

    public function titleTooShort(?string $title): bool
    {
        return $this->length($title) < $this->titleMin;
    }

    public function descriptionTooLong(?string $description): bool
    {
        return $this->length($description) > $this->descriptionMax;
    }

    public function descriptionTooShort(?string $description): bool
    {
        return $this->length($description) < $this->descriptionMin;
    }

    /**
     * Scale a budget expressed for the default script to this script.
     *
     * The computed-description truncation keeps its historical single knob
     * (`seo.computed.description_max_length`, a Latin budget); for a CJK text
     * that knob is scaled by this policy's ratio, so one configured number
     * stays correct for every script.
     */
    public function scaleDescription(int $defaultBudget): int
    {
        if ($this->isDefault) {
            return $defaultBudget;
        }

        $base = self::default()->descriptionMax;

        return max(1, (int) round($defaultBudget * $this->descriptionMax / max(1, $base)));
    }

    /**
     * @return array{script: string, title_min: int, title_max: int, description_min: int, description_max: int}
     */
    public function toArray(): array
    {
        return [
            'script' => $this->script,
            'title_min' => $this->titleMin,
            'title_max' => $this->titleMax,
            'description_min' => $this->descriptionMin,
            'description_max' => $this->descriptionMax,
        ];
    }

    /**
     * The configured rows merged over the built-in defaults, each row merged
     * over the `default` row, values sanitised to positive integers with
     * min ≤ max.
     *
     * @return array<string, array{title_min: int, title_max: int, description_min: int, description_max: int}>
     */
    private static function rows(): array
    {
        $configured = config('seo.length_policy', []);
        $configured = is_array($configured) ? $configured : [];

        $default = self::sanitise(
            array_merge(self::DEFAULTS[self::DEFAULT], self::onlyInts($configured[self::DEFAULT] ?? [])),
            self::DEFAULTS[self::DEFAULT],
        );

        $rows = [self::DEFAULT => $default];

        $scripts = array_unique(array_merge(array_keys(self::DEFAULTS), array_keys($configured)));

        foreach ($scripts as $script) {
            if ($script === self::DEFAULT || ! is_string($script)) {
                continue;
            }

            $row = array_merge(
                $default,
                self::onlyInts(self::DEFAULTS[$script] ?? []),
                self::onlyInts($configured[$script] ?? []),
            );

            $rows[$script] = self::sanitise($row, $default);
        }

        return $rows;
    }

    /**
     * @param  mixed  $row
     * @return array<string, int>
     */
    private static function onlyInts(mixed $row): array
    {
        if (! is_array($row)) {
            return [];
        }

        $ints = [];

        foreach (['title_min', 'title_max', 'description_min', 'description_max'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key]) && (int) $row[$key] > 0) {
                $ints[$key] = (int) $row[$key];
            }
        }

        return $ints;
    }

    /**
     * @param  array<string, int>  $row
     * @param  array<string, int>  $fallback
     * @return array{title_min: int, title_max: int, description_min: int, description_max: int}
     */
    private static function sanitise(array $row, array $fallback): array
    {
        $titleMax = $row['title_max'] ?? $fallback['title_max'];
        $titleMin = $row['title_min'] ?? $fallback['title_min'];
        $descriptionMax = $row['description_max'] ?? $fallback['description_max'];
        $descriptionMin = $row['description_min'] ?? $fallback['description_min'];

        return [
            'title_min' => min($titleMin, $titleMax),
            'title_max' => $titleMax,
            'description_min' => min($descriptionMin, $descriptionMax),
            'description_max' => $descriptionMax,
        ];
    }
}
