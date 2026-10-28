=== BlueFairy SEO Toolkit ===
Contributors: bluefairydevelopment
Donate link: https://ko-fi.com/bluefairydevelopment
Tags: seo, open graph, sitemap, xml sitemap, social media
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Open Graph tags and paginated XML sitemaps — no bloat, no configuration. Pauses automatically when another SEO plugin is active.

== Description ==

BlueFairy SEO Toolkit gives your WordPress site the two things it cannot do without: **Open Graph tags** so shared links render properly, and a **paginated XML sitemap** so search engines can find every post and page. Nothing else. No dashboard to learn, no settings you must fill in before it works.

**Conflict detection is built in.** When the plugin detects Yoast SEO, Rank Math, SEOPress, or The SEO Framework, it automatically pauses its own output so you never end up with duplicate meta tags. Duplicate Open Graph tags are worse than none — a crawler that finds two `og:title` tags may take either — so BlueFairy SEO Toolkit steps aside and shows a clear admin notice explaining what it found.

**Open Graph tags output:**

* `og:title` — post title, or site name on the homepage
* `og:description` — post excerpt (fallback: first 55 words of content, then the site tagline)
* `og:url` — the page URL, with tracking parameters stripped
* `og:type` — `article` for posts, `product` for WooCommerce products, `website` for everything else
* `og:site_name` — your site name

Tags are suppressed on 404, search results, and feed pages, where they would describe something that is not a real document.

This plugin does **not** output `og:image`. Shared links therefore render as text-only cards. The image tag and its fallback chain are part of SEO Toolkit Pro — see the comparison below.

**URL cleaning:**

Before a URL is written into `og:url`, campaign and filter parameters are removed: anything beginning `utm_`, plus `fbclid`, `gclid`, anything beginning `attribute_pa_`, and `orderby`, `min_price`, `max_price`, `rating_filter`, `add-to-cart`, `variation_id`, `replytocom`, `unapproved` and `moderation-hash`.

**XML sitemaps:**

* A sitemap index at `/sitemap_index.xml`
* One child sitemap per post type at `/sitemap-{post-type}-{n}.xml`, covering posts and pages
* Paginated at 1,000 URLs per file
* `<lastmod>` from `post_modified_gmt`
* `<changefreq>` derived from post age: daily (under 7 days), weekly (under 90 days), monthly beyond that
* Password-protected posts and hidden WooCommerce products are excluded
* Cached through the transient API — one hour for the index, one day per sitemap — so it behaves correctly on Redis and Memcached
* Cache cleared automatically when a post is saved, trashed or deleted
* Correct headers: `Content-Type: application/xml; charset=utf-8` and `X-Robots-Tag: noindex`

**Settings (Settings → SEO):**

* Two feature toggles — Open Graph tags and XML Sitemaps — both on by default
* Sitemap exclusions: tick any post type or taxonomy to keep it out of the sitemap

**Add SEO Toolkit Pro** for JSON-LD schema markup, `og:image` with a five-step fallback chain, Twitter Cards, a real canonical tag with per-post overrides, whole-site and taxonomy sitemaps with image extensions, and a per-post SEO meta box. Pro installs alongside this plugin rather than replacing it.

== Installation ==

1. Upload the `bluefairy-seo-toolkit` folder to `/wp-content/plugins/`, or install the zip from **Plugins → Add New → Upload Plugin**.
2. Activate the plugin from the **Plugins** screen.
3. Visit `/sitemap_index.xml` on your site to confirm the sitemap is live.

No configuration required. Open Graph tags are active immediately on activation.

If the sitemap returns 404, visit **Settings → Permalinks** and click **Save Changes** without changing anything. The plugin flushes rewrite rules on activation, but some hosting configurations need the manual flush as well.

== Frequently Asked Questions ==

= Why is my sitemap returning a 404? =

Visit **Settings → Permalinks** and click **Save Changes** without changing anything. This flushes WordPress rewrite rules. The plugin registers its rewrite rules on activation, but some server configurations or caching plugins need a manual flush before `/sitemap_index.xml` resolves.

Note the URL: it is `/sitemap_index.xml`, not `/sitemap.xml`.

= Does it work alongside Yoast SEO, Rank Math, SEOPress, or The SEO Framework? =

Yes — it detects these plugins automatically and pauses its own output, and shows a notice in wp-admin confirming what it found. There is nothing to configure.

If you would rather run both — say, another plugin for meta tags and this one for the sitemap — the two feature toggles on **Settings → SEO** let you re-enable either half on its own.

= Why are my shared links showing no image? =

Because this plugin does not output an `og:image` tag. That is a Pro feature, along with the fallback chain that picks a custom image, the featured image, the first image in your content, your organisation logo, or the site icon — whichever it finds first.

= What does SEO Toolkit Pro add? =

Pro is an add-on that runs alongside this plugin — it does not replace it, and your settings carry over.

* **`og:image`** with a five-step fallback chain
* **JSON-LD schema markup** — WebSite, Organization, LocalBusiness, BreadcrumbList, Article, Person and Product in a single `@graph` block
* **Twitter Cards** — `twitter:card` and `twitter:site`
* **Article dates and Pinterest Rich Pins** — `article:published_time`, `article:modified_time`, `product:price:amount`, `product:price:currency`
* **A real canonical tag** — emitted by the plugin, with WordPress core's duplicate removed, plus per-post overrides and a configurable strip list
* **Whole-site sitemaps** — every public post type, plus taxonomy sitemaps, with Google image extensions
* **A per-post SEO meta box** — schema type, custom OG image, canonical override, and exclude-from-sitemap

