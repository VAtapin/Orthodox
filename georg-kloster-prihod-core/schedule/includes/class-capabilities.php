<?php
namespace GeorgKlosterPrihodSchedule;

defined( 'ABSPATH' ) || exit;

final class Capabilities {
    public static function install(): void {
        $role = get_role( 'administrator' );
        if ( $role ) { foreach ( array( 'manage_gkpsch_schedule', 'manage_gkpsch_settings' ) as $capability ) { $role->add_cap( $capability ); } }
        update_option( 'gkpsch_capabilities_version', 1, false );
    }
    public static function maybe_upgrade(): void {
        if ( (int) get_option( 'gkpsch_capabilities_version', 0 ) < 1 ) { self::install(); }
    }
    public static function directory(): array {
        $keys = array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts', 'read' );
        return array_fill_keys( $keys, 'manage_gkpsch_schedule' );
    }
}
