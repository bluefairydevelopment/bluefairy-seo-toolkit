<?php
/**
 * WordPress stand-ins for the pure-unit suite.
 *
 * State lives in $GLOBALS['bfst_wp'] and is reset by bfst_wp_reset(), which
 * every test calls in setUp(). Only what the plugin touches is modelled; the
 * escaping helpers follow core's output for the inputs the tests use.
 *
 * @package BlueFairy\SeoToolkit
 */

declare(strict_types=1);

// phpcs:ignoreFile -- test stand-ins, not shipped.

/** Reset every fixture. */
function bfst_wp_reset(): void {
	$GLOBALS['bfst_wp'] = [
		'options'        => [],
		'autoload'       => [],
		'transients'     => [],
		'transient_ttl'  => [],
		'actions'        => [],
		'filters'        => [],
		'did'            => [],
		'cond'           => [],
		'queried'        => null,
		'query_vars'     => [],
		'permalinks'     => [],
		'term_links'     => [],
		'author_urls'    => [],
		'archive_links'  => [],
		'post_meta'      => [],
		'posts'          => [],
		'unlocked'       => [],
		'bloginfo'       => [ 'name' => 'Fixture Site', 'description' => 'Fixture tagline' ],
		'can'            => true,
		'active_plugins' => [],
		'wp_query'       => [ 'posts' => [], 'found_posts' => 0 ],
		'queries'        => [],
		'get_posts'      => [],
		'get_posts_args' => [],
		'rewrite_rules'  => [],
		'flushed'        => 0,
		'scripts'        => [],
		'localized'      => [],
		'rest_routes'    => [],
		'options_pages'  => [],
		'sections'       => [],
		'settings'       => [],
		'fields'         => [],
		'status'         => null,
		'is_404_set'     => false,
		'ext_cache'      => false,
		'db_col'         => [],
		'db_sql'         => [],
		'deactivated'    => [],
		'wp_die'         => null,
		'post_types'     => [],
		'taxonomies'     => [],
		'taxonomy_exists'=> [],
		'functions_wc'   => false,
	];
	$GLOBALS['wp_version'] = '6.8';
	$GLOBALS['wp_query']   = new BFST_Fake_Main_Query();
	$GLOBALS['wpdb']       = new BFST_Fake_Wpdb();
}

/** Set a conditional tag (is_singular, is_404, …). */
function bfst_wp_cond( string $name, bool $value = true ): void {
	$GLOBALS['bfst_wp']['cond'][ $name ] = $value;
}

function bfst_wp_c( string $name ): bool {
	return ! empty( $GLOBALS['bfst_wp']['cond'][ $name ] );
}

final class BFST_Wp_Die extends RuntimeException {}

final class BFST_Fake_Main_Query {
	public bool $is_404 = false;
	public function set_404(): void {
		$this->is_404                       = true;
		$GLOBALS['bfst_wp']['is_404_set']   = true;
	}
}

final class BFST_Fake_Wpdb {
	public string $options = 'wp_options';
	public function get_col( string $sql ): array {
		$GLOBALS['bfst_wp']['db_sql'][] = $sql;
		return $GLOBALS['bfst_wp']['db_col'];
	}
	public function prepare( string $sql, ...$args ): string {
		foreach ( $args as $a ) {
			$sql = preg_replace( '/%s/', "'" . $a . "'", $sql, 1 );
		}
		return $sql;
	}
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}
	public function query( string $sql ): int {
		$GLOBALS['bfst_wp']['db_sql'][] = $sql;
		return 0;
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	final class WP_Post {
		public int $ID                   = 0;
		public string $post_type         = 'post';
		public string $post_title        = '';
		public string $post_content      = '';
		public string $post_excerpt      = '';
		public string $post_password     = '';
		public string $post_modified_gmt = '2026-01-01 00:00:00';
		public function __construct( object $post ) {
			foreach ( get_object_vars( $post ) as $k => $v ) {
				$this->$k = $v;
			}
		}
	}
}

if ( ! class_exists( 'WP_Term' ) ) {
	final class WP_Term {
		public int $term_id        = 0;
		public string $name        = '';
		public string $description = '';
		public string $taxonomy    = 'category';
		public function __construct( object $t ) {
			foreach ( get_object_vars( $t ) as $k => $v ) {
				$this->$k = $v;
			}
		}
	}
}

