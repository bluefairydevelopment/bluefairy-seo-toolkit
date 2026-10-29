#!/usr/bin/env php
<?php
/**
 * Every method in includes/ must have run under the unit suite.
 *
 * Measured, not inferred: the unit suite runs under pcov and a method counts
 * as covered only when at least one of its executable lines ran. Methods are
 * identified by namespace, class and line range from the PHP tokenizer, so
 * two classes with a same-named method are two methods: testing one says
 * nothing about the other. (The previous version of this tool matched method
 * NAMES in the test source, which passed an untested `B::render()` as soon as
 * any test called some `->render(`.)
 *
 * Every method with a body counts: public, protected, private, constructors,
 * and functions declared outside a class. Abstract and interface methods have
 * no body and are not counted. Every executable line under the tree is a
 * gate as well: exit 1 when any line did not run, naming file:line. Exit 1 when a method did not run, when the scan
 * saw nothing, when the coverage holds no data for includes/, or when pcov is
 * not available (no measurement is a failure, not a pass).
 *
 *   php bin/method-coverage.php                    # run the unit suite under pcov, then report
 *   php bin/method-coverage.php --coverage F.php   # report from an existing `phpunit --coverage-php F.php`
 *   php bin/method-coverage.php --root DIR         # measure another plugin tree (DIR/phpunit.xml[.dist], DIR/includes)
 *   php bin/method-coverage.php --self-check       # must fail a planted untested same-named method, and more
 *
 * Needs the pcov extension (installed on the test rig, off by default; this
 * tool switches it on for the run it starts).
 *
 * @package BlueFairy
 */

declare(strict_types=1);

// phpcs:ignoreFile -- developer tooling, not shipped.

$bfcov_tool_root = dirname( __DIR__ );
$bfcov_root      = $bfcov_tool_root;
$bfcov_coverage  = null;
foreach ( $argv as $i => $arg ) {
	if ( '--root' === $arg && isset( $argv[ $i + 1 ] ) ) {
		$bfcov_root = rtrim( $argv[ $i + 1 ], '/' );
	}
	if ( '--coverage' === $arg && isset( $argv[ $i + 1 ] ) ) {
		$bfcov_coverage = $argv[ $i + 1 ];
	}
}

/**
 * Every function with a body under $dir, keyed "Namespace\Class::method" (or
 * "Namespace\function()" outside a class), with its file and line range.
 *
 * @return array<string, array{file: string, start: int, end: int, visibility: string}>
 */
function bfcov_methods( string $dir ): array {
	$out = [];
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	$files = [];
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( $file instanceof SplFileInfo && 'php' === $file->getExtension() ) {
			$files[] = (string) realpath( $file->getPathname() );
		}
	}
	sort( $files );
	foreach ( $files as $path ) {
		$out += bfcov_methods_in_file( $path );
	}
	ksort( $out );
	return $out;
}

/**
 * @return array<string, array{file: string, start: int, end: int, visibility: string}>
 */
