# BlueFairy SEO Toolkit — Feature Guide

Version 1.1.2 | GPL-2.0-or-later | PHP 8.1+ | WordPress 6.6+

BlueFairy SEO Toolkit does two things and does them properly: it writes Open
Graph tags into your `<head>`, and it serves a paginated XML sitemap. There is
no dashboard to learn and nothing you must configure before it starts working.

---

## Open Graph tags

Five tags are written on every page where they make sense.

| Tag | Value |
|---|---|
| `og:title` | The post title. The site name on the front page. |
| `og:description` | The post excerpt, falling back to the first 55 words of the content with tags and shortcodes stripped. The site tagline on the front page, and on a password-protected post, so its text never appears in the page head. |
| `og:url` | The canonical URL of the page, with tracking parameters removed (see below). On archives this is the archive itself: a post type archive such as the WooCommerce shop, a category, tag, author or date archive. |
| `og:type` | `article` on singular post entries, `website` everywhere else. |
| `og:site_name` | Your site name. |

Output is suppressed on 404 pages, search results and feeds, where these tags
would describe something that is not a real document.

> **No `og:image`.** The free plugin does not emit an image tag. Shared links
> therefore render as text-only cards on Facebook, LinkedIn and Slack. The
> image tag and its fallback chain are a Pro feature — see the comparison at
> the end of this guide.

---

### URL cleaning

The URL written into `og:url` is not simply the current address. Tracking and
filter parameters are stripped first, so a visit arriving from a campaign link
or a WooCommerce filter still reports the clean page URL to social crawlers.

Always removed: anything beginning `utm_`, `fbclid`, `gclid`, anything
beginning `attribute_pa_`, and `orderby`, `min_price`, `max_price`,
`rating_filter`, `add-to-cart`, `variation_id`, `replytocom`, `unapproved` and
`moderation-hash`.

This plugin does not emit a `<link rel="canonical">` tag of its own — WordPress
core's own canonical tag stays in place. Emitting the canonical tag, overriding
it per post, and extending this strip list from the settings screen are Pro
features.

---

## XML sitemaps

### The sitemap index

Served at `/sitemap_index.xml`. It lists one entry per post type in scope,
plus one entry per additional page where a post type has more than 1,000
published entries.

### Post-type sitemaps

Served at `/sitemap-<post-type>-<page>.xml`, for example
`/sitemap-post-1.xml` and `/sitemap-page-1.xml`.

The free plugin covers **posts and pages**. Each sitemap carries, per URL:

- `<loc>` — the permalink
- `<lastmod>` — from `post_modified_gmt`
- `<changefreq>` — derived from the age of the post: `daily` under 7 days,
  `weekly` under 90 days, `monthly` beyond that

### Pagination

1,000 URLs per file. A site with 2,500 posts gets `sitemap-post-1.xml`,
`sitemap-post-2.xml` and `sitemap-post-3.xml`, all three listed in the index.

### Caching

The index is cached for one hour and each post-type sitemap for one day.
Caches are cleared automatically whenever a post is saved, trashed or deleted,
so a new post appears in the sitemap immediately rather than at the next
expiry.

Caching goes through the WordPress transient API, which means it works
correctly on sites running Redis or Memcached as an external object cache.

### Sitemap exclusions

**Settings → SEO → Advanced** lists every public post type and taxonomy with a
checkbox. Tick one and it disappears from the sitemap index and stops being
served. Useful for a post type that exists for internal structure and has no
business in search results.

### Setting the page URL from an add-on (`bfst_canonical_url`)

Added in 1.1.2. The URL written into `og:url` passes through a filter, so a
plugin with its own archive or profile pages can supply the right address:

```php
add_filter( 'bfst_canonical_url', function ( $url, $context ) {
    if ( is_my_profile_page() ) {
        return my_profile_url();
    }
    return $url;
}, 10, 2 );
```

Return an empty string to leave `og:url` out on that page.

### Excluding posts from an add-on (`bfst_sitemap_exclusion_meta_query`)

Added in 1.1.1. The clause that keeps a post out of the sitemap is built once,
in `SitemapManager::exclusion_meta_query()`, and passed through a filter before
any query runs:

```php
add_filter( 'bfst_sitemap_exclusion_meta_query', function ( $meta_query, $post_type ) {
    $meta_query[] = [ 'key' => '_my_hide_flag', 'compare' => 'NOT EXISTS' ];
    return $meta_query;
}, 10, 2 );
```

Two things worth knowing before you use it:

- The exclusion happens at **query** level, not while the XML is written, so a
  post removed this way is also absent from the page COUNT. The index and the
  pages it advertises stay consistent — an index that advertises a page the
  router then 404s is the failure this avoids.
- The plugin's own `_bfst_exclude_sitemap` clause is always applied as well.
  A filter that returns an empty array cannot open the sitemap up to posts the
  site owner excluded by hand.

Before 1.1.1 the sitemap exposed no filter at all, and the clause was written
out in three query builders kept in step by a comment. An add-on could set a
`noindex` robots directive on a post and had no way to take that same post out
of the sitemap — which Search Console reports as an error, because the sitemap
is asking a crawler to index a page the page itself refuses.

### Correct headers

Every sitemap response carries `Content-Type: application/xml; charset=utf-8`
and `X-Robots-Tag: noindex`, so the sitemap itself never appears in search
results.

---

## Conflict detection

