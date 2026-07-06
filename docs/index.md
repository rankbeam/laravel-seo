---
layout: home

hero:
  name: Rankbeam
  text: The headless SEO engine for Laravel
  tagline: "Typed, Git-native metadata resolution, a linked JSON-LD schema graph, and XML sitemaps in a free MIT core — rendered the same in Blade, Inertia, or a JSON API. Pro layers on a technical-SEO audit: queued scans, severity-ranked issues, redirects, and 404 recovery. No runtime license check, no telemetry, bring-your-own-key AI."
  image:
    src: /logo.svg
    alt: Rankbeam
  actions:
    - theme: brand
      text: Quickstart
      link: /guide/quickstart
    - theme: alt
      text: Installation
      link: /guide/installation
    - theme: alt
      text: rankbeam.dev ↗
      link: https://rankbeam.dev

features:
  - title: Typed, layered resolution
    details: Six precedence layers — site config, global defaults, model-type defaults, route defaults, computed fallbacks, explicit values — resolved into one typed SEOData object. Null never overwrites a lower layer, so every page renders something sensible.
  - title: One directive
    details: "@seo($post) renders the full head: title, description, canonical, robots, Open Graph, Twitter Cards, and JSON-LD — XSS-hardened."
  - title: Schema graph
    details: Organization, WebSite, and WebPage JSON-LD nodes cross-linked via stable @id values, plus builders for Article, Product, Breadcrumb, FAQ, and LocalBusiness.
  - title: Sitemap registry
    details: Register named sitemap sources from models, closures, or URL lists; the package writes sitemap-{name}.xml files plus an index and serves them for you.
  - title: Headless-ready
    details: The same resolved data renders as HTML, structured arrays, or Inertia Head format — one resolver feeding Blade, Inertia (Vue/React/Svelte), and Livewire, each proven against a shared rendering contract.
  - title: Filament integration
    details: The free laravel-seo-filament package adds a complete SEO section to any resource form in two lines, with live counters and fallback indicators.
  - title: Technical-SEO audit (Pro)
    details: A queued scan pipeline with severity-ranked issues and a transparent 0–100 score, a hardened redirect manager, and a no-IP 404 monitor with one-click recovery — all running headless on any Laravel app, with an optional Filament dashboard.
  - title: AI assist (Pro)
    details: Bring-your-own-key title and meta-description suggestions plus plain-language scan-issue explanations. Your Anthropic or OpenAI account; nothing proxied, metered, or resold.
---

## Why Rankbeam

Rankbeam isn't a WordPress plugin ported to Laravel. It is built for teams who treat SEO as part of the application, not a separate admin product:

- **Headless by default.** The resolver produces typed data; how it reaches the DOM is your choice. Blade is browser-proven; Inertia (Vue/React/Svelte) and Livewire are wired and tested against a shared [rendering contract](/contributing/rendering-contract). No admin panel is required — every Pro feature runs from [artisan and your scheduler](/pro/headless).
- **Typed, not stringly-typed.** Meta resolves into a single `SEOData` value object, and schema is built by typed builders validated before it renders. Your IDE and static analyser see the whole surface.
- **Git-native configuration.** Defaults live in `config/seo.php` and your code, version-controlled and code-reviewed — reproducible across environments, with no click-through wizard to re-do per install.
- **No runtime license check.** Pro is licensed per project, but nothing phones home and no kill-switch can take your app down. Licensing is install-time only.
- **Bring-your-own-key AI.** Suggestions use *your* Anthropic, OpenAI, Google, or local model account. Nothing is proxied, metered, or resold, and it is off by default.

The free MIT core renders metadata, JSON-LD, and sitemaps; Pro adds the [technical-SEO audit](/pro/scan-issues) — scans, the [0–100 score](/pro/scoring), redirects, and 404 recovery.

## Try it

- [Quickstart](/guide/quickstart) — install to a fully-rendered `<head>` in five minutes.
- [Run the demo](/guide/demo) — a seeded app on the released packages: rendered metadata, schema, sitemaps, and a Pro scan, in one command.

