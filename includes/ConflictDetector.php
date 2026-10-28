<?php
/**
 * Detects known SEO plugins that may conflict with BlueFairy SEO Toolkit.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit;

/**
 * Static conflict detector for known SEO plugins.
 *
 * Checks once per request (result is cached in a static property) to avoid
 * repeated filesystem checks on every renderer call.
 *
 * Known plugins and their main plugin files:
 * - Yoast SEO
 * - Rank Math
 * - SEOPress
 * - The SEO Framework
 */
final class ConflictDetector {

	/**
	 * Map of friendly slug => plugin main file (relative to plugins directory).
	 *
	 * @var array<string, string>
	 */
	private const KNOWN_PLUGINS = [
		'yoast'             => 'wordpress-seo/wp-seo.php',
		'rankmath'          => 'seo-by-rank-math/rank-math.php',
		'seopress'          => 'seopress/seopress.php',
		'the-seo-framework' => 'autodescription/autodescription.php',
	];

	/**
	 * Cached list of detected (active) conflict plugin slugs.
	 * Null means the scan has not been run yet.
	 *
	 * @var string[]|null
	 */
	private static ?array $detected = null;

	/**
	 * Run the conflict scan and populate the static cache.
	 *
	 * Called once from Plugin::boot(). Safe to call multiple times — subsequent
	 * calls are no-ops because the result is already cached.
	 */
	public static function scan(): void {
		if ( null !== self::$detected ) {
			return;
		}

		// is_plugin_active() is not available on frontend requests by default.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		self::$detected = [];

		foreach ( self::KNOWN_PLUGINS as $slug => $plugin_file ) {
			if ( is_plugin_active( $plugin_file ) ) {
				self::$detected[] = $slug;
			}
		}
	}

	/**
	 * Return the list of active conflict plugin slugs.
	 *
	 * Triggers the scan if it has not run yet (safe for early calls).
	 *
	 * @return string[] Array of friendly slug strings, e.g. ['yoast', 'rankmath'].
	 */
	public static function detected(): array {
		if ( null === self::$detected ) {
			self::scan();
		}
		return self::$detected ?? [];
	}

	/**
	 * Whether any conflicting plugin is active.
	 */
	public static function has_conflicts(): bool {
		return ! empty( self::detected() );
	}
}
