<?php
/**
 * Orchestrates <head> output for all registered renderers.
 *
 * @package BlueFairy\SeoToolkit\Head
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Head;

use BlueFairy\SeoToolkit\ConflictDetector;
use BlueFairy\SeoToolkit\PageContext;

/**
 * Registers renderers on wp_head and routes the current PageContext to each.
 *
 * Renderers are a REGISTRY, not a hard-coded list. The free plugin registers
 * Open Graph; the Pro add-on registers Canonical and Schema by calling
 * add_renderer() from the bfst_register_head_renderers action. Without that
 * seam an add-on has no way onto wp_head with the conflict gate and the feature
 * toggle applied, and would have to re-implement both.
 *
 * Hook priorities (per design Decision Log #20):
 * - CanonicalRenderer: priority 2 (safely after core charset, before theme output)
 * - OpenGraphRenderer + SchemaRenderer: priority 10
 *
 * For each renderer, HeadManager:
 * 1. Checks the conflict gate (if any SEO plugin is active and the feature is
 *    not explicitly re-enabled, skip the renderer).
 * 2. Checks the settings feature toggle.
 * 3. Calls render(); skips if the return value is ''.
 *
 * bfst_features is loaded once per request via get_features() and cached in a
 * static property to avoid 6 get_option() calls (3 renderers × 2 checks).
 */
final class HeadManager {

    /**
     * Registered renderers, keyed by feature key.
     *
     * @var array<string, array{renderer: RendererInterface, priority: int}>
     */
    private static array $renderers = [];

    /**
     * Whether register() has already bound the renderers to wp_head.
     */
    private static bool $registered = false;

    /**
     * Per-request cache for bfst_features option.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $features_cache = null;

    /**
     * Register wp_head hooks for all renderers.
     * Also registers the PageContext initialiser on the wp hook.
     */
    public static function register(): void {
        // Binding twice would emit every tag twice. Cheap to guard, and the
        // guard is what makes the static renderer registry safe to keep.
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;

        PageContext::init();

        // Open Graph is the base plugin's own head output.
        self::add_renderer( new OpenGraphRenderer(), 10 );

        /**
         * Register or replace <head> renderers.
         *
         * Add-ons hook this and call HeadManager::add_renderer(). Renderers are
         * keyed by feature_key, so an add-on registering a SUBCLASS of one of
         * ours replaces it rather than producing the tags twice — which is how
         * the Pro add-on adds og:image, Twitter Cards and the article
         * timestamps without restating a single tag the base plugin already
         * renders.
         *
         * Fired before anything is bound to wp_head, so replacement works
         * regardless of the order add-ons load in.
         *
         * @since 1.1.0
         */
        do_action( 'bfst_register_head_renderers' );

        foreach ( self::$renderers as $entry ) {
            $renderer = $entry['renderer'];
            add_action(
                'wp_head',
                static function () use ( $renderer ): void {
                    self::maybe_render( $renderer );
                },
                $entry['priority']
            );
        }
    }

    /**
     * Register a renderer, or replace the one already holding its feature key.
     *
     * @param RendererInterface $renderer Renderer to run.
     * @param int               $priority wp_head priority. Canonical belongs at 2 —
     *                                    after the core charset, before theme output.
     */
    public static function add_renderer( RendererInterface $renderer, int $priority = 10 ): void {
        self::$renderers[ $renderer->feature_key() ] = [
            'renderer' => $renderer,
            'priority' => $priority,
        ];
    }

    /**
     * The renderer currently registered for a feature key, if any.
     *
     * @param string $feature_key
     * @return RendererInterface|null
     */
    public static function get_renderer( string $feature_key ): ?RendererInterface {
        return self::$renderers[ $feature_key ]['renderer'] ?? null;
    }

    /**
     * Whether a feature will actually render on this request.
     *
     * Public because a renderer sometimes has to suppress WordPress's own
     * output to avoid duplicating it — the Pro add-on removes core's
     * rel_canonical only when its own canonical renderer is really going to
     * run, so a disabled or conflict-blocked feature leaves core's tag alone.
     *
     * @since 1.1.0
     *
     * @param string $feature_key
     * @return bool
     */
    public static function will_render( string $feature_key ): bool {
        return ! self::is_blocked_by_conflict( $feature_key ) && self::is_feature_enabled( $feature_key );
    }

    /**
     * Decide whether to call a renderer and echo its output.
     *
     * Checks (in order):
     * 1. Conflict gate — if a known SEO plugin is active and this feature has
     *    not been explicitly re-enabled by the user, skip.
     * 2. Settings toggle — if the feature is disabled in bfst_features, skip.
     * 3. render() return value — if '' is returned, skip (nothing to output).
     *
     * @param RendererInterface $renderer
     */
    private static function maybe_render( RendererInterface $renderer ): void {
        $feature_key = $renderer->feature_key();

        // 1. Conflict gate.
        if ( self::is_blocked_by_conflict( $feature_key ) ) {
            return;
        }

        // 2. Settings toggle.
        if ( ! self::is_feature_enabled( $feature_key ) ) {
            return;
        }

        // 3. Get the PageContext (may be null if called outside the wp hook).
        $ctx = PageContext::current();
        if ( $ctx === null ) {
            return;
        }

        $html = $renderer->render( $ctx );

        // Skip empty output — renderer signals "nothing to output on this page".
        if ( $html === '' ) {
            return;
        }

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside each renderer
    }

    /**
     * Return the bfst_features option, cached for the lifetime of the request.
     *
     * @return array<string, mixed>
     */
    private static function get_features(): array {
        if ( self::$features_cache === null ) {
            $features             = get_option( 'bfst_features', [] );
            self::$features_cache = is_array( $features ) ? $features : [];
        }
        return self::$features_cache;
    }

    /**
     * Check whether a conflict plugin is blocking this feature.
     *
     * A feature is blocked when:
     * - At least one known SEO plugin is active, AND
     * - The per-feature override has NOT been saved as 'enabled' by the user.
     *
     * The per-feature override is stored inside bfst_features under a
     * 'conflict_override' sub-key, e.g.:
     * bfst_features['conflict_override']['schema'] = true  → not blocked
     *
     * @param string $feature_key
     * @return bool True if output should be suppressed.
     */
    private static function is_blocked_by_conflict( string $feature_key ): bool {
        if ( ! ConflictDetector::has_conflicts() ) {
            return false;
        }

        $features = self::get_features();

        $overrides = $features['conflict_override'] ?? [];
        if ( ! is_array( $overrides ) ) {
            return true;
        }

        // If the user has explicitly re-enabled this feature via the conflict
        // notice toggle, allow it through.
        return empty( $overrides[ $feature_key ] );
    }

    /**
     * Check whether a feature is enabled in plugin settings.
     *
     * @param string $feature_key
     * @return bool
     */
    private static function is_feature_enabled( string $feature_key ): bool {
        $features = self::get_features();
        // Missing key treated as enabled (default on).
        return isset( $features[ $feature_key ] ) ? (bool) $features[ $feature_key ] : true;
    }
}