function bfcov_methods_in_file( string $path ): array {
	$tokens    = token_get_all( (string) file_get_contents( $path ) );
	$out       = [];
	$namespace = '';
	$depth     = 0;
	$scopes    = []; // Stack of [ name, depth at which its body opened ]; name '' = anonymous class.
	$pending   = null; // Class name waiting for its opening brace.
	$functions = []; // Stack of [ key, depth at which its body opened ].
	$fn_wait   = null; // Function waiting for its body ({) or its end (; = abstract).
	$count     = count( $tokens );
	for ( $i = 0; $i < $count; $i++ ) {
		$t = $tokens[ $i ];
		$id = is_array( $t ) ? $t[0] : null;
		if ( T_NAMESPACE === $id ) {
			$ns = '';
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$u = $tokens[ $j ];
				if ( is_array( $u ) && in_array( $u[0], [ T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR ], true ) ) {
					$ns .= $u[1];
				} elseif ( ';' === $u || '{' === $u ) {
					break;
				}
			}
			$namespace = $ns;
			continue;
		}
		if ( in_array( $id, [ T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM ], true ) ) {
			// `Foo::class` is not a declaration.
			$prev = $i - 1;
			while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
				--$prev;
			}
			if ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_DOUBLE_COLON === $tokens[ $prev ][0] ) {
				continue;
			}
			$anonymous = $prev >= 0 && is_array( $tokens[ $prev ] ) && T_NEW === $tokens[ $prev ][0];
			$name      = '';
			if ( ! $anonymous ) {
				for ( $j = $i + 1; $j < $count; $j++ ) {
					if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
						$name = ( '' === $namespace ? '' : $namespace . '\\' ) . $tokens[ $j ][1];
						break;
					}
				}
			}
			$pending = $anonymous ? '' : $name;
			continue;
		}
		if ( T_FUNCTION === $id ) {
			// A named function: the next non-space token (after an optional &) is its name.
			$j = $i + 1;
			while ( $j < $count && ( ( is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) || '&' === $tokens[ $j ] || ( is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], [ T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG ], true ) ) ) ) {
				++$j;
			}
			// A method may be named with a reserved word (list, print, match, new): any identifier-shaped token counts.
			if ( $j < $count && is_array( $tokens[ $j ] ) && 1 === preg_match( '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/', $tokens[ $j ][1] ) && [] === $functions ) {
				$visibility = 'public';
				for ( $k = $i - 1; $k >= 0; $k-- ) {
					$u = $tokens[ $k ];
					if ( ! is_array( $u ) || ! in_array( $u[0], [ T_WHITESPACE, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT, T_COMMENT, T_DOC_COMMENT ], true ) ) {
						break;
					}
					if ( T_PRIVATE === $u[0] ) {
						$visibility = 'private';
					} elseif ( T_PROTECTED === $u[0] ) {
						$visibility = 'protected';
					}
				}
				$scope = [] === $scopes ? null : $scopes[ count( $scopes ) - 1 ][0];
				if ( null === $scope ) {
					$key = ( '' === $namespace ? '' : $namespace . '\\' ) . $tokens[ $j ][1] . '()';
				} elseif ( '' === $scope ) {
					$key = 'class@anonymous(' . basename( $path ) . ':' . $t[2] . ')::' . $tokens[ $j ][1];
				} else {
					$key = $scope . '::' . $tokens[ $j ][1];
				}
				$fn_wait = [ $key, $t[2], $visibility ];
			}
			continue;
		}
		if ( '{' === $t || ( is_array( $t ) && in_array( $id, [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ], true ) ) ) {
			++$depth;
			if ( '{' === $t && null !== $pending ) {
				$scopes[] = [ $pending, $depth ];
				$pending  = null;
			} elseif ( '{' === $t && null !== $fn_wait ) {
				$functions[] = [ $fn_wait, $depth ];
				$fn_wait     = null;
			}
			continue;
		}
		if ( ';' === $t && null !== $fn_wait ) {
			$fn_wait = null; // Abstract or interface method: no body.
			continue;
		}
		if ( '}' === $t ) {
			$line = bfcov_line_of( $tokens, $i );
			if ( [] !== $functions && $functions[ count( $functions ) - 1 ][1] === $depth ) {
				[ $fn ] = array_pop( $functions );
				$out[ $fn[0] ] = [ 'file' => $path, 'start' => $fn[1], 'end' => $line, 'visibility' => $fn[2] ];
			} elseif ( [] !== $scopes && $scopes[ count( $scopes ) - 1 ][1] === $depth ) {
				array_pop( $scopes );
			}
			--$depth;
		}
	}
	return $out;
}

/** The line a bare token (no line number of its own) sits on. */
function bfcov_line_of( array $tokens, int $index ): int {
	$line = 1;
	for ( $k = $index - 1; $k >= 0; $k-- ) {
		if ( is_array( $tokens[ $k ] ) ) {
			return $tokens[ $k ][2] + substr_count( $tokens[ $k ][1], "\n" );
		}
	}
	return $line;
}

/**
 * Line coverage from a `phpunit --coverage-php` file: file => line => list of tests that ran it.
 *
 * @return array<string, array<int, array<int, string>|null>>
 */
