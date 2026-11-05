<?php
/** Disposable WordPress regression for native homepage migration. */
require_once '/wordpress/wp-load.php';
define( 'WP_CLI', true );
class WP_CLI {
    public static function error( $message ) { throw new RuntimeException( $message ); }
    public static function log( $message ) {}
    public static function success( $message ) {}
}
$checks = 0;
$check = static function ( $condition, $message ) use ( &$checks ) { ++$checks; if ( ! $condition ) { throw new RuntimeException( $message ); } };
$original = '<!-- wp:paragraph --><p>Original page content</p><!-- /wp:paragraph -->';
$source_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Homepage', 'post_content' => $original ) );
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $source_id );
$saved_options = array( 'scheme' => 'blue', 'hero_motto' => 'Saved motto', 'home' => array( 'hero_description' => 'Saved hero description', 'sections' => array( 'hero' => array( 'enabled' => true, 'title' => 'Saved heading' ) ) ) );
update_option( 'georg_kloster_prihod_options', $saved_options );
$args = array();
require '/wordpress/wp-content/plugins/prihod-scripts/migrate_home_blocks.php';
$check( (int) get_option( 'page_on_front' ) === $source_id, 'Draft migration changed live homepage.' );
$check( get_post_status( $draft_id ) === 'draft', 'Migration did not create draft.' );
$blocks = get_post_field( 'post_content', $draft_id );
$check( has_block( 'core/group', $blocks ) && has_block( 'core/query', $blocks ), 'Native blocks missing.' );
$check( str_contains( $blocks, 'Saved hero description' ) && str_contains( $blocks, 'Saved heading' ) && str_contains( $blocks, 'Saved motto' ), 'Saved content missing.' );
$check( str_contains( $blocks, $original ), 'Original page blocks missing.' );
$first_draft = $draft_id;
require '/wordpress/wp-content/plugins/prihod-scripts/migrate_home_blocks.php';
$check( $first_draft === $draft_id, 'Repeated migration created duplicate.' );
$args = array( 'apply' );
require '/wordpress/wp-content/plugins/prihod-scripts/migrate_home_blocks.php';
$check( (int) get_option( 'page_on_front' ) === $source_id && get_post_field( 'post_content', $source_id ) === $blocks, 'Apply changed the page ID or lost draft content.' );
$check( get_post_meta( $source_id, '_prihod_original_home_content', true ) === $original && get_option( 'georg_kloster_prihod_options' ) === $saved_options, 'Original data not backed up or options modified.' );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $source_id ) ); $GLOBALS['wp_query']->the_post();
foreach ( array_keys( prihod_theme_schemes() ) as $scheme ) {
    update_option( 'georg_kloster_prihod_options', array_merge( $saved_options, array( 'scheme' => $scheme ) ) );
    $html = prihod_theme_home();
    $check( str_contains( $html, 'Saved heading' ) && str_contains( $html, 'Original page content' ), 'Block content lost in scheme ' . $scheme );
}
$args = array( 'rollback' );
require '/wordpress/wp-content/plugins/prihod-scripts/migrate_home_blocks.php';
$check( get_post_field( 'post_content', $source_id ) === $original && ! get_post_meta( $source_id, '_prihod_blocks_source', true ), 'Rollback failed.' );
file_put_contents( __DIR__ . '/.native-home-result.json', wp_json_encode( array( 'passed' => $checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION ) ) );
echo 'NATIVE_HOME_OK ' . $checks;
