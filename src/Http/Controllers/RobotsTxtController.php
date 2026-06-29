<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Rankbeam\Seo\Services\RobotsTxt\RobotsTxtBuilder;

/**
 * Controller for serving a dynamic robots.txt with AI-crawler directives.
 *
 * Unlike the llms.txt controller (which serves a file written by its command),
 * robots.txt is rendered on the fly from the current policy — it is cheap to
 * build (no database), so enabling the route "just works" without a generate
 * step, and the file always reflects the live configuration.
 *
 * This route is OFF by default (seo.ai_crawlers.route): most apps ship a static
 * public/robots.txt that the web server serves before Laravel ever routes the
 * request. Enable it only when you want the package to serve robots.txt.
 */
class RobotsTxtController extends Controller
{
    /**
     * Serve the generated robots.txt.
     */
    public function index(RobotsTxtBuilder $builder): Response
    {
        return response($builder->build())
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
