<?php
defined( 'ABSPATH' ) || exit;

// Use WordPress's canonical icon so browser tabs and the administration agree.
add_action( 'admin_init', function () {
    register_setting( 'prihod_theme', 'site_icon', array( 'type' => 'integer', 'sanitize_callback' => 'prihod_theme_icon_sanitize' ) );
} );
function prihod_theme_icon_sanitize( $value ): int {
    $id = is_scalar( $value ) && preg_match( '/^\d+$/', (string) $value ) ? absint( $value ) : -1;
    if ( 0 === $id ) { return 0; }
    $meta = wp_get_attachment_metadata( $id );
    if ( $id > 0 && wp_attachment_is_image( $id ) && is_array( $meta ) && ( $meta['width'] ?? 0 ) >= 512 && ( $meta['width'] ?? 0 ) === ( $meta['height'] ?? -1 ) ) { return $id; }
    add_settings_error( 'site_icon', 'prihod-invalid-icon', __( 'Иконка должна быть квадратным изображением размером не менее 512 × 512 px. Прежняя иконка сохранена.', 'prihod-theme' ) );
    return absint( get_option( 'site_icon' ) );
}
function prihod_theme_branding_panel( array $options ): void {
    echo '<section id="prihod-option-branding" class="prihod-option-section"><h2>' . esc_html__( 'Логотип, иконка и соцсети', 'prihod-theme' ) . '</h2>';
    $fields = array(
        'logo' => array( __( 'Логотип', 'prihod-theme' ), __( 'Квадратный знак или широкий логотип с надписью. Изображение отображается целиком с сохранением пропорций.', 'prihod-theme' ), $options['logo_removed'] ? 0 : ( $options['logo'] ?: get_theme_mod( 'custom_logo' ) ), 'prihod_theme_options[logo]' ),
        'icon' => array( __( 'Иконка сайта', 'prihod-theme' ), __( 'Квадратное изображение от 512 × 512 px. Используется во вкладках браузера и на мобильных устройствах.', 'prihod-theme' ), get_option( 'site_icon' ), 'site_icon' ),
        'social' => array( __( 'Изображение для соцсетей', 'prihod-theme' ), __( 'Рекомендуется 1200 × 630 px. Для статьи используется её главная фотография; это изображение служит запасным для остальных страниц.', 'prihod-theme' ), $options['social_image'], 'prihod_theme_options[social_image]' ),
    );
    foreach ( $fields as $kind => $field ) {
        echo '<div class="prihod-media-field prihod-branding-field" data-branding="' . esc_attr( $kind ) . '"><h3>' . esc_html( $field[0] ) . '</h3><p>' . esc_html( $field[1] ) . '</p><input type="hidden" name="' . esc_attr( $field[3] ) . '" value="' . absint( 'logo' === $kind ? $options['logo'] : $field[2] ) . '"><div class="prihod-media-preview">';
        if ( $field[2] ) { echo wp_get_attachment_image( absint( $field[2] ), 'full' ); }
        echo '</div><button class="button prihod-media-select" type="button" data-multiple="0">' . esc_html__( 'Загрузить или выбрать изображение', 'prihod-theme' ) . '</button> <button class="button prihod-media-clear" type="button">' . esc_html__( 'Убрать', 'prihod-theme' ) . '</button>';
        if ( 'logo' === $kind ) {
            echo '<input type="hidden" name="prihod_theme_options[logo_removed]" value="' . ( $options['logo_removed'] ? '1' : '0' ) . '">';
            echo '<p><label>' . esc_html__( 'Максимальная ширина логотипа, px', 'prihod-theme' ) . ' <input type="number" min="64" max="320" name="prihod_theme_options[logo_width]" value="' . absint( $options['logo_width'] ) . '"></label></p>';
            foreach ( array( 'logo_show' => array( __( 'Показывать логотип', 'prihod-theme' ), $options['logo_show'] ), 'title_visible' => array( __( 'Показывать название', 'prihod-theme' ), ! $options['logo_hide_title'] ) ) as $key => $control ) {
                echo '<input type="hidden" name="prihod_theme_options[' . esc_attr( $key ) . ']" value="0"><p><label><input type="checkbox" name="prihod_theme_options[' . esc_attr( $key ) . ']" value="1"' . checked( $control[1], true, false ) . '> ' . esc_html( $control[0] ) . '</label></p>';
            }
            echo '<div class="prihod-logo-example"><span class="prihod-logo-example-image">' . ( $field[2] ? wp_get_attachment_image( absint( $field[2] ), 'full' ) : '' ) . '</span><strong>' . esc_html( prihod_theme_profile()['short_name'] ?? prihod_theme_profile()['name'] ) . '</strong></div><p class="prihod-brand-empty" hidden>' . esc_html__( 'Логотип и название отключены. На сайте останется ссылка на главную страницу.', 'prihod-theme' ) . '</p>';
        } elseif ( 'icon' === $kind ) {
            echo '<div class="prihod-icon-example"><span>' . ( $field[2] ? wp_get_attachment_image( absint( $field[2] ), array( 32, 32 ) ) : '' ) . '</span>' . esc_html( get_bloginfo( 'name' ) ) . '</div>';
        } else {
            echo '<input type="hidden" name="prihod_theme_options[social_meta]" value="0"><p><label><input type="checkbox" name="prihod_theme_options[social_meta]" value="1"' . checked( $options['social_meta'], true, false ) . '> ' . esc_html__( 'Добавлять предпросмотр ссылок для соцсетей', 'prihod-theme' ) . '</label></p><p>' . esc_html__( 'При активном SEO-плагине предпросмотр ссылок настраивается в нём. Соцсети могут сохранять старый предпросмотр в своём кэше.', 'prihod-theme' ) . '</p><div class="prihod-social-example"><div>' . ( $field[2] ? wp_get_attachment_image( absint( $field[2] ), 'full' ) : '' ) . '</div><small>' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</small><strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong><p>' . esc_html( get_bloginfo( 'description' ) ) . '</p></div>';
        }
        echo '</div>';
    }
    echo '</section>';
}