On every load the plugin checks for Yoast SEO, Rank Math, SEOPress and The SEO
Framework. If any of them is active, BlueFairy SEO Toolkit pauses its own
output and shows one admin notice naming what it found.

This matters because duplicate Open Graph tags are worse than none: crawlers
encountering two `og:title` tags may pick either, and the result is
unpredictable rather than merely wrong.

Detection is automatic and needs no configuration. If you want to run both
anyway — say, another plugin for meta tags and this one for the sitemap — the
feature toggles below let you re-enable either half individually.

---

## Settings

**Settings → SEO.**

### Active features

Two checkboxes: **Open Graph tags** and **XML Sitemaps**. Both are on after
activation. Turning one off stops that subsystem entirely.

### Sitemap exclusions

Described above.

That is the whole settings page. Everything else the plugin does is either
automatic or does not need an opinion from you.

---

## Installation

1. Upload the `bluefairy-seo-toolkit` folder to `/wp-content/plugins/`, or
   install the zip from **Plugins → Add New → Upload Plugin**.
2. Activate it from the **Plugins** screen.
3. Visit `/sitemap_index.xml` to confirm the sitemap is live.

If the sitemap returns 404, go to **Settings → Permalinks** and click **Save
Changes** without changing anything. The plugin flushes rewrite rules on
activation, but a few hosting configurations need the manual flush as well.

Open Graph tags are active the moment you activate the plugin. There is
nothing to configure.

---

## Uninstalling

Deleting the plugin through the WordPress Plugins screen removes every option
it created and every transient it cached, and flushes rewrite rules so
`/sitemap_index.xml` stops resolving. Nothing is left in your database.

---

## What SEO Toolkit Pro adds

You have Open Graph tags and a working sitemap. What you do not yet have is
any of the structured data search engines actually read, an image on your
shared links, or control over which URL Google treats as the real one.

That is what Pro adds. It installs **alongside** this plugin rather than
replacing it — your settings carry over untouched, and this plugin keeps doing
exactly what it does now.

| Feature | SEO Toolkit Pro | SEO Toolkit (this plugin) |
|---|---|---|
| Open Graph: `og:title`, `og:description`, `og:url`, `og:type`, `og:site_name` | Yes | Yes |
| `og:image`, with a five-step fallback chain | Yes | — |
| Twitter Cards: `twitter:card`, `twitter:site` | Yes | — |
| Article dates: `article:published_time`, `article:modified_time` | Yes | — |
| Pinterest Rich Pins: `product:price:amount`, `product:price:currency` | Yes | — |
| JSON-LD `@graph` — WebSite, Organization, LocalBusiness, BreadcrumbList, Article, Person, Product | Yes | — |
| URL cleaning: `utm_*`, `fbclid`, `gclid`, WooCommerce filter and WordPress internal parameters stripped | Yes | Yes |
| A `<link rel="canonical">` tag in the page head | Yes | — |
| Removes WordPress core's duplicate `rel=canonical` tag | Yes | — |
| Per-post canonical override | Yes | — |
| Extra strip parameters configurable in Settings | Yes | — |
| A `<meta name="description">` tag in the page head | Yes | — |
| Per-post SEO title, replacing the whole document title | Yes | — |
| Per-post meta description, with excerpt and content fallbacks | Yes | — |
| Per-post noindex, which also drops the page from the sitemap | Yes | — |
| Per-post SEO meta box: title, description, noindex, schema type, OG image, canonical override, sitemap exclusion | Yes | — |
| Sitemap index at `/sitemap_index.xml` | Yes | Yes |
| Post types included in the sitemap | Every public post type | Posts and pages |
| Taxonomy sitemaps (categories, tags, custom taxonomies) | Yes | — |
| Google image extensions inside the sitemaps | Yes | — |
| Pagination at 1,000 URLs per sitemap file | Yes | Yes |
| Sitemap caching, invalidated on save, trash and delete | Yes | Yes |
| Site-wide sitemap exclusions in Settings | Yes | Yes |
| Conflict detection: Yoast SEO, Rank Math, SEOPress, The SEO Framework | Yes | Yes |
| Feature toggles on the settings page | 4 | 2 |

### Why the missing rows cost you

**Every link you share is a text card.** Without `og:image` your posts appear
on Facebook, LinkedIn and Slack as a headline and a grey box. Pro's fallback
chain tries a custom image you picked, then the featured image, then the first
image in the content, then your organisation logo, then the site icon — so
there is almost always something to show.

**Search engines are guessing at your content.** JSON-LD is how you tell Google
that this page is an Article with an author and a publish date, or a Product
with a price and a rating. Pro emits a single `@graph` block covering seven
node types, built from data your site already holds.

**Two canonical tags, or the wrong one.** This plugin cleans the URL it reports
to social crawlers, but the canonical tag on the page is still WordPress
core's, which carries none of that cleaning. Pro emits the cleaned canonical,
removes core's duplicate so there is exactly one, and lets you override it per
post when a page genuinely lives somewhere else.

**Only half your site is in the sitemap.** This plugin lists posts and pages.
Pro lists every public post type plus taxonomy archives, with image extensions
so Google Images can find your pictures.

### Get Pro

**https://wordpress.bluefairydevelopment.com/product/bluefairy-seo-toolkit-pro/**

One payment, no subscription. Pro requires this plugin to be installed and
active — it is an add-on, not a replacement.
