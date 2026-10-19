<?php
namespace GeorgKlosterPrihodSchedule;
defined( 'ABSPATH' ) || exit;

/** Public calendar connection and local cache owned by Schedule. */
final class Calendar_Provider {
    public const OPTION = 'gkpsch_calendar_provider';
    private const DEFAULT_ORIGIN = 'https://bible-desktop.com';

    public static function options(): array {
        $saved = get_option( self::OPTION, array() );
        $profile = is_array( $saved ) && in_array( $saved['profile'] ?? '', array( 'typikon-strict', 'parish' ), true ) ? $saved['profile'] : 'typikon-strict';
        return array( 'origin' => self::DEFAULT_ORIGIN, 'profile' => $profile );
    }

    public static function sanitize( $input ): array {
        $previous = self::options();
        if ( ! is_array( $input ) ) { return $previous; }
        $profile = isset( $input['profile'] ) && is_string( $input['profile'] ) ? sanitize_key( $input['profile'] ) : '';
        if ( ! in_array( $profile, array( 'typikon-strict', 'parish' ), true ) ) {
            add_settings_error( self::OPTION, 'profile', __( 'Некорректный профиль календаря.', 'georg-kloster-prihod-schedule' ) );
            return $previous;
        }
        return array( 'origin' => self::DEFAULT_ORIGIN, 'profile' => $profile );
    }

    public static function render(): void {
        $options = self::options();
        echo '<h2>' . esc_html__( 'Календарный сервис', 'georg-kloster-prihod-schedule' ) . '</h2>';
        echo '<p>' . esc_html__( 'Даты служб остаются в WordPress. Праздники, уставные знаки и иконы загружаются из публичного календарного API и временно сохраняются на сайте.', 'georg-kloster-prihod-schedule' ) . '</p>';
        settings_errors( self::OPTION );
        echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
        settings_fields( self::OPTION );
        echo '<p><label>' . esc_html__( 'Календарный профиль', 'georg-kloster-prihod-schedule' ) . ' <input type="text" required name="' . esc_attr( self::OPTION ) . '[profile]" value="' . esc_attr( $options['profile'] ) . '"></label></p>';
        submit_button( __( 'Сохранить календарный сервис', 'georg-kloster-prihod-schedule' ) );
        echo '</form>';
    }

    public static function request( string $path, array $query ) {
        if ( ! in_array( $path, array( 'day', 'month' ), true ) ) { return new \WP_Error( 'calendar_path', __( 'Неизвестный календарный запрос.', 'georg-kloster-prihod-schedule' ) ); }
        $options = self::options();
        $url = add_query_arg( $query + array( 'lang' => Calendar_Adapter::language(), 'profile' => $options['profile'] ), $options['origin'] . '/api/v1/calendar/' . $path );
        $key = 'gkpsch_cal_' . md5( $url );
        $cached = get_transient( $key );
        if ( is_array( $cached ) ) { return $cached; }
        if ( get_transient( 'gkpsch_calendar_cooldown' ) ) { return new \WP_Error( 'calendar_busy', __( 'Календарный сервис временно недоступен.', 'georg-kloster-prihod-schedule' ) ); }
        // The provider asks clients to leave at least 2.1 seconds between calls.
        $last = (float) get_option( 'gkpsch_calendar_last_request', 0 );
        $wait = 2.1 - ( microtime( true ) - $last );
        if ( $wait > 0 && $wait <= 2.1 ) { usleep( (int) ( $wait * 1000000 ) ); }
        update_option( 'gkpsch_calendar_last_request', microtime( true ), false );
        $response = wp_safe_remote_get( $url, array( 'timeout' => 25, 'redirection' => 0, 'limit_response_size' => 12 * 1024 * 1024, 'headers' => array( 'Accept' => 'application/json', 'X-Calendar-Client' => 'georg-kloster-prihod-schedule' ) ) );
        if ( is_wp_error( $response ) ) { return new \WP_Error( 'calendar_connection', __( 'Не удалось подключиться к календарному сервису.', 'georg-kloster-prihod-schedule' ) ); }
        $status = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            if ( in_array( $status, array( 429, 503 ), true ) ) { set_transient( 'gkpsch_calendar_cooldown', 1, min( 3600, max( 2, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ) ) ); }
            return new \WP_Error( 'calendar_upstream', __( 'Календарный сервис не ответил успешно.', 'georg-kloster-prihod-schedule' ), array( 'status' => 503 ) );
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) { return new \WP_Error( 'calendar_schema', __( 'Календарь вернул некорректные данные.', 'georg-kloster-prihod-schedule' ) ); }
        $ttl = min( 300, max( 0, (int) wp_remote_retrieve_header( $response, 'x-calendar-application-cache-ttl' ) ) );
        if ( $ttl ) { set_transient( $key, $data, $ttl ); }
        return $data;
    }

    /** Only an image named by the configured calendar origin may be downloaded. */
    public static function icon_url( $source ): string {
        if ( ! is_string( $source ) ) { return ''; }
        $origin = self::options()['origin'];
        if ( ! str_starts_with( $source, $origin . '/storage/calendar-icons/' ) || ! preg_match( '~^' . preg_quote( $origin, '~' ) . '/storage/calendar-icons/([a-f0-9]{64})\.(png|webp|jpe?g|gif)$~D', $source, $match ) ) { return ''; }
        $upload = wp_get_upload_dir();
        if ( ! empty( $upload['error'] ) ) { return ''; }
        $directory = trailingslashit( $upload['basedir'] ) . 'gkpsch-calendar';
        if ( is_link( $directory ) || ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) ) { return ''; }
        $filename = $match[1] . '.' . $match[2];
        $file = $directory . '/' . $filename;
        $url = trailingslashit( $upload['baseurl'] ) . 'gkpsch-calendar/' . $filename;
        if ( is_file( $file ) && ! is_link( $file ) ) { return $url; }
        $response = wp_safe_remote_get( $source, array( 'timeout' => 20, 'redirection' => 0, 'limit_response_size' => 5 * 1024 * 1024 ) );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { return ''; }
        $body = wp_remote_retrieve_body( $response );
        $info = $body && strlen( $body ) <= 5 * 1024 * 1024 ? @getimagesizefromstring( $body ) : false;
        $mime = array( 'png' => 'image/png', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif' );
        if ( ! $info || $info['mime'] !== $mime[$match[2]] || $info[0] * $info[1] > 24000000 || ! hash_equals( $match[1], hash( 'sha256', $body ) ) ) { return ''; }
        $temporary = wp_tempnam( $filename );
        if ( ! $temporary || false === file_put_contents( $temporary, $body ) || ! rename( $temporary, $file ) ) { if ( $temporary ) { wp_delete_file( $temporary ); } return ''; }
        return $url;
    }

    public static function mark_url( $source ): string {
        return is_string( $source ) && preg_match( '~^/assets/typikon/(great|vigil|polyeleos|doxology|six-stichera)\.svg$~D', $source ) ? self::options()['origin'] . $source : '';
    }
}
