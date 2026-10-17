<?php
namespace GeorgKlosterPrihodSchedule;

defined( 'ABSPATH' ) || exit;

final class Admin {
    public static function menu(): void {
        add_menu_page( __( 'Services', 'georg-kloster-prihod-schedule' ), __( 'Services', 'georg-kloster-prihod-schedule' ), 'manage_gkpsch_schedule', 'gkpsch', array( self::class, 'render' ), 'dashicons-calendar-alt', 28 );
        add_submenu_page( 'gkpsch', __( 'Schedule settings', 'georg-kloster-prihod-schedule' ), __( 'Settings', 'georg-kloster-prihod-schedule' ), 'manage_gkpsch_settings', 'gkpsch-settings', array( self::class, 'settings_page' ) );
    }
    public static function settings(): void {
        // Calendar settings register their own Settings API groups.
    }
    public static function render(): void {
        if ( ! current_user_can( 'manage_gkpsch_schedule' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-prihod-schedule' ) ); }
        echo '<div class="wrap"><h1>' . esc_html__( 'Service schedule', 'georg-kloster-prihod-schedule' ) . '</h1><p><a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=gkpsch_service' ) ) . '">' . esc_html__( 'Manage services', 'georg-kloster-prihod-schedule' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=gkpsch_location' ) ) . '">' . esc_html__( 'Manage places', 'georg-kloster-prihod-schedule' ) . '</a></p></div>';
    }
    public static function settings_page(): void {
        if ( ! current_user_can( 'manage_gkpsch_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-prihod-schedule' ) ); }
        echo '<div class="wrap"><h1>' . esc_html__( 'Schedule settings', 'georg-kloster-prihod-schedule' ) . '</h1>';
        Calendar_Provider::render();
        Calendar_Icons::render();
        Calendar_Reminders::render();
        echo '</div>';
    }
}
