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
        if ( ! is_callable( array( '\Orthocal_Plugin', 'request' ) ) ) { return new \WP_Error( 'calendar_unavailable', __( 'Календарный плагин недоступен.', 'georg-kloster-prihod-schedule' ), array( 'status' => 503 ) ); }
        $options = is_callable( array( '\\Orthocal_Plugin', 'options' ) ) ? \Orthocal_Plugin::options() : array();
        if ( ! is_array( $options ) || ! is_string( $options['profile'] ?? null ) || '' === $options['profile'] ) {
            return new \WP_Error( 'calendar_settings', __( 'Calendar settings are unavailable.', 'georg-kloster-prihod-schedule' ), array( 'status' => 503 ) );
        }
        return \Orthocal_Plugin::request( 'calendar', $path, $query + array( 'lang' => self::language(), 'profile' => $options['profile'] ) );
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
    public static function day( string $date ) {
        if ( ! Occurrences::date( $date ) ) { return new \WP_Error( 'date', __( 'Некорректная дата.', 'georg-kloster-prihod-schedule' ), array( 'status' => 400 ) ); }
        $data = self::request( 'day', array( 'date' => $date ) );
        if ( is_wp_error( $data ) ) { return $data; }
        if ( ! is_array( $data['day'] ?? null ) || ( $data['day']['date'] ?? '' ) !== $date || ! is_array( $data['day']['events'] ?? null ) ) { return new \WP_Error( 'calendar_schema', __( 'Календарь вернул некорректные данные.', 'georg-kloster-prihod-schedule' ), array( 'status' => 502 ) ); }
        $day = $data['day']; $icon = null;
        if ( is_callable( array( '\Orthocal_Plugin', 'icon_items' ) ) && class_exists( '\Orthocal_Media_Cache' ) ) {
            $item = \Orthocal_Plugin::icon_items( $day )[0] ?? null;
            if ( $item ) {
                $source = $item['images'][0] ?? '';
                $icon = array( 'url' => $item['localUrl'] ?? '', 'fullUrl' => Schedule_Calendar::image_url( $date ), 'alt' => sanitize_text_field( $item['alt'] ?? '' ), 'attribution' => sanitize_text_field( $item['attribution'] ?? '' ), 'media' => '', 'fullMedia' => '' );
                if ( $source ) {
                    $icon['fullMedia'] = add_query_arg( array( 'source' => $source, 'token' => \Orthocal_Media_Cache::token( $source ) ), rest_url( 'orthocal/v1/media' ) );
                    if ( ! $icon['url'] ) { $icon['media'] = add_query_arg( 'thumbnail', '1', $icon['fullMedia'] ); }
                }
            }
        }
        $feasts = self::feasts( $day );
        return array( 'date' => $date, 'feasts' => $feasts, 'icon' => $icon ?: Calendar_Icons::fallback( $date ), 'recommendation' => Calendar_Colors::day( $day ) );
    }
    /** One cached provider month; no per-day API calls or saved service colors. */
    public static function month( string $date ) {
        if ( ! Occurrences::date( $date ) || '01' !== substr( $date, -2 ) ) { return new \WP_Error( 'date', __( 'Некорректная дата.', 'georg-kloster-prihod-schedule' ), array( 'status' => 400 ) ); }
        $data = self::request( 'month', array( 'year' => (int) substr( $date, 0, 4 ), 'month' => (int) substr( $date, 5, 2 ) ) );
        if ( is_wp_error( $data ) ) { return $data; }
        if ( ! is_array( $data['days'] ?? null ) ) { return new \WP_Error( 'calendar_schema', __( 'Календарь вернул некорректные данные.', 'georg-kloster-prihod-schedule' ), array( 'status' => 502 ) ); }
        $days = array();
        foreach ( $data['days'] as $day ) {
            $key = is_array( $day ) ? ( $day['date'] ?? '' ) : '';
            if ( ! is_string( $key ) || ! Occurrences::date( $key ) || substr( $key, 0, 7 ) !== substr( $date, 0, 7 ) ) { continue; }
            $days[$key] = array( 'feasts' => self::feasts( $day ), 'color' => Calendar_Colors::day( $day )['color'] ?? '' ) + self::liturgical( $day );
        }
        return array( 'date' => $date, 'days' => $days );
    }
    /** API ranks 0–2: great; 3: vigil. Never infer a mark from a saint's name. */
    public static function liturgical( array $day ): array {
        $memories = array(); $primary = null; $rank = 99;
        foreach ( $day['events'] ?? array() as $event ) {
            if ( ! is_array( $event ) || 'commemoration' !== ( $event['category'] ?? '' ) || ! isset( $event['typeCode'] ) || ! is_numeric( $event['typeCode'] ) ) { continue; }
            $code = (int) $event['typeCode'];
            if ( $code < 0 || $code > 6 || (float) $event['typeCode'] !== (float) $code || ! is_string( $event['title'] ?? null ) ) { continue; }
            $title = sanitize_text_field( $event['title'] ?? '' );
            if ( $code <= 3 && $title ) { $memories[] = $title; }
            $mark = $event['typikonMark'] ?? null;
            if ( ! is_array( $mark ) || ! is_string( $mark['label'] ?? null ) || ! is_string( $mark['svgSource'] ?? null ) || ! class_exists( '\Orthocal_Media_Cache' ) ) { continue; }
            $source = $mark['svgSource'];
            if ( ! preg_match( '~^/assets/typikon/(great|vigil|polyeleos|doxology|six-stichera)\.svg$~D', $source ) || ! \Orthocal_Media_Cache::token( $source ) ) { continue; }
            if ( $code < $rank ) {
                $rank = $code;
                $primary = array( 'label' => sanitize_text_field( $mark['label'] ), 'url' => \Orthocal_Media_Cache::url( $source ), 'media' => add_query_arg( array( 'source' => $source, 'token' => \Orthocal_Media_Cache::token( $source ) ), rest_url( 'orthocal/v1/media' ) ) );
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
            if ( ! is_array( $data['days'] ?? null ) ) { return new \WP_Error( 'calendar_schema', __( 'Календарь вернул некорректные данные.', 'georg-kloster-prihod-schedule' ) ); }
            foreach ( $data['days'] as $day ) {
                if ( ! is_array( $day ) || ! is_string( $day['date'] ?? null ) || ! Occurrences::date( $day['date'] ) || $day['date'] < $from->format( 'Y-m-d' ) || $day['date'] >= $to->format( 'Y-m-d' ) ) { continue; }
                $feasts = self::feasts( $day );
                if ( $feasts ) { $result[$day['date']] = $feasts; }
            }
        }
        ksort( $result ); return $result;
    }
}
