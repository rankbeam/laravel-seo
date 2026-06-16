<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Importing\WordPress;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rankbeam\Seo\Importing\ImportOptions;
use Rankbeam\Seo\Importing\ImportResult;
use Throwable;

/**
 * Shared reader for SEO data stored in a WordPress database: it walks
 * `wp_posts` (published posts/pages) and pulls each row's plugin metadata out
 * of `wp_postmeta`, then hands the per-post meta map to a concrete subclass
 * ({@see YoastImporter}, {@see RankMathImporter}) to map onto `seo_meta`.
 *
 * Point it at the WordPress database with `--connection=` (configure that
 * connection in `config/database.php`); the table prefix defaults to `wp_` and
 * is overridable with `--table=` (it is treated as a prefix here, e.g.
 * `--table=wp_` reads `wp_posts` + `wp_postmeta`).
 *
 * Each post's slug (`post_name`) is matched to a content model exactly as the
 * CSV importer matches URL slugs. Posts that match nothing are URL-only.
 */
abstract class WordPressDatabaseImporter extends WordPressImporter
{
    public const DEFAULT_PREFIX = 'wp_';

    /**
     * The default WordPress post types to import when `--post-type` is not set.
     *
     * @var array<int, string>
     */
    protected const DEFAULT_POST_TYPES = ['post', 'page'];

    /**
     * Lazily-built `wp_users.ID => display_name` map, used to resolve the post
     * author into a readable value for the verification report. Null until the
     * first author lookup; an empty array once loaded (or when no users table).
     *
     * @var array<string, string>|null
     */
    protected ?array $authorNameCache = null;

    /**
     * The `wp_postmeta.meta_key` prefix this plugin uses (e.g. `_yoast_wpseo_`).
     */
    abstract protected function metaKeyPrefix(): string;

    /**
     * Plugin meta keys that carry no SEO value worth importing or reporting
     * (scores, processing flags, attachment ids handled elsewhere). Keys are
     * compared without the {@see self::metaKeyPrefix()}.
     *
     * @return array<int, string>
     */
    abstract protected function structuralKeys(): array;

    /**
     * Map this plugin's per-post meta to core `seo_meta` attributes. Implementations
     * resolve template tokens, compose robots, and report keys that have no
     * Core 3 home as unmapped.
     *
     * @param  array<string, string>  $meta  meta_key (with prefix) => meta_value
     * @param  array<string, string|null>  $context  Token context: title, sitename, sep, excerpt
     * @return array<string, mixed>
     */
    abstract protected function mapMeta(array $meta, array $context, ImportResult $result): array;

    public function sourceSummary(ImportOptions $options): string
    {
        $connection = $options->connection ?? 'default';

        return sprintf(
            'tables `%sposts` + `%spostmeta` on the %s connection',
            $this->prefix($options),
            $this->prefix($options),
            $connection,
        );
    }

    public function isAvailable(ImportOptions $options, ?string &$reason = null): bool
    {
        $schema = Schema::connection($options->connection);

        foreach (['posts', 'postmeta'] as $suffix) {
            $table = $this->prefix($options).$suffix;

            if (! $schema->hasTable($table)) {
                $reason = sprintf(
                    'WordPress table `%s` was not found%s. Point --connection= at the WordPress '
                    .'database, or pass --table= if it uses a non-default prefix.',
                    $table,
                    $options->connection ? " on connection [{$options->connection}]" : '',
                );

                return false;
            }
        }

        return true;
    }

