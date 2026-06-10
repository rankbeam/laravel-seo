<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Fibonoir\LaravelSEO\Services\Schema\BreadcrumbSchema;

/*
|--------------------------------------------------------------------------
| Characterization: breadcrumb from page ancestors, with loop guard
|--------------------------------------------------------------------------
|
| Ported from idi-it's SitewideSchema::breadcrumbForPage/pageAncestors:
| - the home page itself gets no breadcrumb
| - pages without ancestors get no breadcrumb
| - chain renders Home → ancestors → page with 1-based positions
| - ancestors with slug '/' are skipped (Home is already prepended)
| - a corrupted parent chain containing a cycle terminates instead of
|   looping forever
|
*/

class BreadcrumbPage extends Model
{
    protected $table = 'breadcrumb_pages';

    protected $fillable = ['title', 'slug', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getUrlForSEO(): string
    {
        return url('/' . ltrim($this->slug, '/'));
    }
}

beforeEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->create('breadcrumb_pages', function ($table) {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->unsignedBigInteger('parent_id')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('breadcrumb_pages');
});

it('returns null for the home page itself', function () {
    $home = BreadcrumbPage::create(['title' => 'Home', 'slug' => '/']);

    expect(BreadcrumbSchema::fromModelAncestors($home))->toBeNull();
});

it('returns null for a page without ancestors', function () {
    $page = BreadcrumbPage::create(['title' => 'Contact', 'slug' => 'contact']);

    expect(BreadcrumbSchema::fromModelAncestors($page))->toBeNull();
});

it('renders Home → ancestors → page with sequential positions', function () {
    $services = BreadcrumbPage::create(['title' => 'Services', 'slug' => 'services']);
    $derma = BreadcrumbPage::create(['title' => 'Dermatology', 'slug' => 'services/dermatology', 'parent_id' => $services->id]);
    $visits = BreadcrumbPage::create(['title' => 'Visits', 'slug' => 'services/dermatology/visits', 'parent_id' => $derma->id]);

    $schema = BreadcrumbSchema::fromModelAncestors($visits)->toArray();

    expect($schema['@type'])->toBe('BreadcrumbList');

    $items = $schema['itemListElement'];

    expect($items)->toHaveCount(4)
        ->and(array_column($items, 'name'))->toBe(['Home', 'Services', 'Dermatology', 'Visits'])
        ->and(array_column($items, 'position'))->toBe([1, 2, 3, 4])
        ->and($items[0]['item'])->toBe(url('/'))
        ->and($items[3]['item'])->toBe(url('/services/dermatology/visits'));
});

it('skips ancestors with slug / because Home is already prepended', function () {
    $home = BreadcrumbPage::create(['title' => 'Homepage', 'slug' => '/']);
    $about = BreadcrumbPage::create(['title' => 'About', 'slug' => 'about', 'parent_id' => $home->id]);
    $team = BreadcrumbPage::create(['title' => 'Team', 'slug' => 'about/team', 'parent_id' => $about->id]);

    $items = BreadcrumbSchema::fromModelAncestors($team)->toArray()['itemListElement'];

    expect(array_column($items, 'name'))->toBe(['Home', 'About', 'Team'])
        ->and(array_column($items, 'position'))->toBe([1, 2, 3]);
});

it('terminates on a cyclic parent chain instead of looping forever', function () {
    // Corrupt data: A and B are each other's parents.
    $a = BreadcrumbPage::create(['title' => 'A', 'slug' => 'a']);
    $b = BreadcrumbPage::create(['title' => 'B', 'slug' => 'b', 'parent_id' => $a->id]);
    $a->update(['parent_id' => $b->id]);

    $page = BreadcrumbPage::create(['title' => 'Page', 'slug' => 'page', 'parent_id' => $a->id]);

    $schema = BreadcrumbSchema::fromModelAncestors($page->fresh());

    expect($schema)->not->toBeNull();

    $items = $schema->toArray()['itemListElement'];

    // Walk: A (visited a.id), then A's parent B (visited b.id), then B's
    // parent is A again — already visited, stop. Finite chain: Home, B, A, Page.
    expect($items)->toHaveCount(4)
        ->and(array_column($items, 'name'))->toBe(['Home', 'B', 'A', 'Page']);
});

it('terminates on a self-referencing page', function () {
    $a = BreadcrumbPage::create(['title' => 'Self', 'slug' => 'self']);
    $a->update(['parent_id' => $a->id]);

    $page = BreadcrumbPage::create(['title' => 'Child', 'slug' => 'child', 'parent_id' => $a->id]);

    $schema = BreadcrumbSchema::fromModelAncestors($page->fresh());

    $items = $schema->toArray()['itemListElement'];

    expect(array_column($items, 'name'))->toBe(['Home', 'Self', 'Child']);
});
