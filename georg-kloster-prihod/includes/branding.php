<?php
defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', function () {
    register_setting( 'prihod_theme', 'site_icon', array( 'type' => 'integer', 'sanitize_callback' => 'prihod_theme_icon_sanitize' ) );
} );

function prihod_theme_icon_sanitize( $value ): int {
    $id = is_scalar( $value ) && preg_match( '/^\d+$/', (string) $value ) ? absint( $value ) : -1;
    if ( 0 === $id ) { return 0; }
    $meta = wp_get_attachment_metadata( $id );
    if ( $id > 0 && wp_attachment_is_image( $id ) && is_array( $meta ) && ( $meta['width'] ?? 0 ) >= 512 && ( $meta['width'] ?? 0 ) === ( $meta['height'] ?? -1 ) ) { return $id; }
    add_settings_error( 'site_icon', 'prihod-invalid-icon', __( 'Иконка должна быть квадратным изображением размером не менее 512 × 512 px. Прежняя иконка сохранена.', 'georg-kloster-prihod' ) );
    return absint( get_option( 'site_icon' ) );
}

function prihod_theme_branding_panel( array $options ): void {
    echo '<section id="prihod-option-branding" class="prihod-option-section"><h2>' . esc_html__( 'Логотип и иконка', 'georg-kloster-prihod' ) . '</h2>';
    $fields = array(
        'logo' => array( __( 'Логотип', 'georg-kloster-prihod' ), $options['logo_removed'] ? 0 : ( $options['logo'] ?: get_theme_mod( 'custom_logo' ) ), 'georg_kloster_prihod_options[logo]' ),
        'icon' => array( __( 'Иконка сайта', 'georg-kloster-prihod' ), get_option( 'site_icon' ), 'site_icon' ),
        'hero_image' => array( __( 'Главное фото', 'georg-kloster-prihod' ), $options['hero_image'], 'georg_kloster_prihod_options[hero_image]' ),
    );
    foreach ( $fields as $kind => $field ) {
        echo '<div class="prihod-media-field prihod-branding-field" data-branding="' . esc_attr( $kind ) . '"><h3>' . esc_html( $field[0] ) . '</h3><input type="hidden" name="' . esc_attr( $field[2] ) . '" value="' . absint( 'logo' === $kind ? $options['logo'] : $field[1] ) . '"><div class="prihod-media-preview">';
        if ( $field[1] ) { echo wp_get_attachment_image( absint( $field[1] ), 'full' ); }
        echo '</div><button class="button prihod-media-select" type="button" data-multiple="0">' . esc_html__( 'Загрузить или выбрать изображение', 'georg-kloster-prihod' ) . '</button> <button class="button prihod-media-clear" type="button">' . esc_html__( 'Убрать', 'georg-kloster-prihod' ) . '</button>';
        if ( 'logo' === $kind ) {
            echo '<input type="hidden" name="georg_kloster_prihod_options[logo_removed]" value="' . ( $options['logo_removed'] ? '1' : '0' ) . '">';
            echo '<p><label>' . esc_html__( 'Максимальная ширина логотипа, px', 'georg-kloster-prihod' ) . ' <input type="number" min="64" max="320" name="georg_kloster_prihod_options[logo_width]" value="' . absint( $options['logo_width'] ) . '"></label></p>';
            foreach ( array( 'logo_show' => array( __( 'Показывать логотип', 'georg-kloster-prihod' ), $options['logo_show'] ), 'title_visible' => array( __( 'Показывать название', 'georg-kloster-prihod' ), ! $options['logo_hide_title'] ) ) as $key => $control ) {
                echo '<input type="hidden" name="georg_kloster_prihod_options[' . esc_attr( $key ) . ']" value="0"><p><label><input type="checkbox" name="georg_kloster_prihod_options[' . esc_attr( $key ) . ']" value="1"' . checked( $control[1], true, false ) . '> ' . esc_html( $control[0] ) . '</label></p>';
            }
            echo '<div class="prihod-logo-example"><span class="prihod-logo-example-image">' . ( $field[1] ? wp_get_attachment_image( absint( $field[1] ), 'full' ) : '' ) . '</span><strong>' . esc_html( prihod_theme_profile()['short_name'] ?? prihod_theme_profile()['name'] ) . '</strong></div><p class="prihod-brand-empty" hidden>' . esc_html__( 'Логотип и название отключены. На сайте останется ссылка на главную страницу.', 'georg-kloster-prihod' ) . '</p>';
        } elseif ( 'icon' === $kind ) {
            echo '<p>' . esc_html__( 'Иконка должна быть квадратной, не менее 512 × 512 px.', 'georg-kloster-prihod' ) . '</p>';
            echo '<div class="prihod-icon-example"><span>' . ( $field[1] ? wp_get_attachment_image( absint( $field[1] ), array( 32, 32 ) ) : '' ) . '</span>' . esc_html( get_bloginfo( 'name' ) ) . '</div>';
        }
        echo '</div>';
    }
    echo '</section>';
}
