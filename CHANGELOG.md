# Changelog

All notable changes to BlueFairy SEO Toolkit are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/): major = architecture change, minor = new feature, patch = bug fix or security fix.

---

## [1.1.4] — 2026-10-28

### Changed
- When the front page shows your latest posts, the home page is now listed in
  the sitemap, in its own `/sitemap-frontpage-1.xml`, the first entry of the
  sitemap index. It was missing before. It is listed even when no post is
  published yet or posts are excluded from the sitemap. Sites with a static
  front page are unchanged: that page is already in the page sitemap.
- A post whose modification date is unset (`0000-00-00 00:00:00`, common in
  imported content) gets no `<lastmod>`; before, it showed 1970-01-01.

### Fixed
- A post type with no published entries was still listed in the sitemap index
  (a site with no published pages listed `/sitemap-page-1.xml`), and that URL
  returned a 404, which Search Console reports as an error. The index now
  leaves out a post type until it has at least one published entry.
- On a site with a persistent object cache (Redis, Memcached), deleting the
  plugin left the cached sitemap pages in the cache, where the database
  clean-up could not reach them. Each cached page is now deleted from the list
  the plugin keeps; that list is capped, and a page dropped from it is deleted
  at the same time.
- Deleting the plugin also removed the SEO Toolkit Pro add-on's cached
  category and tag sitemap pages. Those belong to Pro and are left for its own
  uninstall.

---

## [1.1.3] — 2026-10-28

### Fixed
- Deleting the plugin left its sitemap cache list (`bfst_sitemap_cache_keys`) in the database. It is now removed with the other options.

### Changed
- Maintenance: the code now meets the WordPress coding standards in full and every class method is covered by automated tests.

## [1.1.2] — 2026-10-27

### Fixed
- The plugin package no longer includes developer and internal files that were never meant to ship.
- `og:url` on post type archives (such as the WooCommerce shop) and date archives pointed at the home page; it is now the archive's own address.
- A password-protected post's excerpt or text could appear in `og:description` on the locked page. The site tagline is used instead.

### Added
- `bfst_canonical_url` filter, so an add-on can set the page URL used for `og:url`.

## [1.1.1] — 2026-08-31

### Added
- `bfst_sitemap_exclusion_meta_query` filter on the meta_query that keeps posts
  out of the sitemap. The sitemap previously exposed no filter at all, so an
  add-on had no way to exclude a post: SEO Toolkit Pro could set a robots
  noindex tag and nothing else, leaving the URL advertised in the sitemap while
  telling crawlers to ignore it — a combination Search Console reports as an
  error.

### Changed
- `SitemapManager::exclusion_meta_query()` is now the single source of the
  exclusion clause, called from `SitemapIndex`, `PostTypeSitemap::page_count()`
  and `PostTypeSitemap::build_xml()`. The clause was previously written out in
  all three and kept in step only by a comment. The index advertises a page
  count computed from the same query the pages are built from, and when the two
  diverged the index once advertised a page the router answered with a 404.
- Compatibility: tested against WordPress 7.1.

## [1.1.0] — 2026-08-30

Rebuilt as the free base plugin of a free/Pro pair. BlueFairy SEO Toolkit Pro
is now an add-on that extends these classes rather than a separate plugin that
duplicates them.

### Added
- Sitemap index at `/sitemap_index.xml` with paginated per-post-type children at `/sitemap-{post-type}-{n}.xml`, 1,000 URLs per file, replacing the single flat `/sitemap.xml`.
- Settings page at **Settings → SEO**: feature toggles for Open Graph and XML Sitemaps, and sitemap exclusions per post type and per taxonomy.
- URL cleaning before a URL is written into `og:url` — `utm_*`, `fbclid`, `gclid`, `attribute_pa_*`, WooCommerce filter and variation parameters, and WordPress comment parameters.
- Documented extension points so an add-on can extend the plugin: the `bfst_loaded` action, `bfst_register_head_renderers`, `bfst_feature_labels`, `bfst_sitemap_post_types`, `bfst_render_sitemap`, `bfst_settings_page_footer`, and `SitemapManager::set_handler()`.
- A "Get SEO Toolkit Pro" link in the plugins-list row and a callout beneath the settings form, both hidden while Pro is active.

### Changed
- `og:image` has been removed from this plugin. The image tag and its fallback chain are part of SEO Toolkit Pro.
- Sitemap caching now goes through the transient API with a tracked key index.

### Fixed
- Unauthenticated requests for non-existent sitemaps each wrote a transient, so anyone could flood the cache. Bogus sitemap URLs now return 404 and cache nothing.
- Cache invalidation deleted rows from the options table directly, so it did nothing at all on sites using Redis or Memcached as an external object cache.
- Sitemap queries requested `post_status = 'publish'` for every post type, so attachment sitemaps were advertised in the index and then 404ed by the router. Each post type's real status is now used.
- Hidden WooCommerce products were not excluded from the sitemap. The exclusion tested the `_visibility` post meta key, which WooCommerce stopped writing in 3.0 and now leaves empty; it reads the `product_visibility` taxonomy instead. The same exclusion is now applied when counting pages, which it previously was not — so the index could advertise more pages than existed.
- 16 translation strings used the wrong text domain and were never translated.
- Sitemap output escaping, multisite-safe pagination, readme headers, and a double-bind guard on head renderer registration.

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