function bfcov_line_coverage( string $file, string $autoload ): array {
	if ( ! is_file( $file ) ) {
		throw new RuntimeException( "no coverage file at {$file}" );
	}
	require_once $autoload;
	$coverage = include $file;
	if ( ! is_object( $coverage ) || ! method_exists( $coverage, 'getData' ) ) {
		throw new RuntimeException( "{$file} is not a php-code-coverage report" );
	}
	return $coverage->getData()->lineCoverage();
}

/**
 * Methods whose executable lines never ran. A method with no executable line
 * at all is reported too: it cannot be shown to have run.
 *
 * @param array<string, array{file: string, start: int, end: int, visibility: string}> $methods
 * @param array<string, array<int, array<int, string>|null>>                         $lines
 * @return array{missing: list<string>, unmeasured: list<string>}
 */
function bfcov_missing( array $methods, array $lines ): array {
	$missing    = [];
	$unmeasured = [];
	foreach ( $methods as $key => $m ) {
		$executable = 0;
		$ran        = false;
		foreach ( $lines[ $m['file'] ] ?? [] as $line => $tests ) {
			if ( $line < $m['start'] || $line > $m['end'] || null === $tests ) {
				continue;
			}
			++$executable;
			if ( [] !== $tests ) {
				$ran = true;
				break;
			}
		}
		$where = sprintf( '%s (%s:%d)', $key, $m['file'], $m['start'] );
		if ( $ran ) {
			continue;
		}
		if ( 0 === $executable && isset( $lines[ $m['file'] ] ) ) {
			$unmeasured[] = $where;
		} else {
			$missing[] = $where;
		}
	}
	return [ 'missing' => $missing, 'unmeasured' => $unmeasured ];
}

/** Run the root's unit suite under pcov, writing PHP coverage to $out. Returns phpunit's exit status. */
function bfcov_run_suite( string $root, string $tool_root, string $out ): int {
	$config = is_file( $root . '/phpunit.xml' ) ? $root . '/phpunit.xml' : $root . '/phpunit.xml.dist';
	$cmd    = implode(
		' ',
		[
			escapeshellarg( PHP_BINARY ),
			'-d pcov.enabled=1',
			'-d ' . escapeshellarg( 'pcov.directory=' . $root ),
			'-d memory_limit=1G',
			escapeshellarg( $tool_root . '/vendor/bin/phpunit' ),
			'-c ' . escapeshellarg( $config ),
			'--no-progress',
			'--coverage-php ' . escapeshellarg( $out ),
		]
	);
	passthru( $cmd, $status );
	return (int) $status;
}

