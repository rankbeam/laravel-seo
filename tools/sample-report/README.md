# Localized website sample report

The public website and documentation download this two-page editorial companion
in each of the 15 published languages. This is deliberately **not a change to the
Pro package's PDF template**. The document and its download pages disclose that
distinction. No package or customer data is changed by this tool.

## Evidence

`evidence/before.json` and `evidence/after.json` are the recorded scan exports from
the isolated, seeded Merchant demonstration on 9 September 2026. They are copied
without edits. The original public Pro-generated PDF is preserved at
`docs/public/pro-walkthrough/archive/merchant-demo-report-2026-09-09.pdf`.

- Both scans completed 21 targets. The SEO score changed from 92 to 93.
- Open findings changed from 20 to 19; warnings from six to five; notices stayed at 14.
- One 142-character description saved for Post #5 resolved one issue; no new
  issues were recorded. These facts also appear in `docs/pro/walkthrough.md`.
- The original PDF records AI-readiness at 50 before and after. That separate
  technical score is not a measure of AI visibility or citations.
- Search Console and bot logging were disabled. We do not add fabricated trend,
  traffic, ranking or citation metrics.

Group counts and original target identifiers are derived from the scan export.
Local-origin routes are presented as paths (`/`, `/blog`, `/products`). Record
identifiers remain source data in all languages. Suggested next steps are clearly
separate from observed findings.

## Build and validation

Install `requirements.txt`, then run from the repository root:

```bash
python tools/sample-report/build.py
python tools/sample-report/validate.py
npm run docs:build
```

Use `--locale it` for a single-file preview. The complete build writes all PDFs,
first-page PNGs and a hash/size manifest under `docs/public/pro-walkthrough`.
English retains the original download URL; other editions live in locale folders.
The build rejects missing glyphs or text that exceeds its reserved layout area.
It uses only local, embedded fonts/assets and does not fetch any network resources.

The catalogs were translated and editorially reviewed by Codex, not independently
certified by native human reviewers. A catalog update requires semantic review,
regeneration, glyph/geometry checks and visual inspection of both pages.

## Typography and brand

Noto Sans Regular/Bold are OFL-licensed fonts. Japanese, Simplified Chinese and
Korean use local Noto Sans JP/SC/KR static subsets, derived from Google Fonts'
variable TrueType sources. Source hashes, URLs, catalog hashes and weight choices
are in `fonts/sources.json`; Latin font versions and hashes are in
`fonts/latin-sources.json`. All OFL notices are included. Font embedding is
subsetted again by ReportLab. The Rankbeam mark is the existing website asset.

To expand a CJK catalog, fetch the source fonts listed in the manifest into a
temporary directory, preserving the family filenames and OFL notices, then run:

```bash
python tools/sample-report/prepare_cjk_fonts.py --source-dir /path/to/font-sources
```

The published PDF includes its language in metadata, searchable text, localized
dates/labels and links to the matching documentation edition. The original capture
date remains 9 September; the redesign date is separately disclosed. This is not
a new scan or a refreshed certification of the current package release.

Rollback: revert the docs and site sample-report commits and let their existing
Cloudflare deployments rebuild. No server migration or private release is involved.
