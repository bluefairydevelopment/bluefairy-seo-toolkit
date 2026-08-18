=== BlueFairy SEO Toolkit Lite ===
Contributors: bluefairydevelopment
Tags: seo, open graph, sitemap, xml sitemap, social media
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Essential Open Graph tags and a flat XML sitemap — no bloat. Automatically pauses when Yoast SEO, Rank Math, SEOPress, or The SEO Framework is active.

== Description ==

BlueFairy SEO Toolkit Lite gives you the two things every WordPress site needs to look great when shared on social media and to be properly indexed by search engines: **Open Graph tags** and an **XML sitemap**. Nothing else. No settings screens to wade through, no bloat.

**Conflict detection is built in.** When the plugin detects Yoast SEO, Rank Math, SEOPress, or The SEO Framework, it automatically pauses its own Open Graph output and sitemap so you never end up with duplicate meta tags. Duplicate OG tags break how your content appears in Facebook, LinkedIn, and other social crawlers — BlueFairy SEO Toolkit Lite prevents that automatically and shows a clear admin notice explaining what it found.

**Open Graph tags output:**

* `og:title` — post title, or site name on the homepage
* `og:description` — post excerpt (fallback: first 155 characters of content). Tagline on homepage.
* `og:url` — canonical URL for the page
* `og:type` — `article` for singular post entries, `website` for everything else
* `og:site_name` — your site name
* `og:image` — featured image (large size), if set

Tags are suppressed on 404, search results, and feed pages where they would be incorrect or harmful.

**XML sitemap:**

* Served at `/sitemap.xml`
* Includes all published posts and pages (up to 1,000 entries)
* `<lastmod>` from post_modified_gmt
* `<changefreq>` based on post age: daily (< 7 days), weekly (< 90 days), monthly (90+ days)
* Cached for 12 hours; cache invalidated automatically when posts are saved, deleted, or trashed
* Correct HTTP headers: `Content-Type: application/xml` and `X-Robots-Tag: noindex`

**Upgrade to Pro** for JSON-LD schema markup, canonical URL management, Twitter Cards meta tags, and a per-post SEO meta box with overrides for title, description, and OG image.

== Installation ==

1. Upload the `bluefairy-seo-toolkit-lite` folder to `/wp-content/plugins/`.
2. Activate the plugin from the **Plugins** screen.
3. Visit **Settings > Permalinks** and click **Save Changes** — this flushes WordPress rewrite rules so `/sitemap.xml` starts resolving. (The plugin flushes rules on activation automatically, but some hosting configurations require a manual flush.)
4. Visit `/sitemap.xml` on your site to confirm the sitemap is live.

No configuration required. Open Graph tags are active immediately on activation.

== Frequently Asked Questions ==

= Why is my sitemap returning a 404? =

Visit **Settings > Permalinks** and click **Save Changes** without changing anything. This flushes WordPress rewrite rules. The plugin registers its rewrite rule on activation, but some server configurations or caching plugins may need a manual flush before `/sitemap.xml` resolves correctly.

= Does it work alongside Yoast SEO, Rank Math, SEOPress, or The SEO Framework? =

Yes — it detects these plugins automatically and pauses its own output. You will see a notice in wp-admin confirming the conflict was detected. There is no need to configure anything; the detection is automatic. You can safely deactivate BlueFairy SEO Toolkit Lite if you prefer to rely on one of those plugins exclusively.

= What does the Pro version add? =

The Pro version adds:

* **JSON-LD schema markup** — Article, WebSite, Organization, LocalBusiness, Product, and more
* **Canonical URL management** — automatic canonical tags with configurable query string stripping
* **Twitter Cards meta tags** — `twitter:card`, `twitter:site`, and `twitter:title`
* **Per-post SEO meta box** — override title, description, and OG image on a post-by-post basis
* **WooCommerce integration** — Product schema and Pinterest Rich Pin tags

[Upgrade to Pro →](https://wordpress.bluefairydevelopment.com/product/seo-toolkit/)

= Will it slow down my site? =

No. The plugin adds two lightweight hooks: one action on `wp_head` (priority 2) and a rewrite rule. The sitemap XML is cached in a transient for 12 hours, so database queries only run on the first request after activation or after a post is saved.

= Does it support multisite? =

The plugin works on single-site installs. Multisite support (network-wide activation, per-subsite sitemaps) is available in the Pro version.

== Screenshots ==

1. The flat XML sitemap at /sitemap.xml — all published posts and pages with lastmod and changefreq.
2. Open Graph meta tags injected into a post's &lt;head&gt;: og:title, og:description, og:url, og:type, og:site_name.
3. The plugin row in Plugins > Installed Plugins — no settings required, auto-pauses when a competing SEO plugin is detected.

== Changelog ==

= 1.0.0 =
* Initial release.
* Open Graph tags: og:title, og:description, og:url, og:type, og:site_name, og:image.
* Flat XML sitemap at /sitemap.xml with lastmod and changefreq.
* Automatic conflict detection: Yoast SEO, Rank Math, SEOPress, The SEO Framework.
* Admin notice when a conflict is detected.
* Upgrade to Pro link in the plugin action links row.
* 12-hour transient cache for the sitemap; invalidated on post save/delete/trash.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
