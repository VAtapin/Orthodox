<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_preview_scheme(): string {
    $value = $_GET['prihod_preview'] ?? '';
    return is_string( $value ) && isset( prihod_theme_schemes()[$value] ) && current_user_can( 'edit_theme_options' ) ? $value : '';
}
function prihod_theme_preview_notice(): void {
    $scheme = prihod_theme_preview_scheme();
    if ( ! $scheme ) { return; }
    echo '<aside class="prihod-preview-notice" aria-label="' . esc_attr__( 'Предпросмотр оформления', 'georg-kloster-prihod' ) . '"><div><strong>' . esc_html( sprintf( __( 'Предпросмотр: %s', 'georg-kloster-prihod' ), prihod_theme_schemes()[$scheme] ) ) . '</strong><span>' . esc_html__( 'Оформление сайта не изменено.', 'georg-kloster-prihod' ) . '</span></div><a href="' . esc_url( admin_url( 'themes.php?page=georg-kloster-prihod' ) . '#prihod-option-schemes' ) . '">' . esc_html__( 'Вернуться к оформлениям', 'georg-kloster-prihod' ) . '</a></aside>';
}
add_action( 'wp_body_open', 'prihod_theme_preview_notice' );
add_filter( 'wp_theme_json_data_user', function ( WP_Theme_JSON_Data $data ): WP_Theme_JSON_Data {
    $preview = prihod_theme_preview_scheme();
    if ( 'georg-kloster-prihod' === get_template() && $preview ) {
        $path = get_template_directory() . ( 'classic' === $preview ? '/theme.json' : '/styles/' . $preview . '.json' );
        $scheme = json_decode( file_get_contents( $path ), true );
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
        $path = get_template_directory() . ( 'classic' === $options['scheme'] ? '/theme.json' : '/styles/' . $options['scheme'] . '.json' );
        $scheme = json_decode( file_get_contents( $path ), true );
        if ( is_array( $scheme ) ) { $data->update_with( $scheme ); }
    }
    $data->update_with( array( 'version' => 2, 'settings' => array( 'layout' => array( 'wideSize' => $options['width'] . 'px', 'contentSize' => ( $options['width'] - 440 ) . 'px' ) ), 'styles' => array( 'typography' => array( 'fontFamily' => 'var:preset|font-family|' . $options['font'], 'fontSize' => $options['font_size'] . 'px' ) ) ) );
    return $data;
}
add_filter( 'wp_theme_json_data_theme', 'prihod_theme_design' );
add_action( 'wp_enqueue_scripts', function () {
    wp_add_inline_style( 'georg-kloster-prihod-design', ':root{--prihod-width:' . (int) georg_kloster_prihod_options()['width'] . 'px}' );
}, 15 );
