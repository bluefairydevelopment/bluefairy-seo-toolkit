<?php
/**
 * The home page's own sitemap (WPMAP-237): a front page showing the latest
 * posts is listed whether or not there are posts, and whether or not posts
 * are excluded from the sitemap. A <lastmod> is never invented.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Tests\Unit;

use BlueFairy\SeoToolkit\Sitemap\HomeSitemap;
use BlueFairy\SeoToolkit\Sitemap\PostTypeSitemap;
use BlueFairy\SeoToolkit\Sitemap\SitemapIndex;
use BlueFairy\SeoToolkit\Sitemap\SitemapManager;
use BlueFairy\SeoToolkit\Tests\TestCase;

/** Exposes the index's entries. */
final class HomeIndexProbe extends SitemapIndex {
	public function list_entries(): array { return $this->entries(); }
	public function lastmod( string $t ): string { return $this->get_post_type_lastmod( $t ); }
}

/** Exposes a post-type page's XML. */
final class HomePtsProbe extends PostTypeSitemap {
	public function xml( string $t, int $p ): string { return $this->build_xml( $t, $p ); }
}

final class HomeSitemapTest extends TestCase {

	private const HOME_XML = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n  <url>\n    <loc>https://example.test/</loc>\n%s    <changefreq>daily</changefreq>\n  </url>\n</urlset>";

