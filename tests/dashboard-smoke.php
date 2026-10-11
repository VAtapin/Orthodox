<?php
require_once ABSPATH . 'wp-admin/includes/dashboard.php';
wp_set_current_user( 1 );
set_current_screen( 'dashboard' );
Prihod\Dashboard::menu();
Prihod\Dashboard::widgets();
global $wp_meta_boxes, $submenu;
foreach ( array( 'profile', 'services', 'requests', 'plugins' ) as $widget ) {
    $found = false;
    foreach ( $wp_meta_boxes['dashboard'] ?? array() as $contexts ) { foreach ( $contexts as $boxes ) { if ( isset( $boxes['prihod_dashboard_' . $widget] ) ) { $found = true; } } }
    prihod_assert( $found, 'Owner dashboard widget exists: ' . $widget );
}
ob_start(); Prihod\Dashboard::overview(); $overview = ob_get_clean();
prihod_assert( str_contains( $overview, 'prihod-wizard' ) && str_contains( $overview, 'prihod-plugins' ) && str_contains( $overview, 'prihod-settings-hub' ), 'Plugin dashboard exposes wizard and grouped navigation buttons.' );
ob_start(); Prihod\Dashboard::settings(); $settings_hub = ob_get_clean();
prihod_assert( str_contains( $settings_hub, 'prihod-connect-telegram' ) && str_contains( $settings_hub, 'prihod-connect-whatsapp' ) && str_contains( $settings_hub, 'prihod-connect-facebook' ), 'Settings hub provides direct buttons for every connector.' );
Prihod\Dashboard::organize_menu();
prihod_assert( 'prihod' === $submenu['prihod'][0][2], 'Primary menu opens dashboard instead of location records.' );
$slugs = array_column( $submenu['prihod'], 2 );
prihod_assert( in_array( 'prihod-info', $slugs, true ) && in_array( 'prihod-plugins', $slugs, true ) && ! in_array( 'prihod-profile', $slugs, true ), 'Menu groups information, plugins and settings without exposing every settings form.' );
global $plugin_page, $pagenow, $parent_file, $_registered_pages;
$previous_page = $plugin_page ?? null; $previous_pagenow = $pagenow ?? null;
foreach ( array( 'prihod-profile', 'prihod-settings', 'prihod-connect-telegram', 'prihod-connect-whatsapp', 'prihod-connect-facebook' ) as $route ) {
    $plugin_page = $route; $pagenow = 'admin.php'; $parent_file = '';
    $hook = get_plugin_page_hookname( $route, get_admin_page_parent() );
    prihod_assert( ! empty( $_registered_pages[$hook] ) && has_action( $hook ), 'Grouped page retains a real authorized route: ' . $route );
    prihod_assert( ( 'prihod-profile' === $route ? 'prihod-info' : 'prihod-settings-hub' ) === apply_filters( 'submenu_file', $route ), 'Grouped page highlights its information or settings hub.' );
}
prihod_assert( 'prihod' === apply_filters( 'parent_file', 'prihod-settings-hub' ), 'Grouped settings retain the Prihod parent menu.' );
$plugin_page = $previous_page; $pagenow = $previous_pagenow; $parent_file = '';
$previous_boxes = $wp_meta_boxes;
$dashboard_editor = wp_insert_user( array( 'user_login' => 'dashboard_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
wp_set_current_user( $dashboard_editor ); unset( $wp_meta_boxes['dashboard'] ); Prihod\Dashboard::widgets();
prihod_assert( empty( $wp_meta_boxes['dashboard'] ), 'Unassigned editor cannot see parish or private request widgets.' );
ob_start(); Prihod\Dashboard::requests_card(); $private = ob_get_clean();
prihod_assert( '' === $private, 'Private request summary is capability protected.' );
$wp_meta_boxes = $previous_boxes; wp_set_current_user( 1 );
