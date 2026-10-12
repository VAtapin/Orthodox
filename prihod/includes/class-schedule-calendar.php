<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Schedule_Calendar {
    public static function hooks(): void {
        add_action( 'rest_api_init', function () {
            $args = array( 'date' => array( 'required' => true, 'type' => 'string', 'validate_callback' => static fn( $date ) => is_string( $date ) && Occurrences::date( $date ) ) );
            register_rest_route( Plugin::REST_NAMESPACE, '/calendar-day', array( 'methods' => 'GET', 'args' => $args, 'permission_callback' => static fn() => Modules::enabled( 'schedule' ) && current_user_can( 'manage_prihod_schedule' ), 'callback' => static fn( $request ) => Calendar_Adapter::day( $request['date'] ) ) );
            register_rest_route( Plugin::REST_NAMESPACE, '/schedule-feast', array( 'methods' => 'GET', 'args' => $args, 'permission_callback' => static fn( $request ) => self::public_date( $request['date'], false ), 'callback' => static function ( $request ) {
                if ( is_callable( array( '\Orthocal_Plugin', 'throttle' ) ) ) { $rate = \Orthocal_Plugin::throttle(); if ( is_wp_error( $rate ) ) { return $rate; } }
                $data = Calendar_Adapter::day( $request['date'] );
                return is_wp_error( $data ) ? $data : array( 'date' => $data['date'], 'feasts' => $data['feasts'], 'color' => $data['recommendation']['color'] ?? '', 'icon' => self::public_date( $request['date'] ) ? $data['icon'] : null );
            } ) );
            register_rest_route( Plugin::REST_NAMESPACE, '/schedule-icon', array( 'methods' => 'GET', 'args' => $args, 'permission_callback' => static fn( $request ) => self::public_date( $request['date'] ), 'callback' => static function ( $request ) {
                if ( is_callable( array( '\Orthocal_Plugin', 'throttle' ) ) ) { $rate = \Orthocal_Plugin::throttle(); if ( is_wp_error( $rate ) ) { return $rate; } }
                $data = Calendar_Adapter::day( $request['date'] );
                return is_wp_error( $data ) ? $data : array( 'date' => $data['date'], 'icon' => $data['icon'] );
            } ) );
        } );
    }
    public static function public_date( string $date, bool $icon_only = true ): bool {
        if ( ! Occurrences::date( $date ) || ! Modules::enabled( 'schedule' ) ) { return false; }
        $anchor = new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) );
        foreach ( Schedule::query( $anchor->modify( '-1 day' ), $anchor->modify( '+2 days' ), 0, false ) as $event ) {
            if ( ( ! $icon_only || ! empty( $event['day_icon'] ) ) && $event['start']->format( 'Y-m-d' ) === $date ) { return true; }
        }
        return false;
    }
    public static function icon_placeholder( array $event ): string {
        if ( empty( $event['day_icon'] ) || $event['cancelled'] ) { return ''; }
        wp_enqueue_script( 'prihod-schedule-icons', plugins_url( 'assets/schedule-icons.js', PRIHOD_FILE ), array(), PRIHOD_VERSION . '.' . filemtime( dirname( PRIHOD_FILE ) . '/assets/schedule-icons.js' ), true );
        $url = add_query_arg( 'date', $event['start']->format( 'Y-m-d' ), rest_url( Plugin::REST_NAMESPACE . '/schedule-feast' ) );
        return '<figure class="prihod-service-icon" data-prihod-day-icon="' . esc_url( $url ) . '" hidden></figure>';
    }
    public static function feast_placeholder( array $event ): string {
        if ( $event['cancelled'] ) { return ''; }
        wp_enqueue_script( 'prihod-schedule-icons', plugins_url( 'assets/schedule-icons.js', PRIHOD_FILE ), array(), PRIHOD_VERSION . '.' . filemtime( dirname( PRIHOD_FILE ) . '/assets/schedule-icons.js' ), true );
        $url = add_query_arg( 'date', $event['start']->format( 'Y-m-d' ), rest_url( Plugin::REST_NAMESPACE . '/schedule-feast' ) );
        $color = $event['vestment_color'] ?? '';
        return '<p class="prihod-service-feast" data-prihod-feast="' . esc_url( $url ) . '" data-service-title="' . esc_attr( $event['title'] ) . '" data-color="' . esc_attr( $color ) . '" hidden></p>';
    }
    public static function editor( array $rule ): void {
        echo Calendar_Colors::field( 'prihod_service[vestment_color]', $rule['vestment_color'] ?? '', ! empty( $rule['_color_persisted'] ) );
        echo '<fieldset class="prihod-service-section"><legend>' . esc_html__( 'Праздник и икона дня', 'prihod' ) . '</legend><p data-prihod-calendar-status role="status" aria-live="polite"></p><button type="button" class="button" data-prihod-use-feast hidden>' . esc_html__( 'Использовать название праздника', 'prihod' ) . '</button><p><label><input type="checkbox" name="prihod_service[day_icon]" value="1"' . checked( ! empty( $rule['day_icon'] ), true, false ) . '> ' . esc_html__( 'Показывать икону дня из календаря', 'prihod' ) . '</label></p><p class="prihod-service-help">' . esc_html__( 'Икона определяется автоматически для каждой даты службы. Время и место выбираете вы.', 'prihod' ) . '</p><p data-prihod-icon-status role="status" hidden>' . esc_html__( 'Икона дня недоступна.', 'prihod' ) . '</p><figure class="prihod-service-icon" data-prihod-icon-preview hidden></figure></fieldset>';
    }
}
