<?php
/**
 * Plugin bootstrap — wires all subsystems.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit;

use BlueFairy\SeoToolkit\Admin\FeatureToggleEndpoint;
use BlueFairy\SeoToolkit\Admin\ProUpsell;
use BlueFairy\SeoToolkit\Admin\SettingsPage;
use BlueFairy\SeoToolkit\Head\HeadManager;
use BlueFairy\SeoToolkit\Sitemap\SitemapManager;

/**
 * Plugin bootstrap class.
 *
 * Registered as the plugins_loaded callback. Instantiates subsystems and
 * wires hooks. Admin-only classes are gated behind is_admin() to keep the
 * frontend memory footprint small.
 */
final class Plugin {

    /**
     * Boot the plugin on plugins_loaded.
     */
    public static function boot(): void {
        // Run conflict detection once; result is statically cached.
        ConflictDetector::scan();

        /**
         * The base plugin is ready to be extended.
         *
         * Fired BEFORE the subsystems register, which is the only ordering that
         * works. An add-on cannot substitute a sitemap handler after
         * SitemapManager has already routed with the old one, and it cannot
         * hook bfst_register_head_renderers after HeadManager has already fired
         * it. Add-ons boot from here — Pro registers its sitemap subclasses
         * directly and hooks bfst_register_head_renderers for its renderers,
         * which HeadManager fires moments later once ours are in place.
         *
         * This does not fire at all when the base plugin is inactive, so an
         * add-on must ALSO check the dependency on plugins_loaded if it wants
         * to explain itself to the user.
         *
         * @since 1.1.0
         */
        do_action( 'bfst_loaded' );

        HeadManager::register();
        SitemapManager::register();

        if ( is_admin() ) {
            SettingsPage::register();
            FeatureToggleEndpoint::register();
            ProUpsell::register();
        }
    }
}
