<?php
/**
 * SitemapManager, SitemapIndex and PostTypeSitemap.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Tests\Unit;

use BlueFairy\SeoToolkit\Sitemap\PostTypeSitemap;
use BlueFairy\SeoToolkit\Sitemap\SitemapIndex;
use BlueFairy\SeoToolkit\Sitemap\SitemapManager;
use BlueFairy\SeoToolkit\Tests\TestCase;

/** Exposes the protected steps of a post-type sitemap. */
final class PtsProbe extends PostTypeSitemap {
	public function xml( string $t, int $p ): string { return $this->build_xml( $t, $p ); }
	public function pages( string $t ): int { return $this->page_count( $t ); }
	public function empty_doc(): string { return $this->empty_xml(); }
	public function attrs(): string { return $this->urlset_attributes(); }
	public function extras( int $id ): array { return $this->extra_url_lines( $id ); }
	public function prepare( array $ids ): void { $this->prepare_entries( $ids ); }
	public static function freq( \WP_Post $p ): string { return self::changefreq_for( $p ); }
	public static function visibility( string $t ): array { return self::visibility_tax_query( $t ); }
}

/** Exposes the protected steps of the index. */
final class IndexProbe extends SitemapIndex {
	public function xml(): string { return $this->build_xml(); }
	public function list_entries(): array { return $this->entries(); }
	public function excluded(): array { return $this->exclusions(); }
	public function count( string $t ): int { return $this->count_posts( $t ); }
	public function lastmod( string $t ): string { return $this->get_post_type_lastmod( $t ); }
	public function to_xml( array $e ): string { return $this->entries_to_xml( $e ); }
}

final class SitemapTest extends TestCase {

	private function posts( array ...$rows ): void {
		$posts = [];
		foreach ( $rows as $r ) {
			$url = $r['url'] ?? 'https://example.test/?p=' . $r['ID'];
			unset( $r['url'] );
			$p = bfst_wp_post( $r );
			$GLOBALS['bfst_wp']['permalinks'][ $p->ID ] = $url;
			$posts[] = $p;
		}
		$GLOBALS['bfst_wp']['wp_query'] = [ 'posts' => $posts, 'found_posts' => count( $posts ) ];
	}

	public function test_post_types_default_to_posts_and_pages_and_are_filterable(): void {
		$this->assertSame( [ 'post', 'page' ], SitemapManager::post_types() );
		add_filter( 'bfst_sitemap_post_types', static fn() => [ 'post', '', 7 ] );
		$this->assertSame( [ 'post', '7' ], SitemapManager::post_types() );
		bfst_wp_reset();
		add_filter( 'bfst_sitemap_post_types', static fn() => 'junk' );
		$this->assertSame( [ 'post', 'page' ], SitemapManager::post_types() );
	}

	public function test_post_status_is_inherit_only_for_attachments(): void {
		$this->assertSame( 'inherit', SitemapManager::post_status_for( 'attachment' ) );
		$this->assertSame( 'publish', SitemapManager::post_status_for( 'post' ) );
	}

	public function test_exclusion_meta_query_is_filterable_but_never_emptied(): void {
		$base = [ [ 'key' => '_bfst_exclude_sitemap', 'compare' => 'NOT EXISTS' ] ];
		$this->assertSame( $base, SitemapManager::exclusion_meta_query( 'post' ) );
		add_filter( 'bfst_sitemap_exclusion_meta_query', static fn() => [], 10, 2 );
		$this->assertSame( $base, SitemapManager::exclusion_meta_query( 'post' ) );
		bfst_wp_reset();
		add_filter( 'bfst_sitemap_exclusion_meta_query', static fn( $q, $t ) => [ [ 'key' => $t ] ], 10, 2 );
		$this->assertSame( [ [ 'key' => 'page' ] ], SitemapManager::exclusion_meta_query( 'page' ) );
	}

