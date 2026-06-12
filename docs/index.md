---
layout: home

hero:
  name: Rankbeam
  text: SEO for Laravel that resolves itself
  tagline: Layered meta resolution, Open Graph / Twitter Cards, a linked JSON-LD schema graph, and XML sitemaps — with sane fallbacks for every page you forgot about.
  actions:
    - theme: brand
      text: Quickstart
      link: /guide/quickstart
    - theme: alt
      text: Installation
      link: /guide/installation
    - theme: alt
      text: GitHub
      link: https://github.com/rankbeam/laravel-seo

features:
  - title: Layered resolution
    details: Six precedence layers — site config, global defaults, model-type defaults, route defaults, computed fallbacks, explicit values. Null never overwrites a lower layer, so every page renders something sensible.
  - title: One directive
    details: "@seo($post) renders the full head: title, description, canonical, robots, Open Graph, Twitter Cards, and JSON-LD — XSS-hardened."
  - title: Schema graph
    details: Organization, WebSite, and WebPage JSON-LD nodes cross-linked via stable @id values, plus builders for Article, Product, Breadcrumb, FAQ, and LocalBusiness.
  - title: Sitemap registry
    details: Register named sitemap sources from models, closures, or URL lists; the package writes sitemap-{name}.xml files plus an index and serves them for you.
  - title: Headless-ready
    details: The same resolved data renders as HTML, structured arrays for Vue/React, or Inertia Head format — one source of truth for every stack.
  - title: Filament integration
    details: The free laravel-seo-filament package adds a complete SEO section to any resource form in two lines, with live counters and fallback indicators.
---
