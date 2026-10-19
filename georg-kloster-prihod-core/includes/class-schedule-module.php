<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

/** Keep the public Schedule identifiers while moving its implementation into Parish. */
final class Schedule_Module {
    private const LEGACY = 'georg-kloster-prihod-schedule/georg-kloster-prihod-schedule.php';

    public static function activate( bool $network_wide = false ): void {
        if ( is_plugin_active_for_network( self::LEGACY ) && ! $network_wide ) {
            wp_die( esc_html__( 'Activate Parish network-wide to replace a network-active Parish Schedule.', 'georg-kloster-prihod-core' ) );
        }
        Admin::activate();
        if ( ! class_exists( '\GeorgKlosterPrihodSchedule\Capabilities', false ) ) { require_once dirname( GKPC_FILE ) . '/schedule/includes/class-capabilities.php'; }
        \GeorgKlosterPrihodSchedule\Capabilities::install();
        deactivate_plugins( self::LEGACY, true, $network_wide );
    }

    public static function load(): void {
        // During a ZIP upgrade the old plugin may already be loaded in this request.
        // Let it finish once, then load the bundled implementation on the next request.
        if ( class_exists( '\GeorgKlosterPrihodSchedule\Plugin', false ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            if ( ! is_plugin_active_for_network( self::LEGACY ) || is_plugin_active_for_network( plugin_basename( GKPC_FILE ) ) ) {
                deactivate_plugins( self::LEGACY, true, is_plugin_active_for_network( self::LEGACY ) );
            } else {
                add_action( 'admin_notices', static function () { echo '<div class="notice notice-error"><p>' . esc_html__( 'Activate Parish network-wide to replace a network-active Parish Schedule.', 'georg-kloster-prihod-core' ) . '</p></div>'; } );
            }
            return;
        }
        require_once dirname( GKPC_FILE ) . '/schedule/bootstrap.php';
    }
}
