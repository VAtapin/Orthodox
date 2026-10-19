<?php
/** Disposable WordPress only; real media APIs, intercepted calendar requests. */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
use GeorgKlosterPrihodCore\Directory;
use GeorgKlosterPrihodCore\Place_Media;
use GeorgKlosterPrihodCore\Blocks;
use GeorgKlosterPrihodSchedule\Schedule;
use GeorgKlosterPrihodSchedule\Calendar_Provider;
use GeorgKlosterPrihodSchedule\Public_View;

$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } };
$throws = static function ( callable $fn, string $message ) use ( $check ): void { $failed = false; try { $fn(); } catch ( Throwable $error ) { $failed = true; } $check( $failed, $message ); };
$http = array();
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$http ) { $http[] = $url; return new WP_Error( 'fixture', 'No external network in fixture.' ); }, 10, 3 );
wp_set_current_user( 1 ); set_current_screen( 'dashboard' );
$check( '0.2.0' === GKPC_VERSION && GKPC_VERSION === GKPSCH_VERSION, 'Merged package version or module missing.' );
$check( ! is_plugin_active( 'georg-kloster-prihod-schedule/georg-kloster-prihod-schedule.php' ), 'Old Schedule remains active.' );
$check( str_contains( GKPSCH_FILE, '/georg-kloster-prihod-core/schedule/' ), 'Schedule still runs from the old package.' );
$check( array() === GeorgKlosterPrihodCore\Demo_Importer::state(), 'Merge implicitly imported demo data.' );
require __DIR__ . '/parish-merge-seed.php';
$fixture = get_option( 'gkpc_merge_fixture' );
$check( 'Existing owner parish' === GeorgKlosterPrihodCore\Profile::get()['name'], 'Owner profile lost.' );
$check( 'Existing chapel description' === get_post_field( 'post_content', $fixture['place'] ) && 'Existing church address' === get_post_field( 'post_content', $fixture['old_place'] ), 'Place descriptions changed.' );
$check( 'Existing biography' === get_post_field( 'post_content', $fixture['clergy'] ) && 'Existing rank' === Directory::details( $fixture['clergy'] )['rank'], 'Clergy data changed.' );
$check( $fixture['rule'] === get_post_meta( $fixture['service'], '_gkpsch_service', true ), 'Existing rule or service place ID changed.' );
$check( 'Europe/Berlin' === Schedule::timezone( $fixture['old_place'] ), 'Old place timezone lost.' );
$places = Directory::places( array( 'fields' => 'ids' ) );
$check( in_array( $fixture['place'], $places, true ) && in_array( $fixture['old_place'], $places, true ) && 2 === count( $places ), 'Unified place list duplicates or omits existing places.' );
$check( 'gkpc_location' === get_post_type( $fixture['place'] ) && 'gkpsch_location' === get_post_type( $fixture['old_place'] ), 'Merge rewrote source post types.' );
$pack = GeorgKlosterPrihodCore\Demo_Importer::pack( 'en_US' );
update_post_meta( $fixture['old_place'], '_gkpc_demo_key', $pack['id'] . ':en_US:schedule-place' );
$find_demo = new ReflectionMethod( GeorgKlosterPrihodCore\Demo_Importer::class, 'find' ); $find_demo->setAccessible( true );
$check( $fixture['old_place'] === $find_demo->invoke( null, $pack, 'schedule-place', 'gkpc_location' ), 'Resuming a previous demo would duplicate its old Schedule place.' );
$new_rule = Schedule::validate( array_merge( $fixture['rule'], array( 'location_id' => $fixture['place'] ) ) );
$check( $fixture['place'] === $new_rule['location_id'], 'New schedule rules cannot use Parish places.' );
$bad_place = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Not a service place' ) );
$throws( static fn() => Schedule::validate( array_merge( $new_rule, array( 'location_id' => $bad_place ) ) ), 'Schedule accepted a page as a place.' );

do_action( 'admin_menu' ); global $menu, $submenu;
$check( 1 === count( array_filter( $menu, static fn( $row ) => 'gkpc' === $row[2] ) ) && ! array_filter( $menu, static fn( $row ) => 'gkpsch' === $row[2] ), 'Separate Schedule top-level menu remains.' );
$links = array_column( $submenu['gkpc'], 2 );
foreach ( array( 'edit.php?post_type=gkpc_location', 'edit.php?post_type=gkpc_clergy', 'edit.php?post_type=gkpsch_service', 'gkpsch-settings', 'gkpc-demo' ) as $link ) { $check( in_array( $link, $links, true ), 'Merged menu link missing: ' . $link ); }
$check( ! in_array( 'edit.php?post_type=gkpsch_location', $links, true ), 'Two separate place menus remain.' );
$check( false === get_post_type_object( 'gkpsch_location' )->show_in_menu && 'gkpc' === get_post_type_object( 'gkpsch_service' )->show_in_menu, 'Legacy places or services attached to wrong menu.' );
$query = new WP_Query(); $query->set( 'post_type', 'gkpc_location' ); global $wp_the_query; $old_main = $wp_the_query; $wp_the_query = $query;
Directory::unified_list( $query ); $wp_the_query = $old_main;
$check( Directory::location_types() === $query->get( 'post_type' ), 'Admin list query is not unified.' );
$check( 2 === (int) wp_count_posts( 'gkpc_location' )->publish && 2 === (int) wp_count_posts( 'gkpc_location' )->publish, 'Unified list counters omit legacy places or double-count cached values.' );
ob_start(); GeorgKlosterPrihodSchedule\Schedule_Admin::render( get_post( $fixture['service'] ) ); $editor = ob_get_clean();
$check( str_contains( $editor, 'Existing parish chapel' ) && str_contains( $editor, 'Existing schedule church' ), 'Service place selector is not unified.' );

