# Bundled fonts

## Noto Sans (Bold)

`NotoSans-Bold.ttf` is used by the default OG-image template
(`resources/views/og/default.blade.php`) as the Latin display face. The
headless browser supplies its own fallback fonts for any script this face does
not cover (e.g. CJK), so only the bold Latin weight is bundled.

- **Family:** Noto Sans
- **Copyright:** The Noto Project Authors (https://github.com/notofonts/latin-greek-cyrillic)
- **License:** SIL Open Font License, Version 1.1 — see [`OFL.txt`](./OFL.txt)

The OFL permits bundling and redistribution with this notice and the license
retained. To use a different face, publish the views and edit the template, or
point `config('seo.og_image.template')` at your own view.
