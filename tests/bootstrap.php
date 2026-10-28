<?php
/**
 * Bootstrap for the pure-unit suite: runs without WordPress, using the
 * stand-ins in tests/wp-stubs.php.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/fixtures/' );
defined( 'BFST_VERSION' ) || define( 'BFST_VERSION', '1.1.3' );
defined( 'BFST_PLUGIN_FILE' ) || define( 'BFST_PLUGIN_FILE', dirname( __DIR__ ) . '/bluefairy-seo-toolkit.php' );
defined( 'BFST_PLUGIN_DIR' ) || define( 'BFST_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
defined( 'BFST_PLUGIN_URL' ) || define( 'BFST_PLUGIN_URL', 'https://example.test/wp-content/plugins/bluefairy-seo-toolkit/' );
defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'BlueFairy\\SeoToolkit\\';
		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}
		$file = dirname( __DIR__ ) . '/includes/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once __DIR__ . '/wp-stubs.php';
require_once __DIR__ . '/TestCase.php';

bfst_wp_reset();
