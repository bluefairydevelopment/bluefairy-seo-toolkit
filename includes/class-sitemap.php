<?php
/**
 * Flat XML sitemap at /sitemap.xml.
 *
 * Implementation notes:
 * - Rewrite rule registered on init and also during activation (before init fires).
 * - bfstl_sitemap is registered as a public query var.
 * - template_redirect handles output if the query var is set.
 * - Output cached in a transient for 12 hours; invalidated on save_post,
 *   delete_post, and trashed_post.
 * - Content-Type: application/xml; charset=utf-8
 * - X-Robots-Tag: noindex (sitemap itself should not be indexed)
 * - Limit: 1000 entries, ordered by post_modified DESC.
 * - changefreq: daily (< 7 days), weekly (< 90 days), monthly (>= 90 days).
 * - If a conflicting SEO plugin is active the sitemap returns a 404 so as not
 *   to compete with the conflict plugin's own sitemap.
 *
 * @package BlueFairy_SEO_Toolkit_Lite
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers sitemap rewrite rules and serves the XML sitemap response.
 */
final class BlueFairy_SEO_Toolkit_Lite_Sitemap {

    /**
     * Transient key for the cached sitemap XML.
     */
    private const TRANSIENT_KEY = 'bfstl_sitemap_xml';

    /**
     * Cache TTL: 12 hours in seconds.
     */
    private const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /**
     * Maximum number of entries to include in the sitemap.
     */
    private const MAX_ENTRIES = 1000;

    /**
     * Register all hooks.
     *
     * No echo in this method — only hook registration.
     * Called from Plugin::boot() via plugins_loaded.
     */
    public static function register(): void {
        add_action( 'init', [ self::class, 'register_rules' ] );
        add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );
        add_action( 'template_redirect', [ self::class, 'handle_request' ] );

        // Cache invalidation hooks.
        add_action( 'save_post',    [ self::class, 'invalidate_cache' ] );
        add_action( 'delete_post',  [ self::class, 'invalidate_cache' ] );
        add_action( 'trashed_post', [ self::class, 'invalidate_cache' ] );
    }

    /**
     * Register the sitemap rewrite rule.
     *
     * This is a standalone static method (not a closure) so it can be called
     * both from the init hook and directly from the activation hook — before
     * init has fired — to ensure rules exist before flush_rewrite_rules().
     */
    public static function register_rules(): void {
        add_rewrite_rule(
            '^sitemap\.xml$',
            'index.php?bfstl_sitemap=1',
            'top'
        );
    }

    /**
     * Register bfstl_sitemap as a public query var.
     *
     * @param string[] $vars
     * @return string[]
     */
    public static function add_query_vars( array $vars ): array {
        $vars[] = 'bfstl_sitemap';
        return $vars;
    }

    /**
     * Handle the sitemap request on template_redirect.
     *
     * Bails immediately if bfstl_sitemap query var is not set.
     * Returns a 404 if a conflicting SEO plugin is active.
     * Otherwise outputs cached (or freshly built) XML and exits.
     *
     * @return void
     */
    public static function handle_request(): void {
        if ( ! get_query_var( 'bfstl_sitemap' ) ) {
            return;
        }

        // If a conflict plugin owns the sitemap, hand off via 404 to avoid duplication.
        if ( BlueFairy_SEO_Toolkit_Lite_Conflict_Detector::has_conflicts() ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            return;
        }

        if ( ! headers_sent() ) {
            header( 'Content-Type: application/xml; charset=utf-8' );
            header( 'X-Robots-Tag: noindex' );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML is built with esc_url/esc_html internally
        echo self::get_xml();
        exit;
    }

    /**
     * Invalidate the cached sitemap XML.
     *
     * Called on save_post, delete_post, and trashed_post.
     *
     * @param int $post_id
     * @return void
     */
    public static function invalidate_cache( int $post_id ): void {
        // Only invalidate for post types we include in the sitemap.
        $post = get_post( $post_id );
        if ( ! $post instanceof WP_Post ) {
            return;
        }
        if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
            return;
        }
        delete_transient( self::TRANSIENT_KEY );
    }

    /**
     * Return the sitemap XML, from cache or freshly built.
     *
     * @return string Valid XML string.
     */
    private static function get_xml(): string {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }

        $xml = self::build_xml();
        set_transient( self::TRANSIENT_KEY, $xml, self::CACHE_TTL );
        return $xml;
    }

    /**
     * Build the sitemap XML document.
     *
     * Queries published posts and pages ordered by post_modified DESC, limited to
     * MAX_ENTRIES. Assigns changefreq based on how recently the post was modified:
     * - < 7 days  : daily
     * - < 90 days : weekly
     * - >= 90 days: monthly
     *
     * @return string Complete XML document as a string.
     */
    private static function build_xml(): string {
        $query = new WP_Query( [
            'post_type'              => [ 'post', 'page' ],
            'post_status'            => 'publish',
            'posts_per_page'         => self::MAX_ENTRIES,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'has_password'           => false,
        ] );

        $posts = $query->posts;

        $lines   = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        if ( is_array( $posts ) && ! empty( $posts ) ) {
            $now = time();

            foreach ( $posts as $post ) {
                if ( ! $post instanceof WP_Post ) {
                    continue;
                }

                $permalink = get_permalink( $post->ID );
                if ( ! is_string( $permalink ) || $permalink === '' ) {
                    continue;
                }

                // Use post_modified_gmt for the lastmod date (ISO 8601 date portion).
                $modified_gmt = $post->post_modified_gmt;
                $lastmod      = gmdate( 'Y-m-d', (int) strtotime( $modified_gmt ) );

                // Determine changefreq from post age.
                $age_seconds = $now - (int) strtotime( $modified_gmt );
                if ( $age_seconds < 7 * DAY_IN_SECONDS ) {
                    $changefreq = 'daily';
                } elseif ( $age_seconds < 90 * DAY_IN_SECONDS ) {
                    $changefreq = 'weekly';
                } else {
                    $changefreq = 'monthly';
                }

                $lines[] = '  <url>';
                $lines[] = '    <loc>' . esc_url( $permalink ) . '</loc>';
                $lines[] = '    <lastmod>' . esc_html( $lastmod ) . '</lastmod>';
                $lines[] = '    <changefreq>' . esc_html( $changefreq ) . '</changefreq>';
                $lines[] = '  </url>';
            }
        }

        $lines[] = '</urlset>';

        return implode( "\n", $lines );
    }
}
