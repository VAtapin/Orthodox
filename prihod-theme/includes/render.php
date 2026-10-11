<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_profile(): array {
    $profile = class_exists( '\Prihod\Profile' ) ? \Prihod\Profile::get() : array();
    return array_merge( array( 'name' => get_bloginfo( 'name' ), 'description' => get_bloginfo( 'description' ), 'address' => '', 'phone' => '', 'email' => '' ), array_filter( $profile, fn( $value ) => '' !== $value ) );
}
function prihod_theme_url( string $key ): string {
    $resources = get_option( 'prihod_resources', array() );
    $id = absint( $resources[ $key ]['id'] ?? 0 );
    if ( function_exists( 'pll_get_post' ) && $id ) { $translated = pll_get_post( $id ); if ( $translated ) { $id = $translated; } }
    elseif ( has_filter( 'wpml_object_id' ) && $id ) { $id = (int) apply_filters( 'wpml_object_id', $id, 'page', true ); }
    return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '';
}
function prihod_theme_languages(): string {
    if ( function_exists( 'pll_the_languages' ) ) { return '<nav class="prihod-languages" aria-label="' . esc_attr__( 'Язык', 'prihod-theme' ) . '"><ul>' . pll_the_languages( array( 'echo' => 0, 'hide_if_empty' => 1 ) ) . '</ul></nav>'; }
    $urls = prihod_theme_options()['languages'];
    $wpml = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 1 ) );
    if ( is_array( $wpml ) ) { foreach ( $wpml as $code => $lang ) { if ( isset( $lang['url'] ) ) { $urls[ $code ] = $lang['url']; } } }
    $html = ''; $current = substr( determine_locale(), 0, 2 );
    foreach ( array( 'ru' => 'RU', 'de' => 'DE', 'pl' => 'PL', 'uk' => 'UK' ) as $code => $label ) {
        if ( empty( $urls[ $code ] ) ) { continue; }
        $html .= '<a hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '" href="' . esc_url( $urls[ $code ] ) . '"' . ( $code === $current ? ' aria-current="true"' : '' ) . '>' . $label . '</a>';
    }
    return $html ? '<nav class="prihod-languages" aria-label="' . esc_attr__( 'Язык', 'prihod-theme' ) . '">' . $html . '</nav>' : '';
}
function prihod_theme_brand(): string {
    $profile = prihod_theme_profile();
    $logo = has_custom_logo() ? get_custom_logo() : '<a class="prihod-mark" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $profile['name'] ) . '"><svg viewBox="0 0 64 76" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2"><path d="M32 2v14M27 7h10M32 14c-13 11-11 17 0 19 11-2 13-8 0-19ZM23 33v37h18V33M13 70V44h10M41 44h10v26M7 70h50M28 70V52h8v18M29 41h6"/></g></svg></a>';
    if ( prihod_theme_options()['logo'] ) { $logo = '<a href="' . esc_url( home_url( '/' ) ) . '">' . wp_get_attachment_image( prihod_theme_options()['logo'], 'thumbnail', false, array( 'class' => 'custom-logo' ) ) . '</a>'; }
    return '<div class="prihod-brand">' . $logo . '<div><a class="prihod-brand-title" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $profile['short_name'] ?? $profile['name'] ) . '</a><p>' . esc_html( get_bloginfo( 'description' ) ) . '</p></div></div>';
}
function prihod_theme_block( string $name, array $attributes = array() ): string {
    if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) { return ''; }
    return render_block( array( 'blockName' => $name, 'attrs' => $attributes, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
}
function prihod_theme_header(): string {
    $profile = prihod_theme_profile(); $contacts = '';
    if ( $profile['phone'] ) { $contacts .= '<a href="' . esc_url( 'tel:' . preg_replace( '/[^+0-9]/', '', $profile['phone'] ) ) . '">' . esc_html( $profile['phone'] ) . '</a>'; }
    if ( $profile['email'] ) { $contacts .= '<a href="' . esc_url( 'mailto:' . $profile['email'] ) . '">' . esc_html( $profile['email'] ) . '</a>'; }
    if ( ! prihod_theme_options()['header_contacts'] ) { $contacts = ''; }
    $navigation = array( 'overlayMenu' => 'mobile', 'layout' => array( 'type' => 'flex', 'justifyContent' => 'right' ) );
    $resources = get_option( 'prihod_resources', array() ); $id = absint( $resources['_navigation']['id'] ?? 0 );
    if ( $id && 'wp_navigation' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) { $navigation['ref'] = $id; }
    return '<div class="prihod-header" id="prihod-header"><div class="prihod-container prihod-header-top">' . prihod_theme_brand() . '<div class="prihod-header-right"><div class="prihod-contact-strip">' . $contacts . prihod_theme_languages() . '</div>' . prihod_theme_block( 'core/navigation', $navigation ) . '</div></div></div>';
}
function prihod_theme_footer(): string {
    $policy = get_privacy_policy_url();
    return '<div class="prihod-footer"><div class="prihod-container">' . prihod_theme_brand() . '<div>' . ( $policy ? '<a href="' . esc_url( $policy ) . '">' . esc_html__( 'Политика конфиденциальности', 'prihod-theme' ) . '</a>' : '' ) . '<p>© ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( prihod_theme_profile()['name'] ) . '</p><p>' . nl2br( esc_html( prihod_theme_options()['footer_text'] ) ) . '</p></div><a class="prihod-top" href="#prihod-header" aria-label="' . esc_attr__( 'Наверх', 'prihod-theme' ) . '">↑</a></div></div>';
}
function prihod_theme_sidebar(): string {
    $html = '';
    $content = (string) get_post_field( 'post_content', get_the_ID() );
    $has_calendar = has_block( 'orthocal/calendar', $content ) || has_block( 'orthocal/today', $content ) || has_shortcode( $content, 'orthocal_calendar' ) || has_shortcode( $content, 'orthocal_today' );
    if ( ! $has_calendar && shortcode_exists( 'orthocal_today' ) ) { $html .= '<section class="prihod-panel"><h2>' . esc_html__( 'Православный календарь', 'prihod-theme' ) . '</h2>' . do_shortcode( '[orthocal_today]' ) . '</section>'; }
    $next = prihod_theme_block( 'prihod/next-services', array( 'limit' => 3 ) );
    if ( $next ) { $html .= '<section class="prihod-panel"><h2>' . esc_html__( 'Ближайшие службы', 'prihod-theme' ) . '</h2>' . $next . '</section>'; }
    $contact = prihod_theme_url( 'contacts' );
    if ( $contact ) { $html .= '<section class="prihod-panel"><h2>' . esc_html__( 'Есть вопросы?', 'prihod-theme' ) . '</h2><a class="prihod-button is-outline" href="' . esc_url( $contact ) . '">' . esc_html__( 'Связаться с нами', 'prihod-theme' ) . ' →</a></section>'; }
    return $html;
}
function prihod_theme_heading(): string {
    $image = get_the_post_thumbnail_url( get_the_ID(), 'full' );
    return '<div class="prihod-page-heading"' . ( $image ? ' style="--prihod-hero-image:url(' . esc_url( $image ) . ')"' : '' ) . '><div class="prihod-container"><a class="prihod-breadcrumb" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Главная', 'prihod-theme' ) . '</a><h1>' . esc_html( get_the_title() ) . '</h1></div></div>';
}
function prihod_theme_home(): string {
    $profile = prihod_theme_profile(); $options = prihod_theme_options();
    $monastery = 'monastery' === ( $profile['type'] ?? '' );
    $about_label = $monastery ? __( 'О монастыре', 'prihod-theme' ) : __( 'О приходе', 'prihod-theme' );
    $hero = wp_get_attachment_image_url( $options['hero_image'], 'full' ) ?: get_the_post_thumbnail_url( get_the_ID(), 'full' );
    $schedule = prihod_theme_url( 'schedule' ); $about = prihod_theme_url( 'about' );
    $settings = WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
    $next = prihod_theme_block( 'editorial' === ( $settings['custom']['prihod']['scheme'] ?? '' ) ? 'prihod/next-services' : 'prihod/next-service', array( 'limit' => 3 ) );
    $html = '<section class="prihod-home-hero"' . ( $hero ? ' style="--prihod-hero-image:url(' . esc_url( $hero ) . ')"' : '' ) . '><div class="prihod-container prihod-hero-grid"><div class="prihod-hero-copy"><p class="prihod-eyebrow">' . esc_html__( 'Вера. Люди. Добрые дела.', 'prihod-theme' ) . '</p><h1>' . esc_html( 'monastery' === ( $profile['type'] ?? '' ) ? __( 'Добро пожаловать в нашу обитель!', 'prihod-theme' ) : __( 'Добро пожаловать в наш приход!', 'prihod-theme' ) ) . '</h1><p>' . esc_html( $profile['description'] ) . '</p><div class="prihod-actions">';
    foreach ( array( $schedule => __( 'Расписание богослужений', 'prihod-theme' ), $about => $about_label ) as $url => $label ) { if ( $url ) { $html .= '<a class="prihod-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' →</a>'; } }
    $html .= '</div></div>' . ( $next ? '<div class="prihod-hero-service prihod-panel"><h2>' . esc_html__( 'Ближайшее богослужение', 'prihod-theme' ) . '</h2>' . $next . '</div>' : '' ) . '</div></section><div class="prihod-container">';
    $cards = array( 'schedule' => array( '▦', __( 'Расписание', 'prihod-theme' ), __( 'Богослужения и особые события', 'prihod-theme' ) ), 'about' => array( '♧', $about_label, __( 'История и жизнь общины', 'prihod-theme' ) ), 'news' => array( '▤', __( 'Новости', 'prihod-theme' ), __( 'События и объявления', 'prihod-theme' ) ), 'requests' => array( '◇', __( 'Вопрос священнику', 'prihod-theme' ), __( 'Задайте свой вопрос', 'prihod-theme' ) ), 'donations' => array( '♡', ( $monastery ? __( 'Помочь обители', 'prihod-theme' ) : __( 'Помочь приходу', 'prihod-theme' ) ), __( 'Поддержите добрые дела', 'prihod-theme' ) ), 'contacts' => array( '⌖', __( 'Контакты', 'prihod-theme' ), __( 'Как нас найти', 'prihod-theme' ) ) );
    $html .= '<nav class="prihod-feature-grid" aria-label="' . esc_attr__( 'Разделы сайта', 'prihod-theme' ) . '">';
    foreach ( $cards as $key => $card ) { $url = prihod_theme_url( $key ); if ( ! $url ) { continue; } $html .= '<a class="prihod-feature" href="' . esc_url( $url ) . '"><span aria-hidden="true">' . $card[0] . '</span><h2>' . esc_html( $card[1] ) . '</h2><p>' . esc_html( $card[2] ) . '</p><b aria-hidden="true">→</b></a>'; }
    $html .= '</nav><section class="prihod-about-section"><div><p class="prihod-eyebrow">' . esc_html__( 'Наша община', 'prihod-theme' ) . '</p><h2>' . esc_html( $about_label ) . '</h2><p>' . nl2br( esc_html( $profile['description'] ) ) . '</p>' . ( $about ? '<a href="' . esc_url( $about ) . '">' . esc_html( $monastery ? __( 'Подробнее об обители', 'prihod-theme' ) : __( 'Подробнее о приходе', 'prihod-theme' ) ) . ' →</a>' : '' ) . '</div>' . wp_get_attachment_image( $options['about_image'], 'large' ) . '</section>';
    $news_category = get_term_by( 'slug', 'prihod-news', 'category' );
    $posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 3, 'suppress_filters' => false, 'category' => $news_category ? $news_category->term_id : 0 ) );
    $html .= '<section class="prihod-news-section"><h2>' . esc_html( $monastery ? __( 'Новости обители', 'prihod-theme' ) : __( 'Новости прихода', 'prihod-theme' ) ) . '</h2><div class="prihod-news-grid">';
    foreach ( $posts as $post ) { $html .= '<article class="prihod-news-card"><a href="' . esc_url( get_permalink( $post ) ) . '">' . get_the_post_thumbnail( $post, 'medium_large' ) . '<div><time>' . esc_html( get_the_date( '', $post ) ) . '</time><h3>' . esc_html( $post->post_title ) . '</h3><p>' . esc_html( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ?: $post->post_content ) ), 22 ) ) . '</p><span>' . esc_html__( 'Читать дальше', 'prihod-theme' ) . ' →</span></div></a></article>'; }
    $html .= '</div>' . ( ! $posts ? '<p>' . esc_html__( 'Публикаций пока нет.', 'prihod-theme' ) . '</p>' : '' ) . '</section>';
    if ( $options['gallery'] ) { $html .= '<section><h2>' . esc_html( $monastery ? __( 'Жизнь обители в фотографиях', 'prihod-theme' ) : __( 'Жизнь прихода в фотографиях', 'prihod-theme' ) ) . '</h2><div class="prihod-photo-strip">'; foreach ( explode( ',', $options['gallery'] ) as $id ) { $html .= wp_get_attachment_image( absint( $id ), 'medium_large' ); } $html .= '</div></section>'; }
    $html .= '<section class="prihod-home-contacts"><h2>' . esc_html__( 'Как нас найти', 'prihod-theme' ) . '</h2>' . prihod_theme_block( 'prihod/contacts' );
    if ( $options['map_url'] ) { $html .= '<a class="prihod-button is-outline" href="' . esc_url( $options['map_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Открыть карту', 'prihod-theme' ) . ' →</a>'; }
    return $html . '</section></div>';
}
