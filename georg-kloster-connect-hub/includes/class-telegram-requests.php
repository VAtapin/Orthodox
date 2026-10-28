<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

/** Private Telegram replies reuse the ordinary private history and immediate PHP mail. */
final class Telegram_Requests {
    public const OPTION = 'gkps_telegram_requests';
    public static function connector() {
        return is_callable( array( '\GeorgKlosterPrihodSocial\Connector', 'instance' ) ) ? \GeorgKlosterPrihodSocial\Connector::instance( 'telegram' ) : null;
    }
    public static function options(): array {
        return array_merge( array( 'enabled' => false, 'staff' => 0, 'registered' => false, 'configuration' => '', 'secret' => '', 'bot' => '' ), (array) get_option( self::OPTION, array() ) );
    }
    public static function configuration( array $settings, int $staff, string $target = '' ): string {
        return hash( 'sha256', $settings['token'] . '|' . ( '' !== $target ? $target : $settings['target'] ) . '|' . $staff );
    }
    public static function destination_for_request( int $id ): string {
        $settings = self::connector() ? self::connector()->settings() : array( 'target' => '' );
        $form_id = absint( get_post_meta( $id, '_gkch_form_id', true ) );
        return Forms::telegram_target( $form_id, '' );
    }
    public static function staff_for_request( int $id ): int {
        $default = (int) self::options()['staff'];
        $form_id = absint( get_post_meta( $id, '_gkch_form_id', true ) );
        return Forms::telegram_staff( $form_id, $default );
    }
    public static function configuration_for_request( int $id ): string {
        $connector = self::connector();
        return $connector ? self::configuration( $connector->settings(), self::staff_for_request( $id ), self::destination_for_request( $id ) ) : '';
    }
    public static function message_key( string $target, string $bot, int $message_id ): string {
        return hash( 'sha256', $bot . '|' . $target . '|' . $message_id );
    }
    public static function ready_for_request( int $id ): bool {
        $target = self::destination_for_request( $id );
        $staff = self::staff_for_request( $id );
        return self::ready() && preg_match( '/^[1-9][0-9]*$/D', $target ) && user_can( $staff, 'view_gkps_requests' ) && user_can( $staff, 'reply_gkps_requests' );
    }
    public static function ready(): bool {
        $connector = self::connector(); $o = self::options();
        return self::webhook_ready() && ! get_option( 'gkps_purge_job' ) && $o['enabled'] && ! empty( $connector->settings()['notify_requests'] ) && user_can( $o['staff'], 'view_gkps_requests' ) && user_can( $o['staff'], 'reply_gkps_requests' );
    }
    public static function webhook_ready(): bool {
        $connector = self::connector(); $o = self::options();
        if ( ! $connector ) { return false; }
        $settings = $connector->settings();
        $feature_enabled = ! empty( $settings['telegram_broadcasts'] ) || ! empty( $o['enabled'] );
        return ! get_option( 'gkps_purge_job' ) && $connector->valid( $settings ) && ! empty( $settings['enabled'] ) && $feature_enabled && is_string( $o['secret'] ) && strlen( $o['secret'] ) >= 32 && preg_match( '/^[1-9][0-9]*$/D', $o['bot'] ) && ! empty( $o['registered'] ) && hash_equals( (string) $o['configuration'], self::configuration( $settings, (int) $o['staff'] ) );
    }
    public static function hooks(): void {
        Telegram_Request_Settings::hooks();
        add_filter( 'gkps_connector_request_notification', static fn( $ordinary, $provider ) => 'telegram' === $provider && self::ready() ? false : $ordinary, 10, 2 );
        add_filter( 'gkps_connector_text_limit', static fn( $limit, $provider, $kind ) => 'telegram' === $provider && 'request' === $kind && self::ready() ? 2000 : $limit, 10, 3 );
        add_filter( 'gkps_connector_job_allowed', static function ( $allowed, $provider, $job ) {
            if ( 'telegram' !== $provider || 'request' !== ( $job['kind'] ?? '' ) || empty( $job['reply_context'] ) ) { return $allowed; }
            $request_id = absint( $job['reply_context']['request_id'] ?? 0 );
            return $allowed && $request_id && self::ready_for_request( $request_id ) && ( $job['telegram_target'] ?? '' ) === self::destination_for_request( $request_id ) && hash_equals( self::configuration_for_request( $request_id ), $job['reply_context']['configuration'] ?? '' );
        }, 10, 3 );
        add_action( 'gkps_request_created', array( self::class, 'notify' ) );
        add_action( 'gkps_connector_sent', array( self::class, 'sent' ), 10, 4 );
        add_action( 'rest_api_init', static function () {
            register_rest_route( Plugin::REST_NAMESPACE, '/telegram-reply', array( 'methods' => 'POST', 'permission_callback' => array( self::class, 'authorize' ), 'callback' => array( self::class, 'receive' ) ) );
        } );
    }
    public static function chunks( string $text ): array {
        $chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
        return array_map( static fn( $part ) => implode( '', $part ), array_chunk( $chars ?: array(), 1200 ) );
    }
    public static function notify( int $id ): void {
        if ( ! self::ready() || empty( self::connector()->settings()['notify_requests'] ) || get_option( 'gkps_purge_job' ) ) { return; }
        $post = get_post( $id );
        if ( ! $post || 'gkps_request' !== $post->post_type || 'private' !== $post->post_status ) { return; }
        $destination = self::destination_for_request( $id );
        $staff = self::staff_for_request( $id );
        if ( ! self::ready_for_request( $id ) ) { return; }
        $configuration = self::configuration( self::connector()->settings(), $staff, $destination );
        $sender = (array) get_post_meta( $id, '_gkps_sender', true );
        $header = '<b>' . esc_html__( 'New incoming message', 'georg-kloster-connect-hub' ) . ' #' . $id . "</b>\n\n";
        foreach ( array( __( 'Sender name', 'georg-kloster-connect-hub' ) => $sender['name'] ?? '', __( 'Email', 'georg-kloster-connect-hub' ) => $sender['email'] ?? '', __( 'Message subject', 'georg-kloster-connect-hub' ) => $post->post_title, __( 'Message date', 'georg-kloster-connect-hub' ) => get_post_time( 'd.m.Y H:i', false, $post ), __( 'Message status', 'georg-kloster-connect-hub' ) => Requests::statuses()[get_post_meta( $id, '_gkps_request_status', true )] ?? '' ) as $label => $value ) { $header .= '<b>' . esc_html( $label ) . ':</b> ' . esc_html( $value ) . "\n"; }
        $parts = self::chunks( $post->post_content ); $url = admin_url( 'admin.php?page=gkps-requests&request_id=' . $id );
        foreach ( $parts as $index => $part ) {
            $text = $header . "\n<b>" . esc_html__( 'Message', 'georg-kloster-connect-hub' ) . '</b> · ' . ( $index + 1 ) . '/' . count( $parts ) . "\n" . esc_html( $part );
            self::connector()->queue( $text, $url, 'request', $id, array( 'request_id' => $id, 'configuration' => $configuration, 'telegram_html' => true ), $destination );
        }
    }
    public static function sent( string $provider, string $job_id, array $job, array $response ): void {
        if ( 'telegram' !== $provider || empty( $job['reply_context']['request_id'] ) ) { return; }
        $context = $job['reply_context']; $message = $response['result'] ?? array(); $o = self::options();
        $request_id = absint( $context['request_id'] );
        if ( ! self::ready_for_request( $request_id ) || ! hash_equals( self::configuration_for_request( $request_id ), $context['configuration'] ?? '' ) || (string) ( $message['chat']['id'] ?? '' ) !== self::destination_for_request( $request_id ) || 'private' !== ( $message['chat']['type'] ?? '' ) || (string) ( $message['from']['id'] ?? '' ) !== $o['bot'] || ! is_int( $message['message_id'] ?? null ) || $message['message_id'] < 1 ) { return; }
        if ( 'private' !== get_post_status( (int) $context['request_id'] ) ) { return; }
        $key = self::message_key( self::destination_for_request( $request_id ), $o['bot'], $message['message_id'] );
        if ( ! in_array( $key, get_post_meta( $request_id, '_gkps_telegram_message' ), true ) ) { add_post_meta( $request_id, '_gkps_telegram_message', $key ); }
    }
    public static function authorize( \WP_REST_Request $request ) {
        $o = self::options(); $provided = $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
        return self::webhook_ready() && is_string( $o['secret'] ) && strlen( $o['secret'] ) >= 32 && is_string( $provided ) && hash_equals( $o['secret'], $provided ) ? true : new \WP_Error( 'telegram_unauthorized', __( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), array( 'status' => 403 ) );
    }
    public static function receive( \WP_REST_Request $request ) {
        // Also enforce the secret for direct callers, not only REST dispatch.
        $allowed = self::authorize( $request ); if ( is_wp_error( $allowed ) ) { return $allowed; }
        $data = $request->get_json_params(); $message = is_array( $data ) ? ( $data['message'] ?? array() ) : array();
        $o = self::options(); $settings = self::connector()->settings();
        $ok = new \WP_REST_Response( array( 'ok' => true ), 200 );
        if ( is_array( $data ) && Telegram_Bot::receive_update( $data ) ) { return $ok; }
        if ( ! is_array( $message ) || ! is_int( $data['update_id'] ?? null ) || $data['update_id'] < 0 || 'private' !== ( $message['chat']['type'] ?? '' ) || ! is_scalar( $message['chat']['id'] ?? null ) || (string) ( $message['chat']['id'] ?? '' ) !== (string) ( $message['from']['id'] ?? '' ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) ( $message['chat']['id'] ?? '' ) ) || ! empty( $message['from']['is_bot'] ) || ! is_int( $message['reply_to_message']['message_id'] ?? null ) || (string) ( $message['reply_to_message']['from']['id'] ?? '' ) !== $o['bot'] ) { return $ok; }
        $raw_body = $message['text'] ?? $message['caption'] ?? '';
        if ( ! is_string( $raw_body ) ) { return $ok; }
        $body = sanitize_textarea_field( $raw_body );
        if ( ( '' === trim( $body ) && ! isset( $message['photo'] ) && ! isset( $message['sticker'] ) && ! isset( $message['video'] ) && ! isset( $message['animation'] ) && ! isset( $message['document'] ) ) || strlen( $raw_body ) > 20000 ) { return $ok; }
        $sender_chat = (string) $message['chat']['id'];
        $key = self::message_key( $sender_chat, $o['bot'], $message['reply_to_message']['message_id'] );
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Telegram reply lookup is bounded to two private request matches by a generated message key.
        $posts = get_posts( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'numberposts' => 2, 'meta_key' => '_gkps_telegram_message', 'meta_value' => $key ) );
        // Preserve replies to messages sent before per-form Chat IDs were introduced.
        if ( ! $posts && $sender_chat === (string) $settings['target'] ) {
            $legacy_key = hash( 'sha256', $o['configuration'] . '|' . $message['reply_to_message']['message_id'] );
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Legacy Telegram reply lookup is bounded to two private request matches by a generated message key.
            $posts = get_posts( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'numberposts' => 2, 'meta_key' => '_gkps_telegram_message', 'meta_value' => $legacy_key ) );
        }
        if ( 1 !== count( $posts ) || 'closed' === get_post_meta( $posts[0]->ID, '_gkps_request_status', true ) || self::destination_for_request( (int) $posts[0]->ID ) !== $sender_chat || ! self::ready_for_request( (int) $posts[0]->ID ) || get_option( 'gkps_purge_job' ) ) { return $ok; }
        $reply_configuration = self::configuration_for_request( (int) $posts[0]->ID );
        $receipt = hash( 'sha256', $reply_configuration . '|update|' . $data['update_id'] );
        $lock = 'gkps_telegram_reply_' . $receipt; $owner = Lock::acquire( $lock, 60 );
        if ( ! $owner ) { return new \WP_Error( 'telegram_busy', __( 'Try again later.', 'georg-kloster-connect-hub' ), array( 'status' => 503 ) ); }
        $previous = get_current_user_id();
        try {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Idempotency checks inspect at most one private reply record by a fixed key.
            $existing = get_posts( array( 'post_type' => 'gkps_message', 'post_status' => 'private', 'numberposts' => 1, 'meta_key' => '_gkps_reply_source', 'meta_value' => $receipt ) );
            if ( $existing ) { return $ok; }
            wp_set_current_user( self::staff_for_request( (int) $posts[0]->ID ) );
            try { $media = Request_Media::telegram( $message ); }
            catch ( \InvalidArgumentException $error ) {
                Requests::message( $posts[0]->ID, Request_Media::unsupported(), true, $receipt );
                return $ok;
            }
            $id = Requests::message( $posts[0]->ID, $body, false, $receipt, $media );
            return $ok;
        } catch ( \Throwable $error ) {
            return new \WP_Error( 'telegram_reply_failed', __( 'The reply was not saved.', 'georg-kloster-connect-hub' ), array( 'status' => 503 ) );
        } finally { wp_set_current_user( $previous ); Lock::release( $lock, $owner ); }
    }
}