[Get SEO Toolkit Pro →](https://wordpress.bluefairydevelopment.com/product/bluefairy-seo-toolkit-pro/)

= Will it slow down my site? =

No. On the front end the plugin adds one `wp_head` action and a rewrite rule. Sitemap XML is cached in transients, so database queries only run on the first request after activation or after a post changes.

= Does it support multisite? =

The plugin is built and tested for single-site installs. It contains no multisite-specific code, and neither does Pro — if you need network-wide behaviour, neither plugin provides it.

= What happens to my data if I uninstall it? =

Deleting the plugin through the Plugins screen removes every option it created and every transient it cached, and flushes rewrite rules. Nothing is left behind in your database.

== Screenshots ==

1. Settings → SEO — the two feature toggles and the sitemap exclusions list.
2. The sitemap index at /sitemap_index.xml, listing the post and page sitemaps.
3. The five Open Graph meta tags in a post's &lt;head&gt;.

== Changelog ==

= 1.1.4 =
* Changed: when the front page shows your latest posts, the home page is now listed first in the post sitemap. Sites with a static front page are unchanged.
* Fixed: a post type with no published entries (for example a site with no published pages) was still listed in the sitemap index, and that listed sitemap returned a 404. It is now left out until it has something in it.
* Fixed: on a site with a persistent object cache (Redis, Memcached), deleting the plugin left the cached sitemap pages in the cache; they are now removed.
* Fixed: deleting the plugin also removed the Pro add-on's cached taxonomy sitemap pages; they are now left for Pro's own uninstall.

= 1.1.3 =
* Fixed: deleting the plugin left its sitemap cache list in the database; it is now removed.
* Maintenance: the code now meets the WordPress coding standards in full and every class method is covered by automated tests.

= 1.1.2 =
* Fixed: The plugin package no longer includes developer and internal files that were never meant to ship.
* Fixed: `og:url` on post type archives (such as the WooCommerce shop) and date archives pointed at the home page.
* Fixed: A password-protected post's excerpt or text could appear in `og:description` on the locked page.
* Added: `bfst_canonical_url` filter, so an add-on can set the page URL used for `og:url`.

= 1.1.1 =
* Added: `bfst_sitemap_exclusion_meta_query` filter, so an add-on can exclude posts from every sitemap. The sitemap previously exposed no filter at all.
* Changed: the sitemap exclusion clause is now built once in `SitemapManager::exclusion_meta_query()` instead of being repeated in three query builders that had to be kept in step by hand.
* Compatibility: tested against WordPress 7.1.

= 1.1.0 =
* Rebuilt as the free base plugin of a pair, with documented extension points so BlueFairy SEO Toolkit Pro can extend it rather than duplicate it.
* Sitemaps are now a sitemap index at `/sitemap_index.xml` with paginated per-post-type children, replacing the single flat `/sitemap.xml`.
* Added a settings page at Settings → SEO with feature toggles and per-post-type and per-taxonomy sitemap exclusions.
* Added URL cleaning: `utm_*`, `fbclid`, `gclid`, WooCommerce filter and variation parameters and WordPress comment parameters are stripped from `og:url`.
* Removed `og:image`; the image tag and its fallback chain are now part of SEO Toolkit Pro.
* Fixed: unauthenticated requests for non-existent sitemaps could each write a transient. Bogus sitemap URLs now return 404 and cache nothing.
* Fixed: cache invalidation swept the options table directly, so it did nothing at all on sites using Redis or Memcached as an object cache. Cache keys are now tracked in an index and cleared through the transient API.
* Fixed: sitemap queries asked for `post_status = publish` for every post type, so attachment sitemaps were advertised in the index and then 404ed by the router.
* Fixed: hidden WooCommerce products were not excluded from the sitemap. The exclusion tested the `_visibility` post meta key, which WooCommerce stopped writing in version 3.0; it now reads the `product_visibility` taxonomy. The same exclusion is now applied when counting pages, which it previously was not.
* Fixed: 16 translation strings used the wrong text domain and were never translated.
* Fixed: sitemap output escaping, multisite-safe pagination, and readme headers.

= 1.0.0 =
* Initial release.
* Open Graph tags: og:title, og:description, og:url, og:type, og:site_name.
* Flat XML sitemap at /sitemap.xml with lastmod and changefreq.
* Automatic conflict detection: Yoast SEO, Rank Math, SEOPress, The SEO Framework.
* Admin notice when a conflict is detected.
* 12-hour transient cache for the sitemap; invalidated on post save/delete/trash.

== Upgrade Notice ==

= 1.1.4 =
The home page is now in the sitemap when the front page shows your latest posts, the sitemap index no longer lists an empty post type's sitemap, and deleting the plugin on a site with a persistent object cache (Redis, Memcached) now also removes its cached sitemap pages. No action required.

= 1.1.3 =
Maintenance release; deleting the plugin now also removes its sitemap cache list. No action required.

= 1.1.2 =
Removes files that did not belong in the plugin package. No action required.

= 1.1.1 =
No action required. BlueFairy SEO Toolkit Pro 1.1.1 requires this version — update this plugin first if you use Pro.

= 1.1.0 =
The sitemap has moved from /sitemap.xml to /sitemap_index.xml — resubmit it in Google Search Console. og:image has moved to SEO Toolkit Pro. Fixes a bug that left hidden WooCommerce products in your sitemap.
