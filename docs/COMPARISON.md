# BlueFairy SEO Toolkit — Pro vs Free

Single source of truth for the feature comparison table. Verified line by line
against the shipped code of both plugins at 1.1.0 on 2026-08-30.

Every downstream copy of this table — the hub page, the FAQ, both readme.txt
files, both features guides and both product descriptions — derives from here.
Change a row here first, then rebuild every node.

| Feature | SEO Toolkit Pro | SEO Toolkit (free) |
|---|---|---|
| Open Graph: `og:title`, `og:description`, `og:url`, `og:type`, `og:site_name`, `og:locale` | Yes | Yes |
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
| Per-post SEO meta box: schema type, OG image, canonical override, sitemap exclusion | Yes | — |
| Sitemap index at `/sitemap_index.xml` | Yes | Yes |
| Post types included in the sitemap | Every public post type | Posts and pages |
| Taxonomy sitemaps (categories, tags, custom taxonomies) | Yes | — |
| Google image extensions inside the sitemaps | Yes | — |
| Pagination at 1,000 URLs per sitemap file | Yes | Yes |
| Sitemap caching, invalidated on save, trash and delete | Yes | Yes |
| Site-wide sitemap exclusions in Settings | Yes | Yes |
| Conflict detection: Yoast SEO, Rank Math, SEOPress, The SEO Framework | Yes | Yes |
| Feature toggles on the settings page | 4 | 2 |

## Notes on individual rows

**URL cleaning is a free feature, and this row is easy to get wrong.** The
stripping code lives in the FREE plugin, in `PageContext::strip_query_params()`,
and it runs whether or not Pro is installed — the cleaned URL is what the free
plugin puts in `og:url`. What Pro adds is the `<link rel="canonical">` tag
itself, the per-post override, the configurable extra-parameter list, and the
removal of core's own canonical tag. Do not write "Pro strips UTM parameters"
as though the free plugin does not: it does.

Parameters always stripped: anything starting `utm_`, `fbclid`, `gclid`,
anything starting `attribute_pa_`, plus `orderby`, `min_price`, `max_price`,
`rating_filter`, `add-to-cart`, `variation_id`, `replytocom`, `unapproved` and
`moderation-hash`.

**`og:image`.** The free plugin emits five Open Graph tags and no image tag.
This is the single most visible difference: a link shared without `og:image`
renders as a bare text card on Facebook, LinkedIn and Slack. Pro's chain is
five steps, not four — custom OG image from the meta box, then the featured
image, then the first `<img>` in the content, then the configured organisation
logo, then the site icon.

**Schema `@graph`.** Seven top-level node types. `Offer`, `AggregateRating`
and `Review` appear as child nodes inside `Product` when the data exists; they
are not separate top-level nodes and must not be counted as such. The per-post
schema type override offers Article, BlogPosting, FAQPage, HowTo, NewsArticle,
WebPage and Product — those are override values, not additional graph nodes.

**Attachment pages.** Pro follows WordPress's own `wp_attachment_pages_enabled`
setting. With attachment pages disabled — the default since WordPress 6.4 —
attachments are excluded from the sitemap, because core 301-redirects them to
the file and listing them would ask crawlers to fetch redirects.

**Feature toggles.** Free ships Open Graph and XML Sitemaps. Pro adds Schema
Markup and Canonical URL Management to the same list.

## Claims that are NOT true and must never be restored

These appeared in the pre-split copy and were removed on 2026-08-30. Each one
was checked against the code and found false.

- **"Pro adds multisite support."** Neither plugin has any multisite-specific
  code. The old free readme.txt said Pro offered "network-wide activation,
  per-subsite sitemaps". Nothing of the sort exists.
- **"The meta box overrides title and description."** It does not. Its four
  controls are schema type, OG image, canonical URL and sitemap exclusion.
- **"Pro adds `twitter:title`."** Pro emits `twitter:card` and `twitter:site`
  only.
- **"The sitemap is a flat file at `/sitemap.xml`."** It has been an index at
  `/sitemap_index.xml` with paginated children since 1.1.0.
- **"The free plugin emits `og:image`."** It does not, and this is the point of
  the upgrade.
