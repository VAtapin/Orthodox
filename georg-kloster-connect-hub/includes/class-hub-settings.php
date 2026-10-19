<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Hub_Settings {
    public static function get(): array {
        $saved = get_option( 'gkch_settings', array() );
        return array_merge( array( 'enabled' => true ), is_array( $saved ) ? $saved : array() );
    }
    public static function register(): void {
        register_setting( 'gkch_settings', 'gkch_settings', array( 'type' => 'array', 'sanitize_callback' => static fn( $input ) => array( 'enabled' => is_array( $input ) && ! empty( $input['enabled'] ) ) ) );
        register_setting( 'gkch_settings', 'gkps_retention_days', array( 'type' => 'integer', 'sanitize_callback' => static fn( $value ) => max( 30, min( 3650, absint( $value ) ) ) ) );
        add_filter( 'option_page_capability_gkch_settings', static fn() => 'manage_gkps_settings' );
    }
    public static function render(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        $network = isset( $_GET['network'] ) && is_string( $_GET['network'] ) ? sanitize_key( $_GET['network'] ) : '';
        $networks = Publications::titles();
        if ( ! isset( $networks[$network] ) ) { $network = ''; }
        echo '<div class="wrap"><h1>' . esc_html__( 'Settings', 'georg-kloster-connect-hub' ) . '</h1><nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Settings sections', 'georg-kloster-connect-hub' ) . '">';
        echo '<a class="nav-tab' . ( '' === $network ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=gkch-settings' ) ) . '">' . esc_html__( 'General', 'georg-kloster-connect-hub' ) . '</a>';
        foreach ( $networks as $id => $title ) { echo '<a class="nav-tab' . ( $id === $network ? ' nav-tab-active' : '' ) . '" href="' . esc_url( Connector::instance( $id )->settings_url() ) . '">' . esc_html( $title ) . '</a>'; }
        echo '</nav>';
        if ( $network ) { echo '</div>'; Connector::instance( $network )->render(); return; }
        echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
        settings_fields( 'gkch_settings' );
        echo '<p><label><input type="checkbox" name="gkch_settings[enabled]" value="1"' . checked( self::get()['enabled'], true, false ) . '> ' . esc_html__( 'Accept incoming form messages', 'georg-kloster-connect-hub' ) . '</label></p><p><label>' . esc_html__( 'Keep closed messages for days', 'georg-kloster-connect-hub' ) . '<br><input type="number" name="gkps_retention_days" min="30" max="3650" value="' . esc_attr( get_option( 'gkps_retention_days', 365 ) ) . '"></label></p>';
        submit_button();
        echo '</form><p>' . esc_html__( 'Each form has its own topics, text, recipient and enabled state. Channel credentials are configured in their respective sections.', 'georg-kloster-connect-hub' ) . '</p>';
        Blocks::help();
        echo '</div>';
    }
}
