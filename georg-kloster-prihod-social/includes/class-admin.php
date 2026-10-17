<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Admin {
    public static function menu(): void {
        add_menu_page( __( 'Contacts', 'georg-kloster-prihod-social' ), __( 'Contacts', 'georg-kloster-prihod-social' ), 'manage_gkps_settings', 'gkps', array( self::class, 'render' ), 'dashicons-email', 27 );
    }

    public static function defaults(): array {
        return array( 'enabled' => false, 'title' => __( 'Contact us', 'georg-kloster-prihod-social' ), 'intro' => '', 'submit' => __( 'Send message', 'georg-kloster-prihod-social' ), 'topics' => '', 'recipient' => '' );
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

    public static function render(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-prihod-social' ) ); }
        $settings = self::get();
        echo '<div class="wrap"><h1>' . esc_html__( 'Contact form', 'georg-kloster-prihod-social' ) . '</h1><form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
        settings_fields( 'gkps_form' );
        echo '<p><label><input type="checkbox" name="gkps_form[enabled]" value="1"' . checked( $settings['enabled'], true, false ) . '> ' . esc_html__( 'Accept messages', 'georg-kloster-prihod-social' ) . '</label></p>';
        foreach ( array( 'title' => __( 'Form title', 'georg-kloster-prihod-social' ), 'intro' => __( 'Introduction', 'georg-kloster-prihod-social' ), 'submit' => __( 'Button label', 'georg-kloster-prihod-social' ), 'topics' => __( 'Suggested topics (one per line)', 'georg-kloster-prihod-social' ), 'recipient' => __( 'Notification email', 'georg-kloster-prihod-social' ) ) as $key => $label ) {
            echo '<p><label>' . esc_html( $label ) . '<br>';
            if ( in_array( $key, array( 'intro', 'topics' ), true ) ) { echo '<textarea class="large-text" rows="4" name="gkps_form[' . esc_attr( $key ) . ']">' . esc_textarea( $settings[ $key ] ) . '</textarea>'; }
            else { echo '<input class="regular-text" type="' . ( 'recipient' === $key ? 'email' : 'text' ) . '" name="gkps_form[' . esc_attr( $key ) . ']" value="' . esc_attr( $settings[ $key ] ) . '">'; }
            echo '</label></p>';
        }
        submit_button();
        echo '</form><p>' . esc_html__( 'Insert the Contact form block or [gkps_contact_form] shortcode on any page.', 'georg-kloster-prihod-social' ) . '</p></div>';
    }
}
