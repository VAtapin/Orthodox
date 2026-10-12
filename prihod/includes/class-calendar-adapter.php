<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

/** Use the calendar provider's connection, cache and authoritative event/icon order. */
final class Calendar_Adapter {
    public static function language(): string {
        $lang = substr( determine_locale(), 0, 2 );
        return in_array( $lang, array( 'ru', 'de', 'pl', 'uk' ), true ) ? $lang : 'ru';
    }
    public static function request( string $path, array $query ) {
        if ( ! is_callable( array( '\Orthocal_Plugin', 'request' ) ) ) { return new \WP_Error( 'calendar_unavailable', __( 'Календарный плагин недоступен.', 'prihod' ), array( 'status' => 503 ) ); }
        $options = \Orthocal_Plugin::options();
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
        if ( ! Occurrences::date( $date ) ) { return new \WP_Error( 'date', __( 'Некорректная дата.', 'prihod' ), array( 'status' => 400 ) ); }
        $data = self::request( 'day', array( 'date' => $date ) );
        if ( is_wp_error( $data ) ) { return $data; }
        if ( ! is_array( $data['day'] ?? null ) || ( $data['day']['date'] ?? '' ) !== $date || ! is_array( $data['day']['events'] ?? null ) ) { return new \WP_Error( 'calendar_schema', __( 'Календарь вернул некорректные данные.', 'prihod' ), array( 'status' => 502 ) ); }
        $day = $data['day']; $icon = null;
        if ( is_callable( array( '\Orthocal_Plugin', 'icon_items' ) ) && class_exists( '\Orthocal_Media_Cache' ) ) {
            $item = \Orthocal_Plugin::icon_items( $day )[0] ?? null;
            if ( $item ) {
                $source = $item['images'][0] ?? '';
                $icon = array( 'url' => $item['localUrl'] ?? '', 'alt' => sanitize_text_field( $item['alt'] ?? '' ), 'attribution' => sanitize_text_field( $item['attribution'] ?? '' ), 'media' => '' );
                if ( ! $icon['url'] && $source ) { $icon['media'] = add_query_arg( array( 'source' => $source, 'token' => \Orthocal_Media_Cache::token( $source ), 'thumbnail' => '1' ), rest_url( 'orthocal/v1/media' ) ); }
            }
        }
        $feasts = self::feasts( $day );
        return array( 'date' => $date, 'feasts' => $feasts, 'icon' => $icon, 'recommendation' => Calendar_Colors::recommendation( $feasts ) );
    }
    public static function week( \DateTimeImmutable $from ) {
        $to = $from->modify( '+7 days' ); $months = array(); $result = array();
        for ( $date = $from; $date < $to; $date = $date->modify( '+1 day' ) ) { $months[$date->format( 'Y-m' )] = $date; }
        foreach ( $months as $month ) {
            $data = self::request( 'month', array( 'year' => (int) $month->format( 'Y' ), 'month' => (int) $month->format( 'n' ) ) );
            if ( is_wp_error( $data ) ) { return $data; }
            if ( ! is_array( $data['days'] ?? null ) ) { return new \WP_Error( 'calendar_schema', __( 'Календарь вернул некорректные данные.', 'prihod' ) ); }
            foreach ( $data['days'] as $day ) {
                if ( ! is_array( $day ) || ! is_string( $day['date'] ?? null ) || ! Occurrences::date( $day['date'] ) || $day['date'] < $from->format( 'Y-m-d' ) || $day['date'] >= $to->format( 'Y-m-d' ) ) { continue; }
                $feasts = self::feasts( $day );
                if ( $feasts ) { $result[$day['date']] = $feasts; }
            }
        }
        ksort( $result ); return $result;
    }
}
