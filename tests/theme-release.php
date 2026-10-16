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
wp_update_post( array( 'ID' => $home_page_id, 'post_content' => '<!-- wp:georg-test/contact /-->' ) );
georg_theme_release_assert( str_contains( prihod_theme_home_content(), 'Plugin block content' ), 'Plugin content on front page is hidden.' );
unregister_block_type( 'georg-test/contact' );
remove_shortcode( 'georg_test_contact' );

wp_set_current_user( 1 );
ob_start();
prihod_theme_settings_page();
$settings_markup = ob_get_clean();
georg_theme_release_assert( str_contains( $settings_markup, 'georg_kloster_prihod_options[hero_image]' ), 'Generic hero image control is missing.' );
foreach ( array( 'prihod-option-photos', 'georg_kloster_prihod_options[feature_schedule]', 'georg_kloster_prihod_options[map_url]', 'georg_kloster_prihod_options[request_image]' ) as $plugin_control ) {
    georg_theme_release_assert( ! str_contains( $settings_markup, $plugin_control ), 'Plugin content control remains in theme settings: ' . $plugin_control );
}
$sanitized = prihod_theme_sanitize( array( 'scheme' => 'classic', 'hero_image' => 0, 'feature_schedule' => 123, 'map_embed' => 'https://www.openstreetmap.org/export/embed.html' ) );
georg_theme_release_assert( ! array_key_exists( 'feature_schedule', $sanitized ) && ! array_key_exists( 'map_embed', $sanitized ), 'Theme still stores plugin content settings.' );
wp_set_current_user( 0 );

$menu = wp_create_nav_menu( 'Release check menu' );
georg_theme_release_assert( ! is_wp_error( $menu ), 'Could not create a standard WordPress menu.' );
wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Standard menu link', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish' ) );
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu ) );
georg_theme_release_assert( str_contains( prihod_theme_header(), 'Standard menu link' ), 'Standard WordPress menu did not render.' );

echo 'THEME_RELEASE_OK ' . esc_html( get_bloginfo( 'version' ) ) . ' PHP ' . esc_html( PHP_VERSION );