	public function test_register_hooks_rules_routing_vars_invalidation_and_redirect_guard(): void {
		SitemapManager::register();
		$this->assertSame( 10, has_action( 'init', [ SitemapManager::class, 'register_rules' ] ) );
		$this->assertSame( 10, has_action( 'template_redirect', [ SitemapManager::class, 'route_sitemap' ] ) );
		$this->assertSame( 10, has_filter( 'query_vars', [ SitemapManager::class, 'add_query_vars' ] ) );
		$this->assertSame( 10, has_filter( 'redirect_canonical', [ SitemapManager::class, 'prevent_sitemap_redirect' ] ) );
		$this->assertSame( 10, has_action( 'save_post', [ PostTypeSitemap::class, 'invalidate_on_save' ] ) );
		$this->assertSame( 10, has_action( 'wp_trash_post', [ PostTypeSitemap::class, 'invalidate_on_save' ] ) );
		$this->assertSame( 10, has_action( 'before_delete_post', [ PostTypeSitemap::class, 'invalidate_on_save' ] ) );
	}

	public function test_rewrite_rules_map_the_index_and_paged_type_sitemaps(): void {
		SitemapManager::register_rules();
		$this->assertSame(
			[
				'^sitemap_index\.xml$'                     => [ 'index.php?bfst_sitemap=index', 'top' ],
				'^sitemap-([a-z0-9_-]+)-([0-9]+)\.xml$' => [ 'index.php?bfst_sitemap=$matches[1]&bfst_sitemap_page=$matches[2]', 'top' ],
			],
			$GLOBALS['bfst_wp']['rewrite_rules']
		);
	}

	public function test_query_vars_and_redirect_guard(): void {
		$this->assertSame( [ 'p', 'bfst_sitemap', 'bfst_sitemap_page' ], SitemapManager::add_query_vars( [ 'p' ] ) );
		$this->assertSame( 'https://example.test/x/', SitemapManager::prevent_sitemap_redirect( 'https://example.test/x/', '/x' ) );
		$GLOBALS['bfst_wp']['query_vars']['bfst_sitemap'] = 'index';
		$this->assertFalse( SitemapManager::prevent_sitemap_redirect( 'https://example.test/sitemap_index.xml/', '/sitemap_index.xml' ) );
	}

	public function test_cache_keys_are_remembered_once_and_flushed(): void {
		SitemapManager::remember_cache_key( 'bfst_sitemap_a' );
		SitemapManager::remember_cache_key( 'bfst_sitemap_a' );
		update_option( 'bfst_sitemap_cache_keys', 'corrupt' );
		SitemapManager::remember_cache_key( 'bfst_sitemap_b' );
		$this->assertSame( [ 'bfst_sitemap_b' ], get_option( 'bfst_sitemap_cache_keys' ) );
		SitemapManager::remember_cache_key( 'bfst_sitemap_c' );
		$this->assertSame( [ 'bfst_sitemap_b', 'bfst_sitemap_c' ], get_option( 'bfst_sitemap_cache_keys' ) );

		foreach ( [ 'bfst_sitemap_b', 'bfst_sitemap_c', 'bfst_sitemap_old', 'bfst_sitemap_index', 'keep' ] as $k ) {
			set_transient( $k, 'x' );
		}
		$GLOBALS['bfst_wp']['db_col'] = [ '_transient_bfst_sitemap_old' ];
		SitemapManager::flush_caches();
		$this->assertSame( [ 'keep' ], array_keys( $GLOBALS['bfst_wp']['transients'] ) );
		$this->assertFalse( get_option( 'bfst_sitemap_cache_keys' ) );
		$this->assertSame( [ "SELECT option_name FROM wp_options WHERE option_name LIKE '_transient_bfst_sitemap_%'" ], $GLOBALS['bfst_wp']['db_sql'] );
	}

	public function test_flush_skips_the_database_sweep_with_an_object_cache(): void {
		$GLOBALS['bfst_wp']['ext_cache'] = true;
		SitemapManager::flush_caches();
		$this->assertSame( [], $GLOBALS['bfst_wp']['db_sql'] );
	}