	public function test_applies_only_when_the_front_page_shows_the_latest_posts(): void {
		$this->assertTrue( HomeSitemap::applies(), 'no row: WordPress shows the latest posts' );
		update_option( 'show_on_front', 'posts' );
		$this->assertTrue( HomeSitemap::applies() );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 9 );
		$this->assertFalse( HomeSitemap::applies(), 'static front page: it is in the page sitemap' );
		update_option( 'page_on_front', 0 );
		$this->assertTrue( HomeSitemap::applies(), '"a static page" with none selected still shows the posts' );
	}

	public function test_the_home_sitemap_lists_the_home_url_with_the_newest_post_date(): void {
		$GLOBALS['bfst_wp']['get_posts'] = [ new \WP_Post( (object) [ 'post_modified_gmt' => '2026-03-04 05:06:07' ] ) ];
		$this->assertSame( sprintf( self::HOME_XML, "    <lastmod>2026-03-04</lastmod>\n" ), ( new HomeSitemap() )->render( 1 ) );
		$args = $GLOBALS['bfst_wp']['get_posts_args'][0];
		$this->assertSame( [ 'post', 'publish', 1, 'modified', 'DESC' ], [ $args['post_type'], $args['post_status'], $args['numberposts'], $args['orderby'], $args['order'] ] );
	}

	public function test_no_posts_still_lists_the_home_url_without_a_lastmod(): void {
		$GLOBALS['bfst_wp']['get_posts'] = [];
		$this->assertSame( sprintf( self::HOME_XML, '' ), ( new HomeSitemap() )->render( 1 ) );
		$this->assertStringNotContainsString( '1970', ( new HomeSitemap() )->render( 1 ) );
	}

	public function test_a_zero_modified_date_never_becomes_1970(): void {
		$GLOBALS['bfst_wp']['get_posts'] = [ new \WP_Post( (object) [ 'post_modified_gmt' => '0000-00-00 00:00:00' ] ) ];
		$this->assertSame( sprintf( self::HOME_XML, '' ), ( new HomeSitemap() )->render( 1 ) );
		$this->assertSame( '', SitemapManager::lastmod( '0000-00-00 00:00:00' ) );
		$this->assertSame( '', SitemapManager::lastmod( '1970-01-01 00:00:00' ) );
		$this->assertSame( '', SitemapManager::lastmod( 'garbage' ) );
		$this->assertSame( '', SitemapManager::lastmod( '' ) );
		$this->assertSame( '2026-03-04', SitemapManager::lastmod( '2026-03-04 05:06:07' ) );
	}

	public function test_a_post_with_a_zero_modified_date_gets_no_lastmod_in_its_sitemap(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 9 );
		$p = bfst_wp_post( [ 'ID' => 5, 'post_modified_gmt' => '0000-00-00 00:00:00' ] );
		$GLOBALS['bfst_wp']['permalinks'][5] = 'https://example.test/five/';
		$GLOBALS['bfst_wp']['wp_query']      = [ 'posts' => [ $p ], 'found_posts' => 1 ];
		$xml = ( new HomePtsProbe() )->xml( 'post', 1 );
		$this->assertStringContainsString( '<loc>https://example.test/five/</loc>', $xml );
		$this->assertStringNotContainsString( '<lastmod>', $xml );
	}

	public function test_the_index_lastmod_is_never_1970(): void {
		$GLOBALS['bfst_wp']['get_posts'] = [ new \WP_Post( (object) [ 'post_modified_gmt' => '0000-00-00 00:00:00' ] ) ];
		$this->assertSame( '', ( new HomeIndexProbe() )->lastmod( 'post' ) );
	}

	public function test_the_home_sitemap_is_only_page_one_and_only_without_a_static_front(): void {
		$this->assertSame( '', ( new HomeSitemap() )->render( 2 ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 9 );
		$this->assertSame( '', ( new HomeSitemap() )->render( 1 ) );
	}

	public function test_the_index_advertises_the_home_sitemap_first_even_with_no_posts_or_posts_excluded(): void {
		$GLOBALS['bfst_wp']['wp_query']['found_posts_by_type'] = [ 'post' => 0, 'page' => 2 ];
		$GLOBALS['bfst_wp']['get_posts']                       = [];
		$entries = ( new HomeIndexProbe() )->list_entries();
		$this->assertSame( [ 'loc' => 'https://example.test/sitemap-frontpage-1.xml', 'lastmod' => '' ], $entries[0] );
		$this->assertSame( [ 'https://example.test/sitemap-frontpage-1.xml', 'https://example.test/sitemap-page-1.xml' ], array_column( $entries, 'loc' ) );

		update_option( 'bfst_sitemap_exclusions', [ 'post' => true ] );
		$GLOBALS['bfst_wp']['wp_query']['found_posts_by_type'] = [ 'post' => 5, 'page' => 2 ];
		$this->assertSame( 'https://example.test/sitemap-frontpage-1.xml', ( new HomeIndexProbe() )->list_entries()[0]['loc'], 'posts excluded: the home page stays' );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', 9 );
		$this->assertNotContains( 'https://example.test/sitemap-frontpage-1.xml', array_column( ( new HomeIndexProbe() )->list_entries(), 'loc' ) );
	}

	public function test_the_post_sitemap_no_longer_carries_the_home_url(): void {
		$p = bfst_wp_post( [ 'ID' => 1, 'post_modified_gmt' => '2026-01-01 00:00:00' ] );
		$GLOBALS['bfst_wp']['permalinks'][1] = 'https://example.test/one/';
		$GLOBALS['bfst_wp']['wp_query']      = [ 'posts' => [ $p ], 'found_posts' => 1 ];
		$this->assertStringNotContainsString( '<loc>https://example.test/</loc>', ( new HomePtsProbe() )->xml( 'post', 1 ) );
	}

	public function test_the_router_serves_the_home_sitemap_and_404s_it_with_a_static_front(): void {
		$run = static fn( string $setup ) => self::run_isolated( $setup . "\nBlueFairy\\SeoToolkit\\Sitemap\\SitemapManager::route_sitemap();\necho 'NOT EXITED ' . \$GLOBALS['bfst_wp']['status'];" );

		[ $out ] = $run( "\$GLOBALS['bfst_wp']['query_vars'] = [ 'bfst_sitemap' => 'frontpage', 'bfst_sitemap_page' => '1' ];" );
		$this->assertSame( sprintf( self::HOME_XML, '' ), $out );

		[ $out ] = $run( "update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', 9 ); \$GLOBALS['bfst_wp']['query_vars'] = [ 'bfst_sitemap' => 'frontpage', 'bfst_sitemap_page' => '1' ];" );
		$this->assertSame( 'NOT EXITED 404', $out );

		[ $out ] = $run( "\$GLOBALS['bfst_wp']['query_vars'] = [ 'bfst_sitemap' => 'frontpage', 'bfst_sitemap_page' => '2' ];" );
		$this->assertSame( 'NOT EXITED 404', $out );
	}

	public function test_changing_the_reading_settings_drops_the_cached_index(): void {
		set_transient( 'bfst_sitemap_index', 'x' );
		set_transient( 'bfst_sitemap_' . md5( 'post_1' ), 'x' );
		PostTypeSitemap::invalidate_home_entry();
		$this->assertSame( [ 'bfst_sitemap_' . md5( 'post_1' ) ], array_keys( $GLOBALS['bfst_wp']['transients'] ), 'only the index carries the home entry now' );
	}
}
