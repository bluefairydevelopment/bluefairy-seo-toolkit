<?php
/**
 * Points free users at the Pro add-on, discreetly.
 *
 * @package BlueFairy\SeoToolkit\Admin
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Admin;

/**
 * A plugins-list link and one notice on our own settings page.
 *
 * Both hide themselves the moment Pro is active — an upsell shown to someone
 * who has already bought is an advert for something they own, and it makes the
 * product look like it does not know its own state.
 *
 * Every feature named here ships in Pro today. Nothing aspirational: the free
 * plugin's own copy is the first place a prospective buyer reads what Pro does,
 * and a claim that fails on contact with the product is worse than no claim.
 */
final class ProUpsell {

	/**
	 * Where to send someone who wants Pro.
	 */
	private const PRODUCT_URL = 'https://wordpress.bluefairydevelopment.com/product/bluefairy-seo-toolkit-pro/';

	/**
	 * Register the upsell surfaces.
	 */
	public static function register(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( BFST_PLUGIN_FILE ), [ self::class, 'add_action_link' ] );
		add_action( 'bfst_settings_page_footer', [ self::class, 'render_notice' ] );
	}

	/**
	 * Is the Pro add-on already running?
	 */
	private static function pro_active(): bool {
		return defined( 'BFSTP_VERSION' );
	}

	/**
	 * Add "Get SEO Toolkit Pro" to the plugins-list row.
	 *
	 * "Get", not "Upgrade": Pro runs alongside this plugin rather than
	 * replacing it, and "upgrade" reads as a replacement.
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[]
	 */
	public static function add_action_link( array $links ): array {
		if ( self::pro_active() ) {
			return $links;
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" style="font-weight:600;color:#1d4ed8;">%s</a>',
				esc_url( self::PRODUCT_URL ),
				esc_html__( 'Get SEO Toolkit Pro', 'bluefairy-seo-toolkit' )
			)
		);

		return $links;
	}

	/**
	 * Render the callout beneath the settings form.
	 */
	public static function render_notice(): void {
		if ( self::pro_active() ) {
			return;
		}
		?>
		<div class="bfst-pro-callout" style="margin-top:2rem;padding:1rem 1.25rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:4px;max-width:46rem;">
			<h2 style="margin-top:0;font-size:1rem;"><?php esc_html_e( 'BlueFairy SEO Toolkit Pro', 'bluefairy-seo-toolkit' ); ?></h2>
			<p style="margin-bottom:0.5rem;">
				<?php esc_html_e( 'This plugin handles Open Graph tags, URL cleaning and your XML sitemap. Pro adds the parts search engines act on:', 'bluefairy-seo-toolkit' ); ?>
			</p>
			<ul style="margin:0 0 0.75rem 1.25rem;list-style:disc;">
				<li><?php esc_html_e( 'JSON-LD schema markup — Article, Product, Organization, BreadcrumbList and more, in one @graph block', 'bluefairy-seo-toolkit' ); ?></li>
				<li><?php esc_html_e( 'og:image, so shared links stop rendering as text-only cards, plus Twitter Cards', 'bluefairy-seo-toolkit' ); ?></li>
				<li><?php esc_html_e( 'A canonical tag of your own, with core\'s duplicate removed and per-post overrides', 'bluefairy-seo-toolkit' ); ?></li>
				<li><?php esc_html_e( 'A per-post SEO meta box: schema type, custom OG image, canonical override, sitemap exclusion', 'bluefairy-seo-toolkit' ); ?></li>
				<li><?php esc_html_e( 'Sitemaps for every post type and taxonomy, with Google image extensions', 'bluefairy-seo-toolkit' ); ?></li>
			</ul>
			<p style="margin-bottom:0;">
				<a href="<?php echo esc_url( self::PRODUCT_URL ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
					<?php esc_html_e( 'See SEO Toolkit Pro', 'bluefairy-seo-toolkit' ); ?>
				</a>
				<span style="margin-left:0.5rem;color:#4a6080;">
					<?php esc_html_e( 'Installs alongside this plugin. Your settings carry straight over.', 'bluefairy-seo-toolkit' ); ?>
				</span>
			</p>
		</div>
		<?php
	}
}
