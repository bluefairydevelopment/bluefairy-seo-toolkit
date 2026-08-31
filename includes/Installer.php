<?php
/**
 * Activation and deactivation lifecycle handler.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit;

use BlueFairy\SeoToolkit\Sitemap\SitemapManager;

/**
 * Handles plugin activation and deactivation.
 *
 * Activation and deactivation hooks are registered at file top level in the
 * main plugin file (not inside any hook) — see Decision Log #21.
 */
final class Installer {

    /**
     * Minimum required PHP version.
     */
    private const MIN_PHP = '8.1';

    /**
     * Minimum required WordPress version.
     */
    private const MIN_WP = '6.6';

    /**
     * Plugin schema version stored in options.
     */
    private const SCHEMA_VERSION = '1.0.0';

    /**
     * Run on plugin activation.
     *
     * Order matters:
     * 1. Version check first — bail early if requirements not met.
     * 2. Register sitemap rewrite rules BEFORE flushing (init hasn't fired yet).
     * 3. Flush rewrite rules.
     * 4. Set default options (idempotent — only if not already set).
     * 5. Store schema version and activation timestamp.
     */
    public static function activate(): void {
        self::check_requirements();

        // Must register rules before flush — init hasn't fired during activation.
        SitemapManager::register_rules();
        flush_rewrite_rules();

        self::set_default_options();

        update_option( 'bfst_schema_version', self::SCHEMA_VERSION, true );
        update_option( 'bfst_activated_at', gmdate( 'Y-m-d H:i:s' ), true );
    }

    /**
     * Run on plugin deactivation.
     *
     * Only needs to flush rewrite rules — no rule re-registration needed
     * because WordPress rebuilds rules on the next init after activation.
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Enforce minimum PHP and WordPress version requirements.
     * Deactivates the plugin and dies with a user-friendly message if not met.
     */
    private static function check_requirements(): void {
        $errors = [];

        if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: minimum PHP version, 2: current PHP version */
                __( 'BlueFairy SEO Toolkit requires PHP %1$s or higher. You are running PHP %2$s.', 'bluefairy-seo-toolkit' ),
                self::MIN_PHP,
                PHP_VERSION
            );
        }

        global $wp_version;
        if ( version_compare( $wp_version, self::MIN_WP, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: minimum WP version, 2: current WP version */
                __( 'BlueFairy SEO Toolkit requires WordPress %1$s or higher. You are running WordPress %2$s.', 'bluefairy-seo-toolkit' ),
                self::MIN_WP,
                $wp_version
            );
        }

        if ( empty( $errors ) ) {
            return;
        }

        // Deactivate the plugin and terminate with an error message.
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins( plugin_basename( BFST_PLUGIN_FILE ) );
        wp_die(
            '<p>' . implode( '</p><p>', array_map( 'esc_html', $errors ) ) . '</p>',
            esc_html__( 'Plugin Activation Error', 'bluefairy-seo-toolkit' ),
            [ 'back_link' => true ]
        );
    }

    /**
     * Set default option values on first activation (idempotent).
     *
     * Uses add_option() which only sets the value if the option does not
     * already exist — safe to call on every activation.
     */
    private static function set_default_options(): void {
        // Features — all enabled by default (conflict detector may override at runtime).
        add_option(
            'bfst_features',
            [
                'og'      => true,
                'sitemap' => true,
            ],
            '',
            true
        );

        // Advanced — autoload=false (admin-only config, not needed on frontend).
        add_option( 'bfst_sitemap_exclusions', [], '', false );
    }
}
