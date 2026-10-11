<?php
/** Run only inside the disposable WordPress Playground test site. */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

$assertions = 0;
function prihod_assert( bool $condition, string $message ): void {
    global $assertions;
    ++$assertions;
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

wp_set_current_user( 1 );
$before = array(
    'pages' => wp_count_posts( 'page' ), 'front' => get_option( 'page_on_front' ),
    'theme' => get_option( 'stylesheet' ), 'timezone' => get_option( 'timezone_string' ),
);
$result = activate_plugin( 'prihod/prihod.php' );
prihod_assert( ! is_wp_error( $result ), 'Plugin activates without an error.' );
wp_set_current_user( 0 );
wp_set_current_user( 1 );
// Activation happens after plugins_loaded in this request; initialize hooks explicitly.
Prihod\Plugin::boot();
Prihod\Directory::register();
prihod_assert( current_user_can( 'manage_prihod' ), 'Initial administrator receives capability.' );
prihod_assert( ! get_role( 'editor' )->has_cap( 'view_prihod_requests' ), 'Editor cannot view requests.' );
prihod_assert( $before['pages'] == wp_count_posts( 'page' ) && $before['front'] === get_option( 'page_on_front' )
    && $before['theme'] === get_option( 'stylesheet' ) && $before['timezone'] === get_option( 'timezone_string' ), 'Activation preserves site structure and settings.' );

$admin = new Prihod\Admin( Prihod\Plugin::boot()->integrations() );
$admin->settings();
update_option( 'prihod_profile', array( 'name' => '<b>Parish</b>', 'timezone' => 'Europe/Berlin', 'email' => 'office@example.org' ) );
prihod_assert( 'Parish' === Prihod\Profile::get()['name'], 'WordPress option pipeline sanitizes profile.' );
update_option( 'prihod_profile', array( 'timezone' => '+02:00', 'email' => 'invalid' ) );
prihod_assert( 'Europe/Berlin' === Prihod\Profile::get()['timezone'] && 'office@example.org' === Prihod\Profile::get()['email'], 'Invalid updates preserve prior fields in real Settings API pipeline.' );
prihod_assert( 'manage_prihod' === apply_filters( 'option_page_capability_prihod_profile', 'manage_options' ), 'Settings save checks parish capability.' );

$location = wp_insert_post( array( 'post_type' => 'prihod_location', 'post_title' => 'Chapel', 'post_status' => 'publish' ), true );
prihod_assert( ! is_wp_error( $location ) && $location > 0, 'Location persists as a WordPress record.' );
$_POST = array( 'prihod_directory_nonce' => wp_create_nonce( 'prihod_directory_' . $location ), 'prihod_details' => array( 'address' => '<b>Berlin</b>', 'timezone' => 'Europe/Berlin' ) );
Prihod\Directory::save( $location );
prihod_assert( 'Berlin' === Prihod\Directory::details( $location )['address'], 'Location metadata saves with nonce and sanitization.' );
$_POST = array();
$clergy = wp_insert_post( array( 'post_type' => 'prihod_clergy', 'post_title' => 'Priest', 'post_status' => 'publish' ), true );
$_POST = array( 'prihod_directory_nonce' => wp_create_nonce( 'prihod_directory_' . $clergy ), 'prihod_details' => array( 'rank' => 'Priest', 'location_ids' => array( (string) $location ) ) );
Prihod\Directory::save( $clergy );
prihod_assert( array( $location ) === Prihod\Directory::details( $clergy )['location_ids'], 'Clergy links to actual location ID.' );

$editor_id = wp_insert_user( array( 'user_login' => 'test_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
wp_set_current_user( $editor_id );
prihod_assert( ! current_user_can( 'manage_prihod' ) && ! current_user_can( 'edit_post', $location ), 'Editor cannot manage or edit directory records.' );
$_POST = array( 'prihod_directory_nonce' => wp_create_nonce( 'prihod_directory_' . $location ), 'prihod_details' => array( 'address' => 'Unauthorized' ) );
Prihod\Directory::save( $location );
prihod_assert( 'Berlin' === Prihod\Directory::details( $location )['address'], 'Editor cannot write even with valid nonce.' );
$staff_id = wp_insert_user( array( 'user_login' => 'test_staff', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$staff = new WP_User( $staff_id );
$staff->add_cap( 'manage_prihod' );
wp_set_current_user( $staff_id );
prihod_assert( current_user_can( 'edit_post', $location ), 'Directory object access follows assigned capability.' );
prihod_assert( current_user_can( get_post_type_object( 'prihod_location' )->cap->publish_posts ), 'Staff capability grants publish access without administrator role.' );
$_POST = array();
wp_set_current_user( 1 );
$admin->menu();
ob_start();
$admin->profile();
$html = ob_get_clean();
prihod_assert( str_contains( $html, 'prihod_profile[name]' ) && str_contains( $html, '_wpnonce' ), 'Profile renders fields and nonce in WordPress.' );

require __DIR__ . '/schedule-smoke.php';
require __DIR__ . '/requests-smoke.php';
require __DIR__ . '/wizard-smoke.php';
require __DIR__ . '/theme-smoke.php';
require __DIR__ . '/integrations-smoke.php';
require __DIR__ . '/dashboard-smoke.php';
require __DIR__ . '/admin-navigation-smoke.php';
deactivate_plugins( 'prihod/prihod.php' );
prihod_assert( get_post( $location ) && 'Parish' === get_option( 'prihod_profile' )['name'], 'Deactivation retains parish records.' );
uninstall_plugin( 'prihod/prihod.php' );
prihod_assert( get_post( $clergy ) && get_post_meta( $clergy, '_prihod_details', true )['location_ids'] === array( $location ), 'Ordinary uninstall retains data.' );
Prihod\Maintenance::start( false );
prihod_assert( ! Prihod\Modules::enabled( 'requests' ), 'Confirmed purge pauses incoming requests.' );
Prihod\Maintenance::step();
prihod_assert( ! get_post( $location ) && ! get_post( $clergy ) && get_post( $existing ), 'Explicit background purge removes parish data but preserves reused site pages.' );
echo "WORDPRESS_SMOKE_OK: {$assertions} assertions; WordPress " . get_bloginfo( 'version' ) . '; PHP ' . PHP_VERSION . "\n";
file_put_contents( __DIR__ . '/.wordpress-result.json', wp_json_encode( array(
    'status' => 'WORDPRESS_SMOKE_OK', 'assertions' => $assertions,
    'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION,
) ) );
