{{--
    Product OG template (1200x630): a commerce card — the site name as a brand
    lockup (top), an optional category chip, the product title, and a one-line
    description. Extra variables over the default: $description, $section.
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
    .brand {
        position: absolute;
        top: 54px;
        left: 90px;
        display: flex;
        align-items: center;
        gap: 20px;
        font-size: 30px;
        letter-spacing: 0.5px;
        opacity: 0.95;
    }
    .brand .mark {
        width: 18px;
        height: 44px;
        border-radius: 6px;
        background: #ffffff;
        opacity: 0.92;
    }
    .chip {
        align-self: flex-start;
        margin-bottom: 24px;
        padding: 10px 24px;
        border: 3px solid rgba(255, 255, 255, 0.55);
        border-radius: 999px;
        font-size: 24px;
        letter-spacing: 2px;
        text-transform: uppercase;
        opacity: 0.92;
    }
    .title {
        font-size: 68px;
        line-height: 1.1;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
        text-overflow: ellipsis;
        word-break: break-word;
    }
    .desc {
        margin-top: 26px;
        max-width: 920px;
        font-size: 30px;
        line-height: 1.35;
        opacity: 0.82;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
        text-overflow: ellipsis;
        word-break: break-word;
    }
</style>
</head>
<body>
    @if (! empty($siteName))
        <div class="brand"><span class="mark"></span>{{ $siteName }}</div>
    @endif
    @if (! empty($section))
        <div class="chip">{{ $section }}</div>
    @endif
    <div class="title">{{ $title }}</div>
    @if (! empty($description))
        <div class="desc">{{ $description }}</div>
    @endif
</body>
</html>
