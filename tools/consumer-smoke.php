<?php

declare(strict_types=1);
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Rankbeam\Seo\I18n\CaseFolder;
use Rankbeam\Seo\I18n\Script;

// A production-only consumer: no Testbench, Pro, Filament or Chrome dependency.
$mode = $argv[1] ?? '';
$root = $argv[2] ?? '';
if ($mode === 'prepare') {
    if ($root === '' || is_dir($root)) {
        throw new RuntimeException('Pass a new isolated directory.');
    }
    foreach (['bootstrap/cache', 'config', 'database/migrations', 'storage/framework/views', 'storage/logs'] as $dir) {
        mkdir($root.'/'.$dir, 0777, true);
    }
    file_put_contents($root.'/.rankbeam-consumer-fixture', "isolated test application\n");
    file_put_contents($root.'/composer.json', json_encode([
        'name' => 'rankbeam-evidence/core-consumer',
        'require' => ['laravel/framework' => $argv[3] ?? '^12.0', 'rankbeam/laravel-seo' => 'dev-consumer'],
        'repositories' => [['type' => 'path', 'url' => str_replace('\\', '/', dirname(__DIR__)), 'options' => [
            'symlink' => false, 'versions' => ['rankbeam/laravel-seo' => 'dev-consumer'],
        ]]],
        'config' => ['allow-plugins' => false],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    exit;
}
if ($mode !== 'run' || ! is_file($root.'/.rankbeam-consumer-fixture')) {
    throw new RuntimeException('Pass run and a prepared isolated consumer.');
}
require $root.'/vendor/autoload.php';
$expectedIntl = getenv('RANKBEAM_EXPECT_INTL');
if ($expectedIntl !== false && extension_loaded('intl') !== ($expectedIntl === '1')) {
    throw new RuntimeException('The requested ICU test environment was not established.');
}
$app = Application::configure(basePath: $root)->withExceptions()->withMiddleware()->create();
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'sqlite', 'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    'cache.default' => 'array', 'session.driver' => 'array', 'seo.features.auto_create_meta' => false,
]);
Artisan::call('vendor:publish', ['--tag' => 'seo-migrations', '--force' => true]);
Artisan::call('migrate', ['--force' => true]);
Artisan::call('list', ['--raw' => true]);
if (! str_contains(Artisan::output(), 'seo:audit')) {
    throw new RuntimeException('Core commands did not boot.');
}
$scripts = Script::present('Laravel 東京');
if ($scripts !== ['cjk', 'latin'] || CaseFolder::fold('İSTANBUL', 'tr') !== 'istanbul') {
    throw new RuntimeException('Unicode helpers failed in the production consumer.');
}
foreach (['orchestra/testbench', 'filament/filament', 'wamania/php-stemmer', 'spatie/browsershot'] as $extra) {
    if (InstalledVersions::isInstalled($extra)) {
        throw new RuntimeException('Unexpected development/optional dependency: '.$extra);
    }
}
echo json_encode(['php' => PHP_VERSION, 'intl' => extension_loaded('intl'), 'framework' => app()->version(),
    'core' => InstalledVersions::getPrettyVersion('rankbeam/laravel-seo'), 'scripts' => $scripts], JSON_PRETTY_PRINT).PHP_EOL;
