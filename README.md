# BlueFairy SEO Toolkit

Open Graph tags and paginated XML sitemaps for WordPress — no bloat, no configuration.

**Version:** 1.1.0
**Requires WordPress:** 6.6+
**Requires PHP:** 8.1+
**License:** GPL-2.0-or-later

This is the free base plugin of a pair. [BlueFairy SEO Toolkit Pro](https://wordpress.bluefairydevelopment.com/product/bluefairy-seo-toolkit-pro/) is an add-on that extends the classes here; it does not replace this plugin and cannot run without it.

For the customer-facing feature list see `FEATURES.md`. For the Pro/free comparison — which is easy to get wrong in both directions — see `docs/COMPARISON.md`.

---

## Features

- **Open Graph tags** — `og:title`, `og:description`, `og:url`, `og:type`, `og:site_name`, suppressed on 404, search and feed pages. No `og:image`; that is a Pro feature.
- **XML sitemaps** — index at `/sitemap_index.xml`, per-post-type children at `/sitemap-{post-type}-{n}.xml`, paginated at 1,000 URLs per file, covering posts and pages.
- **URL cleaning** — `utm_*`, `fbclid`, `gclid`, `attribute_pa_*`, WooCommerce filter and variation parameters, and WordPress comment parameters stripped before a URL is written.
- **Conflict detection** — pauses all output when Yoast SEO, Rank Math, SEOPress, or The SEO Framework is active.
- **Settings → SEO** — two feature toggles and per-post-type and per-taxonomy sitemap exclusions.

## Installation

1. Copy the plugin folder to `wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Visit `/sitemap_index.xml` to confirm. If it 404s, go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules.

## File structure

```
bluefairy-seo-toolkit.php        Plugin header, constants, autoloader, lifecycle hooks
includes/
  Plugin.php                     Bootstrap; fires bfst_loaded, then binds subsystems
  Installer.php                  Activation, deactivation, requirement checks, defaults
  ConflictDetector.php           Detects Yoast / Rank Math / SEOPress / The SEO Framework
  PageContext.php                Per-request page facts, incl. the cleaned canonical URL
  Head/
    RendererInterface.php        feature_key() + render()
    HeadManager.php              Keyed renderer registry, bound to wp_head
    OpenGraphRenderer.php        The five base OG tags; non-final, build_tags() is protected
  Sitemap/
    SitemapManager.php           Rewrite rules, routing, handler registry, cache index
    SitemapIndex.php             /sitemap_index.xml
    PostTypeSitemap.php          /sitemap-{post-type}-{n}.xml; seams for subclasses
  Admin/
    SettingsPage.php             Settings → SEO; feature_labels() drives UI and sanitiser
    FeatureToggleEndpoint.php    REST toggle endpoint
    ProUpsell.php                Plugins-row link + settings-page callout, hidden when Pro is active
uninstall.php                    Removes every option and transient the plugin created
```

## Extension points

Pro is built entirely on these. They are public API — changing one is a breaking change.

| Hook | Type | Purpose |
|---|---|---|
| `bfst_loaded` | action | Fired by `Plugin::boot()` **before** any subsystem binds. This is where an add-on registers. Firing it afterwards would be useless: a sitemap handler cannot be substituted once routing has happened, and `bfst_register_head_renderers` would already have fired. Does not fire at all when this plugin is inactive, so an add-on must also check the dependency on `plugins_loaded`. |
| `bfst_register_head_renderers` | action | Fired by `HeadManager::register()`. Call `HeadManager::add_renderer()` from here. The registry is keyed on `feature_key()`, so registering a subclass under the same key replaces the base renderer rather than doubling it. |
| `bfst_feature_labels` | filter | Adds entries to the settings-page toggle list. The same array drives the checkbox UI, the sanitiser and the REST endpoint, so an added key is toggleable everywhere with no further work. |
| `bfst_sitemap_post_types` | filter | The single source of sitemap scope; defaults to `['post', 'page']`. |
| `bfst_render_sitemap` | filter | Serve a sitemap this plugin has no handler for. Return a string to take over, or `null` to pass. Pro uses it for taxonomy sitemaps. |
| `bfst_settings_page_footer` | action | Render below the settings form. |
| `SitemapManager::set_handler()` | method | Substitute the index or post-type sitemap class. |
| `HeadManager::will_render()` | method | Ask whether a feature key will actually render this request, rather than inferring it from the option. |

`OpenGraphRenderer` is deliberately non-final and its `build_tags()` is `protected`, so a subclass can call `parent::build_tags()` and append. `PostTypeSitemap` exposes `page_count()`, `prepare_entries()`, `urlset_attributes()` and `extra_url_lines()` for the same reason.

## Conflict detection

Checked on `plugins_loaded`, before any output hook runs. On a conflict all Open Graph output is suppressed, the sitemap 404s so it does not compete, and one admin notice names what was found.

| Plugin | Check |
|--------|-------|
| Yoast SEO | `class_exists('WPSEO_Options')` |
| Rank Math | `class_exists('RankMath')` |
| SEOPress | `defined('SEOPRESS_VERSION')` |
| The SEO Framework | `class_exists('The_SEO_Framework\\Load')` |

## Sitemap caching

The index caches for one hour, each post-type sitemap for one day, both through the transient API.

Cache keys are recorded in a tracked index and cleared with `delete_transient()`. An earlier version deleted `_transient_%` rows from `wp_options` with SQL, which meant invalidation did nothing whatsoever on any site using Redis or Memcached as an external object cache — the transients never lived in that table.

## Coding standards

- `declare(strict_types=1)` in every PHP file
- All output escaped with `esc_attr()`, `esc_url()`, `esc_html()`
- No output in constructors or `register()` methods — hook registration only
- PSR-12 formatting
