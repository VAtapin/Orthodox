<?php
defined( 'ABSPATH' ) || exit;

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
