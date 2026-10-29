<?php
/**
 * Plugin Name: BlueFairy SEO Toolkit
 * Plugin URI:  https://wordpress.bluefairydevelopment.com/product/bluefairy-seo-toolkit/
 * Description: Open Graph tags and XML sitemaps for WordPress — no bloat, no configuration. Pauses automatically when Yoast SEO, Rank Math, SEOPress or The SEO Framework is active, so you never get duplicate output. Add BlueFairy SEO Toolkit Pro for JSON-LD schema, canonical management and the per-post SEO meta box.
 * Version:     1.1.5
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author:      Blue Fairy Development
 * Author URI:  https://bluefairydevelopment.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bluefairy-seo-toolkit
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BFST_PLUGIN_FILE', __FILE__ );
define( 'BFST_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BFST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BFST_VERSION', '1.1.5' );

// Autoloader — no Composer required.
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'BlueFairy\\SeoToolkit\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = __DIR__ . '/includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

// Activation and deactivation hooks MUST be at file top level (not inside any hook).
register_activation_hook( __FILE__, [ BlueFairy\SeoToolkit\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ BlueFairy\SeoToolkit\Installer::class, 'deactivate' ] );

/**
 * Accessor used by the Pro add-on to confirm the base plugin is really loaded.
 *
 * BFST_VERSION alone is not proof: a constant can be defined by anything. Pro
 * checks for this function AND the constant, exactly as Forms Pro checks
 * bffrm_plugin() and BFFRM_VERSION.
 */
function bfst_plugin(): string {
	return BFST_VERSION;
}

// Boot after all plugins have loaded so conflict detection is accurate and the
// Pro add-on (priority 20) can register its renderers against ours.
add_action( 'plugins_loaded', [ BlueFairy\SeoToolkit\Plugin::class, 'boot' ], 10 );
