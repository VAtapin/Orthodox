<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_preview_scheme(): string {
    $value = $_GET['prihod_preview'] ?? '';
    return is_string( $value ) && isset( prihod_theme_schemes()[$value] ) && current_user_can( 'edit_theme_options' ) ? $value : '';
}
add_filter( 'wp_theme_json_data_user', function ( WP_Theme_JSON_Data $data ): WP_Theme_JSON_Data {
    $preview = prihod_theme_preview_scheme();
    if ( 'prihod-theme' === get_template() && $preview ) {
        $path = get_template_directory() . ( 'classic' === $preview ? '/theme.json' : '/styles/' . $preview . '.json' );
        $scheme = json_decode( file_get_contents( $path ), true );
        // Only the palette and scheme tokens are previewed, never persisted.
        $data->update_with( array( 'version' => 2, 'settings' => array( 'color' => $scheme['settings']['color'], 'custom' => $scheme['settings']['custom'] ) ) );
    }
    return $data;
} );

// Theme defaults remain below WordPress user styles; no saved style posts are replaced.
function prihod_theme_design( WP_Theme_JSON_Data $data ): WP_Theme_JSON_Data {
    if ( 'prihod-theme' !== get_template() ) { return $data; }
    $options = prihod_theme_options();
    if ( $options['scheme'] && isset( prihod_theme_schemes()[ $options['scheme'] ] ) ) {
        $path = get_template_directory() . ( 'classic' === $options['scheme'] ? '/theme.json' : '/styles/' . $options['scheme'] . '.json' );
        $scheme = json_decode( file_get_contents( $path ), true );
        if ( is_array( $scheme ) ) { $data->update_with( $scheme ); }
    }
    $data->update_with( array( 'version' => 2, 'settings' => array( 'layout' => array( 'wideSize' => $options['width'] . 'px', 'contentSize' => ( $options['width'] - 440 ) . 'px' ) ), 'styles' => array( 'typography' => array( 'fontFamily' => 'var:preset|font-family|' . $options['font'], 'fontSize' => $options['font_size'] . 'px' ) ) ) );
    return $data;
}
add_filter( 'wp_theme_json_data_theme', 'prihod_theme_design' );
add_action( 'wp_enqueue_scripts', function () {
    wp_add_inline_style( 'prihod-theme-design', ':root{--prihod-width:' . (int) prihod_theme_options()['width'] . 'px}' );
}, 15 );
