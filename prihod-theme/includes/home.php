<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_home_features( string $scheme, array $photos, string $about_label, bool $monastery ): string {
    $cards = array(
        'schedule' => array( __( 'Расписание', 'prihod-theme' ), __( 'Богослужения и особые события', 'prihod-theme' ), __( 'Смотреть расписание', 'prihod-theme' ) ),
        'about' => array( $about_label, __( 'История и жизнь общины', 'prihod-theme' ), __( 'Узнать больше', 'prihod-theme' ) ),
        'news' => array( __( 'Новости', 'prihod-theme' ), __( 'События и объявления', 'prihod-theme' ), __( 'Читать новости', 'prihod-theme' ) ),
        'requests' => array( __( 'Вопрос священнику', 'prihod-theme' ), __( 'Задайте свой вопрос', 'prihod-theme' ), __( 'Задать вопрос', 'prihod-theme' ) ),
        'donations' => array( $monastery ? __( 'Помочь обители', 'prihod-theme' ) : __( 'Помочь приходу', 'prihod-theme' ), __( 'Поддержите добрые дела', 'prihod-theme' ), __( 'Узнать, как помочь', 'prihod-theme' ) ),
        'contacts' => array( __( 'Контакты', 'prihod-theme' ), __( 'Как нас найти', 'prihod-theme' ), __( 'Как добраться', 'prihod-theme' ) ),
    );
    $order = 'classic' === $scheme ? array( 'schedule', 'news', 'requests', 'donations' ) : ( in_array( $scheme, array( 'hospitality', 'blue' ), true ) ? array( 'schedule', 'about', 'news', 'requests', 'contacts' ) : array( 'schedule', 'news', 'about', 'requests', 'donations' ) );
    $photo_cards = in_array( $scheme, array( 'classic', 'editorial', 'tradition' ), true );
    $options = prihod_theme_options(); $resources = get_option( 'prihod_resources', array() );
    $fallback_photos = array( 'schedule' => $options['about_image'], 'news' => $options['about_image'], 'about' => $options['hero_image'], 'requests' => prihod_theme_resource_image( 'clergy' ), 'donations' => $options['hero_image'] );
    $html = '<nav class="prihod-feature-grid" aria-label="' . esc_attr__( 'Разделы сайта', 'prihod-theme' ) . '">';
    foreach ( $order as $index => $key ) {
        $url = prihod_theme_url( $key ); if ( ! $url ) { continue; }
        $card = $cards[$key]; $photo = absint( $options['feature_' . $key] ?? 0 ) ?: ( prihod_theme_resource_image( $key ) ?: ( $fallback_photos[$key] ?? 0 ) );
        if ( ! $photo ) { $photo = $photos[0] ?? 0; }
        $support = ( 'editorial' === $scheme && 'donations' === $key || 'tradition' === $scheme && in_array( $key, array( 'requests', 'donations' ), true ) ) && ! absint( $options['feature_' . $key] ?? 0 );
        $html .= '<a class="prihod-feature' . ( $support ? ( 'editorial' === $scheme ? ' prihod-feature-support' : ' prihod-feature-symbol' ) : ( $photo_cards ? ' has-photo' : '' ) ) . '" href="' . esc_url( $url ) . '">' . ( $support ? '<div class="prihod-feature-support-symbol">' . prihod_theme_icon( 'requests' === $key ? 'requests' : 'support' ) . '</div>' : ( $photo_cards && $photo ? wp_get_attachment_image( $photo, 'medium_large', false, array( 'class' => 'prihod-feature-photo' . ( 'requests' === $key ? ' is-portrait' : '' ), 'style' => 'object-position:center 25%' ) ) : '' ) ) . '<div class="prihod-feature-copy">' . prihod_theme_icon( $key ) . '<div><h2>' . esc_html( $card[0] ) . '</h2><p>' . esc_html( $card[1] ) . '</p><span class="prihod-feature-link">' . esc_html( $card[2] ) . ' →</span></div></div></a>';
    }
    return $html . '</nav>';
}
function prihod_theme_home_news( string $scheme, bool $monastery ): string {
    $category = get_term_by( 'slug', 'prihod-news', 'category' ); $count = 'hospitality' === $scheme ? 4 : 3;
    $posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $count, 'category' => $category ? $category->term_id : 0, 'suppress_filters' => false ) );
    if ( count( $posts ) < $count ) {
        $sermons = get_term_by( 'slug', 'prihod-sermons', 'category' );
        if ( $sermons ) { $posts = array_merge( $posts, get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $count - count( $posts ), 'category' => $sermons->term_id, 'post__not_in' => wp_list_pluck( $posts, 'ID' ), 'suppress_filters' => false ) ) ); }
    }
    $html = '<section class="prihod-news-section">' . prihod_theme_section_title( $monastery ? __( 'Новости обители', 'prihod-theme' ) : __( 'Новости прихода', 'prihod-theme' ), prihod_theme_url( 'news' ), __( 'Все новости', 'prihod-theme' ) ) . '<div class="prihod-news-grid">';
    foreach ( $posts as $post ) {
        $html .= '<article class="prihod-news-card"><a href="' . esc_url( prihod_theme_preview_url( get_permalink( $post ) ) ) . '">' . get_the_post_thumbnail( $post, 'medium_large' ) . '<div><time datetime="' . esc_attr( get_the_date( 'Y-m-d', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time><h3>' . esc_html( $post->post_title ) . '</h3><p>' . esc_html( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ?: $post->post_content ) ), 'tradition' === $scheme ? 9 : ( 'hospitality' === $scheme ? 12 : 19 ) ) ) . '</p><span>' . esc_html__( 'Читать дальше', 'prihod-theme' ) . ' →</span></div></a></article>';
    }
    return $html . '</div>' . ( ! $posts ? '<p>' . esc_html__( 'Публикаций пока нет.', 'prihod-theme' ) . '</p>' : '' ) . '</section>';
}
function prihod_theme_home_gallery( array $photos, bool $monastery ): string {
    if ( ! $photos ) { return ''; }
    if ( 'blue' === prihod_theme_scheme() ) {
        $resources = get_option( 'prihod_resources', array() ); $page = get_post( absint( $resources['photos']['id'] ?? 0 ) );
        if ( $page && 'publish' === $page->post_status ) {
            $collect = static function ( array $blocks ) use ( &$collect ): array {
                $ids = array();
                foreach ( $blocks as $block ) { if ( 'core/image' === $block['blockName'] ) { $ids[] = absint( $block['attrs']['id'] ?? 0 ); } if ( ! empty( $block['innerBlocks'] ) ) { $ids = array_merge( $ids, $collect( $block['innerBlocks'] ) ); } }
                return $ids;
            };
            $photos = array_values( array_unique( array_filter( array_merge( $photos, $collect( parse_blocks( $page->post_content ) ) ), 'wp_attachment_is_image' ) ) );
        }
        $published = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 8, 'suppress_filters' => false ) );
        $photos = array_values( array_unique( array_filter( array_merge( $photos, array_map( 'get_post_thumbnail_id', $published ) ), 'wp_attachment_is_image' ) ) );
    }
    $html = '<section class="prihod-home-gallery">' . prihod_theme_section_title( $monastery ? __( 'Жизнь обители в фотографиях', 'prihod-theme' ) : __( 'Жизнь прихода в фотографиях', 'prihod-theme' ), prihod_theme_url( 'photos' ), __( 'Все фото', 'prihod-theme' ) ) . '<div class="prihod-photo-strip">';
    foreach ( $photos as $id ) { $html .= wp_get_attachment_image( $id, 'medium_large', false, array( 'class' => $id === prihod_theme_resource_image( 'clergy' ) ? 'is-portrait' : '', 'style' => 'object-position:center 25%' ) ); }
    return $html . '</div><div class="prihod-gallery-controls"><button type="button" data-gallery-direction="-1" aria-label="' . esc_attr__( 'Назад', 'prihod-theme' ) . '">←</button><button type="button" data-gallery-direction="1" aria-label="' . esc_attr__( 'Вперёд', 'prihod-theme' ) . '">→</button></div></section>';
}
function prihod_theme_home(): string {
    $profile = prihod_theme_profile(); $options = prihod_theme_options(); $scheme = prihod_theme_scheme();
    $monastery = 'monastery' === ( $profile['type'] ?? '' );
    $about_label = $monastery ? __( 'О монастыре', 'prihod-theme' ) : __( 'О приходе', 'prihod-theme' );
    $hero = wp_get_attachment_image_url( $options['hero_image'], 'full' ) ?: get_the_post_thumbnail_url( get_the_ID(), 'full' );
    $photos = array_values( array_filter( array_map( 'absint', explode( ',', $options['gallery'] ) ), 'wp_attachment_is_image' ) );
    $card_photos = array_values( array_filter( array_merge( array( $options['about_image'] ), $photos ) ) );
    $schedule = prihod_theme_url( 'schedule' ); $about = prihod_theme_url( 'about' ); $contact = prihod_theme_url( 'contacts' );
    $title = $monastery ? __( 'Добро пожаловать в нашу обитель!', 'prihod-theme' ) : __( 'Добро пожаловать в наш приход!', 'prihod-theme' );
    if ( 'editorial' === $scheme ) { $title = __( 'Здесь начинается Главное', 'prihod-theme' ); }
    if ( 'blue' === $scheme ) { $title = $monastery ? __( 'Обитель, где каждый найдёт свой путь', 'prihod-theme' ) : __( 'Приход, где каждый найдёт свой путь', 'prihod-theme' ); }
    $html = '<section class="prihod-home-hero"' . ( $hero ? ' style="--prihod-hero-image:url(' . esc_url( $hero ) . ')"' : '' ) . '><div class="prihod-container prihod-hero-grid"><div class="prihod-hero-copy"><p class="prihod-eyebrow">' . esc_html__( 'Вера. Люди. Добрые дела.', 'prihod-theme' ) . '</p>';
    if ( 'tradition' === $scheme ) { $html .= '<h1>«' . esc_html__( 'Где двое или трое собраны во имя Моё, там Я посреди них', 'prihod-theme' ) . '»</h1><p class="prihod-hero-citation">' . esc_html__( 'Мф. 18:20', 'prihod-theme' ) . '</p>'; }
    else { $html .= '<h1>' . esc_html( $title ) . '</h1>'; }
    if ( 'classic' === $scheme ) { $html .= prihod_theme_verse(); }
    $html .= '<p class="prihod-hero-description">' . esc_html( $profile['description'] ) . '</p>';
    if ( 'hospitality' === $scheme ) { $html .= prihod_theme_verse(); }
    if ( 'tradition' !== $scheme ) {
        $html .= '<div class="prihod-actions">';
        foreach ( array( $schedule => __( 'Расписание богослужений', 'prihod-theme' ), ( 'classic' === $scheme ? $contact : $about ) => ( 'classic' === $scheme ? __( 'Как добраться', 'prihod-theme' ) : $about_label ) ) as $url => $label ) { if ( $url ) { $html .= '<a class="prihod-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' →</a>'; } }
        $html .= '</div>';
    }
    if ( 'blue' === $scheme ) { $html .= prihod_theme_verse( 'hero_quote_text', 'hero_quote_source' ); }
    $html .= ( 'editorial' === $scheme ? prihod_theme_home_content() : '' ) . '</div>';
    if ( 'classic' === $scheme && $options['hero_motto'] ) { $html .= '<aside class="prihod-classic-motto">' . esc_html( $options['hero_motto'] ) . '</aside>'; }
    if ( in_array( $scheme, array( 'hospitality', 'blue', 'editorial' ), true ) ) { $html .= prihod_theme_home_service_panel( $scheme, $schedule ); }
    if ( 'tradition' === $scheme ) { $html .= '<aside class="prihod-tradition-motto">' . prihod_theme_icon( 'cross' ) . '<p>' . esc_html__( 'Вера объединяет людей', 'prihod-theme' ) . '</p></aside>'; }
    $html .= '</div></section><div class="prihod-container">';
    if ( in_array( $scheme, array( 'classic', 'tradition' ), true ) ) { $html .= prihod_theme_home_service_panel( $scheme, $schedule ); }
    $html .= prihod_theme_home_features( $scheme, $card_photos, $about_label, $monastery );
    if ( 'editorial' !== $scheme ) {
        $html .= '<section class="prihod-about-section"><div class="prihod-about-copy">' . ( 'tradition' === $scheme ? '<p class="prihod-section-eyebrow">' . esc_html( $monastery ? __( 'Наша обитель', 'prihod-theme' ) : __( 'Наш приход', 'prihod-theme' ) ) . '</p>' : '' ) . prihod_theme_section_title( $about_label ) . '<p>' . nl2br( esc_html( $profile['description'] ) ) . '</p>' . ( $about ? '<a class="prihod-button is-outline" href="' . esc_url( $about ) . '">' . esc_html( $monastery ? __( 'Подробнее об обители', 'prihod-theme' ) : __( 'Подробнее о приходе', 'prihod-theme' ) ) . ' →</a>' : '' ) . prihod_theme_home_content() . '</div><div class="prihod-about-photo">' . wp_get_attachment_image( $options['about_image'] ?: $options['hero_image'], 'large' ) . '</div>' . ( 'blue' === $scheme ? '' : prihod_theme_verse() ) . '</section>';
    }
    $news = prihod_theme_home_news( $scheme, $monastery ); $gallery = prihod_theme_home_gallery( $photos, $monastery );
    if ( 'editorial' === $scheme ) { $html .= '<div class="prihod-editorial-news">' . $news . prihod_theme_verse() . '</div>' . $gallery; }
    elseif ( 'tradition' === $scheme ) { $html .= '<div class="prihod-tradition-stories">' . $news . $gallery . '</div>'; }
    else { $html .= $news . $gallery; }
    return $html . prihod_theme_contact_panel() . '</div>';
}
function prihod_theme_home_service_panel( string $scheme, string $schedule ): string {
    $html = '<section class="prihod-hero-service prihod-panel"><h2>' . prihod_theme_icon( 'schedule' ) . esc_html( 'editorial' === $scheme ? __( 'Ближайшие богослужения', 'prihod-theme' ) : ( 'tradition' === $scheme ? __( 'Ближайшее богослужение', 'prihod-theme' ) : __( 'Ближайшая служба', 'prihod-theme' ) ) ) . '</h2><div class="prihod-compact-services">' . prihod_theme_compact_services( 'editorial' === $scheme ? 3 : 1 ) . '</div>';
    if ( $schedule ) { $html .= '<a class="prihod-service-all" href="' . esc_url( $schedule ) . '">' . esc_html__( 'Все богослужения', 'prihod-theme' ) . ' →</a>'; }
    if ( 'blue' === $scheme ) { $html .= prihod_theme_verse(); }
    return $html . '</section>';
}

/** Keep the owner's native page content accessible without repeating the landing page. */
function prihod_theme_home_content(): string {
    $post = get_post( get_option( 'page_on_front' ) );
    if ( ! $post || ! trim( $post->post_content ) || has_block( 'prihod-theme/home', $post ) ) { return ''; }
    return '<details class="prihod-home-content"><summary>' . esc_html__( 'О жизни общины', 'prihod-theme' ) . '</summary><div class="wp-block-post-content">' . apply_filters( 'the_content', $post->post_content ) . '</div></details>';
}

function prihod_theme_resource_image( string $key ): int {
    $resources = get_option( 'prihod_resources', array() ); $id = absint( $resources[$key]['id'] ?? 0 );
    $featured = get_post_thumbnail_id( $id ); if ( $featured ) { return $featured; }
    $post = get_post( $id ); if ( ! $post ) { return 0; }
    foreach ( parse_blocks( $post->post_content ) as $block ) { if ( 'core/image' === $block['blockName'] && wp_attachment_is_image( absint( $block['attrs']['id'] ?? 0 ) ) ) { return absint( $block['attrs']['id'] ); } }
    return 0;
}
