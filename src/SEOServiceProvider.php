<?php

declare(strict_types=1);

namespace Rankbeam\Seo;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Rankbeam\Seo\AiCrawlers\AiCrawlerRegistry;
use Rankbeam\Seo\Console\Commands\AuditCommand;
use Rankbeam\Seo\Console\Commands\ImportFromCommand;
use Rankbeam\Seo\Console\Commands\LlmsTxtCommand;
use Rankbeam\Seo\Console\Commands\RobotsTxtCommand;
use Rankbeam\Seo\Console\Commands\SitemapCommand;
use Rankbeam\Seo\Http\Middleware\ServeMarkdownToBots;
use Rankbeam\Seo\Importing\ImporterRegistry;
use Rankbeam\Seo\Importing\RalphJSmitImporter;
use Rankbeam\Seo\Importing\WordPress\RankMathImporter;
use Rankbeam\Seo\Importing\WordPress\WordPressCsvImporter;
use Rankbeam\Seo\Importing\WordPress\YoastImporter;
use Rankbeam\Seo\Services\Markdown\MarkdownRegistry;
use Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder;
use Rankbeam\Seo\Services\SEOComputedBuilder;
use Rankbeam\Seo\Services\SEODefaultsRepository;
use Rankbeam\Seo\Services\SEOResolutionCache;
use Rankbeam\Seo\Services\SEOResolver;
use Rankbeam\Seo\Services\Sitemap\SitemapRegistry;
use Rankbeam\Seo\Services\TagRenderer;

class SEOServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<string, string>
     */
    public array $singletons = [
        SEODefaultsRepository::class => SEODefaultsRepository::class,
        SEOComputedBuilder::class => SEOComputedBuilder::class,
        TagRenderer::class => TagRenderer::class,
        SitemapRegistry::class => SitemapRegistry::class,
        SEOResolutionCache::class => SEOResolutionCache::class,
        AiCrawlerRegistry::class => AiCrawlerRegistry::class,
        RobotsTxtBuilder::class => RobotsTxtBuilder::class,
        MarkdownRegistry::class => MarkdownRegistry::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Deep-merge package config UNDER the application config so a client
        // who published config/seo.php still receives newly-added nested
        // defaults (e.g. sitemap.images / sitemap.alternates, robots.emit_default)
        // without their env vars silently no-op'ing. Laravel's shallow
        // mergeConfigFrom() only fills top-level keys, so a published `sitemap`
        // array masked the new sitemap leaves. See mergeConfigRecursivelyFrom().
        $this->mergeConfigRecursivelyFrom(
            __DIR__.'/../config/seo.php',
            'seo'
        );

        // Register the SEO Resolver as a singleton
        $this->app->singleton(SEOResolver::class, function ($app) {
            return new SEOResolver(
                $app->make(SEODefaultsRepository::class),
                $app->make(SEOComputedBuilder::class)
            );
        });

        // Register the SEO facade accessor
        $this->app->alias(SEOResolver::class, 'seo');

        // Register the importer registry and the built-in importers. New
        // sources (e.g. the WordPress importer) register a key here.
        $this->app->singleton(ImporterRegistry::class, function ($app) {
            $registry = new ImporterRegistry($app);
            $registry->register('ralphjsmit', RalphJSmitImporter::class);
            $registry->register('wordpress-csv', WordPressCsvImporter::class);
            $registry->register('yoast', YoastImporter::class);
            $registry->register('rank-math', RankMathImporter::class);

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerCommands();
        $this->registerBladeDirectives();
        $this->registerViews();
        $this->registerTranslations();
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__.'/../config/seo.php' => config_path('seo.php'),
            ], 'seo-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'seo-migrations');

            // Publish views
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/seo'),
            ], 'seo-views');

            // Publish translations
            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/seo'),
            ], 'seo-lang');
        }
    }

    /**
     * Register the package's migrations.
     */
    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Register the package's routes.
     */
    protected function registerRoutes(): void
    {
        // Allow applications to opt out of all package routes (e.g. when
        // serving a statically generated /sitemap.xml themselves).
        if (! config('seo.routes.enabled', true)) {
            return;
        }

        // Web routes (sitemap, etc.)
        if (file_exists(__DIR__.'/../routes/web.php')) {
            Route::group($this->routeConfiguration(), function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        // API routes
        if (file_exists(__DIR__.'/../routes/api.php')) {
            Route::group([
                'prefix' => config('seo.routes.api_prefix', 'api/seo'),
                'middleware' => config('seo.routes.api_middleware', ['api']),
                'as' => 'seo.api.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }
    }

    /**
     * Get the route group configuration array.
     *
     * @return array<string, mixed>
     */
    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('seo.routes.prefix', ''),
            'middleware' => config('seo.routes.middleware', ['web']),
            'as' => 'seo.',
        ];
    }

    /**
     * Register the markdown-for-bots content-negotiation middleware globally.
     *
     * Gated on seo.markdown_for_bots.enabled (off by default), so by default the
     * package adds no middleware at all. When on, the middleware still passes
     * every response through untouched unless the request asks for markdown AND
     * a source resolves for the route — see {@see ServeMarkdownToBots}.
     */
    protected function registerMiddleware(): void
    {
        if (! config('seo.markdown_for_bots.enabled', false)
            || ! config('seo.markdown_for_bots.auto_register_middleware', true)) {
            return;
        }

        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'pushMiddleware')) {
            $kernel->pushMiddleware(ServeMarkdownToBots::class);
        }
    }

    /**
     * Register the package's Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SitemapCommand::class,
                LlmsTxtCommand::class,
                RobotsTxtCommand::class,
                AuditCommand::class,
                ImportFromCommand::class,
            ]);
        }
    }

    /**
     * Register the package's Blade directives.
     *
     * Available directives:
     *
     * @seo($model) - Renders all SEO tags for a model
     *     <head>
     *         @seo($post)
     *     </head>
     *
     * @seoForRoute($routeName) - Renders SEO tags for a named route (no model)
     *     <head>
     *         @seoForRoute('pages.about')
     *     </head>
     *
     * @seoTitle($model) - Renders only the <title> tag
     *     @seoTitle($post)
     *
     * @seoMeta($model) - Renders only the meta description tag
     *     @seoMeta($post)
     *
     * @seoSchema($model) - Renders only the JSON-LD schema script
     *     @seoSchema($post)
     *
     * @seoCanonical($model) - Renders only the canonical link tag
     *     @seoCanonical($post)
     *
     * @seoRobots($model) - Renders only the robots meta tag
     *     @seoRobots($post)
     */
    protected function registerBladeDirectives(): void
    {
        /*
        |------------------------------------------------------------------
        | @seo($model) - Complete SEO Tags
        |------------------------------------------------------------------
        |
        | Renders all SEO meta tags for a model. This is the primary
        | directive most users will need.
        |
        | Usage:
        |   @seo($post)                    // With model
        |   @seo($seoData)                 // With a hand-built SEOData
        |   @seo($post, 'blog.show')       // With model and route
        |   @seo($post, null, 'en')        // With model and locale
        |   @seo(null)                     // Current page without model
        |
        | Accepts a Model, a hand-built SEOData (model-less pages), or null.
        | The render() path prepares a supplied SEOData (fills absent fields,
        | absolutizes images, applies the title suffix) before the verbatim
        | TagRenderer; the route/locale arguments apply to the Model/null path.
        |
        */
        Blade::directive('seo', function ($expression) {
            return "<?php echo \\Rankbeam\\Seo\\Facades\\SEO::render({$expression}); ?>";
        });

        /*
        |------------------------------------------------------------------
        | @seoForRoute($routeName) - SEO Tags for Named Routes
        |------------------------------------------------------------------
        |
        | Renders SEO tags for a named route without a model.
        | Useful for static pages, archives, and other non-model pages.
        |
        | Usage:
        |   @seoForRoute('pages.about')
        |   @seoForRoute('blog.index')
        |   @seoForRoute('contact', 'de')  // With locale
        |
        */
        Blade::directive('seoForRoute', function ($expression) {
            return "<?php echo app(\\Rankbeam\\Seo\\Services\\TagRenderer::class)->render(
                app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolveForRoute({$expression})
            ); ?>";
        });

        /*
        |------------------------------------------------------------------
        | @seoTitle($model) - Title Tag Only
        |------------------------------------------------------------------
        |
        | Renders only the <title> tag. Useful when you need granular
        | control over which tags are rendered where.
        |
        | Usage:
        |   @seoTitle($post)
        |   @seoTitle()  // For current page
        |
        */
        Blade::directive('seoTitle', function ($expression) {
            if (empty($expression)) {
                return "<?php
                    \$__seoTitle = app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve()->title;
                    if (\$__seoTitle) echo '<title>' . e(\$__seoTitle) . '</title>';
                ?>";
            }

            return "<?php
                \$__seoTitle = app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve({$expression})->title;
                if (\$__seoTitle) echo '<title>' . e(\$__seoTitle) . '</title>';
            ?>";
        });

        /*
        |------------------------------------------------------------------
        | @seoMeta($model) - Meta Description Only
        |------------------------------------------------------------------
        |
        | Renders only the meta description tag.
        |
        | Usage:
        |   @seoMeta($post)
        |   @seoMeta()  // For current page
        |
        */
        Blade::directive('seoMeta', function ($expression) {
            if (empty($expression)) {
                return "<?php
                    \$__seoDesc = app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve()->description;
                    if (\$__seoDesc) echo '<meta name=\"description\" content=\"' . e(\$__seoDesc) . '\">';
                ?>";
            }

            return "<?php
                \$__seoDesc = app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve({$expression})->description;
                if (\$__seoDesc) echo '<meta name=\"description\" content=\"' . e(\$__seoDesc) . '\">';
            ?>";
        });

        /*
        |------------------------------------------------------------------
        | @seoSchema($model) - JSON-LD Schema Only
        |------------------------------------------------------------------
        |
        | Renders only the JSON-LD structured data script tag.
        | Can be placed anywhere (head or body).
        |
        | Usage:
        |   @seoSchema($post)
        |   @seoSchema()  // For current page
        |
        */
        Blade::directive('seoSchema', function ($expression) {
            if (empty($expression)) {
                return "<?php echo app(\\Rankbeam\\Seo\\Services\\TagRenderer::class)->renderSchema(
                    app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve()
                ) ?? ''; ?>";
            }

            return "<?php echo app(\\Rankbeam\\Seo\\Services\\TagRenderer::class)->renderSchema(
                app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve({$expression})
            ) ?? ''; ?>";
        });

        /*
        |------------------------------------------------------------------
        | @seoCanonical($model) - Canonical Link Only
        |------------------------------------------------------------------
        |
        | Renders only the canonical link tag.
        |
        | Usage:
        |   @seoCanonical($post)
        |   @seoCanonical()  // For current page
        |
        */
        Blade::directive('seoCanonical', function ($expression) {
            if (empty($expression)) {
                return "<?php
                    \$__seoCanonical = app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve()->canonical ?? url()->current();
                    echo '<link rel=\"canonical\" href=\"' . e(\$__seoCanonical) . '\">';
                ?>";
            }

            return "<?php
                \$__seoCanonical = app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve({$expression})->canonical ?? url()->current();
                echo '<link rel=\"canonical\" href=\"' . e(\$__seoCanonical) . '\">';
            ?>";
        });

        /*
        |------------------------------------------------------------------
        | @seoRobots($model) - Robots Meta Tag Only
        |------------------------------------------------------------------
        |
        | Renders only the robots meta tag.
        |
        | Usage:
        |   @seoRobots($post)
        |   @seoRobots()  // For current page
        |
        */
        Blade::directive('seoRobots', function ($expression) {
            if (empty($expression)) {
                return "<?php
                    \$__seoRobots = app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve()->robots ?? 'index,follow';
                    echo '<meta name=\"robots\" content=\"' . e(\$__seoRobots) . '\">';
                ?>";
            }

            return "<?php
                \$__seoRobots = app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve({$expression})->robots ?? 'index,follow';
                echo '<meta name=\"robots\" content=\"' . e(\$__seoRobots) . '\">';
            ?>";
        });
    }

    /**
     * Register the package's views.
     */
    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'seo');
    }

    /**
     * Register the package's translations.
     */
    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'seo');
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
    }

    /**
     * Deep-merge a package config file under the application's config.
     *
     * Laravel's built-in mergeConfigFrom() merges only the top level: once an
     * app publishes config/seo.php, its `sitemap` array (or any other nested
     * array) wholesale-masks the package defaults, so a leaf the package adds
     * in a later release (and the env var that drives it) never reaches the
     * resolved config. This recurses: package defaults fill any key the
     * published config is missing at every depth, while every value the app
     * DID set — including falsey leaves — is preserved.
     */
    protected function mergeConfigRecursivelyFrom(string $path, string $key): void
    {
        if ($this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');

        $config->set(
            $key,
            $this->mergeConfigArrays(require $path, $config->get($key, []))
        );
    }

    /**
     * Recursively merge package defaults UNDER existing values. The existing
     * (app/published) value wins at every leaf; package defaults only fill
     * keys the app omitted. Two associative arrays are merged key-by-key;
     * anything else (a scalar, or a list the app set) is kept verbatim from
     * the app, so a user who emptied or replaced a list is never re-seeded.
     *
     * @param  array<mixed>  $defaults
     * @param  array<mixed>  $existing
     * @return array<mixed>
     */
    protected function mergeConfigArrays(array $defaults, array $existing): array
    {
        $merged = $existing;

        foreach ($defaults as $key => $value) {
            if (is_int($key)) {
                // List entries: never duplicate or re-seed; the app's list wins.
                continue;
            }

            if (! array_key_exists($key, $existing)) {
                $merged[$key] = $value;

                continue;
            }

            if (is_array($value) && is_array($existing[$key]) && $this->isAssoc($value) && $this->isAssoc($existing[$key])) {
                $merged[$key] = $this->mergeConfigArrays($value, $existing[$key]);
            }
            // Otherwise the existing leaf (scalar, null, or a list) is kept.
        }

        return $merged;
    }

    /**
     * An array is "associative" for merge purposes unless it is a clean,
     * zero-indexed list. Empty arrays are treated as associative so an empty
     * published `[]` still receives nested package defaults.
     *
     * @param  array<mixed>  $array
     */
    protected function isAssoc(array $array): bool
    {
        return $array === [] || array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            SEOResolver::class,
            TagRenderer::class,
            SEODefaultsRepository::class,
            SEOComputedBuilder::class,
            SitemapRegistry::class,
            SEOResolutionCache::class,
            AiCrawlerRegistry::class,
            RobotsTxtBuilder::class,
            MarkdownRegistry::class,
            ImporterRegistry::class,
            'seo',
        ];
    }
}
