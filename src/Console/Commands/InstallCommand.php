<?php

declare(strict_types=1);

namespace Fibonoir\LaravelSEO\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'seo:install
                            {--force : Overwrite existing files}
                            {--stack= : Skip detection and use this stack (filament|livewire|vue|react|api)}
                            {--skip-migrations : Do not run migrations}
                            {--no-interaction : Use defaults without prompting}';

    /**
     * The console command description.
     */
    protected $description = 'Install the Laravel SEO suite with interactive configuration';

    /**
     * Track published files for rollback.
     *
     * @var array<int, string>
     */
    protected array $publishedFiles = [];

    /**
     * Track published directories for rollback.
     *
     * @var array<int, string>
     */
    protected array $publishedDirectories = [];

    /**
     * The selected stack.
     */
    protected string $stack = 'api';

    /**
     * Configuration gathered during installation.
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Valid stack options.
     *
     * @var array<int, string>
     */
    protected array $validStacks = ['filament', 'livewire', 'vue', 'react', 'api'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->showWelcome();

        try {
            // Step 1: Detect environment
            $environment = $this->detectEnvironment();

            // Step 2: Choose stack
            $this->stack = $this->determineStack($environment);

            // Step 3: Gather configuration
            $this->config = $this->gatherConfiguration();

            // Step 4: Publish files
            $this->publishFiles();

            // Step 5: Run migrations
            if (! $this->option('skip-migrations')) {
                $this->runMigrations();
            }

            // Step 6: Register middleware
            $this->registerMiddleware();

            // Step 7: Seed initial SEO defaults
            $this->seedDefaults();

            // Step 8: Post-install setup
            $this->postInstallSetup();

            // Step 9: Show success
            $this->showSuccess();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->rollback();
            error('Installation failed: ' . $e->getMessage());

            if ($this->output->isVerbose()) {
                $this->line('<fg=red>' . $e->getTraceAsString() . '</>');
            }

            return self::FAILURE;
        }
    }

    /**
     * Display the welcome banner.
     */
    protected function showWelcome(): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║                                                                  ║');
        $this->line('║   ██╗      █████╗ ██████╗  █████╗ ██╗   ██╗███████╗██╗           ║');
        $this->line('║   ██║     ██╔══██╗██╔══██╗██╔══██╗██║   ██║██╔════╝██║           ║');
        $this->line('║   ██║     ███████║██████╔╝███████║██║   ██║█████╗  ██║           ║');
        $this->line('║   ██║     ██╔══██║██╔══██╗██╔══██║╚██╗ ██╔╝██╔══╝  ██║           ║');
        $this->line('║   ███████╗██║  ██║██║  ██║██║  ██║ ╚████╔╝ ███████╗███████╗     ║');
        $this->line('║   ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═╝  ╚═╝  ╚═══╝  ╚══════╝╚══════╝     ║');
        $this->line('║                                                                  ║');
        $this->line('║              <fg=cyan>SEO Suite Installer</fg=cyan> v1.0                            ║');
        $this->line('║                                                                  ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->newLine();
        info("Welcome! Let's configure your SEO suite for optimal performance.");
        $this->newLine();
    }

    /**
     * Detect the current environment.
     *
     * @return array<string, mixed>
     */
    protected function detectEnvironment(): array
    {
        $this->showStepBox(1, 'Environment Detection');

        $environment = spin(
            callback: function () {
                return [
                    'laravel_version' => app()->version(),
                    'php_version' => PHP_VERSION,
                    'is_laravel_11' => version_compare(app()->version(), '11.0.0', '>='),
                    'has_filament' => $this->packageInstalled('filament/filament'),
                    'has_inertia' => $this->packageInstalled('inertiajs/inertia-laravel'),
                    'has_livewire' => $this->packageInstalled('livewire/livewire'),
                    'frontend_framework' => $this->detectFrontendFramework(),
                ];
            },
            message: 'Scanning your project...'
        );

        $this->newLine();
        $this->line('  <fg=green>✓</> Laravel <fg=cyan>' . $environment['laravel_version'] . '</> detected');
        $this->line('  <fg=green>✓</> PHP <fg=cyan>' . $environment['php_version'] . '</> detected');

        if ($environment['has_filament']) {
            $this->line('  <fg=green>✓</> Filament admin panel detected');
        }
        if ($environment['has_inertia']) {
            $this->line('  <fg=green>✓</> Inertia.js detected');
        }
        if ($environment['has_livewire']) {
            $this->line('  <fg=green>✓</> Livewire detected');
        }
        if ($environment['frontend_framework']) {
            $this->line('  <fg=green>✓</> ' . ucfirst($environment['frontend_framework']) . ' detected in package.json');
        }
        if (! $environment['has_filament'] && ! $environment['has_inertia'] && ! $environment['has_livewire'] && ! $environment['frontend_framework']) {
            $this->line('  <fg=yellow>→</> No specific frontend framework detected');
        }

        $this->newLine();

        return $environment;
    }

    /**
     * Determine which stack to use.
     *
     * @param  array<string, mixed>  $environment
     */
    protected function determineStack(array $environment): string
    {
        // If stack was provided via option, validate and use it
        if ($stack = $this->option('stack')) {
            return $this->validateStack($stack);
        }

        // Auto-suggest based on detection
        $suggested = $this->suggestStack($environment);

        if ($this->option('no-interaction')) {
            $this->line("  <fg=yellow>→</> Using auto-detected stack: <fg=cyan>{$suggested}</>");
            $this->newLine();

            return $suggested;
        }

        $this->showStepBox(2, 'Frontend Stack Selection');

        return select(
            label: 'Which frontend stack are you using?',
            options: [
                'filament' => 'Filament — Admin panel with Filament resources',
                'livewire' => 'Livewire — Blade templates with Livewire components',
                'vue' => 'Vue + Inertia — Vue 3 single-file components',
                'react' => 'React + Inertia — React with TypeScript support',
                'api' => 'API Only — Headless mode, no frontend components',
            ],
            default: $suggested,
            hint: $suggested !== 'api' ? "Detected: {$suggested}" : 'Select the stack that matches your project'
        );
    }

    /**
     * Gather configuration from user input.
     *
     * @return array<string, mixed>
     */
    protected function gatherConfiguration(): array
    {
        $this->showStepBox(3, 'Configuration');

        $defaults = [
            'stack' => $this->stack,
            'site_name' => config('app.name', 'My Website'),
            'enable_analytics' => false,
            'enable_sitemap' => true,
            'enable_schema' => true,
            'enable_multilingual' => false,
        ];

        if ($this->option('no-interaction')) {
            $this->line('  <fg=yellow>→</> Using default configuration');
            $this->newLine();

            return $defaults;
        }

        $config = ['stack' => $this->stack];

        $config['site_name'] = text(
            label: 'What is your site name?',
            default: config('app.name', 'My Website'),
            hint: 'Used for meta tags, Open Graph, and schema markup',
            validate: fn(string $value) => strlen($value) < 2
                ? 'Site name must be at least 2 characters'
                : null
        );

        $config['enable_analytics'] = confirm(
            label: 'Enable Google Analytics (GA4) integration?',
            default: false,
            hint: 'Requires GA4 property ID and service account credentials'
        );

        if ($config['enable_analytics']) {
            $config['ga4_property_id'] = text(
                label: 'GA4 Property ID (optional)',
                placeholder: 'G-XXXXXXXXXX or 123456789',
                hint: 'Leave empty to configure later via .env'
            );
        }

        $config['enable_sitemap'] = confirm(
            label: 'Enable automatic sitemap generation?',
            default: true,
            hint: 'Creates XML sitemaps for search engines'
        );

        $config['enable_schema'] = confirm(
            label: 'Enable JSON-LD schema markup?',
            default: true,
            hint: 'Adds structured data for rich snippets'
        );

        $config['enable_multilingual'] = confirm(
            label: 'Enable multilingual support (hreflang tags)?',
            default: false,
            hint: 'For sites with multiple language versions'
        );

        $this->newLine();

        return $config;
    }

    /**
     * Publish package files.
     */
    protected function publishFiles(): void
    {
        $this->showStepBox(4, 'Publishing Files');

        $force = (bool) $this->option('force');

        // Publish config with stub replacement
        $this->publishConfigFromStub($force);

        // Publish migrations
        $this->publishMigrations($force);

        // Publish stack-specific files
        match ($this->stack) {
            'filament' => $this->publishFilamentFiles($force),
            'livewire' => $this->publishLivewireFiles($force),
            'vue' => $this->publishVueFiles($force),
            'react' => $this->publishReactFiles($force),
            'api' => $this->line('  <fg=yellow>→</> Skipping frontend components (API mode)'),
        };

        $this->newLine();
    }

    /**
     * Publish config file from stub with variable replacement.
     */
    protected function publishConfigFromStub(bool $force): void
    {
        $stubPath = __DIR__ . '/../../../stubs/config.stub';
        $targetPath = config_path('seo.php');

        if (File::exists($targetPath) && ! $force) {
            $this->line('  <fg=yellow>→</> Config already exists (use --force to overwrite)');

            return;
        }

        if (File::exists($stubPath)) {
            $content = File::get($stubPath);
            $content = $this->replaceStubVariables($content);
            $this->publishFile($stubPath, $targetPath, $content);
            $this->line('  <fg=green>✓</> Published config/seo.php');
        } else {
            // Fallback to standard publish
            $this->call('vendor:publish', [
                '--tag' => 'seo-config',
                '--force' => $force,
            ]);
            $this->trackPublishedFile($targetPath);
            $this->line('  <fg=green>✓</> Published config/seo.php');
        }
    }

    /**
     * Publish migrations.
     */
    protected function publishMigrations(bool $force): void
    {
        $migrationPath = database_path('migrations');
        $sourcePath = __DIR__ . '/../../../database/migrations';

        if (! File::isDirectory($sourcePath)) {
            $this->line('  <fg=yellow>→</> No migrations to publish');

            return;
        }

        $files = File::files($sourcePath);
        $publishedCount = 0;

        foreach ($files as $file) {
            $filename = $file->getFilename();
            // Check if migration with similar name already exists
            $existingMigrations = File::glob($migrationPath . '/*_' . Str::after($filename, '_000001_'));

            if (! empty($existingMigrations) && ! $force) {
                continue;
            }

            $targetFile = $migrationPath . '/' . $filename;
            $this->publishFile($file->getPathname(), $targetFile);
            $publishedCount++;
        }

        if ($publishedCount > 0) {
            $this->line("  <fg=green>✓</> Published {$publishedCount} migration(s)");
        } else {
            $this->line('  <fg=yellow>→</> Migrations already exist');
        }
    }

    /**
     * Publish Filament-specific files.
     */
    protected function publishFilamentFiles(bool $force): void
    {
        $sourceDir = __DIR__ . '/../../../stubs/filament';
        $targetDir = app_path('Filament');

        if (File::isDirectory($sourceDir) && count(File::allFiles($sourceDir)) > 0) {
            $this->publishDirectory($sourceDir, $targetDir, $force);
            $this->line('  <fg=green>✓</> Published Filament resources to app/Filament/');
        } else {
            // Fallback to vendor:publish
            $this->callSilent('vendor:publish', [
                '--tag' => 'seo-filament',
                '--force' => $force,
            ]);
            $this->line('  <fg=green>✓</> Published Filament resources');
        }
    }

    /**
     * Publish Livewire-specific files.
     */
    protected function publishLivewireFiles(bool $force): void
    {
        $sourceDir = __DIR__ . '/../../../stubs/livewire';
        $targetDir = app_path('Livewire/Seo');

        if (File::isDirectory($sourceDir) && count(File::allFiles($sourceDir)) > 0) {
            $this->publishDirectory($sourceDir, $targetDir, $force);
            $this->line('  <fg=green>✓</> Published Livewire components to app/Livewire/Seo/');
        } else {
            $this->callSilent('vendor:publish', [
                '--tag' => 'seo-livewire',
                '--force' => $force,
            ]);
            $this->line('  <fg=green>✓</> Published Livewire components');
        }

        // Publish views
        $this->callSilent('vendor:publish', [
            '--tag' => 'seo-views',
            '--force' => $force,
        ]);
        $this->line('  <fg=green>✓</> Published Blade views to resources/views/vendor/seo/');
    }

    /**
     * Publish Vue-specific files.
     */
    protected function publishVueFiles(bool $force): void
    {
        $sourceDir = __DIR__ . '/../../../resources/js/vue';
        $targetDir = resource_path('js/Components/SEO');

        if (File::isDirectory($sourceDir) && count(File::allFiles($sourceDir)) > 0) {
            $this->publishDirectory($sourceDir, $targetDir, $force);
            $this->line('  <fg=green>✓</> Published Vue components to resources/js/Components/SEO/');
        } else {
            $this->callSilent('vendor:publish', [
                '--tag' => 'seo-vue',
                '--force' => $force,
            ]);
            $this->line('  <fg=green>✓</> Published Vue components');
        }

        warning('  → Run: npm install @unhead/vue');
    }

    /**
     * Publish React-specific files.
     */
    protected function publishReactFiles(bool $force): void
    {
        $sourceDir = __DIR__ . '/../../../resources/js/react';
        $targetDir = resource_path('js/Components/SEO');

        if (File::isDirectory($sourceDir) && count(File::allFiles($sourceDir)) > 0) {
            $this->publishDirectory($sourceDir, $targetDir, $force);
            $this->line('  <fg=green>✓</> Published React components to resources/js/Components/SEO/');
        } else {
            $this->callSilent('vendor:publish', [
                '--tag' => 'seo-react',
                '--force' => $force,
            ]);
            $this->line('  <fg=green>✓</> Published React components');
        }

        warning('  → Run: npm install react-helmet-async');
    }

    /**
     * Run migrations.
     */
    protected function runMigrations(): void
    {
        $this->showStepBox(5, 'Database Migrations');

        if (! $this->option('no-interaction') && ! confirm('Run database migrations now?', true)) {
            warning('  → Skipped migrations. Run `php artisan migrate` later.');
            $this->newLine();

            return;
        }

        spin(
            callback: function () {
                Artisan::call('migrate', ['--force' => true]);
            },
            message: 'Running migrations...'
        );

        $this->newLine();
        $this->line('  <fg=green>✓</> Migrations completed successfully');
        $this->newLine();
    }

    /**
     * Register middleware for Laravel 11+.
     */
    protected function registerMiddleware(): void
    {
        $this->showStepBox(6, 'Middleware Registration');

        // Check Laravel version
        $isLaravel11 = version_compare(app()->version(), '11.0.0', '>=');

        if ($isLaravel11) {
            $this->registerMiddlewareLaravel11();
        } else {
            $this->line('  <fg=green>✓</> Middleware aliases registered via service provider');
            $this->line('  <fg=gray>   Add to routes: ->middleware([\'seo.redirect\', \'seo.404\'])</>');
        }

        $this->newLine();
    }

    /**
     * Register middleware in bootstrap/app.php for Laravel 11+.
     */
    protected function registerMiddlewareLaravel11(): void
    {
        $bootstrapPath = base_path('bootstrap/app.php');

        if (! File::exists($bootstrapPath)) {
            $this->line('  <fg=yellow>→</> bootstrap/app.php not found');
            $this->line('  <fg=gray>   Add middleware manually if needed</>');

            return;
        }

        $content = File::get($bootstrapPath);

        // Check if middleware is already registered
        if (str_contains($content, 'seo.redirect') || str_contains($content, 'RedirectMiddleware')) {
            $this->line('  <fg=green>✓</> Middleware already registered');

            return;
        }

        // Look for withMiddleware section
        if (str_contains($content, '->withMiddleware(')) {
            $middlewareCode = <<<'PHP'

        // SEO Middleware
        $middleware->alias([
            'seo.redirect' => \Fibonoir\LaravelSEO\Http\Middleware\RedirectMiddleware::class,
            'seo.404' => \Fibonoir\LaravelSEO\Http\Middleware\Log404Middleware::class,
        ]);
PHP;

            // Find the withMiddleware closure and add our aliases
            $pattern = '/(->withMiddleware\s*\(\s*function\s*\(\s*Middleware\s+\$middleware\s*\)\s*\{)/';
            if (preg_match($pattern, $content)) {
                $content = preg_replace(
                    $pattern,
                    '$1' . $middlewareCode,
                    $content
                );

                File::put($bootstrapPath, $content);
                $this->trackPublishedFile($bootstrapPath);

                $this->line('  <fg=green>✓</> Registered middleware aliases in bootstrap/app.php');
                $this->line('  <fg=gray>   Available: seo.redirect, seo.404</>');

                return;
            }
        }

        // Fallback - show manual instructions
        $this->line('  <fg=green>✓</> Middleware aliases available via service provider');
        $this->line('  <fg=gray>   Apply to routes: Route::middleware([\'seo.redirect\', \'seo.404\'])</>');
    }

    /**
     * Seed initial SEO defaults.
     */
    protected function seedDefaults(): void
    {
        $this->showStepBox(7, 'Initial Setup');

        // Check if table exists
        if (! Schema::hasTable('seo_defaults')) {
            warning('  → Skipping defaults seeding (table not found). Run migrations first.');
            $this->newLine();

            return;
        }

        try {
            // Check if defaults already exist
            $existingCount = DB::table('seo_defaults')->count();

            if ($existingCount > 0) {
                $this->line('  <fg=yellow>→</> SEO defaults already configured');
                $this->newLine();

                return;
            }

            // Seed default values
            $siteName = $this->config['site_name'] ?? config('app.name', 'My Website');

            DB::table('seo_defaults')->insert([
                [
                    'route_pattern' => '*',
                    'key' => 'site_name',
                    'value' => json_encode($siteName),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'route_pattern' => '*',
                    'key' => 'title_suffix',
                    'value' => json_encode(' | ' . $siteName),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'route_pattern' => 'home',
                    'key' => 'title',
                    'value' => json_encode($siteName . ' - Welcome'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'route_pattern' => 'home',
                    'key' => 'description',
                    'value' => json_encode('Welcome to ' . $siteName . '. Discover our products and services.'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $this->line('  <fg=green>✓</> Seeded initial SEO defaults');
        } catch (\Exception $e) {
            warning('  → Could not seed defaults: ' . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Perform post-install setup.
     */
    protected function postInstallSetup(): void
    {
        $this->showStepBox(8, 'Finalizing');

        // Update .env if analytics is enabled
        if (($this->config['enable_analytics'] ?? false)) {
            $envUpdates = ['SEO_GA4_ENABLED' => 'true'];

            if (! empty($this->config['ga4_property_id'])) {
                $envUpdates['SEO_GA4_PROPERTY_ID'] = $this->config['ga4_property_id'];
            }

            $this->updateEnvFile($envUpdates);
            $this->line('  <fg=green>✓</> Updated .env with analytics settings');
        }

        // Clear config cache
        if (File::exists(base_path('bootstrap/cache/config.php'))) {
            Artisan::call('config:clear');
            $this->line('  <fg=green>✓</> Cleared config cache');
        }

        $this->line('  <fg=green>✓</> Installation finalized');
        $this->newLine();
    }

    /**
     * Show the success message.
     */
    protected function showSuccess(): void
    {
        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║                                                                  ║');
        $this->line('║     <fg=green>✓ Installation Complete!</>                                    ║');
        $this->line('║                                                                  ║');
        $this->line('║     Stack: <fg=cyan>' . str_pad($this->stack, 52) . '</>║');
        $this->line('║                                                                  ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $nextSteps = $this->getNextSteps();

        note('Next Steps');
        $this->newLine();

        foreach ($nextSteps as $index => $step) {
            $stepNum = str_pad((string) ($index + 1), 2, ' ', STR_PAD_LEFT);
            $this->line("  <fg=cyan>{$stepNum}.</> {$step['title']}");
            if (isset($step['code'])) {
                $this->line("      <fg=gray>{$step['code']}</>");
            }
            $this->newLine();
        }

        $this->line('┌──────────────────────────────────────────────────────────────────┐');
        $this->line('│  <fg=cyan>Documentation:</> https://github.com/fibonoir/laravel-seo          │');
        $this->line('│  <fg=cyan>Issues:</> https://github.com/fibonoir/laravel-seo/issues          │');
        $this->line('└──────────────────────────────────────────────────────────────────┘');
        $this->newLine();
    }

    /**
     * Get contextual next steps based on stack.
     *
     * @return array<int, array{title: string, code?: string}>
     */
    protected function getNextSteps(): array
    {
        $steps = [];

        // Model trait step
        $steps[] = [
            'title' => 'Add the HasSEO trait to your Eloquent models',
            'code' => 'use Fibonoir\\LaravelSEO\\Traits\\HasSEO;',
        ];

        // Stack-specific step
        match ($this->stack) {
            'filament' => $steps[] = [
                'title' => 'Add SEO form fields to your Filament resources',
                'code' => 'SEOFields::make() // In your form schema',
            ],
            'livewire' => $steps[] = [
                'title' => 'Include the SEO form component in your views',
                'code' => '<livewire:seo.seo-form :model="$post" />',
            ],
            'vue' => $steps[] = [
                'title' => 'Import and use SEO components in your Vue pages',
                'code' => "import { SEOForm, SEOHead } from '@/Components/SEO'",
            ],
            'react' => $steps[] = [
                'title' => 'Import and use SEO components in your React pages',
                'code' => "import { SEOForm, SEOHead } from '@/Components/SEO'",
            ],
            default => null,
        };

        // Add SEO blade directive
        $steps[] = [
            'title' => 'Add SEO meta tags to your layout',
            'code' => "@seo(\$model) {{-- In your <head> section --}}",
        ];

        // Analytics config if enabled
        if ($this->config['enable_analytics'] ?? false) {
            $steps[] = [
                'title' => 'Configure GA4 credentials',
                'code' => 'SEO_GA4_CREDENTIALS_PATH=storage/app/google-credentials.json',
            ];
        }

        // Sitemap generation
        if ($this->config['enable_sitemap'] ?? true) {
            $steps[] = [
                'title' => 'Generate your first sitemap',
                'code' => 'php artisan seo:sitemap',
            ];
        }

        // Health check
        $steps[] = [
            'title' => 'Run a health check to verify installation',
            'code' => 'php artisan seo:health',
        ];

        return $steps;
    }

    /**
     * Rollback published files on failure.
     */
    protected function rollback(): void
    {
        if (empty($this->publishedFiles) && empty($this->publishedDirectories)) {
            return;
        }

        warning('Rolling back published files...');

        // Delete published files
        foreach ($this->publishedFiles as $file) {
            if (File::exists($file)) {
                File::delete($file);
                $this->line("  <fg=red>✗</> Removed: {$file}");
            }
        }

        // Delete published directories (only if empty)
        foreach (array_reverse($this->publishedDirectories) as $dir) {
            if (File::isDirectory($dir) && count(File::allFiles($dir)) === 0) {
                File::deleteDirectory($dir);
                $this->line("  <fg=red>✗</> Removed directory: {$dir}");
            }
        }

        $this->newLine();
    }

    /**
     * Check if a Composer package is installed.
     */
    protected function packageInstalled(string $package): bool
    {
        $composerLock = base_path('composer.lock');

        if (! File::exists($composerLock)) {
            return false;
        }

        try {
            $lock = json_decode(File::get($composerLock), true);
            $packages = array_merge(
                $lock['packages'] ?? [],
                $lock['packages-dev'] ?? []
            );

            return collect($packages)->contains('name', $package);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Detect the frontend framework from package.json.
     */
    protected function detectFrontendFramework(): ?string
    {
        $packageJson = base_path('package.json');

        if (! File::exists($packageJson)) {
            return null;
        }

        try {
            $package = json_decode(File::get($packageJson), true);
            $deps = array_merge(
                $package['dependencies'] ?? [],
                $package['devDependencies'] ?? []
            );

            if (isset($deps['vue'])) {
                return 'vue';
            }
            if (isset($deps['react'])) {
                return 'react';
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Suggest the best stack based on environment.
     *
     * @param  array<string, mixed>  $environment
     */
    protected function suggestStack(array $environment): string
    {
        // Priority: Filament > Inertia+Vue/React > Livewire > API
        if ($environment['has_filament']) {
            return 'filament';
        }

        if ($environment['has_inertia']) {
            return match ($environment['frontend_framework']) {
                'vue' => 'vue',
                'react' => 'react',
                default => 'vue', // Default to Vue for Inertia
            };
        }

        if ($environment['has_livewire']) {
            return 'livewire';
        }

        if ($environment['frontend_framework']) {
            return $environment['frontend_framework'];
        }

        return 'api';
    }

    /**
     * Validate the stack option.
     */
    protected function validateStack(string $stack): string
    {
        $stack = strtolower(trim($stack));

        if (! in_array($stack, $this->validStacks)) {
            throw new \InvalidArgumentException(
                "Invalid stack '{$stack}'. Must be one of: " . implode(', ', $this->validStacks)
            );
        }

        return $stack;
    }

    /**
     * Replace stub variables with actual values.
     */
    protected function replaceStubVariables(string $content): string
    {
        $replacements = [
            '{{stack}}' => $this->stack,
            '{{site_name}}' => $this->config['site_name'] ?? config('app.name', 'My Website'),
            '{{enable_analytics}}' => ($this->config['enable_analytics'] ?? false) ? 'true' : 'false',
            '{{enable_sitemap}}' => ($this->config['enable_sitemap'] ?? true) ? 'true' : 'false',
            '{{enable_schema}}' => ($this->config['enable_schema'] ?? true) ? 'true' : 'false',
            '{{enable_multilingual}}' => ($this->config['enable_multilingual'] ?? false) ? 'true' : 'false',
            '{{namespace}}' => app()->getNamespace(),
            '{{date}}' => now()->format('Y-m-d'),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $content
        );
    }

    /**
     * Publish a single file.
     */
    protected function publishFile(string $source, string $target, ?string $content = null): void
    {
        $directory = dirname($target);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
            $this->trackPublishedDirectory($directory);
        }

        $content = $content ?? File::get($source);
        File::put($target, $content);

        $this->trackPublishedFile($target);
    }

    /**
     * Publish a directory of files.
     */
    protected function publishDirectory(string $source, string $target, bool $force = false): void
    {
        if (! File::isDirectory($source)) {
            return;
        }

        if (! File::isDirectory($target)) {
            File::makeDirectory($target, 0755, true);
            $this->trackPublishedDirectory($target);
        }

        $files = File::allFiles($source);

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $targetFile = $target . '/' . $relativePath;

            if (File::exists($targetFile) && ! $force) {
                continue;
            }

            $content = File::get($file->getPathname());

            // Replace stub variables if it's a stub file or PHP file
            if (Str::endsWith($file->getFilename(), ['.stub', '.php'])) {
                $content = $this->replaceStubVariables($content);
            }

            $this->publishFile($file->getPathname(), $targetFile, $content);
        }
    }

    /**
     * Track a published file for potential rollback.
     */
    protected function trackPublishedFile(string $path): void
    {
        $this->publishedFiles[] = $path;
    }

    /**
     * Track a published directory for potential rollback.
     */
    protected function trackPublishedDirectory(string $path): void
    {
        $this->publishedDirectories[] = $path;
    }

    /**
     * Update the .env file with values.
     *
     * @param  array<string, string>  $values
     */
    protected function updateEnvFile(array $values): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $content = File::get($envPath);
        $newLines = [];

        foreach ($values as $key => $value) {
            // Escape value if it contains spaces
            $escapedValue = str_contains($value, ' ') ? "\"{$value}\"" : $value;

            if (preg_match("/^{$key}=.*/m", $content)) {
                // Key exists, update it
                $content = preg_replace(
                    "/^{$key}=.*/m",
                    "{$key}={$escapedValue}",
                    $content
                );
            } else {
                // Key doesn't exist, add to new lines
                $newLines[] = "{$key}={$escapedValue}";
            }
        }

        // Append new lines
        if (! empty($newLines)) {
            $content = rtrim($content) . "\n\n# SEO Suite Configuration\n" . implode("\n", $newLines) . "\n";
        }

        File::put($envPath, $content);
    }

    /**
     * Display a step header box.
     */
    protected function showStepBox(int $step, string $title): void
    {
        $this->line("┌─────────────────────────────────────────────────────────────────┐");
        $this->line("│  <fg=cyan>Step {$step}:</> " . str_pad($title, 53) . '│');
        $this->line("└─────────────────────────────────────────────────────────────────┘");
    }
}
