<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Lang;
use Rankbeam\Seo\I18n\DisplayLocale;
use Rankbeam\Seo\I18n\ModelLocale;

it('supports an explicit CLI display locale and restores the application locale', function () {
    app()->setLocale('ja');
    Lang::addLines(['seo.cli.audit.no_models' => 'Nessun modello da controllare.'], 'it', 'seo');
    Artisan::call('seo:audit', ['--display-locale' => 'it']);
    expect(Artisan::output())->toContain('Nessun modello da controllare.')
        ->and(app()->getLocale())->toBe('ja')
        ->and(app('translator')->getLocale())->toBe('ja');
});

it('defaults CLI presentation to English without changing the content locale', function () {
    app()->setLocale('it');
    Artisan::call('seo:audit');
    expect(Artisan::output())->toContain('No models')
        ->and(app()->getLocale())->toBe('it')
        ->and(app('translator')->getLocale())->toBe('it');
});

it('restores separate display and content locales after nested failures', function () {
    app()->setLocale('it');
    app('translator')->setLocale('pl');
    try {
        DisplayLocale::run('ja', function () {
            expect(app()->getLocale())->toBe('it');
            ModelLocale::run(new class extends Model {}, 'tr', function ($model) {
                expect(app()->getLocale())->toBe('tr');
                ModelLocale::run($model, 'zh_CN', function () {
                    expect(app()->getLocale())->toBe('zh_CN');
                    throw new RuntimeException('fixture failure');
                }, false);
            }, false);
        });
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('fixture failure');
    }
    expect(app()->getLocale())->toBe('it')
        ->and(app('translator')->getLocale())->toBe('pl');
});

it('loads a published vendor translation in audit output', function () {
    $root = sys_get_temp_dir().'/rankbeam-lang-'.bin2hex(random_bytes(8));
    $directory = $root.'/vendor/seo/it';
    mkdir($directory, 0777, true);
    $file = $directory.'/seo.php';
    file_put_contents($file, "<?php return ['cli' => ['audit' => ['no_models' => 'Controllo editoriale personalizzato']]];");
    try {
        app('translation.loader')->addPath($root);
        app('translator')->setLoaded([]);
        Artisan::call('seo:audit', ['--display-locale' => 'it']);
        expect(Artisan::output())->toContain('Controllo editoriale personalizzato');
    } finally {
        unlink($file);
        rmdir($directory);
        rmdir(dirname($directory));
        rmdir($root.'/vendor');
        rmdir($root);
    }
});
