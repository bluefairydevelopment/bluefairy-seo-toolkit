<?php
/**
 * Plugin Name: BlueFairy SEO Toolkit Lite
 * Plugin URI:  https://wordpress.bluefairydevelopment.com/product/seo-toolkit/
 * Description: Essential Open Graph tags and a flat XML sitemap — no bloat. Automatically pauses when Yoast SEO, Rank Math, SEOPress, or The SEO Framework is active so you never get duplicate meta output. Upgrade to Pro for JSON-LD schema, canonical management, Twitter Cards, and per-post overrides.
 * Version:     1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author:      Blue Fairy Development
 * Author URI:  https://bluefairydevelopment.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bluefairy-seo-toolkit-lite
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BFSTL_PLUGIN_FILE', __FILE__ );
define( 'BFSTL_VERSION', '1.0.0' );
define( 'BFSTL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Activation and deactivation hooks MUST be at file top level (not inside any hook).
require_once BFSTL_PLUGIN_DIR . 'includes/class-plugin.php';
require_once BFSTL_PLUGIN_DIR . 'includes/class-conflict-detector.php';
require_once BFSTL_PLUGIN_DIR . 'includes/class-og-tags.php';
require_once BFSTL_PLUGIN_DIR . 'includes/class-sitemap.php';

register_activation_hook( __FILE__, [ 'BlueFairy_SEO_Toolkit_Lite_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BlueFairy_SEO_Toolkit_Lite_Plugin', 'deactivate' ] );

// Boot the plugin after all plugins have loaded so conflict detection is accurate.
add_action( 'plugins_loaded', [ 'BlueFairy_SEO_Toolkit_Lite_Plugin', 'boot' ] );
