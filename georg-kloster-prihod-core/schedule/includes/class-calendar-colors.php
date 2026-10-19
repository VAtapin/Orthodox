<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

/** Recommendations are deliberately narrower than the administrator's palette. */
final class Calendar_Colors {
    /** Calendar title patterns are multilingual input data, not interface text. */
    private static function matches( string $key, string $title ): bool {
        static $patterns = null;
        if ( null === $patterns ) { $patterns = json_decode( file_get_contents( dirname( __DIR__ ) . '/data/calendar-title-patterns.json' ), true ); }
        return isset( $patterns[$key] ) && 1 === preg_match( $patterns[$key], $title );
    }
    public static function labels(): array {
        return array( 'auto' => __( 'Automatic calendar color', 'georg-kloster-prihod-schedule' ), '' => __( 'No highlighting', 'georg-kloster-prihod-schedule' ), 'white' => __( 'White', 'georg-kloster-prihod-schedule' ), 'red' => __( 'Red', 'georg-kloster-prihod-schedule' ), 'gold' => __( 'Gold / yellow', 'georg-kloster-prihod-schedule' ), 'green' => __( 'Green', 'georg-kloster-prihod-schedule' ), 'blue' => __( 'Blue', 'georg-kloster-prihod-schedule' ), 'purple' => __( 'Violet', 'georg-kloster-prihod-schedule' ), 'black' => __( 'Black', 'georg-kloster-prihod-schedule' ) );
    }
    public static function validate( $color ): string {
        if ( ! is_string( $color ) || ! array_key_exists( $color, self::labels() ) ) { throw new \InvalidArgumentException( __( 'Select a vestment color from the list.', 'georg-kloster-prihod-schedule' ) ); }
        return $color;
    }
    /** Resolve only explicit ranked commemorations; ordinary saints do not override feasts. */
    public static function day( array $day ): ?array {
        $ranked = array(); $periods = array(); $titles = array();
        foreach ( $day['events'] ?? array() as $event ) {
            if ( ! is_array( $event ) || 'commemoration' !== ( $event['category'] ?? '' ) || ! is_numeric( $event['typeCode'] ?? null ) || ! is_string( $event['title'] ?? null ) ) { continue; }
            $rank = (int) $event['typeCode'];
            $titles[] = $event['title'];
            if ( self::matches( 'afterfeast', $event['title'] ) ) { $periods[] = $event['title']; }
            if ( $rank >= 0 && $rank <= 5 ) { $ranked[$rank][] = sanitize_text_field( $event['title'] ); }
        }
        ksort( $ranked );
        $major = $ranked && array_key_first( $ranked ) <= 2;
        if ( $major ) { return self::recommendation( reset( $ranked ) ); }
        if ( $periods ) { $period = self::recommendation( $periods ); if ( $period ) { return $period; } }
        $date = is_string( $day['date'] ?? null ) && Occurrences::date( $day['date'] ) ? new \DateTimeImmutable( $day['date'], new \DateTimeZone( 'UTC' ) ) : null;
        // Calendar period markers, not the dietary fasting rule for Wednesdays/Fridays.
        if ( self::matches( 'lent', implode( '; ', $titles ) ) ) {
            $color = ( $date && (int) $date->format( 'N' ) >= 6 ) || ( $ranked && array_key_first( $ranked ) <= 4 ) ? 'purple' : 'black';
            return self::resolved( $color, implode( '; ', $titles ), 'period' );
        }
        if ( $date && '7' === $date->format( 'N' ) ) { return self::resolved( 'gold', self::labels()['gold'], 'ordinary-sunday' ); }
        $recommendation = $ranked ? self::recommendation( reset( $ranked ) ) : null;
        return $recommendation ?: ( $date ? self::resolved( 'gold', self::labels()['gold'], 'ordinary-day' ) : null );
    }
    private static function resolved( string $color, string $reason, string $source = 'tradition' ): array { return array( 'color' => $color, 'label' => self::labels()[$color], 'reason' => $reason, 'source' => $source ); }
    public static function recommendation( array $feasts ): ?array {
        $colors = array(); $reasons = array();
        foreach ( $feasts as $title ) {
            // Confirmed Marian feasts, in the four supported calendar languages.
            if ( self::matches( 'marian', $title ) ) { $colors['blue'] = true; }
            elseif ( self::matches( 'annunciation', $title ) ) { $colors['blue'] = true; }
            elseif ( self::matches( 'pentecost', $title ) ) { $colors['green'] = true; }
            elseif ( self::matches( 'transfiguration', $title ) ) { $colors['white'] = true; }
            elseif ( self::matches( 'nativity_theophany_ascension', $title ) ) { $colors['white'] = true; }
            elseif ( self::matches( 'cross', $title ) ) { $colors['purple'] = true; }
            elseif ( self::matches( 'pascha_martyrs', $title ) ) { $colors['red'] = true; }
            elseif ( self::matches( 'monastics', $title ) ) { $colors['green'] = true; }
            elseif ( self::matches( 'hierarchs_apostles', $title ) ) { $colors['gold'] = true; }
            else { return null; } // Mixed or unknown commemorations do not get a guess.
            $reasons[] = $title;
        }
        if ( 1 !== count( $colors ) ) { return null; }
        $color = key( $colors );
        return array( 'color' => $color, 'label' => self::labels()[$color], 'reason' => implode( '; ', $reasons ), 'source' => 'tradition' );
    }
    public static function field( string $name, string $selected, bool $persisted = false ): string {
        $html = '<div class="gkpsch-color-control" data-gkpsch-color-control><label class="gkpsch-service-field">' . esc_html__( 'Vestment color', 'georg-kloster-prihod-schedule' ) . '<select name="' . esc_attr( $name ) . '" data-gkpsch-color data-persisted="' . ( $persisted ? '1' : '0' ) . '">';
        foreach ( self::labels() as $value => $label ) { $html .= '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        $html .= '</select></label><span class="gkpsch-color-swatch" data-gkpsch-color-swatch aria-hidden="true"></span><p data-gkpsch-color-status role="status" aria-live="polite"></p><button type="button" class="button" data-gkpsch-use-color hidden>' . esc_html__( 'Use recommended color', 'georg-kloster-prihod-schedule' ) . '</button><div class="gkpsch-color-palette" aria-label="' . esc_attr__( 'Color samples', 'georg-kloster-prihod-schedule' ) . '">';
        foreach ( self::labels() as $value => $label ) { if ( $value && 'auto' !== $value ) { $html .= '<span><i class="gkpsch-color-swatch" data-color="' . esc_attr( $value ) . '" aria-hidden="true"></i>' . esc_html( $label ) . '</span>'; } }
        return $html . '</div></div>';
    }
}
