<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_options(): array {
    $saved = get_option( 'prihod_theme_options', array() );
    return array_merge( array( 'hero_image' => 0, 'about_image' => 0, 'gallery' => '', 'map_url' => '', 'languages' => array(), 'stepped_forms' => true ), is_array( $saved ) ? $saved : array() );
}
function prihod_theme_sanitize( $input ): array {
    $out = prihod_theme_options();
    if ( ! is_array( $input ) ) { return $out; }
    $out['stepped_forms'] = ! empty( $input['stepped_forms'] );
    foreach ( array( 'hero_image', 'about_image' ) as $key ) {
        $id = is_scalar( $input[ $key ] ?? null ) ? absint( $input[ $key ] ) : 0;
        $out[ $key ] = $id && wp_attachment_is_image( $id ) ? $id : 0;
    }
    $ids = is_string( $input['gallery'] ?? null ) ? array_slice( array_filter( array_map( 'absint', explode( ',', $input['gallery'] ) ) ), 0, 12 ) : array();
    $out['gallery'] = implode( ',', array_filter( $ids, 'wp_attachment_is_image' ) );
    $out['map_url'] = is_string( $input['map_url'] ?? null ) ? esc_url_raw( $input['map_url'], array( 'https' ) ) : '';
    $out['languages'] = array();
    foreach ( array( 'ru', 'de', 'pl', 'uk' ) as $language ) {
        if ( is_string( $input['languages'][ $language ] ?? null ) ) {
            $url = esc_url_raw( $input['languages'][ $language ], array( 'https', 'http' ) );
            if ( $url && wp_parse_url( $url, PHP_URL_HOST ) ) { $out['languages'][ $language ] = $url; }
        }
    }
    return $out;
}
add_action( 'admin_init', function () { register_setting( 'prihod_theme', 'prihod_theme_options', array( 'type' => 'array', 'sanitize_callback' => 'prihod_theme_sanitize' ) ); } );
add_action( 'admin_menu', function () {
    if ( class_exists( '\Prihod\Plugin' ) ) { add_submenu_page( 'prihod', __( 'Оформление прихода', 'prihod-theme' ), __( 'Оформление прихода', 'prihod-theme' ), 'manage_options', 'prihod-theme', 'prihod_theme_settings_page' ); }
    else { add_theme_page( __( 'Оформление прихода', 'prihod-theme' ), __( 'Оформление прихода', 'prihod-theme' ), 'manage_options', 'prihod-theme', 'prihod_theme_settings_page' ); }
}, 20 );
add_action( 'admin_enqueue_scripts', function () {
    if ( 'prihod-theme' !== ( $_GET['page'] ?? '' ) ) { return; }
    wp_enqueue_media();
    wp_enqueue_script( 'prihod-theme-media', get_template_directory_uri() . '/assets/media.js', array( 'jquery', 'media-views' ), '0.2.0', true );
} );
function prihod_theme_settings_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod-theme' ) ); }
    $options = prihod_theme_options();
    echo '<div class="wrap"><h1>' . esc_html__( 'Оформление прихода', 'prihod-theme' ) . '</h1><p><a class="button" href="' . esc_url( admin_url( 'site-editor.php' ) ) . '">' . esc_html__( 'Пять схем в редакторе сайта → Стили', 'prihod-theme' ) . '</a></p><form method="post" action="options.php">';
    settings_fields( 'prihod_theme' );
    echo '<p><label><input type="checkbox" name="prihod_theme_options[stepped_forms]" value="1"' . checked( $options['stepped_forms'], true, false ) . '> ' . esc_html__( 'Пошаговые формы записок и пожертвований', 'prihod-theme' ) . '</label></p>';
    foreach ( array( 'hero_image' => __( 'Главное фото', 'prihod-theme' ), 'about_image' => __( 'Фото прихода', 'prihod-theme' ), 'gallery' => __( 'Фотогалерея', 'prihod-theme' ) ) as $key => $label ) {
        echo '<div class="prihod-media-field"><h2>' . esc_html( $label ) . '</h2><input type="hidden" name="prihod_theme_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $options[ $key ] ) . '"><div class="prihod-media-preview">';
        foreach ( explode( ',', (string) $options[ $key ] ) as $id ) { echo wp_get_attachment_image( absint( $id ), 'thumbnail' ); }
        echo '</div><button class="button prihod-media-select" type="button" data-multiple="' . ( 'gallery' === $key ? '1' : '0' ) . '">' . esc_html__( 'Выбрать фотографии', 'prihod-theme' ) . '</button> <button class="button prihod-media-clear" type="button">' . esc_html__( 'Убрать', 'prihod-theme' ) . '</button></div>';
    }
    echo '<p><label>' . esc_html__( 'Ссылка на карту (HTTPS)', 'prihod-theme' ) . '<br><input type="url" class="regular-text" name="prihod_theme_options[map_url]" value="' . esc_attr( $options['map_url'] ) . '"></label></p>';
    echo '<p>' . esc_html__( 'Выберите собственные фотографии из медиатеки. Без фото отображается спокойный фон. Скриншоты макетов не используются как контент.', 'prihod-theme' ) . '</p><h2>' . esc_html__( 'Языковые версии страниц', 'prihod-theme' ) . '</h2><p>' . esc_html__( 'Укажите адреса существующих версий сайта. Переводы материалов создаются отдельно; тема не переводит тексты автоматически.', 'prihod-theme' ) . '</p>';
    foreach ( array( 'ru' => 'Русский', 'de' => 'Deutsch', 'pl' => 'Polski', 'uk' => 'Українська' ) as $code => $name ) { echo '<p><label>' . esc_html( $name ) . '<br><input type="url" class="regular-text" name="prihod_theme_options[languages][' . esc_attr( $code ) . ']" value="' . esc_attr( $options['languages'][ $code ] ?? '' ) . '"></label></p>'; }
    submit_button(); echo '</form></div>';
}