$check( 'https://bible-desktop.com' === Calendar_Provider::options()['origin'] && 'parish' === Calendar_Provider::options()['profile'], 'Calendar origin not fixed or existing profile lost.' );
$safe = Calendar_Provider::sanitize( array( 'origin' => 'https://malicious.example', 'profile' => 'typikon-strict' ) );
$check( 'https://bible-desktop.com' === $safe['origin'], 'Hidden origin can still override the fixed provider.' );
do_action( 'admin_init' );
ob_start(); GeorgKlosterPrihodSchedule\Admin::settings_page(); $settings = ob_get_clean();
$check( ! str_contains( $settings, '[origin]' ) && ! str_contains( $settings, 'obsolete-calendar.example' ), 'API address field remains in settings.' );
Calendar_Provider::request( 'day', array( 'date' => '2026-10-19' ) );
$check( count( $http ) > 0 && str_starts_with( end( $http ), 'https://bible-desktop.com/api/v1/calendar/day' ), 'Calendar request ignored fixed origin.' );

require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
$images = array();
remove_theme_support( 'post-thumbnails' );
$check( ! current_theme_supports( 'post-thumbnails' ), 'Media fixture must prove independence from theme featured-image support.' );
foreach ( array( 'monastery.jpg', 'trinity.jpg' ) as $name ) {
    $temporary = wp_tempnam( $name ); copy( dirname( GKPC_FILE ) . '/demo/media/' . $name, $temporary );
    $id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $temporary ), 0 );
    $check( ! is_wp_error( $id ), 'Could not import the real local image fixture.' ); $images[] = $id;
}
foreach ( array( $fixture['place'], $fixture['old_place'] ) as $id ) {
    Place_Media::store( $id, array( 'logo' => $images[0], 'photos' => array( $images[1], $images[1] ) ) );
    $check( array( 'logo' => $images[0], 'photos' => array( $images[1] ) ) === Place_Media::get( $id ), 'Media storage lost logo, order or duplicate handling.' );
    $html = Place_Media::render( $id );
    $check( str_contains( $html, 'gkpc-place-logo' ) && str_contains( $html, 'gkpc-place-photos' ) && 2 === substr_count( $html, '<img' ), 'Place logo/photos not displayed independently.' );
    ob_start(); Place_Media::render_box( get_post( $id ) ); $box = ob_get_clean();
    $check( str_contains( $box, 'gkpc_media_nonce' ) && str_contains( $box, 'gkpc_media[logo]' ) && str_contains( $box, 'gkpc_media[photos]' ), 'Image editor missing on a place.' );
}
$check( 4 === substr_count( Blocks::render( 'places' ), '<img' ), 'Public places block omitted media or an existing place.' );
$schedule = Public_View::render( 'schedule', array( 'location' => $fixture['old_place'] ) );
$check( str_contains( $schedule, 'gkpc-place-logo' ) && str_contains( $schedule, 'gkpc-place-photos' ), 'Schedule does not display its place media.' );
$check( ! str_contains( Public_View::render( 'schedule' ), 'gkpc-place-media' ), 'Unfiltered multi-place schedule arbitrarily displays one place logo.' );
$before = Place_Media::get( $fixture['place'] );
$throws( static fn() => Place_Media::store( $fixture['place'], array( 'logo' => $bad_place, 'photos' => array() ) ), 'Non-image accepted as logo.' );
$throws( static fn() => Place_Media::store( $fixture['place'], array( 'logo' => 0, 'photos' => array_fill( 0, 25, $images[0] ) ) ), 'Unbounded gallery accepted.' );
$_POST = array( 'gkpc_media_nonce' => 'invalid', 'gkpc_media' => array( 'logo' => '0', 'photos' => '' ) ); Place_Media::save( $fixture['place'] );
$check( $before === Place_Media::get( $fixture['place'] ), 'Invalid nonce changed place images.' );
wp_set_current_user( 0 );
$throws( static fn() => Place_Media::store( $fixture['place'], array( 'logo' => 0, 'photos' => array() ) ), 'Anonymous user changed place images.' );
wp_set_current_user( 1 );
$_POST = array( 'gkpc_media_nonce' => wp_create_nonce( 'gkpc_media_' . $fixture['place'] ), 'gkpc_media' => array( 'logo' => '0', 'photos' => '' ) ); Place_Media::save( $fixture['place'] );
$check( array( 'logo' => 0, 'photos' => array() ) === Place_Media::get( $fixture['place'] ) && get_post( $images[0] ), 'Removing a logo deletes the original attachment or fails.' );
$_POST = array();
wp_update_post( array( 'ID' => $fixture['old_place'], 'post_status' => 'draft' ) );
$check( '' === Place_Media::render( $fixture['old_place'] ), 'Unpublished place media leaked publicly.' );
wp_update_post( array( 'ID' => $fixture['old_place'], 'post_status' => 'publish' ) );
set_current_screen( 'gkpc_location' ); Place_Media::assets();
$check( wp_script_is( 'gkpc-place-media', 'enqueued' ), 'Place media picker script not enqueued.' );
$check( WP_Block_Type_Registry::get_instance()->is_registered( 'gkpsch/schedule' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'gkpc/places' ) && shortcode_exists( 'gkpsch_schedule' ), 'Existing blocks or shortcodes broke.' );
$check( GeorgKlosterPrihodCore\Demo_Importer::schedule_available(), 'Demo no longer detects bundled Schedule.' );
file_put_contents( __DIR__ . '/.parish-merge-result.json', wp_json_encode( array( 'checks' => $checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'PARISH_MERGE_OK' ) ) );
echo 'PARISH_MERGE_OK ' . $checks;
