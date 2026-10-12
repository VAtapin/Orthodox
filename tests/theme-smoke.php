<?php
$theme = wp_get_theme( 'prihod-theme' );
prihod_assert( $theme->exists() && ! $theme->errors() && $theme->is_block_theme(), 'Optional theme is recognized as a valid block theme.' );
$old_theme = get_stylesheet();
switch_theme( 'prihod-theme' );
require_once WP_CONTENT_DIR . '/themes/prihod-theme/functions.php';
load_theme_textdomain( 'prihod-theme', WP_CONTENT_DIR . '/themes/prihod-theme/languages' );
prihod_theme_register_blocks();
prihod_assert( wp_theme_has_theme_json(), 'Theme provides structured global styles.' );
$template = get_block_template( 'prihod-theme//page', 'wp_template' );
prihod_assert( $template && str_contains( $template->content, 'wp:post-content' ), 'Page template renders standard WordPress content.' );
prihod_assert( 'Parish' === Prihod\Profile::get()['name'], 'Changing theme does not migrate or replace parish data.' );
$json = WP_Theme_JSON_Resolver::get_theme_data()->get_raw_data();
$palette = $json['settings']['color']['palette'];
prihod_assert( count( $palette['theme'] ?? $palette ) >= 5, 'Theme palette is available to the editor.' );
$variants = glob( WP_CONTENT_DIR . '/themes/prihod-theme/styles/*.json' );
prihod_assert( 4 === count( $variants ), 'Default and four style variations provide exactly five schemes.' );
foreach ( array( 'wide', 'focused', 'catalog' ) as $name ) { prihod_assert( (bool) get_block_template( 'prihod-theme//' . $name, 'wp_template' ), 'Reference inner page template is registered: ' . $name ); }
$options = prihod_theme_sanitize( array( 'hero_image' => $location, 'map_url' => 'javascript:alert(1)', 'languages' => array( 'ru' => home_url( '/ru/' ), 'de' => 'javascript:alert(1)' ) ) );
prihod_assert( 0 === $options['hero_image'] && '' === $options['map_url'] && ! isset( $options['languages']['de'] ), 'Theme validates media and language links without accepting scripts.' );
foreach ( array( 'de_DE' => 'Willkommen in unserer Gemeinde!', 'pl_PL' => 'Witamy w naszej parafii!', 'uk' => 'Ласкаво просимо до нашої парафії!' ) as $locale => $expected ) {
    load_textdomain( 'prihod-theme', WP_CONTENT_DIR . '/themes/prihod-theme/languages/' . $locale . '.mo' );
    prihod_assert( $expected === __( 'Добро пожаловать в наш приход!', 'prihod-theme' ), 'Bundled theme translation works: ' . $locale );
    unload_textdomain( 'prihod-theme' );
    load_textdomain( 'prihod', WP_PLUGIN_DIR . '/prihod/languages/prihod-' . $locale . '.mo' );
    prihod_assert( 'Возможности' !== Prihod\Wizard::steps()[2], 'Bundled Core locale is available: ' . $locale );
    prihod_assert( 'Мастер настройки' !== __( 'Мастер настройки', 'prihod' ), 'Wizard translation works: ' . $locale );
    unload_textdomain( 'prihod' );
}
prihod_assert( str_contains( prihod_theme_home(), 'prihod-home-hero' ), 'Home renderer uses actual theme markup.' );
add_shortcode( 'orthocal_today', fn() => 'calendar-sidebar-marker' );
add_shortcode( 'orthocal_calendar', fn() => '' );
$saved_post = $GLOBALS['post'] ?? null;
$calendar_fixture = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Calendar fixture', 'post_content' => '[orthocal_calendar]' ) );
$GLOBALS['post'] = get_post( $calendar_fixture );
prihod_assert( ! str_contains( prihod_theme_sidebar(), 'calendar-sidebar-marker' ), 'Full calendar page omits duplicate sidebar calendar.' );
wp_update_post( array( 'ID' => $calendar_fixture, 'post_content' => 'Ordinary page' ) );
$GLOBALS['post'] = get_post( $calendar_fixture );
prihod_assert( str_contains( prihod_theme_sidebar(), 'calendar-sidebar-marker' ), 'Other pages retain calendar sidebar.' );
$GLOBALS['post'] = $saved_post; remove_shortcode( 'orthocal_today' ); remove_shortcode( 'orthocal_calendar' );
wp_delete_post( $calendar_fixture, true );
add_shortcode( 'kloster_form', fn() => '' );
wp_register_script( 'georg-kloster-orthodox-donate', '/test-provider.js' );
do_action( 'wp_enqueue_scripts' );
prihod_assert( in_array( 'georg-kloster-orthodox-donate', wp_scripts()->registered['prihod-theme-forms']->deps, true ), 'Stepped forms preserve the current provider initialization order.' );
remove_shortcode( 'kloster_form' );
// Restore baseline for unrelated tests. Standalone theme verification uses a separate request.
require __DIR__ . '/theme-options-smoke.php';
switch_theme( $old_theme );
