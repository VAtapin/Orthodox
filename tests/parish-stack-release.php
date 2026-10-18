<?php
require_once '/wordpress/wp-load.php';

function gkp_stack_assert( bool $condition, string $message ): void {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}

gkp_stack_assert( 'georg-kloster-prihod' === get_stylesheet(), 'Theme not active.' );
gkp_stack_assert( class_exists( 'GeorgKlosterPrihodCore\Profile' ), 'Core not active.' );
gkp_stack_assert( class_exists( 'GeorgKlosterPrihodSocial\Plugin' ), 'Social not active.' );
gkp_stack_assert( class_exists( 'GeorgKlosterPrihodSchedule\Schedule' ), 'Schedule not active.' );
gkp_stack_assert( WP_Block_Type_Registry::get_instance()->is_registered( 'gkps/contact-form' ), 'Contact block missing.' );
gkp_stack_assert( WP_Block_Type_Registry::get_instance()->is_registered( 'gkpsch/schedule' ), 'Schedule block missing.' );

update_option( 'gkpc_profile', array( 'type' => 'monastery', 'name' => 'Sample monastery', 'description' => 'A place of prayer.' ) );
gkp_stack_assert( 'monastery' === prihod_theme_profile()['type'], 'Theme did not read Core profile.' );
add_filter( 'wpml_translate_single_string', static function ( $value, $context, $name ) {
    return 'georg-kloster-prihod-core' === $context && 'profile-description' === $name ? 'Ein Ort des Gebets.' : $value;
}, 10, 3 );
gkp_stack_assert( 'Ein Ort des Gebets.' === GeorgKlosterPrihodCore\Profile::get()['description'], 'Core profile translation was ignored.' );
add_filter( 'wpml_translate_single_string', static function ( $value, $context, $name ) {
    return 'georg-kloster-prihod' === $context && 'site-tagline' === $name ? 'Orthodoxes Kloster' : $value;
}, 10, 3 );
gkp_stack_assert( 'Orthodoxes Kloster' === prihod_theme_tagline(), 'Theme tagline translation was ignored.' );
$section = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Visit us' ), true );
gkp_stack_assert( ! is_wp_error( $section ), 'Section page failed.' );
update_option( 'gkpc_pages', array( 'schedule' => array( 'id' => $section ) ) );
gkp_stack_assert( get_permalink( $section ) === prihod_theme_url( 'schedule' ), 'Theme did not read Core section links.' );
gkp_stack_assert( array() === GeorgKlosterPrihodCore\Page_Links::sanitize( array( 'schedule' => array( 'id' => 999999 ) ) ), 'Invalid section was accepted.' );

$place = wp_insert_post( array( 'post_type' => 'gkpsch_location', 'post_status' => 'publish', 'post_title' => 'Main church' ), true );
gkp_stack_assert( ! is_wp_error( $place ), 'Service place failed.' );
$date = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+1 day' )->format( 'Y-m-d' );
$rule = GeorgKlosterPrihodSchedule\Schedule::validate( array( 'kind' => 'single', 'date' => $date, 'time' => '09:00', 'location_id' => $place, 'vestment_color' => 'auto' ) );
$service = wp_insert_post( array( 'post_type' => 'gkpsch_service', 'post_status' => 'publish', 'post_title' => 'Morning service' ), true );
gkp_stack_assert( ! is_wp_error( $service ), 'Service creation failed.' );
update_post_meta( $service, '_gkpsch_service', $rule );
$compact_service = prihod_theme_compact_services();
gkp_stack_assert( str_contains( $compact_service, 'Morning service' ), 'Theme did not render Schedule service.' );
gkp_stack_assert( ! str_contains( $compact_service, 'Main church' ) && ! str_contains( $compact_service, 'class="prihod-icon"' ) && ! str_contains( $compact_service, 'has-place' ), 'Compact service must hide the only place and mark the title for centering.' );
gkp_stack_assert( str_contains( $compact_service, 'gkpsch-service-compact' ) && str_contains( $compact_service, 'class="prihod-service-color-data" hidden' ) && str_contains( $compact_service, 'data-color="auto"' ) && str_contains( $compact_service, 'data-color-only="1"' ), 'Compact service must use the schedule colour resolver without extra visible details.' );
$rule['vestment_color'] = 'blue'; update_post_meta( $service, '_gkpsch_service', $rule );
gkp_stack_assert( str_contains( prihod_theme_compact_services(), 'data-vestment-color="blue"' ), 'Manual compact service colours must render without JavaScript.' );
$rule['vestment_color'] = ''; update_post_meta( $service, '_gkpsch_service', $rule );
gkp_stack_assert( ! str_contains( prihod_theme_compact_services(), 'data-vestment-color=' ), 'Neutral compact services must not invent a colour.' );
$rule['vestment_color'] = 'auto'; update_post_meta( $service, '_gkpsch_service', $rule );
gkp_stack_assert( ! str_contains( do_shortcode( '[gkpsch_next_service]' ), 'Main church' ), 'Schedule block must hide the only place.' );
add_filter( 'wpml_translate_single_string', static function ( $value, $context, $name ) use ( $service ) {
    return 'georg-kloster-prihod-schedule' === $context && 'service-' . $service === $name ? 'Morgengottesdienst' : $value;
}, 10, 3 );
gkp_stack_assert( str_contains( prihod_theme_compact_services(), 'Morgengottesdienst' ), 'Schedule title translation was ignored.' );
$second_place = wp_insert_post( array( 'post_type' => 'gkpsch_location', 'post_status' => 'publish', 'post_title' => 'Second church' ), true );
gkp_stack_assert( ! is_wp_error( $second_place ), 'Second service place failed.' );
gkp_stack_assert( str_contains( prihod_theme_compact_services(), 'Main church' ) && str_contains( prihod_theme_compact_services(), 'has-place' ) && 1 === substr_count( prihod_theme_compact_services(), 'class="prihod-icon"' ), 'Compact service must distinguish multiple places and keep room below the title.' );
gkp_stack_assert( str_contains( do_shortcode( '[gkpsch_next_service]' ), 'Main church' ), 'Schedule block must distinguish multiple places.' );

update_option( 'gkps_form', array( 'enabled' => true, 'title' => 'Contact', 'submit' => 'Send' ) );
gkp_stack_assert( str_contains( do_shortcode( '[gkps_contact_form title="Write to us"]' ), 'Write to us' ), 'Social form did not render.' );
GeorgKlosterPrihodSocial\Request_Form::enqueue_styles();
gkp_stack_assert( wp_style_is( 'gkps-request-form', 'enqueued' ), 'Social form did not load its public stylesheet.' );

echo 'GKPS_STACK_OK ' . get_bloginfo( 'version' ) . ' PHP ' . PHP_VERSION;
