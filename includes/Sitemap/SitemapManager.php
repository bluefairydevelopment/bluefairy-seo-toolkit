<?php
/**
 * Registers sitemap rewrite rules and routes sitemap requests.
 *
 * @package BlueFairy\SeoToolkit\Sitemap
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Sitemap;

/**
 * Manages sitemap URL routing.
 *
 * register_rules() is a standalone static method called from BOTH:
 * - the init hook (normal requests)
 * - Installer::activate() (during activation, before init fires)
 *
 * This ensures rewrite rules are registered before flush_rewrite_rules()
 * is called on activation.
 *
 * query_vars filter is registered in register() (not inside register_rules())
 * so it is only registered once — not duplicated on activation.
 *
 * Routing via template_redirect: sets Content-Type, outputs XML, and exits.
 * Disabled sitemap returns a proper 404 rather than a silent no-op.
 */
class SitemapManager {

    /**
     * Handler classes, substitutable by an add-on.
     *
     * The Pro add-on swaps in subclasses that add image extensions and
     * taxonomy entries; it does not reimplement either sitemap.
     *
     * @var array<string, class-string>
     */
    protected static array $handlers = [
        'index'     => SitemapIndex::class,
        'post_type' => PostTypeSitemap::class,
    ];

    /**
     * Substitute a handler class.
     *
     * @param string       $kind  'index' or 'post_type'.
     * @param class-string $class Subclass of the corresponding base handler.
     */
    public static function set_handler( string $kind, string $class ): void {
        self::$handlers[ $kind ] = $class;
    }

    /**
     * Post types the sitemap covers.
     *
     * The base plugin ships posts and pages, which is what the free plugin has
     * always covered. The Pro add-on filters this to every public post type.
     * Both SitemapIndex and route_sitemap() read it, so the index can never
     * advertise a sitemap the router would 404.
     *
     * @return string[]
     */
    public static function post_types(): array {
        $types = apply_filters( 'bfst_sitemap_post_types', [ 'post', 'page' ] );
        return is_array( $types ) ? array_values( array_filter( array_map( 'strval', $types ) ) ) : [ 'post', 'page' ];
    }

    /**
     * Drop every cached sitemap, index included.
     *
     * Needed whenever the SHAPE of the sitemap changes rather than its
     * contents — activating or deactivating an add-on that widens the scope,
     * for instance. The per-post invalidation hooks cannot see that: they fire
     * on save_post and only clear the pages for that post's type. Without this,
     * dropping the Pro add-on left the index advertising taxonomy sitemaps that
     * had started returning 404, for up to a day.
     *
     * @since 1.1.0
     */
    /**
     * Option holding the keys of every sitemap transient currently set.
     */
    private const CACHE_INDEX_OPTION = 'bfst_sitemap_cache_keys';

    /**
     * Record a sitemap transient key so it can be deleted later.
     *
     * @param string $key Transient key, without the _transient_ prefix.
     */
    public static function remember_cache_key( string $key ): void {
        $keys = get_option( self::CACHE_INDEX_OPTION, [] );
        if ( ! is_array( $keys ) ) {
            $keys = [];
        }
        if ( ! in_array( $key, $keys, true ) ) {
            $keys[] = $key;
            update_option( self::CACHE_INDEX_OPTION, $keys, false );
        }
    }

