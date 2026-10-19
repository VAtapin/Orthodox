<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Admin {
    public static function menu(): void {
        add_menu_page( __( 'Connect Hub', 'georg-kloster-connect-hub' ), __( 'Connect Hub', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkps', array( Forms::class, 'render' ), 'dashicons-format-chat', 27 );
        add_submenu_page( 'gkps', __( 'Forms', 'georg-kloster-connect-hub' ), __( 'Forms', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkps', array( Forms::class, 'render' ) );
        add_submenu_page( 'gkps', __( 'Publications', 'georg-kloster-connect-hub' ), __( 'Publications', 'georg-kloster-connect-hub' ), 'publish_posts', 'gkch-publications', array( Publications::class, 'render' ) );
        add_submenu_page( 'gkps', __( 'Settings', 'georg-kloster-connect-hub' ), __( 'Settings', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', 'gkch-settings', array( Hub_Settings::class, 'render' ) );
    }

    public static function order_menu(): void {
        global $submenu;
        if ( empty( $submenu['gkps'] ) ) { return; }
        $order = array_flip( array( 'gkps', 'gkps-requests', 'gkch-publications', 'gkps-connect-telegram', 'gkps-connect-whatsapp', 'gkch-settings' ) );
        usort( $submenu['gkps'], static fn( $a, $b ) => ( $order[$a[2]] ?? 99 ) <=> ( $order[$b[2]] ?? 99 ) );
    }

    public static function defaults(): array {
        return array( 'enabled' => false, 'title' => __( 'Contact us', 'georg-kloster-connect-hub' ), 'intro' => '', 'submit' => __( 'Send message', 'georg-kloster-connect-hub' ), 'topics' => '', 'recipient' => '' );
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
        return $result;
    }

    public static function settings(): void {
        register_setting( 'gkps_form', 'gkps_form', array( 'type' => 'array', 'sanitize_callback' => array( self::class, 'sanitize' ), 'show_in_rest' => false ) );
        add_filter( 'option_page_capability_gkps_form', static fn() => 'manage_gkps_settings' );
    }

    public static function render(): void { Forms::render(); }
}
