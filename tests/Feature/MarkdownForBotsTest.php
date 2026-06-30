<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Http\Middleware\ServeMarkdownToBots;
use Rankbeam\Seo\Traits\HasSEO;

// A HasSEO model with content but NO toSeoMarkdown() — exercises the built
// title + description + getContentForSEO() fallback.
class MdArticle extends Model
{
    use HasSEO;

    protected $table = 'md_articles';

    protected $fillable = ['title', 'slug', 'content'];

    public $timestamps = false;

    public function getSEOTitle(): ?string
    {
        return $this->title;
    }

    public function getUrlForSEO(): string
    {
        return url("/articles/{$this->slug}");
    }
}

// A HasSEO model that provides its own clean markdown.
class MdGuide extends Model
{
    use HasSEO;

    protected $table = 'md_guides';

    protected $fillable = ['title', 'slug', 'body_md'];

    public $timestamps = false;

    public function toSeoMarkdown(): ?string
    {
        return $this->body_md;
    }

    public function getUrlForSEO(): string
    {
        return url("/guides/{$this->slug}");
    }
}

beforeEach(function () {
    config([
        'seo.markdown_for_bots.enabled' => true,
        'seo.markdown_for_bots.serve_to_known_bots' => false,
        'seo.markdown_for_bots.query_param' => 'format',
        'seo.markdown_for_bots.query_value' => 'md',
        'seo.markdown_for_bots.build_from_content' => true,
        'seo.title_suffix' => '',
    ]);

    SEO::markdown()->flush();

    $schema = $this->app['db']->connection()->getSchemaBuilder();

    foreach (['md_articles' => 'content', 'md_guides' => 'body_md'] as $table => $column) {
        if (! $schema->hasTable($table)) {
            $schema->create($table, function ($t) use ($column) {
                $t->id();
                $t->string('title')->nullable();
                $t->string('slug')->unique();
                $t->text($column)->nullable();
            });
        }
    }

    $mw = ServeMarkdownToBots::class;

    Route::middleware(['web', $mw])->group(function () {
        Route::get('/about', fn () => response('<html><body>About us in HTML.</body></html>'))->name('md.about');
        Route::get('/data', fn () => response()->json(['ok' => true]))->name('md.data');
        Route::get('/articles/{article}', fn (MdArticle $article) => response('<html><body>HTML body</body></html>'))->name('md.article');
        Route::get('/guides/{guide}', fn (MdGuide $guide) => response('<html><body>HTML body</body></html>'))->name('md.guide');
    });
});

afterEach(function () {
    SEO::markdown()->flush();
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('md_articles');
    $this->app['db']->connection()->getSchemaBuilder()->dropIfExists('md_guides');
});

describe('triggers', function () {
    it('serves a registered markdown source on ?format=md', function () {
        SEO::markdown()->register('md.about', "# About\n\nThe markdown version.");

        $this->get('/about?format=md')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# About', escape: false)
            ->assertDontSee('About us in HTML');
    });

    it('serves markdown on an explicit Accept: text/markdown', function () {
        SEO::markdown()->register('md.about', "# About\n\nThe markdown version.");

        $this->get('/about', ['Accept' => 'text/markdown'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# About', escape: false);
    });

    it('leaves a normal browser request as HTML', function () {
        SEO::markdown()->register('md.about', "# About\n\nThe markdown version.");

        $response = $this->get('/about', ['Accept' => 'text/html,application/xhtml+xml,*/*']);

        $response->assertOk()->assertSee('About us in HTML');
        expect($response->headers->get('Content-Type'))->toContain('text/html');
    });

    it('does nothing when the feature is disabled at runtime', function () {
        config(['seo.markdown_for_bots.enabled' => false]);
        SEO::markdown()->register('md.about', '# About');

        $this->get('/about?format=md')->assertOk()->assertSee('About us in HTML');
    });
});

describe('sources', function () {
    it('builds markdown from a HasSEO model\'s content when it has no toSeoMarkdown()', function () {
        $article = MdArticle::query()->create(['title' => 'Hello World', 'slug' => 'hello', 'content' => 'The article body in plain text.']);

        $this->get("/articles/{$article->id}?format=md")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# Hello World', escape: false)
            ->assertSee('The article body in plain text.', escape: false);
    });

    it('serves a model\'s own toSeoMarkdown() verbatim', function () {
        $guide = MdGuide::query()->create(['title' => 'Guide', 'slug' => 'g', 'body_md' => "# Clean Guide\n\nHand-written markdown."]);

        $this->get("/guides/{$guide->id}?format=md")
            ->assertOk()
            ->assertSee('# Clean Guide', escape: false)
            ->assertSee('Hand-written markdown.', escape: false);
    });

    it('does not replace a non-HTML (JSON) response', function () {
        SEO::markdown()->register('md.data', '# Should not be served');

        $response = $this->get('/data?format=md')->assertOk();

        expect($response->headers->get('Content-Type'))->toContain('application/json');
        $response->assertJson(['ok' => true]);
    });

    it('passes through when no markdown source resolves for the route', function () {
        // No registered source, no model — middleware must leave HTML alone.
        $this->get('/about?format=md')->assertOk()->assertSee('About us in HTML');
    });
});
