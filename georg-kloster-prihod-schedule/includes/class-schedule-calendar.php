<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

final class Schedule_Calendar {
    public static function hooks(): void {
        add_action( 'rest_api_init', function () {
            $args = array( 'date' => array( 'required' => true, 'type' => 'string', 'validate_callback' => static fn( $date ) => is_string( $date ) && Occurrences::date( $date ) ) );
            register_rest_route( Plugin::REST_NAMESPACE, '/calendar-day', array( 'methods' => 'GET', 'args' => $args, 'permission_callback' => static fn() => Modules::enabled( 'schedule' ) && current_user_can( 'manage_gkpsch_schedule' ), 'callback' => static fn( $request ) => Calendar_Adapter::day( $request['date'] ) ) );
            register_rest_route( Plugin::REST_NAMESPACE, '/schedule-feast', array( 'methods' => 'GET', 'args' => $args, 'permission_callback' => static fn( $request ) => self::public_date( $request['date'], false ), 'callback' => static function ( $request ) {
                if ( is_callable( array( '\Orthocal_Plugin', 'throttle' ) ) ) { $rate = \Orthocal_Plugin::throttle(); if ( is_wp_error( $rate ) ) { return $rate; } }
                $data = Calendar_Adapter::day( $request['date'] );
                return is_wp_error( $data ) ? $data : array( 'date' => $data['date'], 'feasts' => $data['feasts'], 'color' => $data['recommendation']['color'] ?? '', 'icon' => self::public_date( $request['date'] ) ? $data['icon'] : null );
            } ) );
            register_rest_route( Plugin::REST_NAMESPACE, '/schedule-month', array( 'methods' => 'GET', 'args' => $args, 'permission_callback' => static fn( $request ) => Modules::enabled( 'schedule' ) && '01' === substr( $request['date'], -2 ), 'callback' => static function ( $request ) {
                if ( is_callable( array( '\Orthocal_Plugin', 'throttle' ) ) ) { $rate = \Orthocal_Plugin::throttle(); if ( is_wp_error( $rate ) ) { return $rate; } }
                return Calendar_Adapter::month( $request['date'] );
            } ) );
            register_rest_route( Plugin::REST_NAMESPACE, '/schedule-icon', array( 'methods' => 'GET', 'args' => $args, 'permission_callback' => static fn( $request ) => self::public_date( $request['date'] ), 'callback' => static function ( $request ) {
                if ( is_callable( array( '\Orthocal_Plugin', 'throttle' ) ) ) { $rate = \Orthocal_Plugin::throttle(); if ( is_wp_error( $rate ) ) { return $rate; } }
                $data = Calendar_Adapter::day( $request['date'] );
                return is_wp_error( $data ) ? $data : array( 'date' => $data['date'], 'icon' => $data['icon'] );
            } ) );
            register_rest_route( Plugin::REST_NAMESPACE, '/schedule-icon-image', array( 'methods' => 'GET', 'args' => $args, 'permission_callback' => static fn( $request ) => self::public_date( $request['date'] ), 'callback' => array( self::class, 'image' ) ) );
        } );
    }
    /** Stable native Image-block source, resolved only when its viewer requests it. */
    public static function image_url( string $date ): string { return add_query_arg( array( 'date' => $date, 'v' => GKPSCH_VERSION ), rest_url( Plugin::REST_NAMESPACE . '/schedule-icon-image' ) ); }
    public static function image( \WP_REST_Request $request ) {
                if ( is_callable( array( '\Orthocal_Plugin', 'throttle' ) ) ) { $rate = \Orthocal_Plugin::throttle(); if ( is_wp_error( $rate ) ) { return $rate; } }
        $date = $request['date']; $data = Calendar_Adapter::day( $date );
        $icon = is_wp_error( $data ) ? Calendar_Icons::fallback( $date ) : $data['icon'];
        if ( ! $icon ) { return is_wp_error( $data ) ? $data : new \WP_Error( 'icon_unavailable', __( 'Икона дня недоступна.', 'georg-kloster-prihod-schedule' ), array( 'status' => 404 ) ); }
        $url = ! empty( $icon['fallback'] ) ? $icon['fullUrl'] : $icon['url'];
        if ( ! empty( $icon['fullMedia'] ) ) {
            parse_str( wp_parse_url( $icon['fullMedia'], PHP_URL_QUERY ) ?? '', $params );
            $media_request = new \WP_REST_Request( 'GET', '/orthocal/v1/media' ); $media_request->set_query_params( $params );
            $media = rest_do_request( $media_request );
            $body = $media->get_data();
            if ( $media->get_status() >= 400 || ! is_string( $body['url'] ?? null ) ) { return new \WP_Error( 'icon_unavailable', __( 'Икона дня недоступна.', 'georg-kloster-prihod-schedule' ), array( 'status' => 503 ) ); }
            $url = $body['url'];
        }
        if ( ! is_string( $url ) || ! preg_match( '~^https?://~i', $url ) ) { return new \WP_Error( 'icon_unavailable', __( 'Икона дня недоступна.', 'georg-kloster-prihod-schedule' ), array( 'status' => 503 ) ); }
        return new \WP_REST_Response( null, 302, array( 'Location' => esc_url_raw( $url ), 'Cache-Control' => 'public, max-age=60' ) );
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
        wp_enqueue_script( 'gkpsch-schedule-icons', plugins_url( 'assets/schedule-icons.js', GKPSCH_FILE ), array(), GKPSCH_VERSION . '.' . filemtime( dirname( GKPSCH_FILE ) . '/assets/schedule-icons.js' ), true );
        $url = add_query_arg( 'date', $event['start']->format( 'Y-m-d' ), rest_url( Plugin::REST_NAMESPACE . '/schedule-feast' ) );
        $fallback = Calendar_Icons::fallback( $event['start']->format( 'Y-m-d' ) );
        $html = '<figure class="gkpsch-service-icon" data-gkpsch-day-icon="' . esc_url( $url ) . '" data-gkpsch-icon-fallback="' . esc_attr( wp_json_encode( $fallback ) ) . '" hidden></figure>';
        return apply_filters( 'gkpsch_schedule_icon_markup', $html, $event, $fallback );
    }
    public static function feast_placeholder( array $event, bool $compact = false ): string {
        if ( $event['cancelled'] ) { return ''; }
        wp_enqueue_script( 'gkpsch-schedule-icons', plugins_url( 'assets/schedule-icons.js', GKPSCH_FILE ), array(), GKPSCH_VERSION . '.' . filemtime( dirname( GKPSCH_FILE ) . '/assets/schedule-icons.js' ), true );
        $url = add_query_arg( 'date', $event['start']->format( 'Y-m-01' ), rest_url( Plugin::REST_NAMESPACE . '/schedule-month' ) );
        $color = $event['vestment_color'] ?? '';
        return '<p class="gkpsch-service-feast" data-gkpsch-feast="' . esc_url( $url ) . '" data-calendar-date="' . esc_attr( $event['start']->format( 'Y-m-d' ) ) . '" data-compact="' . ( $compact ? '1' : '0' ) . '" data-service-title="' . esc_attr( $event['title'] ) . '" data-color="' . esc_attr( $color ) . '" hidden></p>';
    }
    public static function editor( array $rule ): void {
        echo Calendar_Colors::field( 'gkpsch_service[vestment_color]', $rule['vestment_color'] ?? '', ! empty( $rule['_color_persisted'] ) );
        echo '<fieldset class="gkpsch-service-section"><legend>' . esc_html__( 'Праздник и икона дня', 'georg-kloster-prihod-schedule' ) . '</legend><p data-gkpsch-calendar-status role="status" aria-live="polite"></p><button type="button" class="button" data-gkpsch-use-feast hidden>' . esc_html__( 'Использовать название праздника', 'georg-kloster-prihod-schedule' ) . '</button><p><label><input type="checkbox" name="gkpsch_service[day_icon]" value="1"' . checked( ! empty( $rule['day_icon'] ), true, false ) . '> ' . esc_html__( 'Показывать икону дня из календаря', 'georg-kloster-prihod-schedule' ) . '</label></p><p class="gkpsch-service-help">' . esc_html__( 'Икона определяется автоматически для каждой даты службы. Время и место выбираете вы.', 'georg-kloster-prihod-schedule' ) . '</p><p data-gkpsch-icon-status role="status" hidden>' . esc_html__( 'Икона дня недоступна.', 'georg-kloster-prihod-schedule' ) . '</p><figure class="gkpsch-service-icon" data-gkpsch-icon-preview hidden></figure></fieldset>';
    }
}