if ( ! class_exists( 'WP_User' ) ) {
	final class WP_User {
		public int $ID               = 0;
		public string $display_name  = '';
		public function __construct( int $id = 0, string $name = '' ) {
			$this->ID           = $id;
			$this->display_name = $name;
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	final class WP_Error {}
}

if ( ! class_exists( 'WP_Query' ) ) {
	final class WP_Query {
		public array $posts    = [];
		public int $found_posts = 0;
		public function __construct( public array $query_vars = [] ) {
			$GLOBALS['bfst_wp']['queries'][] = $query_vars;
			$this->posts       = $GLOBALS['bfst_wp']['wp_query']['posts'];
			$by_type           = $GLOBALS['bfst_wp']['wp_query']['found_posts_by_type'] ?? [];
			$type              = is_string( $query_vars['post_type'] ?? null ) ? $query_vars['post_type'] : '';
			$this->found_posts = $by_type[ $type ] ?? $GLOBALS['bfst_wp']['wp_query']['found_posts'];
		}
	}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	final class WP_REST_Server {
		public const EDITABLE = 'POST, PUT, PATCH';
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	final class WP_REST_Request {
		public function __construct( private array $params = [] ) {}
		public function get_param( string $key ): mixed {
			return $this->params[ $key ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	final class WP_REST_Response {
		public function __construct( public mixed $data = null, public int $status = 200 ) {}
		public function get_data(): mixed {
			return $this->data;
		}
		public function get_status(): int {
			return $this->status;
		}
	}
}

/** A registered post by ID. */
function bfst_wp_post( array $fields ): WP_Post {
	$p = new WP_Post( (object) $fields );
	$GLOBALS['bfst_wp']['posts'][ $p->ID ] = $p;
	return $p;
}

// ---- hooks ---------------------------------------------------------------

function add_action( string $hook, $cb, int $priority = 10, int $args = 1 ): bool {
	$GLOBALS['bfst_wp']['actions'][ $hook ][] = [ 'cb' => $cb, 'priority' => $priority, 'args' => $args ];
	return true;
}
function add_filter( string $hook, $cb, int $priority = 10, int $args = 1 ): bool {
	$GLOBALS['bfst_wp']['filters'][ $hook ][] = [ 'cb' => $cb, 'priority' => $priority, 'args' => $args ];
	return true;
}
function has_action( string $hook, $cb = false ) {
	foreach ( $GLOBALS['bfst_wp']['actions'][ $hook ] ?? [] as $h ) {
		if ( $h['cb'] === $cb ) {
			return $h['priority'];
		}
	}
	return false;
}
function has_filter( string $hook, $cb = false ) {
	foreach ( $GLOBALS['bfst_wp']['filters'][ $hook ] ?? [] as $h ) {
		if ( $h['cb'] === $cb ) {
			return $h['priority'];
		}
	}
	return false;
}
function do_action( string $hook, ...$args ): void {
	$GLOBALS['bfst_wp']['did'][] = $hook;
	foreach ( $GLOBALS['bfst_wp']['actions'][ $hook ] ?? [] as $h ) {
		( $h['cb'] )( ...array_slice( $args, 0, $h['args'] ) );
	}
}
function apply_filters( string $hook, $value, ...$args ) {
	foreach ( $GLOBALS['bfst_wp']['filters'][ $hook ] ?? [] as $h ) {
		$value = ( $h['cb'] )( $value, ...array_slice( $args, 0, max( 0, $h['args'] - 1 ) ) );
	}
	return $value;
}

// ---- options / transients ------------------------------------------------

function get_option( string $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['bfst_wp']['options'] ) ? $GLOBALS['bfst_wp']['options'][ $name ] : $default;
}
function update_option( string $name, $value, $autoload = null ): bool {
	$old = $GLOBALS['bfst_wp']['options'][ $name ] ?? null;
	$GLOBALS['bfst_wp']['options'][ $name ] = $value;
	if ( null !== $autoload ) {
		$GLOBALS['bfst_wp']['autoload'][ $name ] = $autoload;
	}
	return $old !== $value;
}
function add_option( string $name, $value = '', $deprecated = '', $autoload = null ): bool {
	if ( array_key_exists( $name, $GLOBALS['bfst_wp']['options'] ) ) {
		return false;
	}
	$GLOBALS['bfst_wp']['options'][ $name ]  = $value;
	$GLOBALS['bfst_wp']['autoload'][ $name ] = $autoload;
	return true;
}
function delete_option( string $name ): bool {
	unset( $GLOBALS['bfst_wp']['options'][ $name ] );
	return true;
}
function get_transient( string $key ) {
	return $GLOBALS['bfst_wp']['transients'][ $key ] ?? false;
}
function set_transient( string $key, $value, int $ttl = 0 ): bool {
	$GLOBALS['bfst_wp']['transients'][ $key ]    = $value;
	$GLOBALS['bfst_wp']['transient_ttl'][ $key ] = $ttl;
	return true;
}
function delete_transient( string $key ): bool {
	unset( $GLOBALS['bfst_wp']['transients'][ $key ] );
	return true;
}
function wp_using_ext_object_cache(): bool {
	return $GLOBALS['bfst_wp']['ext_cache'];
}

// ---- conditionals / query -----------------------------------------------

function is_singular( $type = '' ): bool {
	if ( '' !== $type ) {
		return bfst_wp_c( 'is_singular_' . $type );
	}
	return bfst_wp_c( 'is_singular' );
}
function is_author(): bool { return bfst_wp_c( 'is_author' ); }
function is_tax(): bool { return bfst_wp_c( 'is_tax' ); }
function is_category(): bool { return bfst_wp_c( 'is_category' ); }
function is_tag(): bool { return bfst_wp_c( 'is_tag' ); }
function is_home(): bool { return bfst_wp_c( 'is_home' ); }
function is_front_page(): bool { return bfst_wp_c( 'is_front_page' ); }
function is_search(): bool { return bfst_wp_c( 'is_search' ); }
function is_404(): bool { return bfst_wp_c( 'is_404' ); }
function is_feed(): bool { return bfst_wp_c( 'is_feed' ); }
function is_paged(): bool { return bfst_wp_c( 'is_paged' ); }
function is_post_type_archive(): bool { return bfst_wp_c( 'is_post_type_archive' ); }
function is_date(): bool { return bfst_wp_c( 'is_date' ); }
function is_day(): bool { return bfst_wp_c( 'is_day' ); }
function is_month(): bool { return bfst_wp_c( 'is_month' ); }
function is_admin(): bool { return bfst_wp_c( 'is_admin' ); }
function get_queried_object() { return $GLOBALS['bfst_wp']['queried']; }
function get_query_var( string $var, $default = '' ) {
	return $GLOBALS['bfst_wp']['query_vars'][ $var ] ?? $default;
}
function WC_fake_marker(): void {}

// ---- posts / links -------------------------------------------------------

function get_post( int $id ) { return $GLOBALS['bfst_wp']['posts'][ $id ] ?? null; }
function get_posts( array $args ): array {
	$GLOBALS['bfst_wp']['get_posts_args'][] = $args;
	return $GLOBALS['bfst_wp']['get_posts'];
}
function get_post_meta( int $id, string $key = '', bool $single = false ) {
	return $GLOBALS['bfst_wp']['post_meta'][ $id ] ?? [];
}
function get_permalink( $id ) {
	$id = $id instanceof WP_Post ? $id->ID : (int) $id;
	return $GLOBALS['bfst_wp']['permalinks'][ $id ] ?? false;
}
function get_term_link( $term ) {
	return $GLOBALS['bfst_wp']['term_links'][ $term->term_id ] ?? new WP_Error();
}
function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function get_author_posts_url( int $id ): string { return $GLOBALS['bfst_wp']['author_urls'][ $id ] ?? ''; }
function get_post_type_archive_link( string $pt ) { return $GLOBALS['bfst_wp']['archive_links'][ $pt ] ?? false; }
function get_day_link( int $y, int $m, int $d ): string { return sprintf( 'https://example.test/%04d/%02d/%02d/', $y, $m, $d ); }
function get_month_link( int $y, int $m ): string { return sprintf( 'https://example.test/%04d/%02d/', $y, $m ); }
function get_year_link( int $y ): string { return sprintf( 'https://example.test/%04d/', $y ); }
function home_url( string $path = '' ): string { return 'https://example.test' . $path; }
function rest_url(): string { return 'https://example.test/wp-json/'; }
function plugins_url( string $path, string $file ): string { return 'https://example.test/wp-content/plugins/bluefairy-seo-toolkit/' . basename( $path ); }
function plugin_basename( string $file ): string { return 'bluefairy-seo-toolkit/' . basename( $file ); }
function get_the_title( $post ): string { return $post instanceof WP_Post ? $post->post_title : ''; }
function get_bloginfo( string $show = '' ): string { return $GLOBALS['bfst_wp']['bloginfo'][ $show ] ?? ''; }
function post_password_required( $post ): bool {
	return $post instanceof WP_Post && '' !== $post->post_password && empty( $GLOBALS['bfst_wp']['unlocked'][ $post->ID ] );
}
function taxonomy_exists( string $tax ): bool { return ! empty( $GLOBALS['bfst_wp']['taxonomy_exists'][ $tax ] ); }
function get_post_types( array $args, string $output ): array { return $GLOBALS['bfst_wp']['post_types']; }
function get_taxonomies( array $args, string $output ): array { return $GLOBALS['bfst_wp']['taxonomies']; }

// ---- strings / escaping ---------------------------------------------------

function __( string $t, string $d = '' ): string { return $t; }
function esc_html__( string $t, string $d = '' ): string { return esc_html( $t ); }
function esc_html_e( string $t, string $d = '' ): void { echo esc_html( $t ); }
function esc_html( $t ): string { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $t ): string { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $u ): string { return htmlspecialchars( esc_url_raw( $u ), ENT_QUOTES, 'UTF-8' ); }
function esc_url_raw( $u ): string {
	$u = trim( (string) $u );
	return preg_match( '#^https?://#i', $u ) ? $u : '';
}
function wp_strip_all_tags( string $t ): string { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $t ) ) ); }
function strip_shortcodes( string $t ): string { return preg_replace( '/\[[^\]]*\]/', '', $t ); }
function wp_trim_words( string $t, int $n = 55 ): string {
	$w = preg_split( '/\s+/', trim( $t ), -1, PREG_SPLIT_NO_EMPTY );
	return count( $w ) > $n ? implode( ' ', array_slice( $w, 0, $n ) ) . '&hellip;' : implode( ' ', $w );
}
function sanitize_text_field( string $t ): string { return trim( strip_tags( $t ) ); }
function sanitize_key( string $k ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
function wp_parse_url( string $u ) { return parse_url( $u ); }
function remove_query_arg( string $key, string $url ): string {
	$p = parse_url( $url );
	if ( empty( $p['query'] ) ) {
		return $url;
	}
	parse_str( $p['query'], $q );
	unset( $q[ $key ] );
	$base = strtok( $url, '?' );
	return [] === $q ? $base : $base . '?' . http_build_query( $q );
}
function checked( $a, $b = true, bool $echo = true ): string {
	$r = ( (string) $a === (string) $b ) ? " checked='checked'" : '';
	if ( $echo ) {
		echo $r;
	}
	return $r;
}
function wp_create_nonce( string $a ): string { return 'nonce-' . $a; }

// ---- admin / registration ---------------------------------------------------

function current_user_can( string $cap ): bool { return $GLOBALS['bfst_wp']['can']; }
function wp_die( $msg = '', $title = '', $args = [] ): void {
	$GLOBALS['bfst_wp']['wp_die'] = [ $msg, $title, $args ];
	throw new BFST_Wp_Die( (string) $msg );
}
function is_plugin_active( string $file ): bool { return in_array( $file, $GLOBALS['bfst_wp']['active_plugins'], true ); }
function deactivate_plugins( string $file ): void { $GLOBALS['bfst_wp']['deactivated'][] = $file; }
function flush_rewrite_rules(): void { $GLOBALS['bfst_wp']['flushed']++; }
function add_rewrite_rule( string $regex, string $query, string $after = 'bottom' ): void {
	$GLOBALS['bfst_wp']['rewrite_rules'][ $regex ] = [ $query, $after ];
}
function register_rest_route( string $ns, string $route, array $args ): bool {
	$GLOBALS['bfst_wp']['rest_routes'][ $ns . $route ] = $args;
	return true;
}
function wp_enqueue_script( string $handle, string $src = '', array $deps = [], $ver = false, $footer = false ): void {
	$GLOBALS['bfst_wp']['scripts'][ $handle ] = [ $src, $deps, $ver, $footer ];
}
function wp_localize_script( string $handle, string $name, array $data ): bool {
	$GLOBALS['bfst_wp']['localized'][ $handle ] = [ $name, $data ];
	return true;
}
function add_options_page( string $title, string $menu, string $cap, string $slug, $cb ): string {
	$GLOBALS['bfst_wp']['options_pages'][ $slug ] = [ $title, $menu, $cap, $cb ];
	return 'settings_page_' . $slug;
}
function add_settings_section( string $id, string $title, $cb, string $page ): void {
	$GLOBALS['bfst_wp']['sections'][ $id ] = [ $title, $cb, $page ];
}
function register_setting( string $group, string $name, array $args = [] ): void {
	$GLOBALS['bfst_wp']['settings'][ $name ] = [ $group, $args ];
}
function add_settings_field( string $id, string $title, $cb, string $page, string $section ): void {
	$GLOBALS['bfst_wp']['fields'][ $id ] = [ $title, $cb, $page, $section ];
}
function settings_fields( string $group ): void { echo '<input type="hidden" name="option_page" value="' . esc_attr( $group ) . '">'; }
function do_settings_sections( string $page ): void { echo '<!-- sections:' . esc_html( $page ) . ' -->'; }
function submit_button(): void { echo '<input type="submit" class="button-primary">'; }
function status_header( int $code ): void { $GLOBALS['bfst_wp']['status'] = $code; }
function delete_post_meta_by_key( string $k ): void {}
