<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SEO Package API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the SEOServiceProvider. They provide
| API endpoints for the SEO suite, including content analysis
| and management endpoints.
|
*/

Route::prefix('api/seo')->middleware(['api'])->group(function () {
    // Routes will be added in subsequent phases:
    // - Content analysis endpoint (P.2)
    // - Redirect management API (P.3)
    // - 404 log management API (P.3)
    // - Scanner status API (P.3)
    // - Analytics data API (P.3)
});
