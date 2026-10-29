<?php
/**
 * Stand-in for wp-admin/includes/plugin.php: not loaded by the bootstrap, so
 * the code that require_once's it when it is missing (the front end) is run.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

function is_plugin_active( string $file ): bool { return in_array( $file, $GLOBALS['bfst_wp']['active_plugins'], true ); }
function deactivate_plugins( string $file ): void { $GLOBALS['bfst_wp']['deactivated'][] = $file; }