function prihod_theme_social_tags(): void {
    $options = prihod_theme_options();
    if ( is_admin() || is_feed() || is_404() || ( ! is_singular() && ! is_front_page() ) || ! $options['social_meta'] || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) ) { return; }
    $id = is_singular() ? get_queried_object_id() : 0;
    if ( $id && ( ! is_post_publicly_viewable( $id ) || post_password_required( $id ) ) ) { return; }
    $image = $id ? get_post_thumbnail_id( $id ) : 0;
    $image = wp_get_attachment_image_src( $image ?: $options['social_image'], 'full' );
    if ( ! $image ) { return; }
    $title = $id && ! is_front_page() ? get_the_title( $id ) : get_bloginfo( 'name' );
    $description = $id && has_excerpt( $id ) ? get_the_excerpt( $id ) : get_bloginfo( 'description' );
    $tags = array( 'og:type' => $id && 'post' === get_post_type( $id ) ? 'article' : 'website', 'og:site_name' => get_bloginfo( 'name' ), 'og:title' => $title, 'og:description' => wp_strip_all_tags( $description ), 'og:url' => $id ? get_permalink( $id ) : home_url( '/' ), 'og:image' => $image[0], 'og:image:width' => $image[1], 'og:image:height' => $image[2] );
    foreach ( $tags as $property => $value ) { echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $value ) . '">' . "\n"; }
    foreach ( array( 'twitter:card' => 'summary_large_image', 'twitter:title' => $title, 'twitter:description' => wp_strip_all_tags( $description ), 'twitter:image' => $image[0] ) as $name => $value ) { echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '">' . "\n"; }
}
add_action( 'wp_head', 'prihod_theme_social_tags', 5 );
