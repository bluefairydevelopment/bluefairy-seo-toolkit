#!/usr/bin/env bash
# Seen-red: apply each mutation, confirm it changed the file and still parses,
# run the unit suite, name the failures, restore. Each mutation names the test
# predicted to fail; a mutation counts only when THAT test fails.
# Runs on the rig from the plugin root. Not shipped (tests/ is excluded from the zip).
set -u
pass=0; total=0; bak=$(mktemp)
mutate() { # file from to  (literal, must occur exactly once)
  php -r '$f=$argv[1]; $s=file_get_contents($f); if (substr_count($s,$argv[2])!==1) exit(3); file_put_contents($f, str_replace($argv[2],$argv[3],$s));' "$1" "$2" "$3"
}
run() { # file from to predicted-test
  local f=$1 from=$2 to=$3 want=$4; total=$((total+1))
  cp "$f" "$bak"
  if ! mutate "$f" "$from" "$to"; then cp "$bak" "$f"; echo "MUTATION NOT APPLIED (pattern not found exactly once): $f ($want)"; return; fi
  if cmp -s "$f" "$bak"; then echo "MUTATION CHANGED NOTHING: $f ($want)"; return; fi
  php -l "$f" >/dev/null 2>&1 || { cp "$bak" "$f"; echo "MUTANT DOES NOT PARSE: $f ($want)"; return; }
  out=$(vendor/bin/phpunit --no-progress --log-junit /tmp/mut-junit.xml 2>&1); rc=$?
  cp "$bak" "$f"
  fails=$(php -r '$x=@simplexml_load_file("/tmp/mut-junit.xml"); if(!$x) exit; foreach($x->xpath("//testcase[failure or error]") as $t){ echo preg_replace("/ with data set.*/","",(string)$t["name"]),"\n"; }' | sort -u | tr '\n' ' ')
  hit=no; grep -qw "$want" <<<"$fails" && { hit=yes; pass=$((pass+1)); }
  echo "[$f] exit=$rc predicted=$want hit=$hit | failed: $fails"
}
I=includes
I=includes
run $I/Admin/ProUpsell.php "if ( self::pro_active() ) {
			return \$links;" "if ( false ) {
			return \$links;" test_with_pro_active_the_upsell_disappears
run $I/Admin/ProUpsell.php "if ( self::pro_active() ) {
			return;" "if ( false ) {
			return;" test_with_pro_active_the_upsell_disappears
run $I/Admin/SettingsPage.php "			: [];" "			: [ 'og' => true ];" test_a_corrupt_features_option_checks_no_override_in_the_conflict_notice
run $I/ConflictDetector.php "'wp-admin/includes/plugin.php'" "'wp-admin/includes/missing.php'" test_the_scan_loads_the_admin_plugin_functions_when_they_are_missing
run $I/Installer.php "'wp-admin/includes/plugin.php'" "'wp-admin/includes/missing.php'" test_a_failed_activation_loads_deactivate_plugins_when_it_is_missing
run $I/Installer.php "				\$php_version
" "				PHP_VERSION
" test_requirement_errors_name_each_unmet_version_and_nothing_when_met
run $I/Installer.php "version_compare( \$wp_version, self::MIN_WP, '<' )" "version_compare( \$wp_version, self::MIN_WP, '<=' )" test_requirement_errors_name_each_unmet_version_and_nothing_when_met
run $I/Head/OpenGraphRenderer.php "return 'product';" "return 'website';" test_a_woocommerce_product_is_og_type_product
run $I/PageContext.php "if ( empty( \$params ) ) {" "if ( false ) {" test_a_query_string_with_no_parameters_is_left_as_it_is
run $I/Sitemap/PostTypeSitemap.php "				\$lines[] = \$line;" "				unset( \$line );" test_a_sitemap_skips_rows_that_are_not_posts_and_carries_a_subclass_s_extra_lines
run $I/Sitemap/SitemapManager.php "return is_string( \$output ) && '' !== \$output ? \$output : null;" "return is_string( \$output ) ? \$output : null;" test_sitemap_output_serves_each_kind_and_null_when_there_is_nothing
run $I/Sitemap/SitemapManager.php "if ( HomeSitemap::NAME === \$sitemap && ! in_array( \$sitemap, self::post_types(), true ) ) {" "if ( HomeSitemap::NAME === \$sitemap ) {" test_sitemap_output_serves_the_home_sitemap_unless_a_post_type_took_the_name
run $I/Sitemap/SitemapManager.php "		echo \$output; // phpcs:ignore" "		echo ''; // phpcs:ignore" test_send_xml_prints_the_sitemap
run uninstall.php "defined( 'WP_UNINSTALL_PLUGIN' ) || exit;" "defined( 'WP_UNINSTALL_PLUGIN' ) || true;" test_uninstall_without_the_wordpress_constant_deletes_nothing
run uninstall.php "str_starts_with( \$bfst_cache_key, 'bfst_sitemap_' )" "true" test_uninstall_deletes_the_sitemap_transients_from_a_persistent_object_cache
run uninstall.php "} while ( 200 === \$bfst_got );" "} while ( false );" test_uninstall_walks_every_site_in_batches_of_200
run uninstall.php "		restore_current_blog();" "		( null );" test_uninstall_walks_every_site_in_batches_of_200
rm -f "$bak"
echo "mutations caught by their predicted test: $pass/$total"
[ "$pass" = "$total" ]
