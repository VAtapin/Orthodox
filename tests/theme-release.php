<?php
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

function georg_theme_release_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

$theme = wp_get_theme();
georg_theme_release_assert( 'georg-kloster-prihod' === $theme->get_stylesheet(), 'Wrong active theme.' );
georg_theme_release_assert( $theme->exists() && ! $theme->errors() && ! $theme->is_block_theme(), 'Classic theme cannot activate.' );
georg_theme_release_assert( ! in_array( 'prihod/prihod.php', get_option( 'active_plugins', array() ), true ), 'Standalone theme test must run without Core.' );
georg_theme_release_assert( function_exists( 'prihod_theme_home' ), 'Theme renderer did not load.' );
georg_theme_release_assert( ! WP_Block_Type_Registry::get_instance()->is_registered( 'georg-kloster-prihod/home' ), 'Theme registered a custom block.' );
georg_theme_release_assert( has_filter( 'option_page_capability_prihod_theme' ), 'Theme settings capability is missing.' );
georg_theme_release_assert( isset( $GLOBALS['wp_registered_sidebars']['prihod-sidebar'] ), 'Theme registers the standard right sidebar widget area.' );
georg_theme_release_assert( str_contains( prihod_theme_brand(), 'prihod-mark' ), 'Default theme logo is hidden.' );
georg_theme_release_assert( '<figure>Icon</figure>' === apply_filters( 'prihod_schedule_icon_markup', '<figure>Icon</figure>', array( 'start' => new DateTimeImmutable() ), null ), 'Theme assumes the legacy Core calendar class exists.' );

$home_page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Parish home', 'post_content' => '<!-- wp:paragraph --><p>Owner content</p><!-- /wp:paragraph -->' ), true );
georg_theme_release_assert( ! is_wp_error( $home_page_id ), 'Could not create test page.' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $home_page_id );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $home_page_id ) );
georg_theme_release_assert( have_posts(), 'Front page query is empty.' );
the_post();

foreach ( array( 'classic', 'hospitality', 'editorial', 'tradition', 'blue' ) as $scheme ) {
    update_option( 'georg_kloster_prihod_options', array( 'scheme' => $scheme ) );
    WP_Theme_JSON_Resolver::clean_cached_data();
    georg_theme_release_assert( $scheme === prihod_theme_scheme(), 'Scheme not applied: ' . $scheme );
    $html = prihod_theme_home();
    georg_theme_release_assert( str_contains( $html, 'prihod-home-hero' ) && ! str_contains( $html, 'prihod-home-contacts' ), 'Standalone front page contains an empty contact panel: ' . $scheme );
    georg_theme_release_assert( is_file( get_template_directory() . '/front-page.php' ), 'Front page template missing.' );
}

ob_start();
include get_template_directory() . '/front-page.php';
$markup = ob_get_clean();
georg_theme_release_assert( str_contains( $markup, 'id="prihod-main"' ), 'Skip-link target missing.' );
georg_theme_release_assert( str_contains( $markup, 'Owner content' ), 'Owner content missing.' );
georg_theme_release_assert( str_contains( $markup, '</html>' ), 'Template did not complete.' );

register_block_type( 'georg-test/contact', array( 'render_callback' => static fn() => '<p>Plugin block content</p>' ) );
add_shortcode( 'georg_test_contact', static fn() => '<p>Plugin shortcode content</p>' );
$plugin_page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Plugin content', 'post_content' => '<!-- wp:georg-test/contact /-->[georg_test_contact]' ), true );
georg_theme_release_assert( ! is_wp_error( $plugin_page ), 'Could not create plugin content page.' );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $plugin_page ) );
georg_theme_release_assert( have_posts(), 'Plugin content page query is empty.' );
ob_start();
include get_template_directory() . '/page.php';
$plugin_markup = ob_get_clean();
georg_theme_release_assert( str_contains( $plugin_markup, 'Plugin block content' ), 'Theme does not display plugin supplied block: ' . substr( wp_strip_all_tags( $plugin_markup ), -500 ) );
georg_theme_release_assert( str_contains( $plugin_markup, 'Plugin shortcode content' ), 'Theme does not display plugin supplied shortcode.' );
wp_register_sidebar_widget( 'theme-release-widget', 'Theme release widget', static function (): void { echo '<p>Theme widget marker</p>'; } );
update_option( 'sidebars_widgets', array( 'wp_inactive_widgets' => array(), 'prihod-sidebar' => array( 'theme-release-widget' ) ) );
georg_theme_release_assert( str_contains( prihod_theme_sidebar(), 'Theme widget marker' ), 'A standard WordPress sidebar widget is rendered in the right column.' );
wp_update_post( array( 'ID' => $home_page_id, 'post_content' => '<!-- wp:georg-test/contact /-->' ) );
georg_theme_release_assert( str_contains( prihod_theme_home_content(), 'Plugin block content' ), 'Plugin content on front page is hidden.' );
unregister_block_type( 'georg-test/contact' );
remove_shortcode( 'georg_test_contact' );

