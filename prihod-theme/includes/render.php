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
    return $id && 'publish' === get_post_status( $id ) ? prihod_theme_preview_url( get_permalink( $id ) ) : '';
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
    $logo = has_custom_logo() ? get_custom_logo() : '<a class="prihod-mark" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '" aria-label="' . esc_attr( $profile['name'] ) . '"><svg viewBox="0 0 64 76" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2"><path d="M32 2v14M27 7h10M32 14c-13 11-11 17 0 19 11-2 13-8 0-19ZM23 33v37h18V33M13 70V44h10M41 44h10v26M7 70h50M28 70V52h8v18M29 41h6"/></g></svg></a>';
    if ( prihod_theme_options()['logo'] ) { $logo = '<a href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . wp_get_attachment_image( prihod_theme_options()['logo'], 'thumbnail', false, array( 'class' => 'custom-logo' ) ) . '</a>'; }
    return '<div class="prihod-brand">' . $logo . '<div><a class="prihod-brand-title" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html( ! empty( $profile['short_name'] ) ? $profile['short_name'] : $profile['name'] ) . '</a><p>' . esc_html( get_bloginfo( 'description' ) ) . '</p></div></div>';
}
function prihod_theme_block( string $name, array $attributes = array() ): string {
    if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) { return ''; }
    return render_block( array( 'blockName' => $name, 'attrs' => $attributes, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
}
function prihod_theme_header(): string {
    $profile = prihod_theme_profile(); $contacts = '';
    if ( $profile['phone'] ) { $contacts .= '<a href="' . esc_url( 'tel:' . preg_replace( '/[^+0-9]/', '', $profile['phone'] ) ) . '">' . prihod_theme_icon( 'phone' ) . esc_html( $profile['phone'] ) . '</a>'; }
    if ( $profile['email'] ) { $contacts .= '<a href="' . esc_url( 'mailto:' . $profile['email'] ) . '">' . prihod_theme_icon( 'email' ) . esc_html( $profile['email'] ) . '</a>'; }
    if ( ! prihod_theme_options()['header_contacts'] ) { $contacts = ''; }
    $scheme = prihod_theme_scheme(); $options = prihod_theme_options();
    if ( 'classic' === $scheme && $options['header_motto'] ) { $contacts = '<span class="prihod-header-motto">' . esc_html( $options['header_motto'] ) . '</span>' . $contacts; }
    if ( 'editorial' === $scheme && $options['quote_text'] ) { $contacts = '<div class="prihod-header-quote">' . prihod_theme_verse() . '</div>' . $contacts; }
    $donation = prihod_theme_url( 'donations' );
    if ( $donation && in_array( $scheme, array( 'hospitality', 'editorial' ), true ) ) {
        $label = 'editorial' === $scheme ? __( 'Поддержать храм', 'prihod-theme' ) : ( 'monastery' === ( $profile['type'] ?? '' ) ? __( 'Помочь обители', 'prihod-theme' ) : __( 'Помочь приходу', 'prihod-theme' ) );
        $contacts .= '<a class="prihod-header-support prihod-button" href="' . esc_url( $donation ) . '">' . esc_html( $label ) . '</a>';
    }
    $navigation = array( 'overlayMenu' => 'mobile', 'layout' => array( 'type' => 'flex', 'justifyContent' => 'right' ) );
    $resources = get_option( 'prihod_resources', array() ); $id = absint( $resources['_navigation']['id'] ?? 0 );
    if ( $id && 'wp_navigation' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) { $navigation['ref'] = $id; }
    return '<div class="prihod-header" id="prihod-header"><div class="prihod-container prihod-header-top">' . prihod_theme_brand() . '<div class="prihod-header-right"><div class="prihod-contact-strip">' . $contacts . prihod_theme_languages() . '</div>' . prihod_theme_block( 'core/navigation', $navigation ) . '<details class="prihod-header-search"><summary aria-label="' . esc_attr__( 'Поиск', 'prihod-theme' ) . '">' . prihod_theme_icon( 'search' ) . '</summary><form role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><label>' . esc_html__( 'Поиск', 'prihod-theme' ) . '<input type="search" name="s" value="' . esc_attr( get_search_query() ) . '"></label>' . ( prihod_theme_preview_scheme() ? '<input type="hidden" name="prihod_preview" value="' . esc_attr( prihod_theme_preview_scheme() ) . '">' : '' ) . '<button type="submit">' . esc_html__( 'Найти', 'prihod-theme' ) . '</button></form></details></div></div></div>';
}
function prihod_theme_footer(): string {
    $policy = get_privacy_policy_url(); $links = '';
    foreach ( array( 'home' => __( 'Главная', 'prihod-theme' ), 'schedule' => __( 'Расписание', 'prihod-theme' ), 'about' => __( 'О приходе', 'prihod-theme' ), 'news' => __( 'Новости', 'prihod-theme' ), 'requests' => __( 'Вопрос священнику', 'prihod-theme' ), 'contacts' => __( 'Контакты', 'prihod-theme' ) ) as $key => $label ) { $url = prihod_theme_url( $key ); if ( $url ) { $links .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>'; } }
    return '<div class="prihod-footer"><div class="prihod-container">' . prihod_theme_brand() . '<nav class="prihod-footer-links" aria-label="' . esc_attr__( 'Разделы сайта', 'prihod-theme' ) . '">' . $links . '</nav><div>' . ( $policy ? '<a href="' . esc_url( $policy ) . '">' . esc_html__( 'Политика конфиденциальности', 'prihod-theme' ) . '</a>' : '' ) . '<p>© ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( prihod_theme_profile()['name'] ) . '</p><p>' . nl2br( esc_html( prihod_theme_options()['footer_text'] ) ) . '</p></div><a class="prihod-top" href="#prihod-header" aria-label="' . esc_attr__( 'Наверх', 'prihod-theme' ) . '">↑</a></div></div>';
}
function prihod_theme_sidebar(): string {
    $html = '';
    $content = (string) get_post_field( 'post_content', get_the_ID() );
    $has_calendar = has_block( 'orthocal/calendar', $content ) || has_block( 'orthocal/today', $content ) || has_shortcode( $content, 'orthocal_calendar' ) || has_shortcode( $content, 'orthocal_today' );
    if ( ! $has_calendar && shortcode_exists( 'orthocal_today' ) ) {
        $resources = get_option( 'prihod_resources', array() );
        $calendar = prihod_theme_url( 'calendar' );
        $html .= '<section class="prihod-panel"><h2>' . esc_html__( 'Православный календарь', 'prihod-theme' ) . '</h2>' . do_shortcode( '[orthocal_today compact="1" sections="fasting,icons" icon_limit="1" images="1" icons="1" show_nav="0" show_picker="0" show_copy="0" show_font_size="0" show_section_titles="0" heading="0" image_size="90" open="page" day_page="' . absint( $resources['calendar']['id'] ?? 0 ) . '"]' );
        if ( $calendar ) { $html .= '<a class="prihod-service-all" href="' . esc_url( $calendar ) . '">' . esc_html__( 'Все праздники дня', 'prihod-theme' ) . ' →</a>'; }
        $html .= '<nav class="prihod-sidebar-links">';
        foreach ( array( 'schedule' => __( 'Расписание богослужений', 'prihod-theme' ), 'prayer-notes' => __( 'Подать записку', 'prihod-theme' ), 'requests' => __( 'Вопрос священнику', 'prihod-theme' ) ) as $key => $label ) { $url = prihod_theme_url( $key ); if ( $url ) { $html .= '<a href="' . esc_url( $url ) . '">' . prihod_theme_icon( 'prayer-notes' === $key ? 'about' : $key ) . esc_html( $label ) . '</a>'; } }
        $html .= '</nav></section>';
    }
    if ( prihod_theme_request_page() ) { return $html . prihod_theme_verse(); }
    $next = prihod_theme_compact_services( 3 );
    if ( $next ) { $html .= '<section class="prihod-panel"><h2>' . esc_html__( 'Ближайшие службы', 'prihod-theme' ) . '</h2>' . $next . '<a class="prihod-service-all" href="' . esc_url( prihod_theme_url( 'schedule' ) ) . '">' . esc_html__( 'Все богослужения', 'prihod-theme' ) . ' →</a></section>'; }
    $contact = prihod_theme_url( 'contacts' );
    if ( $contact ) { $html .= '<section class="prihod-panel"><h2>' . esc_html__( 'Есть вопросы?', 'prihod-theme' ) . '</h2><a class="prihod-button is-outline" href="' . esc_url( $contact ) . '">' . esc_html__( 'Связаться с нами', 'prihod-theme' ) . ' →</a></section>'; }
    return $html . prihod_theme_verse();
}
function prihod_theme_heading(): string {
    if ( is_singular( 'post' ) ) {
        $section = prihod_theme_article_section();
        $html = '<div class="prihod-page-heading"><div class="prihod-container"><nav class="prihod-article-breadcrumb" aria-label="' . esc_attr__( 'Путь к статье', 'prihod-theme' ) . '"><a href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'prihod-theme' ) . '</a>';
        if ( ! empty( $section['url'] ) ) { $html .= '<span aria-hidden="true"> / </span><a href="' . esc_url( $section['url'] ) . '">' . esc_html( $section['name'] ) . '</a>'; }
        $html .= '<span aria-hidden="true"> / </span><span aria-current="page">' . esc_html( get_the_title() ) . '</span></nav>';
        if ( $section ) { $html .= '<p class="prihod-article-category">' . esc_html( $section['name'] ) . '</p>'; }
        return $html . '<h1>' . esc_html( get_the_title() ) . '</h1></div></div>';
    }
    $image = get_the_post_thumbnail_url( get_the_ID(), 'full' ) ?: wp_get_attachment_image_url( prihod_theme_resource_image( 'about' ) ?: prihod_theme_options()['about_image'] ?: prihod_theme_options()['hero_image'], 'full' );
    if ( prihod_theme_request_page() ) {
        $image = wp_get_attachment_image_url( prihod_theme_options()['request_image'] ?: prihod_theme_resource_image( 'clergy' ) ?: prihod_theme_resource_image( 'about' ), 'full' );
        $about = prihod_theme_url( 'about' );
        return '<div class="prihod-page-heading prihod-request-heading"' . ( $image ? ' style="--prihod-hero-image:url(' . esc_url( $image ) . ')"' : '' ) . '><div class="prihod-container"><h1>' . esc_html( get_the_title() ) . '</h1><nav aria-label="' . esc_attr__( 'Путь к странице', 'prihod-theme' ) . '"><a href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'prihod-theme' ) . '</a>' . ( $about ? '<span aria-hidden="true"> → </span><a href="' . esc_url( $about ) . '">' . esc_html( get_the_title( get_option( 'prihod_resources', array() )['about']['id'] ) ) . '</a>' : '' ) . '<span aria-hidden="true"> → </span><span aria-current="page">' . esc_html( get_the_title() ) . '</span></nav></div></div>';
    }
    if ( prihod_theme_about_page() ) {
        $visit = prihod_theme_url( 'first-visit' ) ?: prihod_theme_url( 'contacts' );
        return '<div class="prihod-page-heading prihod-about-heading"' . ( $image ? ' style="--prihod-hero-image:url(' . esc_url( $image ) . ')"' : '' ) . '><div class="prihod-container"><a class="prihod-breadcrumb" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'prihod-theme' ) . '</a><h1>' . esc_html( get_the_title() ) . '</h1><div class="prihod-about-intro">' . prihod_theme_about_intro() . '</div>' . ( $visit ? '<a class="prihod-button" href="' . esc_url( $visit ) . '">' . esc_html__( 'Впервые в храме?', 'prihod-theme' ) . ' →</a>' : '' ) . '</div></div>';
    }
    return '<div class="prihod-page-heading"' . ( $image ? ' style="--prihod-hero-image:url(' . esc_url( $image ) . ')"' : '' ) . '><div class="prihod-container"><a class="prihod-breadcrumb" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'prihod-theme' ) . '</a><h1>' . esc_html( get_the_title() ) . '</h1></div></div>';
}
function prihod_theme_request_page(): bool {
    $post = get_post(); $resources = get_option( 'prihod_resources', array() );
    return $post && $post->ID === absint( $resources['requests']['id'] ?? 0 ) && ( has_block( 'prihod/requests', $post ) || has_shortcode( $post->post_content, 'prihod_requests' ) );
}
function prihod_theme_focused_banner(): string { return prihod_theme_request_page() ? prihod_theme_heading() : ''; }
function prihod_theme_focused_heading(): string { return prihod_theme_request_page() ? '' : prihod_theme_heading(); }

/** Put the unchanged demo's guidance beside its form flow, preserving owner edits. */
function prihod_theme_focus_content( string $html ): string {
    $resources = get_option( 'prihod_resources', array() ); $post = get_post();
    if ( ! $post || ! get_post_meta( $post->ID, '_prihod_theme_demo', true ) || ! hash_equals( (string) get_post_meta( $post->ID, '_prihod_generated_hash', true ), hash( 'sha256', $post->post_title . '|' . $post->post_content ) ) ) { return $html; }
    $ids = array_map( static fn( $key ) => absint( $resources[$key]['id'] ?? 0 ), array( 'requests', 'prayer-notes', 'donations' ) );
    if ( ! in_array( $post->ID, $ids, true ) ) { return $html; }
    $intro = ''; $guidance = ''; $form = ''; $after = ''; $found = false;
    foreach ( parse_blocks( $post->post_content ) as $block ) {
        if ( 'prihod/requests' === $block['blockName'] || ( 'core/shortcode' === $block['blockName'] && preg_match( '/\[(?:gkod|kloster)_(?:form|donation)\b/', $block['innerHTML'] ) ) ) { $found = true; $form .= do_shortcode( render_block( $block ) ); }
        elseif ( $found ) { $after .= render_block( $block ); }
        elseif ( ! $intro && 'core/paragraph' === $block['blockName'] ) { $intro = render_block( $block ); }
        else { $guidance .= render_block( $block ); }
    }
    if ( ! $found ) { return $html; }
    return $intro . ( trim( $guidance ) ? '<details class="prihod-form-guidance"><summary>' . esc_html__( 'Перед заполнением', 'prihod-theme' ) . '</summary>' . $guidance . '</details>' : '' ) . $form . $after;
}
add_filter( 'render_block_core/post-content', 'prihod_theme_focus_content' );