    public function import(ImportOptions $options): ImportResult
    {
        $result = new ImportResult($options->dryRun);
        $writer = new RedirectCsvWriter($options->extra('redirects_csv'), $options->dryRun);

        $modelClass = $this->targetModelClass($options, $result);
        $matchBy = $options->extra('match_by');
        $postTypes = $this->postTypes($options);
        $siteName = $this->siteName($options);
        $remaining = $options->limit > 0 ? $options->limit : null;

        try {
            $this->postsQuery($options, $postTypes)->chunkById(200, function ($posts) use (
                $options, $modelClass, $matchBy, $siteName, $result, &$remaining
            ): bool {
                $metaByPost = $this->metaForPosts($options, $posts->pluck('ID')->all());

                foreach ($posts as $post) {
                    $result->recordScanned();

                    $meta = $metaByPost[$post->ID] ?? [];
                    $this->handlePost($post, $meta, $options, $modelClass, $matchBy, $siteName, $result);

                    if ($remaining !== null && --$remaining <= 0) {
                        return false;
                    }
                }

                return true;
            }, $this->prefix($options).'posts.ID', 'ID');

            // Plugin-specific redirect sources (e.g. Rank Math's redirections
            // table) — emitted to the CSV, never written to the Pro table.
            $this->importRedirects($options, $writer, $result);
        } finally {
            $writer->close();
        }

        return $result;
    }

    /**
     * Map one post + its meta and write (or skip) it.
     *
     * @param  array<string, string>  $meta
     * @param  class-string<Model>|null  $modelClass
     */
    protected function handlePost(
        object $post,
        array $meta,
        ImportOptions $options,
        ?string $modelClass,
        ?string $matchBy,
        ?string $siteName,
        ImportResult $result,
    ): void {
        $slug = $this->clean($post->post_name ?? null);

        try {
            $context = [
                'title' => $this->clean($post->post_title ?? null) ?? '',
                'excerpt' => $this->clean($post->post_excerpt ?? null) ?? '',
                'sitename' => $siteName ?? '',
                'sep' => '-',
            ];

            $attributes = $this->mapMeta($meta, $context, $result);

            if ($attributes === []) {
                $result->skip($slug, $modelClass, 'no SEO metadata stored for this post');

                return;
            }

            // The post author has no Core 3 column (it is a getSEOAuthor()
            // concern), so surface every distinct value rather than lose it —
            // for matched and URL-only pages alike.
            $author = $this->authorFor($post, $options);

            if ($author !== null) {
                $result->unmapped('author', $author);
            }

            $model = $this->matchModel($modelClass, $matchBy, $slug);

            if ($model === null) {
                $result->urlOnly($slug, $modelClass, $this->urlOnlyReason($modelClass, $slug));

                return;
            }

            $this->writeSeoMeta($model, $options->locale, $attributes, $options, $result);
        } catch (Throwable $e) {
            $result->skip($slug, $modelClass, 'error: '.$this->shortMessage($e));
        }
    }

    /**
     * Hook for subclasses that read a redirect table. The base does nothing —
     * the DB reader never guesses permalinks, so canonical-based redirects are
     * a job for the CSV importer where the operator controls the exact URLs.
     */
    protected function importRedirects(ImportOptions $options, RedirectCsvWriter $writer, ImportResult $result): void
    {
        // no-op by default
    }

    /**
     * Build the published-posts query, scoped to the requested post types.
     *
     * @param  array<int, string>  $postTypes
     */
    protected function postsQuery(ImportOptions $options, array $postTypes): Builder
    {
        return DB::connection($options->connection)
            ->table($this->prefix($options).'posts')
            ->where('post_status', 'publish')
            ->whereIn('post_type', $postTypes);
    }

