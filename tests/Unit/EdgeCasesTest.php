<?php
/**
 * The branches the main suites left unrun: Pro present, a front end without
 * wp-admin's plugin functions, an old PHP, a WooCommerce product, an empty
 * query string, non-post rows and extra lines in a sitemap, sitemap routing
 * as data, and uninstall.php in a separate process (so it is measured).
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Tests\Unit;

use BlueFairy\SeoToolkit\Admin\ProUpsell;
use BlueFairy\SeoToolkit\Admin\SettingsPage;
use BlueFairy\SeoToolkit\ConflictDetector;
use BlueFairy\SeoToolkit\Head\OpenGraphRenderer;
use BlueFairy\SeoToolkit\Installer;
use BlueFairy\SeoToolkit\PageContext;
use BlueFairy\SeoToolkit\Sitemap\PostTypeSitemap;
use BlueFairy\SeoToolkit\Sitemap\SitemapManager;
use BlueFairy\SeoToolkit\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/** A subclass that adds one line to every <url>, as Pro's image sitemap does. */
final class ExtraLinesProbe extends PostTypeSitemap {
	public function xml( string $t, int $p ): string { return $this->build_xml( $t, $p ); }
	protected function extra_url_lines( int $post_id ): array { return [ '    <x:extra id="' . $post_id . '"/>' ]; }
}

final class EdgeCasesTest extends TestCase {

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_with_pro_active_the_upsell_disappears(): void {
		define( 'BFSTP_VERSION', '1.3.1' );
		$this->assertSame( [ 'x' ], ProUpsell::add_action_link( [ 'x' ] ), 'no "Get SEO Toolkit Pro" link' );
		$this->assertSame( '', self::capture( [ ProUpsell::class, 'render_notice' ] ), 'no callout' );
	}

	public function test_a_corrupt_features_option_checks_no_override_in_the_conflict_notice(): void {
		$GLOBALS['bfst_wp']['active_plugins'] = [ 'wordpress-seo/wp-seo.php' ];
		update_option( 'bfst_features', 'corrupt' );
		$html = self::capture( [ SettingsPage::class, 'render_conflict_notice' ] );
		$this->assertStringContainsString( 'active: <strong>Yoast SEO</strong>', $html );
		$this->assertStringContainsString( 'data-feature="og"', $html );
		$this->assertStringNotContainsString( 'checked', $html, 'nothing overridden' );
	}

	/** The front end has no is_plugin_active(): the scan loads wp-admin/includes/plugin.php itself. */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_the_scan_loads_the_admin_plugin_functions_when_they_are_missing(): void {
		$this->assertFalse( function_exists( 'is_plugin_active' ), 'precondition: a front-end request' );
		$GLOBALS['bfst_wp']['active_plugins'] = [ 'seopress/seopress.php' ];
		ConflictDetector::scan();
		$this->assertTrue( function_exists( 'is_plugin_active' ) );
		$this->assertSame( [ 'seopress' ], ConflictDetector::detected() );
	}

	/** Activation on an old WordPress deactivates even where deactivate_plugins() is not yet loaded. */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_a_failed_activation_loads_deactivate_plugins_when_it_is_missing(): void {
		$this->assertFalse( function_exists( 'deactivate_plugins' ), 'precondition' );
		$GLOBALS['wp_version'] = '6.0';
		try {
			Installer::activate();
			$this->fail( 'wp_die was not called' );
		} catch ( \BFST_Wp_Die $e ) {
			$this->assertStringContainsString( 'requires WordPress 6.6 or higher', $e->getMessage() );
		}
		$this->assertSame( [ 'bluefairy-seo-toolkit/bluefairy-seo-toolkit.php' ], $GLOBALS['bfst_wp']['deactivated'] );
	}

	public function test_requirement_errors_name_each_unmet_version_and_nothing_when_met(): void {
		$this->assertSame( [], Installer::requirement_errors( '8.1.0', '6.6' ), 'the minimums themselves are met' );
		$this->assertSame(
			[
				'BlueFairy SEO Toolkit requires PHP 8.1 or higher. You are running PHP 8.0.30.',
				'BlueFairy SEO Toolkit requires WordPress 6.6 or higher. You are running WordPress 6.5.5.',
			],
			Installer::requirement_errors( '8.0.30', '6.5.5' )
		);
		$this->assertSame( [ 'BlueFairy SEO Toolkit requires PHP 8.1 or higher. You are running PHP 7.4.0.' ], Installer::requirement_errors( '7.4.0', '6.8' ) );
	}

