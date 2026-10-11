<?php
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
if ( is_plugin_active( 'prihod/prihod.php' ) || class_exists( '\Prihod\Plugin' ) ) { throw new RuntimeException( 'Independent connector check requires inactive Core.' ); }
foreach ( array( 'telegram', 'whatsapp', 'facebook' ) as $provider ) {
    $connector = PrihodConnect\Connector::boot( $provider, $provider, '' );
    if ( 'needs_setup' !== $connector->health()['status'] ) { throw new RuntimeException( 'Disabled independent connector should need setup.' ); }
    $connector->menu();
    global $menu;
    if ( ! in_array( 'prihod-connect-' . $provider, array_column( $menu, 2 ), true ) ) { throw new RuntimeException( 'Standalone connector requires its own plugin menu.' ); }
}
file_put_contents( __DIR__ . '/.connectors-result.json', wp_json_encode( array( 'status' => 'CONNECTORS_ALONE_OK', 'wordpress' => get_bloginfo( 'version' ) ) ) );
