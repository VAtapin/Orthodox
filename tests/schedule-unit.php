<?php
use Prihod\Occurrences;
use Prihod\Schedule;
use Prihod\Modules;

check( ! Occurrences::date( '2026-02-30' ) && Occurrences::date( '2028-02-29' ), 'Calendar dates are strictly validated.' );
check( null === Occurrences::instant( '2026-03-29', '02:30', 'Europe/Berlin' ), 'Spring DST gap is rejected.' );
check( '2026-10-25T00:30:00+00:00' === Occurrences::instant( '2026-10-25', '02:30', 'Europe/Berlin' )->setTimezone( new DateTimeZone( 'UTC' ) )->format( DATE_ATOM ), 'Fall fold uses first occurrence.' );
$rule = array_merge( Schedule::defaults(), array( 'id' => 10, 'title' => 'Liturgy', 'description' => '', 'kind' => 'regular', 'date' => '2026-03-01', 'weekdays' => array( 7 ), 'time' => '02:30', 'timezone' => 'Europe/Berlin', 'location_id' => 1 ) );
$from = new DateTimeImmutable( '2026-03-22 00:00:00 Europe/Berlin' );
$to = new DateTimeImmutable( '2026-04-06 00:00:00 Europe/Berlin' );
$events = Occurrences::expand( $rule, $from, $to );
check( 2 === count( $events ) && '2026-04-05' === $events[1]['original_date'], 'Recurrence skips gap without shifting to a different time.' );
$rule['time'] = '09:00';
$rule['exceptions'] = array( '2026-03-22' => array( 'action' => 'cancel' ), '2026-03-29' => array( 'action' => 'replace', 'date' => '2026-03-30', 'time' => '11:00', 'location_id' => 4 ) );
$events = Occurrences::expand( $rule, $from, $to );
check( 3 === count( $events ) && $events[0]['cancelled'] && '2026-03-30' === $events[1]['start']->format( 'Y-m-d' ) && 4 === $events[1]['location_id'], 'Cancellation and relocation affect only their original dates.' );
$rule['exceptions']['2026-03-01'] = array( 'action' => 'replace', 'date' => '2026-04-01', 'time' => '10:00' );
check( 4 === count( Occurrences::expand( $rule, $from, $to ) ), 'Moved event outside original query window enters destination window.' );
$single = array_merge( $rule, array( 'kind' => 'holiday', 'date' => '2026-03-23', 'time' => '23:00', 'end' => '01:00', 'exceptions' => array() ) );
$event = Occurrences::expand( $single, $from, $to )[0];
check( '2026-03-24 01:00' === $event['end']->format( 'Y-m-d H:i' ), 'Services may finish after midnight.' );
$validated = Schedule::validate( array_merge( Schedule::defaults(), array( 'date' => '2026-04-05', 'location_id' => 1 ) ) );
check( 1 === $validated['location_id'], 'Valid schedule persists location ID.' );
foreach ( array( array( 'location_id' => 2 ), array( 'date' => '2026-02-30' ), array( 'kind' => 'regular' ), array( 'timezone' => '+02:00' ), array( 'exceptions' => array( '2026-04-06' => array( 'action' => 'cancel' ) ) ) ) as $change ) {
    try { Schedule::validate( array_merge( $validated, $change ) ); check( false, 'Invalid schedule should throw.' ); }
    catch ( InvalidArgumentException $error ) { check( true, 'Invalid schedule rejected without partial writes.' ); }
}
$GLOBALS['options']['prihod_modules'] = Modules::sanitize( array( 'requests' => 1 ) );
check( ! Modules::enabled( 'schedule' ) && Modules::enabled( 'requests' ), 'Module toggles are independent.' );
unset( $GLOBALS['options']['prihod_modules'] );
