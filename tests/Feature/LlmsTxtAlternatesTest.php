<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Services\LlmsTxt\LlmsTxtBuilder;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| llms.txt lists a page's other-language versions (M2, opt-in)
|--------------------------------------------------------------------------
*/

class LlmsAlternatesArticle extends Model
{
    use HasSEO;

    protected $table = 'llms_alternates_articles';

    protected $fillable = ['title', 'slug', 'description'];

    public $timestamps = false;

    public function getSEOTitle(): ?string
    {
        return $this->title;
    }

    public function getSEODescription(): ?string
    {
        return $this->description;
    }

    public function getUrlForSEO(): string
    {
        return url("/it/{$this->slug}");
    }

    public function getSEOAlternates(): ?array
    {
        return [
            ['hreflang' => 'it_IT', 'href' => url("/it/{$this->slug}")],
            ['hreflang' => 'en', 'href' => url("/en/{$this->slug}")],
            ['hreflang' => 'de', 'href' => url("/de/{$this->slug}")],
            ['hreflang' => 'x-default', 'href' => url("/en/{$this->slug}")],
        ];
    }
}

beforeEach(function () {
    Storage::fake('public');

    $this->app['db']->connection()->getSchemaBuilder()->create('llms_alternates_articles', function ($table) {
        $table->increments('id');
        $table->string('title');
        $table->string('slug');
        $table->string('description')->nullable();
    });

    config([
        'seo.llms_txt.enabled' => true,
        'seo.llms_txt.disk' => 'public',
        'seo.llms_txt.path' => 'llms.txt',
        'seo.llms_txt.sources' => [],
        'seo.llms_txt.max_entries_per_section' => 100,
        'seo.llms_txt.alternates' => false,
        'seo.sitemap.models' => [LlmsAlternatesArticle::class],
        'seo.features.auto_create_meta' => false,
        'seo.title_suffix' => '',
        'seo.hreflang.normalize' => true,
    ]);

    LlmsAlternatesArticle::create(['title' => 'Guida', 'slug' => 'guida', 'description' => 'Una guida.']);
    LlmsAlternatesArticle::create(['title' => 'Senza descrizione', 'slug' => 'nuda']);
});

it('leaves the file unchanged by default', function () {
    $txt = app(LlmsTxtBuilder::class)->build();

    expect($txt)->toContain('- [Guida]('.url('/it/guida').'): Una guida.')
        ->and($txt)->not->toContain('Also in');
});

it('appends the other languages, normalised, minus the page itself and x-default', function () {
    config(['seo.llms_txt.alternates' => true]);

    $txt = app(LlmsTxtBuilder::class)->build();

    expect($txt)->toContain('- [Guida]('.url('/it/guida').'): Una guida. Also in: [en]('.url('/en/guida').'), [de]('.url('/de/guida').')')
        ->and($txt)->toContain('- [Senza descrizione]('.url('/it/nuda').'): Also in: [en]('.url('/en/nuda').'), [de]('.url('/de/nuda').')')
        ->and($txt)->not->toContain('it-IT')
        ->and($txt)->not->toContain('x-default');
});
