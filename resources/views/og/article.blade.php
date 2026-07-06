{{--
    Article OG template (1200x630): an editorial card — section eyebrow, the
    title, and a byline (author · date) with the site name. Extra variables over
    the default: $author, $publishedDate, $section. Missing ones are omitted.
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
        font-family: 'OGBrand', sans-serif;
        font-weight: 700;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: linear-gradient(135deg, {{ $gradientFrom }} 0%, {{ $gradientTo }} 100%);
        color: #ffffff;
        -webkit-font-smoothing: antialiased;
        position: relative;
        overflow: hidden;
        padding: 0 90px;
    }
    .eyebrow {
        display: flex;
        align-items: center;
        gap: 20px;
        font-size: 26px;
        letter-spacing: 3px;
        text-transform: uppercase;
        opacity: 0.88;
        margin-bottom: 28px;
    }
    .eyebrow::before {
        content: '';
        width: 58px;
        height: 8px;
        border-radius: 4px;
        background: #ffffff;
        opacity: 0.9;
    }
    .title {
        font-size: 64px;
        line-height: 1.16;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        overflow: hidden;
        text-overflow: ellipsis;
        word-break: break-word;
    }
    .byline {
        position: absolute;
        left: 90px;
        right: 90px;
        bottom: 56px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 28px;
        opacity: 0.92;
    }
    .byline .who { display: flex; align-items: center; gap: 16px; }
    .byline .dot { opacity: 0.5; }
    .byline .site { opacity: 0.8; }
</style>
</head>
<body>
    @if (! empty($section))
        <div class="eyebrow">{{ $section }}</div>
    @endif
    <div class="title">{{ $title }}</div>
    <div class="byline">
        <span class="who">
            @if (! empty($author)){{ $author }}@endif
            @if (! empty($author) && ! empty($publishedDate))<span class="dot">·</span>@endif
            @if (! empty($publishedDate)){{ $publishedDate }}@endif
        </span>
        @if (! empty($siteName))<span class="site">{{ $siteName }}</span>@endif
    </div>
</body>
</html>
