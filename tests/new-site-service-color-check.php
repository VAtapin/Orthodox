<?php
/** Read-only check of the theme's actual next service and calendar colour source. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This check is restricted to new.georg-kloster.ru.' );
}
$events = GeorgKlosterPrihodSchedule\Schedule::next( 0, 1 );
if ( ! $events ) { throw new RuntimeException( 'No upcoming service to verify.' ); }
$event = $events[0];
$html = prihod_theme_compact_services();
foreach ( array( 'gkpsch-service-compact', 'class="prihod-service-color-data" hidden', 'data-color-only="1"', 'data-calendar-date="' . $event['start']->format( 'Y-m-d' ) . '"', 'data-color="' . esc_attr( $event['vestment_color'] ) . '"' ) as $expected ) {
    if ( ! str_contains( $html, $expected ) ) { throw new RuntimeException( 'Missing compact service colour data: ' . $expected ); }
}
$places = get_posts( array( 'post_type' => 'gkpsch_location', 'post_status' => 'publish', 'numberposts' => 2, 'fields' => 'ids' ) );
if ( str_contains( $html, 'has-place' ) !== ( count( $places ) > 1 ) ) { throw new RuntimeException( 'Compact service alignment does not match its place label.' ); }
$month = GeorgKlosterPrihodSchedule\Calendar_Adapter::month( $event['start']->format( 'Y-m-01' ) );
if ( is_wp_error( $month ) || ! isset( $month['days'][$event['start']->format( 'Y-m-d' )]['color'] ) ) {
    throw new RuntimeException( 'The actual service calendar colour is unavailable.' );
}
WP_CLI::success( 'Compact service colour hook and place alignment verified; calendar colour: ' . $month['days'][$event['start']->format( 'Y-m-d' )]['color'] );
