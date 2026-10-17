<?php
defined( 'ABSPATH' ) || exit;

function georg_kloster_prihod_options(): array {
    $saved = get_option( 'georg_kloster_prihod_options', array() );
    $defaults = array( 'hero_image' => 0, 'logo' => 0, 'logo_show' => true, 'logo_width' => 240, 'logo_removed' => false, 'logo_hide_title' => false, 'languages' => array(), 'scheme' => '', 'width' => 1200, 'font' => 'sans', 'font_size' => 16, 'sticky_header' => false, 'header_contacts' => true, 'footer_text' => '', 'header_motto' => __( 'С любовью к Богу и людям', 'georg-kloster-prihod' ), 'hero_motto' => __( 'Вера объединяет сердца', 'georg-kloster-prihod' ), 'hero_quote_text' => __( 'Где двое или трое собраны во имя Моё, там Я посреди них', 'georg-kloster-prihod' ), 'hero_quote_source' => __( 'Мф. 18:20', 'georg-kloster-prihod' ), 'quote_text' => __( 'Где двое или трое собраны во имя Моё, там Я посреди них', 'georg-kloster-prihod' ), 'quote_source' => __( 'Мф. 18:20', 'georg-kloster-prihod' ) );
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
    if ( is_string( $input['scheme'] ?? null ) && isset( prihod_theme_schemes()[ $input['scheme'] ] ) ) { $out['scheme'] = $input['scheme']; }
    $out['width'] = max( 960, min( 1440, is_scalar( $input['width'] ?? null ) ? absint( $input['width'] ) : 1200 ) );
    $out['font_size'] = max( 14, min( 20, is_scalar( $input['font_size'] ?? null ) ? absint( $input['font_size'] ) : 16 ) );
    $out['font'] = in_array( $input['font'] ?? null, array( 'sans', 'serif' ), true ) ? $input['font'] : 'sans';
    $out['footer_text'] = is_string( $input['footer_text'] ?? null ) ? sanitize_textarea_field( $input['footer_text'] ) : '';
    foreach ( array( 'hero_image', 'logo' ) as $key ) {
        if ( ! array_key_exists( $key, $input ) ) { continue; }
        $id = is_scalar( $input[ $key ] ?? null ) ? absint( $input[ $key ] ) : 0;
        $out[ $key ] = $id && wp_attachment_is_image( $id ) ? $id : 0;
    }
    foreach ( array( 'quote_text', 'quote_source', 'header_motto', 'hero_motto', 'hero_quote_text', 'hero_quote_source' ) as $key ) { if ( array_key_exists( $key, $input ) ) { $out[$key] = is_string( $input[$key] ) ? sanitize_textarea_field( $input[$key] ) : ''; } }
    $out['languages'] = array();
    foreach ( array( 'ru', 'de', 'pl', 'uk' ) as $language ) {
        if ( is_string( $input['languages'][ $language ] ?? null ) ) {
            $url = esc_url_raw( $input['languages'][ $language ], array( 'https', 'http' ) );
            if ( $url && wp_parse_url( $url, PHP_URL_HOST ) ) { $out['languages'][ $language ] = $url; }
        }
    }
    return $out;
}
add_action( 'admin_init', function () { register_setting( 'prihod_theme', 'georg_kloster_prihod_options', array( 'type' => 'array', 'sanitize_callback' => 'prihod_theme_sanitize' ) ); } );
add_filter( 'option_page_capability_prihod_theme', static function (): string { return 'edit_theme_options'; } );
add_action( 'admin_menu', function () {
    add_theme_page( __( 'Оформление прихода', 'georg-kloster-prihod' ), __( 'Оформление прихода', 'georg-kloster-prihod' ), 'edit_theme_options', 'georg-kloster-prihod', 'prihod_theme_settings_page' );
}, 20 );
add_action( 'admin_enqueue_scripts', function () {
    if ( 'georg-kloster-prihod' !== ( $_GET['page'] ?? '' ) ) { return; }
    wp_enqueue_media();
    wp_enqueue_style( 'georg-kloster-prihod-settings', get_template_directory_uri() . '/assets/settings.css', array(), wp_get_theme()->get( 'Version' ) );
    wp_enqueue_script( 'georg-kloster-prihod-media', get_template_directory_uri() . '/assets/media.js', array( 'jquery', 'media-views' ), '0.3.28', true );
    wp_enqueue_script( 'georg-kloster-prihod-settings', get_template_directory_uri() . '/assets/settings.js', array(), '0.3.28', true );
} );
function prihod_theme_schemes(): array {
    return array( 'classic' => __( 'Классическая золотая', 'georg-kloster-prihod' ), 'hospitality' => __( 'Светлая гостеприимная', 'georg-kloster-prihod' ), 'editorial' => __( 'Журнальная', 'georg-kloster-prihod' ), 'tradition' => __( 'Традиционная', 'georg-kloster-prihod' ), 'blue' => __( 'Современная синяя', 'georg-kloster-prihod' ) );
}
function prihod_theme_settings_page(): void {
    if ( ! current_user_can( 'edit_theme_options' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'georg-kloster-prihod' ) ); }
    $options = georg_kloster_prihod_options();
    $sections = array( 'schemes' => __( 'Оформление', 'georg-kloster-prihod' ), 'layout' => __( 'Макет и шрифты', 'georg-kloster-prihod' ), 'branding' => __( 'Логотип и иконка', 'georg-kloster-prihod' ), 'header' => __( 'Шапка и подвал', 'georg-kloster-prihod' ), 'languages' => __( 'Языки', 'georg-kloster-prihod' ) );
    echo '<div class="wrap georg-kloster-prihod-settings"><h1>' . esc_html__( 'Оформление прихода', 'georg-kloster-prihod' ) . ' <small>' . esc_html( wp_get_theme()->get( 'Version' ) ) . '</small></h1>';
    settings_errors();
    echo '<div class="prihod-options-shell"><nav class="prihod-options-nav" aria-label="' . esc_attr__( 'Разделы настроек', 'georg-kloster-prihod' ) . '">';
    foreach ( $sections as $id => $title ) { echo '<a href="#prihod-option-' . esc_attr( $id ) . '" data-section="' . esc_attr( $id ) . '">' . esc_html( $title ) . '</a>'; }
    echo '</nav><div class="prihod-options-content"><form method="post" action="options.php"><div class="prihod-options-toolbar"><strong>' . esc_html__( 'Настройки темы', 'georg-kloster-prihod' ) . '</strong>';
    submit_button( __( 'Сохранить изменения', 'georg-kloster-prihod' ), 'primary', 'submit', false );
    echo '<a class="button" href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Посмотреть сайт', 'georg-kloster-prihod' ) . '</a></div>';
    settings_fields( 'prihod_theme' );
    echo '<section id="prihod-option-schemes" class="prihod-option-section"><h2>' . esc_html( $sections['schemes'] ) . '</h2><p>' . esc_html__( 'Предпросмотр открывается в новой вкладке и не меняет сайт. Чтобы применить оформление, выберите карточку и сохраните изменения.', 'georg-kloster-prihod' ) . '</p><p>' . esc_html__( 'Карточки показывают примеры оформления. Живой предпросмотр использует ваши страницы, фотографии и настройки.', 'georg-kloster-prihod' ) . '</p><div class="prihod-scheme-cards">';
    $current = $options['scheme'] ?: ( WP_Theme_JSON_Resolver::get_merged_data()->get_settings()['custom']['prihod']['scheme'] ?? 'classic' );
    foreach ( prihod_theme_schemes() as $id => $title ) {
        echo '<article class="prihod-scheme-card"><label><img class="prihod-scheme-preview" src="' . esc_url( get_template_directory_uri() . '/assets/schemes/' . $id . '.jpg' ) . '" alt="" loading="lazy"><span class="prihod-scheme-choice"><input type="radio" aria-label="' . esc_attr( $title ) . '" name="georg_kloster_prihod_options[scheme]" value="' . esc_attr( $id ) . '"' . checked( $current, $id, false ) . '><strong>' . esc_html( $title ) . '</strong></span></label><div class="prihod-scheme-actions">';
        if ( $current === $id ) { echo '<small>' . esc_html__( 'Текущее оформление', 'georg-kloster-prihod' ) . '</small>'; }
        echo '<a class="button" href="' . esc_url( add_query_arg( 'prihod_preview', $id, home_url( '/' ) ) ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( sprintf( __( 'Предпросмотр: %s (новая вкладка)', 'georg-kloster-prihod' ), $title ) ) . '">' . esc_html__( 'Предпросмотр', 'georg-kloster-prihod' ) . '</a></div></article>';
    }
    echo '</div></section><section id="prihod-option-layout" class="prihod-option-section"><h2>' . esc_html( $sections['layout'] ) . '</h2>';
    foreach ( array( 'width' => array( __( 'Ширина сайта, px', 'georg-kloster-prihod' ), 960, 1440 ), 'font_size' => array( __( 'Размер основного текста, px', 'georg-kloster-prihod' ), 14, 20 ) ) as $key => $field ) { echo '<p><label>' . esc_html( $field[0] ) . '<br><input type="number" min="' . $field[1] . '" max="' . $field[2] . '" name="georg_kloster_prihod_options[' . esc_attr( $key ) . ']" value="' . esc_attr( $options[$key] ) . '"></label></p>'; }
    echo '<p><label>' . esc_html__( 'Основной шрифт', 'georg-kloster-prihod' ) . '<br><select name="georg_kloster_prihod_options[font]">';
    foreach ( array( 'sans' => __( 'Простой', 'georg-kloster-prihod' ), 'serif' => __( 'Книжный', 'georg-kloster-prihod' ) ) as $id => $title ) { echo '<option value="' . $id . '"' . selected( $options['font'], $id, false ) . '>' . esc_html( $title ) . '</option>'; }
    echo '</select></label></p></section>'; prihod_theme_branding_panel( $options ); echo '<section id="prihod-option-header" class="prihod-option-section"><h2>' . esc_html( $sections['header'] ) . '</h2>';
    foreach ( array( 'sticky_header' => __( 'Закрепить шапку при прокрутке', 'georg-kloster-prihod' ), 'header_contacts' => __( 'Показывать контакты в шапке', 'georg-kloster-prihod' ) ) as $key => $title ) { echo '<p><label><input type="checkbox" name="georg_kloster_prihod_options[' . esc_attr( $key ) . ']" value="1"' . checked( $options[$key], true, false ) . '> ' . esc_html( $title ) . '</label></p>'; }
    echo '<p><label>' . esc_html__( 'Текст цитаты', 'georg-kloster-prihod' ) . '<br><textarea class="large-text" rows="3" name="georg_kloster_prihod_options[quote_text]">' . esc_textarea( $options['quote_text'] ) . '</textarea></label></p><p><label>' . esc_html__( 'Источник цитаты', 'georg-kloster-prihod' ) . '<br><input class="regular-text" name="georg_kloster_prihod_options[quote_source]" value="' . esc_attr( $options['quote_source'] ) . '"></label></p>';
    foreach ( array( 'header_motto' => __( 'Девиз в шапке классической схемы', 'georg-kloster-prihod' ), 'hero_motto' => __( 'Надпись первого экрана классической схемы', 'georg-kloster-prihod' ), 'hero_quote_text' => __( 'Цитата под кнопками синей схемы', 'georg-kloster-prihod' ), 'hero_quote_source' => __( 'Источник цитаты синей схемы', 'georg-kloster-prihod' ) ) as $key => $label ) { echo '<p><label>' . esc_html( $label ) . '<br><textarea class="large-text" rows="2" name="georg_kloster_prihod_options[' . esc_attr( $key ) . ']">' . esc_textarea( $options[$key] ) . '</textarea></label></p>'; }
    echo '<p><label>' . esc_html__( 'Дополнительный текст подвала', 'georg-kloster-prihod' ) . '<br><textarea class="large-text" rows="3" name="georg_kloster_prihod_options[footer_text]">' . esc_textarea( $options['footer_text'] ) . '</textarea></label></p></section><section id="prihod-option-languages" class="prihod-option-section"><h2>' . esc_html__( 'Языковые версии страниц', 'georg-kloster-prihod' ) . '</h2><p>' . esc_html__( 'Укажите адреса существующих версий сайта. Переводы материалов создаются отдельно; тема не переводит тексты автоматически.', 'georg-kloster-prihod' ) . '</p>';
    foreach ( array( 'ru' => 'Русский', 'de' => 'Deutsch', 'pl' => 'Polski', 'uk' => 'Українська' ) as $code => $name ) { echo '<p><label>' . esc_html( $name ) . '<br><input type="url" class="regular-text" name="georg_kloster_prihod_options[languages][' . esc_attr( $code ) . ']" value="' . esc_attr( $options['languages'][ $code ] ?? '' ) . '"></label></p>'; }
    echo '</section></form></div></div></div>';
}
