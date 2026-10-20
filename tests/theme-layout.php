<?php
/** Regression fixture for theme layout states. Disposable WordPress only. */
require_once '/wordpress/wp-load.php';
$layout_checks = 0;
$layout_check = static function ( bool $ok, string $message ) use ( &$layout_checks ): void {
    ++$layout_checks;
    if ( ! $ok ) { throw new RuntimeException( $message ); }
};
$layout_saved = get_option( 'georg_kloster_prihod_options' );
$layout_page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Layout fixture page', 'post_content' => 'Owner layout content' ) );
$layout_file = wp_upload_bits( 'layout-fixture.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP1cAAAAASUVORK5CYII=' ) );
$layout_image = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ), $layout_file['file'] );
set_post_thumbnail( $layout_page, $layout_image );
$layout_keys = array( 'schedule', 'about', 'news', 'requests', 'donations', 'contacts' );
foreach ( array_keys( prihod_theme_schemes() ) as $layout_scheme ) {
    foreach ( range( 1, 6 ) as $layout_count ) {
        $layout_home = array( 'pages' => array_fill_keys( $layout_keys, $layout_page ), 'hide_schedule_card' => false );
        foreach ( $layout_keys as $layout_index => $layout_key ) { $layout_home['cards'][$layout_key]['enabled'] = $layout_index < $layout_count; }
        update_option( 'georg_kloster_prihod_options', array( 'scheme' => $layout_scheme, 'home' => $layout_home ) );
        WP_Theme_JSON_Resolver::clean_cached_data();
        $layout_html = prihod_theme_home_features( $layout_scheme, '', false, true );
        $layout_columns = min( $layout_count, 'classic' === $layout_scheme ? 4 : 5 );
        $layout_check( str_contains( $layout_html, '--prihod-feature-columns:' . $layout_columns ), 'Grid reserves empty columns: ' . $layout_scheme . '/' . $layout_count );
        $layout_check( $layout_count === preg_match_all( '/<div class="prihod-feature(?: |")/', $layout_html ), 'Visible card count changed: ' . $layout_scheme . '/' . $layout_count );
    }
    $layout_home['hide_schedule_card'] = true;
    update_option( 'georg_kloster_prihod_options', array( 'scheme' => $layout_scheme, 'home' => $layout_home ) );
    $layout_html = prihod_theme_home_features( $layout_scheme, '', false, true );
    $layout_check( 5 === preg_match_all( '/<div class="prihod-feature(?: |")/', $layout_html ), 'Schedule card visibility was ignored: ' . $layout_scheme );
    $layout_check( ! str_contains( $layout_html, 'has-schedule-panel' ), 'Service presence still changes the card grid independently of card count.' );
}
$layout_options = array( 'scheme' => 'tradition', 'hero_motto' => 'Owner motto', 'quote_text' => 'Owner quotation', 'quote_source' => 'Owner source', 'home' => array( 'sections' => array( 'hero' => array( 'title' => '' ) ) ) );
update_option( 'georg_kloster_prihod_options', $layout_options ); WP_Theme_JSON_Resolver::clean_cached_data();
$layout_hero = prihod_theme_home_hero( 'tradition', prihod_theme_profile(), georg_kloster_prihod_options(), false, '', false );
$layout_check( str_contains( $layout_hero, 'Owner quotation' ) && str_contains( $layout_hero, 'Owner source' ), 'Traditional hero loses the saved quotation.' );
$layout_check( str_contains( $layout_hero, 'prihod-tradition-motto' ) && str_contains( $layout_hero, '<svg' ) && str_contains( $layout_hero, 'has-motto' ), 'Traditional motto decoration is missing.' );
$layout_options['quote_text'] = ''; $layout_options['hero_motto'] = '';
update_option( 'georg_kloster_prihod_options', $layout_options );
$layout_hero = prihod_theme_home_hero( 'tradition', prihod_theme_profile(), georg_kloster_prihod_options(), false, '', false );
$layout_check( ! str_contains( $layout_hero, 'has-motto' ) && ! str_contains( $layout_hero, 'prihod-verse' ), 'Empty fields create layout placeholders.' );
foreach ( array( 'hospitality', 'blue', 'editorial' ) as $layout_scheme ) {
    $layout_hero = prihod_theme_home_hero( $layout_scheme, prihod_theme_profile(), georg_kloster_prihod_options(), false, '', false );
    $layout_check( ! str_contains( $layout_hero, 'has-service-panel' ), 'Empty service column is enabled: ' . $layout_scheme );
}
foreach ( array( 'tradition' => 'prihod-feature-symbol', 'editorial' => 'prihod-feature-support' ) as $layout_scheme => $layout_symbol ) {
    update_option( 'georg_kloster_prihod_options', array( 'scheme' => $layout_scheme, 'home' => array( 'pages' => array_fill_keys( $layout_keys, $layout_page ), 'cards_show_images' => true ) ) );
    $layout_html = prihod_theme_home_features( $layout_scheme, '', false );
    $layout_check( str_contains( $layout_html, $layout_symbol ), 'Style symbols were replaced by featured images: ' . $layout_scheme );
}
$layout_check( 'Owner layout content' === get_post( $layout_page )->post_content, 'Layout rendering rewrote page content.' );
$layout_map_options = prihod_theme_sanitize( array( 'map_latitude' => '91', 'map_longitude' => '13.4', 'map_provider' => 'unsafe', 'google_maps_key' => '<b>key</b>' ) );
$layout_check( '' === $layout_map_options['map_latitude'] && '13.4' === $layout_map_options['map_longitude'] && 'openstreetmap' === $layout_map_options['map_provider'] && 'key' === $layout_map_options['google_maps_key'], 'Map settings validate coordinates and provider and sanitize the optional key.' );
foreach ( array_keys( prihod_theme_schemes() ) as $layout_scheme ) {
    update_option( 'georg_kloster_prihod_options', array( 'scheme' => $layout_scheme, 'profile' => array( 'address' => 'Götschendorf 33, 17268 Milmersdorf' ), 'map_latitude' => '53.091587', 'map_longitude' => '13.671996' ) );
    WP_Theme_JSON_Resolver::clean_cached_data();
    $layout_contacts = prihod_theme_contact_panel( 'Contacts' );
    $layout_check( str_contains( $layout_contacts, 'prihod-contact-map' ) && str_contains( $layout_contacts, 'openstreetmap.org/export/embed.html' ) && str_contains( $layout_contacts, 'prihod-contact-list' ), 'Homepage contacts include the configured map in every visual style: ' . $layout_scheme );
    preg_match( '/bbox=([^&"]+)/', $layout_contacts, $layout_bbox_match );
    $layout_bbox = isset( $layout_bbox_match[1] ) ? array_map( 'floatval', explode( ',', rawurldecode( $layout_bbox_match[1] ) ) ) : array();
    $layout_check( 4 === count( $layout_bbox ) && ( $layout_bbox[2] - $layout_bbox[0] ) > 5 * ( $layout_bbox[3] - $layout_bbox[1] ), 'OpenStreetMap viewport fills the wide contact map in every visual style: ' . $layout_scheme );
}
$layout_contact_options = array( 'scheme' => 'classic', 'profile' => array( 'address' => 'Test address', 'phone' => '+491234567', 'email' => 'test@example.org' ), 'map_latitude' => '53.09', 'map_longitude' => '13.67', 'home' => array( 'contact_items' => array( 'address' => false, 'phone' => true, 'email' => false, 'map' => false ) ) );
update_option( 'georg_kloster_prihod_options', $layout_contact_options ); WP_Theme_JSON_Resolver::clean_cached_data();
$layout_contacts = prihod_theme_contact_panel( 'Contacts' );
$layout_check( str_contains( $layout_contacts, '+491234567' ) && ! str_contains( $layout_contacts, 'Test address' ) && ! str_contains( $layout_contacts, 'test@example.org' ) && ! str_contains( $layout_contacts, 'prihod-contact-map' ), 'Contact visibility settings independently hide address, email and map.' );
$layout_contact_options['home']['contact_items'] = array( 'address' => false, 'phone' => false, 'email' => false, 'map' => false );
update_option( 'georg_kloster_prihod_options', $layout_contact_options ); WP_Theme_JSON_Resolver::clean_cached_data();
$layout_check( '' === prihod_theme_contact_panel( 'Contacts' ), 'The contacts section is omitted when every contact item is disabled.' );
$layout_contact_options = array( 'scheme' => 'editorial', 'hero_image' => $layout_image, 'profile' => array( 'description' => 'Parish description' ), 'home' => array( 'contact_items' => array( 'address' => false, 'phone' => false, 'email' => false, 'map' => false, 'description' => true, 'image' => false ) ) );
update_option( 'georg_kloster_prihod_options', $layout_contact_options ); WP_Theme_JSON_Resolver::clean_cached_data();
$layout_contacts = prihod_theme_contact_panel( 'Contacts' );
$layout_check( str_contains( $layout_contacts, 'Parish description' ) && ! str_contains( $layout_contacts, '<img' ), 'The journal style contact area hides the hero image when its control is off.' );
$layout_contact_options['home']['contact_items']['image'] = true;
update_option( 'georg_kloster_prihod_options', $layout_contact_options ); WP_Theme_JSON_Resolver::clean_cached_data();
$layout_check( str_contains( prihod_theme_contact_panel( 'Contacts' ), '<img' ), 'The journal style contact area shows its optional hero image when enabled.' );
$layout_check( str_contains( file_get_contents( get_template_directory() . '/assets/theme.css' ), '.prihod-home-contacts .prihod-contact-map>.prihod-button{position:absolute;top:auto;left:auto;right:12px;bottom:12px;z-index:2;background:#fff;color:' ), 'The map directions link has a visible high-contrast overlay position.' );
if ( false === $layout_saved ) { delete_option( 'georg_kloster_prihod_options' ); } else { update_option( 'georg_kloster_prihod_options', $layout_saved ); }
WP_Theme_JSON_Resolver::clean_cached_data();
file_put_contents( __DIR__ . '/.theme-layout-result.json', wp_json_encode( array( 'checks' => $layout_checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'THEME_LAYOUT_OK' ) ) );
echo 'THEME_LAYOUT_OK ' . $layout_checks;
