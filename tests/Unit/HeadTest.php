<?php
/**
 * HeadManager, OpenGraphRenderer and the renderer contract.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Tests\Unit;

use BlueFairy\SeoToolkit\Head\HeadManager;
use BlueFairy\SeoToolkit\Head\OpenGraphRenderer;
use BlueFairy\SeoToolkit\Head\RendererInterface;
use BlueFairy\SeoToolkit\PageContext;
use BlueFairy\SeoToolkit\Tests\TestCase;

/** Exposes the protected steps of the renderer. */
final class OgProbe extends OpenGraphRenderer {
	public function tags( PageContext $c ): array { return $this->build_tags( $c ); }
	public function tag( string $p, string $v ): string { return $this->meta_tag( $p, $v ); }
	public function title( PageContext $c ): string { return $this->get_title( $c ); }
	public function description( PageContext $c ): string { return $this->get_description( $c ); }
	public function type( PageContext $c ): string { return $this->get_og_type( $c ); }
	public static function suppressed( PageContext $c ): bool { return self::is_suppressed_context( $c ); }
}

final class FixedRenderer implements RendererInterface {
	public function __construct( private string $key, private string $html ) {}
	public function feature_key(): string { return $this->key; }
	public function render( PageContext $ctx ): string { return $this->html; }
}

final class HeadTest extends TestCase {

	private function singular( array $post, array $meta = [] ): PageContext {
		$p = bfst_wp_post( $post );
		$GLOBALS['bfst_wp']['queried']             = $p;
		$GLOBALS['bfst_wp']['post_meta'][ $p->ID ] = $meta;
		$GLOBALS['bfst_wp']['permalinks'][ $p->ID ] = 'https://example.test/p/' . $p->ID . '/';
		bfst_wp_cond( 'is_singular' );
		return PageContext::build();
	}

	public function test_a_post_gets_the_exact_open_graph_block(): void {
		$ctx = $this->singular( [ 'ID' => 7, 'post_title' => 'Tom & "Jerry"', 'post_excerpt' => '<b>Short</b> & sweet', 'post_type' => 'post' ] );
		$this->assertSame(
			"<meta property=\"og:title\" content=\"Tom &amp; &quot;Jerry&quot;\">\n" .
			"<meta property=\"og:description\" content=\"Short &amp; sweet\">\n" .
			"<meta property=\"og:url\" content=\"https://example.test/p/7/\">\n" .
			"<meta property=\"og:type\" content=\"article\">\n" .
			"<meta property=\"og:site_name\" content=\"Fixture Site\">\n" .
			"<meta property=\"og:locale\" content=\"en_US\">\n",
			( new OpenGraphRenderer() )->render( $ctx )
		);
	}

	public function test_404_search_and_feeds_get_no_open_graph(): void {
		bfst_wp_cond( 'is_404' );
		$ctx = PageContext::build();
		$this->assertSame( '404', $ctx->type );
		$this->assertTrue( OgProbe::suppressed( $ctx ) );
		$this->assertSame( '', ( new OgProbe() )->render( $ctx ) );

		bfst_wp_reset();
		bfst_wp_cond( 'is_feed' );
		$ctx = PageContext::build();
		$this->assertSame( 'archive', $ctx->type );
		$this->assertTrue( OgProbe::suppressed( $ctx ) );

		bfst_wp_reset();
		$this->assertFalse( OgProbe::suppressed( PageContext::build() ) );
	}

	public function test_meta_tag_uses_name_for_twitter_and_nothing_for_empty(): void {
		$r = new OgProbe();
		$this->assertSame( '<meta name="twitter:card" content="summary">', $r->tag( 'twitter:card', 'summary' ) );
		$this->assertSame( '<meta property="og:title" content="&lt;x&gt;">', $r->tag( 'og:title', '<x>' ) );
		$this->assertSame( '', $r->tag( 'og:title', '' ) );
	}

