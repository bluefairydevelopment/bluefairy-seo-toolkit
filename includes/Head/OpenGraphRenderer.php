<?php
/**
 * Outputs Open Graph, Twitter Cards, and Pinterest Rich Pin meta tags.
 *
 * @package BlueFairy\SeoToolkit\Head
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Head;

use BlueFairy\SeoToolkit\PageContext;

/**
 * Renders the base Open Graph tags: og:title, og:description, og:url, og:type
 * and og:site_name.
 *
 * Everything richer belongs to the Pro add-on and arrives through the
 * bfst_og_tags filter: og:image with its fallback chain, Twitter Card tags,
 * article:published_time / article:modified_time, and the WooCommerce
 * product:price:* tags for Pinterest Rich Pins.
 *
 * All output values are escaped with esc_attr().
 */
class OpenGraphRenderer implements RendererInterface {

    /**
     * {@inheritdoc}
     */
    public function feature_key(): string {
        return 'og';
    }

    /**
     * {@inheritdoc}
     */
    /**
     * Is this a page type where Open Graph tags would be wrong?
     *
     * @param PageContext $ctx
     * @return bool
     */
    protected static function is_suppressed_context( PageContext $ctx ): bool {
        if ( in_array( $ctx->type, [ '404', 'search' ], true ) ) {
            return true;
        }
        return is_404() || is_search() || is_feed();
    }

    public function render( PageContext $ctx ): string {
        // 404s, search results and feeds are not documents, and describing them
        // with og:title and og:site_name tells a crawler that an error page is
        // a shareable thing on this site.
        //
        // The readme and the features guide have both promised this suppression
        // since 1.0.0 and the renderer never implemented it: PageContext worked
        // out that the page was a 404 and nothing ever asked. Every 404 on every
        // install carried three Open Graph tags.
        //
        // Guarded in render(), not build_tags(), so the Pro subclass inherits it
        // — Pro overrides build_tags() and would otherwise re-open the hole.
        if ( self::is_suppressed_context( $ctx ) ) {
            return '';
        }

        $tags = $this->build_tags( $ctx );

        $output = implode( "\n", array_filter( $tags ) );
        return $output !== '' ? $output . "\n" : '';
    }

    /**
     * Build the Open Graph meta tags for this page.
     *
     * Subclasses override this, call parent::build_tags( $ctx ), and append —
     * which is how the Pro add-on adds og:image, Twitter Cards, article
     * timestamps and the WooCommerce price tags without restating any tag the
     * base plugin already renders.
     *
     * @param PageContext $ctx
     * @return string[] Rendered <meta> elements, each already escaped.
     */
    protected function build_tags( PageContext $ctx ): array {
        $tags = [];

        // -- og:title --
        $tags[] = $this->meta_tag( 'og:title', $this->get_title( $ctx ) );

        // -- og:description --
        $tags[] = $this->meta_tag( 'og:description', $this->get_description( $ctx ) );

        // -- og:url --
        if ( $ctx->canonical !== '' ) {
            $tags[] = $this->meta_tag( 'og:url', $ctx->canonical );
        }

        // -- og:type --
        $tags[] = $this->meta_tag( 'og:type', $this->get_og_type( $ctx ) );

        // -- og:site_name --
        $site_name = get_bloginfo( 'name' );
        if ( is_string( $site_name ) && $site_name !== '' ) {
            $tags[] = $this->meta_tag( 'og:site_name', $site_name );
        }

        return $tags;
    }

    /**
     * Build a <meta property="..." content="..."> tag.
     *
     * @param string $property
     * @param string $content
     * @return string Empty string if content is empty.
     */
    protected function meta_tag( string $property, string $content ): string {
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
     * Determine the page title for og:title.
     *
     * @param PageContext $ctx
     * @return string
     */
    protected function get_title( PageContext $ctx ): string {
        if ( $ctx->post instanceof \WP_Post ) {
            return get_the_title( $ctx->post );
        }
        if ( $ctx->term instanceof \WP_Term ) {
            return $ctx->term->name;
        }
        if ( $ctx->author instanceof \WP_User ) {
            return $ctx->author->display_name;
        }
        return get_bloginfo( 'name' );
    }

    /**
     * Determine the page description for og:description.
     *
     * @param PageContext $ctx
     * @return string
     */
    protected function get_description( PageContext $ctx ): string {
        if ( $ctx->post instanceof \WP_Post ) {
            if ( $ctx->post->post_excerpt !== '' ) {
                return wp_strip_all_tags( $ctx->post->post_excerpt );
            }
            // Auto-generate excerpt from content (strips shortcodes, HTML).
            return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $ctx->post->post_content ) ), 55 );
        }
        if ( $ctx->term instanceof \WP_Term && $ctx->term->description !== '' ) {
            return wp_strip_all_tags( $ctx->term->description );
        }
        return wp_strip_all_tags( get_bloginfo( 'description' ) );
    }

    /**
     * Determine og:type for this page.
     *
     * @param PageContext $ctx
     * @return string
     */
    protected function get_og_type( PageContext $ctx ): string {
        if ( $ctx->is_product ) {
            return 'product';
        }
        if ( $ctx->post instanceof \WP_Post && $ctx->post->post_type === 'post' ) {
            return 'article';
        }
        return 'website';
    }



}
