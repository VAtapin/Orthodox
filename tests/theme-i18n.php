<?php
/** Disposable WordPress only. Exercise native gettext and renderer escaping. */
require_once '/wordpress/wp-load.php';
$i18n_checks = 0;
$i18n_check = static function ( bool $ok, string $message ) use ( &$i18n_checks ): void {
    ++$i18n_checks;
    if ( ! $ok ) { throw new RuntimeException( $message ); }
};
$domain = 'georg-kloster-prihod';
$saved_options = get_option( 'georg_kloster_prihod_options' );
$saved_content = get_posts( array( 'post_type' => array( 'page', 'post' ), 'numberposts' => -1 ) );
foreach ( array( 'ru_RU', 'de_DE', 'pl_PL', 'uk' ) as $catalog_locale ) {
    unload_textdomain( $domain, true );
    $i18n_check( load_textdomain( $domain, get_template_directory() . '/languages/' . $catalog_locale . '.mo' ), 'WordPress could not load ' . $catalog_locale );
    $i18n_check( 'Home' !== __( 'Home', 'georg-kloster-prihod' ), 'Untranslated breadcrumb in ' . $catalog_locale );
    $i18n_check( 'Parish Appearance' !== __( 'Parish Appearance', 'georg-kloster-prihod' ), 'Untranslated settings in ' . $catalog_locale );
    $i18n_check( 'Wide page' !== _x( 'Wide page', 'Custom template name', 'georg-kloster-prihod' ), 'Missing JSON template context in ' . $catalog_locale );
    $i18n_check( 'Wide' !== __( 'Wide', 'georg-kloster-prihod' ), 'Missing classic template title in ' . $catalog_locale );
    foreach ( array_keys( prihod_theme_schemes() ) as $scheme ) {
        update_option( 'georg_kloster_prihod_options', array( 'scheme' => $scheme ) );
        WP_Theme_JSON_Resolver::clean_cached_data();
        $palette = WP_Theme_JSON_Resolver::get_merged_data()->get_settings()['color']['palette']['theme'];
        $i18n_check( $palette[0]['name'] === _x( 'Background', 'Color name', 'georg-kloster-prihod' ) && 'Background' !== $palette[0]['name'], 'Untranslated scheme palette: ' . $catalog_locale . '/' . $scheme );
    }
}
unload_textdomain( $domain, true );
$i18n_check( 'Home' === __( 'Home', 'georg-kloster-prihod' ), 'English does not work without a catalog.' );
$i18n_check( 'Background' === prihod_theme_scheme_data( 'classic' )['settings']['color']['palette'][0]['name'], 'English JSON fallback failed.' );
$attack = '<img src=x onerror=alert(1)>';
$section = prihod_theme_section_title( $attack, 'javascript:alert(1)', $attack, $attack );
$i18n_check( ! str_contains( $section, '<img' ) && ! str_contains( $section, 'javascript:' ) && str_contains( $section, '&lt;img' ), 'Section renderer does not escape text or URLs.' );
update_option( 'georg_kloster_prihod_options', array( 'quote_text' => $attack, 'quote_source' => $attack, 'profile' => array( 'name' => $attack ) ) );
$i18n_check( ! str_contains( prihod_theme_verse(), '<img' ) && ! str_contains( prihod_theme_brand(), '<img src=x' ), 'Quote or brand renderer does not escape owner text.' );
if ( false === $saved_options ) { delete_option( 'georg_kloster_prihod_options' ); } else { update_option( 'georg_kloster_prihod_options', $saved_options ); }
$i18n_check( $saved_content == get_posts( array( 'post_type' => array( 'page', 'post' ), 'numberposts' => -1 ) ), 'Translation loading changed page content.' );
WP_Theme_JSON_Resolver::clean_cached_data();
file_put_contents( __DIR__ . '/.theme-i18n-result.json', wp_json_encode( array( 'checks' => $i18n_checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'THEME_I18N_OK' ) ) );
echo 'THEME_I18N_OK ' . $i18n_checks;
