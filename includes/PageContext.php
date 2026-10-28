<?php
/**
 * PageContext — single source of truth for the current page.
 *
 * Built once on the `wp` hook after the query is established. Injected into
 * all renderers so each is a pure function of context rather than calling
 * WordPress global functions independently.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit;

/**
 * Immutable snapshot of the current page context.
 *
 * All properties are readonly so no renderer can mutate shared state.
 */
final class PageContext {

    /**
     * Page type: 'singular', 'archive', 'home', 'search', or '404'.
     */
    public readonly string $type;

    /**
     * Current post object (singular pages only).
     */
    public readonly ?\WP_Post $post;

    /**
     * Current taxonomy term (archive pages only).
     */
    public readonly ?\WP_Term $term;

    /**
     * Current author (author archive pages only).
     */
    public readonly ?\WP_User $author;

    /**
     * Pre-computed canonical URL for this page.
     * Empty string on 404 and search pages.
     */
    public readonly string $canonical;

    /**
     * Whether the current page is a WooCommerce product.
     */
    public readonly bool $is_product;

    /**
     * Whether the current page is the static front page.
     */
    public readonly bool $is_front_page;

    /**
     * Whether WooCommerce is active.
     */
    public readonly bool $is_woocommerce;

    /**
     * ID of the primary queried object (post ID or term ID).
     */
    public readonly ?int $queried_id;

    /**
     * All _bfst_* post meta for the current post, loaded in a single call.
     * Array keys are the full meta key names (including the underscore prefix).
     *
     * @var array<string, mixed>
     */
    public readonly array $post_meta;

    /**
     * Build a PageContext from the current WordPress global state.
     * Call this only on the `wp` hook (after the query is established).
     *
     * @return self
     */
    public static function build(): self {
        $ctx = new self();
        $ctx->populate();
        return $ctx;
    }

    /**
     * Shared instance for the current request.
     */
    private static ?self $instance = null;

    /**
     * Register the `wp` hook to build and cache the PageContext.
     * HeadManager calls this during register().
     */
    public static function init(): void {
        add_action( 'wp', static function (): void {
            self::$instance = self::build();
        } );
    }

    /**
     * Return the built PageContext for the current request.
     * Returns null if called before the `wp` hook has fired.
     */
    public static function current(): ?self {
        return self::$instance;
    }

    /**
     * Populate all readonly properties from global WordPress state.
     */
    private function populate(): void {
        $this->is_woocommerce = function_exists( 'WC' );
        $this->is_front_page  = is_front_page();

        // Determine type and primary object.
        if ( is_singular() ) {
            $this->type = 'singular';
            $queried    = get_queried_object();
            $this->post = $queried instanceof \WP_Post ? $queried : null;
            $this->term = null;
            $this->author = null;
            $this->queried_id = $this->post instanceof \WP_Post ? $this->post->ID : null;
            $this->is_product = $this->is_woocommerce && is_singular( 'product' );
        } elseif ( is_author() ) {
            $this->type = 'archive';
            $this->post = null;
            $queried = get_queried_object();
            $this->term = null;
            $this->author = $queried instanceof \WP_User ? $queried : null;
            $this->queried_id = $this->author instanceof \WP_User ? $this->author->ID : null;
            $this->is_product = false;
        } elseif ( is_tax() || is_category() || is_tag() ) {
            $this->type = 'archive';
            $this->post = null;
            $queried = get_queried_object();
            $this->term = $queried instanceof \WP_Term ? $queried : null;
            $this->author = null;
            $this->queried_id = $this->term instanceof \WP_Term ? $this->term->term_id : null;
            $this->is_product = false;
        } elseif ( is_home() || is_front_page() ) {
            $this->type = 'home';
            $this->post = null;
            $this->term = null;
            $this->author = null;
            $this->queried_id = null;
            $this->is_product = false;
        } elseif ( is_search() ) {
            $this->type = 'search';
            $this->post = null;
            $this->term = null;
            $this->author = null;
            $this->queried_id = null;
            $this->is_product = false;
        } elseif ( is_404() ) {
            $this->type = '404';
            $this->post = null;
            $this->term = null;
            $this->author = null;
            $this->queried_id = null;
            $this->is_product = false;
        } else {
            // Catch-all: any archive or unknown page type.
            $this->type = 'archive';
            $this->post = null;
            $this->term = null;
            $this->author = null;
            $this->queried_id = null;
            $this->is_product = false;
        }

        // Load all _bfst_* post meta in a single call (no third arg = return array).
        // Each key maps to an array of values; we take the first for scalar fields.
        if ( $this->post instanceof \WP_Post ) {
            $raw_meta = get_post_meta( $this->post->ID );
            $bfst_meta = [];
            foreach ( $raw_meta as $key => $values ) {
                if ( str_starts_with( $key, '_bfst_' ) ) {
                    // Store the first value directly (scalar convenience).
                    $bfst_meta[ $key ] = $values[0] ?? '';
                }
            }
            $this->post_meta = $bfst_meta;
        } else {
            $this->post_meta = [];
        }

        // Pre-compute canonical URL.
        $this->canonical = $this->compute_canonical();
    }

