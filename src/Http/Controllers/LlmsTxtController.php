<?php

declare(strict_types=1);

namespace Rankbeam\Seo\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

/**
 * Controller for serving the generated llms.txt.
 *
 * Reads the file written by `seo:llms-txt` from storage and serves it as
 * markdown / plain text — mirroring how SitemapController serves sitemap.xml.
 */
class LlmsTxtController extends Controller
{
    /**
     * Serve the generated llms.txt file.
     */
    public function index(): Response
    {
        $disk = config('seo.llms_txt.disk', config('seo.sitemap.disk', 'public'));
        $path = config('seo.llms_txt.path', 'llms.txt');
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            abort(404, 'llms.txt not found');
        }

        $content = $storage->get($path);
        $lastModified = $storage->lastModified($path);

        return response($content)
            ->header('Content-Type', 'text/markdown; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
    }
}
