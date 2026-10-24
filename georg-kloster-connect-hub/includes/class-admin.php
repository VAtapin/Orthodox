<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Admin {
    public static function menu(): void {
        add_menu_page( __( 'Connect Hub', 'georg-kloster-connect-hub' ), __( 'Connect Hub', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkps', array( Forms::class, 'render' ), 'dashicons-format-chat', 27 );
        add_submenu_page( 'gkps', __( 'Forms', 'georg-kloster-connect-hub' ), __( 'Forms', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkps', array( Forms::class, 'render' ) );
        add_submenu_page( 'gkps', __( 'Publications', 'georg-kloster-connect-hub' ), __( 'Publications', 'georg-kloster-connect-hub' ), 'publish_posts', 'gkch-publications', array( Publications::class, 'render' ) );
        add_submenu_page( 'gkps', __( 'Message delivery log', 'georg-kloster-connect-hub' ), __( 'Message log', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkch-log', array( Outgoing_Log::class, 'render' ) );
        add_submenu_page( 'gkps', __( 'Settings', 'georg-kloster-connect-hub' ), __( 'Settings', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkch-settings', array( Hub_Settings::class, 'render' ) );
    }

    public static function order_menu(): void {
        global $submenu;
        if ( empty( $submenu['gkps'] ) ) { return; }
        $order = array_flip( array( 'gkps', 'gkps-requests', 'gkch-publications', 'gkch-telegram', 'gkch-telegram-commands', 'gkch-log', 'gkch-settings' ) );
        usort( $submenu['gkps'], static fn( $a, $b ) => ( $order[$a[2]] ?? 99 ) <=> ( $order[$b[2]] ?? 99 ) );
    }

    public static function defaults(): array {
        return array( 'enabled' => false, 'title' => __( 'Contact us', 'georg-kloster-connect-hub' ), 'intro' => '', 'submit' => __( 'Send message', 'georg-kloster-connect-hub' ), 'topics' => '', 'recipient' => '', 'telegram_chat_id' => '', 'telegram_staff' => 0 );
    }

    public static function get(): array {
        $saved = get_option( 'gkps_form', array() );
        return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
    }

    public static function sanitize( $input ): array {
        $result = self::get();
        if ( ! is_array( $input ) ) { return $result; }
        $result['enabled'] = ! empty( $input['enabled'] );
        foreach ( array( 'title', 'intro', 'submit', 'topics', 'recipient' ) as $key ) {
            if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { continue; }
            $value = trim( wp_unslash( $input[ $key ] ) );
            if ( 'recipient' === $key ) { $value = is_email( $value ) ? sanitize_email( $value ) : ''; }
            elseif ( in_array( $key, array( 'intro', 'topics' ), true ) ) { $value = sanitize_textarea_field( $value ); }
            else { $value = sanitize_text_field( $value ); }
            $result[ $key ] = $value;
        }
        if ( isset( $input['telegram_chat_id'] ) && is_string( $input['telegram_chat_id'] ) ) {
            $chat_id = trim( wp_unslash( $input['telegram_chat_id'] ) );
            if ( '' !== $chat_id && ! preg_match( '/^[1-9][0-9]*$/D', $chat_id ) ) { throw new \InvalidArgumentException( __( 'Enter a positive numeric Telegram Chat ID for this form.', 'georg-kloster-connect-hub' ) ); }
            $result['telegram_chat_id'] = $chat_id;
        }
        if ( isset( $input['telegram_staff'] ) && is_scalar( $input['telegram_staff'] ) ) {
            $staff = absint( $input['telegram_staff'] );
            if ( $staff && ( ! user_can( $staff, 'view_gkps_requests' ) || ! user_can( $staff, 'reply_gkps_requests' ) ) ) { throw new \InvalidArgumentException( __( 'Choose a staff member who can view and reply to messages.', 'georg-kloster-connect-hub' ) ); }
            $result['telegram_staff'] = $staff;
        }
        return $result;
    }

    public static function settings(): void {
        register_setting( 'gkps_form', 'gkps_form', array( 'type' => 'array', 'sanitize_callback' => array( self::class, 'sanitize' ), 'show_in_rest' => false ) );
        add_filter( 'option_page_capability_gkps_form', static fn() => 'manage_gkps_settings' );
    }

    public static function render(): void { Forms::render(); }
}
