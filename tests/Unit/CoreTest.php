<?php
/**
 * ConflictDetector, Installer, Plugin::boot and uninstall.php.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Tests\Unit;

use BlueFairy\SeoToolkit\Admin\FeatureToggleEndpoint;
use BlueFairy\SeoToolkit\Admin\ProUpsell;
use BlueFairy\SeoToolkit\Admin\SettingsPage;
use BlueFairy\SeoToolkit\ConflictDetector;
use BlueFairy\SeoToolkit\Installer;
use BlueFairy\SeoToolkit\Plugin;
use BlueFairy\SeoToolkit\Sitemap\SitemapManager;
use BlueFairy\SeoToolkit\Tests\TestCase;

final class CoreTest extends TestCase {

	public function test_no_known_seo_plugin_means_no_conflicts(): void {
		$GLOBALS['bfst_wp']['active_plugins'] = [ 'akismet/akismet.php' ];
		ConflictDetector::scan();
		$this->assertSame( [], ConflictDetector::detected() );
		$this->assertFalse( ConflictDetector::has_conflicts() );
	}

	public function test_each_known_seo_plugin_is_detected_by_slug_in_order(): void {
		$GLOBALS['bfst_wp']['active_plugins'] = [ 'autodescription/autodescription.php', 'wordpress-seo/wp-seo.php', 'seopress/seopress.php', 'seo-by-rank-math/rank-math.php' ];
		$this->assertSame( [ 'yoast', 'rankmath', 'seopress', 'the-seo-framework' ], ConflictDetector::detected() );
		$this->assertTrue( ConflictDetector::has_conflicts() );
	}

	public function test_the_scan_runs_once_per_request(): void {
		ConflictDetector::scan();
		$GLOBALS['bfst_wp']['active_plugins'] = [ 'wordpress-seo/wp-seo.php' ];
		ConflictDetector::scan();
		$this->assertSame( [], ConflictDetector::detected(), 'cached result is kept' );
	}

	public function test_activation_registers_rules_flushes_and_seeds_defaults_once(): void {
		Installer::activate();
		$o = $GLOBALS['bfst_wp']['options'];
		$this->assertSame( [ 'og' => true, 'sitemap' => true ], $o['bfst_features'] );
		$this->assertSame( [], $o['bfst_sitemap_exclusions'] );
		$this->assertFalse( $GLOBALS['bfst_wp']['autoload']['bfst_sitemap_exclusions'] );
		$this->assertTrue( $GLOBALS['bfst_wp']['autoload']['bfst_features'] );
		$this->assertSame( '1.0.0', $o['bfst_schema_version'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $o['bfst_activated_at'] );
		$this->assertSame( 1, $GLOBALS['bfst_wp']['flushed'] );
		$this->assertArrayHasKey( '^sitemap_index\.xml$', $GLOBALS['bfst_wp']['rewrite_rules'] );

		$GLOBALS['bfst_wp']['options']['bfst_features'] = [ 'og' => false ];
		Installer::activate();
		$this->assertSame( [ 'og' => false ], $GLOBALS['bfst_wp']['options']['bfst_features'], 'reactivation keeps the owner\'s choices' );
	}

	public function test_activation_on_an_old_wordpress_deactivates_and_dies_with_the_versions(): void {
		$GLOBALS['wp_version'] = '6.5';
		try {
			Installer::activate();
			$this->fail( 'wp_die was not called' );
		} catch ( \BFST_Wp_Die $e ) {
			$this->assertSame( '<p>BlueFairy SEO Toolkit requires WordPress 6.6 or higher. You are running WordPress 6.5.</p>', $e->getMessage() );
		}
		$this->assertSame( [ 'bluefairy-seo-toolkit/bluefairy-seo-toolkit.php' ], $GLOBALS['bfst_wp']['deactivated'] );
		$this->assertSame( 'Plugin Activation Error', $GLOBALS['bfst_wp']['wp_die'][1] );
		$this->assertArrayNotHasKey( 'bfst_features', $GLOBALS['bfst_wp']['options'], 'nothing seeded' );
	}

	public function test_deactivation_flushes_rewrite_rules(): void {
		Installer::deactivate();
		$this->assertSame( 1, $GLOBALS['bfst_wp']['flushed'] );
	}

	public function test_boot_on_the_front_end_wires_head_and_sitemaps_but_no_admin(): void {
		Plugin::boot();
		$this->assertContains( 'bfst_loaded', $GLOBALS['bfst_wp']['did'] );
		$this->assertSame( 10, has_action( 'template_redirect', [ SitemapManager::class, 'route_sitemap' ] ) );
		$this->assertNotEmpty( self::hooked( 'wp' ), 'PageContext::init bound to wp' );
		$this->assertFalse( has_action( 'admin_menu', [ SettingsPage::class, 'add_page' ] ) );
		$this->assertFalse( has_action( 'rest_api_init', [ FeatureToggleEndpoint::class, 'register_route' ] ) );
	}

	public function test_boot_in_admin_also_wires_settings_rest_and_upsell(): void {
		bfst_wp_cond( 'is_admin' );
		Plugin::boot();
		$this->assertSame( 10, has_action( 'admin_menu', [ SettingsPage::class, 'add_page' ] ) );
		$this->assertSame( 10, has_action( 'rest_api_init', [ FeatureToggleEndpoint::class, 'register_route' ] ) );
		$this->assertSame( 10, has_action( 'bfst_settings_page_footer', [ ProUpsell::class, 'render_notice' ] ) );
	}

	public function test_uninstall_deletes_the_options_and_bfst_transients(): void {
		[ $out, $status ] = self::run_isolated(
			<<<'PHP'
			define( 'WP_UNINSTALL_PLUGIN', true );
			foreach ( [ 'bfst_features', 'bfst_sitemap_exclusions', 'bfst_schema_version', 'bfst_activated_at', 'bfst_sitemap_cache_keys', 'unrelated' ] as $k ) { update_option( $k, 1 ); }
			require BFST_PLUGIN_DIR . 'uninstall.php';
			echo json_encode( [ array_keys( $GLOBALS['bfst_wp']['options'] ), $GLOBALS['bfst_wp']['db_sql'] ] );
			PHP
		);
		$this->assertSame( 0, $status, $out );
		$this->assertSame( '[["unrelated"],["DELETE FROM wp_options WHERE option_name LIKE \'\\\\_transient\\\\_bfst\\\\_sitemap\\\\_%\' OR option_name LIKE \'\\\\_transient\\\\_timeout\\\\_bfst\\\\_sitemap\\\\_%\'"]]', $out, 'only this plugin\'s sitemap prefix: Pro\'s bfst_tax_ transients are Pro\'s to delete' );
	}

	/**
	 * On a site with a persistent object cache (Redis, Memcached) transients
	 * never reach wp_options, so the LIKE sweep finds nothing. Uninstall must
	 * delete the recorded keys and the index through delete_transient(), and
	 * leave the Pro add-on's bfst_tax_ pages alone.
	 */
	public function test_uninstall_deletes_the_sitemap_transients_from_a_persistent_object_cache(): void {
		[ $out, $status ] = self::run_isolated(
			<<<'PHP'
			define( 'WP_UNINSTALL_PLUGIN', true );
			$GLOBALS['bfst_wp']['ext_cache'] = true;
			$a = 'bfst_sitemap_' . md5( 'post_1' );
			$b = 'bfst_sitemap_' . md5( 'page_1' );
			$t = 'bfst_tax_' . md5( 'category_1' );
			foreach ( [ $a, $b, 'bfst_sitemap_index', $t, 'other' ] as $k ) { set_transient( $k, 'x', 60 ); }
			update_option( 'bfst_sitemap_cache_keys', [ $a, $b ] );
			require BFST_PLUGIN_DIR . 'uninstall.php';
			echo json_encode( array_keys( $GLOBALS['bfst_wp']['transients'] ) ), '|', json_encode( get_option( 'bfst_sitemap_cache_keys' ) );
			PHP
		);
		$this->assertSame( 0, $status, $out );
		$this->assertSame( '["bfst_tax_' . md5( 'category_1' ) . '","other"]|false', $out );
	}

	public function test_uninstall_walks_every_site_in_batches_of_200(): void {
		[ $out, $status ] = self::run_isolated(
			<<<'PHP'
			define( 'WP_UNINSTALL_PLUGIN', true );
			$GLOBALS['switched'] = [];
			function get_sites( array $a ): array { return $a['offset'] === 0 ? range( 1, 200 ) : [ 201 ]; }
			function switch_to_blog( int $id ): void { $GLOBALS['switched'][] = $id; }
			function restore_current_blog(): void {}
			require BFST_PLUGIN_DIR . 'uninstall.php';
			echo count( $GLOBALS['switched'] ), ',', end( $GLOBALS['switched'] );
			PHP
		);
		$this->assertSame( 0, $status, $out );
		$this->assertSame( '201,201', $out );
	}
}
