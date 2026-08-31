<?php
/**
 * Settings > SEO admin page.
 *
 * @package BlueFairy\SeoToolkit\Admin
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Admin;

use BlueFairy\SeoToolkit\ConflictDetector;

/**
 * Registers and renders the Settings > SEO admin page.
 *
 * Three sections:
 * 1. General — Twitter handle, org name, org logo, local business address
 * 2. Features — per-feature toggles; conflict notice when conflicting plugin detected
 * 3. Advanced — sitemap exclusions; add-ons append their own fields
 *
 * Conflict notice is persistent (not dismissible) and scoped to this settings page only.
 * IMPORTANT: Do not make this notice globally dismissible or store dismissal per-user.
 * The coexistence use case depends on this configuration being always visible and
 * accessible. This is intentional design. See Decision Log #3.
 */
final class SettingsPage {

    /**
     * Every toggleable feature, the base plugin's plus any add-on's.
     *
     * Single source for the checkbox list AND the sanitizer. If the sanitizer
     * iterated only the base keys, an add-on's toggle would be dropped on every
     * save — the checkbox would appear to work and revert on reload.
     *
     * @return array<string, string> Feature key => human-readable label.
     */
    public static function feature_labels(): array {
        $labels = [
            'og'      => __( 'Open Graph tags', 'bluefairy-seo-toolkit' ),
            'sitemap' => __( 'XML Sitemaps', 'bluefairy-seo-toolkit' ),
        ];

        /**
         * Filter the list of toggleable features.
         *
         * @since 1.1.0
         *
         * @param array<string, string> $labels Feature key => label.
         */
        return (array) apply_filters( 'bfst_feature_labels', $labels );
    }

