<?php
/** Disposable WordPress only. Uses real bundled assets and WordPress media APIs. */
if ( ! is_file( '/wordpress/wp-load.php' ) ) { throw new RuntimeException( 'Requires disposable WordPress Playground.' ); }
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
use GeorgKlosterPrihodCore\Demo_Importer;
use GeorgKlosterPrihodCore\Demo_Admin;
use GeorgKlosterPrihodCore\Lock;
$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } };
$snapshot = static function (): string { global $wpdb; return hash( 'sha256', serialize( $wpdb->get_results( "SELECT ID,post_type,post_status,post_title,post_content,post_excerpt FROM {$wpdb->posts} ORDER BY ID", ARRAY_A ) ) ); };
$http = $mail = 0;
add_filter( 'pre_http_request', static function () use ( &$http ) { ++$http; return new WP_Error( 'fixture', 'No external HTTP in demo tests.' ); } );
add_filter( 'pre_wp_mail', static function () use ( &$mail ) { ++$mail; return false; } );
$locale = defined( 'GKPC_DEMO_TEST_LOCALE' ) ? GKPC_DEMO_TEST_LOCALE : 'ru_RU';
$pack = Demo_Importer::pack( $locale );
$check( ! str_starts_with( $pack['theme']['quote_text'], '«' ) && ! str_ends_with( $pack['theme']['quote_text'], '»' ), 'Demo quotation duplicates theme punctuation.' );
$check( array() === Demo_Importer::state(), 'Activation imported demo data automatically.' );
$before = $snapshot();
$check( is_wp_error( Demo_Importer::run( $locale ) ) && $before === $snapshot(), 'Anonymous demo import is possible.' );
wp_set_current_user( 1 );
$check( Demo_Importer::allowed(), 'Administrator cannot import.' );
$check( is_wp_error( Demo_Importer::run( '../../private' ) ) && array() === Demo_Importer::state(), 'Untrusted locale can select an arbitrary data file.' );
$token = Lock::acquire( 'gkpc_demo_lock', 900 );
$check( is_wp_error( Demo_Importer::run( $locale ) ), 'Concurrent demo import was allowed.' ); Lock::release( 'gkpc_demo_lock', $token );
$other_token = Lock::acquire( 'gkpc_demo_lock', 900 ); Lock::release( 'gkpc_demo_lock', $token );
$check( $other_token === get_option( 'gkpc_demo_lock' ), 'Old request can release another importer lock.' ); Lock::release( 'gkpc_demo_lock', $other_token );

$owner_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Owner homepage', 'post_content' => 'Owner material must remain.' ) );
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $owner_id );
$profile = array( 'name' => 'Owner parish', 'email' => 'owner@example.org', 'type' => 'monastery' );
update_option( 'gkpc_profile', $profile );
update_option( 'gkpc_pages', array( 'about' => array( 'id' => $owner_id ) ) );
$theme = array( 'scheme' => 'blue', 'header_contacts' => true, 'hero_motto' => 'Owner motto', 'home' => array( 'sections' => array( 'hero' => array( 'title' => 'Owner hero' ) ), 'cards' => array( 'news' => array( 'enabled' => false ) ) ) );
update_option( 'georg_kloster_prihod_options', $theme );
$menu = wp_create_nav_menu( 'Owner menu' );
wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object-id' => $owner_id, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu ) );
$owner_menu = serialize( wp_get_nav_menu_items( $menu ) );
ob_start(); Demo_Admin::page(); $ui = ob_get_clean();
$check( str_contains( $ui, 'name="consent"' ) && str_contains( $ui, 'name="apply_layout"' ) && ! str_contains( $ui, 'name="apply_layout" value="1" checked' ), 'Import/application consent is missing or preselected.' );
$check( (bool) preg_match( '/name="_wpnonce" value="([^"]+)"/', $ui, $nonce ) && wp_verify_nonce( $nonce[1], 'gkpc_demo_import' ), 'Import form lacks a valid nonce.' );
$check( array() === Demo_Importer::state(), 'Viewing demo preview wrote import state.' );
ob_start(); prihod_theme_settings_page(); $settings_ui = ob_get_clean();
$check( str_contains( $settings_ui, 'page=gkpc-demo' ) && strpos( $settings_ui, 'page=gkpc-demo' ) > strpos( $settings_ui, 'id="prihod-option-home"' ) && strpos( $settings_ui, 'page=gkpc-demo' ) < strpos( $settings_ui, 'class="prihod-home-order"' ), 'Plugin-owned demo entry is not near the homepage controls.' );
$die = static fn() => static function () { throw new RuntimeException( 'denied' ); };
add_filter( 'wp_die_handler', $die ); $_REQUEST['_wpnonce'] = 'invalid';
ob_start(); try { Demo_Admin::handle(); $check( false, 'Invalid import nonce was accepted.' ); } catch ( RuntimeException $error ) { $check( 'denied' === $error->getMessage(), 'Unexpected nonce response.' ); } ob_end_clean();
$_REQUEST['_wpnonce'] = wp_create_nonce( 'gkpc_demo_import' ); $_SERVER['REQUEST_METHOD'] = 'POST';
try { Demo_Admin::handle(); $check( false, 'Import without consent was accepted.' ); } catch ( RuntimeException $error ) { $check( 'denied' === $error->getMessage(), 'Unexpected consent response.' ); }
remove_filter( 'wp_die_handler', $die ); unset( $_REQUEST['_wpnonce'] );

