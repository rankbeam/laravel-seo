<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Rankbeam\Seo\Data\SEOData;
use Rankbeam\Seo\I18n\Script;
use Rankbeam\Seo\Services\OgImage\FontProbe;
use Rankbeam\Seo\Services\OgImage\OgImageGenerator;
use Rankbeam\Seo\Services\OgImage\OgImageManager;
use Rankbeam\Seo\Tests\Support\FakeOgImageRenderer;
use Rankbeam\Seo\Traits\HasSEO;

/*
|--------------------------------------------------------------------------
| OG images: per-script font stack + the seo:og-images font pre-flight (M2)
|--------------------------------------------------------------------------
*/

class FontStackArticle extends Model
{
    use HasSEO;

    protected $table = 'font_stack_articles';

    protected $fillable = ['title', 'slug'];

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

/** A probe with scripted answers, so the command's warning is testable without fontconfig. */
class ScriptedFontProbe extends FontProbe
{
    /** @var array<string, bool|null> */
    public static array $answers = [];

    public static array $asked = [];

    public function covers(string $script): ?bool
    {
        self::$asked[] = $script;

        return self::$answers[$script] ?? null;
    }
}

beforeEach(function () {
    $schema = $this->app['db']->connection()->getSchemaBuilder();
    $schema->dropIfExists('font_stack_articles');
    $schema->create('font_stack_articles', function ($table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->string('slug');
    });

    Storage::fake('og_font', ['url' => 'http://localhost/og', 'visibility' => 'public']);
    config([
        'seo.og_image.enabled' => true,
        'seo.og_image.driver' => 'fake',
        'seo.og_image.disk' => 'og_font',
        'seo.og_image.path' => 'og-images',
        'seo.og_image.models' => [FontStackArticle::class],
        'seo.og_image.font_stack' => null,
        'seo.title_suffix' => '',
        'seo.features.auto_create_meta' => false,
    ]);
    FakeOgImageRenderer::reset();
    app(OgImageManager::class)->extend('fake', fn () => new FakeOgImageRenderer);

    ScriptedFontProbe::$answers = [];
    ScriptedFontProbe::$asked = [];
    app()->bind(FontProbe::class, fn () => new ScriptedFontProbe);
});

function fontGenerator(): OgImageGenerator
{
    return app(OgImageGenerator::class);
}

function renderedHtml(SEOData $data, ?string $template = null): string
{
    fontGenerator()->generate($data, $template, force: true);

    return FakeOgImageRenderer::$lastHtml ?? '';
}

describe('font-family in the rendered template', function () {
    it('lists the bundled face, then the fallback stack, then sans-serif', function () {
        $html = renderedHtml(new SEOData(title: 'Hello', locale: 'en'));

        expect($html)->toContain("font-family: 'OGBrand', 'Noto Sans', 'Noto Sans CJK JP', 'Noto Sans CJK SC', 'Noto Sans CJK TC', 'Noto Sans CJK KR', 'Noto Sans Thai', 'Noto Sans Arabic', 'Noto Sans Hebrew', 'Noto Sans Devanagari', 'Noto Color Emoji', sans-serif;")
            ->and($html)->toContain('<html lang="en">');
    });

    it('moves the page language CJK family to the front and stamps a BCP 47 lang', function (string $locale, string $family, string $lang) {
        $html = renderedHtml(new SEOData(title: '見出し', locale: $locale));

        expect($html)->toContain("font-family: 'OGBrand', '{$family}', ")
            ->and($html)->toContain('<html lang="'.$lang.'">');
    })->with([
        ['ja', 'Noto Sans CJK JP', 'ja'],
        ['ja_JP', 'Noto Sans CJK JP', 'ja-JP'],
        ['zh_CN', 'Noto Sans CJK SC', 'zh-CN'],
        ['zh-Hans', 'Noto Sans CJK SC', 'zh-Hans'],
        ['zh_TW', 'Noto Sans CJK TC', 'zh-TW'],
        ['zh-Hant-HK', 'Noto Sans CJK TC', 'zh-Hant-HK'],
        ['ko_KR', 'Noto Sans CJK KR', 'ko-KR'],
    ]);

    it('applies to the article and product templates too', function () {
        foreach (['seo::og.article', 'seo::og.product'] as $template) {
            $html = renderedHtml(new SEOData(title: '見出し', locale: 'ko'), $template);

            expect($html)->toContain("font-family: 'OGBrand', 'Noto Sans CJK KR', ")
                ->and($html)->toContain('<html lang="ko">');
        }
    });

    it('uses a configured stack and neutralises characters that could break the declaration', function () {
        config(['seo.og_image.font_stack' => ['My Brand Sans', "Evil'; } body { display:none", '', 42, 'My Brand Sans']]);

        expect(fontGenerator()->fontStack())->toBe(['My Brand Sans', 'Evil  body  display:none'])
            ->and(renderedHtml(new SEOData(title: 'Hello', locale: 'en')))->toContain("font-family: 'OGBrand', 'My Brand Sans', 'Evil  body  display:none', sans-serif;");
    });

    it('falls back to the app locale for the lang attribute', function () {
        app()->setLocale('pt_BR');

        expect(renderedHtml(new SEOData(title: 'Olá')))->toContain('<html lang="pt-BR">');
    });

    it('busts the cache when the font stack changes', function () {
        $data = new SEOData(title: 'Hello', locale: 'en');
        $before = fontGenerator()->cacheKey($data);

        config(['seo.og_image.font_stack' => ['Another Sans']]);

        expect(fontGenerator()->cacheKey($data))->not->toBe($before);
    });
});

describe('FontProbe', function () {
    it('checks all mixed-text scripts and does not assume the OG font is embedded in reports', function () {
        $probe = new class extends FontProbe
        {
            protected function query(string $lang): ?bool
            {
                return false;
            }
        };
        expect(Script::present('English 東京 ภาษาไทย 。'))->toBe(['cjk', 'latin', 'thai'])
            ->and(Script::present('123 。'))->toBe([])
            ->and($probe->missingForText('English 東京'))->toBe(['cjk' => 'apt-get install fonts-noto-cjk'])
            ->and($probe->missingForText('English 東京', false))->toBe([
                'cjk' => 'apt-get install fonts-noto-cjk', 'latin' => 'apt-get install fonts-noto-core',
            ]);
    });

    it('treats the bundled scripts as covered without asking the host', function () {
        $probe = new FontProbe;

        foreach (FontProbe::BUNDLED as $script) {
            expect($probe->covers($script))->toBeTrue();
        }

        expect($probe->covers('klingon'))->toBeNull()
            ->and($probe->installHint(Script::CJK))->toBe('apt-get install fonts-noto-cjk')
            ->and($probe->installHint(Script::THAI))->toBe('apt-get install fonts-noto-core');
    });

    it('answers unknown rather than false where fontconfig is absent', function () {
        $probe = new class extends FontProbe
        {
            protected function query(string $lang): ?bool
            {
                return null;
            }
        };

        expect($probe->covers(Script::CJK))->toBeNull();
    });
});

describe('seo:og-images font pre-flight', function () {
    it('warns for a short minority script in a mostly Latin title', function () {
        FontStackArticle::create(['title' => 'A complete technical optimization guide for Tokyo 東京', 'slug' => 'mixed']);
        ScriptedFontProbe::$answers = [Script::CJK => false, Script::LATIN => true];
        Artisan::call('seo:og-images');
        expect(Artisan::output())->toContain('No installed font covers cjk text');
    });

    it('warns once per uncovered script with the install hint', function () {
        FontStackArticle::create(['title' => '検索エンジン最適化', 'slug' => 'ja-1']);
        FontStackArticle::create(['title' => '日本語のタイトル', 'slug' => 'ja-2']);
        FontStackArticle::create(['title' => 'คู่มือภาษาไทย', 'slug' => 'th']);
        FontStackArticle::create(['title' => 'Plain Latin', 'slug' => 'en']);
        ScriptedFontProbe::$answers = [Script::CJK => false, Script::THAI => true];

        $exit = Artisan::call('seo:og-images');
        $output = Artisan::output();

        expect($exit)->toBe(0)
            ->and($output)->toContain('No installed font covers cjk text')
            ->and($output)->toContain('apt-get install fonts-noto-cjk')
            ->and(substr_count($output, 'No installed font'))->toBe(1)
            ->and(array_count_values(ScriptedFontProbe::$asked))->toMatchArray([Script::CJK => 1, Script::THAI => 1, Script::LATIN => 1])
            ->and(FakeOgImageRenderer::$calls)->toBe(4);
    });

    it('stays silent when the host cannot say or the font is present', function () {
        FontStackArticle::create(['title' => '検索エンジン最適化', 'slug' => 'ja-1']);
        ScriptedFontProbe::$answers = [Script::CJK => null];

        $exit = Artisan::call('seo:og-images');

        expect($exit)->toBe(0)
            ->and(Artisan::output())->not->toContain('No installed font');
    });
});
