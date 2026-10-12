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
foreach ( array_keys( prihod_theme_schemes() ) as $scheme ) {
    $_GET['prihod_preview'] = $scheme;
    WP_Theme_JSON_Resolver::clean_cached_data();
    prihod_assert( $scheme === WP_Theme_JSON_Resolver::get_merged_data()->get_settings()['custom']['prihod']['scheme'] && $previous_options === get_option( 'prihod_theme_options' ), 'Owner can preview scheme without changing saved options: ' . $scheme );
}
wp_set_current_user( 0 );
prihod_assert( '' === prihod_theme_preview_scheme(), 'Public visitors cannot alter theme previews.' );
wp_set_current_user( 1 ); unset( $_GET['prihod_preview'] ); WP_Theme_JSON_Resolver::clean_cached_data();
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

prihod_assert( str_contains( $panel, '0.3.15' ) && ! str_contains( $panel, '<small>0.3.0' ), 'Theme panel reports the installed version.' );
$map = prihod_theme_sanitize( array( 'map_embed' => 'https://www.openstreetmap.org/export/embed.html?marker=53,13' ) );
prihod_assert( str_contains( $map['map_embed'], '/export/embed.html' ), 'Verified OpenStreetMap embeds are accepted.' );
foreach ( array( 'javascript:alert(1)', 'https://example.com/export/embed.html', 'https://www.openstreetmap.org.evil.test/export/embed.html', 'https://www.openstreetmap.org/user/test' ) as $url ) { prihod_assert( '' === prihod_theme_sanitize( array( 'map_embed' => $url ) )['map_embed'], 'Other iframe origins and paths are rejected.' ); }
$_GET['prihod_preview'] = 'blue';
prihod_assert( str_contains( prihod_theme_preview_url( home_url( '/?page_id=8' ) ), 'prihod_preview=blue' ) && 'https://example.com/' === prihod_theme_preview_url( 'https://example.com/' ), 'Owner scheme previews survive internal navigation without rewriting external URLs.' );
unset( $_GET['prihod_preview'] );
$resources_backup = get_option( 'prihod_resources' );
$id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Wizard published fixture', 'post_status' => 'publish', 'post_content' => 'Ready' ) );
update_option( 'prihod_resources', array( 'about' => array( 'id' => $id, 'owned' => true ) ) );
ob_start(); $has_drafts = Prihod\Wizard_Result::render(); $result = ob_get_clean();
prihod_assert( ! $has_drafts && str_contains( $result, 'is-publish' ) && ! str_contains( $result, 'name="publish_keys[]"' ), 'Published wizard resources are labelled correctly and are not offered for republication.' );
wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
update_post_meta( $id, '_prihod_generated_hash', hash( 'sha256', 'Wizard published fixture|Ready' ) );
prihod_assert( Prihod\Wizard_Result::resources()['about']['publishable'], 'Untouched owned drafts remain explicitly publishable.' );
$published_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Published companion', 'post_status' => 'publish' ) );
update_option( 'prihod_resources', array( 'about' => array( 'id' => $id, 'owned' => true ), 'news' => array( 'id' => $published_id, 'owned' => true ) ) );
ob_start(); Prihod\Wizard_Result::render(); $mixed = ob_get_clean();
prihod_assert( strpos( $mixed, 'name="publish_keys[]"' ) < strpos( $mixed, '<details' ) && ! str_contains( $mixed, 'prihod-wizard-resources" open' ) && 1 === substr_count( $mixed, 'name="publish_keys[]"' ), 'Mixed wizard results place only publishable drafts before the collapsed published resources.' );
wp_update_post( array( 'ID' => $id, 'post_content' => 'Owner edited' ) );
prihod_assert( ! Prihod\Wizard_Result::resources()['about']['publishable'], 'Manually edited drafts retain the existing owner-edit protection.' );
update_option( 'prihod_resources', $resources_backup );

$config = array( 'examples' => array( '[orthocal_today]', 'a [bracket] b', 'quote " and slash \\' ), 'nested' => array( array( 'number' => 1 ) ) );
$markup = '<section data-orthocal="' . esc_attr( wp_json_encode( $config ) ) . '"></section>';
$safe = prihod_theme_calendar_markup( $markup, array( 'blockName' => 'orthocal/calendar' ) );
$processed = new WP_HTML_Tag_Processor( do_shortcode( $safe ) ); $processed->next_tag();
prihod_assert( $config === json_decode( $processed->get_attribute( 'data-orthocal' ), true ), 'Calendar JSON string examples survive native shortcode processing and retain structural arrays.' );
prihod_assert( $safe === prihod_theme_calendar_markup( $safe, array( 'blockName' => 'orthocal/calendar' ) ), 'Calendar markup protection is idempotent.' );
