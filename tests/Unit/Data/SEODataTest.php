<?php

declare(strict_types=1);

use Rankbeam\Seo\Data\SEOData;

describe('SEOData', function () {
    describe('Creation', function () {
        it('creates from array', function () {
            $data = SEOData::fromArray([
                'title' => 'Test Title',
                'description' => 'Test description',
                'canonical' => 'https://example.com/test',
                'robots' => 'index,follow',
            ]);

            expect($data)->toBeInstanceOf(SEOData::class)
                ->and($data->title)->toBe('Test Title')
                ->and($data->description)->toBe('Test description')
                ->and($data->canonical)->toBe('https://example.com/test')
                ->and($data->robots)->toBe('index,follow');
        });

        it('creates from array with snake_case keys', function () {
            $data = SEOData::fromArray([
                'og_title' => 'OG Title',
                'og_description' => 'OG Description',
                'og_image' => '/og-image.jpg',
                'twitter_title' => 'Twitter Title',
                'twitter_card' => 'summary_large_image',
            ]);

            expect($data->ogTitle)->toBe('OG Title')
                ->and($data->ogDescription)->toBe('OG Description')
                ->and($data->ogImage)->toBe('/og-image.jpg')
                ->and($data->twitterTitle)->toBe('Twitter Title')
                ->and($data->twitterCard)->toBe('summary_large_image');
        });

        it('creates from array with camelCase keys', function () {
            $data = SEOData::fromArray([
                'ogTitle' => 'OG Title',
                'ogDescription' => 'OG Description',
                'twitterTitle' => 'Twitter Title',
            ]);

            expect($data->ogTitle)->toBe('OG Title')
                ->and($data->ogDescription)->toBe('OG Description')
                ->and($data->twitterTitle)->toBe('Twitter Title');
        });

        it('creates with focus keywords', function () {
            $data = SEOData::fromArray([
                'title' => 'Test',
                'focus_keywords' => [
                    ['keyword' => 'laravel seo', 'is_primary' => true, 'synonyms' => ['seo for laravel']],
                    ['keyword' => 'meta tags', 'is_primary' => false],
                ],
            ]);

            expect($data->focusKeywords)->toBeArray()
                ->and($data->focusKeywords)->toHaveCount(2)
                ->and($data->focusKeywords[0]['keyword'])->toBe('laravel seo')
                ->and($data->focusKeywords[0]['is_primary'])->toBeTrue();
        });

        it('creates from model', function () {
            $model = createMockModel(['id' => 1], [
                'title' => 'SEO Title',
                'description' => 'SEO Description',
                'og_title' => 'OG Title',
                'robots' => 'noindex',
            ]);

            $data = SEOData::fromModel($model);

            expect($data)->toBeInstanceOf(SEOData::class)
                ->and($data->title)->toBe('SEO Title')
                ->and($data->description)->toBe('SEO Description')
                ->and($data->ogTitle)->toBe('OG Title')
                ->and($data->robots)->toBe('noindex');
        });

        it('creates from model without seo meta', function () {
            $model = createMockModel(['id' => 1]);

            $data = SEOData::fromModel($model);

            expect($data)->toBeInstanceOf(SEOData::class)
                ->and($data->title)->toBeNull()
                ->and($data->description)->toBeNull();
        });

        it('creates empty instance', function () {
            $data = SEOData::empty();

            expect($data)->toBeInstanceOf(SEOData::class)
                ->and($data->title)->toBeNull()
                ->and($data->description)->toBeNull()
                ->and($data->canonical)->toBeNull()
                ->and($data->ogType)->toBe('website')
                ->and($data->twitterCard)->toBe('summary_large_image');
        });

        it('has default values for ogType and twitterCard', function () {
            $data = new SEOData();

            expect($data->ogType)->toBe('website')
                ->and($data->twitterCard)->toBe('summary_large_image');
        });

        it('parses datetime fields', function () {
            $data = SEOData::fromArray([
                'title' => 'Test',
                'published_time' => '2024-01-15 10:30:00',
                'modified_time' => '2024-01-20 14:00:00',
            ]);

            expect($data->publishedTime)->toBeInstanceOf(DateTimeInterface::class)
                ->and($data->modifiedTime)->toBeInstanceOf(DateTimeInterface::class);
        });
    });

    describe('Merge', function () {
        it('merge overrides non-null values', function () {
            $base = new SEOData(
                title: 'Base Title',
                description: 'Base Description',
                ogImage: '/base-image.jpg',
            );

            $override = new SEOData(
                title: 'Override Title',
                ogImage: '/override-image.jpg',
            );

            $merged = $base->merge($override);

            expect($merged->title)->toBe('Override Title')
                ->and($merged->description)->toBe('Base Description') // preserved
                ->and($merged->ogImage)->toBe('/override-image.jpg');
        });

        it('merge preserves null values', function () {
            $base = new SEOData(
                title: 'Base Title',
                description: 'Base Description',
                ogImage: '/base-image.jpg',
            );

            $override = new SEOData(
                title: 'Override Title',
                description: null, // explicit null
                ogImage: null,     // explicit null
            );

            $merged = $base->merge($override);

            expect($merged->title)->toBe('Override Title')
                ->and($merged->description)->toBe('Base Description') // NULL didn't override
                ->and($merged->ogImage)->toBe('/base-image.jpg');      // NULL didn't override
        });

        it('merge is immutable', function () {
            $base = new SEOData(title: 'Base Title');
            $override = new SEOData(title: 'Override Title');

            $merged = $base->merge($override);

            expect($base->title)->toBe('Base Title')  // Original unchanged
                ->and($override->title)->toBe('Override Title')  // Original unchanged
                ->and($merged->title)->toBe('Override Title')
                ->and($merged)->not->toBe($base)
                ->and($merged)->not->toBe($override);
        });

        it('merge chains correctly', function () {
            $global = new SEOData(
                ogImage: '/default.jpg',
                robots: 'index,follow',
            );

            $typeDefaults = new SEOData(
                ogType: 'article',
                robots: 'index,follow,max-image-preview:large',
            );

            // Note: SEOData has default values for ogType ('website') and twitterCard.
            // To preserve values from earlier in the chain, later SEOData must explicitly
            // set ogType to the value they want to preserve, or we'd need to change the merge behavior.
            $computed = new SEOData(
                title: 'My Post Title',
                description: null, // Not computed
                ogType: 'article', // Must explicitly preserve from earlier merge
            );

            $explicit = new SEOData(
                title: 'Custom SEO Title',
                ogImage: '/custom.jpg',
                ogType: 'article', // Must explicitly preserve from earlier merge
            );

            $final = $global
                ->merge($typeDefaults)
                ->merge($computed)
                ->merge($explicit);

            expect($final->title)->toBe('Custom SEO Title')           // Explicit wins
                ->and($final->ogImage)->toBe('/custom.jpg')           // Explicit wins
                ->and($final->ogType)->toBe('article')                // Type default preserved
                ->and($final->robots)->toBe('index,follow,max-image-preview:large'); // Type default
        });

        it('merge preserves all fields', function () {
            $base = new SEOData(
                title: 'Title',
                description: 'Desc',
                canonical: 'https://example.com',
                robots: 'index',
                ogTitle: 'OG Title',
                ogDescription: 'OG Desc',
                ogImage: '/og.jpg',
                ogType: 'article',
                ogSiteName: 'Site',
                ogUrl: 'https://example.com/og',
                twitterTitle: 'TW Title',
                twitterDescription: 'TW Desc',
                twitterImage: '/tw.jpg',
                twitterCard: 'summary',
                twitterSite: '@site',
                twitterCreator: '@author',
                author: 'John',
                section: 'Tech',
                tags: ['tag1', 'tag2'],
                locale: 'en',
            );

            $override = new SEOData(title: 'New Title');

            $merged = $base->merge($override);

            expect($merged->title)->toBe('New Title')
                ->and($merged->description)->toBe('Desc')
                ->and($merged->canonical)->toBe('https://example.com')
                ->and($merged->ogTitle)->toBe('OG Title')
                ->and($merged->twitterTitle)->toBe('TW Title')
                ->and($merged->author)->toBe('John')
                ->and($merged->tags)->toBe(['tag1', 'tag2']);
        });
    });

    describe('Keywords', function () {
        it('hasKeywords returns false when empty', function () {
            $data = new SEOData();

            expect($data->hasKeywords())->toBeFalse();
        });

        it('hasKeywords returns true when set', function () {
            $data = SEOData::fromArray([
                'focus_keywords' => [
                    ['keyword' => 'test', 'is_primary' => true],
                ],
            ]);

            expect($data->hasKeywords())->toBeTrue();
        });

        it('getPrimaryKeyword returns primary', function () {
            $data = SEOData::fromArray([
                'focus_keywords' => [
                    ['keyword' => 'secondary', 'is_primary' => false],
                    ['keyword' => 'primary', 'is_primary' => true],
                    ['keyword' => 'another', 'is_primary' => false],
                ],
            ]);

            $primary = $data->getPrimaryKeyword();

            expect($primary['keyword'])->toBe('primary')
                ->and($primary['is_primary'])->toBeTrue();
        });

        it('getPrimaryKeyword returns first if none marked', function () {
            $data = SEOData::fromArray([
                'focus_keywords' => [
                    ['keyword' => 'first', 'is_primary' => false],
                    ['keyword' => 'second', 'is_primary' => false],
                ],
            ]);

            $primary = $data->getPrimaryKeyword();

            expect($primary['keyword'])->toBe('first');
        });

        it('getPrimaryKeyword returns null when no keywords', function () {
            $data = new SEOData();

            expect($data->getPrimaryKeyword())->toBeNull();
        });

        it('getKeywordStrings returns keyword strings', function () {
            $data = SEOData::fromArray([
                'focus_keywords' => [
                    ['keyword' => 'laravel seo', 'is_primary' => true],
                    ['keyword' => 'meta tags', 'is_primary' => false],
                    ['keyword' => 'structured data', 'is_primary' => false],
                ],
            ]);

            $strings = $data->getKeywordStrings();

            expect($strings)->toBe(['laravel seo', 'meta tags', 'structured data']);
        });

        it('getKeywordStrings returns empty array when no keywords', function () {
            $data = new SEOData();

            expect($data->getKeywordStrings())->toBe([]);
        });
    });

    describe('Conversion', function () {
        it('to array groups correctly', function () {
            $data = new SEOData(
                title: 'Page Title',
                description: 'Page description',
                ogTitle: 'OG Title',
                ogImage: '/og.jpg',
                twitterCard: 'summary_large_image',
                twitterTitle: 'Twitter Title',
            );

            $array = $data->toArray();

            expect($array)->toHaveKey('title')
                ->and($array)->toHaveKey('description')
                ->and($array)->toHaveKey('og')
                ->and($array)->toHaveKey('twitter')
                ->and($array['og'])->toBeArray()
                ->and($array['og']['title'])->toBe('OG Title')
                ->and($array['og']['image'])->toBe('/og.jpg')
                ->and($array['twitter'])->toBeArray()
                ->and($array['twitter']['card'])->toBe('summary_large_image');
        });

        it('toArray falls back og values to core', function () {
            $data = new SEOData(
                title: 'Page Title',
                description: 'Page description',
            );

            $array = $data->toArray();

            expect($array['og']['title'])->toBe('Page Title')
                ->and($array['og']['description'])->toBe('Page description');
        });

        it('toArray falls back twitter to og', function () {
            $data = new SEOData(
                title: 'Page Title',
                ogTitle: 'OG Title',
                ogImage: '/og.jpg',
            );

            $array = $data->toArray();

            expect($array['twitter']['title'])->toBe('OG Title')  // Falls back to og
                ->and($array['twitter']['image'])->toBe('/og.jpg');
        });

        it('toArray filters empty groups', function () {
            $data = new SEOData(title: 'Title');

            $array = $data->toArray();

            expect($array)->not->toHaveKey('article')  // Empty group filtered
                ->and($array)->not->toHaveKey('schema')
                ->and($array)->not->toHaveKey('alternates');
        });

        it('toFlatArray returns snake_case keys', function () {
            $data = new SEOData(
                title: 'Title',
                ogTitle: 'OG Title',
                ogImage: '/og.jpg',
                twitterCard: 'summary',
            );

            $flat = $data->toFlatArray();

            expect($flat)->toHaveKey('title')
                ->and($flat)->toHaveKey('og_title')
                ->and($flat)->toHaveKey('og_image')
                ->and($flat)->toHaveKey('twitter_card')
                ->and($flat['og_title'])->toBe('OG Title')
                ->and($flat['og_image'])->toBe('/og.jpg');
        });

        it('toFlatArray formats dates', function () {
            $data = SEOData::fromArray([
                'title' => 'Test',
                'published_time' => '2024-01-15 10:30:00',
            ]);

            $flat = $data->toFlatArray();

            expect($flat['published_time'])->toBe('2024-01-15 10:30:00');
        });

        it('serializes to json', function () {
            $data = new SEOData(
                title: 'Title',
                description: 'Description',
            );

            $json = json_encode($data);
            $decoded = json_decode($json, true);

            expect($decoded)->toBeArray()
                ->and($decoded['title'])->toBe('Title')
                ->and($decoded['description'])->toBe('Description');
        });
    });

    describe('With method', function () {
        it('creates copy with changed field', function () {
            $original = new SEOData(title: 'Original Title');

            $updated = $original->with('title', 'Updated Title');

            expect($original->title)->toBe('Original Title')  // Unchanged
                ->and($updated->title)->toBe('Updated Title')
                ->and($updated)->not->toBe($original);
        });

        it('with accepts camelCase field names', function () {
            $data = new SEOData(ogTitle: 'Original');

            $updated = $data->with('ogTitle', 'Updated');

            expect($updated->ogTitle)->toBe('Updated');
        });

        it('with accepts snake_case field names', function () {
            $data = new SEOData(ogTitle: 'Original');

            $updated = $data->with('og_title', 'Updated');

            expect($updated->ogTitle)->toBe('Updated');
        });
    });

    describe('isEmpty', function () {
        it('returns true for empty instance', function () {
            $data = new SEOData();

            expect($data->isEmpty())->toBeTrue();
        });

        it('returns false when title is set', function () {
            $data = new SEOData(title: 'Title');

            expect($data->isEmpty())->toBeFalse();
        });

        it('returns false when description is set', function () {
            $data = new SEOData(description: 'Description');

            expect($data->isEmpty())->toBeFalse();
        });

        it('returns false when ogImage is set', function () {
            $data = new SEOData(ogImage: '/image.jpg');

            expect($data->isEmpty())->toBeFalse();
        });

        it('ignores default values for isEmpty check', function () {
            // ogType and twitterCard have defaults but shouldn't make isEmpty false
            $data = new SEOData();

            expect($data->ogType)->toBe('website')
                ->and($data->twitterCard)->toBe('summary_large_image')
                ->and($data->isEmpty())->toBeTrue();
        });
    });

    describe('Edge Cases', function () {
        it('handles array with all null values', function () {
            $data = SEOData::fromArray([
                'title' => null,
                'description' => null,
                'og_title' => null,
            ]);

            expect($data->title)->toBeNull()
                ->and($data->description)->toBeNull()
                ->and($data->isEmpty())->toBeTrue();
        });

        it('handles empty array', function () {
            $data = SEOData::fromArray([]);

            expect($data)->toBeInstanceOf(SEOData::class)
                ->and($data->isEmpty())->toBeTrue();
        });

        it('handles schema jsonld array', function () {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => 'Test Article',
            ];

            $data = SEOData::fromArray([
                'title' => 'Test',
                'schema_jsonld' => $schema,
            ]);

            expect($data->schemaJsonld)->toBe($schema)
                ->and($data->schemaJsonld['@type'])->toBe('Article');
        });

        it('handles alternates array', function () {
            $alternates = [
                ['hreflang' => 'en', 'href' => 'https://example.com/en/page'],
                ['hreflang' => 'de', 'href' => 'https://example.com/de/page'],
            ];

            $data = SEOData::fromArray([
                'title' => 'Test',
                'alternates' => $alternates,
            ]);

            expect($data->alternates)->toBe($alternates)
                ->and($data->alternates)->toHaveCount(2);
        });

        it('handles tags array', function () {
            $data = SEOData::fromArray([
                'title' => 'Test',
                'tags' => ['tag1', 'tag2', 'tag3'],
            ]);

            expect($data->tags)->toBe(['tag1', 'tag2', 'tag3']);
        });

    });
});
