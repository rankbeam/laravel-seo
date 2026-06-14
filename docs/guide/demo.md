# Run the demo

The fastest way to see Rankbeam working on real pages — without wiring it into
your own app first — is the runnable demo. It is a seeded Laravel app that
installs the **released** packages (no path repos, no sibling checkouts) and
renders a handful of pages with full SEO metadata, a JSON-LD schema graph, and a
sitemap. Add a license and it also runs the Pro [technical-SEO
audit](/pro/scan-issues).

## One command (free core)

The demo ships as a Docker image in the
[`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples) repo:

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

Open `http://localhost:8080`. View source on any page to see the resolved
`<head>`; visit `/sitemap.xml` for the generated sitemap. Everything here is the
free MIT core, installed from Packagist.

## With Pro (the audit)

Pro is licensed per project and installs from its private Composer repository.
Pass your license through `COMPOSER_AUTH` (a build secret — never written to an
image layer) and build with the Pro flag:

```bash
export COMPOSER_AUTH='{"http-basic":{"laravel-seo-pro.composer.sh":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

On boot the demo runs [`seo:doctor`](/pro/headless#health-check) and a first
`seo-pro:scan` over the seeded pages — the health report, scan summary, and
[0–100 score](/pro/scoring) print in the compose logs.

## Hosted demo

A read-only hosted instance — the full Filament dashboard with live scan
progress, issue browsing, the redirect manager, and the 404 monitor — is
published at launch. Until then, the Docker demo above is the way to run the
engine yourself; see the
[demo README](https://github.com/rankbeam/rankbeam-examples/tree/main/demo) for
the full rundown and the dev ↔ released "one-line flip".

::: tip Already have an app?
Skip the demo and go straight to the [Quickstart](/guide/quickstart) — install
to a fully-rendered `<head>` in five minutes.
:::
