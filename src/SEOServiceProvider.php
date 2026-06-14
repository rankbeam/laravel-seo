<?php

declare(strict_types=1);

namespace Rankbeam\Seo;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Rankbeam\Seo\Console\Commands\AuditCommand;
use Rankbeam\Seo\Console\Commands\SitemapCommand;
use Rankbeam\Seo\Services\SEOComputedBuilder;
use Rankbeam\Seo\Services\SEODefaultsRepository;
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
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge package config with application config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/seo.php',
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerMigrations();
        $this->registerRoutes();
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
                __DIR__ . '/../config/seo.php' => config_path('seo.php'),
            ], 'seo-config');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'seo-migrations');

            // Publish views
            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/seo'),
            ], 'seo-views');

            // Publish translations
            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/seo'),
            ], 'seo-lang');
        }
    }

    /**
     * Register the package's migrations.
     */
    protected function registerMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
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
        if (file_exists(__DIR__ . '/../routes/web.php')) {
            Route::group($this->routeConfiguration(), function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
            });
        }

        // API routes
        if (file_exists(__DIR__ . '/../routes/api.php')) {
            Route::group([
                'prefix' => config('seo.routes.api_prefix', 'api/seo'),
                'middleware' => config('seo.routes.api_middleware', ['api']),
                'as' => 'seo.api.',
            ], function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
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
     * Register the package's Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SitemapCommand::class,
                AuditCommand::class,
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
        |   @seo($post, 'blog.show')       // With model and route
        |   @seo($post, null, 'en')        // With model and locale
        |   @seo(null)                     // Current page without model
        |
        */
        Blade::directive('seo', function ($expression) {
            return "<?php echo app(\\Rankbeam\\Seo\\Services\\TagRenderer::class)->render(
                app(\\Rankbeam\\Seo\\Services\\SEOResolver::class)->resolve({$expression})
            ); ?>";
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
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'seo');
    }

    /**
     * Register the package's translations.
     */
    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'seo');
        $this->loadJsonTranslationsFrom(__DIR__ . '/../resources/lang');
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
            'seo',
        ];
    }
}