wp_set_current_user( 1 );
ob_start();
prihod_theme_settings_page();
$settings_markup = ob_get_clean();
georg_theme_release_assert( str_contains( $settings_markup, 'georg_kloster_prihod_options[hero_image]' ), 'Generic hero image control is missing.' );
georg_theme_release_assert( str_contains( $settings_markup, 'georg_kloster_prihod_options[menu_style]' ) && str_contains( $settings_markup, 'georg_kloster_prihod_options[menu_columns]' ), 'Visual menu settings are missing.' );
foreach ( array( 'prihod-option-photos', 'georg_kloster_prihod_options[feature_schedule]', 'georg_kloster_prihod_options[map_url]', 'georg_kloster_prihod_options[request_image]' ) as $plugin_control ) {
    georg_theme_release_assert( ! str_contains( $settings_markup, $plugin_control ), 'Plugin content control remains in theme settings: ' . $plugin_control );
}
$sanitized = prihod_theme_sanitize( array( 'scheme' => 'classic', 'hero_image' => 0, 'feature_schedule' => 123, 'map_embed' => 'https://www.openstreetmap.org/export/embed.html' ) );
georg_theme_release_assert( ! array_key_exists( 'feature_schedule', $sanitized ) && ! array_key_exists( 'map_embed', $sanitized ), 'Theme still stores plugin content settings.' );
georg_theme_release_assert( 'visual' === prihod_theme_sanitize( array( 'menu_style' => 'invalid', 'menu_columns' => 99 ) )['menu_style'] && 4 === prihod_theme_sanitize( array( 'menu_style' => 'visual', 'menu_columns' => 99 ) )['menu_columns'], 'Visual menu options must be bounded.' );
wp_set_current_user( 0 );

$menu = wp_create_nav_menu( 'Release check menu' );
georg_theme_release_assert( ! is_wp_error( $menu ), 'Could not create a standard WordPress menu.' );
$item_id = wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Standard menu link', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish' ) );
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu ) );
$header = prihod_theme_header();
georg_theme_release_assert( 2 === substr_count( $header, 'Standard menu link' ) && str_contains( $header, 'id="prihod-desktop-menu"' ) && str_contains( $header, 'id="prihod-visual-menu"' ), 'Visual menu must supplement the simple desktop links.' );
georg_theme_release_assert( ! str_contains( $header, 'prihod-menu-chevron' ) && 1 === preg_match_all( '/id="menu-item-\d+"/', $header ), 'Menu trigger and duplicate item IDs must be cleaned up.' );
wp_set_current_user( 1 );
$menu_item = wp_setup_nav_menu_item( get_post( $item_id ) );
ob_start(); do_action( 'wp_nav_menu_item_custom_fields', $item_id, $menu_item, 0, new stdClass() ); $menu_fields = ob_get_clean();
georg_theme_release_assert( str_contains( $menu_fields, 'name="prihod_menu_image[' . $item_id . ']"' ) && str_contains( $menu_fields, 'prihod_menu_image_nonce[' . $item_id . ']' ), 'Menu image field and nonce are missing.' );
$image_file = wp_upload_bits( 'menu-fixture.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP1cAAAAASUVORK5CYII=' ) );
$image_id = wp_insert_attachment( array( 'post_title' => 'Menu image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $image_file['file'] );
update_post_meta( $item_id, '_prihod_menu_image', $image_id );
georg_theme_release_assert( $image_id === prihod_theme_menu_image_id( $menu_item ), 'Chosen menu image must override the linked content thumbnail.' );
update_post_meta( $item_id, '_prihod_menu_image', 999999 );
georg_theme_release_assert( 0 === prihod_theme_menu_image_id( $menu_item ), 'A missing attachment must not appear as a menu image.' );
delete_post_meta( $item_id, '_prihod_menu_image' );
update_option( 'georg_kloster_prihod_options', array( 'menu_style' => 'classic' ) );
$header = prihod_theme_header();
georg_theme_release_assert( 2 === substr_count( $header, 'Standard menu link' ), 'Classic menu must be available in desktop and mobile navigation.' );
georg_theme_release_assert( str_contains( $header, 'prihod-mobile-menu' ) && str_contains( $header, 'prihod-mobile-panel' ) && str_contains( $header, 'prihod-desktop-navigation' ), 'Compact mobile navigation is missing.' );
georg_theme_release_assert( str_contains( $header, 'id="prihod-desktop-menu"' ) && str_contains( $header, 'id="prihod-mobile-menu"' ), 'Navigation lists need distinct IDs.' );
preg_match_all( '/\bid="([^"]+)"/', $header, $header_ids );
georg_theme_release_assert( count( $header_ids[1] ) === count( array_unique( $header_ids[1] ) ), 'Duplicated menu item IDs are invalid HTML.' );

echo 'THEME_RELEASE_OK ' . esc_html( get_bloginfo( 'version' ) ) . ' PHP ' . esc_html( PHP_VERSION );
