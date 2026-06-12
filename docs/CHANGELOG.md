# Changelog

All notable changes to Laravel SEO Suite will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Features that are in development but not yet released

### Changed
- Changes to existing functionality in development

### Deprecated
- Features that will be removed in future versions

### Removed
- Features removed in this release

### Fixed
- Bug fixes in development

### Security
- Security improvements in development

---

## [1.0.0] - YYYY-MM-DD

### Added
- Initial release of Laravel SEO Suite
- **HasSEO Trait** - Add SEO capabilities to any Eloquent model
- **Content Analyzer** - 32+ SEO rules with scoring and recommendations
  - Keyword analysis rules (density, placement, distribution)
  - Meta tag validation (title, description length)
  - Content quality checks (readability, heading structure)
  - Link analysis (internal, external, broken)
  - Media validation (image alt tags, broken images)
  - Technical SEO checks (canonical, robots, schema)
- **Multi-Keyword Support** - Primary and secondary keywords with synonyms
- **JSON-LD Schema Generation**
  - Article schema
  - FAQ schema
  - Product schema
  - LocalBusiness schema
  - Organization schema
  - Breadcrumb schema
- **XML Sitemap Generator** - Automatic generation with configurable options
- **Redirect Manager** - 301/302 redirects with regex support
- **404 Monitoring** - Track and resolve broken links
- **Google Analytics 4 Integration** - Dashboard integration with cached metrics
- **Internal Links Suggester** - Smart linking suggestions based on content analysis
- **Multi-locale Support** - Full i18n with hreflang tag generation
- **Multi-Stack Support**
  - Filament admin panel components
  - Livewire components
  - Vue 3 components (Inertia.js)
  - React components (Inertia.js)
  - API-only mode
- **Cache Management** - Centralized cache warming and clearing
- **Artisan Commands**
  - `seo:install` - Interactive installer
  - `seo:cache` - Cache management
  - `seo:scan` - Sitewide SEO scanning
  - `seo:sitemap` - Sitemap generation
  - `seo:sync-analytics` - GA4 data sync
  - `seo:health` - Health check
- **Blade Directives**
  - `@seo($model)` - All SEO tags
  - `@seoForRoute('route.name')` - Route-based SEO
  - `@seoTitle`, `@seoMeta`, `@seoSchema`, etc.

### Requirements
- PHP 8.2+
- Laravel 11.0+
- MySQL 8.0+ / PostgreSQL 13+ / SQLite 3.35+

---

## Migration Notes

### Upgrading to 1.x from 0.x

> Template for future major version upgrades

#### Breaking Changes

1. **Namespace Change**
   ```php
   // Before
   use OldVendor\SEO\Traits\HasSEO;

   // After
   use Rankbeam\Seo\Traits\HasSEO;
   ```

2. **Configuration Key Renames**
   ```php
   // Before
   'default_image' => '...',

   // After
   'default_og_image' => '...',
   ```

3. **Method Signature Changes**
   ```php
   // Before
   $model->getSEO();

   // After
   $model->seoData();
   ```

#### Migration Steps

1. **Update composer.json:**
   ```bash
   composer require rankbeam/laravel-seo:^1.0
   ```

2. **Re-publish config:**
   ```bash
   php artisan vendor:publish --tag=seo-config --force
   ```

3. **Run migrations:**
   ```bash
   php artisan migrate
   ```

4. **Update imports in your code:**
   ```bash
   # Use your IDE's find/replace or:
   grep -r "OldVendor\\SEO" app/ --include="*.php" -l | xargs sed -i 's/OldVendor\\SEO/Rankbeam\\Seo/g'
   ```

5. **Clear caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan seo:cache --clear
   ```

---

## Version History Template

Use this template when adding new releases:

```markdown
## [X.Y.Z] - YYYY-MM-DD

### Added
- New feature A ([#123](link-to-pr))
- New feature B ([#124](link-to-pr))

### Changed
- Improved performance of X ([#125](link-to-pr))
- Updated dependency Y to version Z

### Deprecated
- Method `oldMethod()` is deprecated, use `newMethod()` instead

### Removed
- Removed support for PHP 8.1

### Fixed
- Fixed bug where X didn't work correctly ([#126](link-to-pr))
- Fixed typo in configuration ([#127](link-to-pr))

### Security
- Fixed XSS vulnerability in tag renderer ([#128](link-to-pr))
```

---

## Release Checklist

Before each release:

- [ ] All tests passing
- [ ] Documentation updated
- [ ] CHANGELOG.md updated
- [ ] Version bumped in composer.json
- [ ] Migration notes added (if breaking changes)
- [ ] Tag created in git
- [ ] Package published

---

## Links

- [GitHub Releases](https://github.com/rankbeam/laravel-seo/releases)
- [Documentation](./README.md)
- [Upgrade Guide](./UPGRADE.md)

[Unreleased]: https://github.com/rankbeam/laravel-seo/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/rankbeam/laravel-seo/releases/tag/v1.0.0
