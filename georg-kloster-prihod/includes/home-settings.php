<?php
defined( 'ABSPATH' ) || exit;

/** Presentation settings only: never create, copy or rewrite page content. */
function prihod_theme_home_sections(): array {
    return array( 'hero' => __( 'Первый экран', 'georg-kloster-prihod' ), 'services' => __( 'Ближайшие службы', 'georg-kloster-prihod' ), 'features' => __( 'Карточки разделов', 'georg-kloster-prihod' ), 'topics' => __( 'Дополнительные ссылки', 'georg-kloster-prihod' ), 'about' => __( 'О приходе / монастыре', 'georg-kloster-prihod' ), 'news' => __( 'Новости', 'georg-kloster-prihod' ), 'content' => __( 'Содержимое страницы «Главная»', 'georg-kloster-prihod' ), 'contacts' => __( 'Контакты', 'georg-kloster-prihod' ) );
}
function prihod_theme_home_pages(): array {
    return array( 'schedule' => __( 'Расписание', 'georg-kloster-prihod' ), 'about' => __( 'О приходе / монастыре', 'georg-kloster-prihod' ), 'news' => __( 'Новости', 'georg-kloster-prihod' ), 'requests' => __( 'Вопрос священнику', 'georg-kloster-prihod' ), 'donations' => __( 'Пожертвования', 'georg-kloster-prihod' ), 'contacts' => __( 'Контакты', 'georg-kloster-prihod' ), 'first-visit' => __( 'Паломнику', 'georg-kloster-prihod' ), 'sermons' => __( 'Проповеди и беседы', 'georg-kloster-prihod' ) );
}
function prihod_theme_home_card_order( string $scheme ): array {
    return 'classic' === $scheme ? array( 'schedule', 'news', 'requests', 'donations' ) : ( in_array( $scheme, array( 'hospitality', 'blue' ), true ) ? array( 'schedule', 'about', 'news', 'requests', 'contacts' ) : array( 'schedule', 'news', 'about', 'requests', 'donations' ) );
}
function prihod_theme_home_order( $input, array $allowed ): array {
    $order = is_array( $input ) ? array_values( array_unique( array_filter( $input, static fn( $key ) => is_string( $key ) && in_array( $key, $allowed, true ) ) ) ) : array();
    return array_merge( $order, array_values( array_diff( $allowed, $order ) ) );
}
function prihod_theme_home_normalize( $input, string $scheme ): array {
    $input = is_array( $input ) ? $input : array();
    $out = array( 'order' => prihod_theme_home_order( $input['order'] ?? null, array_keys( prihod_theme_home_sections() ) ), 'sections' => array(), 'cards' => array(), 'pages' => array() );
    foreach ( prihod_theme_home_sections() as $key => $label ) {
        $row = is_array( $input['sections'][$key] ?? null ) ? $input['sections'][$key] : array();
        $out['sections'][$key] = array( 'enabled' => array_key_exists( 'enabled', $row ) ? (bool) $row['enabled'] : ( 'about' !== $key || 'editorial' !== $scheme ), 'title' => is_string( $row['title'] ?? null ) ? sanitize_text_field( $row['title'] ) : '' );
    }
    $defaults = prihod_theme_home_card_order( $scheme );
    $card_keys = array_slice( array_keys( prihod_theme_home_pages() ), 0, 6 );
    $out['card_order'] = prihod_theme_home_order( $input['card_order'] ?? null, array_merge( $defaults, array_values( array_diff( $card_keys, $defaults ) ) ) );
    foreach ( $card_keys as $key ) {
        $row = is_array( $input['cards'][$key] ?? null ) ? $input['cards'][$key] : array();
        $out['cards'][$key] = array( 'enabled' => array_key_exists( 'enabled', $row ) ? (bool) $row['enabled'] : in_array( $key, $defaults, true ) );
        foreach ( array( 'title', 'description', 'button' ) as $field ) { $out['cards'][$key][$field] = is_string( $row[$field] ?? null ) ? sanitize_text_field( $row[$field] ) : ''; }
    }
    foreach ( prihod_theme_home_pages() as $key => $label ) { $out['pages'][$key] = is_scalar( $input['pages'][$key] ?? null ) ? absint( $input['pages'][$key] ) : 0; }
    foreach ( array( 'hero_description', 'about_description' ) as $key ) { $out[$key] = is_string( $input[$key] ?? null ) ? sanitize_textarea_field( $input[$key] ) : ''; }
    foreach ( array( 'services_count' => 6, 'news_count' => 12, 'news_excerpt_words' => 80, 'news_columns' => 4 ) as $key => $max ) { $out[$key] = is_scalar( $input[$key] ?? null ) ? max( 0, min( $max, (int) $input[$key] ) ) : 0; }
    $out['news_category'] = is_scalar( $input['news_category'] ?? null ) ? max( -1, (int) $input['news_category'] ) : 0;
    foreach ( array( 'news_show_date', 'news_show_excerpt', 'news_show_images', 'cards_show_images', 'hide_schedule_card' ) as $key ) { $out[$key] = array_key_exists( $key, $input ) ? (bool) $input[$key] : ( 'cards_show_images' !== $key || in_array( $scheme, array( 'classic', 'editorial', 'tradition' ), true ) ); }
    return $out;
}
function prihod_theme_home_options(): array {
    return prihod_theme_home_normalize( georg_kloster_prihod_options()['home'], prihod_theme_scheme() );
}
function prihod_theme_home_text( string $value, string $key, string $fallback = '' ): string {
    if ( '' === $value ) { return $fallback; }
    $translated = apply_filters( 'wpml_translate_single_string', false, 'georg-kloster-prihod', 'home-' . $key );
    return is_string( $translated ) && '' !== $translated ? $translated : $value;
}
function prihod_theme_home_title( string $key, string $fallback = '' ): string {
    return prihod_theme_home_text( prihod_theme_home_options()['sections'][$key]['title'], $key . '-title', $fallback );
}
function prihod_theme_home_resource_id( string $key ): int {
    $id = prihod_theme_home_options()['pages'][$key] ?? 0;
    if ( ! $id ) { return prihod_theme_resource_id( $key ); }
    if ( function_exists( 'pll_get_post' ) ) { $id = pll_get_post( $id ) ?: $id; }
    $id = (int) apply_filters( 'wpml_object_id', $id, get_post_type( $id ) ?: 'page', true );
    return 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
}
function prihod_theme_home_url( string $key ): string {
    $id = prihod_theme_home_resource_id( $key );
    return $id && 'publish' === get_post_status( $id ) ? prihod_theme_preview_url( get_permalink( $id ) ) : '';
}
function prihod_theme_home_sanitize( $input, string $scheme ): array {
    $out = prihod_theme_home_normalize( $input, $scheme );
    foreach ( $out['pages'] as $key => $id ) { if ( $id && ( 'page' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) ) { $out['pages'][$key] = 0; } }
    if ( $out['news_category'] > 0 && ! term_exists( $out['news_category'], 'category' ) ) { $out['news_category'] = 0; }
    $strings = array( 'hero-description' => $out['hero_description'], 'about-description' => $out['about_description'] );
    foreach ( $out['sections'] as $key => $section ) { $strings[$key . '-title'] = $section['title']; }
    foreach ( $out['cards'] as $key => $card ) { foreach ( array( 'title', 'description', 'button' ) as $field ) { $strings['card-' . $key . '-' . $field] = $card[$field]; } }
    foreach ( $strings as $name => $value ) { if ( '' !== $value ) { do_action( 'wpml_register_single_string', 'georg-kloster-prihod', 'home-' . $name, $value ); } }
    return $out;
}