	/** og:type is "product" only on a WooCommerce product; the same post without WooCommerce is a website. */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_a_woocommerce_product_is_og_type_product(): void {
		$p = bfst_wp_post( [ 'ID' => 3, 'post_type' => 'product' ] );
		$GLOBALS['bfst_wp']['queried']        = $p;
		$GLOBALS['bfst_wp']['permalinks'][3] = 'https://example.test/shop/3/';
		bfst_wp_cond( 'is_singular' );
		bfst_wp_cond( 'is_singular_product' );
		$this->assertStringContainsString( '<meta property="og:type" content="website">', ( new OpenGraphRenderer() )->render( PageContext::build() ), 'control: no WooCommerce' );

		eval( 'function WC() { return null; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- WooCommerce's global function, in the global namespace.
		$this->assertStringContainsString( '<meta property="og:type" content="product">', ( new OpenGraphRenderer() )->render( PageContext::build() ) );
	}

	public function test_a_query_string_with_no_parameters_is_left_as_it_is(): void {
		$p = bfst_wp_post( [ 'ID' => 5 ] );
		$GLOBALS['bfst_wp']['queried']        = $p;
		$GLOBALS['bfst_wp']['permalinks'][5] = 'https://example.test/p/?&';
		bfst_wp_cond( 'is_singular' );
		$this->assertSame( 'https://example.test/p/?&', PageContext::build()->canonical );
	}

	public function test_a_sitemap_skips_rows_that_are_not_posts_and_carries_a_subclass_s_extra_lines(): void {
		$p = bfst_wp_post( [ 'ID' => 4, 'post_modified_gmt' => '2026-01-02 03:04:05' ] );
		$GLOBALS['bfst_wp']['permalinks'][4] = 'https://example.test/four/';
		$GLOBALS['bfst_wp']['wp_query']      = [ 'posts' => [ 99, $p, null ], 'found_posts' => 3 ];
		$xml = ( new ExtraLinesProbe() )->xml( 'post', 1 );
		$this->assertSame( 1, substr_count( $xml, '<url>' ), 'only the WP_Post becomes a <url>' );
		$this->assertStringContainsString( "<loc>https://example.test/four/</loc>\n    <lastmod>2026-01-02</lastmod>", $xml );
		$this->assertStringContainsString( "    <x:extra id=\"4\"/>\n  </url>", $xml );
	}

	public function test_sitemap_output_serves_each_kind_and_null_when_there_is_nothing(): void {
		$GLOBALS['bfst_wp']['wp_query'] = [ 'posts' => [], 'found_posts' => 0 ];
		$this->assertStringStartsWith( "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<sitemapindex", (string) SitemapManager::sitemap_output( 'index', 1 ) );

		$p = bfst_wp_post( [ 'ID' => 1 ] );
		$GLOBALS['bfst_wp']['permalinks'][1] = 'https://example.test/one/';
		$GLOBALS['bfst_wp']['wp_query']      = [ 'posts' => [ $p ], 'found_posts' => 1 ];
		$this->assertStringContainsString( '<loc>https://example.test/one/</loc>', (string) SitemapManager::sitemap_output( 'post', 1 ) );

		$GLOBALS['bfst_wp']['wp_query'] = [ 'posts' => [], 'found_posts' => 0 ];
		$this->assertNull( SitemapManager::sitemap_output( 'post', 5 ), 'past the end' );
		$this->assertNull( SitemapManager::sitemap_output( 'nope', 1 ), 'unknown kind' );

		add_filter( 'bfst_render_sitemap', static fn( $o, $s, $pg ) => 'category' === $s ? "<x p=\"{$pg}\"/>" : ( 'blank' === $s ? '' : $o ), 10, 3 );
		$this->assertSame( '<x p="2"/>', SitemapManager::sitemap_output( 'category', 2 ) );
		$this->assertNull( SitemapManager::sitemap_output( 'blank', 1 ), 'an empty add-on render is a 404 too' );
	}

	public function test_sitemap_output_serves_the_home_sitemap_unless_a_post_type_took_the_name(): void {
		$home = (string) SitemapManager::sitemap_output( 'frontpage', 1 );
		$this->assertStringContainsString( '<loc>https://example.test/</loc>', $home );

		add_filter( 'bfst_sitemap_post_types', static fn() => [ 'post', 'frontpage' ] );
		$GLOBALS['bfst_wp']['wp_query'] = [ 'posts' => [], 'found_posts' => 0 ];
		$this->assertNull( SitemapManager::sitemap_output( 'frontpage', 1 ), 'the post type "frontpage" owns the name, and it is empty' );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_send_xml_prints_the_sitemap(): void {
		$this->expectOutputString( '<urlset/>' );
		SitemapManager::send_xml( '<urlset/>' );
	}

	public function test_uninstall_without_the_wordpress_constant_deletes_nothing(): void {
		$code = "update_option( 'bfst_features', 1 );\nregister_shutdown_function( static function () { echo json_encode( array_keys( \$GLOBALS['bfst_wp']['options'] ) ); } );\n";
		[ $out, $status ] = self::run_isolated( $code . "require BFST_PLUGIN_DIR . 'uninstall.php';\necho 'NOT EXITED';" );
		$this->assertSame( 0, $status, $out );
		$this->assertSame( '["bfst_features"]', $out, 'exited before deleting anything' );

		[ $out ] = self::run_isolated( "define( 'WP_UNINSTALL_PLUGIN', true );\n" . $code . "require BFST_PLUGIN_DIR . 'uninstall.php';" );
		$this->assertSame( '[]', $out, 'control: with the constant it deletes' );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_uninstall_deletes_the_options_and_bfst_transients(): void {
		define( 'WP_UNINSTALL_PLUGIN', true );
		foreach ( [ 'bfst_features', 'bfst_sitemap_exclusions', 'bfst_schema_version', 'bfst_activated_at', 'bfst_sitemap_cache_keys', 'unrelated' ] as $k ) {
			update_option( $k, 1 );
		}
		require BFST_PLUGIN_DIR . 'uninstall.php';
		$this->assertSame( [ 'unrelated' ], array_keys( $GLOBALS['bfst_wp']['options'] ) );
		$this->assertSame( [ "DELETE FROM wp_options WHERE option_name LIKE '\\_transient\\_bfst\\_sitemap\\_%' OR option_name LIKE '\\_transient\\_timeout\\_bfst\\_sitemap\\_%'" ], $GLOBALS['bfst_wp']['db_sql'], 'only this plugin\'s sitemap prefix: Pro\'s bfst_tax_ transients are Pro\'s to delete' );
	}

	/**
	 * With a persistent object cache transients never reach wp_options, so
	 * uninstall deletes the recorded keys and the index through
	 * delete_transient(), and leaves the Pro add-on's bfst_tax_ pages alone.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_uninstall_deletes_the_sitemap_transients_from_a_persistent_object_cache(): void {
		define( 'WP_UNINSTALL_PLUGIN', true );
		$GLOBALS['bfst_wp']['ext_cache'] = true;
		$a = 'bfst_sitemap_' . md5( 'post_1' );
		$b = 'bfst_sitemap_' . md5( 'page_1' );
		$t = 'bfst_tax_' . md5( 'category_1' );
		foreach ( [ $a, $b, 'bfst_sitemap_index', $t, 'other' ] as $k ) {
			set_transient( $k, 'x', 60 );
		}
		update_option( 'bfst_sitemap_cache_keys', [ $a, $b, 42, 'not_ours' ] );
		set_transient( 'not_ours', 'x', 60 );
		require BFST_PLUGIN_DIR . 'uninstall.php';
		$this->assertSame( [ $t, 'other', 'not_ours' ], array_keys( $GLOBALS['bfst_wp']['transients'] ), 'a recorded key without the bfst_sitemap_ prefix is never deleted' );
		$this->assertFalse( get_option( 'bfst_sitemap_cache_keys' ) );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_uninstall_walks_every_site_in_batches_of_200(): void {
		define( 'WP_UNINSTALL_PLUGIN', true );
		$GLOBALS['switched'] = [];
		$GLOBALS['restored'] = 0;
		eval( 'function get_sites( array $a ): array { $GLOBALS["asked"][] = $a; return 0 === $a["offset"] ? range( 1, 200 ) : [ 201 ]; } function switch_to_blog( int $id ): void { $GLOBALS["switched"][] = $id; } function restore_current_blog(): void { $GLOBALS["restored"]++; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- multisite functions, in the global namespace.
		require BFST_PLUGIN_DIR . 'uninstall.php';
		$this->assertSame( range( 1, 201 ), $GLOBALS['switched'] );
		$this->assertSame( 201, $GLOBALS['restored'], 'every switch is restored' );
		$this->assertSame( [ 0, 200 ], array_column( $GLOBALS['asked'], 'offset' ) );
		$this->assertSame( [ 200, 200 ], array_column( $GLOBALS['asked'], 'number' ) );
	}
}