/** The whole measurement for one tree; prints the report, returns the exit status. */
function bfcov_report( string $root, string $tool_root, ?string $coverage_file ): int {
	if ( null === $coverage_file && ! extension_loaded( 'pcov' ) ) {
		echo "method coverage: the pcov extension is not available to " . PHP_BINARY . " — nothing can be measured, so this is a failure, not a pass\n";
		return 1;
	}
	// Classes live in includes/ (admin screens under includes/Admin); a top-level admin/ is measured too if one appears.
	$methods = bfcov_methods( $root . '/includes' );
	if ( [] !== $methods && is_dir( $root . '/admin' ) ) {
		$methods = array_merge( $methods, bfcov_methods( $root . '/admin' ) );
	}
	if ( [] === $methods ) {
		echo "method coverage: nothing scanned (no methods under {$root}/includes) — that is a failure, not a pass\n";
		return 1;
	}
	$cleanup = false;
	if ( null === $coverage_file ) {
		$coverage_file = tempnam( sys_get_temp_dir(), 'bfcov' ) . '.php';
		$cleanup       = true;
		$status        = bfcov_run_suite( $root, $tool_root, $coverage_file );
		if ( 0 !== $status ) {
			echo "method coverage: the unit suite failed (exit {$status}); coverage from a failing suite is not counted\n";
			@unlink( $coverage_file );
			return 1;
		}
	}
	try {
		$lines = bfcov_line_coverage( $coverage_file, $tool_root . '/vendor/autoload.php' );
	} catch ( Throwable $e ) {
		echo 'method coverage: ' . $e->getMessage() . "\n";
		return 1;
	} finally {
		if ( $cleanup ) {
			@unlink( $coverage_file );
		}
	}
	$measured_files = array_intersect( array_unique( array_column( $methods, 'file' ) ), array_keys( $lines ) );
	if ( [] === $measured_files ) {
		echo "method coverage: the coverage data has no line for any file under {$root}/includes — was pcov on, and is includes/ in phpunit's <source>? A failure, not a pass\n";
		return 1;
	}
	$result  = bfcov_missing( $methods, $lines );
	$total   = count( $methods );
	$bad     = count( $result['missing'] ) + count( $result['unmeasured'] );
	$files   = count( array_unique( array_column( $methods, 'file' ) ) );
	$exec    = 0;
	$hit     = 0;
	foreach ( $lines as $file => $per_line ) {
		if ( ! str_starts_with( (string) $file, $root . '/' ) && ! str_starts_with( (string) $file, (string) realpath( $root ) . '/' ) ) {
			continue;
		}
		foreach ( $per_line as $tests ) {
			if ( null !== $tests ) {
				++$exec;
				$hit += [] === $tests ? 0 : 1;
			}
		}
	}
	printf( "%d methods/functions with a body in %d files under includes/ + admin/ (measured by pcov line coverage)\n", $total, $files );
	$status = 0;
	if ( 0 === $bad ) {
		printf( "method coverage: %d/%d methods ran under tests/Unit\n", $total, $total );
	} else {
		$status = 1;
		printf( "method coverage: %d/%d ran; NOT RUN:\n", $total - $bad, $total );
		foreach ( $result['missing'] as $m ) {
			echo "  - {$m}\n";
		}
		foreach ( $result['unmeasured'] as $m ) {
			echo "  - {$m} [no executable line recorded]\n";
		}
	}
	// An ignored line is not measured, so every one is named: an exclusion
	// must be visible in the report, never silent.
	$ignored = bfcov_ignored_lines( $root );
	printf( "excluded from line coverage: %d line(s) marked @codeCoverageIgnore%s\n", count( $ignored ), [] === $ignored ? '' : ': ' . implode( ', ', $ignored ) );
	// Lines are a gate too: a method can run while a branch inside it never does.
	if ( $hit === $exec && $exec > 0 ) {
		printf( "line coverage: %d/%d executable lines ran (100%%)\n", $hit, $exec );
		return $status;
	}
	printf( "line coverage: %d/%d executable lines ran (%.1f%%); NOT RUN:\n", $hit, $exec, $exec > 0 ? 100 * $hit / $exec : 0 );
	foreach ( bfcov_unrun_lines( $lines, $root ) as $file => $numbers ) {
		echo "  - {$file}:" . implode( ',', $numbers ) . "\n";
	}
	return 1;
}

/**
 * Every line under includes/ and admin/ carrying @codeCoverageIgnore, as file:line.
 *
 * @return list<string>
 */
function bfcov_ignored_lines( string $root ): array {
	$out = [];
	foreach ( [ 'includes', 'admin' ] as $sub ) {
		if ( ! is_dir( $root . '/' . $sub ) ) {
			continue;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $sub, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( ! $file instanceof SplFileInfo || 'php' !== $file->getExtension() ) {
				continue;
			}
			foreach ( (array) file( $file->getPathname() ) as $i => $line ) {
				if ( str_contains( (string) $line, '@codeCoverageIgnore' ) && ! str_contains( (string) $line, '@codeCoverageIgnoreEnd' ) ) {
					$out[] = substr( $file->getPathname(), strlen( $root ) + 1 ) . ':' . ( $i + 1 );
				}
			}
		}
	}
	sort( $out );
	return $out;
}

/**
 * Executable lines under $root that never ran, by file.
 *
 * @param array<string, array<int, array<int, string>|null>> $lines
 * @return array<string, list<int>>
 */
function bfcov_unrun_lines( array $lines, string $root ): array {
	$out = [];
	foreach ( $lines as $file => $per_line ) {
		if ( ! str_starts_with( (string) $file, $root . '/' ) && ! str_starts_with( (string) $file, (string) realpath( $root ) . '/' ) ) {
			continue;
		}
		foreach ( $per_line as $line => $tests ) {
			if ( [] === $tests ) {
				$out[ (string) $file ][] = (int) $line;
			}
		}
	}
	ksort( $out );
	return $out;
}

