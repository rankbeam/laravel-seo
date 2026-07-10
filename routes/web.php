<?php

use Illuminate\Support\Facades\Route;
use Rankbeam\Seo\Http\Controllers\LlmsTxtController;
use Rankbeam\Seo\Http\Controllers\RobotsTxtController;
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

// Styled-sitemap XSL stylesheet, referenced from generated sitemaps via an
// xml-stylesheet processing instruction so a browser renders them as a
// readable, branded page. Registered only when the styled sitemap is enabled;
// browsers apply an XSLT only when it is same-origin with the XML, so the
// package serves it here. (Note: no literal PHP close tag in this comment — it
// would terminate the // comment and the whole PHP block.)
if (config('seo.sitemap.stylesheet.enabled', true)) {
    Route::get('sitemap.xsl', [SitemapController::class, 'stylesheet'])
        ->name('sitemap.stylesheet');
}

// llms.txt — the AEO/GEO index served the same way as the sitemap. Opt out per
// route via seo.llms_txt.route (the whole group is still gated by
// seo.routes.enabled above) when serving a statically generated /llms.txt.
if (config('seo.llms_txt.route', true)) {
    Route::get('llms.txt', [LlmsTxtController::class, 'index'])
        ->name('llms-txt.index');
}

// robots.txt — a managed robots.txt with AI-crawler directives, served the same
// way as the sitemap. OFF by default (seo.ai_crawlers.route): most apps ship a
// static public/robots.txt that the web server serves before Laravel routing
// runs. Enable it to have the package serve robots.txt dynamically instead.
if (config('seo.ai_crawlers.route', false)) {
    Route::get('robots.txt', [RobotsTxtController::class, 'index'])
        ->name('robots-txt.index');
}
