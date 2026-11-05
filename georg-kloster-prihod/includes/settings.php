<?php
defined( 'ABSPATH' ) || exit;

function georg_kloster_prihod_options(): array {
    $saved = get_option( 'georg_kloster_prihod_options', array() );
    $defaults = array( 'hero_image' => 0, 'logo' => 0, 'logo_show' => true, 'logo_width' => 240, 'logo_removed' => false, 'logo_hide_title' => false, 'scheme' => '', 'width' => 1200, 'font' => 'sans', 'font_size' => 16, 'sticky_header' => false, 'header_contacts' => true, 'menu_style' => 'visual', 'menu_columns' => 3, 'footer_text' => '', 'header_motto' => '', 'hero_motto' => '', 'hero_quote_text' => '', 'hero_quote_source' => '', 'quote_text' => '', 'quote_source' => '', 'map_provider' => 'openstreetmap', 'map_latitude' => '', 'map_longitude' => '', 'google_maps_key' => '' );
    $defaults['home'] = array();
    $defaults['profile'] = array();
    return array_merge( $defaults, is_array( $saved ) ? array_intersect_key( $saved, $defaults ) : array() );
}
function prihod_theme_sanitize( $input ): array {
    $out = georg_kloster_prihod_options();
    if ( ! is_array( $input ) ) { return $out; }
    if ( array_key_exists( 'logo_show', $input ) ) { $out['logo_show'] = ! empty( $input['logo_show'] ); }
    if ( array_key_exists( 'title_visible', $input ) ) { $out['logo_hide_title'] = empty( $input['title_visible'] ); }
    foreach ( array( 'logo_hide_title', 'logo_removed' ) as $key ) { if ( array_key_exists( $key, $input ) ) { $out[$key] = ! empty( $input[$key] ); } }
    if ( array_key_exists( 'logo_width', $input ) ) { $out['logo_width'] = max( 64, min( 320, is_scalar( $input['logo_width'] ) ? absint( $input['logo_width'] ) : 240 ) ); }
    $out['sticky_header'] = ! empty( $input['sticky_header'] );
    $out['header_contacts'] = ! empty( $input['header_contacts'] );
    $out['menu_style'] = in_array( $input['menu_style'] ?? '', array( 'visual', 'classic' ), true ) ? $input['menu_style'] : 'visual';
    $out['menu_columns'] = max( 2, min( 4, is_scalar( $input['menu_columns'] ?? null ) ? absint( $input['menu_columns'] ) : 3 ) );
    if ( is_string( $input['scheme'] ?? null ) && isset( prihod_theme_schemes()[ $input['scheme'] ] ) ) { $out['scheme'] = $input['scheme']; }
    $out['width'] = max( 960, min( 1440, is_scalar( $input['width'] ?? null ) ? absint( $input['width'] ) : 1200 ) );
    $out['font_size'] = max( 14, min( 20, is_scalar( $input['font_size'] ?? null ) ? absint( $input['font_size'] ) : 16 ) );
    $out['font'] = in_array( $input['font'] ?? null, array( 'sans', 'serif' ), true ) ? $input['font'] : 'sans';
    $out['footer_text'] = is_string( $input['footer_text'] ?? null ) ? sanitize_textarea_field( $input['footer_text'] ) : '';
    $out['map_provider'] = in_array( $input['map_provider'] ?? null, array( 'openstreetmap', 'google' ), true ) ? $input['map_provider'] : 'openstreetmap';
    foreach ( array( 'map_latitude' => array( -90, 90 ), 'map_longitude' => array( -180, 180 ) ) as $key => $range ) {
        $value = is_scalar( $input[$key] ?? null ) ? (string) $input[$key] : '';
        $value = str_replace( ',', '.', trim( $value ) );
        $out[$key] = is_numeric( $value ) && (float) $value >= $range[0] && (float) $value <= $range[1] ? (string) (float) $value : '';
    }
    $out['google_maps_key'] = sanitize_text_field( is_scalar( $input['google_maps_key'] ?? null ) ? (string) $input['google_maps_key'] : '' );
    foreach ( array( 'hero_image', 'logo' ) as $key ) {
        if ( ! array_key_exists( $key, $input ) ) { continue; }
        $id = is_scalar( $input[ $key ] ?? null ) ? absint( $input[ $key ] ) : 0;
        $out[ $key ] = $id && wp_attachment_is_image( $id ) ? $id : 0;
    }
    foreach ( array( 'quote_text', 'quote_source', 'header_motto', 'hero_motto', 'hero_quote_text', 'hero_quote_source' ) as $key ) { if ( array_key_exists( $key, $input ) ) { $out[$key] = is_string( $input[$key] ) ? sanitize_textarea_field( $input[$key] ) : ''; } }
    if ( array_key_exists( 'home', $input ) && is_array( $input['home'] ) ) { $out['home'] = prihod_theme_home_sanitize( $input['home'], $out['scheme'] ?: prihod_theme_scheme() ); }
    if ( array_key_exists( 'profile', $input ) ) {
        $out['profile'] = prihod_theme_profile_sanitize( $input['profile'] );
        foreach ( $out['profile'] as $key => $value ) { if ( '' !== $value && 'type' !== $key ) { do_action( 'wpml_register_single_string', 'georg-kloster-prihod', 'profile-' . $key, $value ); } }
    }
    return $out;
}
add_action( 'admin_init', function () { register_setting( 'prihod_theme', 'georg_kloster_prihod_options', array( 'type' => 'array', 'sanitize_callback' => 'prihod_theme_sanitize' ) ); } );
add_filter( 'option_page_capability_prihod_theme', static function (): string { return 'edit_theme_options'; } );
add_action( 'admin_menu', function () {
    add_theme_page( __( 'Parish Appearance', 'georg-kloster-prihod' ), __( 'Parish Appearance', 'georg-kloster-prihod' ), 'edit_theme_options', 'georg-kloster-prihod', 'prihod_theme_settings_page' );
}, 20 );
add_action( 'admin_enqueue_scripts', function () {
    if ( 'georg-kloster-prihod' !== ( $_GET['page'] ?? '' ) ) { return; }
    wp_enqueue_media();
    wp_enqueue_style( 'georg-kloster-prihod-settings', get_template_directory_uri() . '/assets/settings.css', array(), wp_get_theme()->get( 'Version' ) );
    wp_enqueue_script( 'georg-kloster-prihod-media', get_template_directory_uri() . '/assets/media.js', array( 'jquery', 'media-views' ), '0.3.28', true );
    wp_enqueue_script( 'georg-kloster-prihod-settings', get_template_directory_uri() . '/assets/settings.js', array(), wp_get_theme()->get( 'Version' ), true );
} );
function prihod_theme_schemes(): array {
    return array( 'classic' => __( 'Classic gold', 'georg-kloster-prihod' ), 'hospitality' => __( 'Light hospitality', 'georg-kloster-prihod' ), 'editorial' => __( 'Editorial', 'georg-kloster-prihod' ), 'tradition' => __( 'Traditional', 'georg-kloster-prihod' ), 'blue' => __( 'Modern blue', 'georg-kloster-prihod' ) );
}
function prihod_theme_map_settings_panel( array $options ): void {
    echo '<section id="prihod-option-map" class="prihod-option-section"><h2>' . esc_html__( 'Map', 'georg-kloster-prihod' ) . '</h2><p>' . esc_html__( 'The map appears in the homepage contact area in every visual style. OpenStreetMap is used by default and does not need an API key. Add the exact coordinates of the parish or monastery.', 'georg-kloster-prihod' ) . '</p>';
    echo '<p><label>' . esc_html__( 'Map provider', 'georg-kloster-prihod' ) . '<br><select name="georg_kloster_prihod_options[map_provider]">';
    foreach ( array( 'openstreetmap' => __( 'OpenStreetMap', 'georg-kloster-prihod' ), 'google' => __( 'Google Maps', 'georg-kloster-prihod' ) ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $options['map_provider'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
    echo '</select></label></p>';
    foreach ( array( 'map_latitude' => array( __( 'Map latitude', 'georg-kloster-prihod' ), -90, 90 ), 'map_longitude' => array( __( 'Map longitude', 'georg-kloster-prihod' ), -180, 180 ) ) as $key => $field ) { echo '<p><label>' . esc_html( $field[0] ) . '<br><input class="regular-text" type="number" step="any" min="' . $field[1] . '" max="' . $field[2] . '" name="georg_kloster_prihod_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $options[$key] ) . '"></label></p>'; }
    echo '<p><label>' . esc_html__( 'Google Maps API key', 'georg-kloster-prihod' ) . '<br><input class="regular-text" type="password" autocomplete="new-password" name="georg_kloster_prihod_options[google_maps_key]" value="' . esc_attr( $options['google_maps_key'] ) . '"></label></p><p class="description">' . esc_html__( 'A Google key is only used when Google Maps is selected. The key is sent to visitors’ browsers and should be restricted to this site in Google Cloud.', 'georg-kloster-prihod' ) . '</p></section>';
}
function prihod_theme_settings_page(): void {
    if ( ! current_user_can( 'edit_theme_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-prihod' ) ); }
    $options = georg_kloster_prihod_options();
    $sections = array( 'schemes' => __( 'Styles', 'georg-kloster-prihod' ), 'layout' => __( 'Layout and fonts', 'georg-kloster-prihod' ), 'branding' => __( 'Logo and icon', 'georg-kloster-prihod' ), 'header' => __( 'Header and footer', 'georg-kloster-prihod' ) );
    $sections = array_slice( $sections, 0, 1, true ) + array( 'home' => __( 'Homepage', 'georg-kloster-prihod' ), 'profile' => __( 'Site details', 'georg-kloster-prihod' ), 'map' => __( 'Map', 'georg-kloster-prihod' ) ) + array_slice( $sections, 1, null, true );
    $sections['plugins'] = __( 'Optional plugins', 'georg-kloster-prihod' );
    echo '<div class="wrap georg-kloster-prihod-settings"><h1>' . esc_html__( 'Parish Appearance', 'georg-kloster-prihod' ) . ' <small>' . esc_html( wp_get_theme()->get( 'Version' ) ) . '</small></h1>';
    settings_errors();
    echo '<div class="prihod-options-shell"><nav class="prihod-options-nav" aria-label="' . esc_attr__( 'Settings sections', 'georg-kloster-prihod' ) . '">';
    foreach ( $sections as $id => $title ) { echo '<a href="#prihod-option-' . esc_attr( $id ) . '" data-section="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</a>'; }
    echo '</nav><div class="prihod-options-content"><form method="post" action="options.php"><div class="prihod-options-toolbar"><strong>' . esc_html__( 'Theme settings', 'georg-kloster-prihod' ) . '</strong>';
    submit_button( __( 'Save changes', 'georg-kloster-prihod' ), 'primary', 'submit', false );
    echo '<a class="button" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View site', 'georg-kloster-prihod' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'customize.php' ) ) . '">' . esc_html__( 'Open live preview customizer', 'georg-kloster-prihod' ) . '</a></div>';
    settings_fields( 'prihod_theme' );
    echo '<section id="prihod-option-schemes" class="prihod-option-section"><h2>' . esc_html( $sections['schemes'] ) . '</h2><p>' . esc_html__( 'The preview opens in a new tab without changing the site. To apply a style, select its card and save your changes.', 'georg-kloster-prihod' ) . '</p><p>' . esc_html__( 'The cards show example layouts. The live preview uses your pages, images and settings.', 'georg-kloster-prihod' ) . '</p><div class="prihod-scheme-cards">';
    $current = $options['scheme'] ?: ( WP_Theme_JSON_Resolver::get_merged_data()->get_settings()['custom']['prihod']['scheme'] ?? 'classic' );
    foreach ( prihod_theme_schemes() as $id => $title ) {
        echo '<article class="prihod-scheme-card"><label><img class="prihod-scheme-preview" src="' . esc_url( get_template_directory_uri() . '/assets/schemes/' . $id . '.jpg' ) . '" alt="" loading="lazy"><span class="prihod-scheme-choice"><input type="radio" aria-label="' . esc_attr( $title ) . '" name="georg_kloster_prihod_options[scheme]" value="' . esc_attr( $id ) . '"' . checked( $current, $id, false ) . '><strong>' . esc_html( $title ) . '</strong></span></label><div class="prihod-scheme-actions">';
        if ( $current === $id ) { echo '<small>' . esc_html__( 'Current style', 'georg-kloster-prihod' ) . '</small>'; }
        echo '<a class="button" href="' . esc_url( add_query_arg( 'prihod_preview', $id, home_url( '/' ) ) ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( sprintf( __( 'Preview: %s (new tab)', 'georg-kloster-prihod' ), $title ) ) . '">' . esc_html__( 'Preview', 'georg-kloster-prihod' ) . '</a></div></article>';
    }
    echo '</div></section>';
    prihod_theme_home_settings_panel( $options );
    prihod_theme_profile_settings_panel( $options );
    prihod_theme_map_settings_panel( $options );
    echo '<section id="prihod-option-layout" class="prihod-option-section"><h2>' . esc_html( $sections['layout'] ) . '</h2>';
    foreach ( array( 'width' => array( __( 'Site width in px', 'georg-kloster-prihod' ), 960, 1440 ), 'font_size' => array( __( 'Base text size in px', 'georg-kloster-prihod' ), 14, 20 ) ) as $key => $field ) { echo '<p><label>' . esc_html( $field[0] ) . '<br><input type="number" min="' . $field[1] . '" max="' . $field[2] . '" name="georg_kloster_prihod_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $options[$key] ) . '"></label></p>'; }
    echo '<p><label>' . esc_html__( 'Base font', 'georg-kloster-prihod' ) . '<br><select name="georg_kloster_prihod_options[font]">';
    foreach ( array( 'sans' => __( 'Sans serif', 'georg-kloster-prihod' ), 'serif' => __( 'Serif', 'georg-kloster-prihod' ) ) as $id => $title ) { echo '<option value="' . $id . '"' . selected( $options['font'], $id, false ) . '>' . esc_html( $title ) . '</option>'; }
    echo '</select></label></p></section>'; prihod_theme_branding_panel( $options ); echo '<section id="prihod-option-header" class="prihod-option-section"><h2>' . esc_html( $sections['header'] ) . '</h2>';
    foreach ( array( 'sticky_header' => __( 'Keep the header visible while scrolling', 'georg-kloster-prihod' ), 'header_contacts' => __( 'Show contact details in the header', 'georg-kloster-prihod' ) ) as $key => $title ) { echo '<p><label><input type="checkbox" name="georg_kloster_prihod_options[' . esc_attr( $key ) . ']" value="1"' . checked( $options[$key], true, false ) . '> ' . esc_html( $title ) . '</label></p>'; }
    echo '<h3>' . esc_html__( 'Primary menu', 'georg-kloster-prihod' ) . '</h3><p><label>' . esc_html__( 'Menu style', 'georg-kloster-prihod' ) . '<br><select name="georg_kloster_prihod_options[menu_style]">';
    foreach ( array( 'visual' => __( 'Menu with cards and images', 'georg-kloster-prihod' ), 'classic' => __( 'Link row', 'georg-kloster-prihod' ) ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $options['menu_style'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
    echo '</select></label></p><p><label>' . esc_html__( 'Cards per row', 'georg-kloster-prihod' ) . '<br><select name="georg_kloster_prihod_options[menu_columns]">';
    foreach ( array( 2, 3, 4 ) as $columns ) { echo '<option value="' . $columns . '"' . selected( $options['menu_columns'], $columns, false ) . '>' . $columns . '</option>'; }
    echo '</select></label></p><p class="description">' . esc_html__( 'Edit menu items and their order under Appearance → Menus. You can also choose an image for each item there. Without a chosen image, the linked page or post featured image is used.', 'georg-kloster-prihod' ) . '</p>';
    echo '<p><label>' . esc_html__( 'Quotation text', 'georg-kloster-prihod' ) . '<br><textarea class="large-text" rows="3" name="georg_kloster_prihod_options[quote_text]">' . esc_textarea( $options['quote_text'] ) . '</textarea></label></p><p><label>' . esc_html__( 'Quotation source', 'georg-kloster-prihod' ) . '<br><input class="regular-text" name="georg_kloster_prihod_options[quote_source]" value="' . esc_attr( $options['quote_source'] ) . '"></label></p>';
    foreach ( array( 'header_motto' => __( 'Classic header motto', 'georg-kloster-prihod' ), 'hero_motto' => __( 'Classic hero motto', 'georg-kloster-prihod' ), 'hero_quote_text' => __( 'Quotation below the buttons in the blue style', 'georg-kloster-prihod' ), 'hero_quote_source' => __( 'Blue style quotation source', 'georg-kloster-prihod' ) ) as $key => $label ) { echo '<p><label>' . esc_html( $label ) . '<br><textarea class="large-text" rows="2" name="georg_kloster_prihod_options[' . esc_attr( $key ) . ']">' . esc_textarea( $options[$key] ) . '</textarea></label></p>'; }
    echo '<p><label>' . esc_html__( 'Additional footer text', 'georg-kloster-prihod' ) . '<br><textarea class="large-text" rows="3" name="georg_kloster_prihod_options[footer_text]">' . esc_textarea( $options['footer_text'] ) . '</textarea></label></p></section></form>';
    prihod_theme_plugins_panel();
    echo '</div></div></div>';
}
