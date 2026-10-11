<?php
// Non-destructive verification of an already installed, explicitly authorized test site.
defined( 'ABSPATH' ) || exit;
if ( 'prihod.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { throw new RuntimeException( 'This check is restricted to the authorized test domain.' ); }
$checked = 0;
$check = function ( bool $ok, string $label ) use ( &$checked ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checked; };
require_once ABSPATH . 'wp-admin/includes/plugin.php';
foreach ( array( 'prihod/prihod.php', 'orthocal/orthocal.php', 'georg-kloster-orthodox-donate/georg-kloster-orthodox-donate.php', 'prihod-telegram/prihod-telegram.php', 'prihod-whatsapp/prihod-whatsapp.php', 'prihod-social/prihod-social.php' ) as $plugin ) { $check( is_plugin_active( $plugin ), 'Inactive component: ' . $plugin ); }
$check( 'prihod-theme' === get_stylesheet(), 'Prihod theme must be active.' );
$check( false === apply_filters( 'pre_http_request', false, array(), 'https://example.invalid/' ), 'HTTP must not be blocked in WordPress.' );
$check( null === apply_filters( 'pre_wp_mail', null, array() ), 'Email must not be blocked in WordPress.' );
$form_tag = shortcode_exists( 'gkod_form' ) ? 'gkod_form' : 'kloster_form';
$donation_tag = shortcode_exists( 'gkod_donation' ) ? 'gkod_donation' : 'kloster_donation';
foreach ( array( 'orthocal_calendar', 'orthocal_today', $form_tag, $donation_tag ) as $tag ) { $check( shortcode_exists( $tag ), 'Missing actual shortcode: ' . $tag ); }
foreach ( array( 'prihod/profile', 'prihod/schedule', 'prihod/requests', 'prihod-theme/home' ) as $block ) { $check( WP_Block_Type_Registry::get_instance()->is_registered( $block ), 'Missing block: ' . $block ); }
$check( str_contains( do_shortcode( '[' . $form_tag . ']' ), '<form' ), 'Actual prayer form must render.' );
$check( str_contains( do_shortcode( '[' . $donation_tag . ']' ), '<form' ), 'Actual donation form must render.' );
$check( '' !== do_shortcode( '[orthocal_today]' ), 'Actual calendar must render its current state.' );
$check( str_contains( prihod_theme_home(), 'prihod-home-hero' ), 'Theme must render actual homepage.' );
$check( 4 === count( glob( get_template_directory() . '/styles/*.json' ) ), 'Five schemes required.' );
foreach ( array( 'wide', 'focused', 'catalog' ) as $name ) { $check( (bool) get_block_template( 'prihod-theme//' . $name, 'wp_template' ), 'Missing inner template: ' . $name ); }
$previous = get_current_user_id(); wp_set_current_user( get_user_by( 'login', 'prihod-owner' )->ID );
$check( current_user_can( 'manage_prihod_settings' ), 'Owner must have setup access.' );
ob_start(); Prihod\Wizard::render(); $wizard = ob_get_clean();
$check( str_contains( $wizard, 'prihod-wizard' ), 'Setup wizard must render.' );
ob_start(); Prihod\Dashboard::overview(); $dashboard = ob_get_clean();
$check( str_contains( $dashboard, 'prihod-plugins' ) && str_contains( $dashboard, 'prihod-wizard' ), 'Dashboard must expose plugins and setup.' );
ob_start(); Prihod\Dashboard::plugins(); $plugins_html = ob_get_clean();
$check( str_contains( $plugins_html, '1.0.2' ) && str_contains( $plugins_html, 'prihod-connect-telegram' ), 'Plugin cards show installed donation version and connector settings.' );
$check( 'ready' === ( new Prihod\Existing_Integration( 'calendar' ) )->health()['status'], 'Public calendar API must not require a legacy key.' );
wp_set_current_user( $previous );
foreach ( array( 'de_DE', 'pl_PL', 'uk' ) as $locale ) {
    load_textdomain( 'prihod-theme', get_template_directory() . '/languages/' . $locale . '.mo' );
    $check( 'Главная' !== __( 'Главная', 'prihod-theme' ), 'Missing theme locale: ' . $locale );
    unload_textdomain( 'prihod-theme' );
}
echo wp_json_encode( array( 'status' => 'PRIHOD_SERVER_OK', 'checks' => $checked, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'theme' => wp_get_theme()->get( 'Version' ) ), JSON_UNESCAPED_UNICODE ) . PHP_EOL;
