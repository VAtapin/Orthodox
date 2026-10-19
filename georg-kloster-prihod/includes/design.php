<?php
defined( 'ABSPATH' ) || exit;

/** Read a scheme and translate its editor labels using WordPress JSON contexts. */
function prihod_theme_scheme_data( string $name ): array {
    if ( ! isset( prihod_theme_schemes()[$name] ) ) { return array(); }
    $path = get_template_directory() . ( 'classic' === $name ? '/theme.json' : '/styles/' . $name . '.json' );
    $scheme = json_decode( file_get_contents( $path ), true );
    if ( ! is_array( $scheme ) ) { return array(); }
    foreach ( array( array( 'color', 'palette', 'Color name' ), array( 'typography', 'fontFamilies', 'Font family name' ), array( 'typography', 'fontSizes', 'Font size name' ) ) as $field ) {
        foreach ( $scheme['settings'][$field[0]][$field[1]] ?? array() as $index => $item ) {
            $scheme['settings'][$field[0]][$field[1]][$index]['name'] = translate_with_gettext_context( $item['name'], $field[2], 'georg-kloster-prihod' );
        }
    }
    foreach ( array( 'templateParts' => 'Template part name', 'customTemplates' => 'Custom template name' ) as $key => $context ) {
        foreach ( $scheme[$key] ?? array() as $index => $item ) { $scheme[$key][$index]['title'] = translate_with_gettext_context( $item['title'], $context, 'georg-kloster-prihod' ); }
    }
    if ( isset( $scheme['title'] ) ) { $scheme['title'] = translate_with_gettext_context( $scheme['title'], 'Style variation name', 'georg-kloster-prihod' ); }
    return $scheme;
}

function prihod_theme_preview_scheme(): string {
    $value = $_GET['prihod_preview'] ?? '';
    return is_string( $value ) && isset( prihod_theme_schemes()[$value] ) && current_user_can( 'edit_theme_options' ) ? $value : '';
}
function prihod_theme_preview_notice(): void {
    $scheme = prihod_theme_preview_scheme();
    if ( ! $scheme ) { return; }
    echo '<aside class="prihod-preview-notice" aria-label="' . esc_attr__( 'Style preview', 'georg-kloster-prihod' ) . '"><div><strong>' . esc_html( sprintf( __( 'Preview: %s', 'georg-kloster-prihod' ), prihod_theme_schemes()[$scheme] ) ) . '</strong><span>' . esc_html__( 'The site style has not been changed.', 'georg-kloster-prihod' ) . '</span></div><a href="' . esc_url( admin_url( 'themes.php?page=georg-kloster-prihod' ) . '#prihod-option-schemes' ) . '">' . esc_html__( 'Back to styles', 'georg-kloster-prihod' ) . '</a></aside>';
}
add_action( 'wp_body_open', 'prihod_theme_preview_notice' );
add_filter( 'wp_theme_json_data_user', function ( WP_Theme_JSON_Data $data ): WP_Theme_JSON_Data {
    $preview = prihod_theme_preview_scheme();
    if ( 'georg-kloster-prihod' === get_template() && $preview ) {
        $scheme = prihod_theme_scheme_data( $preview );
        // Only the palette and scheme tokens are previewed, never persisted.
        $data->update_with( array( 'version' => 2, 'settings' => array( 'color' => $scheme['settings']['color'], 'custom' => $scheme['settings']['custom'] ) ) );
    }
    return $data;
} );

// Theme defaults remain below WordPress user styles; no saved style posts are replaced.
function prihod_theme_design( WP_Theme_JSON_Data $data ): WP_Theme_JSON_Data {
    if ( 'georg-kloster-prihod' !== get_template() ) { return $data; }
    $options = georg_kloster_prihod_options();
    if ( $options['scheme'] && isset( prihod_theme_schemes()[ $options['scheme'] ] ) ) {
        $scheme = prihod_theme_scheme_data( $options['scheme'] );
        if ( is_array( $scheme ) ) { $data->update_with( $scheme ); }
    }
    $data->update_with( array( 'version' => 2, 'settings' => array( 'layout' => array( 'wideSize' => $options['width'] . 'px', 'contentSize' => ( $options['width'] - 440 ) . 'px' ) ), 'styles' => array( 'typography' => array( 'fontFamily' => 'var:preset|font-family|' . $options['font'], 'fontSize' => $options['font_size'] . 'px' ) ) ) );
    return $data;
}
add_filter( 'wp_theme_json_data_theme', 'prihod_theme_design' );
add_action( 'wp_enqueue_scripts', function () {
    wp_add_inline_style( 'georg-kloster-prihod-design', ':root{--prihod-width:' . (int) georg_kloster_prihod_options()['width'] . 'px}' );
}, 15 );