    /**
     * Fetch the plugin's meta for a batch of posts, grouped by post and keyed
     * by meta_key (last value wins).
     *
     * @param  array<int, int>  $postIds
     * @return array<int, array<string, string>>
     */
    protected function metaForPosts(ImportOptions $options, array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $rows = DB::connection($options->connection)
            ->table($this->prefix($options).'postmeta')
            ->whereIn('post_id', $postIds)
            ->where('meta_key', 'like', $this->metaKeyPrefix().'%')
            ->get(['post_id', 'meta_key', 'meta_value']);

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row->post_id][$row->meta_key] = (string) $row->meta_value;
        }

        return $grouped;
    }

    /**
     * Report any non-empty plugin meta key that this importer did not map and
     * is not structural — so the operator knows it was deliberately dropped,
     * not silently lost.
     *
     * @param  array<string, string>  $meta
     * @param  array<int, string>  $mappedKeys  Keys (with prefix) the importer consumed.
     */
    protected function reportUnmapped(array $meta, array $mappedKeys, ImportResult $result): void
    {
        $prefix = $this->metaKeyPrefix();
        $ignore = array_map(fn (string $k): string => $prefix.$k, $this->structuralKeys());

        foreach ($meta as $key => $value) {
            if (in_array($key, $mappedKeys, true) || in_array($key, $ignore, true)) {
                continue;
            }

            if ($this->clean($value) === null) {
                continue;
            }

            // Report under the short (prefix-stripped) name for readability.
            $result->unmapped(str_starts_with($key, $prefix) ? substr($key, strlen($prefix)) : $key);
        }
    }

    /**
     * Decode a WordPress serialized-PHP meta value to an array of strings,
     * disallowing object instantiation. Returns an empty array when the value
     * is not a serialized array.
     *
     * @return array<int, string>
     */
    protected function unserializeList(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = @unserialize($value, ['allowed_classes' => false]);

        if (! is_array($decoded)) {
            return [];
        }

        $list = [];

        foreach ($decoded as $item) {
            if (is_scalar($item)) {
                $list[] = (string) $item;
            }
        }

        return $list;
    }

    /**
     * @param  class-string<Model>|null  $modelClass
     */
    protected function urlOnlyReason(?string $modelClass, ?string $slug): string
    {
        if ($modelClass === null) {
            return 'url-only (no --model given to attach metadata to)';
        }

        return sprintf('url-only (no %s matched slug "%s")', class_basename($modelClass), (string) $slug);
    }

    /**
     * @return array<int, string>
     */
    protected function postTypes(ImportOptions $options): array
    {
        $types = (array) $options->extra('post_type', []);
        $types = array_values(array_filter(array_map('strval', $types), static fn ($t) => $t !== ''));

        return $types === [] ? static::DEFAULT_POST_TYPES : $types;
    }

    /**
     * The blog name from `wp_options`, used to resolve the `%%sitename%%` token.
     * Best-effort — a missing options table just leaves the token to be stripped.
     */
    protected function siteName(ImportOptions $options): ?string
    {
        try {
            $value = DB::connection($options->connection)
                ->table($this->prefix($options).'options')
                ->where('option_name', 'blogname')
                ->value('option_value');

            return is_string($value) ? $this->clean($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Resolve a post's author to a readable value for the verification report,
     * or null when the post has no author. Display names come from the
     * `{prefix}users` table when readable; otherwise the numeric WordPress user
     * id is surfaced as `user #N` so the author is never silently dropped.
     */
    protected function authorFor(object $post, ImportOptions $options): ?string
    {
        $rawId = $post->post_author ?? null;

        if ($rawId === null || $rawId === '' || (string) $rawId === '0') {
            return null;
        }

        $id = (string) $rawId;

        return $this->authorNames($options)[$id] ?? ('user #'.$id);
    }

    /**
     * The `{prefix}users` display-name map, loaded once per run. Best-effort:
     * a missing or unreadable users table just leaves authors as numeric ids.
     *
     * @return array<string, string>
     */
    protected function authorNames(ImportOptions $options): array
    {
        if ($this->authorNameCache !== null) {
            return $this->authorNameCache;
        }

        $this->authorNameCache = [];

        try {
            $rows = DB::connection($options->connection)
                ->table($this->prefix($options).'users')
                ->get(['ID', 'display_name']);

            foreach ($rows as $row) {
                $name = $this->clean($row->display_name ?? null);

                if ($name !== null) {
                    $this->authorNameCache[(string) $row->ID] = $name;
                }
            }
        } catch (Throwable) {
            // No users table / not readable — authors surface as numeric ids.
        }

        return $this->authorNameCache;
    }

    protected function prefix(ImportOptions $options): string
    {
        return $options->table ?: self::DEFAULT_PREFIX;
    }
}