    /**
     * Drop every cached sitemap, index included.
     *
     * Needed whenever the SHAPE of the sitemap changes rather than its
     * contents — activating or deactivating an add-on that widens the scope,
     * for instance. The per-post invalidation hooks cannot see that: they fire
     * on save_post and only clear the pages for that post's type. Without this,
     * dropping the Pro add-on left the index advertising taxonomy sitemaps that
     * had started returning 404, for up to a day.
     *
     * Deletion goes through delete_transient() against a recorded index of
     * keys, because that is the only method correct on BOTH backends. An
     * earlier version swept wp_options with a LIKE query, which finds nothing
     * on any site running Redis or Memcached — transients never reach the
     * options table there, so the flush silently did nothing on exactly the
     * sites most likely to be caching hard. The SQL sweep is kept afterwards
     * as a mop-up for keys written before this index existed.
     *
     * @since 1.1.0
     */
    public static function flush_caches(): void {
        SitemapIndex::invalidate();

        $keys = get_option( self::CACHE_INDEX_OPTION, [] );
        if ( is_array( $keys ) ) {
            foreach ( $keys as $key ) {
                delete_transient( (string) $key );
            }
        }
        delete_option( self::CACHE_INDEX_OPTION );

        // Mop up anything cached before the index existed. Only ever finds
        // rows on sites without an external object cache; harmless elsewhere.
        if ( ! wp_using_ext_object_cache() ) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $stale = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_bfst_sitemap_%'"
            );
            foreach ( (array) $stale as $name ) {
                delete_transient( (string) preg_replace( '/^_transient_/', '', (string) $name ) );
            }
        }
    }

    /**
     * Register SitemapManager hooks on plugins_loaded.
     *
     * Called from Plugin::boot(). Registers rules on init, query_vars filter,
     * and routes template_redirect to the appropriate sitemap handler.
     */
    public static function register(): void {
        add_action( 'init', [ self::class, 'register_rules' ] );
        add_action( 'template_redirect', [ self::class, 'route_sitemap' ] );

        // Register here (not inside register_rules) so it is added exactly once.
        add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );

        // Register sitemap cache invalidation hooks.
        PostTypeSitemap::register_invalidation_hooks();

        // Prevent WordPress canonical redirect from appending a trailing slash
        // to sitemap URLs before the rewrite rule can handle the request.
        add_filter( 'redirect_canonical', [ self::class, 'prevent_sitemap_redirect' ], 10, 2 );
    }

    /**
     * Add custom sitemap query vars.
     *
     * Registered via register() so it is not duplicated when register_rules()
     * is called a second time during activation.
     *
     * @param string[] $vars
     * @return string[]
     */
    public static function add_query_vars( array $vars ): array {
        $vars[] = 'bfst_sitemap';
        $vars[] = 'bfst_sitemap_page';
        return $vars;
    }

    /**
     * Prevent WordPress canonical redirect from adding a trailing slash to sitemap URLs.
     *
     * Without this, redirect_canonical fires before the rewrite rule resolves
     * bfst_sitemap, so sitemap_index.xml → 301 → sitemap_index.xml/ → 200.
     * Google's sitemap crawler doesn't follow that redirect.
     *
     * @param string|false $redirect_url
     * @param string       $requested_url
     * @return string|false
     */
    public static function prevent_sitemap_redirect( string|false $redirect_url, string $requested_url ): string|false {
        if ( get_query_var( 'bfst_sitemap', '' ) !== '' ) {
            return false;
        }
        return $redirect_url;
    }

    /**
     * Register rewrite rules for all sitemap URLs.
     *
     * Must be a standalone static method (not an anonymous function) so it
     * can be called from both the init hook and Installer::activate().
     *
     * URL patterns:
     * - /sitemap_index.xml
     * - /sitemap-{type}-{page}.xml  (e.g. sitemap-post-1.xml, sitemap-category-1.xml)
     */
    public static function register_rules(): void {
        add_rewrite_rule(
            '^sitemap_index\.xml$',
            'index.php?bfst_sitemap=index',
            'top'
        );

        add_rewrite_rule(
            '^sitemap-([a-z0-9_-]+)-([0-9]+)\.xml$',
            'index.php?bfst_sitemap=$matches[1]&bfst_sitemap_page=$matches[2]',
            'top'
        );
    }

    /**
     * Route incoming requests to the appropriate sitemap handler.
     *
     * Called on template_redirect. Checks for bfst_sitemap query var and
     * delegates to the registered index or post-type handler, then offers
     * the request to add-ons through bfst_render_sitemap.
     * Sets Content-Type to application/xml and exits after output.
     * Returns a 404 if the sitemap feature is disabled or the type is unknown.
     */
    public static function route_sitemap(): void {
        $sitemap = get_query_var( 'bfst_sitemap', '' );

        if ( $sitemap === '' ) {
            return;
        }

        // Check whether the sitemap feature is enabled.
        $features = get_option( 'bfst_features', [] );
        if ( is_array( $features ) && isset( $features['sitemap'] ) && ! $features['sitemap'] ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            return;
        }

        $page = max( 1, (int) get_query_var( 'bfst_sitemap_page', 1 ) );

        // Set XML content type headers before any output.
        if ( ! headers_sent() ) {
            header( 'Content-Type: application/xml; charset=UTF-8' );
            header( 'X-Robots-Tag: noindex, follow' );
        }

        if ( $sitemap === 'index' ) {
            $class   = self::$handlers['index'];
            $handler = new $class();
            echo $handler->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML output
            exit;
        }

        if ( in_array( $sitemap, self::post_types(), true ) ) {
            $class   = self::$handlers['post_type'];
            $handler = new $class();
            $output  = $handler->render( $sitemap, $page );

            // An empty string means the page is past the end of the sitemap.
            // Fall through to the 404 below rather than serving an empty 200,
            // which is what let /sitemap-post-900001.xml look like a real page.
            if ( $output !== '' ) {
                echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML output
                exit;
            }
        }

        /**
         * Render a sitemap kind the base plugin does not know about.
         *
         * The Pro add-on serves taxonomy sitemaps from here. Return a string to
         * output it; return null to fall through to the 404 below.
         *
         * @since 1.1.0
         *
         * @param string|null $output  Rendered XML, or null.
         * @param string      $sitemap Requested sitemap name.
         * @param int         $page    Page number, 1-based.
         */
        $output = apply_filters( 'bfst_render_sitemap', null, $sitemap, $page );
        if ( is_string( $output ) && $output !== '' ) {
            echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML output
            exit;
        }

        // Unknown sitemap type — return 404.
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
    }
}
