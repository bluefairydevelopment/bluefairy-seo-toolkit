<?php
/**
 * The home page's own sitemap, /sitemap-frontpage-1.xml.
 *
 * @package BlueFairy\SeoToolkit\Sitemap
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Sitemap;

/**
 * Lists the home page when the front page shows the latest posts.
 *
 * That page is no post, so no post-type sitemap carries it. It has its own
 * sitemap, always first in the index, so it is listed even on a site with no
 * published posts or with posts excluded from the sitemap (WPMAP-237). With a
 * static front page nothing is listed: that page is in the page sitemap.
 *
 * Not cached: it is one indexed query, and whether it applies follows
 * Settings > Reading on the very next request.
 *
 * @since 1.1.4
 */
class HomeSitemap {

	/**
	 * The sitemap name in /sitemap-{name}-1.xml.
	 */
	public const NAME = 'frontpage';

	/**
	 * Whether the front page shows the latest posts.
	 *
	 * A static front page needs both settings: with "a static page" chosen but
	 * none selected, WordPress still shows the latest posts.
	 */
	public static function applies(): bool {
		return ! ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) > 0 );
	}

	/**
	 * The sitemap's URL.
	 */
	public static function url(): string {
		return home_url( '/sitemap-' . self::NAME . '-1.xml' );
	}

	/**
	 * The home page's last change: the newest published post's, or '' when
	 * there is none (no <lastmod> is better than an invented one).
	 */
	public static function lastmod(): string {
		$posts = get_posts(
			[
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'numberposts'            => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			]
		);

		if ( empty( $posts ) || ! $posts[0] instanceof \WP_Post ) {
			return '';
		}

		return SitemapManager::lastmod( $posts[0]->post_modified_gmt );
	}

	/**
	 * The sitemap XML, or '' (a 404) past page 1 or with a static front page.
	 *
	 * @param int $page 1-based sitemap page number.
	 * @return string
	 */
	public function render( int $page ): string {
		if ( 1 !== $page || ! self::applies() ) {
			return '';
		}

		$lastmod = self::lastmod();

		$lines   = [];
		$lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
		$lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
		$lines[] = '  <url>';
		$lines[] = '    <loc>' . esc_url( home_url( '/' ) ) . '</loc>';
		if ( '' !== $lastmod ) {
			$lines[] = '    <lastmod>' . esc_html( $lastmod ) . '</lastmod>';
		}
		$lines[] = '    <changefreq>daily</changefreq>';
		$lines[] = '  </url>';
		$lines[] = '</urlset>';

		return implode( "\n", $lines );
	}
}
