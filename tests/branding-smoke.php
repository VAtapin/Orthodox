<?php
// Disposable database only. Real square/wide PNGs exercise image validation.
$branding_options = get_option( 'prihod_theme_options' );
$branding_icon = get_option( 'site_icon' );
$branding_ids = array();
foreach ( array( 'square', 'wide' ) as $shape ) {
    $upload = wp_upload_bits( 'branding-' . $shape . '.png', null, file_get_contents( __DIR__ . '/fixtures/brand-' . $shape . '.png' ) );
    $id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $upload['file'] );
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
    $branding_ids[$shape] = $id;
}
$square = $branding_ids['square']; $wide = $branding_ids['wide'];
$check( $square === prihod_theme_icon_sanitize( $square ), 'Real 512-square icon accepted.' );
update_option( 'site_icon', $square );
$check( $square === prihod_theme_icon_sanitize( $wide ), 'Wide favicon rejected without replacing existing icon.' );
$check( $square === prihod_theme_icon_sanitize( 'invalid' ), 'Malformed favicon value preserves existing icon.' );
$check( 0 === prihod_theme_icon_sanitize( 0 ), 'Explicit favicon removal accepted.' );
ob_start(); wp_site_icon(); $tags = ob_get_clean();
$check( str_contains( $tags, 'rel="icon"' ) && str_contains( $tags, 'apple-touch-icon' ), 'Canonical site_icon supplies native browser/mobile metadata.' );
$out = prihod_theme_sanitize( array( 'logo' => $wide, 'logo_width' => 9999, 'logo_hide_title' => 1, 'social_image' => $square, 'social_meta' => 1 ) );
$check( $wide === $out['logo'] && 320 === $out['logo_width'] && $out['logo_hide_title'], 'Wide logo and bounded sizing survive sanitize.' );
update_option( 'prihod_theme_options', $out );
$brand = prihod_theme_brand();
$check( str_contains( $brand, 'branding-wide.png' ) && ! str_contains( $brand, 'prihod-brand-title' ), 'Wide logo uses uncropped original and can replace adjacent title.' );
$check( $out['logo_show'] && str_contains( $brand, 'prihod-brand-wide' ), 'Existing logos stay enabled and wide proportions are identified.' );
$title_only = prihod_theme_sanitize( array( 'logo_show' => 0, 'title_visible' => 1 ) );
update_option( 'prihod_theme_options', $title_only );
$check( ! str_contains( prihod_theme_brand(), 'custom-logo' ) && str_contains( prihod_theme_brand(), 'prihod-brand-title' ) && $wide === $title_only['logo'], 'Logo can be hidden independently without deleting its attachment choice.' );
$both_off = prihod_theme_sanitize( array( 'logo_show' => 0, 'title_visible' => 0 ) );
update_option( 'prihod_theme_options', $both_off );
$check( str_contains( prihod_theme_brand(), 'prihod-brand-home' ) && ! str_contains( prihod_theme_brand(), 'custom-logo' ) && ! str_contains( prihod_theme_brand(), 'prihod-brand-title' ), 'Both controls off retains an accessible home link.' );
$both_on = prihod_theme_sanitize( array( 'logo_show' => 1, 'title_visible' => 1 ) );
update_option( 'prihod_theme_options', $both_on );
$check( str_contains( prihod_theme_brand(), 'custom-logo' ) && str_contains( prihod_theme_brand(), 'prihod-brand-title' ), 'Logo and site title can both be enabled.' );
update_option( 'prihod_theme_options', $out );
$custom_logo_before = get_theme_mod( 'custom_logo' );
set_theme_mod( 'custom_logo', $square );
$removed = prihod_theme_sanitize( array( 'logo' => 0, 'logo_removed' => 1 ) );
update_option( 'prihod_theme_options', $removed );
$check( ! str_contains( prihod_theme_brand(), 'custom-logo' ) && str_contains( prihod_theme_brand(), 'prihod-brand-title' ), 'Removing a logo also suppresses native fallback while restoring the site name.' );
update_option( 'prihod_theme_options', $out );
if ( $custom_logo_before ) { set_theme_mod( 'custom_logo', $custom_logo_before ); } else { remove_theme_mod( 'custom_logo' ); }
$check( $wide === prihod_theme_sanitize( array() )['logo'] && $square === prihod_theme_sanitize( array() )['social_image'], 'Partial settings retain branding images.' );
ob_start(); prihod_theme_settings_page(); $branding_panel = ob_get_clean();
preg_match( '/<section id="prihod-option-photos".*?<\/section>/s', $branding_panel, $photos_section );
$check( str_contains( $branding_panel, 'prihod-option-branding' ) && str_contains( $branding_panel, 'name="site_icon"' ) && ! str_contains( $photos_section[0], '[logo]' ), 'Branding is a separate Prihod section outside Photos.' );
$home_id = $first['pages']['home'];
query_posts( array( 'page_id' => $home_id ) );
$_GET['prihod_preview'] = 'blue';
ob_start(); prihod_theme_social_tags(); $social = ob_get_clean();
$check( str_contains( $social, 'og:image' ) && str_contains( $social, 'twitter:card' ) && ! str_contains( $social, 'prihod_preview' ), 'Public social metadata includes image/card without private preview parameters.' );
set_post_thumbnail( $home_id, $wide );
ob_start(); prihod_theme_social_tags(); $social = ob_get_clean();
$check( str_contains( $social, 'branding-wide.png' ), 'Page featured image takes priority over fallback social image.' );
delete_post_thumbnail( $home_id );
$out['social_meta'] = false; update_option( 'prihod_theme_options', $out );
ob_start(); prihod_theme_social_tags(); $social = ob_get_clean();
$check( '' === $social, 'Social metadata can be disabled for another provider.' );
unset( $_GET['prihod_preview'] ); wp_reset_query();
update_option( 'prihod_theme_options', $branding_options ); update_option( 'site_icon', $branding_icon );
foreach ( $branding_ids as $id ) { wp_delete_attachment( $id, true ); }