function prihod_theme_home_field( string $key, string $label, $value, string $type = 'text' ): void {
    $name = 'georg_kloster_prihod_options[home]' . $key;
    echo '<p><label>' . esc_html( $label ) . '<br>';
    if ( 'checkbox' === $type ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( (bool) $value, true, false ) . '>'; }
    elseif ( 'textarea' === $type ) { echo '<textarea class="large-text" rows="3" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>'; }
    else { echo '<input class="regular-text" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">'; }
    echo '</label></p>';
}
function prihod_theme_home_number( string $key, string $label, int $value, int $max ): void {
    echo '<p><label>' . esc_html( $label ) . '<br><select name="georg_kloster_prihod_options[home][' . esc_attr( $key ) . ']">';
    echo '<option value="0"' . selected( $value, 0, false ) . '>' . esc_html__( 'По оформлению', 'georg-kloster-prihod' ) . '</option>';
    for ( $i = 1; $i <= $max; ++$i ) { echo '<option value="' . $i . '"' . selected( $value, $i, false ) . '>' . $i . '</option>'; }
    echo '</select></label></p>';
}
function prihod_theme_home_move_buttons( string $label ): void {
    echo '<span class="prihod-home-move"><button class="button" type="button" data-home-move="up" aria-label="' . esc_attr( sprintf( __( 'Выше: %s', 'georg-kloster-prihod' ), $label ) ) . '">↑</button> <button class="button" type="button" data-home-move="down" aria-label="' . esc_attr( sprintf( __( 'Ниже: %s', 'georg-kloster-prihod' ), $label ) ) . '">↓</button></span>';
}
function prihod_theme_home_settings_panel( array $options ): void {
    $scheme = $options['scheme'] ?: prihod_theme_scheme();
    $home = prihod_theme_home_normalize( $options['home'], $scheme );
    $sections = prihod_theme_home_sections(); $pages = prihod_theme_home_pages();
    echo '<section id="prihod-option-home" class="prihod-option-section"><h2>' . esc_html__( 'Главная страница', 'georg-kloster-prihod' ) . '</h2><p>' . esc_html__( 'Включайте секции и меняйте их порядок стрелками. Пустые поля текста сохраняют стандартные подписи. Содержимое страниц не изменяется.', 'georg-kloster-prihod' ) . '</p><div class="prihod-home-order" data-home-order>';
    foreach ( $home['order'] as $key ) {
        $row = $home['sections'][$key];
        echo '<article class="prihod-home-setting" data-home-item><header><input type="hidden" name="georg_kloster_prihod_options[home][order][]" value="' . esc_attr( $key ) . '">';
        prihod_theme_home_field( '[sections][' . $key . '][enabled]', $sections[$key], $row['enabled'], 'checkbox' );
        prihod_theme_home_move_buttons( $sections[$key] );
        echo '</header><details><summary>' . esc_html__( 'Настройки секции', 'georg-kloster-prihod' ) . '</summary>';
        prihod_theme_home_field( '[sections][' . $key . '][title]', __( 'Заголовок', 'georg-kloster-prihod' ), $row['title'] );
        if ( in_array( $key, array( 'hero', 'about' ), true ) ) { prihod_theme_home_field( '[' . $key . '_description]', __( 'Описание', 'georg-kloster-prihod' ), $home[$key . '_description'], 'textarea' ); }
        if ( 'services' === $key ) { prihod_theme_home_number( 'services_count', __( 'Количество служб', 'georg-kloster-prihod' ), $home['services_count'], 6 ); echo '<p class="description">' . esc_html__( 'Секция появляется, когда плагин расписания предоставляет ближайшие службы.', 'georg-kloster-prihod' ) . '</p>'; }
        if ( 'features' === $key ) {
            prihod_theme_home_field( '[cards_show_images]', __( 'Показывать фотографии карточек', 'georg-kloster-prihod' ), $home['cards_show_images'], 'checkbox' );
            prihod_theme_home_field( '[hide_schedule_card]', __( 'Скрывать карточку расписания, если показаны ближайшие службы', 'georg-kloster-prihod' ), $home['hide_schedule_card'], 'checkbox' );
            echo '<div class="prihod-home-order" data-home-order>';
            foreach ( $home['card_order'] as $card_key ) {
                $card = $home['cards'][$card_key];
                echo '<div class="prihod-home-setting" data-home-item><header><input type="hidden" name="georg_kloster_prihod_options[home][card_order][]" value="' . esc_attr( $card_key ) . '">';
                prihod_theme_home_field( '[cards][' . $card_key . '][enabled]', $pages[$card_key], $card['enabled'], 'checkbox' ); prihod_theme_home_move_buttons( $pages[$card_key] );
                echo '</header>';
                foreach ( array( 'title' => __( 'Название карточки', 'georg-kloster-prihod' ), 'description' => __( 'Подпись карточки', 'georg-kloster-prihod' ), 'button' => __( 'Текст ссылки', 'georg-kloster-prihod' ) ) as $field => $label ) { prihod_theme_home_field( '[cards][' . $card_key . '][' . $field . ']', $label, $card[$field] ); }
                echo '</div>';
            }
            echo '</div>';
        }
        if ( 'news' === $key ) {
            foreach ( array( 'news_count' => array( __( 'Количество публикаций', 'georg-kloster-prihod' ), 12 ), 'news_columns' => array( __( 'Колонок на компьютере', 'georg-kloster-prihod' ), 4 ), 'news_excerpt_words' => array( __( 'Длина анонса, слов', 'georg-kloster-prihod' ), 80 ) ) as $field => $data ) { prihod_theme_home_number( $field, $data[0], $home[$field], $data[1] ); }
            echo '<p><label>' . esc_html__( 'Рубрика публикаций', 'georg-kloster-prihod' ) . '<br><select name="georg_kloster_prihod_options[home][news_category]">';
            foreach ( array( 0 => __( 'По умолчанию', 'georg-kloster-prihod' ), -1 => __( 'Все рубрики', 'georg-kloster-prihod' ) ) as $id => $label ) { echo '<option value="' . $id . '"' . selected( $home['news_category'], $id, false ) . '>' . esc_html( $label ) . '</option>'; }
            $terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
            if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { echo '<option value="' . (int) $term->term_id . '"' . selected( $home['news_category'], $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>'; } }
            echo '</select></label></p>';
            foreach ( array( 'news_show_date' => __( 'Показывать дату', 'georg-kloster-prihod' ), 'news_show_excerpt' => __( 'Показывать анонс', 'georg-kloster-prihod' ), 'news_show_images' => __( 'Показывать фотографии', 'georg-kloster-prihod' ) ) as $field => $label ) { prihod_theme_home_field( '[' . $field . ']', $label, $home[$field], 'checkbox' ); }
        }
        if ( 'content' === $key ) { echo '<p class="description">' . esc_html__( 'Выводится только собственное содержимое страницы, выбранной главной в настройках чтения.', 'georg-kloster-prihod' ) . '</p>'; }
        echo '</details></article>';
    }
    echo '</div><h3>' . esc_html__( 'Страницы разделов', 'georg-kloster-prihod' ) . '</h3><p>' . esc_html__( 'Выберите страницы для ссылок и карточек. Их изображения записей используются как фотографии карточек. Автоматический выбор использует привязки плагинов, если они установлены.', 'georg-kloster-prihod' ) . '</p>';
    foreach ( $pages as $key => $label ) {
        echo '<p><label>' . esc_html( $label ) . '<br>';
        wp_dropdown_pages( array( 'name' => 'georg_kloster_prihod_options[home][pages][' . $key . ']', 'id' => 'prihod-home-page-' . $key, 'selected' => $home['pages'][$key], 'show_option_none' => __( 'Автоматически', 'georg-kloster-prihod' ), 'option_none_value' => '0', 'post_status' => 'publish' ) );
        echo '</label></p>';
    }
    echo '<p class="description">' . esc_html__( 'Переводы собственных подписей доступны в WPML String Translation, раздел georg-kloster-prihod.', 'georg-kloster-prihod' ) . '</p></section>';
}
