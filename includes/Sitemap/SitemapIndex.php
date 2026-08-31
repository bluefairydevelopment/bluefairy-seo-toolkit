<?php
/**
 * Generates the /sitemap_index.xml file.
 *
 * @package BlueFairy\SeoToolkit\Sitemap
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Sitemap;

/**
 * Outputs the sitemap index XML.
 *
 * Lists one <sitemap> entry per enabled post type covered by
 * SitemapManager::post_types(). The Pro add-on subclasses this to append
 * taxonomy entries. For large sites each source has paginated sub-sitemaps;
 * each page is listed as a separate <sitemap> entry.
 *
 * Cached in transient bfst_sitemap_index for HOUR_IN_SECONDS.
 * Invalidated by SitemapManager's save_post/trash/delete hooks via
 * PostTypeSitemap's invalidation methods.
 */
class SitemapIndex {

    /**
     * Transient key for the sitemap index cache.
     */
    private const TRANSIENT_KEY = 'bfst_sitemap_index';

    /**
     * Number of posts per sitemap page.
     */
    protected const POSTS_PER_PAGE = 1000;

    /**
     * Render the sitemap index XML.
     *
     * @return string XML string.
     */
    public function render(): string {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }

        $xml = $this->build_xml();

        set_transient( self::TRANSIENT_KEY, $xml, HOUR_IN_SECONDS );

        return $xml;
    }

    /**
     * Invalidate the sitemap index cache.
     * Called when posts are saved, trashed, or deleted.
     */
    public static function invalidate(): void {
        delete_transient( self::TRANSIENT_KEY );
    }

    /**
     * Build the sitemap index XML string.
     *
     * @return string
     */
    protected function build_xml(): string {
        return $this->entries_to_xml( $this->entries() );
    }

    /**
     * The sitemaps this index advertises.
     *
     * Subclasses call parent::entries() and append. The Pro add-on adds one
     * entry per taxonomy page that way, without restating the post-type loop.
     *
     * @return array<int, array<string, string>>
     */
    protected function entries(): array {
        $entries    = [];
        $exclusions = $this->exclusions();

        // Post type sitemaps. SitemapManager::post_types() is the single source
        // of scope, so the index can never advertise a sitemap that the router
        // would answer with a 404.
        foreach ( SitemapManager::post_types() as $post_type ) {
            if ( ! empty( $exclusions[ $post_type ] ) ) {
                continue;
            }

            // Count at SQL level (meta_query NOT EXISTS) so excluded posts don't
            // inflate page counts and produce empty trailing sitemap pages.
            $count      = $this->count_posts( $post_type );
            $page_count = max( 1, (int) ceil( $count / self::POSTS_PER_PAGE ) );

            for ( $page = 1; $page <= $page_count; $page++ ) {
                $entries[] = [
                    'loc'     => home_url( '/sitemap-' . $post_type . '-' . $page . '.xml' ),
                    'lastmod' => $this->get_post_type_lastmod( $post_type ),
                ];
            }
        }

        return $entries;
    }

    /**
     * Post types and taxonomies the site owner has excluded.
     *
     * @return array<string, mixed>
     */
    protected function exclusions(): array {
        $exclusions = get_option( 'bfst_sitemap_exclusions', [] );
        return is_array( $exclusions ) ? $exclusions : [];
    }

    /**
     * Count published posts for a post type (excluding excluded and hidden WC products).
     *
     * @param string $post_type
     * @return int
     */
    protected function count_posts( string $post_type ): int {
        $args = [
            'post_type'              => $post_type,
            'post_status'            => SitemapManager::post_status_for( $post_type ),
            'posts_per_page'         => 1,
            'no_found_rows'          => false,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'meta_query'             => [
                [ 'key' => '_bfst_exclude_sitemap', 'compare' => 'NOT EXISTS' ],
            ],
        ];

        // WooCommerce visibility: exclude hidden products.
        if ( $post_type === 'product' && function_exists( 'wc_get_product' ) ) {
            $args['meta_query'][] = [
                'relation' => 'OR',
                [ 'key' => '_visibility', 'value' => 'hidden', 'compare' => '!=' ],
                [ 'key' => '_visibility', 'compare' => 'NOT EXISTS' ],
            ];
        }

        $query = new \WP_Query( $args );
        return (int) $query->found_posts;
    }

    /**
     * Get the lastmod date for a post type (most recently modified published post).
     *
     * @param string $post_type
     * @return string ISO 8601 date string.
     */
    protected function get_post_type_lastmod( string $post_type ): string {
        $posts = get_posts( [
            'post_type'              => $post_type,
            'post_status'            => SitemapManager::post_status_for( $post_type ),
            'numberposts'            => 1,
            'orderby'                => 'modified',
            'order'                  => 'DESC',
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ] );

        if ( empty( $posts ) || ! $posts[0] instanceof \WP_Post ) {
            return gmdate( 'Y-m-d' );
        }

        return gmdate( 'Y-m-d', (int) strtotime( $posts[0]->post_modified_gmt ) );
    }

    /**
     * Convert an array of sitemap entries to XML string.
     *
     * @param array<int, array<string, string>> $entries
     * @return string
     */
    protected function entries_to_xml( array $entries ): string {
        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ( $entries as $entry ) {
            $lines[] = '  <sitemap>';
            $lines[] = '    <loc>' . esc_url( $entry['loc'] ) . '</loc>';
            if ( isset( $entry['lastmod'] ) && $entry['lastmod'] !== '' ) {
                $lines[] = '    <lastmod>' . esc_html( $entry['lastmod'] ) . '</lastmod>';
            }
            $lines[] = '  </sitemap>';
        }

        $lines[] = '</sitemapindex>';

        return implode( "\n", $lines );
    }
}