/**
 * Proves the instrument can fail. Builds a scratch plugin whose test runs
 * Fx\Alpha::render() and names 'render' in a string, while Fx\Beta::render()
 * (same name, other class) and Fx\Beta::helper() never run — the old
 * name-matching tool passed exactly this.
 */
function bfcov_self_check( string $tool_root ): int {
	$ok      = true;
	$say     = static function ( bool $pass, string $what ) use ( &$ok ): void {
		$ok = $ok && $pass;
		echo ( $pass ? '  ok   ' : '  FAIL ' ) . $what . "\n";
	};
	$scratch = sys_get_temp_dir() . '/bfcov-self-' . getmypid();
	@mkdir( $scratch . '/includes/Sub', 0777, true );
	@mkdir( $scratch . '/tests/Unit', 0777, true );
	file_put_contents( $scratch . '/includes/Alpha.php', "<?php\nnamespace Fx;\nfinal class Alpha {\n\tpublic function render(): string {\n\t\tif ( func_num_args() > 5 ) {\n\t\t\treturn 'z';\n\t\t}\n\t\treturn 'a';\n\t}\n}\n" );
	file_put_contents( $scratch . '/includes/Sub/Beta.php', "<?php\nnamespace Fx;\nabstract class Base { abstract public function shape(): string; }\ninterface Shows { public function show(): void; }\nclass Beta extends Base {\n\tpublic function render(): string {\n\t\treturn \$this->helper();\n\t}\n\tprivate function helper(): string {\n\t\t\$f = function () { return 'b'; };\n\t\treturn \$f();\n\t}\n\tpublic function shape(): string { return Beta::class; }\n\tpublic function list(): array { return []; }\n}\nfunction loose(): int {\n\treturn 1;\n}\n" );
	file_put_contents( $scratch . '/tests/bootstrap.php', "<?php\nrequire __DIR__ . '/../includes/Alpha.php';\nrequire __DIR__ . '/../includes/Sub/Beta.php';\n" );
	file_put_contents( $scratch . '/tests/Unit/AlphaTest.php', "<?php\nuse PHPUnit\\Framework\\TestCase;\nfinal class AlphaTest extends TestCase {\n\tpublic function test_render(): void {\n\t\t\$this->assertSame( 'a', ( new Fx\\Alpha() )->render() );\n\t\t\$this->assertSame( 'shape', 'shape' );\n\t\t\$this->assertSame( 1, \\Fx\\loose() );\n\t\t\$cb = [ 'x', 'helper' ];\n\t\t\$this->assertCount( 2, \$cb );\n\t}\n}\n" );
	file_put_contents( $scratch . '/phpunit.xml', "<?xml version=\"1.0\"?>\n<phpunit bootstrap=\"tests/bootstrap.php\" cacheDirectory=\".phpunit.cache\">\n<testsuites><testsuite name=\"u\"><directory suffix=\"Test.php\">tests/Unit</directory></testsuite></testsuites>\n<source><include><directory>includes/</directory></include></source>\n</phpunit>\n" );

	// 1. The scanner: namespaced, class-qualified keys; private + closures + loose functions; no body = not counted.
	$methods = bfcov_methods( $scratch . '/includes' );
	$say( [ 'Fx\\Alpha::render', 'Fx\\Beta::helper', 'Fx\\Beta::list', 'Fx\\Beta::render', 'Fx\\Beta::shape', 'Fx\\loose()' ] === array_keys( $methods ), 'scanner: ' . implode( ', ', array_keys( $methods ) ) );

	// 2. The planted case: the run must exit 1 and name Beta::render and Beta::helper, and nothing that ran.
	ob_start();
	$status = bfcov_report( (string) realpath( $scratch ), $tool_root, null );
	$report = (string) ob_get_clean();
	$say( 1 === $status, "planted untested same-named method: exit {$status} (want 1)" );
	$say( str_contains( $report, '  - Fx\\Beta::render (' ) && str_contains( $report, '  - Fx\\Beta::helper (' ) && str_contains( $report, '  - Fx\\Beta::shape (' ) && str_contains( $report, '  - Fx\\Beta::list (' ), 'report names Fx\\Beta::render, ::helper, ::shape and ::list as not run' );
	$say( ! str_contains( $report, 'Fx\\Alpha::render (' ) && ! str_contains( $report, 'Fx\\loose() (' ), 'report does not name the methods that ran' );
	$say( str_contains( $report, 'method coverage: 2/6 ran' ), 'count: 2/6 ran' );

	// 3. Cure the plant: a test that runs Beta::render (and through it helper) and shape → exit 0.
	file_put_contents( $scratch . '/tests/Unit/BetaTest.php', "<?php\nuse PHPUnit\\Framework\\TestCase;\nfinal class BetaTest extends TestCase {\n\tpublic function test_render(): void {\n\t\t\$this->assertSame( 'b', ( new Fx\\Beta() )->render() );\n\t\t\$this->assertSame( 'Fx\\\\Beta', ( new Fx\\Beta() )->shape() );\n\t\t\$this->assertSame( [], ( new Fx\\Beta() )->list() );\n\t}\n}\n" );
	ob_start();
	$status = bfcov_report( (string) realpath( $scratch ), $tool_root, null );
	$report = (string) ob_get_clean();
	$say( 1 === $status && str_contains( $report, 'method coverage: 6/6 methods ran' ) && str_contains( $report, 'Alpha.php:6' ), "every method ran but a planted branch line did not: exit {$status} (want 1), Alpha.php:6 named" );

	// 3b. Cure the line gap too → exit 0, every line.
	file_put_contents( $scratch . '/tests/Unit/AlphaBranchTest.php', "<?php\nuse PHPUnit\\Framework\\TestCase;\nfinal class AlphaBranchTest extends TestCase {\n\tpublic function test_branch(): void {\n\t\t\$this->assertSame( 'z', ( new Fx\\Alpha() )->render( 1, 2, 3, 4, 5, 6 ) );\n\t}\n}\n" );
	ob_start();
	$status = bfcov_report( (string) realpath( $scratch ), $tool_root, null );
	$report = (string) ob_get_clean();
	$say( 0 === $status && str_contains( $report, 'method coverage: 6/6 methods ran' ) && str_contains( $report, '(100%)' ), "cured plant passes: exit {$status}, 6/6 methods and 100% lines" );

	// 4. A failing suite is not counted.
	file_put_contents( $scratch . '/tests/Unit/RedTest.php', "<?php\nuse PHPUnit\\Framework\\TestCase;\nfinal class RedTest extends TestCase {\n\tpublic function test_red(): void {\n\t\t\$this->assertTrue( false );\n\t}\n}\n" );
	ob_start();
	$status = bfcov_report( (string) realpath( $scratch ), $tool_root, null );
	ob_end_clean();
	$say( 1 === $status, "a failing suite: exit {$status} (want 1)" );
	unlink( $scratch . '/tests/Unit/RedTest.php' );

	// 5. An empty tree is a failure, not a pass.
	@mkdir( $scratch . '/empty/includes', 0777, true );
	ob_start();
	$status = bfcov_report( $scratch . '/empty', $tool_root, null );
	ob_end_clean();
	$say( 1 === $status, "empty includes/: exit {$status} (want 1)" );

	// 6. No pcov is a failure, not a pass: the script, run with no extensions at all, must exit 1.
	exec( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( __FILE__ ) . ' --root ' . escapeshellarg( (string) realpath( $scratch ) ) . ' 2>&1', $no_pcov_out, $no_pcov_status );
	$say( 1 === $no_pcov_status && str_contains( implode( "\n", $no_pcov_out ), 'pcov' ), "without pcov: exit {$no_pcov_status} (want 1)" );

	exec( 'rm -rf ' . escapeshellarg( $scratch ) );
	echo $ok ? "self-check: planted untested same-named method and planted unrun branch line caught; failing suite, empty tree and missing pcov all fail\n" : "self-check: FAILED\n";
	return $ok ? 0 : 1;
}

if ( in_array( '--self-check', $argv, true ) ) {
	exit( bfcov_self_check( $bfcov_tool_root ) );
}
exit( bfcov_report( (string) realpath( $bfcov_root ), $bfcov_tool_root, $bfcov_coverage ) );
