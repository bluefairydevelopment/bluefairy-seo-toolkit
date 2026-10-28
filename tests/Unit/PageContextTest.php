<?php
/**
 * PageContext: page type, objects, post meta and the canonical URL.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Tests\Unit;

use BlueFairy\SeoToolkit\PageContext;
use BlueFairy\SeoToolkit\Tests\TestCase;

final class PageContextTest extends TestCase {

	private function post( int $id, string $url, array $meta = [] ): void {
		$p = bfst_wp_post( [ 'ID' => $id ] );
		$GLOBALS['bfst_wp']['queried']              = $p;
		$GLOBALS['bfst_wp']['permalinks'][ $id ]    = $url;
		$GLOBALS['bfst_wp']['post_meta'][ $id ]     = $meta;
		bfst_wp_cond( 'is_singular' );
	}

	public function test_init_builds_the_shared_context_on_wp(): void {
		$this->assertNull( PageContext::current() );
		PageContext::init();
		bfst_wp_cond( 'is_home' );
		do_action( 'wp' );
		$this->assertSame( 'home', PageContext::current()->type );
		$this->assertSame( 'https://example.test/', PageContext::current()->canonical );
	}

	public function test_a_singular_page_keeps_only_bfst_meta_first_values(): void {
		$this->post( 5, 'https://example.test/hello/', [ '_bfst_title' => [ 'T', 'ignored' ], '_edit_lock' => [ 'x' ] ] );
		$ctx = PageContext::build();
		$this->assertSame( 'singular', $ctx->type );
		$this->assertSame( 5, $ctx->queried_id );
		$this->assertSame( [ '_bfst_title' => 'T' ], $ctx->post_meta );
		$this->assertFalse( $ctx->is_product );
		$this->assertFalse( $ctx->is_woocommerce );
	}

	public function test_a_safe_canonical_override_wins_and_an_unsafe_one_falls_back(): void {
		$this->post( 5, 'https://example.test/hello/', [ '_bfst_canonical' => [ 'https://other.test/x' ] ] );
		$this->assertSame( 'https://other.test/x', PageContext::build()->canonical );
		$this->post( 6, 'https://example.test/six/', [ '_bfst_canonical' => [ 'javascript:alert(1)' ] ] );
		$this->assertSame( 'https://example.test/six/', PageContext::build()->canonical );
	}

	public function test_tracking_and_shop_parameters_are_stripped_and_others_kept(): void {
		update_option( 'bfst_canonical_strip_params', [ 'ref' ] );
		$this->post( 5, 'https://example.test:8080/p/?utm_source=x&gclid=1&attribute_pa_size=m&ref=a&color=red&replytocom=3#top' );
		$this->assertSame( 'https://example.test:8080/p/?color=red#top', PageContext::build()->canonical );
	}

	public function test_a_url_with_only_stripped_params_loses_the_query(): void {
		$this->post( 5, 'https://example.test/p/?fbclid=abc' );
		$this->assertSame( 'https://example.test/p/', PageContext::build()->canonical );
	}

	public function test_paged_archives_point_at_page_one(): void {
		$GLOBALS['bfst_wp']['queried']        = new \WP_Term( (object) [ 'term_id' => 3 ] );
		$GLOBALS['bfst_wp']['term_links'][3] = 'https://example.test/category/news/page/4/';
		bfst_wp_cond( 'is_category' );
		bfst_wp_cond( 'is_paged' );
		$ctx = PageContext::build();
		$this->assertSame( 'https://example.test/category/news/', $ctx->canonical );
		$this->assertSame( 3, $ctx->queried_id );
	}

	public function test_a_term_without_a_link_falls_back_to_home(): void {
		$GLOBALS['bfst_wp']['queried'] = new \WP_Term( (object) [ 'term_id' => 9 ] );
		bfst_wp_cond( 'is_tax' );
		$this->assertSame( 'https://example.test/', PageContext::build()->canonical );
	}

	public function test_author_posts_page_and_post_type_archive_canonicals(): void {
		$GLOBALS['bfst_wp']['queried']        = new \WP_User( 4, 'Ada' );
		$GLOBALS['bfst_wp']['author_urls'][4] = 'https://example.test/author/ada/';
		bfst_wp_cond( 'is_author' );
		$ctx = PageContext::build();
		$this->assertSame( 'https://example.test/author/ada/', $ctx->canonical );
		$this->assertSame( 4, $ctx->queried_id );

		bfst_wp_reset();
		update_option( 'page_for_posts', 12 );
		$GLOBALS['bfst_wp']['permalinks'][12] = 'https://example.test/blog/';
		bfst_wp_cond( 'is_home' );
		$this->assertSame( 'https://example.test/blog/', PageContext::build()->canonical );

		bfst_wp_reset();
		bfst_wp_cond( 'is_post_type_archive' );
		$GLOBALS['bfst_wp']['query_vars']['post_type']      = [ 'book', 'film' ];
		$GLOBALS['bfst_wp']['archive_links']['book']        = 'https://example.test/books/';
		$this->assertSame( 'https://example.test/books/', PageContext::build()->canonical );

		bfst_wp_reset();
		bfst_wp_cond( 'is_post_type_archive' );
		$this->assertSame( 'https://example.test/', PageContext::build()->canonical );
	}

	public function test_date_archive_canonicals(): void {
		$GLOBALS['bfst_wp']['query_vars'] = [ 'year' => 2026, 'monthnum' => 3, 'day' => 9 ];
		bfst_wp_cond( 'is_date' );
		bfst_wp_cond( 'is_day' );
		$this->assertSame( 'https://example.test/2026/03/09/', PageContext::build()->canonical );
		bfst_wp_cond( 'is_day', false );
		bfst_wp_cond( 'is_month' );
		$this->assertSame( 'https://example.test/2026/03/', PageContext::build()->canonical );
		bfst_wp_cond( 'is_month', false );
		$this->assertSame( 'https://example.test/2026/', PageContext::build()->canonical );
	}

	public function test_search_and_404_have_no_canonical(): void {
		bfst_wp_cond( 'is_search' );
		$this->assertSame( [ 'search', '' ], [ PageContext::build()->type, PageContext::build()->canonical ] );
		bfst_wp_reset();
		bfst_wp_cond( 'is_404' );
		$this->assertSame( [ '404', '' ], [ PageContext::build()->type, PageContext::build()->canonical ] );
	}

	public function test_the_canonical_filter_can_replace_the_url_but_not_with_a_non_string(): void {
		bfst_wp_cond( 'is_home' );
		add_filter( 'bfst_canonical_url', static fn() => 'https://cdn.test/home' );
		$this->assertSame( 'https://cdn.test/home', PageContext::build()->canonical );
		bfst_wp_reset();
		bfst_wp_cond( 'is_home' );
		add_filter( 'bfst_canonical_url', static fn() => 42 );
		$this->assertSame( 'https://example.test/', PageContext::build()->canonical );
	}

	public function test_a_woocommerce_product_is_flagged(): void {
		[ $out, $status ] = self::run_isolated(
			<<<'PHP'
			function WC() {}
			$p = bfst_wp_post( [ 'ID' => 3, 'post_type' => 'product' ] );
			$GLOBALS['bfst_wp']['queried'] = $p;
			bfst_wp_cond( 'is_singular' );
			bfst_wp_cond( 'is_singular_product' );
			$c = BlueFairy\SeoToolkit\PageContext::build();
			echo json_encode( [ $c->is_woocommerce, $c->is_product, ( new BlueFairy\SeoToolkit\Head\OpenGraphRenderer() )->render( $c ) ] );
			PHP
		);
		$this->assertSame( 0, $status, $out );
		$this->assertSame( '[true,true,"<meta property=\"og:type\" content=\"product\">\n<meta property=\"og:site_name\" content=\"Fixture Site\">\n"]', $out );
	}
}
