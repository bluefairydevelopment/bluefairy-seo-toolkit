<?php
/**
 * Uninstall routine for BlueFairy SEO Toolkit.
 *
 * Removes all plugin options and post meta. Multisite-aware even though v1
 * targets single-site — avoids leaving debris if network-activated in future.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Every option this plugin creates, and nothing else. Installer::set_default_options()
// and this list must stay equal in both directions: an option created and not
// deleted is a data leak, and deleting one this plugin never created would take
// the Pro add-on's settings with it.
$bfst_option_keys = [
	'bfst_features',
	'bfst_sitemap_exclusions',
	'bfst_schema_version',
	'bfst_activated_at',
	'bfst_sitemap_cache_keys',
];

// The base plugin writes no post meta — the per-post SEO meta box belongs to
// the Pro add-on, which removes these keys in its own uninstall.
$bfst_meta_keys = [];

// Multisite awareness: page through EVERY site.
//
// This used to be get_sites( [ 'number' => 100 ] ), which silently left sites
// 101 and beyond holding all of this plugin's options and post meta — on
// exactly the networks big enough for it to matter, and with nothing to say it
// had happened.
$bfst_site_ids = [ null ];
if ( function_exists( 'get_sites' ) && function_exists( 'switch_to_blog' ) ) {
	$bfst_site_ids = [];
	$bfst_offset   = 0;
	do {
		$bfst_batch    = get_sites(
			[
				'number'  => 200,
				'offset'  => $bfst_offset,
				'fields'  => 'ids',
				'orderby' => 'id',
			]
		);
		$bfst_site_ids = array_merge( $bfst_site_ids, $bfst_batch );
		$bfst_offset  += 200;
		$bfst_got      = count( $bfst_batch );
	} while ( 200 === $bfst_got );
}

foreach ( $bfst_site_ids as $bfst_site ) {
	if ( $bfst_site ) {
		switch_to_blog( (int) $bfst_site );
	}

	// The sitemap's cached pages, by the keys this plugin recorded
	// (SitemapManager::CACHE_INDEX_OPTION), and the index. delete_transient()
	// reaches a persistent object cache (Redis, Memcached), where these
	// transients live and the options-table sweep below cannot see them. Read
	// before the options are deleted: the registry is one of them.
	$bfst_cache_keys = get_option( 'bfst_sitemap_cache_keys', [] );
	if ( is_array( $bfst_cache_keys ) ) {
		foreach ( $bfst_cache_keys as $bfst_cache_key ) {
			if ( is_string( $bfst_cache_key ) && str_starts_with( $bfst_cache_key, 'bfst_sitemap_' ) ) {
				delete_transient( $bfst_cache_key );
			}
		}
	}
	delete_transient( 'bfst_sitemap_index' );

	foreach ( $bfst_option_keys as $bfst_key ) {
		delete_option( $bfst_key );
	}

	// Sites without an object cache: sweep this plugin's sitemap transients
	// from the options table too, which also catches pages cached before the
	// registry existed. Only the `bfst_sitemap_` prefix: the Pro add-on's
	// taxonomy pages (`bfst_tax_`) are removed by Pro's own uninstall, and
	// this used to match them as well.
	global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_bfst_sitemap_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_bfst_sitemap_' ) . '%'
		)
	);

	foreach ( $bfst_meta_keys as $bfst_meta_key ) {
		delete_post_meta_by_key( $bfst_meta_key );
	}

	if ( $bfst_site ) {
		restore_current_blog();
	}
}
// Note: no flush_rewrite_rules() here — WordPress rebuilds rules automatically
// after the plugin is deleted, and calling it during uninstall can cause errors.
