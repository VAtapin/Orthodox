<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

/** Calendar API recommendations, plus the administrator's vestment palette. */
final class Calendar_Colors {
    private const API_COLORS = array( 'gold' => 'gold', 'white' => 'white', 'blue' => 'blue', 'red' => 'red', 'green' => 'green', 'violet' => 'purple', 'black' => 'black' );
    public static function labels(): array {
        return array( 'auto' => __( 'Automatic calendar color', 'georg-kloster-prihod-schedule' ), '' => __( 'No highlighting', 'georg-kloster-prihod-schedule' ), 'white' => __( 'White', 'georg-kloster-prihod-schedule' ), 'red' => __( 'Red', 'georg-kloster-prihod-schedule' ), 'gold' => __( 'Gold / yellow', 'georg-kloster-prihod-schedule' ), 'green' => __( 'Green', 'georg-kloster-prihod-schedule' ), 'blue' => __( 'Blue', 'georg-kloster-prihod-schedule' ), 'purple' => __( 'Violet', 'georg-kloster-prihod-schedule' ), 'black' => __( 'Black', 'georg-kloster-prihod-schedule' ) );
    }
    public static function validate( $color ): string {
        if ( ! is_string( $color ) || ! array_key_exists( $color, self::labels() ) ) { throw new \InvalidArgumentException( __( 'Select a vestment color from the list.', 'georg-kloster-prihod-schedule' ) ); }
        return $color;
    }
    /** Use the calendar provider's recommendation; do not infer a color from event titles. */
    public static function day( array $day ): ?array {
        $vestment = $day['vestment'] ?? null;
        if ( ! is_array( $vestment ) || ! is_string( $vestment['color'] ?? null ) || ! isset( self::API_COLORS[$vestment['color']] ) ) { return null; }
        $color = self::API_COLORS[$vestment['color']];
        $event = is_array( $vestment['event'] ?? null ) ? $vestment['event'] : array();
        $rule = $vestment['rule'] ?? null;
        $reason = is_string( $event['title'] ?? null ) ? sanitize_text_field( $event['title'] ) : ( is_string( $rule ) ? sanitize_text_field( $rule ) : '' );
        $candidates = array();
        foreach ( is_array( $vestment['candidateColors'] ?? null ) ? $vestment['candidateColors'] : array() as $candidate ) {
            if ( is_string( $candidate ) && isset( self::API_COLORS[$candidate] ) ) { $candidates[] = self::API_COLORS[$candidate]; }
        }
        $result = array(
            'color' => $color,
            'label' => is_string( $vestment['label'] ?? null ) ? sanitize_text_field( $vestment['label'] ) : self::labels()[$color],
            'reason' => $reason,
            'source' => 'api',
            'status' => is_string( $vestment['status'] ?? null ) ? preg_replace( '/[^a-z0-9_-]/', '', strtolower( $vestment['status'] ) ) : '',
            'rule' => $rule,
            'event' => $event,
        );
        if ( $candidates ) { $result['candidateColors'] = array_values( array_unique( $candidates ) ); }
        return $result;
    }
    public static function field( string $name, string $selected, bool $persisted = false ): string {
        $html = '<div class="gkpsch-color-control" data-gkpsch-color-control><label class="gkpsch-service-field">' . esc_html__( 'Vestment color', 'georg-kloster-prihod-schedule' ) . '<select name="' . esc_attr( $name ) . '" data-gkpsch-color data-persisted="' . ( $persisted ? '1' : '0' ) . '">';
        foreach ( self::labels() as $value => $label ) { $html .= '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        $html .= '</select></label><span class="gkpsch-color-swatch" data-gkpsch-color-swatch aria-hidden="true"></span><p data-gkpsch-color-status role="status" aria-live="polite"></p><button type="button" class="button" data-gkpsch-use-color hidden>' . esc_html__( 'Use recommended color', 'georg-kloster-prihod-schedule' ) . '</button><div class="gkpsch-color-palette" aria-label="' . esc_attr__( 'Color samples', 'georg-kloster-prihod-schedule' ) . '">';
        foreach ( self::labels() as $value => $label ) { if ( $value && 'auto' !== $value ) { $html .= '<span><i class="gkpsch-color-swatch" data-color="' . esc_attr( $value ) . '" aria-hidden="true"></i>' . esc_html( $label ) . '</span>'; } }
        return $html . '</div></div>';
    }
}
