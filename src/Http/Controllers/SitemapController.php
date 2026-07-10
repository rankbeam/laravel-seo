<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Controller for serving XML sitemaps.
 *
 * Reads sitemaps from storage and serves them with proper
 * XML headers and caching.
 */
class SitemapController extends Controller
{
    /**
     * Serve the main sitemap (or index).
     */
    public function index(): Response
    {
        return $this->serveSitemap(
            config('seo.sitemap.path', 'sitemap.xml')
        );
    }

    /**
     * Serve a specific model sitemap.
     */
    public function show(string $name): Response
    {
        $filename = "sitemap-{$name}.xml";

        return $this->serveSitemap($filename);
    }

    /**
     * Serve the styled-sitemap XSL stylesheet.
     *
     * Referenced from generated sitemaps via <?xml-stylesheet?>, so a browser
     * renders them as a readable, branded page. Served from the app origin
     * because browsers only apply an XSLT that is same-origin with the sitemap.
     * The file is static package content — no request input reaches it, and the
     * stylesheet itself escapes every sitemap value it renders (see the XSL).
     */
    public function stylesheet(): Response
    {
        // resources/xsl/sitemap.xsl, three directories up from this controller
        // (src/Http/Controllers -> package root).
        $path = dirname(__DIR__, 3).'/resources/xsl/sitemap.xsl';

        if (! is_file($path)) {
            abort(404, 'Sitemap stylesheet not found');
        }

        return response((string) file_get_contents($path))
            ->header('Content-Type', 'text/xsl; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=86400')
            ->header('X-Robots-Tag', 'noindex');
    }

    /**
     * Serve a sitemap file.
     */
    protected function serveSitemap(string $path): Response
    {
        $disk = config('seo.sitemap.disk', 'public');
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            abort(404, 'Sitemap not found');
        }

        $content = $storage->get($path);
        $lastModified = $storage->lastModified($path);

        return response($content)
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT')
            ->header('X-Robots-Tag', 'noindex');
    }
}
