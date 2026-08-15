<?php
/**
 * Uninstall handler for BlueFairy SEO Toolkit Lite.
 *
 * Runs when the plugin is deleted via the WordPress admin (Plugins > Delete).
 * This file is loaded by WordPress directly — NOT via the normal plugin bootstrap.
 *
 * Cleans up:
 * - Sitemap XML transient.
 * - Rewrite rules flush so /sitemap.xml stops resolving.
 *
 * @package BlueFairy_SEO_Toolkit_Lite
 * @see https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

declare(strict_types=1);

// Security: WordPress must define WP_UNINSTALL_PLUGIN before loading this file.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete the sitemap transient.
delete_transient( 'bfstl_sitemap_xml' );

// Flush rewrite rules so the /sitemap.xml rewrite rule is removed.
flush_rewrite_rules( false );
