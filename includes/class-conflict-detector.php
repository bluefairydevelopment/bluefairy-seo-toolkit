<?php
/**
 * Detects known SEO plugins that may conflict with BlueFairy SEO Toolkit Lite.
 *
 * Checks for the defining class or constant of each plugin rather than relying
 * on is_plugin_active(), which is not available on frontend requests by default.
 * Class/constant checks work as soon as all plugins have loaded (plugins_loaded),
 * which is exactly when Plugin::boot() runs.
 *
 * Result is cached in a static property so the check happens only once per request.
 *
 * @package BlueFairy_SEO_Toolkit_Lite
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Static conflict detector for known SEO plugins.
 *
 * Known plugins and their detection signatures:
 * - Yoast SEO        : class WPSEO_Options
 * - Rank Math        : class RankMath
 * - SEOPress         : constant SEOPRESS_VERSION
 * - The SEO Framework: class The_SEO_Framework\Load
 */
final class BlueFairy_SEO_Toolkit_Lite_Conflict_Detector {

    /**
     * Map of friendly plugin names to their detection signatures.
     * Each entry is an array with 'type' => 'class'|'constant' and 'name' => the identifier.
     *
     * @var array<string, array{type: string, name: string}>
     */
    private const KNOWN_PLUGINS = [
        'Yoast SEO'         => [ 'type' => 'class',    'name' => 'WPSEO_Options' ],
        'Rank Math'         => [ 'type' => 'class',    'name' => 'RankMath' ],
        'SEOPress'          => [ 'type' => 'constant', 'name' => 'SEOPRESS_VERSION' ],
        'The SEO Framework' => [ 'type' => 'class',    'name' => 'The_SEO_Framework\\Load' ],
    ];

    /**
     * Cached list of detected (active) conflict plugin names.
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
     *
     * Must run after plugins_loaded so that all plugin classes and constants
     * are defined before we check for them.
     */
    public static function scan(): void {
        if ( self::$detected !== null ) {
            return;
        }

        self::$detected = [];

        foreach ( self::KNOWN_PLUGINS as $name => $signature ) {
            $detected = false;

            if ( $signature['type'] === 'class' ) {
                $detected = class_exists( $signature['name'], false );
            } elseif ( $signature['type'] === 'constant' ) {
                $detected = defined( $signature['name'] );
            }

            if ( $detected ) {
                self::$detected[] = $name;
            }
        }
    }

    /**
     * Return the list of active conflict plugin names.
     *
     * Triggers the scan if it has not run yet (safe for early calls after plugins_loaded).
     *
     * @return string[] Array of friendly plugin name strings, e.g. ['Yoast SEO'].
     */
    public static function detected(): array {
        if ( self::$detected === null ) {
            self::scan();
        }
        return self::$detected ?? [];
    }

    /**
     * Whether any conflicting plugin is active.
     *
     * @return bool
     */
    public static function has_conflicts(): bool {
        return ! empty( self::detected() );
    }
}
