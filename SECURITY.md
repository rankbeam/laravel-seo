# Security Policy

This is the security policy for **`rankbeam/laravel-seo`**, the free MIT core of
[Rankbeam](https://rankbeam.dev). The Filament companion
([`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament),
free) and the commercial Pro package are distributed separately and have their
own security processes — report an issue in the package where the affected code
actually lives.

## Supported versions

The current major (`3.x`) receives security fixes. Older majors are
end-of-life; upgrade to a supported release before reporting.

| Version | Supported |
|---|---|
| 3.x | ✅ |
| 2.x and earlier | ❌ |

Fixes ship in a normal patch release on the current major; see
[CHANGELOG.md](CHANGELOG.md) and [UPGRADING.md](UPGRADING.md).

## Reporting a vulnerability

**Please do not open a public GitHub issue for a security problem.** Report it
privately through either channel:

- **GitHub private vulnerability reporting** — on the
  [repository's Security tab](https://github.com/rankbeam/laravel-seo/security),
  choose **Report a vulnerability**. This keeps the report and discussion
  private until a fix is released.
- **Email** — [hello@rankbeam.dev](mailto:hello@rankbeam.dev). Use the subject
  line `SECURITY` and describe the issue in the body.

Please include, where you can:

- the affected version(s) and PHP/Laravel versions,
- a description of the vulnerability and its impact,
- a minimal reproduction or proof of concept,
- any suggested remediation.

## What to expect

Rankbeam is maintained by a small team, so timelines are best-effort rather than
a contractual SLA:

- We aim to **acknowledge** a report within a few business days.
- We validate the issue, prepare a fix, and coordinate a release. We will keep
  you updated on progress.
- We practise **coordinated disclosure**: please give us reasonable time to
  release a fix before any public write-up, and we will credit you in the
  release notes if you would like.

There is no paid bug-bounty programme at this time.

## Scope

In scope: vulnerabilities in this package's own code — for example, output that
is not correctly escaped when rendered by `TagRenderer`, an unsafe canonical or
sitemap URL, or a console command that writes outside its documented paths.

Out of scope: misconfiguration of your own application, vulnerabilities in
Laravel or third-party dependencies (report those upstream), and issues that
require an already-compromised environment. When in doubt, report it privately
and we will triage.
