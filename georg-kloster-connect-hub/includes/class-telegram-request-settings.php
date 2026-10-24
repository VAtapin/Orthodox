<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

final class Telegram_Request_Settings {
    public static function hooks(): void {
        add_action( 'gkps_connector_settings_fields', array( self::class, 'fields' ), 10, 2 );
        add_action( 'gkps_connector_settings_after', array( self::class, 'after' ) );
        add_filter( 'gkps_connector_settings_errors', array( self::class, 'errors' ), 10, 3 );
        add_action( 'gkps_connector_saved', array( self::class, 'saved' ), 10, 2 );
        add_action( 'admin_post_gkps_telegram_receive', array( self::class, 'connect' ) );
        add_action( 'admin_post_gkps_telegram_request', array( self::class, 'resend' ) );
        add_action( 'gkps_request_telegram_controls', array( self::class, 'request_button' ) );
    }
    private static function input(): array {
        return array( 'enabled' => ! empty( $_POST['telegram_full_requests'] ), 'staff' => is_scalar( $_POST['telegram_reply_staff'] ?? null ) ? absint( $_POST['telegram_reply_staff'] ) : 0 );
    }
    public static function errors( array $errors, string $provider, array $settings ): array {
        if ( 'telegram' !== $provider ) { return $errors; }
        $o = self::input();
        if ( $o['enabled'] ) {
            if ( ! $settings['enabled'] || ! $settings['notify_requests'] ) { $errors[] = __( 'Enable sending and notifications about new messages.', 'georg-kloster-connect-hub' ); }
            if ( ! user_can( $o['staff'], 'view_gkps_requests' ) || ! user_can( $o['staff'], 'reply_gkps_requests' ) ) { $errors[] = __( 'Select a staff member with permission to view and reply to messages.', 'georg-kloster-connect-hub' ); }
        }
        return $errors;
    }
    public static function saved( string $provider, array $settings ): void {
        if ( 'telegram' !== $provider ) { return; }
        $o = array_merge( Telegram_Requests::options(), self::input() );
        if ( ( ! $o['enabled'] && ! Telegram_Bot::broadcasting_enabled() ) || ! hash_equals( (string) $o['configuration'], Telegram_Requests::configuration( $settings, (int) $o['staff'] ) ) ) { $o['registered'] = false; unset( $o['connection_job'] ); wp_clear_scheduled_hook( 'gkps_telegram_register' ); }
        update_option( Telegram_Requests::OPTION, $o, false );
    }
    public static function fields( string $provider, array $settings ): void {
        if ( 'telegram' !== $provider ) { return; }
        $o = Telegram_Requests::options();
        echo '<fieldset><legend><h2>' . esc_html__( 'Messages and replies in Telegram', 'georg-kloster-connect-hub' ) . '</h2></legend><p><label><input type="checkbox" name="telegram_full_requests" value="1"' . checked( $o['enabled'], true, false ) . '> ' . esc_html__( 'Send complete messages and receive replies in each form’s private chat', 'georg-kloster-connect-hub' ) . '</label></p><p>' . esc_html__( 'Each form’s Telegram Chat ID determines which responsible person receives the contact message. Use Reply on the bot’s message: the site saves the reply and sends a formatted email to the visitor. Internal notes are not transmitted. Public posts and schedule broadcasts do not use these form Chat IDs.', 'georg-kloster-connect-hub' ) . '</p><p><label>' . esc_html__( 'Save replies on behalf of this staff member', 'georg-kloster-connect-hub' ) . ' <select name="telegram_reply_staff"><option value="0">' . esc_html__( 'Select a staff member', 'georg-kloster-connect-hub' ) . '</option>';
        foreach ( get_users( array( 'capability' => 'view_gkps_requests' ) ) as $user ) {
            if ( user_can( $user, 'reply_gkps_requests' ) ) { echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( $o['staff'], $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>'; }
        }
        echo '<p>' . esc_html__( 'You can reply to a bot message multiple times: each reply is saved separately and immediately passed to PHP mail. Captioned photos and static stickers are included in the email. Visitor replies by email are not received automatically yet.', 'georg-kloster-connect-hub' ) . '</p>';
        echo '</select></label></p><p>' . esc_html__( 'Save settings first, then connect the bot. This one webhook receives both bot commands (/start, /stop, /today and /week) and replies to form messages. Connecting it moves this bot’s incoming-message webhook to this site; the previous application will stop receiving messages.', 'georg-kloster-connect-hub' ) . '</p></fieldset>';
    }
    public static function after( string $provider ): void {
        if ( 'telegram' !== $provider ) { return; }
        $key = 'gkps_telegram_notice_' . get_current_user_id(); $notice = get_transient( $key );
        if ( $notice ) { delete_transient( $key ); echo '<p role="status">' . esc_html( $notice ) . '</p>'; }
        $wanted = Telegram_Bot::broadcasting_enabled() || ! empty( Telegram_Requests::options()['enabled'] );
        if ( $wanted ) {
            echo '<p>' . esc_html( Telegram_Requests::webhook_ready() ? __( 'The shared bot webhook is connected. Subscriber commands and enabled form replies are being received here.', 'georg-kloster-connect-hub' ) : __( 'The shared bot webhook is not connected yet. Press the button after saving the bot settings.', 'georg-kloster-connect-hub' ) ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkps_telegram_receive">'; wp_nonce_field( 'gkps_telegram_receive' );
            submit_button( __( 'Connect / check Telegram bot webhook', 'georg-kloster-connect-hub' ), 'secondary' ); echo '</form>';
        }
    }
    public static function api( string $method, array $body = array(), array $snapshot = array() ) {
        $connector = Telegram_Requests::connector();
        $settings = $snapshot ?: ( $connector ? $connector->settings() : array() );
        if ( ! $connector || ! in_array( $method, array( 'getMe', 'getChat', 'getWebhookInfo', 'setWebhook', 'setMyCommands', 'getFile' ), true ) || ! $connector->valid( $settings ) ) { return new \WP_Error( 'telegram_settings', __( 'Save the connection first.', 'georg-kloster-connect-hub' ) ); }
        $response = wp_remote_post( 'https://api.telegram.org/bot' . $settings['token'] . '/' . $method, array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ), 'timeout' => 15, 'redirection' => 0 ) );
        $data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) || empty( $data['ok'] ) ) { return new \WP_Error( 'telegram_api', __( 'Telegram did not confirm the connection. Check the bot token and service availability.', 'georg-kloster-connect-hub' ) ); }
        return $data['result'] ?? null;
    }
    public static function configure() {
        $connector = Telegram_Requests::connector(); $o = Telegram_Requests::options();
        if ( ! $connector || ( ! $o['enabled'] && ! Telegram_Bot::broadcasting_enabled() ) ) { return new \WP_Error( 'telegram_settings', __( 'Enable Telegram broadcasts or complete-message form replies before connecting the bot.', 'georg-kloster-connect-hub' ) ); }
        if ( $o['enabled'] && ( ! user_can( $o['staff'], 'view_gkps_requests' ) || ! user_can( $o['staff'], 'reply_gkps_requests' ) || empty( $connector->settings()['notify_requests'] ) ) ) { return new \WP_Error( 'telegram_settings', __( 'Enable contact notifications and select a staff member with reply permissions.', 'georg-kloster-connect-hub' ) ); }
        $url = rest_url( Plugin::REST_NAMESPACE . '/telegram-reply' );
        if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) { return new \WP_Error( 'telegram_https', __( 'An HTTPS site address is required to receive Telegram replies.', 'georg-kloster-connect-hub' ) ); }
        $settings = $connector->settings(); $fingerprint = Telegram_Requests::configuration( $settings, (int) $o['staff'] );
        $info = self::api( 'getWebhookInfo', array(), $settings ); if ( is_wp_error( $info ) ) { return $info; }
        if ( ! is_array( $info ) ) { return new \WP_Error( 'telegram_api', __( 'Telegram did not confirm the connection. Check the bot token and service availability.', 'georg-kloster-connect-hub' ) ); }
        $me = self::api( 'getMe', array(), $settings ); if ( is_wp_error( $me ) ) { return $me; }
        if ( ! is_array( $me ) || empty( $me['is_bot'] ) || ! is_int( $me['id'] ?? null ) ) { return new \WP_Error( 'telegram_chat', __( 'Telegram did not confirm the bot identity.', 'georg-kloster-connect-hub' ) ); }
        if ( ! hash_equals( $fingerprint, Telegram_Requests::configuration( $connector->settings(), (int) Telegram_Requests::options()['staff'] ) ) || ( ! Telegram_Requests::options()['enabled'] && ! Telegram_Bot::broadcasting_enabled() ) ) { return new \WP_Error( 'telegram_changed', __( 'Settings changed during the check. Reconnect the bot webhook.', 'georg-kloster-connect-hub' ) ); }
        $o['secret'] = $o['secret'] ?: wp_generate_password( 48, false, false );
        $set = self::api( 'setWebhook', array( 'url' => $url, 'secret_token' => $o['secret'], 'allowed_updates' => array( 'message', 'callback_query' ), 'drop_pending_updates' => false ), $settings );
        if ( is_wp_error( $set ) ) { return $set; }
        if ( true !== $set ) { return new \WP_Error( 'telegram_registration', __( 'Telegram did not confirm incoming replies.', 'georg-kloster-connect-hub' ) ); }
        if ( ! hash_equals( $fingerprint, Telegram_Requests::configuration( $connector->settings(), (int) Telegram_Requests::options()['staff'] ) ) || ( ! Telegram_Requests::options()['enabled'] && ! Telegram_Bot::broadcasting_enabled() ) ) { return new \WP_Error( 'telegram_changed', __( 'Settings changed during the check. Reconnect the bot webhook.', 'georg-kloster-connect-hub' ) ); }
        foreach ( Telegram_Bot::menu_languages() as $language => $details ) { self::api( 'setMyCommands', array( 'commands' => Telegram_Bot::command_descriptions( $language ), 'language_code' => substr( $language, 0, 2 ) ), $settings ); }
        $o['registered'] = true; $o['bot'] = (string) $me['id']; $o['configuration'] = $fingerprint;
        update_option( Telegram_Requests::OPTION, $o, false ); return true;
    }
    public static function connect(): void {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'gkps_telegram_receive' ); $result = self::register_now( get_current_user_id() );
        set_transient( 'gkps_telegram_notice_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : __( 'The shared bot webhook is connected.', 'georg-kloster-connect-hub' ), 120 );
        wp_safe_redirect( Connector::instance( 'telegram' )->settings_url() ); exit;
    }
    public static function register_now( int $user ) {
        $connector = Telegram_Requests::connector(); $o = Telegram_Requests::options();
        if ( ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'manage_gkps_settings' ) ) { return new \WP_Error( 'telegram_permission', __( 'Insufficient permissions.', 'georg-kloster-connect-hub' ) ); }
        if ( ! $connector || ! $connector->valid( $connector->settings() ) || ( ! $o['enabled'] && ! Telegram_Bot::broadcasting_enabled() ) || ( $o['enabled'] && ( ! user_can( $o['staff'], 'view_gkps_requests' ) || ! user_can( $o['staff'], 'reply_gkps_requests' ) || empty( $connector->settings()['notify_requests'] ) ) ) ) { return new \WP_Error( 'telegram_settings', __( 'Enable Telegram broadcasts or configure complete-message replies with a permitted staff member.', 'georg-kloster-connect-hub' ) ); }
        wp_clear_scheduled_hook( 'gkps_telegram_register' );
        return self::configure();
    }
    public static function request_button( int $id ): void {
        if ( ! Telegram_Requests::ready() || ! current_user_can( 'reply_gkps_requests' ) ) { return; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkps_telegram_request"><input type="hidden" name="request_id" value="' . esc_attr( $id ) . '">'; wp_nonce_field( 'gkps_telegram_request_' . $id );
        submit_button( __( 'Send the complete message to Telegram', 'georg-kloster-connect-hub' ), 'secondary' ); echo '</form>';
    }
    public static function resend(): void {
        $id = is_scalar( $_POST['request_id'] ?? null ) ? absint( $_POST['request_id'] ) : 0;
        if ( ! current_user_can( 'view_gkps_requests' ) || ! current_user_can( 'reply_gkps_requests' ) || ! Telegram_Requests::ready() ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'gkps_telegram_request_' . $id ); Requests::assert_request( $id ); Telegram_Requests::notify( $id );
        wp_safe_redirect( admin_url( 'admin.php?page=gkps-requests&request_id=' . $id ) ); exit;
    }
}
