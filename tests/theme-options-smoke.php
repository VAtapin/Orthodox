<?php
$previous_options = get_option( 'prihod_theme_options', array() );
$sanitized = prihod_theme_sanitize( array( 'scheme' => '../../escape', 'width' => 99999, 'font_size' => 1, 'font' => 'bad', 'footer_text' => '<script>bad</script>Footer', 'logo' => $location ) );
prihod_assert( 1440 === $sanitized['width'] && 14 === $sanitized['font_size'] && 'sans' === $sanitized['font'] && 0 === $sanitized['logo'] && ! str_contains( $sanitized['footer_text'], '<script' ), 'Theme options enforce bounds, allowed fonts, media types and plain text.' );
foreach ( array_keys( prihod_theme_schemes() ) as $scheme ) {
    update_option( 'prihod_theme_options', array( 'scheme' => $scheme, 'width' => 1280, 'font' => 'serif', 'font_size' => 18 ) );
    WP_Theme_JSON_Resolver::clean_cached_data();
    $settings = WP_Theme_JSON_Resolver::get_theme_data()->get_settings();
    prihod_assert( $scheme === $settings['custom']['prihod']['scheme'] && '1280px' === $settings['layout']['wideSize'], 'Saved scheme and width reach native theme.json: ' . $scheme );
}
update_option( 'prihod_theme_options', $previous_options ); WP_Theme_JSON_Resolver::clean_cached_data();
ob_start(); prihod_theme_settings_page(); $panel = ob_get_clean();
prihod_assert( 5 === substr_count( $panel, 'name="prihod_theme_options[scheme]"' ) && str_contains( $panel, 'prihod-option-demo' ) && str_contains( $panel, 'name="_wpnonce"' ), 'Theme panel provides five choices, grouped controls and protected demo action.' );
$profile_before = Prihod\Profile::get(); $modules_before = get_option( 'prihod_modules' ); $front_before = get_option( 'page_on_front' );
$demo = prihod_theme_import_demo( array( 'first-visit', 'photos', 'not-a-page' ) );
prihod_assert( 2 === count( $demo['created'] ) && 'draft' === get_post_status( $demo['created']['photos'] ), 'Demo import creates selected editable draft pages and ignores unknown keys.' );
prihod_assert( 'wide' === get_post_meta( $demo['created']['photos'], '_wp_page_template', true ) && str_contains( get_post( $demo['created']['first-visit'] )->post_content, '<!-- wp:paragraph' ), 'Demo pages carry real blocks and matching page templates.' );
prihod_assert( $profile_before === Prihod\Profile::get() && $modules_before === get_option( 'prihod_modules' ) && $front_before === get_option( 'page_on_front' ), 'Draft demo import preserves profile, module choices and homepage.' );
$again_demo = prihod_theme_import_demo( array( 'first-visit', 'photos' ) );
prihod_assert( ! $again_demo['created'] && $again_demo['resources']['photos']['id'] === $demo['created']['photos'], 'Repeating demo import reuses the same pages.' );
wp_update_post( array( 'ID' => $demo['created']['photos'], 'post_content' => 'Owner gallery content' ) );
prihod_theme_import_demo( array( 'first-visit', 'photos' ), true );
prihod_assert( 'Owner gallery content' === get_post( $demo['created']['photos'] )->post_content && 'draft' === get_post_status( $demo['created']['photos'] ) && 'publish' === get_post_status( $demo['created']['first-visit'] ), 'Explicit publication skips manually edited drafts while publishing untouched demo.' );
try { prihod_theme_import_demo( array( 'photos' ), false, true ); prihod_assert( false, 'Homepage selection must require publication.' ); } catch ( RuntimeException $error ) { prihod_assert( $front_before === get_option( 'page_on_front' ), 'Invalid homepage request leaves reading options unchanged.' ); }
$editor_test = wp_insert_user( array( 'user_login' => 'theme-demo-editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
wp_set_current_user( $editor_test );
try { prihod_theme_import_demo( array( 'about' ) ); prihod_assert( false, 'Editor must not import site structure.' ); } catch ( RuntimeException $error ) { prihod_assert( true, 'Demo import checks administrative permissions before changes.' ); }
wp_set_current_user( 1 );
wp_trash_post( $demo['created']['photos'] );
$after_trash = prihod_theme_import_demo( array( 'photos', 'first-visit' ) );
prihod_assert( ! $after_trash['created'] && 'trash' === get_post_status( $demo['created']['photos'] ), 'Import does not recreate or restore a deliberately trashed demo page.' );
