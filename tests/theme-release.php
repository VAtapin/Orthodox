<?php
require_once '/wordpress/wp-load.php';

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

$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Parish home', 'post_content' => '<!-- wp:paragraph --><p>Owner content</p><!-- /wp:paragraph -->' ), true );
georg_theme_release_assert( ! is_wp_error( $page ), 'Could not create test page.' );
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page ) );
georg_theme_release_assert( have_posts(), 'Front page query is empty.' );
the_post();

foreach ( array( 'classic', 'hospitality', 'editorial', 'tradition', 'blue' ) as $scheme ) {
    update_option( 'georg_kloster_prihod_options', array( 'scheme' => $scheme ) );
    WP_Theme_JSON_Resolver::clean_cached_data();
    georg_theme_release_assert( $scheme === prihod_theme_scheme(), 'Scheme not applied: ' . $scheme );
    $html = prihod_theme_home();
    georg_theme_release_assert( str_contains( $html, 'prihod-home-hero' ) && str_contains( $html, 'prihod-home-contacts' ), 'Incomplete front page: ' . $scheme );
    georg_theme_release_assert( is_file( get_template_directory() . '/front-page.php' ), 'Front page template missing.' );
}

ob_start();
include get_template_directory() . '/front-page.php';
$markup = ob_get_clean();
georg_theme_release_assert( str_contains( $markup, 'id="prihod-main"' ), 'Skip-link target missing.' );
georg_theme_release_assert( str_contains( $markup, 'Owner content' ), 'Owner content missing.' );
georg_theme_release_assert( str_contains( $markup, '</html>' ), 'Template did not complete.' );

$menu = wp_create_nav_menu( 'Release check menu' );
georg_theme_release_assert( ! is_wp_error( $menu ), 'Could not create a standard WordPress menu.' );
wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => 'Standard menu link', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish' ) );
set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu ) );
georg_theme_release_assert( str_contains( prihod_theme_header(), 'Standard menu link' ), 'Standard WordPress menu did not render.' );

echo 'THEME_RELEASE_OK ' . esc_html( get_bloginfo( 'version' ) ) . ' PHP ' . esc_html( PHP_VERSION );
