<?php
/**
 * Base case: fresh stand-ins and fresh static state in every test.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Tests;

use BlueFairy\SeoToolkit\ConflictDetector;
use BlueFairy\SeoToolkit\Head\HeadManager;
use BlueFairy\SeoToolkit\PageContext;
use BlueFairy\SeoToolkit\Sitemap\PostTypeSitemap;
use BlueFairy\SeoToolkit\Sitemap\SitemapIndex;
use BlueFairy\SeoToolkit\Sitemap\SitemapManager;

abstract class TestCase extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		bfst_wp_reset();
		self::set_static( ConflictDetector::class, 'detected', null );
		self::set_static( HeadManager::class, 'renderers', [] );
		self::set_static( HeadManager::class, 'registered', false );
		self::set_static( HeadManager::class, 'features_cache', null );
		self::set_static( PageContext::class, 'instance', null );
		self::set_static(
			SitemapManager::class,
			'handlers',
			[
				'index'     => SitemapIndex::class,
				'post_type' => PostTypeSitemap::class,
			]
		);
	}

	protected static function set_static( string $class_name, string $prop, mixed $value ): void {
		$r = new \ReflectionProperty( $class_name, $prop );
		$r->setValue( null, $value );
	}

	protected static function get_static( string $class_name, string $prop ): mixed {
		return ( new \ReflectionProperty( $class_name, $prop ) )->getValue();
	}

	/** Output of a callable. */
	protected static function capture( callable $fn ): string {
		ob_start();
		try {
			$fn();
		} finally {
			$out = (string) ob_get_clean();
		}
		return $out;
	}

	/** The callbacks registered on a hook (actions and filters). */
	protected static function hooked( string $hook ): array {
		return array_column( array_merge( $GLOBALS['bfst_wp']['actions'][ $hook ] ?? [], $GLOBALS['bfst_wp']['filters'][ $hook ] ?? [] ), 'cb' );
	}

	/**
	 * Run a PHP snippet in a fresh process with the bootstrap loaded (for code
	 * that calls exit). Returns [stdout, exit status].
	 *
	 * @return array{0: string, 1: int}
	 */
	protected static function run_isolated( string $code ): array {
		$script = sys_get_temp_dir() . '/bfst-iso-' . getmypid() . '-' . bin2hex( random_bytes( 4 ) ) . '.php';
		file_put_contents( $script, "<?php\nrequire " . var_export( dirname( __DIR__ ) . '/tests/bootstrap.php', true ) . ";\n" . $code );
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' 2>&1', $out, $status );
		unlink( $script );
		return [ implode( "\n", $out ), $status ];
	}
}
