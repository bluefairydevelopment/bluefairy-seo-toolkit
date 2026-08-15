<?php
/**
 * Outputs Open Graph meta tags in wp_head.
 *
 * Tags are suppressed on 404, search, and feed pages.
 * All output is escaped with esc_attr() / esc_url().
 * If any conflicting SEO plugin is detected, no tags are output.
 *
 * @package BlueFairy_SEO_Toolkit_Lite
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers and renders Open Graph meta tags.
 *
 * Tags output:
 * - og:title       Post title; site name on homepage.
 * - og:description Post excerpt (fallback: first 155 chars of content). Bloginfo description on homepage.
 * - og:url         get_permalink() or home_url('/') on homepage.
 * - og:type        'article' for singular posts, 'website' for everything else.
 * - og:site_name   get_bloginfo('name').
 * - og:image       Featured image at 'large' size, if set.
 */
final class BlueFairy_SEO_Toolkit_Lite_OG_Tags {

    /**
     * Register the wp_head hook.
     *
     * No echo in this method — only hook registration.
     */
    public static function register(): void {
        // Priority 2 so OG tags follow the <title> tag at priority 1.
        add_action( 'wp_head', [ self::class, 'render' ], 2 );
    }

    /**
     * Output Open Graph meta tags.
     *
     * Called via wp_head at priority 2. Bails early if:
     * - A conflicting SEO plugin is active.
     * - Current page is a 404, search result, or feed.
     *
     * @return void
     */
    public static function render(): void {
        // Conflict guard — most important check, runs first.
        if ( BlueFairy_SEO_Toolkit_Lite_Conflict_Detector::has_conflicts() ) {
            return;
        }

        // Do not output on pages where OG tags are not useful or would be wrong.
        if ( is_404() || is_search() || is_feed() ) {
            return;
        }

        $tags = [];

        $tags[] = self::meta_tag( 'og:title', self::get_title() );
        $tags[] = self::meta_tag( 'og:description', self::get_description() );
        $tags[] = self::meta_tag( 'og:url', self::get_url() );
        $tags[] = self::meta_tag( 'og:type', self::get_type() );
        $tags[] = self::meta_tag( 'og:site_name', (string) get_bloginfo( 'name' ) );

        $og_image = self::get_image();
        if ( $og_image !== '' ) {
            $tags[] = self::image_tag( $og_image );
        }

        // Filter out empty strings from tags that had no content.
        $output = implode( "\n", array_filter( $tags ) );

        if ( $output === '' ) {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all values escaped inside meta_tag() / image_tag()
        echo "\n" . $output . "\n";
    }

    /**
     * Build a <meta property="..." content="..."> tag.
     *
     * @param string $property The og: property name.
     * @param string $content  The content value. Returns '' if content is empty.
     * @return string
     */
    private static function meta_tag( string $property, string $content ): string {
        $content = trim( $content );
        if ( $content === '' ) {
            return '';
        }
        return sprintf(
            '<meta property="%s" content="%s">',
            esc_attr( $property ),
            esc_attr( $content )
        );
    }

    /**
     * Build an og:image meta tag using esc_url() for the URL value.
     *
     * @param string $url Absolute image URL.
     * @return string
     */
    private static function image_tag( string $url ): string {
        $url = trim( $url );
        if ( $url === '' ) {
            return '';
        }
        return sprintf(
            '<meta property="og:image" content="%s">',
            esc_url( $url )
        );
    }

    /**
     * Determine the og:title for the current page.
     *
     * @return string
     */
    private static function get_title(): string {
        if ( is_singular() ) {
            $post = get_queried_object();
            if ( $post instanceof WP_Post ) {
                return (string) get_the_title( $post );
            }
        }

        if ( is_front_page() || is_home() ) {
            return (string) get_bloginfo( 'name' );
        }

        // Category, tag, taxonomy, author, date archives.
        return (string) get_the_archive_title();
    }

    /**
     * Determine the og:description for the current page.
     *
     * On singular posts/pages: excerpt if set; otherwise first 155 characters of
     * content (stripped of HTML and shortcodes).
     * On homepage: bloginfo description.
     * On archives: archive description or bloginfo description.
     *
     * @return string
     */
    private static function get_description(): string {
        if ( is_singular() ) {
            $post = get_queried_object();
            if ( $post instanceof WP_Post ) {
                if ( $post->post_excerpt !== '' ) {
                    return wp_strip_all_tags( $post->post_excerpt );
                }
                // Auto-generate from content: strip shortcodes + HTML, trim to 155 chars.
                $content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
                $content = preg_replace( '/\s+/', ' ', $content );
                $content = trim( (string) $content );
                if ( strlen( $content ) > 155 ) {
                    $content = substr( $content, 0, 155 );
                    // Trim to last word boundary to avoid cutting mid-word.
                    $last_space = strrpos( $content, ' ' );
                    if ( $last_space !== false ) {
                        $content = substr( $content, 0, $last_space );
                    }
                    $content = trim( $content ) . '…';
                }
                return $content;
            }
        }

        // Homepage and blog index.
        if ( is_front_page() || is_home() ) {
            return (string) get_bloginfo( 'description' );
        }

        // Archive pages.
        $description = (string) get_the_archive_description();
        if ( $description !== '' ) {
            return wp_strip_all_tags( $description );
        }

        return (string) get_bloginfo( 'description' );
    }

    /**
     * Determine the canonical URL for og:url.
     *
     * @return string
     */
    private static function get_url(): string {
        if ( is_singular() ) {
            $permalink = get_permalink();
            return is_string( $permalink ) ? $permalink : '';
        }

        if ( is_front_page() || is_home() ) {
            return home_url( '/' );
        }

        return '';
    }

    /**
     * Determine the og:type for the current page.
     *
     * 'article' for singular post post-type entries; 'website' for everything else.
     *
     * @return string
     */
    private static function get_type(): string {
        if ( is_singular( 'post' ) ) {
            return 'article';
        }
        return 'website';
    }

    /**
     * Resolve the og:image URL for the current page.
     *
     * Uses the featured image at 'large' size. Returns '' if no featured image is set
     * or the queried object is not a post.
     *
     * @return string Absolute image URL or '' if not available.
     */
    private static function get_image(): string {
        if ( ! is_singular() ) {
            return '';
        }

        $post = get_queried_object();
        if ( ! $post instanceof WP_Post ) {
            return '';
        }

        $url = get_the_post_thumbnail_url( $post, 'large' );
        if ( ! is_string( $url ) || $url === '' ) {
            return '';
        }

        return $url;
    }
}
