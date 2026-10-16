<?php
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

wp_set_current_user( 1 );
$activated = activate_plugin( 'prihod/prihod.php' );
if ( is_wp_error( $activated ) ) { throw new RuntimeException( $activated->get_error_message() ); }
wp_set_current_user( 0 );
wp_set_current_user( 1 );
Prihod\Plugin::boot();
if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'prihod/profile' ) ) {
    Prihod\Public_View::register();
}

$theme = wp_get_theme( 'georg-kloster-prihod' );
if ( ! $theme->exists() || 'georg-kloster-prihod' !== get_stylesheet() ) {
    throw new RuntimeException( 'Theme and Core cannot run together.' );
}
$plan = Prihod\Site_Plan::plan( array(
    'theme_choice' => 'georg-kloster-prihod',
    'scheme_choice' => 'blue',
    'pages' => array(),
    'modules' => array(),
    'profile' => array( 'name' => 'Test parish', 'address' => 'Test address' ),
) );
Prihod\Site_Plan::apply( $plan );
WP_Theme_JSON_Resolver::clean_cached_data();
if ( 'blue' !== get_option( 'georg_kloster_prihod_options' )['scheme'] || 'blue' !== prihod_theme_scheme() ) {
    throw new RuntimeException( 'Core wizard did not apply the new theme style.' );
}
if ( ! str_contains( prihod_theme_brand(), 'Test parish' ) ) {
    throw new RuntimeException( 'Theme did not display the Core profile.' );
}
if ( ! str_contains( prihod_theme_contact_panel(), 'Test address' ) ) {
    throw new RuntimeException( 'Theme did not display the Core contact address.' );
}
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Core content', 'post_content' => '<!-- wp:prihod/profile /-->' ), true );
if ( is_wp_error( $page ) ) { throw new RuntimeException( 'Core page creation failed.' ); }
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page ) );
if ( ! have_posts() ) { throw new RuntimeException( 'Core content page query is empty.' ); }
ob_start();
include get_template_directory() . '/page.php';
$markup = ob_get_clean();
if ( ! str_contains( $markup, 'Test parish' ) || ! str_contains( $markup, 'prihod-profile' ) ) {
    throw new RuntimeException( 'Theme did not display the Core block inside a page.' );
}
echo 'THEME_CORE_OK';
