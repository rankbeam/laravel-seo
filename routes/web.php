<?php

use Illuminate\Support\Facades\Route;
use Fibonoir\LaravelSEO\Http\Controllers\SitemapController;

/*
|--------------------------------------------------------------------------
| SEO Package Web Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the SEOServiceProvider. They handle
| SEO-related web endpoints like sitemap serving.
|
| Note: These routes are registered with the 'web' middleware group
| and can be disabled via config('seo.routes.enabled').
|
*/

// Sitemap routes
Route::middleware(config('seo.routes.middleware', ['web']))
    ->group(function () {
        // Main sitemap (or sitemap index)
        Route::get('sitemap.xml', [SitemapController::class, 'index'])
            ->name('seo.sitemap.index');

        // Individual model sitemaps (e.g., sitemap-posts.xml)
        Route::get('sitemap-{name}.xml', [SitemapController::class, 'show'])
            ->where('name', '[a-z0-9\-]+')
            ->name('seo.sitemap.show');
    });
