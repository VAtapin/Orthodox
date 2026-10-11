<?php
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
if ( is_plugin_active( 'prihod/prihod.php' ) ) { throw new RuntimeException( 'Standalone check needs inactive core.' ); }
switch_theme( 'prihod-theme' );
require_once WP_CONTENT_DIR . '/themes/prihod-theme/functions.php';
prihod_theme_register_blocks();
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Ordinary page', 'post_content' => '<!-- wp:paragraph --><p>Standalone content</p><!-- /wp:paragraph -->' ) );
$GLOBALS['post'] = get_post( $page );
$html = do_blocks( get_block_template( 'prihod-theme//page', 'wp_template' )->content );
if ( ! str_contains( $html, 'Standalone content' ) ) { throw new RuntimeException( 'Theme must work without core.' ); }
wp_set_current_user( 1 );
// The preceding Core tests deliberately preserve an edited gallery. Start this
// independent importer scenario with clean fixtures in the disposable database.
foreach ( array( 'home', 'photos' ) as $key ) {
    foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'trash' ), 'numberposts' => -1, 'meta_key' => '_prihod_resource', 'meta_value' => $key ) ) as $fixture ) { wp_delete_post( $fixture->ID, true ); }
}
delete_option( 'prihod_resources' );
$demo = prihod_theme_import_demo( array( 'home', 'photos' ) );
$again = prihod_theme_import_demo( array( 'home', 'photos' ) );
if ( count( $demo['created'] ) !== 2 || $again['created'] || 'draft' !== get_post_status( $demo['created']['home'] ) ) { throw new RuntimeException( 'Standalone demo import must create drafts without duplicates.' ); }
prihod_theme_import_demo( array( 'home', 'photos' ), true, true, true );
if ( (int) get_option( 'page_on_front' ) !== $demo['created']['home'] || ! isset( get_option( 'prihod_resources' )['_navigation'] ) ) { throw new RuntimeException( 'Standalone demo publication must set the selected homepage and native navigation.' ); }
file_put_contents( __DIR__ . '/.theme-result.json', wp_json_encode( array( 'status' => 'THEME_ALONE_OK', 'wordpress' => get_bloginfo( 'version' ) ) ) );
