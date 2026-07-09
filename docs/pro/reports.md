# White-label reports

A branded **PDF report** for one site — overall score, the issues-found trend,
what was **fixed vs. new since the last report**, 404s and broken links
recovered, Search Console movers, and AI-bot activity — generated with one
command and, optionally, **e-mailed on a schedule**. Built for agencies: put
your logo, colour, and "prepared for {client}" on it and hand it to the client.

It is a one-time part of Pro — no per-seat report subscription to resent every
month.

![A generated white-label SEO report](/reports-example.png)

## What's in it

- **Overall score** — the average of the latest per-page scores (the published
  [rubric](/pro/scoring): A ≥ 90 … F), with the change since your last report,
  plus an **overall-score trend** across your recent scans. Each scan now stamps
  the site's score onto the run, so the trend is real per-scan history — it
  starts filling in from the first scan after you upgrade (older runs carry no
  score and are simply skipped).
- **Issues found per scan** — a real trend over your recent completed scans
  (fewer is better).
- **Issues fixed vs. new** — how many defects you cleared and how many appeared
  since the last report. Read from the real issue history — issues now carry a
  fixed / reopened [lifecycle](/pro/scan-issues#issue-lifecycle) — once a full
  period has run under it, and from the previous report's snapshot otherwise.
- **Recovered** — broken links resolved, 404s **recovered** (the path returns
  200 again on its own — see [`seo-pro:404-recheck`](/pro/production#scheduler))
  and 404s **redirected** since the last report, plus what's still open. A
  recovered 404 is a genuine source-side fix, counted separately from a redirect.
- **Search Console** — top queries and pages, and **movers**: the biggest
  click swings versus the last report. Skipped cleanly when GSC isn't set up.
- **AI-bot activity** — which AI crawlers hit the site, lifetime totals, and
  **how many hits landed this period**.

## "Since the last report"

The report is **period-over-period against your previous report**, not against
an arbitrary date. Each time you generate one, a lightweight snapshot is stored
(`seo_report_runs`) — the score, the open-issue identities, the Search Console
rows, and each bot's hit counter. The next report diffs today's state against
that snapshot.

This is deliberate for the signals that keep no history of their own: per-page
scores are kept latest-only, Search Console metrics aren't stored, and the
AI-bot log keeps lifetime counters. Snapshotting at report time turns those into
an honest comparison. **Issues are the exception** — they now carry a real
fixed / reopened [lifecycle](/pro/scan-issues#issue-lifecycle), so once a full
period has run under it the report reads genuine fixed/new counts from the issue
history, falling back to the snapshot diff only for the first report after
upgrading.

Two consequences:

- **The first report is a baseline.** It shows current state; the "fixed",
  "new", movers, and "since last report" figures fill in from the *second*
  report onward.
- **Cadence is up to you.** Generate monthly and the deltas cover a month;
  generate weekly and they cover a week. Use `--no-store` for an ad-hoc preview
  that must not move the baseline.

## Generate a report

```bash
php artisan seo-pro:report
```

With no options it writes a PDF to `storage/app/seo-reports/`. Point it
somewhere, or e-mail it:

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### Options

| Option | Effect |
| --- | --- |
| `--client=` | Override the "prepared for" client label |
| `--agency=` | Override the agency name on the report |
| `--accent=` | Override the accent colour (hex, e.g. `#3D5AFE`) |
| `--logo=` | Override the logo image path |
| `--email=` | Recipient address (repeatable); e-mails the report |
| `--send` | E-mail to the configured recipients |
| `--output=` | Write the PDF to this file or directory |
| `--no-store` | Don't persist a snapshot (period deltas won't advance) |
| `--json` | Emit a machine-readable summary |

## Schedule the e-mail

The package never schedules itself — you own the cadence. In your app's console
schedule (`routes/console.php` or `app/Console/Kernel.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Set the default recipients once, in config or `.env`:

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` falls back to those; explicit `--email` options override them.

## Branding

Branding is non-secret, so it lives in config — set it once and every report
picks it up. Any field can be overridden per report with the command options
above (handy when one install reports for several clients).

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Notes:

- **Logo** — an absolute path to a `PNG`/`JPG`/`GIF`/`WEBP`/`SVG` file. It is
  embedded in the PDF as a data URI, so the renderer never needs network or
  filesystem access. `PNG` or `JPG` is safest.
- **Accent colour** — validated to a hex literal; a bad value falls back to the
  default. It only ever appears as a colour, never as raw CSS.
- **Agency name** — defaults to your app's name (`config('app.name')`).

The full config block lives under `reports` in `config/seo-pro.php`, including
`paper` (default `a4`), `include_gsc`, and how many trend runs / GSC rows / bots
to include.

## One site per install

Pro scans the one application it is installed in, so a report describes **that
install**. An agency running several client sites generates one report per
install (the `--client` / branding overrides label each). There is no
multi-tenant "sites" model.

## How it's built

The PDF is rendered with **dompdf** — pure PHP, no Node or headless Chromium —
so a scheduled report renders inside a queue worker or cron with zero system
binaries, and Pro stays headless. Remote fetching is disabled in the renderer;
the only image (your logo) is embedded, so nothing in a rendered field can
trigger a fetch.

Programmatically, resolve `ReportGenerator` from the container:

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```
