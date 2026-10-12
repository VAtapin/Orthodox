<?php
// Disposable WordPress database only; transport uses recorded public API responses.
$old_reminders = get_option( Prihod\Calendar_Reminders::OPTION, array() );
$old_profile = get_option( Prihod\Profile::OPTION );
prihod_assert( ! Prihod\Calendar_Reminders::options()['enabled'] && null === Prihod\Calendar_Reminders::next( new DateTimeImmutable( '2026-10-12 UTC' ) ), 'Reminder has no default sending time and is disabled until configured.' );
$invalid_reminder = Prihod\Calendar_Reminders::sanitize( array( 'enabled' => 1, 'weekday' => '0', 'time' => '' ) );
prihod_assert( ! $invalid_reminder['enabled'], 'Enabling without a valid time preserves the previous settings.' );
update_option( Prihod\Profile::OPTION, array_merge( Prihod\Profile::get(), array( 'timezone' => 'Europe/Berlin' ) ) );
update_option( Prihod\Calendar_Reminders::OPTION, array( 'enabled' => true, 'weekday' => 0, 'time' => '09:00' ) );
$next = Prihod\Calendar_Reminders::next( new DateTimeImmutable( '2026-10-24 12:00 UTC' ) );
prihod_assert( '2026-10-25T09:00:00+01:00' === $next->format( DATE_ATOM ), 'Weekly reminder uses parish wall-clock time across autumn DST.' );
update_option( Prihod\Calendar_Reminders::OPTION, array( 'enabled' => true, 'weekday' => 0, 'time' => '02:30' ) );
$next = Prihod\Calendar_Reminders::next( new DateTimeImmutable( '2026-03-28 12:00 UTC' ) );
prihod_assert( '2026-04-05 02:30' === $next->format( 'Y-m-d H:i' ), 'A nonexistent spring clock time is skipped rather than silently shifted.' );
update_option( Prihod\Calendar_Reminders::OPTION, array( 'enabled' => false, 'weekday' => 0, 'time' => '09:00' ) );
prihod_assert( ! wp_next_scheduled( Prihod\Calendar_Reminders::HOOK, array( '2026-04-05', 0 ) ), 'Disabling reminders clears their own WordPress Cron events.' );
$sample = json_decode( file_get_contents( __DIR__ . '/fixtures/calendar-days.json' ), true );
prihod_assert( 1 === count( Prihod\Calendar_Adapter::feasts( $sample['2026-10-14']['day'] ) ) && str_contains( Prihod\Calendar_Adapter::feasts( $sample['2026-10-14']['day'] )[0], 'Покров' ), 'Recorded real API uses commemoration ranks 0/1/2, including Pokrov, excluding ordinary saints.' );
prihod_assert( is_wp_error( Prihod\Calendar_Adapter::day( '2026-02-30' ) ), 'Invalid dates are rejected before any provider request.' );
$new_service_preview = (object) array( 'ID' => 0, 'post_status' => 'auto-draft' ); $_GET['prihod_calendar_date'] = '2026-10-14';
ob_start(); Prihod\Schedule_Admin::render( $new_service_preview ); $suggestion_form = ob_get_clean(); $_GET = array();
prihod_assert( str_contains( $suggestion_form, 'name="prihod_service[date]" value="2026-10-14"' ) && str_contains( $suggestion_form, 'name="prihod_service[time]" value=""' ), 'Reminder link prefills only its date; administrator must explicitly choose time and place.' );
$icon_rule = Prihod\Schedule::validate( array( 'kind' => 'regular', 'date' => '2026-10-01', 'weekdays' => array( 6 ), 'location_id' => $location, 'time' => '09:00', 'day_icon' => '1', 'exceptions' => array( '2026-10-17' => array( 'action' => 'replace', 'date' => '2026-10-18', 'time' => '10:00', 'location_id' => $location ) ) ) );
$icon_service = wp_insert_post( array( 'post_type' => 'prihod_service', 'post_title' => 'Recorded icon fixture', 'post_status' => 'publish' ) );
update_post_meta( $icon_service, '_prihod_service', $icon_rule );
$icon_events = Prihod\Occurrences::expand( array_merge( $icon_rule, array( 'id' => $icon_service, 'title' => 'Fixture', 'description' => '', 'timezone' => 'Europe/Berlin' ) ), new DateTimeImmutable( '2026-10-17 UTC' ), new DateTimeImmutable( '2026-10-19 UTC' ) );
$placeholder = Prihod\Schedule_Calendar::icon_placeholder( $icon_events[0] );
prihod_assert( str_contains( $placeholder, 'date=2026-10-18' ) && ! str_contains( $placeholder, 'date=2026-10-17' ), 'Moved service requests the actual new local date, never its original recurrence date.' );
prihod_assert( Prihod\Schedule_Calendar::public_date( '2026-10-18' ) && ! Prihod\Schedule_Calendar::public_date( '2026-10-17' ), 'Public icon endpoint permits only dates of published, visible opted-in occurrences.' );
$_GET = array( 'prihod_date' => '2026-10-18', 'prihod_view' => 'month' );
$calendar_month_html = Prihod\Public_View::render( 'schedule' ); $_GET = array();
prihod_assert( 31 === substr_count( $calendar_month_html, 'data-prihod-month-day=' ) && 31 === substr_count( $calendar_month_html, 'class="prihod-month-day-panel"' ), 'Mobile month has every day and an accessible full-width disclosure, including days without services.' );
prihod_assert( (bool) preg_match( '/id="prihod-month-[^"]*2026-10-18" open/', $calendar_month_html ) && str_contains( $calendar_month_html, 'Recorded icon fixture' ) && $icon_rule === get_post_meta( $icon_service, '_prihod_service', true ), 'Selected mobile day opens with complete real service details without altering its rule.' );
wp_update_post( array( 'ID' => $icon_service, 'post_status' => 'draft' ) );
prihod_assert( ! Prihod\Schedule_Calendar::public_date( '2026-10-18' ), 'Private draft service does not expose its icon endpoint.' );
wp_delete_post( $icon_service, true );
if ( file_exists( WP_PLUGIN_DIR . '/orthocal/orthocal.php' ) ) {
    activate_plugin( 'orthocal/orthocal.php' ); \Orthocal_Plugin::register();
    $calls = 0;
    $transport = static function ( $pre, $args, $url ) use ( $sample, &$calls ) {
        if ( ! str_contains( $url, '/api/v1/calendar/' ) ) { return $pre; }
        ++$calls; parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );
        if ( ( $query['date'] ?? '' ) === '2026-10-15' || ( $query['year'] ?? '' ) === '2027' ) { return new WP_Error( 'recorded_connection_failure', 'Disposable transport failure.' ); }
        $data = str_contains( $url, '/month' ) ? json_decode( file_get_contents( __DIR__ . '/fixtures/calendar-month.json' ), true ) : ( $sample[$query['date'] ?? ''] ?? array() );
        return array( 'headers' => array(), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $data ), 'cookies' => array() );
    };
    add_filter( 'pre_http_request', $transport, 10, 3 );
    $day = Prihod\Calendar_Adapter::day( '2026-10-14' );
    prihod_assert( ! is_wp_error( $day ) && str_contains( $day['feasts'][0], 'Покров' ) && ! empty( $day['icon']['media'] ), 'Actual orthocal adapter preserves feast title and its first authoritative icon via the native signed media endpoint.' );
    prihod_assert( null === Prihod\Calendar_Adapter::day( '2026-10-17' )['icon'] && null === Prihod\Calendar_Adapter::day( '2026-10-18' )['icon'], 'Missing icons in actual API samples stay absent instead of substituting an unrelated image.' );
    prihod_assert( is_wp_error( Prihod\Calendar_Adapter::day( '2026-10-15' ) ) && is_wp_error( Prihod\Calendar_Adapter::day( '2026-10-16' ) ), 'Provider transport and schema errors are returned without changing services.' );
    $request = new WP_REST_Request( 'GET', '/prihod/v1/calendar-day' ); $request->set_param( 'date', '2026-10-14' );
    wp_set_current_user( 0 );
    prihod_assert( 401 === rest_get_server()->dispatch( $request )->get_status(), 'Anonymous callers cannot access the editor calendar endpoint.' );
    wp_set_current_user( 1 );
    prihod_assert( 200 === rest_get_server()->dispatch( $request )->get_status(), 'Authorized schedule administrators can read the calendar endpoint.' );
    $week = Prihod\Calendar_Adapter::week( new DateTimeImmutable( '2026-10-11 Europe/Berlin' ) );
    prihod_assert( isset( $week['2026-10-14'] ) && ! isset( $week['2026-10-18'] ), 'Weekly range includes Pokrov and excludes the next week and ordinary saints.' );
    $emails = array();
    $mail = static function ( $pre, $attributes ) use ( &$emails ) { $emails[] = $attributes; return true; };
    add_filter( 'pre_wp_mail', $mail, 10, 2 );
    update_option( Prihod\Calendar_Reminders::OPTION, array( 'enabled' => true, 'weekday' => 0, 'time' => '09:00' ) );
    $now = new DateTimeImmutable( '2026-10-12 Europe/Berlin' );
    {
        $before = get_posts( array( 'post_type' => 'prihod_service', 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any' ) );
        Prihod\Calendar_Reminders::run( '2026-10-11', 0, $now ); Prihod\Calendar_Reminders::run( '2026-10-11', 0, $now );
        prihod_assert( 1 === count( $emails ) && get_option( 'admin_email' ) === $emails[0]['to'] && str_contains( $emails[0]['message'], 'prihod_calendar_date=2026-10-14' ), 'Reminder sends once to WordPress admin with authenticated editor links; transport is intercepted only in this disposable test.' );
        prihod_assert( $before === get_posts( array( 'post_type' => 'prihod_service', 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ), 'Reminder never creates or changes services before administrator confirmation.' );
    }
    delete_option( 'prihod_calendar_last_sent' ); delete_option( 'prihod_calendar_last_attempt' );
    $failed_mail = static function ( $pre, $attributes ) use ( &$emails ) { $emails[] = $attributes; return false; };
    remove_filter( 'pre_wp_mail', $mail, 10 ); add_filter( 'pre_wp_mail', $failed_mail, 10, 2 );
    Prihod\Calendar_Reminders::run( '2026-10-11', 0, $now ); Prihod\Calendar_Reminders::run( '2026-10-11', 0, $now );
    prihod_assert( 2 === count( $emails ) && 'mail_failed' === get_option( 'prihod_calendar_reminder_status' ), 'Uncertain mail failure is not retried automatically and records an honest status.' );
    Prihod\Calendar_Reminders::run( '2027-01-03', 0, new DateTimeImmutable( '2027-01-04 Europe/Berlin' ) );
    prihod_assert( 2 === count( $emails ) && 'calendar_unavailable' === get_option( 'prihod_calendar_reminder_status' ) && wp_next_scheduled( Prihod\Calendar_Reminders::HOOK, array( '2027-01-03', 1 ) ), 'An unavailable provider schedules a bounded retry, sends no mail and never creates a guessed service.' );
    remove_filter( 'pre_wp_mail', $failed_mail, 10 );
    remove_filter( 'pre_wp_mail', $mail, 10 ); remove_filter( 'pre_http_request', $transport, 10 );
    deactivate_plugins( 'orthocal/orthocal.php' );
} else { prihod_assert( is_wp_error( Prihod\Calendar_Adapter::day( '2026-10-14' ) ), 'Without calendar provider the schedule remains usable and adapter reports unavailable.' ); }
update_option( Prihod\Calendar_Reminders::OPTION, $old_reminders );
update_option( Prihod\Profile::OPTION, $old_profile );
wp_clear_scheduled_hook( Prihod\Calendar_Reminders::HOOK );
delete_option( 'prihod_calendar_last_sent' ); delete_option( 'prihod_calendar_last_attempt' ); delete_option( 'prihod_calendar_reminder_status' );
$_POST = array(); $_GET = array();
