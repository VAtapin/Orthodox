<?php
// Disposable local browser fixture. Never run in an existing site.
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_set_current_user( 1 );
activate_plugin( 'prihod/prihod.php' );
Prihod\Capabilities::install(); wp_set_current_user( 0 ); wp_set_current_user( 1 ); Prihod\Directory::register(); Prihod\Schedule::register();
foreach ( array( 'orthocal/orthocal.php', 'georg-kloster-orthodox-donate/georg-kloster-orthodox-donate.php' ) as $plugin ) { $result = activate_plugin( $plugin ); if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); } }
GeorgKlosterOrthodoxDonate\Public_Form::init(); Orthocal_Plugin::register(); Prihod\Existing_Integration::hooks();
switch_theme( 'prihod-theme' ); require_once WP_CONTENT_DIR . '/themes/prihod-theme/functions.php';
update_option( 'WPLANG', 'ru_RU' ); switch_to_locale( 'ru_RU' );
update_option( 'prihod_modules', array( 'schedule' => true, 'requests' => true ) );
prihod_theme_import_demo( array_keys( prihod_theme_demo_pages() ), true, true, true );
$source_dir = WP_CONTENT_DIR . '/prihod-demo-source';
$pack = json_decode( file_get_contents( $source_dir . '/monastery-content.json' ), true );
foreach ( $pack['images'] as $key => $image ) {
    $upload = wp_upload_bits( $key . '.jpg', null, file_get_contents( WP_CONTENT_DIR . '/design-media/' . $key . '.jpg' ) );
    $id = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_title' => $image['caption'] ), $upload['file'] );
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
    update_post_meta( $id, '_prihod_demo_source', $image['url'] );
}
define( 'PRIHOD_DEMO_TEST', true );
ob_start(); require $source_dir . '/apply-monastery-demo.php'; $result = json_decode( ob_get_clean(), true );
$options = prihod_theme_options(); $options['map_embed'] = 'https://www.openstreetmap.org/export/embed.html?bbox=13.6518627%2C53.0821374%2C13.6918627%2C53.1021374&layer=mapnik&marker=53.0921374%2C13.6718627'; update_option( 'prihod_theme_options', $options );
echo wp_json_encode( $result );
