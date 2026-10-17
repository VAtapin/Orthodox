<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_home_features( string $scheme, string $about_label, bool $monastery, bool $has_schedule = false ): string {
    $cards = array(
        'schedule' => array( __( 'Расписание', 'georg-kloster-prihod' ), __( 'Богослужения и особые события', 'georg-kloster-prihod' ), __( 'Смотреть расписание', 'georg-kloster-prihod' ) ),
        'about' => array( $about_label, __( 'История и жизнь общины', 'georg-kloster-prihod' ), __( 'Узнать больше', 'georg-kloster-prihod' ) ),
        'news' => array( __( 'Новости', 'georg-kloster-prihod' ), __( 'События и объявления', 'georg-kloster-prihod' ), __( 'Читать новости', 'georg-kloster-prihod' ) ),
        'requests' => array( __( 'Вопрос священнику', 'georg-kloster-prihod' ), __( 'Задайте свой вопрос', 'georg-kloster-prihod' ), __( 'Задать вопрос', 'georg-kloster-prihod' ) ),
        'donations' => array( $monastery ? __( 'Помочь обители', 'georg-kloster-prihod' ) : __( 'Помочь приходу', 'georg-kloster-prihod' ), __( 'Поддержите добрые дела', 'georg-kloster-prihod' ), __( 'Узнать, как помочь', 'georg-kloster-prihod' ) ),
        'contacts' => array( __( 'Контакты', 'georg-kloster-prihod' ), __( 'Как нас найти', 'georg-kloster-prihod' ), __( 'Как добраться', 'georg-kloster-prihod' ) ),
    );
    $order = 'classic' === $scheme ? array( 'schedule', 'news', 'requests', 'donations' ) : ( in_array( $scheme, array( 'hospitality', 'blue' ), true ) ? array( 'schedule', 'about', 'news', 'requests', 'contacts' ) : array( 'schedule', 'news', 'about', 'requests', 'donations' ) );
    $photo_cards = in_array( $scheme, array( 'classic', 'editorial', 'tradition' ), true );
    $html = '<nav class="prihod-feature-grid' . ( $has_schedule ? ' has-schedule-panel' : '' ) . '" aria-label="' . esc_attr__( 'Разделы сайта', 'georg-kloster-prihod' ) . '">'; $has_cards = false;
    foreach ( $order as $key ) {
        if ( $has_schedule && 'schedule' === $key ) { continue; }
        $url = prihod_theme_url( $key ); if ( ! $url ) { continue; } $has_cards = true;
        $card = $cards[$key]; $photo = prihod_theme_resource_image( $key );
        $support = ( 'editorial' === $scheme && 'donations' === $key || 'tradition' === $scheme && in_array( $key, array( 'requests', 'donations' ), true ) ) && ! $photo;
        $html .= '<div class="prihod-feature' . ( $support ? ( 'editorial' === $scheme ? ' prihod-feature-support' : ' prihod-feature-symbol' ) : ( $photo_cards ? ' has-photo' : '' ) ) . '">' . ( $support ? '<a class="prihod-feature-support-symbol" href="' . esc_url( $url ) . '">' . prihod_theme_icon( 'requests' === $key ? 'requests' : 'support' ) . '</a>' : ( $photo_cards && $photo ? prihod_theme_photo( $photo, 'medium_large', array( 'class' => 'prihod-feature-photo' . ( 'requests' === $key ? ' is-portrait' : '' ), 'style' => 'object-position:center 25%' ), '', $url ) : '' ) ) . '<a class="prihod-feature-copy" href="' . esc_url( $url ) . '">' . prihod_theme_icon( $key ) . '<div><h2>' . esc_html( $card[0] ) . '</h2><p>' . esc_html( $card[1] ) . '</p><span class="prihod-feature-link">' . esc_html( $card[2] ) . ' →</span></div></a></div>';
    }
    return $has_cards ? $html . '</nav>' : '';
}
function prihod_theme_home_topic_links(): string {
    $links = '';
    foreach ( array( 'first-visit', 'sermons' ) as $key ) {
        $id = prihod_theme_resource_id( $key );
        if ( ! $id || 'publish' !== get_post_status( $id ) ) { continue; }
        $links .= '<a href="' . esc_url( prihod_theme_preview_url( get_permalink( $id ) ) ) . '"><span>' . esc_html( get_the_title( $id ) ) . '</span><span aria-hidden="true">→</span></a>';
    }
    return $links ? '<nav class="prihod-home-topic-links" aria-label="' . esc_attr__( 'Разделы сайта', 'georg-kloster-prihod' ) . '">' . $links . '</nav>' : '';
}
function prihod_theme_home_news( string $scheme, bool $monastery ): string {
    $category = get_term_by( 'slug', 'prihod-news', 'category' ) ?: get_term_by( 'slug', 'news', 'category' ); $count = 'hospitality' === $scheme ? 4 : 3;
    $posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $count, 'category' => $category ? $category->term_id : 0, 'suppress_filters' => false ) );
    if ( count( $posts ) < $count ) {
        $sermons = get_term_by( 'slug', 'prihod-sermons', 'category' );
        if ( $sermons ) { $posts = array_merge( $posts, get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $count - count( $posts ), 'category' => $sermons->term_id, 'post__not_in' => wp_list_pluck( $posts, 'ID' ), 'suppress_filters' => false ) ) ); }
    }
    $html = '<section class="prihod-news-section">' . prihod_theme_section_title( $monastery ? __( 'Новости обители', 'georg-kloster-prihod' ) : __( 'Новости прихода', 'georg-kloster-prihod' ), prihod_theme_url( 'news' ), __( 'Все новости', 'georg-kloster-prihod' ) ) . '<div class="prihod-news-grid">';
    foreach ( $posts as $post ) {
        $html .= '<article class="prihod-news-card">' . prihod_theme_post_photo( $post, 'medium_large', prihod_theme_preview_url( get_permalink( $post ) ) ) . '<a class="prihod-news-copy" href="' . esc_url( prihod_theme_preview_url( get_permalink( $post ) ) ) . '"><div><time datetime="' . esc_attr( get_the_date( 'Y-m-d', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time><h3>' . esc_html( $post->post_title ) . '</h3><p>' . esc_html( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ?: $post->post_content ) ), 'tradition' === $scheme ? 9 : ( 'hospitality' === $scheme ? 12 : 19 ) ) ) . '</p><span>' . esc_html__( 'Читать дальше', 'georg-kloster-prihod' ) . ' →</span></div></a></article>';
    }
    return $html . '</div>' . ( ! $posts ? '<p>' . esc_html__( 'Публикаций пока нет.', 'georg-kloster-prihod' ) . '</p>' : '' ) . '</section>';
}
function prihod_theme_home(): string {
    $profile = prihod_theme_profile(); $options = georg_kloster_prihod_options(); $scheme = prihod_theme_scheme();
    $monastery = 'monastery' === ( $profile['type'] ?? '' );
    $about_label = $monastery ? __( 'О монастыре', 'georg-kloster-prihod' ) : __( 'О приходе', 'georg-kloster-prihod' );
    $hero = wp_get_attachment_image_url( $options['hero_image'], 'full' ) ?: get_the_post_thumbnail_url( get_the_ID(), 'full' );
    $schedule = prihod_theme_url( 'schedule' ); $about = prihod_theme_url( 'about' ); $contact = prihod_theme_url( 'contacts' );
    $schedule_class = prihod_theme_schedule_class();
    $has_schedule = (bool) ( $schedule_class && $schedule_class::next( 0, 1 ) );
    $title = $monastery ? __( 'Добро пожаловать в нашу обитель!', 'georg-kloster-prihod' ) : __( 'Добро пожаловать в наш приход!', 'georg-kloster-prihod' );
    if ( 'editorial' === $scheme ) { $title = __( 'Здесь начинается Главное', 'georg-kloster-prihod' ); }
    if ( 'blue' === $scheme ) { $title = $monastery ? __( 'Обитель, где каждый найдёт свой путь', 'georg-kloster-prihod' ) : __( 'Приход, где каждый найдёт свой путь', 'georg-kloster-prihod' ); }
    $html = '<section class="prihod-home-hero"' . ( $hero ? ' style="--prihod-hero-image:url(' . esc_url( $hero ) . ')"' : '' ) . '><div class="prihod-container prihod-hero-grid"><div class="prihod-hero-copy"><p class="prihod-eyebrow">' . esc_html__( 'Вера. Люди. Добрые дела.', 'georg-kloster-prihod' ) . '</p>';
    if ( 'tradition' === $scheme ) { $html .= '<h1>«' . esc_html__( 'Где двое или трое собраны во имя Моё, там Я посреди них', 'georg-kloster-prihod' ) . '»</h1><p class="prihod-hero-citation">' . esc_html__( 'Мф. 18:20', 'georg-kloster-prihod' ) . '</p>'; }
    else { $html .= '<h1>' . esc_html( $title ) . '</h1>'; }
    if ( 'classic' === $scheme ) { $html .= prihod_theme_verse(); }
    $html .= '<p class="prihod-hero-description">' . esc_html( $profile['description'] ) . '</p>';
    if ( 'hospitality' === $scheme ) { $html .= prihod_theme_verse(); }
    if ( 'tradition' !== $scheme ) {
        $html .= '<div class="prihod-actions">';
        $actions = array();
        $actions[$schedule] = __( 'Расписание богослужений', 'georg-kloster-prihod' );
        $actions['classic' === $scheme ? $contact : $about] = 'classic' === $scheme ? __( 'Как добраться', 'georg-kloster-prihod' ) : $about_label;
        foreach ( $actions as $url => $label ) { if ( $url ) { $html .= '<a class="prihod-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' →</a>'; } }
        $html .= '</div>';
    }
    if ( 'blue' === $scheme ) { $html .= prihod_theme_verse( 'hero_quote_text', 'hero_quote_source' ); }
    $html .= '</div>';
    if ( 'classic' === $scheme && $options['hero_motto'] ) { $html .= '<aside class="prihod-classic-motto">' . esc_html( $options['hero_motto'] ) . '</aside>'; }
    if ( $has_schedule && in_array( $scheme, array( 'hospitality', 'blue', 'editorial' ), true ) ) { $html .= prihod_theme_home_service_panel( $scheme, $schedule ); }
    if ( 'tradition' === $scheme ) { $html .= '<aside class="prihod-tradition-motto">' . prihod_theme_icon( 'cross' ) . '<p>' . esc_html__( 'Вера объединяет людей', 'georg-kloster-prihod' ) . '</p></aside>'; }
    $html .= '</div></section><div class="prihod-container">';
    if ( $has_schedule && in_array( $scheme, array( 'classic', 'tradition' ), true ) ) { $html .= prihod_theme_home_service_panel( $scheme, $schedule ); }
    $html .= prihod_theme_home_features( $scheme, $about_label, $monastery, $has_schedule ) . prihod_theme_home_topic_links();
    if ( 'editorial' !== $scheme ) {
        $html .= '<section class="prihod-about-section"><div class="prihod-about-copy">' . ( 'tradition' === $scheme ? '<p class="prihod-section-eyebrow">' . esc_html( $monastery ? __( 'Наша обитель', 'georg-kloster-prihod' ) : __( 'Наш приход', 'georg-kloster-prihod' ) ) . '</p>' : '' ) . prihod_theme_section_title( $about_label ) . '<p>' . nl2br( esc_html( $profile['description'] ) ) . '</p>' . ( $about ? '<a class="prihod-button is-outline" href="' . esc_url( $about ) . '">' . esc_html( $monastery ? __( 'Подробнее об обители', 'georg-kloster-prihod' ) : __( 'Подробнее о приходе', 'georg-kloster-prihod' ) ) . ' →</a>' : '' ) . '</div><div class="prihod-about-photo">' . prihod_theme_photo( prihod_theme_resource_image( 'about' ) ?: $options['hero_image'], 'large' ) . '</div>' . ( 'blue' === $scheme ? '' : prihod_theme_verse() ) . '</section>';
    }
    $news = prihod_theme_home_news( $scheme, $monastery );
    if ( 'editorial' === $scheme ) { $html .= '<div class="prihod-editorial-news">' . $news . prihod_theme_verse() . '</div>'; }
    elseif ( 'tradition' === $scheme ) { $html .= '<div class="prihod-tradition-stories">' . $news . '</div>'; }
    else { $html .= $news; }
    return $html . prihod_theme_home_content() . prihod_theme_contact_panel() . '</div>';
}
function prihod_theme_home_service_panel( string $scheme, string $schedule ): string {
    $html = '<section class="prihod-hero-service prihod-panel"><h2>' . prihod_theme_icon( 'schedule' ) . esc_html( 'editorial' === $scheme ? __( 'Ближайшие богослужения', 'georg-kloster-prihod' ) : ( 'tradition' === $scheme ? __( 'Ближайшее богослужение', 'georg-kloster-prihod' ) : __( 'Ближайшая служба', 'georg-kloster-prihod' ) ) ) . '</h2><div class="prihod-compact-services">' . prihod_theme_compact_services( 'editorial' === $scheme ? 3 : 1 ) . '</div>';
    if ( $schedule ) { $html .= '<a class="prihod-service-all" href="' . esc_url( $schedule ) . '">' . esc_html__( 'Все богослужения', 'georg-kloster-prihod' ) . ' →</a>'; }
    if ( 'blue' === $scheme ) { $html .= prihod_theme_verse(); }
    return $html . '</section>';
}

/** Display the owner's page content and any plugin blocks without a collapsed panel. */
function prihod_theme_home_content(): string {
    $post = get_post( get_queried_object_id() ?: get_option( 'page_on_front' ) );
    if ( ! $post || ! trim( $post->post_content ) ) { return ''; }
    return '<section class="prihod-home-content wp-block-post-content">' . apply_filters( 'the_content', $post->post_content ) . '</section>';
}

function prihod_theme_resource_image( string $key ): int {
    $id = prihod_theme_resource_id( $key );
    $featured = get_post_thumbnail_id( $id ); if ( $featured ) { return $featured; }
    $post = get_post( $id ); if ( ! $post ) { return 0; }
    foreach ( parse_blocks( $post->post_content ) as $block ) { if ( 'core/image' === $block['blockName'] && wp_attachment_is_image( absint( $block['attrs']['id'] ?? 0 ) ) ) { return absint( $block['attrs']['id'] ); } }
    return 0;
}
