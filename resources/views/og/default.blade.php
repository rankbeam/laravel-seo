{{--
    Default OG-image template (1200x630). Rendered to a PNG by the configured
    OgImageRenderer. Everything is inlined (the font arrives as a data URI in
    $fontDataUri) so the HTML is self-contained for a headless browser.

    Variables: $title, $siteName, $fontDataUri, $gradientFrom, $gradientTo,
    $width, $height. Publish with `php artisan vendor:publish --tag=seo-views`
    to customize; register additional templates via config('seo.og_image.template').
--}}<!doctype html>
<html lang="{{ $locale ?? 'en' }}">
<head>
<meta charset="utf-8">
<style>
    @font-face {
        font-family: 'OGBrand';
        font-weight: 700;
        font-style: normal;
        src: url('{{ $fontDataUri }}') format('truetype');
        font-display: block;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { width: {{ $width }}px; height: {{ $height }}px; }
    body {
        /* Bundled Latin bold first; the browser's own system font stack then
           supplies glyphs for any script the bundled font lacks (CJK, etc.). */
        font-family: 'OGBrand', sans-serif;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: linear-gradient(135deg, {{ $gradientFrom }} 0%, {{ $gradientTo }} 100%);
        color: #ffffff;
        -webkit-font-smoothing: antialiased;
        position: relative;
        overflow: hidden;
    }
    .title {
        font-weight: 700;
        font-size: 68px;
        line-height: 1.18;
        padding: 0 90px;
        /* natural word-wrap; clamp to 4 lines and ellipsize the overflow */
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 4;
        overflow: hidden;
        text-overflow: ellipsis;
        word-break: break-word; /* lets CJK (no spaces) wrap per-character */
    }
    .site {
        position: absolute;
        left: 90px;
        bottom: 54px;
        font-size: 30px;
        font-weight: 700;
        opacity: 0.92;
        letter-spacing: 0.5px;
    }
    .accent {
        position: absolute;
        left: 90px;
        top: 54px;
        width: 84px;
        height: 8px;
        border-radius: 4px;
        background: #ffffff;
        opacity: 0.85;
    }
</style>
</head>
<body>
    <div class="accent"></div>
    <div class="title">{{ $title }}</div>
    @if (! empty($siteName))
        <div class="site">{{ $siteName }}</div>
    @endif
</body>
</html>
