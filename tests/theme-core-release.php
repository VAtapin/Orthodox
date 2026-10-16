<?php
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

wp_set_current_user( 1 );
$activated = activate_plugin( 'prihod/prihod.php' );
if ( is_wp_error( $activated ) ) { throw new RuntimeException( $activated->get_error_message() ); }
wp_set_current_user( 0 );
wp_set_current_user( 1 );
Prihod\Plugin::boot();

$theme = wp_get_theme( 'georg-kloster-prihod' );
if ( ! $theme->exists() || 'georg-kloster-prihod' !== get_stylesheet() ) {
    throw new RuntimeException( 'Theme and Core cannot run together.' );
}
$plan = Prihod\Site_Plan::plan( array(
    'theme_choice' => 'georg-kloster-prihod',
    'scheme_choice' => 'blue',
    'pages' => array(),
    'modules' => array(),
    'profile' => array( 'name' => 'Test parish' ),
) );
Prihod\Site_Plan::apply( $plan );
WP_Theme_JSON_Resolver::clean_cached_data();
if ( 'blue' !== get_option( 'georg_kloster_prihod_options' )['scheme'] || 'blue' !== prihod_theme_scheme() ) {
    throw new RuntimeException( 'Core wizard did not apply the new theme style.' );
}
if ( ! str_contains( prihod_theme_brand(), 'Test parish' ) ) {
    throw new RuntimeException( 'Theme did not display the Core profile.' );
}
echo 'THEME_CORE_OK';
