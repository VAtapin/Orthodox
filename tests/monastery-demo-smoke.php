<?php
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
activate_plugin( 'prihod/prihod.php' );
Prihod\Capabilities::install();
Prihod\Directory::register(); Prihod\Schedule::register();
wp_set_current_user( 1 );
switch_theme( 'prihod-theme' ); require_once WP_CONTENT_DIR . '/themes/prihod-theme/functions.php';
// A fresh database scenario only; never run this fixture cleanup on a real site.
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => array( 'publish', 'draft', 'trash' ), 'numberposts' => -1, 'meta_key' => '_prihod_resource' ) ) as $fixture ) { wp_delete_post( $fixture->ID, true ); }
delete_option( 'prihod_resources' ); update_option( 'prihod_profile', array() );
update_option( 'prihod_modules', array( 'schedule' => true, 'requests' => true ) );
add_shortcode( 'orthocal_calendar', fn() => '' ); add_shortcode( 'gkod_form', fn() => '' ); add_shortcode( 'gkod_donation', fn() => '' );
Prihod\Existing_Integration::hooks();
prihod_theme_import_demo( array_keys( prihod_theme_demo_pages() ), true, true, true );
$source_dir = WP_CONTENT_DIR . '/prihod-demo-source';
$fixture_pack = json_decode( file_get_contents( $source_dir . '/monastery-content.json' ), true );
require_once ABSPATH . 'wp-admin/includes/image.php';
foreach ( $fixture_pack['images'] as $image ) {
    $upload = wp_upload_bits( 'fixture-' . md5( $image['url'] ) . '.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==' ) );
    $id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_title' => $image['caption'] ), $upload['file'] );
    update_post_meta( $id, '_prihod_demo_source', $image['url'] );
}
define( 'PRIHOD_DEMO_TEST', true );
ob_start(); require $source_dir . '/apply-monastery-demo.php'; $first = json_decode( ob_get_clean(), true );
if ( 13 !== count( $first['pages'] ) || 6 !== count( $first['posts'] ) || $first['preserved'] || 8 !== count( $first['images'] ) ) { throw new RuntimeException( 'Content pack must populate all thirteen pages, six articles and eight images: ' . wp_json_encode( $first ) ); }
$home = get_post( $first['pages']['home'] );
wp_update_post( array( 'ID' => $home->ID, 'post_content' => $home->post_content . '<p>Owner edit</p>' ) );
$before = wp_count_posts( 'post' )->publish;
ob_start(); require $source_dir . '/apply-monastery-demo.php'; $second = json_decode( ob_get_clean(), true );
if ( ! in_array( 'home', $second['preserved'], true ) || $before !== wp_count_posts( 'post' )->publish || ! str_contains( get_post( $home->ID )->post_content, 'Owner edit' ) ) { throw new RuntimeException( 'Repeated import must preserve owner edits and avoid duplicate articles.' ); }
echo "MONASTERY_DEMO_OK: 13 pages, 6 posts, 8 media; repeat and owner-edit protection.\n";
file_put_contents( __DIR__ . '/.monastery-result.json', wp_json_encode( array( 'status' => 'MONASTERY_DEMO_OK', 'pages' => count( $first['pages'] ), 'posts' => count( $first['posts'] ), 'media' => count( $first['images'] ), 'preserved' => $second['preserved'] ) ) );
