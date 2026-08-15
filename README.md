# BlueFairy SEO Toolkit Lite

Essential Open Graph tags and a flat XML sitemap for WordPress — no bloat.

**Version:** 1.0.0
**Requires WordPress:** 6.6+
**Requires PHP:** 8.1+
**License:** GPL-2.0-or-later

---

## Features

- **Open Graph tags** — `og:title`, `og:description`, `og:url`, `og:type`, `og:site_name`, `og:image`
- **XML sitemap** at `/sitemap.xml` — posts + pages, `<lastmod>`, `<changefreq>`, 12-hour cache
- **Automatic conflict detection** — pauses all output when Yoast SEO, Rank Math, SEOPress, or The SEO Framework is active

## Installation

1. Copy the plugin folder to `wp-content/plugins/`.
2. Activate via **Plugins > Installed Plugins**.
3. Visit **Settings > Permalinks > Save Changes** to flush rewrite rules (ensures `/sitemap.xml` resolves).

## File Structure

```
bluefairy-seo-toolkit-lite.php    Plugin header + bootstrap
includes/
  class-plugin.php               Bootstrap, activation, deactivation, admin notices
  class-conflict-detector.php    Detects Yoast / Rank Math / SEOPress / The SEO Framework
  class-og-tags.php              Open Graph output (wp_head priority 2)
  class-sitemap.php              XML sitemap + rewrite rule + transient cache
uninstall.php                    Deletes transients and flushes rewrite rules on delete
README.md                        This file
readme.txt                       WordPress.org listing copy
CHANGELOG.md                     Version history
.gitignore                       Standard exclusions
```

## Conflict Detection

The plugin checks for the defining class or constant of each known SEO plugin as soon as `plugins_loaded` fires — before any output hooks run. If a conflict is found:

- All Open Graph output is suppressed (the `wp_head` action bails early).
- The sitemap returns a 404 (so it does not compete with the conflict plugin's own sitemap).
- A single admin notice is shown in wp-admin identifying the conflicting plugin(s).

Detection signatures:

| Plugin | Check |
|--------|-------|
| Yoast SEO | `class_exists('WPSEO_Options')` |
| Rank Math | `class_exists('RankMath')` |
| SEOPress | `defined('SEOPRESS_VERSION')` |
| The SEO Framework | `class_exists('The_SEO_Framework\\Load')` |

## Sitemap Caching

The sitemap XML is cached in a WordPress transient (`bfstl_sitemap_xml`) for 12 hours. The cache is deleted automatically on `save_post`, `delete_post`, and `trashed_post` for posts and pages.

## Upgrade to Pro

The Pro version adds JSON-LD schema markup, canonical URL management, Twitter Cards, and a per-post SEO meta box.

[Upgrade to Pro →](https://wordpress.bluefairydevelopment.com/product/seo-toolkit/)

## Coding Standards

- `declare(strict_types=1)` in every PHP file
- All output escaped with `esc_attr()`, `esc_url()`, `esc_html()`
- No `echo` in constructors or `register()` methods — only hook registration
- PSR-12 formatting
