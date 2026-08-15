# Changelog

All notable changes to BlueFairy SEO Toolkit Lite are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/): major = architecture change, minor = new feature, patch = bug fix or security fix.

---

## [1.0.0] — 2026-08-15

### Added
- Open Graph tags output in `wp_head` at priority 2: `og:title`, `og:description`, `og:url`, `og:type`, `og:site_name`, `og:image`.
- Tags suppressed on 404, search, and feed pages.
- Featured image (large size) used for `og:image` when set.
- Description fallback: post excerpt → first 155 characters of content (stripped) → bloginfo tagline.
- Flat XML sitemap at `/sitemap.xml` covering all published posts and pages (up to 1,000 entries).
- `<lastmod>` from `post_modified_gmt`; `<changefreq>` based on post age (daily / weekly / monthly).
- Correct HTTP headers on sitemap response: `Content-Type: application/xml; charset=utf-8` and `X-Robots-Tag: noindex`.
- 12-hour transient cache for sitemap XML; invalidated automatically on `save_post`, `delete_post`, `trashed_post`.
- Automatic conflict detection for Yoast SEO, Rank Math, SEOPress, and The SEO Framework using class/constant presence checks.
- All plugin output (OG tags + sitemap) paused when a conflict is detected.
- Single admin notice in wp-admin when a conflict is detected, naming the active conflicting plugin(s).
- "Upgrade to Pro" action link in the plugin row on Plugins > Installed Plugins.
- Minimum PHP 8.1 and WordPress 6.6 version check on activation with a graceful error message.
- `uninstall.php` cleans up the sitemap transient and flushes rewrite rules on plugin deletion.
