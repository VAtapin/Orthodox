<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

final class Admin {
    public static function activate(): void {
        $role = get_role( 'administrator' );
        if ( $role ) { $role->add_cap( 'manage_gkpc_profile' ); }
    }

    public static function menu(): void {
        add_menu_page( __( 'Parish', 'georg-kloster-prihod-core' ), __( 'Parish', 'georg-kloster-prihod-core' ), 'manage_gkpc_profile', 'gkpc', array( self::class, 'page' ), 'dashicons-admin-home', 26 );
        add_submenu_page( 'gkpc', __( 'Parish profile', 'georg-kloster-prihod-core' ), __( 'Parish profile', 'georg-kloster-prihod-core' ), 'manage_gkpc_profile', 'gkpc', array( self::class, 'page' ) );
    }

    public static function settings(): void {
        register_setting( 'gkpc_profile', Profile::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( Profile::class, 'sanitize' ), 'show_in_rest' => false ) );
        register_setting( 'gkpc_pages', Page_Links::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( Page_Links::class, 'sanitize' ), 'show_in_rest' => false ) );
        add_filter( 'option_page_capability_gkpc_profile', static function () { return 'manage_gkpc_profile'; } );
        add_filter( 'option_page_capability_gkpc_pages', static function () { return 'manage_gkpc_profile'; } );
    }

    public static function page(): void {
        if ( ! current_user_can( 'manage_gkpc_profile' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-prihod-core' ) ); }
        $profile = Profile::raw();
        echo '<div class="wrap"><h1>' . esc_html__( 'Parish profile', 'georg-kloster-prihod-core' ) . '</h1>';
        settings_errors( Profile::OPTION );
        Demo_Admin::link();
        echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
        settings_fields( 'gkpc_profile' );
        echo '<table class="form-table"><tbody>';
        foreach ( Profile::fields() as $key => $label ) {
            echo '<tr><th><label for="gkpc-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
            if ( 'type' === $key ) {
                echo '<select id="gkpc-type" name="gkpc_profile[type]">';
                foreach ( array( 'parish' => __( 'Parish', 'georg-kloster-prihod-core' ), 'church' => __( 'Church', 'georg-kloster-prihod-core' ), 'monastery' => __( 'Monastery', 'georg-kloster-prihod-core' ), 'chapel' => __( 'Chapel', 'georg-kloster-prihod-core' ) ) as $value => $name ) {
                    echo '<option value="' . esc_attr( $value ) . '"' . selected( $profile['type'], $value, false ) . '>' . esc_html( $name ) . '</option>';
                }
                echo '</select>';
            } elseif ( in_array( $key, array( 'description', 'address' ), true ) ) {
                echo '<textarea class="large-text" id="gkpc-' . esc_attr( $key ) . '" name="gkpc_profile[' . esc_attr( $key ) . ']">' . esc_textarea( $profile[ $key ] ) . '</textarea>';
            } else {
                $type = 'email' === $key ? 'email' : ( 'website' === $key ? 'url' : 'text' );
                echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="gkpc-' . esc_attr( $key ) . '" name="gkpc_profile[' . esc_attr( $key ) . ']" value="' . esc_attr( $profile[ $key ] ) . '">';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        submit_button();
        echo '</form>';
        Page_Links::render();
        Blocks::help();
        echo '</div>';
    }
}