	public function test_post_type_sitemap_lists_each_post_with_escaped_loc_and_dates(): void {
		$this->posts(
			[ 'ID' => 1, 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() - 86400 ), 'url' => 'https://example.test/a?x=1&y=2' ],
			[ 'ID' => 2, 'post_modified_gmt' => '2020-05-06 07:08:09', 'url' => '' ]
		);
		$day = gmdate( 'Y-m-d', time() - 86400 );
		$this->assertSame(
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n  <url>\n    <loc>https://example.test/a?x=1&amp;y=2</loc>\n    <lastmod>{$day}</lastmod>\n    <changefreq>daily</changefreq>\n  </url>\n</urlset>",
			( new PtsProbe() )->xml( 'post', 1 )
		);
		$q = end( $GLOBALS['bfst_wp']['queries'] );
		$this->assertSame( [ 'post', 'publish', 1000, 1, false ], [ $q['post_type'], $q['post_status'], $q['posts_per_page'], $q['paged'], $q['has_password'] ] );
		$this->assertArrayNotHasKey( 'tax_query', $q );
	}

	public function test_changefreq_follows_age(): void {
		$p = static fn( int $ago ) => new \WP_Post( (object) [ 'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', time() - $ago * 86400 ) ] );
		$this->assertSame( 'daily', PtsProbe::freq( $p( 1 ) ) );
		$this->assertSame( 'weekly', PtsProbe::freq( $p( 30 ) ) );
		$this->assertSame( 'monthly', PtsProbe::freq( $p( 200 ) ) );
		$this->assertSame( 'monthly', PtsProbe::freq( new \WP_Post( (object) [ 'post_modified_gmt' => 'garbage' ] ) ) );
	}

	public function test_hidden_products_are_excluded_only_when_woocommerce_visibility_exists(): void {
		$this->assertSame( [], PtsProbe::visibility( 'product' ) );
		$GLOBALS['bfst_wp']['taxonomy_exists']['product_visibility'] = true;
		$this->assertSame( [], PtsProbe::visibility( 'post' ) );
		$tq = [ [ 'taxonomy' => 'product_visibility', 'field' => 'slug', 'terms' => [ 'exclude-from-catalog', 'exclude-from-search' ], 'operator' => 'NOT IN' ] ];
		$this->assertSame( $tq, PtsProbe::visibility( 'product' ) );
		$this->posts( [ 'ID' => 3 ] );
		( new PtsProbe() )->xml( 'product', 1 );
		$this->assertSame( $tq, end( $GLOBALS['bfst_wp']['queries'] )['tax_query'] );
		( new PtsProbe() )->pages( 'product' );
		$this->assertSame( $tq, end( $GLOBALS['bfst_wp']['queries'] )['tax_query'], 'page count uses the same scope' );
	}

	public function test_page_count_rounds_up_by_1000_with_a_floor_of_one(): void {
		$GLOBALS['bfst_wp']['wp_query']['found_posts'] = 0;
		$this->assertSame( 1, ( new PtsProbe() )->pages( 'post' ) );
		$GLOBALS['bfst_wp']['wp_query']['found_posts'] = 2001;
		$this->assertSame( 3, ( new PtsProbe() )->pages( 'post' ) );
	}

	public function test_render_caches_a_real_page_and_refuses_pages_past_the_end(): void {
		$this->posts( [ 'ID' => 1, 'url' => 'https://example.test/one/' ] );
		$s   = new PostTypeSitemap();
		$xml = $s->render( 'post', 1 );
		$this->assertStringContainsString( '<loc>https://example.test/one/</loc>', $xml );
		$key = 'bfst_sitemap_' . md5( 'post_1' );
		$this->assertSame( $xml, $GLOBALS['bfst_wp']['transients'][ $key ] );
		$this->assertSame( 86400, $GLOBALS['bfst_wp']['transient_ttl'][ $key ] );
		$this->assertSame( [ $key ], get_option( 'bfst_sitemap_cache_keys' ) );

		$this->assertSame( '', $s->render( 'post', 900001 ) );
		$this->assertArrayNotHasKey( 'bfst_sitemap_' . md5( 'post_900001' ), $GLOBALS['bfst_wp']['transients'] );

		set_transient( $key, 'CACHED' );
		$this->assertSame( 'CACHED', $s->render( 'post', 1 ) );
	}

	public function test_an_empty_page_is_neither_served_nor_cached(): void {
		$GLOBALS['bfst_wp']['wp_query'] = [ 'posts' => [], 'found_posts' => 0 ];
		$this->assertSame( '', ( new PostTypeSitemap() )->render( 'post', 1 ) );
		$this->assertSame( [], $GLOBALS['bfst_wp']['transients'] );
		$this->assertSame( "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\"></urlset>", ( new PtsProbe() )->xml( 'post', 1 ) );
		$this->assertSame( ( new PtsProbe() )->empty_doc(), ( new PtsProbe() )->xml( 'post', 1 ) );
	}

	public function test_extension_points_default_to_nothing(): void {
		$p = new PtsProbe();
		$this->assertSame( 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', $p->attrs() );
		$this->assertSame( [], $p->extras( 1 ) );
		$p->prepare( [ 1, 2 ] );
		$this->addToAssertionCount( 1 );
	}

	public function test_saving_a_post_drops_ten_pages_of_its_type_and_the_index(): void {
		bfst_wp_post( [ 'ID' => 4, 'post_type' => 'page' ] );
		for ( $i = 1; $i <= 11; $i++ ) {
			set_transient( 'bfst_sitemap_' . md5( 'page_' . $i ), 'x' );
		}
		set_transient( 'bfst_sitemap_' . md5( 'post_1' ), 'x' );
		set_transient( 'bfst_sitemap_index', 'x' );
		PostTypeSitemap::invalidate_on_save( 999 );
		$this->assertCount( 13, $GLOBALS['bfst_wp']['transients'], 'unknown post: nothing dropped' );
		PostTypeSitemap::invalidate_on_save( 4 );
		$this->assertSame( [ 'bfst_sitemap_' . md5( 'page_11' ), 'bfst_sitemap_' . md5( 'post_1' ) ], array_keys( $GLOBALS['bfst_wp']['transients'] ) );
	}

	public function test_register_invalidation_hooks(): void {
		PostTypeSitemap::register_invalidation_hooks();
		$this->assertSame( [ [ PostTypeSitemap::class, 'invalidate_on_save' ] ], self::hooked( 'save_post' ) );
	}

	public function test_index_lists_a_page_per_1000_posts_skipping_excluded_types(): void {
		update_option( 'bfst_sitemap_exclusions', [ 'page' => true ] );
		$GLOBALS['bfst_wp']['wp_query']['found_posts'] = 1500;
		$GLOBALS['bfst_wp']['get_posts']               = [ new \WP_Post( (object) [ 'post_modified_gmt' => '2026-02-03 04:05:06' ] ) ];
		$this->assertSame(
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" .
			"  <sitemap>\n    <loc>https://example.test/sitemap-post-1.xml</loc>\n    <lastmod>2026-02-03</lastmod>\n  </sitemap>\n" .
			"  <sitemap>\n    <loc>https://example.test/sitemap-post-2.xml</loc>\n    <lastmod>2026-02-03</lastmod>\n  </sitemap>\n</sitemapindex>",
			( new IndexProbe() )->xml()
		);
		$this->assertCount( 2, ( new IndexProbe() )->list_entries() );
	}

	/**
	 * WPMAP-235: a post type with no published posts must not be advertised.
	 * The router answers /sitemap-page-1.xml with a 404 when there are no
	 * pages (render() returns ''), so the index has to skip it to agree.
	 */
	public function test_index_skips_a_post_type_with_no_published_posts(): void {
		$GLOBALS['bfst_wp']['wp_query']['found_posts_by_type'] = [ 'post' => 3, 'page' => 0 ];
		$GLOBALS['bfst_wp']['get_posts']                       = [ new \WP_Post( (object) [ 'post_modified_gmt' => '2026-02-03 04:05:06' ] ) ];
		$this->assertSame(
			[ [ 'loc' => 'https://example.test/sitemap-post-1.xml', 'lastmod' => '2026-02-03' ] ],
			( new IndexProbe() )->list_entries()
		);

		// The router agrees: the page sitemap it would have advertised is a 404.
		$GLOBALS['bfst_wp']['wp_query']['posts'] = [];
		$this->assertSame( '', ( new PostTypeSitemap() )->render( 'page', 1 ) );

		$GLOBALS['bfst_wp']['wp_query']['found_posts_by_type'] = [ 'post' => 0, 'page' => 0 ];
		$this->assertSame(
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n</sitemapindex>",
			( new IndexProbe() )->xml()
		);
	}

	public function test_index_render_caches_for_an_hour_and_invalidate_clears_it(): void {
		$xml = ( new SitemapIndex() )->render();
		$this->assertSame( $xml, get_transient( 'bfst_sitemap_index' ) );
		$this->assertSame( 3600, $GLOBALS['bfst_wp']['transient_ttl']['bfst_sitemap_index'] );
		set_transient( 'bfst_sitemap_index', 'CACHED' );
		$this->assertSame( 'CACHED', ( new SitemapIndex() )->render() );
		SitemapIndex::invalidate();
		$this->assertFalse( get_transient( 'bfst_sitemap_index' ) );
	}

	public function test_index_helpers(): void {
		$i = new IndexProbe();
		$this->assertSame( [], $i->excluded() );
		update_option( 'bfst_sitemap_exclusions', 'corrupt' );
		$this->assertSame( [], $i->excluded() );
		$this->assertSame( gmdate( 'Y-m-d' ), $i->lastmod( 'post' ), 'no posts: today' );
		$this->assertSame( [ 'post', 'publish', 1, 'modified', 'DESC' ], array_values( array_intersect_key( $GLOBALS['bfst_wp']['get_posts_args'][0], array_flip( [ 'post_type', 'post_status', 'numberposts', 'orderby', 'order' ] ) ) ) );
		$GLOBALS['bfst_wp']['wp_query']['found_posts'] = 42;
		$this->assertSame( 42, $i->count( 'post' ) );
		$this->assertCount( 1, end( $GLOBALS['bfst_wp']['queries'] )['meta_query'] );
		$this->assertSame( "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n  <sitemap>\n    <loc>https://example.test/a</loc>\n  </sitemap>\n</sitemapindex>", $i->to_xml( [ [ 'loc' => 'https://example.test/a', 'lastmod' => '' ] ] ) );
	}

	public function test_index_counts_exclude_hidden_products_when_woocommerce_runs(): void {
		[ $out, $status ] = self::run_isolated(
			<<<'PHP'
			function wc_get_product() {}
			$GLOBALS['bfst_wp']['wp_query']['found_posts'] = 3;
			$r = new ReflectionMethod( BlueFairy\SeoToolkit\Sitemap\SitemapIndex::class, 'count_posts' );
			$n = $r->invoke( new BlueFairy\SeoToolkit\Sitemap\SitemapIndex(), 'product' );
			echo $n, ' ', json_encode( end( $GLOBALS['bfst_wp']['queries'] )['meta_query'][1] );
			PHP
		);
		$this->assertSame( 0, $status, $out );
		$this->assertSame( '3 {"relation":"OR","0":{"key":"_visibility","value":"hidden","compare":"!="},"1":{"key":"_visibility","compare":"NOT EXISTS"}}', $out );
	}

	public function test_set_handler_swaps_the_class_used_for_a_sitemap_kind(): void {
		SitemapManager::set_handler( 'index', IndexProbe::class );
		$this->assertSame( IndexProbe::class, self::get_static( SitemapManager::class, 'handlers' )['index'] );
	}

	public function test_route_does_nothing_off_sitemap_and_404s_when_disabled_or_unknown(): void {
		SitemapManager::route_sitemap();
		$this->assertNull( $GLOBALS['bfst_wp']['status'] );

		$GLOBALS['bfst_wp']['query_vars']['bfst_sitemap'] = 'index';
		update_option( 'bfst_features', [ 'sitemap' => false ] );
		SitemapManager::route_sitemap();
		$this->assertSame( [ 404, true ], [ $GLOBALS['bfst_wp']['status'], $GLOBALS['bfst_wp']['is_404_set'] ] );

		bfst_wp_reset();
		$GLOBALS['bfst_wp']['query_vars']['bfst_sitemap'] = 'nope';
		SitemapManager::route_sitemap();
		$this->assertSame( 404, $GLOBALS['bfst_wp']['status'] );

		bfst_wp_reset();
		$GLOBALS['bfst_wp']['query_vars'] = [ 'bfst_sitemap' => 'post', 'bfst_sitemap_page' => 5 ];
		SitemapManager::route_sitemap();
		$this->assertSame( 404, $GLOBALS['bfst_wp']['status'], 'past the end is a 404, not an empty 200' );
	}

	public function test_route_serves_the_index_a_post_page_and_filtered_kinds_then_exits(): void {
		$run = static fn( string $setup ) => self::run_isolated( $setup . "\nBlueFairy\\SeoToolkit\\Sitemap\\SitemapManager::route_sitemap();\necho 'NOT EXITED';" );

		[ $out, $status ] = $run( "\$GLOBALS['bfst_wp']['wp_query']['found_posts'] = 1; \$GLOBALS['bfst_wp']['query_vars']['bfst_sitemap'] = 'index';" );
		$this->assertSame( 0, $status, $out );
		$this->assertSame( "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n  <sitemap>\n    <loc>https://example.test/sitemap-post-1.xml</loc>\n    <lastmod>" . gmdate( 'Y-m-d' ) . "</lastmod>\n  </sitemap>\n  <sitemap>\n    <loc>https://example.test/sitemap-page-1.xml</loc>\n    <lastmod>" . gmdate( 'Y-m-d' ) . "</lastmod>\n  </sitemap>\n</sitemapindex>", $out );

		[ $out ] = $run( "\$p = bfst_wp_post( [ 'ID' => 1 ] ); \$GLOBALS['bfst_wp']['permalinks'][1] = 'https://example.test/one/'; \$GLOBALS['bfst_wp']['wp_query'] = [ 'posts' => [ \$p ], 'found_posts' => 1 ]; \$GLOBALS['bfst_wp']['query_vars'] = [ 'bfst_sitemap' => 'post', 'bfst_sitemap_page' => '1' ];" );
		$this->assertStringContainsString( '<loc>https://example.test/one/</loc>', $out );
		$this->assertStringNotContainsString( 'NOT EXITED', $out );

		[ $out ] = $run( "\$GLOBALS['bfst_wp']['query_vars']['bfst_sitemap'] = 'category'; add_filter( 'bfst_render_sitemap', static fn( \$o, \$s, \$p ) => \"<x s=\\\"\$s\\\" p=\\\"\$p\\\"/>\", 10, 3 );" );
		$this->assertSame( '<x s="category" p="1"/>', $out );
	}

	public function test_the_cache_key_registry_is_capped_and_an_evicted_key_has_its_transient_deleted(): void {
		$old = [];
		for ( $i = 0; $i < SitemapManager::CACHE_INDEX_MAX; $i++ ) {
			$old[] = 'bfst_sitemap_old' . $i;
			set_transient( 'bfst_sitemap_old' . $i, 'x', DAY_IN_SECONDS );
		}
		update_option( 'bfst_sitemap_cache_keys', $old );

		SitemapManager::remember_cache_key( 'bfst_sitemap_new' );

		$keys = get_option( 'bfst_sitemap_cache_keys' );
		$this->assertCount( SitemapManager::CACHE_INDEX_MAX, $keys );
		$this->assertSame( 'bfst_sitemap_old1', $keys[0], 'the oldest key is dropped' );
		$this->assertSame( 'bfst_sitemap_new', end( $keys ) );
		$this->assertFalse( get_transient( 'bfst_sitemap_old0' ), 'an evicted key\'s transient is deleted, so nothing is left untracked' );
		$this->assertSame( 'x', get_transient( 'bfst_sitemap_old1' ) );
	}
}
