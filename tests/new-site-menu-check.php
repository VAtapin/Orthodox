<?php
/** Read-only check for the visual menu on the test WordPress. Run with wp eval-file. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This check is limited to the test WordPress.' );
}

if ( 'visual' !== georg_kloster_prihod_options()['menu_style'] || ! has_nav_menu( 'primary' ) ) {
    throw new RuntimeException( 'The visual menu or primary WordPress menu is not active.' );
}

foreach ( array( 'ru', 'de' ) as $language ) {
    do_action( 'wpml_switch_language', $language );
    $html = prihod_theme_header();
    if ( ! str_contains( $html, 'class="prihod-visual-menu"' ) || ! str_contains( $html, 'id="prihod-visual-menu"' ) || ! str_contains( $html, 'class="prihod-visual-list"' ) || ! str_contains( $html, 'prihod-visual-close' ) ) {
        throw new RuntimeException( 'Visual menu markup is missing: ' . $language );
    }
    if ( str_contains( $html, 'id="prihod-mobile-menu"' ) || ! str_contains( $html, 'prihod-menu-title' ) ) {
        throw new RuntimeException( 'Duplicate legacy menu or missing visual items: ' . $language );
    }
    if ( ! str_contains( $html, 'id="prihod-desktop-menu"' ) || str_contains( $html, 'prihod-menu-chevron' ) || str_contains( $html, '⌄' ) ) {
        throw new RuntimeException( 'Simple desktop links or clean visual-menu trigger are missing: ' . $language );
    }
    preg_match_all( '/\bid="([^"]+)"/', $html, $ids );
    if ( count( $ids[1] ) !== count( array_unique( $ids[1] ) ) ) {
        throw new RuntimeException( 'Header has duplicate element IDs: ' . $language );
    }
    $locations = get_nav_menu_locations();
    $items = wp_get_nav_menu_items( $locations['primary'] );
    if ( ! is_array( $items ) || count( $items ) < 3 ) {
        throw new RuntimeException( 'Primary menu items are missing: ' . $language );
    }
    foreach ( $items as $item ) {
        if ( ! str_contains( $html, esc_url( $item->url ) ) ) {
            throw new RuntimeException( 'Menu link is missing: ' . $language . '/' . $item->ID );
        }
    }
    echo 'Visual menu OK: ' . $language . ' (' . count( $items ) . ' items)' . PHP_EOL;
}

$classic_options = georg_kloster_prihod_options();
$classic_options['menu_style'] = 'classic';
$classic_filter = static fn() => $classic_options;
add_filter( 'pre_option_georg_kloster_prihod_options', $classic_filter );
try {
    $classic_html = prihod_theme_header();
    if ( ! str_contains( $classic_html, 'id="prihod-desktop-menu"' ) || ! str_contains( $classic_html, 'id="prihod-mobile-menu"' ) || str_contains( $classic_html, 'class="prihod-visual-menu"' ) ) {
        throw new RuntimeException( 'The simple-menu-only setting must retain desktop and mobile links.' );
    }
} finally {
    remove_filter( 'pre_option_georg_kloster_prihod_options', $classic_filter );
}
echo 'Simple-menu-only setting OK' . PHP_EOL;

require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
ob_start();
prihod_theme_settings_page();
$settings = ob_get_clean();
if ( ! str_contains( $settings, 'georg_kloster_prihod_options[menu_style]' ) || ! str_contains( $settings, 'georg_kloster_prihod_options[menu_columns]' ) ) {
    throw new RuntimeException( 'Visual menu theme controls are missing.' );
}
ob_start();
do_action( 'wp_nav_menu_item_custom_fields', $items[0]->ID, $items[0], 0, new stdClass() );
$field = ob_get_clean();
if ( ! str_contains( $field, 'prihod_menu_image[' . $items[0]->ID . ']' ) || ! str_contains( $field, 'prihod_menu_image_nonce[' . $items[0]->ID . ']' ) ) {
    throw new RuntimeException( 'The menu item image selector is missing.' );
}
echo 'Menu editor controls OK' . PHP_EOL;
