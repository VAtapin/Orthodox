<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

/** Calendar arithmetic without WordPress dependencies. End of query range is exclusive. */
final class Occurrences {
    public static function date( string $date ): bool {
        $parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
        return $parsed && $parsed->format( 'Y-m-d' ) === $date;
    }
    public static function time( string $time ): bool {
        return (bool) preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time );
    }
    /** Gap: reject. Fold: choose earliest real instant (first occurrence). */
    public static function instant( string $date, string $time, string $timezone ): ?\DateTimeImmutable {
        if ( ! self::date( $date ) || ! self::time( $time ) ) { return null; }
        $zone = new \DateTimeZone( $timezone );
        $wall = new \DateTimeImmutable( $date . ' ' . $time, new \DateTimeZone( 'UTC' ) );
        $offsets = array( $zone->getOffset( $wall ) );
        foreach ( $zone->getTransitions( $wall->getTimestamp() - 172800, $wall->getTimestamp() + 172800 ) ?: array() as $transition ) {
            $offsets[] = $transition['offset'];
        }
        $candidates = array();
        foreach ( array_unique( $offsets ) as $offset ) {
            $candidate = $wall->setTimestamp( $wall->getTimestamp() - $offset )->setTimezone( $zone );
            if ( $candidate->format( 'Y-m-d H:i' ) === $date . ' ' . $time ) { $candidates[] = $candidate; }
        }
        usort( $candidates, fn( $a, $b ) => $a <=> $b );
        return $candidates[0] ?? null;
    }
    public static function matches( array $rule, string $date ): bool {
        if ( ! self::date( $date ) || $date < $rule['date'] ) { return false; }
        if ( 'regular' !== $rule['kind'] ) { return $date === $rule['date']; }
        if ( $rule['until'] && $date > $rule['until'] ) { return false; }
        $day = (int) ( new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) )->format( 'N' );
        return in_array( $day, $rule['weekdays'], true );
    }
    public static function expand( array $rule, \DateTimeImmutable $from, \DateTimeImmutable $to ): array {
        if ( $to <= $from || $to->getTimestamp() - $from->getTimestamp() > 370 * 86400 ) {
            throw new \InvalidArgumentException( 'Schedule range must be at most 370 days.' );
        }
        $zone = new \DateTimeZone( $rule['timezone'] );
        $cursor = $from->setTimezone( $zone )->setTime( 0, 0 );
        $last = $to->setTimezone( $zone )->format( 'Y-m-d' );
        $dates = array();
        while ( $cursor->format( 'Y-m-d' ) <= $last ) {
            $dates[ $cursor->format( 'Y-m-d' ) ] = true;
            $cursor = $cursor->modify( '+1 day' );
        }
        // Moved occurrences can enter this range from any original date.
        foreach ( array_keys( $rule['exceptions'] ) as $date ) { $dates[ $date ] = true; }
        $result = array();
        foreach ( array_keys( $dates ) as $original ) {
            $event = self::occurrence( $rule, $original );
            if ( $event && $event['start'] >= $from && $event['start'] < $to ) { $result[] = $event; }
        }
        return $result;
    }
    /** Resolve one original date with the same clock/DST/override rules everywhere. */
    public static function occurrence( array $rule, string $original ): ?array {
        if ( ! self::matches( $rule, $original ) ) { return null; }
        $override = $rule['exceptions'][$original] ?? array(); $cancelled = 'cancel' === ( $override['action'] ?? '' );
        $date = $cancelled ? $original : ( $override['date'] ?? $original ); $time = $cancelled ? $rule['time'] : ( $override['time'] ?? $rule['time'] );
        $start = self::instant( $date, $time, $override['timezone'] ?? $rule['timezone'] );
        if ( ! $start ) { return null; }
        $end_time = $override['end'] ?? $rule['end']; $end = null;
        if ( $end_time ) {
            $end_date = $end_time <= $time ? ( new \DateTimeImmutable( $date ) )->modify( '+1 day' )->format( 'Y-m-d' ) : $date;
            $end = self::instant( $end_date, $end_time, $start->getTimezone()->getName() );
            if ( ! $end || $end <= $start ) { return null; }
        }
        return array( 'id' => $rule['id'] . ':' . $original, 'service_id' => $rule['id'], 'original_date' => $original, 'title' => $rule['title'], 'description' => $rule['description'], 'kind' => $rule['kind'], 'start' => $start, 'end' => $end, 'cancelled' => $cancelled, 'moved' => ! $cancelled && ! empty( $override ), 'location_id' => $override['location_id'] ?? $rule['location_id'] );
    }
}