// A real WordPress media failure after the first image must be resumable.
$media_calls = 0;
$fail_media = static function ( $file ) use ( &$media_calls ) { ++$media_calls; if ( 2 === $media_calls ) { $file['error'] = 'Fixture media failure.'; } return $file; };
add_filter( 'wp_handle_sideload_prefilter', $fail_media );
$failed = Demo_Importer::run( $locale );
$check( is_wp_error( $failed ) && 1 === count( Demo_Importer::state()['media'] ?? array() ), 'Partial media import was not recorded safely.' );
remove_filter( 'wp_handle_sideload_prefilter', $fail_media );
$result = Demo_Importer::run( $locale );
$check( ! is_wp_error( $result ), 'Import failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : '' ) );
$check( ! empty( $result['complete'] ) && count( $result['pages'] ) === count( $pack['pages'] ) && count( $result['posts'] ) === count( $pack['posts'] ) && count( $result['media'] ) === count( $pack['media'] ), 'Demo content is incomplete.' );
$check( $owner_id === (int) get_option( 'page_on_front' ) && $theme === get_option( 'georg_kloster_prihod_options' ) && $profile === get_option( 'gkpc_profile' ), 'Content-only import changed the live homepage, theme or profile.' );
$check( $menu === get_theme_mod( 'nav_menu_locations' )['primary'] && $owner_menu === serialize( wp_get_nav_menu_items( $menu ) ), 'Owner menu was changed.' );
$check( ! post_type_exists( 'gkpsch_service' ) && empty( $result['services'] ), 'Demo invented an unavailable Schedule integration.' );
foreach ( $result['media'] as $id ) {
    $check( is_file( get_attached_file( $id ) ) && wp_attachment_is_image( $id ) && is_array( wp_get_attachment_metadata( $id ) ) && '' !== get_post_field( 'post_excerpt', $id ), 'Image was linked remotely or lacks its source credit.' );
}
$check( ! str_contains( get_post_field( 'post_content', $result['pages']['schedule'] ), 'wp:gkpsch/' ), 'Standalone schedule page requires an absent plugin.' );
$check( str_contains( get_post_field( 'post_content', $result['pages']['home'] ), 'wp:gallery' ), 'Homepage has no editable native gallery.' );
$check( count( wp_get_nav_menu_items( $result['menu'] ) ) === count( $pack['navigation'] ), 'Demo navigation is incomplete.' );
foreach ( $result['posts'] as $key => $id ) {
    $check( in_array( $result['categories'][$pack['posts'][$key]['category']], wp_get_post_categories( $id ), true ), 'Demo post has no correct category.' );
    $check( $pack['posts'][$key]['excerpt'] === get_post_field( 'post_excerpt', $id ), 'Demo news lacks its own readable excerpt.' );
}
$after = $snapshot(); $again = Demo_Importer::run( $locale );
$check( $again === $result && $after === $snapshot(), 'Repeated import duplicates or rewrites content.' );
$check( is_wp_error( Demo_Importer::run( 'ru_RU' === $locale ? 'en_US' : 'ru_RU' ) ) && $after === $snapshot(), 'Switching packs mid-import replaces or duplicates content.' );
wp_update_post( array( 'ID' => $result['pages']['about'], 'post_content' => 'Owner revised this demo page.' ) );
wp_trash_post( $result['pages']['donations'] );
$partial = $result; $partial['complete'] = false; update_option( Demo_Importer::OPTION, $partial );
$after_edit = $snapshot(); $resumed = Demo_Importer::run( $locale );
$check( ! is_wp_error( $resumed ) && $after_edit === $snapshot(), 'Resume overwrites an edited demo page or recreates a trashed one.' );
$http_before = $http;
$applied = Demo_Importer::run( $locale, true );
$check( ! is_wp_error( $applied ) && ! empty( $applied['applied'] ), 'Applying selected demo layout failed.' );
$check( (int) get_option( 'page_on_front' ) === $result['pages']['home'] && 'page' === get_option( 'show_on_front' ), 'Explicit homepage choice was not applied.' );
$check( 'Owner material must remain.' === get_post_field( 'post_content', $owner_id ), 'Previous homepage content changed.' );
$check( 'Owner parish' === get_option( 'gkpc_profile' )['name'] && 'owner@example.org' === get_option( 'gkpc_profile' )['email'] && '' !== get_option( 'gkpc_profile' )['description'], 'Filled profile fields were replaced or empty fields were not filled.' );
$check( $owner_id === get_option( 'gkpc_pages' )['about']['id'], 'Owner page binding was replaced.' );
$saved = get_option( 'georg_kloster_prihod_options' );
$check( 'blue' === $saved['scheme'] && 'Owner motto' === $saved['hero_motto'] && 'Owner hero' === $saved['home']['sections']['hero']['title'] && false === $saved['home']['cards']['news']['enabled'], 'Applying demo replaces saved theme choices or disabled cards.' );
$check( count( $saved['home']['pages'] ) >= 8 && $saved['hero_image'] === $result['media']['monastery'] && $saved['home']['news_category'] === $result['categories']['news'], 'Empty theme fields are not filled from imported data.' );
$check( 'Owner parish' === $saved['profile']['name'] && 'owner@example.org' === $saved['profile']['email'], 'Independent theme profile did not preserve the existing Core identity.' );
$check( $menu === get_theme_mod( 'nav_menu_locations' )['primary'], 'Explicit import replaced an already assigned menu.' );
$check( $http === $http_before && 0 === $http && 0 === $mail, 'Importer contacted an external service or sent email.' );

// Existing real Schedule, its validation and actual service records.
activate_plugin( 'georg-kloster-prihod-schedule/georg-kloster-prihod-schedule.php' );
GeorgKlosterPrihodSchedule\Directory::register(); GeorgKlosterPrihodSchedule\Schedule::register();
wp_set_current_user( 0 ); wp_set_current_user( 1 );
$with_schedule = Demo_Importer::run( $locale, false, true );
$check( ! is_wp_error( $with_schedule ) && 3 === count( $with_schedule['services'] ?? array() ), 'Optional real Schedule demo failed: ' . ( is_wp_error( $with_schedule ) ? $with_schedule->get_error_message() : '' ) );
foreach ( $with_schedule['services'] as $id ) { $rule = get_post_meta( $id, '_gkpsch_service', true ); $check( is_array( $rule ) && $rule === GeorgKlosterPrihodSchedule\Schedule::validate( $rule ) && 'gold' === $rule['vestment_color'], 'Demo service did not use Schedule validation.' ); }
$scheduled_snapshot = $snapshot(); Demo_Importer::run( $locale, false, true );
$check( $scheduled_snapshot === $snapshot(), 'Repeating optional services duplicates them.' );
$check( $http === $http_before && 0 === $mail, 'Optional schedule import contacted a service or sent a message.' );
file_put_contents( __DIR__ . '/.demo-import-result.json', wp_json_encode( array( 'checks' => $checks, 'locale' => $locale, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'DEMO_IMPORT_OK' ) ) );
echo 'DEMO_IMPORT_OK ' . $checks;
