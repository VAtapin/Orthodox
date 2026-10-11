<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_options(): array {
    $saved = get_option( 'prihod_theme_options', array() );
    return array_merge( array( 'hero_image' => 0, 'about_image' => 0, 'logo' => 0, 'gallery' => '', 'map_url' => '', 'languages' => array(), 'stepped_forms' => true, 'scheme' => '', 'width' => 1200, 'font' => 'sans', 'font_size' => 16, 'sticky_header' => false, 'header_contacts' => true, 'footer_text' => '' ), is_array( $saved ) ? $saved : array() );
}
function prihod_theme_sanitize( $input ): array {
    $out = prihod_theme_options();
    if ( ! is_array( $input ) ) { return $out; }
    $out['stepped_forms'] = ! empty( $input['stepped_forms'] );
    $out['sticky_header'] = ! empty( $input['sticky_header'] );
    $out['header_contacts'] = ! empty( $input['header_contacts'] );
    if ( is_string( $input['scheme'] ?? null ) && isset( prihod_theme_schemes()[ $input['scheme'] ] ) ) { $out['scheme'] = $input['scheme']; }
    $out['width'] = max( 960, min( 1440, is_scalar( $input['width'] ?? null ) ? absint( $input['width'] ) : 1200 ) );
    $out['font_size'] = max( 14, min( 20, is_scalar( $input['font_size'] ?? null ) ? absint( $input['font_size'] ) : 16 ) );
    $out['font'] = in_array( $input['font'] ?? null, array( 'sans', 'serif' ), true ) ? $input['font'] : 'sans';
    $out['footer_text'] = is_string( $input['footer_text'] ?? null ) ? sanitize_textarea_field( $input['footer_text'] ) : '';
    foreach ( array( 'hero_image', 'about_image', 'logo' ) as $key ) {
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
    wp_enqueue_style( 'prihod-theme-settings', get_template_directory_uri() . '/assets/settings.css', array(), '0.3.0' );
    wp_enqueue_script( 'prihod-theme-media', get_template_directory_uri() . '/assets/media.js', array( 'jquery', 'media-views' ), '0.3.0', true );
    wp_enqueue_script( 'prihod-theme-settings', get_template_directory_uri() . '/assets/settings.js', array(), '0.3.0', true );
} );
function prihod_theme_schemes(): array {
    return array( 'classic' => __( 'Классическая золотая', 'prihod-theme' ), 'hospitality' => __( 'Светлая гостеприимная', 'prihod-theme' ), 'editorial' => __( 'Журнальная', 'prihod-theme' ), 'tradition' => __( 'Традиционная', 'prihod-theme' ), 'blue' => __( 'Современная синяя', 'prihod-theme' ) );
}
function prihod_theme_settings_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod-theme' ) ); }
    $options = prihod_theme_options();
    $sections = array( 'schemes' => __( 'Оформление', 'prihod-theme' ), 'layout' => __( 'Макет и шрифты', 'prihod-theme' ), 'photos' => __( 'Фотографии', 'prihod-theme' ), 'header' => __( 'Шапка и подвал', 'prihod-theme' ), 'forms' => __( 'Формы', 'prihod-theme' ), 'languages' => __( 'Языки', 'prihod-theme' ), 'demo' => __( 'Демо-страницы', 'prihod-theme' ) );
    echo '<div class="wrap prihod-theme-settings"><h1>' . esc_html__( 'Оформление прихода', 'prihod-theme' ) . ' <small>0.3.0</small></h1>';
    settings_errors();
    if ( isset( $_GET['demo_done'] ) ) { echo '<div class="notice notice-success"><p>' . esc_html__( 'Демо-страницы подготовлены. Откройте их в редакторе и добавьте материалы своего прихода.', 'prihod-theme' ) . '</p></div>'; }
    echo '<div class="prihod-options-shell"><nav class="prihod-options-nav" aria-label="' . esc_attr__( 'Разделы настроек', 'prihod-theme' ) . '">';
    foreach ( $sections as $id => $title ) { echo '<a href="#prihod-option-' . esc_attr( $id ) . '" data-section="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</a>'; }
    echo '</nav><div class="prihod-options-content"><form method="post" action="options.php"><div class="prihod-options-toolbar"><strong>' . esc_html__( 'Настройки темы', 'prihod-theme' ) . '</strong>';
    submit_button( __( 'Сохранить изменения', 'prihod-theme' ), 'primary', 'submit', false );
    echo '<a class="button" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Посмотреть сайт', 'prihod-theme' ) . '</a></div>';
    settings_fields( 'prihod_theme' );
    echo '<section id="prihod-option-schemes" class="prihod-option-section"><h2>' . esc_html( $sections['schemes'] ) . '</h2><p>' . esc_html__( 'Выберите оформление и сохраните изменения. Собственные стили из редактора сайта имеют приоритет.', 'prihod-theme' ) . '</p><div class="prihod-scheme-cards">';
    $current = $options['scheme'] ?: ( WP_Theme_JSON_Resolver::get_merged_data()->get_settings()['custom']['prihod']['scheme'] ?? 'classic' );
    foreach ( prihod_theme_schemes() as $id => $title ) {
        $json = json_decode( file_get_contents( get_template_directory() . ( 'classic' === $id ? '/theme.json' : '/styles/' . $id . '.json' ) ), true );
        echo '<label class="prihod-scheme-card"><input type="radio" name="prihod_theme_options[scheme]" value="' . esc_attr( $id ) . '"' . checked( $current, $id, false ) . '><span class="prihod-scheme-preview" aria-hidden="true">';
        foreach ( array_slice( $json['settings']['color']['palette'], 0, 5 ) as $color ) { echo '<i style="background:' . esc_attr( $color['color'] ) . '"></i>'; }
        echo '</span><strong>' . esc_html( $title ) . '</strong><a href="' . esc_url( add_query_arg( 'prihod_preview', $id, home_url( '/' ) ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Посмотреть сайт', 'prihod-theme' ) . '</a></label>';
    }
    echo '</div><p><a class="button" href="' . esc_url( admin_url( 'site-editor.php' ) ) . '">' . esc_html__( 'Открыть редактор сайта', 'prihod-theme' ) . '</a></p></section><section id="prihod-option-layout" class="prihod-option-section"><h2>' . esc_html( $sections['layout'] ) . '</h2>';
    foreach ( array( 'width' => array( __( 'Ширина сайта, px', 'prihod-theme' ), 960, 1440 ), 'font_size' => array( __( 'Размер основного текста, px', 'prihod-theme' ), 14, 20 ) ) as $key => $field ) { echo '<p><label>' . esc_html( $field[0] ) . '<br><input type="number" min="' . $field[1] . '" max="' . $field[2] . '" name="prihod_theme_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $options[$key] ) . '"></label></p>'; }
    echo '<p><label>' . esc_html__( 'Основной шрифт', 'prihod-theme' ) . '<br><select name="prihod_theme_options[font]">';
    foreach ( array( 'sans' => __( 'Простой', 'prihod-theme' ), 'serif' => __( 'Книжный', 'prihod-theme' ) ) as $id => $title ) { echo '<option value="' . $id . '"' . selected( $options['font'], $id, false ) . '>' . esc_html( $title ) . '</option>'; }
    echo '</select></label></p></section><section id="prihod-option-photos" class="prihod-option-section"><h2>' . esc_html( $sections['photos'] ) . '</h2>';
    foreach ( array( 'hero_image' => __( 'Главное фото', 'prihod-theme' ), 'about_image' => __( 'Фото прихода', 'prihod-theme' ), 'gallery' => __( 'Фотогалерея', 'prihod-theme' ), 'logo' => __( 'Логотип', 'prihod-theme' ) ) as $key => $label ) {
        echo '<div class="prihod-media-field"><h2>' . esc_html( $label ) . '</h2><input type="hidden" name="prihod_theme_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $options[ $key ] ) . '"><div class="prihod-media-preview">';
        foreach ( explode( ',', (string) $options[ $key ] ) as $id ) { echo wp_get_attachment_image( absint( $id ), 'thumbnail' ); }
        echo '</div><button class="button prihod-media-select" type="button" data-multiple="' . ( 'gallery' === $key ? '1' : '0' ) . '">' . esc_html__( 'Выбрать фотографии', 'prihod-theme' ) . '</button> <button class="button prihod-media-clear" type="button">' . esc_html__( 'Убрать', 'prihod-theme' ) . '</button></div>';
    }
    echo '<p><label>' . esc_html__( 'Ссылка на карту (HTTPS)', 'prihod-theme' ) . '<br><input type="url" class="regular-text" name="prihod_theme_options[map_url]" value="' . esc_attr( $options['map_url'] ) . '"></label></p>';
    echo '<p>' . esc_html__( 'Выберите собственные фотографии из медиатеки. Без фото отображается спокойный фон. Скриншоты макетов не используются как контент.', 'prihod-theme' ) . '</p></section><section id="prihod-option-header" class="prihod-option-section"><h2>' . esc_html( $sections['header'] ) . '</h2>';
    foreach ( array( 'sticky_header' => __( 'Закрепить шапку при прокрутке', 'prihod-theme' ), 'header_contacts' => __( 'Показывать контакты в шапке', 'prihod-theme' ) ) as $key => $title ) { echo '<p><label><input type="checkbox" name="prihod_theme_options[' . esc_attr( $key ) . ']" value="1"' . checked( $options[$key], true, false ) . '> ' . esc_html( $title ) . '</label></p>'; }
    echo '<p><label>' . esc_html__( 'Дополнительный текст подвала', 'prihod-theme' ) . '<br><textarea class="large-text" rows="3" name="prihod_theme_options[footer_text]">' . esc_textarea( $options['footer_text'] ) . '</textarea></label></p></section><section id="prihod-option-forms" class="prihod-option-section"><h2>' . esc_html( $sections['forms'] ) . '</h2><p><label><input type="checkbox" name="prihod_theme_options[stepped_forms]" value="1"' . checked( $options['stepped_forms'], true, false ) . '> ' . esc_html__( 'Пошаговые формы записок и пожертвований', 'prihod-theme' ) . '</label></p></section><section id="prihod-option-languages" class="prihod-option-section"><h2>' . esc_html__( 'Языковые версии страниц', 'prihod-theme' ) . '</h2><p>' . esc_html__( 'Укажите адреса существующих версий сайта. Переводы материалов создаются отдельно; тема не переводит тексты автоматически.', 'prihod-theme' ) . '</p>';
    foreach ( array( 'ru' => 'Русский', 'de' => 'Deutsch', 'pl' => 'Polski', 'uk' => 'Українська' ) as $code => $name ) { echo '<p><label>' . esc_html( $name ) . '<br><input type="url" class="regular-text" name="prihod_theme_options[languages][' . esc_attr( $code ) . ']" value="' . esc_attr( $options['languages'][ $code ] ?? '' ) . '"></label></p>'; }
    echo '</section></form><section id="prihod-option-demo" class="prihod-option-section"><h2>' . esc_html( $sections['demo'] ) . '</h2>'; prihod_theme_demo_panel(); echo '</section></div></div></div>';
}
