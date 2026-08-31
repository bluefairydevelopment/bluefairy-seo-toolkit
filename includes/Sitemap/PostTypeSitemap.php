<?php
/**
 * Generates paginated post type sitemap XML.
 *
 * @package BlueFairy\SeoToolkit\Sitemap
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Sitemap;

/**
 * Outputs a post type sub-sitemap with image extensions.
 *
 * Performance optimisations (per design):
 * - no_found_rows: true (no COUNT(*) query)
 * - update_post_term_cache: false
 * - update_post_meta_cache: false
 * - meta_query NOT EXISTS _bfst_exclude_sitemap (query-level, not PHP filter)
 *   so pagination counts are correct
 *
 * WooCommerce visibility: excludes products with _visibility = 'hidden'
 * and search-only products.
 *
 * Caching: each page cached in transient keyed by
 * 'bfst_sitemap_' . md5( $post_type . '_' . $page ) for DAY_IN_SECONDS.
 * Transients invalidated on save_post, wp_trash_post, before_delete_post.
 */
class PostTypeSitemap {

    /**
     * Posts per sitemap page.
     */
    private const POSTS_PER_PAGE = 1000;

    /**
     * Register cache invalidation hooks.
     * Called from SitemapManager::register() via plugins_loaded.
     */
    public static function register_invalidation_hooks(): void {
        add_action( 'save_post', [ self::class, 'invalidate_on_save' ] );
        add_action( 'wp_trash_post', [ self::class, 'invalidate_on_save' ] );
        add_action( 'before_delete_post', [ self::class, 'invalidate_on_save' ] );
    }

    /**
     * Invalidate all post type sitemap transients.
     * Called on save_post, wp_trash_post, before_delete_post.
     *
     * @param int $post_id
     */
    public static function invalidate_on_save( int $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post instanceof \WP_Post ) {
            return;
        }

        // Invalidate all pages for this post type (brute-force up to 10 pages).
        // For most sites one pass is enough; large sites regenerate on next hit.
        for ( $page = 1; $page <= 10; $page++ ) {
            $key = 'bfst_sitemap_' . md5( $post->post_type . '_' . $page );
            delete_transient( $key );
        }

        // Also invalidate the sitemap index.
        SitemapIndex::invalidate();
    }

    /**
     * Attributes for the opening <urlset> element.
     *
     * The base plugin declares only the sitemap namespace. The Pro add-on
     * overrides this to add the image namespace alongside it — declaring a
     * namespace whose elements never appear would be wrong, and the base
     * plugin emits no <image:image>.
     *
     * @return string
     */
    protected function urlset_attributes(): string {
        return 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
    }

    /**
     * Resolve anything a subclass needs before the URLs are written.
     *
     * Called once per sitemap with every post ID it will contain, so a subclass
     * can run one batched query instead of one per URL.
     *
     * @param int[] $post_ids
     */
    protected function prepare_entries( array $post_ids ): void {
    }

    /**
     * Extra lines to nest inside a single <url> element.
     *
     * The Pro add-on returns its <image:image> blocks here.
     *
     * @param int $post_id
     * @return string[]
     */
    protected function extra_url_lines( int $post_id ): array {
        return [];
    }

    /**
     * Render a page of the post type sitemap.
     *
     * @param string $post_type Post type slug.
     * @param int    $page      Sitemap page number (1-based).
     * @return string XML string.
     */
    public function render( string $post_type, int $page ): string {
        $transient_key = 'bfst_sitemap_' . md5( $post_type . '_' . $page );

        $cached = get_transient( $transient_key );
        if ( is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }

        $xml = $this->build_xml( $post_type, $page );

        set_transient( $transient_key, $xml, DAY_IN_SECONDS );

        return $xml;
    }

    /**
     * Build the XML for one page of a post type sitemap.
     *
     * @param string $post_type
     * @param int    $page
     * @return string
     */
    protected function build_xml( string $post_type, int $page ): string {
        $query_args = [
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => self::POSTS_PER_PAGE,
            'paged'                  => $page,
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'has_password'           => false,
            'meta_query'             => [
                // Query-level exclusion so pagination counts remain correct.
                [ 'key' => '_bfst_exclude_sitemap', 'compare' => 'NOT EXISTS' ],
            ],
        ];

        // WooCommerce: exclude hidden and search-only products from sitemap.
        if ( $post_type === 'product' && function_exists( 'wc_get_product' ) ) {
            $query_args['meta_query'][] = [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [ 'key' => '_visibility', 'value' => 'hidden', 'compare' => '!=' ],
                    [ 'key' => '_visibility', 'compare' => 'NOT EXISTS' ],
                ],
                [
                    'relation' => 'OR',
                    [ 'key' => '_visibility', 'value' => 'search', 'compare' => '!=' ],
                    [ 'key' => '_visibility', 'compare' => 'NOT EXISTS' ],
                ],
            ];
        }

        $query = new \WP_Query( $query_args );
        $posts = $query->posts;

        if ( ! is_array( $posts ) || empty( $posts ) ) {
            return $this->empty_xml();
        }

        // Collect post IDs so a subclass can resolve extras in one batched query
        // rather than one per URL.
        $post_ids = array_map(
            static fn( $p ) => $p instanceof \WP_Post ? $p->ID : 0,
            $posts
        );
        $post_ids = array_filter( $post_ids );

        $this->prepare_entries( $post_ids );

        $lines   = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset ' . $this->urlset_attributes() . '>';

        foreach ( $posts as $post ) {
            if ( ! $post instanceof \WP_Post ) {
                continue;
            }

            $permalink = get_permalink( $post->ID );
            if ( ! is_string( $permalink ) || $permalink === '' ) {
                continue;
            }

            $lastmod = gmdate( 'Y-m-d', (int) strtotime( $post->post_modified_gmt ) );

            $lines[] = '  <url>';
            $lines[] = '    <loc>' . esc_url( $permalink ) . '</loc>';
            $lines[] = '    <lastmod>' . esc_html( $lastmod ) . '</lastmod>';

            foreach ( $this->extra_url_lines( $post->ID ) as $line ) {
                $lines[] = $line;
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode( "\n", $lines );
    }

    /**
     * Return an empty but valid sitemap XML document.
     *
     * @return string
     */
    protected function empty_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
               '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }
}