	public function test_titles_come_from_the_post_term_author_or_site(): void {
		$r = new OgProbe();
		$this->assertSame( 'A post', $r->title( $this->singular( [ 'ID' => 1, 'post_title' => 'A post' ] ) ) );

		bfst_wp_reset();
		$GLOBALS['bfst_wp']['queried'] = new \WP_Term( (object) [ 'term_id' => 3, 'name' => 'News', 'description' => '<p>All news</p>' ] );
		bfst_wp_cond( 'is_category' );
		$term = PageContext::build();
		$this->assertSame( 'News', $r->title( $term ) );
		$this->assertSame( 'All news', $r->description( $term ) );
		$this->assertSame( 'website', $r->type( $term ) );

		bfst_wp_reset();
		$GLOBALS['bfst_wp']['queried'] = new \WP_User( 4, 'Ada' );
		bfst_wp_cond( 'is_author' );
		$this->assertSame( 'Ada', $r->title( PageContext::build() ) );

		bfst_wp_reset();
		bfst_wp_cond( 'is_home' );
		$home = PageContext::build();
		$this->assertSame( 'Fixture Site', $r->title( $home ) );
		$this->assertSame( 'Fixture tagline', $r->description( $home ) );
	}

	public function test_a_term_without_a_description_has_none_never_the_tagline(): void {
		$GLOBALS['bfst_wp']['queried'] = new \WP_Term( (object) [ 'term_id' => 5, 'name' => 'Laravel', 'description' => '' ] );
		bfst_wp_cond( 'is_tag' );
		$ctx = PageContext::build();
		$this->assertSame( '', ( new OgProbe() )->description( $ctx ) );
		$this->assertStringNotContainsString( 'og:description', ( new OpenGraphRenderer() )->render( $ctx ) );
		$this->assertStringNotContainsString( 'Fixture tagline', ( new OpenGraphRenderer() )->render( $ctx ) );
	}

	public function test_og_locale_follows_the_site_locale_and_is_dropped_when_empty(): void {
		$ctx = $this->singular( [ 'ID' => 3, 'post_title' => 'T' ] );
		$GLOBALS['bfst_wp']['locale'] = 'de_DE';
		$this->assertStringContainsString( '<meta property="og:locale" content="de_DE">', ( new OpenGraphRenderer() )->render( $ctx ) );
		$GLOBALS['bfst_wp']['locale'] = '';
		$this->assertStringNotContainsString( 'og:locale', ( new OpenGraphRenderer() )->render( $ctx ) );
	}

	public function test_a_locked_post_never_leaks_its_text_into_the_description(): void {
		$r      = new OgProbe();
		$locked = $this->singular( [ 'ID' => 9, 'post_password' => 'pw', 'post_excerpt' => 'SECRET excerpt', 'post_content' => 'SECRET body' ] );
		$this->assertSame( 'Fixture tagline', $r->description( $locked ) );
		$GLOBALS['bfst_wp']['unlocked'][9] = true;
		$this->assertSame( 'SECRET excerpt', $r->description( $locked ) );
	}

	public function test_without_an_excerpt_the_content_is_stripped_and_trimmed_to_55_words(): void {
		$words = implode( ' ', array_map( static fn( $i ) => 'w' . $i, range( 1, 60 ) ) );
		$ctx   = $this->singular( [ 'ID' => 2, 'post_content' => '[gallery ids="1"]<p>' . $words . '</p>' ] );
		$this->assertSame( implode( ' ', array_map( static fn( $i ) => 'w' . $i, range( 1, 55 ) ) ) . '&hellip;', ( new OgProbe() )->description( $ctx ) );
	}

	public function test_og_type_is_article_for_posts_and_website_for_pages(): void {
		$r = new OgProbe();
		$this->assertSame( 'article', $r->type( $this->singular( [ 'ID' => 1, 'post_type' => 'post' ] ) ) );
		$this->assertSame( 'website', $r->type( $this->singular( [ 'ID' => 2, 'post_type' => 'page' ] ) ) );
	}

