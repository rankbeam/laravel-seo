<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Rankbeam\Seo\Importing\Contracts\Importer;
use Rankbeam\Seo\Models\SEOMeta;
use Throwable;

/**
 * Shared machinery for importers: morph-type resolution, column-length
 * trimming, and the idempotent, dry-run-aware write into `seo_meta`.
 *
 * These helpers are deliberately source-agnostic so the WordPress importer
 * can reuse them.
 */
abstract class AbstractImporter implements Importer
{
    /**
     * Max lengths of the Core 3 `seo_meta` columns this importer may write to.
     * Used to trim over-length source values *visibly* rather than letting a
     * strict-mode driver reject the row (or silently truncate it).
     *
     * Only text columns are listed: canonical/og_image are string(255) on both
     * sides of every supported source, so they cannot overflow.
     *
     * @var array<string, int>
     */
    protected const COLUMN_LIMITS = [
        'title' => 70,
        'description' => 160,
        'robots' => 50,
        'og_title' => 70,
        'og_description' => 200,
        'twitter_title' => 70,
        'twitter_description' => 200,
        'twitter_card' => 30,
        'og_type' => 30,
        'schema_type' => 50,
    ];

    /**
     * Resolve a stored morph type (a morph-map alias or an FQCN) to a model
     * class name, or null when it resolves to nothing usable.
     *
     * Handles both conventions: an app that enforces a morph map stored aliases
     * ("post"); one that did not stored FQCNs ("App\\Models\\Post").
     *
     * @return class-string<Model>|null
     */
    protected function resolveModelClass(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $mapped = Relation::getMorphedModel($type);

        if (is_string($mapped) && class_exists($mapped)) {
            $type = $mapped;
        }

        if (! class_exists($type) || ! is_subclass_of($type, Model::class)) {
            return null;
        }

        return $type;
    }

    /**
     * The set of stored morph-type strings that correspond to a model FQCN,
     * so a `--model` filter (always given as FQCNs) can scope a query whether
     * the source stored aliases or class names.
     *
     * @param  array<int, string>  $classes
     * @return array<int, string>
     */
    protected function morphTypesFor(array $classes): array
    {
        $types = [];

        foreach ($classes as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $types[] = $class;
            // The class' own morph alias (== FQCN when no map is enforced).
            $types[] = (new $class)->getMorphClass();
        }

        return array_values(array_unique($types));
    }

    /**
     * Trim a value to the Core 3 column limit, recording the truncation so the
     * operator can review it. Multibyte-safe.
     */
    protected function fit(?string $value, string $column, ImportResult $result): ?string
    {
        if ($value === null) {
            return null;
        }

        $limit = static::COLUMN_LIMITS[$column] ?? null;

        if ($limit === null || mb_strlen($value) <= $limit) {
            return $value;
        }

        $result->truncated($column);

        return mb_substr($value, 0, $limit);
    }

    /**
     * Normalise a raw source value: trim, and collapse an empty string to null
     * so the importer only ever *fills* fields and never clears existing data.
     */
    protected function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Write the mapped attributes onto the model's `seo_meta` row for $locale,
     * idempotently and honouring dry-run, recording the outcome.
     *
     * Fill-empty-only by default: any imported attribute whose target column
     * already holds a non-empty value is dropped, so an import never clobbers
     * hand-edited Rankbeam metadata (the documented contract). Pass
     * ImportOptions::$overwrite to replace existing values instead.
     *
     * Status is computed by comparing the (post-filter) attributes against the
     * stored record, so a re-run over unchanged data reports "unchanged" and
     * performs no write. The write itself goes through HasSEO::saveSEO() when
     * available (so the seoable relation matches exactly what core writes
     * natively), falling back to a direct upsert keyed on the model's own morph
     * class/key for models that do not (yet) carry the trait.
     *
     * @param  array<string, mixed>  $attributes  Only fields that have data.
     */
    protected function writeSeoMeta(Model $model, string $locale, array $attributes, ImportOptions $options, ImportResult $result): void
    {
        $seoableType = $model->getMorphClass();
        $seoableId = $model->getKey();

        $existing = SEOMeta::query()
            ->where('seoable_type', $seoableType)
            ->where('seoable_id', $seoableId)
            ->where('locale', $locale)
            ->first();

        // Fill-empty-only: keep only attributes whose target column is empty,
        // unless the operator explicitly opted into overwriting.
        if (! $options->overwrite) {
            $attributes = $this->fillableOnly($existing, $attributes);
        }

        $status = $this->statusFor($existing, $attributes);

        if (! $options->dryRun && $status !== 'unchanged' && $attributes !== []) {
            if (method_exists($model, 'saveSEO')) {
                $model->saveSEO($attributes, $locale);
            } else {
                SEOMeta::query()->updateOrCreate(
                    [
                        'seoable_type' => $seoableType,
                        'seoable_id' => $seoableId,
                        'locale' => $locale,
                    ],
                    $attributes,
                );
            }
        }

        $result->recordStatus($status);
    }

    /**
     * Drop imported attributes that would overwrite a non-empty existing value,
     * so the import only ever fills empty (null / empty-string) target columns.
     *
     * A brand-new record (no $existing, or the empty seo_meta row HasSEO
     * auto-creates) has every column empty, so every imported attribute is
     * kept. An attribute the operator hand-edited is left untouched.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function fillableOnly(?SEOMeta $existing, array $attributes): array
    {
        if ($existing === null) {
            return $attributes;
        }

        $fillable = [];

        foreach ($attributes as $key => $value) {
            $current = $existing->getAttribute($key);

            // Treat null and empty string as "empty"; arrays/scalars with any
            // content are considered filled and are not overwritten.
            $isEmpty = $current === null || $current === '' || $current === [];

            if ($isEmpty) {
                $fillable[$key] = $value;
            }
        }

        return $fillable;
    }

    /**
     * Decide whether writing $attributes would create, update, or no-op the
     * given record.
     *
     * @param  array<string, mixed>  $attributes
     * @return 'created'|'updated'|'unchanged'
     */
    protected function statusFor(?SEOMeta $existing, array $attributes): string
    {
        if ($existing === null) {
            return 'created';
        }

        foreach ($attributes as $key => $value) {
            if ($existing->getAttribute($key) !== $value) {
                return 'updated';
            }
        }

        return 'unchanged';
    }

    /**
     * Collapse an exception to a single short, body-free line for the report
     * (never the full message — it may carry source data).
     */
    protected function shortMessage(Throwable $e): string
    {
        $message = preg_replace('/\s+/', ' ', $e->getMessage()) ?? '';

        return mb_substr(trim($message), 0, 120);
    }
}
