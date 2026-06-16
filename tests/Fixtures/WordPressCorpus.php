<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A deterministic, fully anonymized WordPress corpus generator, modelled on the
 * shape of a real ~900-page Yoast / Rank Math migration (the idi swap) but with
 * no real content — names are public-domain figures, URLs are `acme.test`, and
 * every value is synthesised from the row index.
 *
 * It seeds a WordPress-shaped database (`{prefix}posts`, `{prefix}postmeta`,
 * `{prefix}options`, `{prefix}users`) on a given connection and returns a
 * **manifest** of exact tallies, so a test can assert the importer's
 * verification report against ground truth without re-deriving the generation
 * arithmetic (which would silently drift).
 *
 * The same generator drives both the Yoast and the Rank Math importer via the
 * `$flavor` switch, so one fixture proves both plugin paths.
 */
final class WordPressCorpus
{
    /** Anonymized author display names — every one appears in a real corpus. */
    public const AUTHORS = ['Ada Lovelace', 'Grace Hopper', 'Alan Turing', 'Katherine Johnson'];

    public const BLOGNAME = 'Acme Journal';

    /**
     * Create the WordPress-shaped tables on $connection.
     */
    public static function createSchema(string $connection, string $prefix = 'wp_'): void
    {
        $schema = Schema::connection($connection);

        $schema->create($prefix.'posts', function ($t): void {
            $t->bigIncrements('ID');
            $t->unsignedBigInteger('post_author')->default(0);
            $t->text('post_title')->nullable();
            $t->string('post_name')->nullable();
            $t->string('post_status')->default('publish');
            $t->string('post_type')->default('post');
            $t->text('post_excerpt')->nullable();
            $t->text('post_content')->nullable();
        });

        $schema->create($prefix.'postmeta', function ($t): void {
            $t->bigIncrements('meta_id');
            $t->unsignedBigInteger('post_id');
            $t->string('meta_key')->nullable();
            $t->longText('meta_value')->nullable();
        });

        $schema->create($prefix.'options', function ($t): void {
            $t->bigIncrements('option_id');
            $t->string('option_name');
            $t->longText('option_value')->nullable();
        });

        $schema->create($prefix.'users', function ($t): void {
            $t->bigIncrements('ID');
            $t->string('display_name')->nullable();
        });
    }

    public static function dropSchema(string $connection, string $prefix = 'wp_'): void
    {
        $schema = Schema::connection($connection);

        foreach (['postmeta', 'posts', 'options', 'users', 'rank_math_redirections'] as $suffix) {
            $schema->dropIfExists($prefix.$suffix);
        }
    }

    /**
     * Seed the blog name + the author users every post is attributed to.
     */
    public static function seedBase(string $connection, string $prefix = 'wp_'): void
    {
        DB::connection($connection)->table($prefix.'options')->insert([
            'option_name' => 'blogname',
            'option_value' => self::BLOGNAME,
        ]);

        $users = [];

        foreach (self::AUTHORS as $index => $name) {
            $users[] = ['ID' => $index + 1, 'display_name' => $name];
        }

        DB::connection($connection)->table($prefix.'users')->insert($users);
    }

    /**
     * Seed $count anonymized posts for the importer flavor ('yoast' or
     * 'rank-math') and return the ground-truth manifest.
     *
     * @return array{
     *     count: int, with_meta: int, without_meta: int,
     *     truncated_titles: int, noindex: int, focus_keywords: int,
     *     unmapped_extra: int, authors: array<int, string>,
     *     slugs: array<int, string>, meta_slugs: array<int, string>,
     * }
     */
    public static function seedPosts(string $connection, string $flavor, int $count, string $prefix = 'wp_'): array
    {
        $prefixMeta = $flavor === 'rank-math' ? 'rank_math_' : '_yoast_wpseo_';

        $manifest = [
            'count' => $count,
            'with_meta' => 0,
            'without_meta' => 0,
            'truncated_titles' => 0,
            'noindex' => 0,
            'focus_keywords' => 0,
            'unmapped_extra' => 0,
            'authors' => [],
            'slugs' => [],
            'meta_slugs' => [],
        ];

        $posts = [];
        $meta = [];

        for ($i = 1; $i <= $count; $i++) {
            $slug = sprintf('post-%04d', $i);
            $authorId = (($i - 1) % count(self::AUTHORS)) + 1;

            $manifest['slugs'][] = $slug;

            $posts[] = [
                'ID' => $i,
                'post_author' => $authorId,
                'post_title' => 'Article '.$i,
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_excerpt' => null,
                'post_content' => null,
            ];

            // 1 in 30 posts carries no SEO metadata at all → skipped.
            if ($i % 30 === 0) {
                $manifest['without_meta']++;

                continue;
            }

            $manifest['with_meta']++;
            $manifest['meta_slugs'][] = $slug;
            $manifest['authors'][self::AUTHORS[$authorId - 1]] = true;

            $rows = self::metaForPost($i, $slug, $flavor, $prefixMeta, $manifest);

            foreach ($rows as $key => $value) {
                $meta[] = ['post_id' => $i, 'meta_key' => $key, 'meta_value' => $value];
            }
        }

        foreach (array_chunk($posts, 500) as $chunk) {
            DB::connection($connection)->table($prefix.'posts')->insert($chunk);
        }

        foreach (array_chunk($meta, 500) as $chunk) {
            DB::connection($connection)->table($prefix.'postmeta')->insert($chunk);
        }

        $manifest['authors'] = array_keys($manifest['authors']);
        sort($manifest['authors']);

        return $manifest;
    }

    /**
     * Build one post's plugin meta, mutating the manifest tallies as it goes so
     * the returned counts are exact rather than re-derived.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, string>
     */
    private static function metaForPost(int $i, string $slug, string $flavor, string $prefixMeta, array &$manifest): array
    {
        $rm = $flavor === 'rank-math';

        // 1 in 20 posts has an over-length title that must be truncated to 70.
        if ($i % 20 === 0) {
            $title = str_repeat('a', 85);
            $manifest['truncated_titles']++;
        } else {
            $title = $rm ? '%title% %sep% %sitename%' : '%%title%% %%sep%% %%sitename%%';
        }

        // 1 in 25 posts has a multibyte description (proves mb-safe handling).
        $description = $i % 25 === 0
            ? 'Übersicht über Ähnliches — 日本語 概要 '.$i
            : 'Description for article '.$i.'.';

        $canonical = 'https://acme.test/'.$slug;

        $meta = [
            $prefixMeta.($rm ? 'title' : 'title') => $title,
            $prefixMeta.($rm ? 'description' : 'metadesc') => $description,
            $prefixMeta.($rm ? 'canonical_url' : 'canonical') => $canonical,
        ];

        // 1 in 33 posts is noindex.
        if ($i % 33 === 0) {
            $manifest['noindex']++;

            if ($rm) {
                $meta[$prefixMeta.'robots'] = serialize(['noindex', 'follow']);
            } else {
                $meta[$prefixMeta.'meta-robots-noindex'] = '1';
            }
        }

        // 1 in 10 posts has a focus keyword.
        if ($i % 10 === 0) {
            $manifest['focus_keywords']++;
            $meta[$prefixMeta.($rm ? 'focus_keyword' : 'focuskw')] = 'keyword '.$i;
        }

        // 1 in 50 posts carries a key with no Core 3 home (reported, not copied).
        if ($i % 50 === 0) {
            $manifest['unmapped_extra']++;
            $meta[$prefixMeta.($rm ? 'breadcrumb_title' : 'schema_article_type')] = 'Crumb '.$i;
        }

        return $meta;
    }
}
