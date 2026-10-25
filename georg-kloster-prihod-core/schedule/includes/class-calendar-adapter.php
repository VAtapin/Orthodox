<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

/** Use the calendar provider's connection, cache and authoritative event/icon order. */
final class Calendar_Adapter {
    public static function language(): string {
        $lang = substr( determine_locale(), 0, 2 );
        return in_array( $lang, array( 'ru', 'de', 'pl', 'uk' ), true ) ? $lang : 'ru';
    }
    public static function request( string $path, array $query ) {
        return Calendar_Provider::request( $path, $query );
    }
    public static function feasts( array $day ): array {
        $result = array();
        foreach ( $day['events'] ?? array() as $event ) {
            if ( ! is_array( $event ) || 'commemoration' !== ( $event['category'] ?? '' ) || ! isset( $event['typeCode'] ) || ! is_numeric( $event['typeCode'] ) || $event['typeCode'] < 0 || $event['typeCode'] > 2 || ! is_string( $event['title'] ?? null ) ) { continue; }
            $title = sanitize_text_field( $event['title'] );
            if ( $title ) { $result[] = $title; }
        }
        return array_values( array_unique( $result ) );
    }
    /** The provider orders commemorations by precedence; skip generated weekday labels. */
    public static function primary_commemoration( array $day ): array {
        foreach ( $day['events'] ?? array() as $event ) {
            if ( ! is_array( $event ) || 'commemoration' !== ( $event['category'] ?? '' ) || ! is_numeric( $event['typeCode'] ?? null ) || (int) $event['typeCode'] < 0 ) { continue; }
            $title = is_string( $event['title'] ?? null ) ? sanitize_text_field( $event['title'] ) : '';
            if ( ! $title ) { continue; }
            $short = is_string( $event['veryShortTitle'] ?? null ) ? sanitize_text_field( $event['veryShortTitle'] ) : '';
            $description = is_string( $event['description'] ?? null ) ? sanitize_text_field( $event['description'] ) : '';
            return array( 'title' => $title, 'short' => $short ?: $title, 'description' => $description );
        }
        return array( 'title' => '', 'short' => '', 'description' => '' );
    }
    public static function commemorations( array $day ): array {
        $titles = array();
        foreach ( $day['events'] ?? array() as $event ) {
            if ( ! is_array( $event ) || 'commemoration' !== ( $event['category'] ?? '' ) || ! is_numeric( $event['typeCode'] ?? null ) || (int) $event['typeCode'] < 0 || ! is_string( $event['title'] ?? null ) ) { continue; }
            $title = sanitize_text_field( is_string( $event['veryShortTitle'] ?? null ) && '' !== trim( $event['veryShortTitle'] ) ? $event['veryShortTitle'] : $event['title'] );
            if ( $title && ! in_array( $title, $titles, true ) ) { $titles[] = $title; }
            if ( count( $titles ) >= 5 ) { break; }
        }
        return $titles;
    }
    public static function day( string $date ) {
        if ( ! Occurrences::date( $date ) ) { return new \WP_Error( 'date', __( 'Invalid date.', 'georg-kloster-prihod-schedule' ), array( 'status' => 400 ) ); }
        $data = self::request( 'day', array( 'date' => $date ) );
        if ( is_wp_error( $data ) ) { return $data; }
        if ( ! is_array( $data['day'] ?? null ) || ( $data['day']['date'] ?? '' ) !== $date || ! is_array( $data['day']['events'] ?? null ) ) { return new \WP_Error( 'calendar_schema', __( 'The calendar returned invalid data.', 'georg-kloster-prihod-schedule' ), array( 'status' => 502 ) ); }
        $day = $data['day']; $icon = null;
        foreach ( $day['icons'] ?? array() as $item ) {
            if ( ! is_array( $item ) ) { continue; }
            $source = $item['images'][0]['url'] ?? $item['imageUrl'] ?? '';
            $url = Calendar_Provider::icon_url( $source );
            if ( ! $url ) { continue; }
            $icon = array( 'url' => $url, 'fullUrl' => Schedule_Calendar::image_url( $date ), 'alt' => sanitize_text_field( $item['title'] ?? '' ), 'attribution' => sanitize_text_field( $item['attribution'] ?? '' ), 'media' => '', 'fullMedia' => '' );
            break;
        }
        $feasts = self::feasts( $day );
        return array( 'date' => $date, 'feasts' => $feasts, 'icon' => $icon ?: Calendar_Icons::fallback( $date ), 'recommendation' => Calendar_Colors::day( $day ) );
    }
    /** One cached provider month; no per-day API calls or saved service colors. */
    public static function month( string $date ) {
        if ( ! Occurrences::date( $date ) || '01' !== substr( $date, -2 ) ) { return new \WP_Error( 'date', __( 'Invalid date.', 'georg-kloster-prihod-schedule' ), array( 'status' => 400 ) ); }
        $data = self::request( 'month', array( 'year' => (int) substr( $date, 0, 4 ), 'month' => (int) substr( $date, 5, 2 ) ) );
        if ( is_wp_error( $data ) ) { return $data; }
        if ( ! is_array( $data['days'] ?? null ) ) { return new \WP_Error( 'calendar_schema', __( 'The calendar returned invalid data.', 'georg-kloster-prihod-schedule' ), array( 'status' => 502 ) ); }
        $days = array();
        foreach ( $data['days'] as $day ) {
            $key = is_array( $day ) ? ( $day['date'] ?? '' ) : '';
            if ( ! is_string( $key ) || ! Occurrences::date( $key ) || substr( $key, 0, 7 ) !== substr( $date, 0, 7 ) ) { continue; }
            $days[$key] = array( 'feasts' => self::feasts( $day ), 'color' => Calendar_Colors::day( $day )['color'] ?? '', 'primary' => self::primary_commemoration( $day ), 'commemorations' => self::commemorations( $day ), 'oldDay' => is_string( $day['oldStyleDate'] ?? null ) ? (int) substr( $day['oldStyleDate'], -2 ) : 0, 'food' => is_string( $day['foodLabel'] ?? null ) ? sanitize_text_field( $day['foodLabel'] ) : '' ) + self::liturgical( $day );
        }
        return array( 'date' => $date, 'days' => $days );
    }
    /** API ranks 0–2: great; 3: vigil. Never infer a mark from a saint's name. */
    public static function liturgical( array $day ): array {
        $memories = array(); $primary = null; $rank = 99;
        foreach ( $day['events'] ?? array() as $event ) {
            if ( ! is_array( $event ) || 'commemoration' !== ( $event['category'] ?? '' ) || ! isset( $event['typeCode'] ) || ! is_numeric( $event['typeCode'] ) ) { continue; }
            $code = (int) $event['typeCode'];
            if ( $code < 0 || (float) $event['typeCode'] !== (float) $code || ! is_string( $event['title'] ?? null ) ) { continue; }
            $title = sanitize_text_field( $event['title'] ?? '' );
            if ( $title ) { $memories[] = $title; }
            $mark = $event['typikonMark'] ?? null;
            if ( ! is_array( $mark ) || ! is_string( $mark['label'] ?? null ) || ! is_string( $mark['svgSource'] ?? null ) ) { continue; }
            $source = $mark['svgSource'];
            $url = Calendar_Provider::mark_url( $source );
            if ( ! $url ) { continue; }
            if ( $code < $rank ) {
                $rank = $code;
                $primary = array( 'label' => sanitize_text_field( $mark['label'] ), 'url' => $url, 'media' => $url );
            }
        }
        return array( 'memories' => array_values( array_unique( $memories ) ), 'typikon' => $primary );
    }
    public static function week( \DateTimeImmutable $from ) {
        $to = $from->modify( '+7 days' ); $months = array(); $result = array();
        for ( $date = $from; $date < $to; $date = $date->modify( '+1 day' ) ) { $months[$date->format( 'Y-m' )] = $date; }
        foreach ( $months as $month ) {
            $data = self::request( 'month', array( 'year' => (int) $month->format( 'Y' ), 'month' => (int) $month->format( 'n' ) ) );
            if ( is_wp_error( $data ) ) { return $data; }
            if ( ! is_array( $data['days'] ?? null ) ) { return new \WP_Error( 'calendar_schema', __( 'The calendar returned invalid data.', 'georg-kloster-prihod-schedule' ) ); }
            foreach ( $data['days'] as $day ) {
                if ( ! is_array( $day ) || ! is_string( $day['date'] ?? null ) || ! Occurrences::date( $day['date'] ) || $day['date'] < $from->format( 'Y-m-d' ) || $day['date'] >= $to->format( 'Y-m-d' ) ) { continue; }
                $feasts = self::feasts( $day );
                if ( $feasts ) { $result[$day['date']] = $feasts; }
            }
        }
        ksort( $result ); return $result;
    }
}
