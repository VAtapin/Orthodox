<?php
/** Apply only the owner's reference appearance inside the authorized WordPress. */
defined( 'ABSPATH' ) || exit;
if ( 'prihod.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) || ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'Restricted to the authorized WordPress administrator.' ); }
$options = prihod_theme_options();
if ( empty( $options['map_embed'] ) ) {
    // Coordinates published in the monastery's own contact map.
    $options['map_embed'] = 'https://www.openstreetmap.org/export/embed.html?bbox=13.6518627%2C53.0821374%2C13.6918627%2C53.1021374&layer=mapnik&marker=53.0921374%2C13.6718627';
    update_option( 'prihod_theme_options', prihod_theme_sanitize( $options ), false );
}
$resources = get_option( 'prihod_resources', array() ); $about_id = absint( $resources['about']['id'] ?? 0 ); $about = get_post( $about_id );
if ( $about && get_post_meta( $about_id, '_prihod_theme_demo', true ) && hash_equals( (string) get_post_meta( $about_id, '_prihod_generated_hash', true ), hash( 'sha256', $about->post_title . '|' . $about->post_content ) ) && in_array( get_page_template_slug( $about_id ), array( '', 'default' ), true ) ) { update_post_meta( $about_id, '_wp_page_template', 'about' ); }
echo "REFERENCE_APPEARANCE_OK: existing owner settings and page content retained.\n";
