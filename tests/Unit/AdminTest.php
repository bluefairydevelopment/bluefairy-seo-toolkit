<?php
/**
 * Settings page, feature-toggle REST endpoint and the Pro upsell.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Tests\Unit;

use BlueFairy\SeoToolkit\Admin\FeatureToggleEndpoint;
use BlueFairy\SeoToolkit\Admin\ProUpsell;
use BlueFairy\SeoToolkit\Admin\SettingsPage;
use BlueFairy\SeoToolkit\Tests\TestCase;

final class AdminTest extends TestCase {

	public function test_feature_labels_are_filterable(): void {
		$this->assertSame( [ 'og' => 'Open Graph tags', 'sitemap' => 'XML Sitemaps' ], SettingsPage::feature_labels() );
		add_filter( 'bfst_feature_labels', static fn( $l ) => $l + [ 'schema' => 'Schema' ] );
		$this->assertSame( [ 'og', 'sitemap', 'schema' ], array_keys( SettingsPage::feature_labels() ) );
	}

	public function test_register_hooks_the_page_settings_and_scripts(): void {
		SettingsPage::register();
		$this->assertSame( 10, has_action( 'admin_menu', [ SettingsPage::class, 'add_page' ] ) );
		$this->assertSame( 10, has_action( 'admin_init', [ SettingsPage::class, 'register_settings' ] ) );
		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', [ SettingsPage::class, 'enqueue_scripts' ] ) );
	}

	public function test_add_page_registers_settings_seo_for_admins(): void {
		SettingsPage::add_page();
		$this->assertSame( [ 'SEO Toolkit', 'SEO', 'manage_options', [ SettingsPage::class, 'render_page' ] ], $GLOBALS['bfst_wp']['options_pages']['bfst-seo'] );
	}

	public function test_scripts_load_only_on_the_settings_page(): void {
		SettingsPage::enqueue_scripts( 'index.php' );
		$this->assertSame( [], $GLOBALS['bfst_wp']['scripts'] );
		SettingsPage::enqueue_scripts( 'settings_page_bfst-seo' );
		$this->assertSame( [ 'https://example.test/wp-content/plugins/bluefairy-seo-toolkit/settings.js', [ 'jquery', 'wp-api' ], BFST_VERSION, true ], $GLOBALS['bfst_wp']['scripts']['bfst-settings'] );
		$this->assertSame( [ 'bfstSettings', [ 'root' => 'https://example.test/wp-json/', 'nonce' => 'nonce-wp_rest', 'savingText' => 'Saving…', 'savedText' => 'Saved', 'errorText' => 'Error saving' ] ], $GLOBALS['bfst_wp']['localized']['bfst-settings'] );
	}

	public function test_register_settings_wires_both_options_and_lets_pro_add_more(): void {
		SettingsPage::register_settings();
		$s = $GLOBALS['bfst_wp']['settings'];
		$this->assertSame( [ 'bfst_options', [ 'sanitize_callback' => [ SettingsPage::class, 'sanitize_features' ], 'default' => [ 'og' => true, 'sitemap' => true ] ] ], $s['bfst_features'] );
		$this->assertSame( [ 'bfst_options', [ 'sanitize_callback' => [ SettingsPage::class, 'sanitize_sitemap_exclusions' ], 'default' => [] ] ], $s['bfst_sitemap_exclusions'] );
		$this->assertSame( [ 'Sitemap Exclusions', [ SettingsPage::class, 'render_field_sitemap_exclusions' ], 'bfst-seo', 'bfst_advanced' ], $GLOBALS['bfst_wp']['fields']['bfst_sitemap_exclusions'] );
		$this->assertSame( [ 'Features', [ SettingsPage::class, 'render_conflict_notice' ], 'bfst-seo' ], $GLOBALS['bfst_wp']['sections']['bfst_features_section'] );
		$this->assertContains( 'bfst_register_settings', $GLOBALS['bfst_wp']['did'] );
	}

	public function test_render_page_prints_the_form_and_footer_hook(): void {
		add_action( 'bfst_settings_page_footer', static function (): void { echo '[footer]'; } );
		$html = self::capture( [ SettingsPage::class, 'render_page' ] );
		$this->assertStringContainsString( '<h1>SEO Toolkit</h1>', $html );
		$this->assertStringContainsString( '<input type="hidden" name="option_page" value="bfst_options">', $html );
		$this->assertStringContainsString( '<!-- sections:bfst-seo -->', $html );
		$this->assertStringContainsString( '[footer]', $html );
	}

	public function test_render_page_refuses_non_admins(): void {
		$GLOBALS['bfst_wp']['can'] = false;
		$this->expectException( \BFST_Wp_Die::class );
		$this->expectExceptionMessage( 'You do not have permission to access this page.' );
		SettingsPage::render_page();
	}

	public function test_conflict_notice_is_silent_without_conflicts(): void {
		$this->assertSame( '', self::capture( [ SettingsPage::class, 'render_conflict_notice' ] ) );
	}

	public function test_conflict_notice_names_the_plugins_and_checks_overridden_features(): void {
		$GLOBALS['bfst_wp']['active_plugins'] = [ 'wordpress-seo/wp-seo.php', 'seopress/seopress.php' ];
		update_option( 'bfst_features', [ 'conflict_override' => [ 'sitemap' => true ] ] );
		$html = self::capture( [ SettingsPage::class, 'render_conflict_notice' ] );
		$this->assertStringContainsString( 'active: <strong>Yoast SEO, SEOPress</strong>. All BlueFairy SEO Toolkit features are paused by default.', $html );
		$this->assertMatchesRegularExpression( '/data-feature="og"\s*>/', $html );
		$this->assertMatchesRegularExpression( "/data-feature=\"sitemap\"\\s* checked='checked'>/", $html );
	}

	public function test_feature_checkboxes_default_on_and_reflect_saved_values(): void {
		update_option( 'bfst_features', [ 'og' => false ] );
		$this->assertSame(
			'<label style="display:block; margin-bottom:6px;"><input type="checkbox" name="bfst_features[og]" value="1" > Open Graph tags</label>' .
			"<label style=\"display:block; margin-bottom:6px;\"><input type=\"checkbox\" name=\"bfst_features[sitemap]\" value=\"1\"  checked='checked'> XML Sitemaps</label>",
			self::capture( [ SettingsPage::class, 'render_field_features' ] )
		);
		update_option( 'bfst_features', 'corrupt' );
		$this->assertSame( 2, substr_count( self::capture( [ SettingsPage::class, 'render_field_features' ] ), "checked='checked'" ) );
	}

	public function test_exclusion_field_lists_public_types_and_taxonomies(): void {
		$GLOBALS['bfst_wp']['post_types'] = [ (object) [ 'name' => 'page', 'label' => 'Pages' ] ];
		$GLOBALS['bfst_wp']['taxonomies'] = [ (object) [ 'name' => 'category', 'label' => 'Categories' ] ];
		update_option( 'bfst_sitemap_exclusions', [ 'category' => true ] );
		$html = self::capture( [ SettingsPage::class, 'render_field_sitemap_exclusions' ] );
		$this->assertStringContainsString( '<input type="checkbox" name="bfst_sitemap_exclusions[page]" value="1" > Pages <code>(page)</code></label>', $html );
		$this->assertStringContainsString( "<input type=\"checkbox\" name=\"bfst_sitemap_exclusions[category]\" value=\"1\"  checked='checked'> Categories <code>(category)</code></label>", $html );
		update_option( 'bfst_sitemap_exclusions', 'corrupt' );
		$this->assertStringNotContainsString( 'checked', self::capture( [ SettingsPage::class, 'render_field_sitemap_exclusions' ] ) );
	}

	public function test_sanitize_features_keeps_known_keys_as_bools_and_preserves_overrides(): void {
		update_option( 'bfst_features', [ 'conflict_override' => [ 'og' => true ] ] );
		$this->assertSame( [ 'og' => true, 'sitemap' => false, 'conflict_override' => [ 'og' => true ] ], SettingsPage::sanitize_features( [ 'og' => '1', 'evil' => '1' ] ) );
		delete_option( 'bfst_features' );
		$this->assertSame( [ 'og' => false, 'sitemap' => false ], SettingsPage::sanitize_features( 'nope' ) );
	}

	public function test_sanitize_exclusions_cleans_keys_and_casts_values(): void {
		$this->assertSame( [ 'post' => true, 'product_cat' => false ], SettingsPage::sanitize_sitemap_exclusions( [ 'Po<s>t' => '1', 'product_cat' => '' ] ) );
		$this->assertSame( [], SettingsPage::sanitize_sitemap_exclusions( 'x' ) );
	}

	public function test_rest_route_is_admin_only_and_limited_to_known_features(): void {
		FeatureToggleEndpoint::register();
		$this->assertSame( 10, has_action( 'rest_api_init', [ FeatureToggleEndpoint::class, 'register_route' ] ) );
		FeatureToggleEndpoint::register_route();
		$r = $GLOBALS['bfst_wp']['rest_routes']['bluefairy-seo/v1/feature-toggle'];
		$this->assertSame( 'POST, PUT, PATCH', $r['methods'] );
		$this->assertSame( [ FeatureToggleEndpoint::class, 'handle' ], $r['callback'] );
		$this->assertSame( [ 'og', 'sitemap' ], $r['args']['feature']['enum'] );
		$this->assertTrue( ( $r['args']['feature']['validate_callback'] )( 'og' ) );
		$this->assertFalse( ( $r['args']['feature']['validate_callback'] )( 'schema' ) );
		$this->assertTrue( ( $r['permission_callback'] )() );
		$GLOBALS['bfst_wp']['can'] = false;
		$this->assertFalse( ( $r['permission_callback'] )() );
	}

	public function test_handle_stores_the_override_and_reports_it(): void {
		update_option( 'bfst_features', [ 'og' => true ] );
		$res = FeatureToggleEndpoint::handle( new \WP_REST_Request( [ 'feature' => 'sitemap', 'enabled' => true ] ) );
		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( [ 'success' => true, 'feature' => 'sitemap', 'enabled' => true, 'updated' => true ], $res->get_data() );
		$this->assertSame( [ 'og' => true, 'conflict_override' => [ 'sitemap' => true ] ], get_option( 'bfst_features' ) );
	}

	public function test_handle_rejects_unknown_features_and_repairs_corrupt_options(): void {
		$res = FeatureToggleEndpoint::handle( new \WP_REST_Request( [ 'feature' => 'schema', 'enabled' => true ] ) );
		$this->assertSame( [ 400, [ 'success' => false, 'message' => 'Invalid feature key.' ] ], [ $res->get_status(), $res->get_data() ] );
		update_option( 'bfst_features', 'corrupt' );
		FeatureToggleEndpoint::handle( new \WP_REST_Request( [ 'feature' => 'og', 'enabled' => false ] ) );
		$this->assertSame( [ 'conflict_override' => [ 'og' => false ] ], get_option( 'bfst_features' ) );
	}

	public function test_upsell_link_and_notice_show_only_without_pro(): void {
		ProUpsell::register();
		$this->assertSame( 10, has_filter( 'plugin_action_links_bluefairy-seo-toolkit/bluefairy-seo-toolkit.php', [ ProUpsell::class, 'add_action_link' ] ) );
		$links = ProUpsell::add_action_link( [ '<a>Settings</a>' ] );
		$this->assertSame( '<a href="https://wordpress.bluefairydevelopment.com/product/bluefairy-seo-toolkit-pro/" target="_blank" rel="noopener noreferrer" style="font-weight:600;color:#1d4ed8;">Get SEO Toolkit Pro</a>', $links[0] );
		$this->assertSame( '<a>Settings</a>', $links[1] );
		$html = self::capture( [ ProUpsell::class, 'render_notice' ] );
		$this->assertStringContainsString( '<h2 style="margin-top:0;font-size:1rem;">BlueFairy SEO Toolkit Pro</h2>', $html );
		$this->assertStringContainsString( 'A canonical tag of your own, with core&#039;s duplicate removed and per-post overrides', $html );
	}
}