    /**
     * Compute the canonical URL for the current page.
     *
     * Resolution order:
     * 1. _bfst_canonical override from post meta
     * 2. WordPress permalink
     * 3. Strip tracked query parameters
     * 4. WC variation → parent product URL
     * 5. Paginated archive → page 1 URL
     *
     * Returns '' on 404 and search pages.
     */
    private function compute_canonical(): string {
        // No canonical for 404 or search.
        if ( is_404() || is_search() ) {
            return '';
        }

        // 1. Custom override from post meta.
        //
        // Only honoured if it survives sanitisation. It used to return the
        // sanitised value unconditionally, so an override that esc_url_raw()
        // rejects — a javascript: URI written by an importer or WP-CLI, say —
        // produced an EMPTY canonical on a page that has a perfectly good
        // permalink. Fall through instead of suppressing the tag.
        $override = (string) ( $this->post_meta['_bfst_canonical'] ?? '' );
        if ( $override !== '' ) {
            $safe_override = esc_url_raw( $override );
            if ( $safe_override !== '' ) {
                return $safe_override;
            }
        }

        // 2. WordPress permalink.
        if ( $this->post instanceof \WP_Post ) {
            $url = (string) get_permalink( $this->post->ID );
        } elseif ( $this->term instanceof \WP_Term ) {
            $term_link = get_term_link( $this->term );
            $url = is_wp_error( $term_link ) ? home_url( '/' ) : (string) $term_link;
        } elseif ( $this->author instanceof \WP_User ) {
            $url = (string) get_author_posts_url( $this->author->ID );
        } elseif ( is_home() ) {
            $page_id = (int) get_option( 'page_for_posts' );
            $url = $page_id > 0 ? (string) get_permalink( $page_id ) : home_url( '/' );
        } elseif ( is_post_type_archive() ) {
            $post_type = get_query_var( 'post_type' );
            if ( is_array( $post_type ) ) {
                $post_type = reset( $post_type );
            }
            $archive = is_string( $post_type ) && $post_type !== '' ? get_post_type_archive_link( $post_type ) : false;
            $url = is_string( $archive ) && $archive !== '' ? $archive : home_url( '/' );
        } elseif ( is_date() ) {
            $year  = (int) get_query_var( 'year' );
            $month = (int) get_query_var( 'monthnum' );
            $day   = (int) get_query_var( 'day' );
            if ( is_day() ) {
                $url = (string) get_day_link( $year, $month, $day );
            } elseif ( is_month() ) {
                $url = (string) get_month_link( $year, $month );
            } else {
                $url = (string) get_year_link( $year );
            }
        } else {
            $url = home_url( '/' );
        }

        // 3. Strip query parameters.
        $url = $this->strip_query_params( $url );

        // 4. WooCommerce variation → parent product URL.
        // The variation's parent is already the product, and get_permalink above
        // returns the product URL for singular product pages. However if a
        // ?variation_id param was present we already stripped it in step 3.

        // 5. Paginated archive → page 1 URL (strip /page/N/ or ?paged=N).
        if ( is_paged() ) {
            // Remove /page/N/ from the URL.
            $url = preg_replace( '#/page/\d+/?$#', '/', $url ) ?? $url;
            // Remove paged query arg (already stripped in step 3 via WordPress internals?
            // paged is not in the strip list but pagination canonical is always page 1).
            $url = remove_query_arg( 'paged', $url );
        }

        /**
         * Filters the canonical URL the toolkit uses for og:url (and, with
         * Pro, rel=canonical). A plugin with its own archive pages can feed
         * the right URL here. Return '' to suppress it.
         *
         * @param string      $url The computed canonical URL.
         * @param PageContext $ctx This page context.
         */
        $filtered = apply_filters( 'bfst_canonical_url', $url, $this );

        return is_string( $filtered ) ? esc_url_raw( $filtered ) : esc_url_raw( $url );
    }

    /**
     * Strip tracked and WooCommerce query parameters from a URL.
     *
     * Parameters always stripped:
     * - Analytics: utm_*, fbclid, gclid
     * - WooCommerce: orderby, min_price, max_price, rating_filter, add-to-cart,
     *   attribute_pa_*, variation_id
     * - WordPress internals: replytocom, unapproved, moderation-hash
     *
     * Additional parameters from bfst_canonical_strip_params option are also removed.
     *
     * @param string $url Input URL.
     * @return string URL with tracked parameters removed.
     */
    private function strip_query_params( string $url ): string {
        $parsed = wp_parse_url( $url );
        if ( ! isset( $parsed['query'] ) || $parsed['query'] === '' ) {
            return $url;
        }

        parse_str( $parsed['query'], $params );
        if ( empty( $params ) ) {
            return $url;
        }

        // Core strip list.
        $always_strip = [
            // Analytics.
            'fbclid', 'gclid',
            // WooCommerce.
            'orderby', 'min_price', 'max_price', 'rating_filter', 'add-to-cart', 'variation_id',
            // WordPress internals.
            'replytocom', 'unapproved', 'moderation-hash',
        ];

        $filtered = [];
        foreach ( $params as $key => $value ) {
            $key_str = (string) $key;
            // Strip utm_* parameters.
            if ( str_starts_with( $key_str, 'utm_' ) ) {
                continue;
            }
            // Strip attribute_pa_* parameters (WooCommerce variation attributes).
            if ( str_starts_with( $key_str, 'attribute_pa_' ) ) {
                continue;
            }
            // Strip explicitly listed parameters.
            if ( in_array( $key_str, $always_strip, true ) ) {
                continue;
            }
            $filtered[ $key_str ] = $value;
        }

        // Additionally strip any admin-configured extra params.
        $extra_params = get_option( 'bfst_canonical_strip_params', [] );
        if ( is_array( $extra_params ) && ! empty( $extra_params ) ) {
            foreach ( $extra_params as $extra ) {
                $extra_key = sanitize_text_field( (string) $extra );
                unset( $filtered[ $extra_key ] );
            }
        }

        // Rebuild the URL.
        $scheme   = isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '';
        $host     = $parsed['host'] ?? '';
        $port     = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
        $path     = $parsed['path'] ?? '';
        $fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';
        $query    = empty( $filtered ) ? '' : '?' . http_build_query( $filtered );

        return $scheme . $host . $port . $path . $query . $fragment;
    }
}
