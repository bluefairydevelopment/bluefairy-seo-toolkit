<?php
/**
 * Plugin bootstrap — wires all subsystems and handles activation/deactivation.
 *
 * Activation and deactivation hooks are registered at file top level in the
 * main plugin file (not inside any hook) and call the static methods here.
 *
 * @package BlueFairy_SEO_Toolkit_Lite
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin bootstrap and lifecycle handler.
 *
 * Boot() runs on plugins_loaded. All hook registration is deferred to the
 * individual subsystem classes — this class only orchestrates them.
 * No echo in any method — only hook registration and class calls.
 */
final class BlueFairy_SEO_Toolkit_Lite_Plugin {

    /**
     * Minimum required PHP version.
     */
    private const MIN_PHP = '8.1';

    /**
     * Minimum required WordPress version.
     */
    private const MIN_WP = '6.6';

    /**
     * Boot the plugin on plugins_loaded.
     *
     * Order:
     * 1. Run conflict detection (result cached statically; must run before any output hook).
     * 2. Register Open Graph hook (will bail at render time if conflict detected).
     * 3. Register sitemap hooks (will bail at render time if conflict detected).
     * 4. Register admin-only hooks behind is_admin() guard.
     *
     * @return void
     */
    public static function boot(): void {
        // Conflict scan must run first — before any output-registering code.
        BlueFairy_SEO_Toolkit_Lite_Conflict_Detector::scan();

        // Register feature hooks (each class guards against conflicts at render time).
        BlueFairy_SEO_Toolkit_Lite_OG_Tags::register();
        BlueFairy_SEO_Toolkit_Lite_Sitemap::register();

        // Admin-only code — not needed on frontend.
        if ( is_admin() ) {
            self::register_admin_hooks();
        }
    }

    /**
     * Register admin-only hooks.
     *
     * - Conflict admin notice.
     * - "Upgrade to Pro" plugin action link.
     *
     * @return void
     */
    private static function register_admin_hooks(): void {
        // Conflict notice — only shown in wp-admin when a conflict exists.
        add_action( 'admin_notices', [ self::class, 'render_conflict_notice' ] );

        // Upgrade to Pro action link in the plugins list table.
        $basename = plugin_basename( BFSTL_PLUGIN_FILE );
        add_filter(
            'plugin_action_links_' . $basename,
            [ self::class, 'add_action_links' ]
        );
    }

    /**
     * Render a single admin notice when a conflicting SEO plugin is active.
     *
     * Only shown when a conflict is detected. Uses a single combined notice
     * regardless of how many conflicting plugins are active.
     *
     * @return void
     */
    public static function render_conflict_notice(): void {
        if ( ! BlueFairy_SEO_Toolkit_Lite_Conflict_Detector::has_conflicts() ) {
            return;
        }

        $conflicts = BlueFairy_SEO_Toolkit_Lite_Conflict_Detector::detected();
        $names     = implode( ', ', array_map( 'esc_html', $conflicts ) );

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
            esc_html__( 'BlueFairy SEO Toolkit Lite:', 'bluefairy-seo-toolkit-lite' ),
            sprintf(
                /* translators: %s: comma-separated list of conflicting plugin names */
                esc_html__( 'Output paused because %s is active. Both plugins would write Open Graph tags and sitemaps — keeping only one active prevents duplicate meta output that breaks social crawlers.', 'bluefairy-seo-toolkit-lite' ),
                $names // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via esc_html() on line 95
            )
        );
    }

    /**
     * Add an "Upgrade to Pro" link to the plugin's action links row.
     *
     * @param string[] $links Existing plugin action links.
     * @return string[]
     */
    public static function add_action_links( array $links ): array {
        $upgrade_link = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#d63638;font-weight:600;">%s</a>',
            esc_url( 'https://wordpress.bluefairydevelopment.com/product/seo-toolkit/' ),
            esc_html__( 'Upgrade to Pro', 'bluefairy-seo-toolkit-lite' )
        );

        // Prepend so the link appears before Deactivate.
        array_unshift( $links, $upgrade_link );
        return $links;
    }

    /**
     * Run on plugin activation.
     *
     * Order:
     * 1. Version check — bail early if requirements not met.
     * 2. Register sitemap rewrite rules BEFORE flushing (init hasn't fired yet during activation).
     * 3. Flush rewrite rules so /sitemap.xml resolves immediately.
     *
     * @return void
     */
    public static function activate(): void {
        self::check_requirements();

        // Must register the rule before flush — init has not fired yet.
        BlueFairy_SEO_Toolkit_Lite_Sitemap::register_rules();
        flush_rewrite_rules( false );
    }

    /**
     * Run on plugin deactivation.
     *
     * Flush rewrite rules so /sitemap.xml no longer resolves.
     *
     * @return void
     */
    public static function deactivate(): void {
        flush_rewrite_rules( false );
    }

    /**
     * Enforce minimum PHP and WordPress version requirements.
     *
     * Deactivates the plugin and calls wp_die() with a user-friendly message
     * if requirements are not met.
     *
     * @return void
     */
    private static function check_requirements(): void {
        $errors = [];

        if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: minimum PHP version, 2: current PHP version */
                __( 'BlueFairy SEO Toolkit Lite requires PHP %1$s or higher. You are running PHP %2$s.', 'bluefairy-seo-toolkit-lite' ),
                self::MIN_PHP,
                PHP_VERSION
            );
        }

        global $wp_version;
        if ( version_compare( (string) $wp_version, self::MIN_WP, '<' ) ) {
            $errors[] = sprintf(
                /* translators: 1: minimum WP version, 2: current WP version */
                __( 'BlueFairy SEO Toolkit Lite requires WordPress %1$s or higher. You are running WordPress %2$s.', 'bluefairy-seo-toolkit-lite' ),
                self::MIN_WP,
                (string) $wp_version
            );
        }

        if ( empty( $errors ) ) {
            return;
        }

        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins( plugin_basename( BFSTL_PLUGIN_FILE ) );
        wp_die(
            '<p>' . implode( '</p><p>', array_map( 'esc_html', $errors ) ) . '</p>',
            esc_html__( 'Plugin Activation Error', 'bluefairy-seo-toolkit-lite' ),
            [ 'back_link' => true ]
        );
    }
}
