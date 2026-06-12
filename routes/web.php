<?php

use Illuminate\Support\Facades\Route;
use Rankbeam\Seo\Http\Controllers\SitemapController;

/*
|--------------------------------------------------------------------------
| SEO Package Web Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the SEOServiceProvider inside a route group
| that already applies config('seo.routes.prefix') and
| config('seo.routes.middleware') — do not re-apply them here.
|
| Registration is skipped entirely when config('seo.routes.enabled') is
| false (e.g. apps serving a statically generated /sitemap.xml).
|
*/

// Main sitemap (or sitemap index)
Route::get('sitemap.xml', [SitemapController::class, 'index'])
    ->name('sitemap.index');

// Individual model/source sitemaps (e.g. sitemap-posts.xml)
Route::get('sitemap-{name}.xml', [SitemapController::class, 'show'])
    ->where('name', '[a-z0-9\-]+')
    ->name('sitemap.show');