    /**
     * Register settings page hooks.
     */
    public static function register(): void {
        add_action( 'admin_menu', [ self::class, 'add_page' ] );
        add_action( 'admin_init', [ self::class, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_scripts' ] );
    }

    /**
     * Add the settings page under Settings.
     */
    public static function add_page(): void {
        add_options_page(
            __( 'SEO Toolkit', 'bluefairy-seo-toolkit' ),
            __( 'SEO', 'bluefairy-seo-toolkit' ),
            'manage_options',
            'bfst-seo',
            [ self::class, 'render_page' ]
        );
    }

    /**
     * Enqueue JS for the conflict notice AJAX toggles.
     *
     * @param string $hook_suffix
     */
    public static function enqueue_scripts( string $hook_suffix ): void {
        if ( $hook_suffix !== 'settings_page_bfst-seo' ) {
            return;
        }

        wp_enqueue_script( 'wp-api' );

        wp_enqueue_script(
            'bfst-settings',
            plugins_url( '../../assets/js/settings.js', __FILE__ ),
            [ 'jquery', 'wp-api' ],
            BFST_VERSION,
            true
        );

        wp_localize_script(
            'bfst-settings',
            'bfstSettings',
            [
                'root'            => esc_url_raw( rest_url() ),
                'nonce'           => wp_create_nonce( 'wp_rest' ),
                'savingText'      => __( 'Saving…', 'bluefairy-seo-toolkit' ),
                'savedText'       => __( 'Saved', 'bluefairy-seo-toolkit' ),
                'errorText'       => __( 'Error saving', 'bluefairy-seo-toolkit' ),
            ]
        );
    }

    /**
     * Register all settings and their sanitize callbacks.
     */
    public static function register_settings(): void {
        // -- Features section --
        add_settings_section( 'bfst_features_section', __( 'Features', 'bluefairy-seo-toolkit' ),
            [ self::class, 'render_conflict_notice' ], 'bfst-seo' );

        register_setting( 'bfst_options', 'bfst_features', [
            'sanitize_callback' => [ self::class, 'sanitize_features' ],
            'default'           => [
                'og'      => true,
                'sitemap' => true,
            ],
        ] );
        add_settings_field( 'bfst_features_field', __( 'Active Features', 'bluefairy-seo-toolkit' ),
            [ self::class, 'render_field_features' ], 'bfst-seo', 'bfst_features_section' );

        // -- Advanced section (autoload=false) --
        add_settings_section( 'bfst_advanced', __( 'Advanced', 'bluefairy-seo-toolkit' ), '__return_null', 'bfst-seo' );

        register_setting( 'bfst_options', 'bfst_sitemap_exclusions', [
            'sanitize_callback' => [ self::class, 'sanitize_sitemap_exclusions' ],
            'default'           => [],
        ] );
        add_settings_field( 'bfst_sitemap_exclusions', __( 'Sitemap Exclusions', 'bluefairy-seo-toolkit' ),
            [ self::class, 'render_field_sitemap_exclusions' ], 'bfst-seo', 'bfst_advanced' );



        /**
         * Register additional settings sections and fields.
         *
         * Add-ons add to the same 'bfst-seo' page and 'bfst_options' group, so
         * their fields save through the one Settings API form the base plugin
         * already renders. The Pro add-on registers its General section
         * (organisation, logo, address, Twitter handle) and its canonical and
         * schema advanced fields here.
         *
         * @since 1.1.0
         */
        do_action( 'bfst_register_settings' );

        // Ensure advanced options are not autoloaded.
        // update_option fourth arg 'autoload' is not available via register_setting;
        // advanced options are set with autoload=false on first activation.
        // The autoload value is only set by add_option/update_option; register_setting
        // does not override it.
    }

    /**
     * Render the full settings page.
     */
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'bluefairy-seo-toolkit' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SEO Toolkit', 'bluefairy-seo-toolkit' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'bfst_options' );
                do_settings_sections( 'bfst-seo' );
                submit_button();
                ?>
            </form>
            <?php
            /**
             * Render below the settings form.
             *
             * @since 1.1.0
             */
            do_action( 'bfst_settings_page_footer' );
            ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Field renderers
    // -------------------------------------------------------------------------





    /**
     * Render the conflict notice in the Features section header.
     * Called as the section callback — fires before the feature fields.
     *
     * IMPORTANT: This notice is persistent and not dismissible by design.
     * See Decision Log #3 and the note in the class docblock.
     */
    public static function render_conflict_notice(): void {
        $conflicts = ConflictDetector::detected();
        if ( empty( $conflicts ) ) {
            return;
        }

        $plugin_labels = [
            'yoast'             => 'Yoast SEO',
            'rankmath'          => 'Rank Math',
            'seopress'          => 'SEOPress',
            'the-seo-framework' => 'The SEO Framework',
        ];

        $detected_names = array_map(
            static fn( $slug ) => $plugin_labels[ $slug ] ?? $slug,
            $conflicts
        );

        $features = get_option( 'bfst_features', [] );
        $overrides = is_array( $features ) && isset( $features['conflict_override'] )
            ? (array) $features['conflict_override']
            : [];

        ?>
        <div class="notice notice-warning" style="margin-left:0; padding:12px 16px;">
            <p>
                <strong><?php esc_html_e( 'SEO Plugin Conflict Detected', 'bluefairy-seo-toolkit' ); ?></strong><br>
                <?php
                printf(
                    /* translators: %s: comma-separated list of plugin names */
                    esc_html__( 'The following SEO plugin(s) are active: %s. All BlueFairy SEO Toolkit features are paused by default. Use the toggles below to re-enable individual features for coexistence.', 'bluefairy-seo-toolkit' ),
                    '<strong>' . esc_html( implode( ', ', $detected_names ) ) . '</strong>'
                );
                ?>
            </p>
            <table>
                <tbody>
                <?php foreach ( self::feature_labels() as $feature_key => $feature_label ) : ?>
                    <tr>
                        <td style="padding: 4px 12px 4px 0;">
                            <strong><?php echo esc_html( $feature_label ); ?></strong>
                        </td>
                        <td>
                            <label class="bfst-conflict-toggle" style="cursor:pointer;">
                                <input type="checkbox"
                                       class="bfst-feature-override-toggle"
                                       data-feature="<?php echo esc_attr( $feature_key ); ?>"
                                       <?php checked( ! empty( $overrides[ $feature_key ] ) ); ?>>
                                <?php esc_html_e( 'Enable despite conflict', 'bluefairy-seo-toolkit' ); ?>
                            </label>
                            <span class="bfst-toggle-status" style="margin-left:8px; color:#666; font-size:12px;"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the features enable/disable checkboxes.
     */
    public static function render_field_features(): void {
        $features = get_option( 'bfst_features', [] );
        if ( ! is_array( $features ) ) {
            $features = [];
        }

        foreach ( self::feature_labels() as $key => $label ) {
            $enabled = isset( $features[ $key ] ) ? (bool) $features[ $key ] : true;
            echo '<label style="display:block; margin-bottom:6px;">';
            echo '<input type="checkbox" name="bfst_features[' . esc_attr( $key ) . ']" value="1" ' . checked( $enabled, true, false ) . '> ';
            echo esc_html( $label );
            echo '</label>';
        }
    }

    /**
     * Render the sitemap exclusions field.
     */
    public static function render_field_sitemap_exclusions(): void {
        $exclusions = get_option( 'bfst_sitemap_exclusions', [] );
        if ( ! is_array( $exclusions ) ) {
            $exclusions = [];
        }

        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        $taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );

        echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Post type exclusions', 'bluefairy-seo-toolkit' ) . '</legend>';
        echo '<strong>' . esc_html__( 'Post Types', 'bluefairy-seo-toolkit' ) . '</strong><br>';
        foreach ( $post_types as $pt ) {
            $checked = ! empty( $exclusions[ $pt->name ] );
            echo '<label style="display:block; margin:4px 0;">';
            echo '<input type="checkbox" name="bfst_sitemap_exclusions[' . esc_attr( $pt->name ) . ']" value="1" ' . checked( $checked, true, false ) . '> ';
            echo esc_html( $pt->label );
            echo ' <code>(' . esc_html( $pt->name ) . ')</code></label>';
        }
        echo '<br><strong>' . esc_html__( 'Taxonomies', 'bluefairy-seo-toolkit' ) . '</strong><br>';
        foreach ( $taxonomies as $tax ) {
            $checked = ! empty( $exclusions[ $tax->name ] );
            echo '<label style="display:block; margin:4px 0;">';
            echo '<input type="checkbox" name="bfst_sitemap_exclusions[' . esc_attr( $tax->name ) . ']" value="1" ' . checked( $checked, true, false ) . '> ';
            echo esc_html( $tax->label );
            echo ' <code>(' . esc_html( $tax->name ) . ')</code></label>';
        }
        echo '</fieldset>';
    }



    // -------------------------------------------------------------------------
    // Sanitize callbacks
    // -------------------------------------------------------------------------

    /**
     * Sanitize the bfst_features option.
     *
     * @param mixed $input
     * @return array<string, bool|array<string, bool>>
     */
    public static function sanitize_features( $input ): array {
        if ( ! is_array( $input ) ) {
            $input = [];
        }

        $sanitized = [];
        foreach ( array_keys( self::feature_labels() ) as $key ) {
            $sanitized[ $key ] = isset( $input[ $key ] ) && (bool) $input[ $key ];
        }

        // Preserve conflict_override sub-key — only updated via REST endpoint.
        $existing = get_option( 'bfst_features', [] );
        if ( is_array( $existing ) && isset( $existing['conflict_override'] ) ) {
            $sanitized['conflict_override'] = $existing['conflict_override'];
        }

        return $sanitized;
    }

    /**
     * Sanitize the bfst_sitemap_exclusions option.
     *
     * @param mixed $input
     * @return array<string, bool>
     */
    public static function sanitize_sitemap_exclusions( $input ): array {
        if ( ! is_array( $input ) ) {
            return [];
        }

        $sanitized = [];
        foreach ( $input as $key => $value ) {
            $sanitized[ sanitize_key( (string) $key ) ] = (bool) $value;
        }

        return $sanitized;
    }


}