	public function test_build_tags_drops_url_and_site_name_when_empty(): void {
		$GLOBALS['bfst_wp']['bloginfo'] = [ 'name' => '', 'description' => 'D' ];
		bfst_wp_cond( 'is_search' );
		$ctx  = PageContext::build();
		$tags = array_values( array_filter( ( new OgProbe() )->tags( $ctx ) ) );
		$this->assertSame( [ '<meta property="og:description" content="D">', '<meta property="og:type" content="website">', '<meta property="og:locale" content="en_US">' ], $tags );
	}

	public function test_the_og_renderer_is_keyed_og(): void {
		$this->assertSame( 'og', ( new OpenGraphRenderer() )->feature_key() );
	}

	public function test_register_binds_each_renderer_to_wp_head_once_at_its_priority(): void {
		add_action( 'bfst_register_head_renderers', static fn() => HeadManager::add_renderer( new FixedRenderer( 'x', '<x>' ), 5 ) );
		HeadManager::register();
		HeadManager::register();
		$this->assertCount( 2, $GLOBALS['bfst_wp']['actions']['wp_head'] );
		$this->assertSame( [ 10, 5 ], array_column( $GLOBALS['bfst_wp']['actions']['wp_head'], 'priority' ) );
		$this->assertInstanceOf( OpenGraphRenderer::class, HeadManager::get_renderer( 'og' ) );
		$this->assertNull( HeadManager::get_renderer( 'missing' ) );
	}

	private function head_output(): string {
		HeadManager::register();
		do_action( 'wp' );
		return self::capture( static fn() => do_action( 'wp_head' ) );
	}

	public function test_wp_head_prints_enabled_renderers_and_skips_disabled_or_empty(): void {
		add_action(
			'bfst_register_head_renderers',
			static function (): void {
				HeadManager::add_renderer( new FixedRenderer( 'og', '<og>' ) );
				HeadManager::add_renderer( new FixedRenderer( 'off', '<off>' ) );
				HeadManager::add_renderer( new FixedRenderer( 'blank', '' ) );
			}
		);
		update_option( 'bfst_features', [ 'off' => false ] );
		$this->assertSame( '<og>', $this->head_output() );
		$this->assertTrue( HeadManager::will_render( 'og' ) );
		$this->assertFalse( HeadManager::will_render( 'off' ) );
	}

	public function test_nothing_prints_before_the_page_context_exists(): void {
		add_action( 'bfst_register_head_renderers', static fn() => HeadManager::add_renderer( new FixedRenderer( 'og', '<og>' ) ) );
		HeadManager::register();
		$this->assertSame( '', self::capture( static fn() => do_action( 'wp_head' ) ) );
	}

	public function test_a_conflicting_seo_plugin_pauses_output_unless_overridden(): void {
		$GLOBALS['bfst_wp']['active_plugins'] = [ 'wordpress-seo/wp-seo.php' ];
		add_action( 'bfst_register_head_renderers', static fn() => HeadManager::add_renderer( new FixedRenderer( 'og', '<og>' ) ) );
		$this->assertSame( '', $this->head_output() );
		$this->assertFalse( HeadManager::will_render( 'og' ) );

		self::set_static( HeadManager::class, 'features_cache', null );
		update_option( 'bfst_features', [ 'conflict_override' => [ 'og' => true ] ] );
		$this->assertTrue( HeadManager::will_render( 'og' ) );
		$this->assertSame( '<og>', self::capture( static fn() => do_action( 'wp_head' ) ) );

		self::set_static( HeadManager::class, 'features_cache', null );
		update_option( 'bfst_features', [ 'conflict_override' => 'garbage' ] );
		$this->assertFalse( HeadManager::will_render( 'og' ), 'a malformed override blocks' );
	}
}
