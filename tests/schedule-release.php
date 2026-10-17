<?php
require_once '/wordpress/wp-load.php';

function gkpsch_assert( bool $condition, string $message ): void {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}

gkpsch_assert( class_exists( 'GeorgKlosterPrihodSchedule\\Schedule' ), 'Schedule did not load.' );
gkpsch_assert( ! class_exists( 'GeorgKlosterPrihodCore\\Profile' ) && ! class_exists( 'Prihod\\Plugin' ), 'Schedule depends on another project plugin.' );
gkpsch_assert( post_type_exists( 'gkpsch_location' ) && post_type_exists( 'gkpsch_service' ), 'Schedule post types missing.' );
gkpsch_assert( WP_Block_Type_Registry::get_instance()->is_registered( 'gkpsch/schedule' ), 'Schedule block missing.' );
gkpsch_assert( 'UTC' === GeorgKlosterPrihodSchedule\Profile::get()['timezone'] || wp_timezone_string() === GeorgKlosterPrihodSchedule\Profile::get()['timezone'], 'WordPress time zone fallback failed.' );

$place = wp_insert_post( array( 'post_type' => 'gkpsch_location', 'post_status' => 'publish', 'post_title' => 'Main church' ), true );
gkpsch_assert( ! is_wp_error( $place ), 'Place creation failed.' );
$date = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+1 day' )->format( 'Y-m-d' );
$rule = GeorgKlosterPrihodSchedule\Schedule::validate( array( 'kind' => 'single', 'date' => $date, 'time' => '09:00', 'location_id' => $place ) );
$service = wp_insert_post( array( 'post_type' => 'gkpsch_service', 'post_status' => 'publish', 'post_title' => 'Morning service' ), true );
gkpsch_assert( ! is_wp_error( $service ), 'Service creation failed.' );
update_post_meta( $service, '_gkpsch_service', $rule );
$from = new DateTimeImmutable( 'today', wp_timezone() );
$events = GeorgKlosterPrihodSchedule\Schedule::query( $from, $from->modify( '+7 days' ) );
gkpsch_assert( 1 === count( $events ) && 'Morning service' === $events[0]['title'], 'Service query failed.' );
gkpsch_assert( str_contains( do_shortcode( '[gkpsch_next_service]' ), 'Morning service' ), 'Service shortcode failed.' );
gkpsch_assert( is_wp_error( GeorgKlosterPrihodSchedule\Calendar_Adapter::day( $date ) ), 'Missing calendar must fail safely.' );

echo 'GKPSCH_RELEASE_OK ' . get_bloginfo( 'version' ) . ' PHP ' . PHP_VERSION;
