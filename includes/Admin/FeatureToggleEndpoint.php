<?php
/**
 * REST API endpoint for toggling per-feature conflict overrides.
 *
 * @package BlueFairy\SeoToolkit\Admin
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Admin;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Registers and handles the feature-toggle REST endpoint.
 *
 * Endpoint: POST /wp-json/bluefairy-seo/v1/feature-toggle
 *
 * Allows toggling individual feature conflict overrides without a full page
 * reload. Used by the conflict notice on Settings > SEO.
 *
 * Authentication: cookie + X-WP-Nonce (standard WP admin auth).
 * Authorization: current_user_can('manage_options').
 *
 * Replaces admin-ajax.php per Decision Log #19.
 */
final class FeatureToggleEndpoint {

    /**
     * REST API namespace.
     */
    private const NAMESPACE = 'bluefairy-seo/v1';

    /**
     * REST API route.
     */
    private const ROUTE = '/feature-toggle';

    /**
     * Feature keys this endpoint will accept.
     *
     * Read from SettingsPage rather than frozen here. This used to be a literal
     * list containing 'schema' and 'canonical' — two features the free plugin
     * does not have — which happened to work only because it was a superset of
     * what the settings page rendered. Any add-on registering a new feature got
     * a conflict toggle that rendered and then 400'd on click.
     *
     * @return string[]
     */
    private static function feature_keys(): array {
        return array_keys( SettingsPage::feature_labels() );
    }

    /**
     * Register the REST route.
     */
    public static function register(): void {
        add_action( 'rest_api_init', [ self::class, 'register_route' ] );
    }

    /**
     * Register the REST route definition.
     * Called on rest_api_init.
     */
    public static function register_route(): void {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ self::class, 'handle' ],
                'permission_callback' => static fn() => current_user_can( 'manage_options' ),
                'args'                => [
                    'feature' => [
                        'type'              => 'string',
                        'required'          => true,
                        'enum'              => self::feature_keys(),
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => static fn( $val ) => in_array( $val, self::feature_keys(), true ),
                        'description'       => 'The feature to toggle. Values come from the registered feature list.',
                    ],
                    'enabled' => [
                        'type'        => 'boolean',
                        'required'    => true,
                        'description' => 'Whether to enable (true) or disable (false) this feature override.',
                    ],
                ],
            ]
        );
    }

    /**
     * Handle the feature-toggle request.
     *
     * Reads the current bfst_features option, updates the conflict_override
     * sub-key for the specified feature, and saves it back.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle( WP_REST_Request $request ): WP_REST_Response {
        $feature = (string) $request->get_param( 'feature' );
        $enabled = (bool) $request->get_param( 'enabled' );

        // Validate feature key (belt-and-suspenders beyond REST schema enum).
        if ( ! in_array( $feature, self::feature_keys(), true ) ) {
            return new WP_REST_Response(
                [ 'success' => false, 'message' => 'Invalid feature key.' ],
                400
            );
        }

        // Load current options.
        $features = get_option( 'bfst_features', [] );
        if ( ! is_array( $features ) ) {
            $features = [];
        }

        // Update the conflict override sub-key.
        if ( ! isset( $features['conflict_override'] ) || ! is_array( $features['conflict_override'] ) ) {
            $features['conflict_override'] = [];
        }
        $features['conflict_override'][ $feature ] = $enabled;

        $updated = update_option( 'bfst_features', $features );

        return new WP_REST_Response(
            [
                'success' => true,
                'feature' => $feature,
                'enabled' => $enabled,
                'updated' => $updated,
            ],
            200
        );
    }
}
