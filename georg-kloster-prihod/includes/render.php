<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_profile(): array {
    $profile = class_exists( '\GeorgKlosterPrihodCore\Profile' ) ? \GeorgKlosterPrihodCore\Profile::get() : ( class_exists( '\Prihod\Profile' ) ? \Prihod\Profile::get() : array() );
    return array_merge( array( 'name' => get_bloginfo( 'name' ), 'description' => get_bloginfo( 'description' ), 'address' => '', 'phone' => '', 'email' => '' ), array_filter( $profile, fn( $value ) => '' !== $value ) );
}
function prihod_theme_tagline(): string {
    $tagline = get_bloginfo( 'description' );
    $translated = apply_filters( 'wpml_translate_single_string', false, 'georg-kloster-prihod', 'site-tagline' );
    return is_string( $translated ) && '' !== $translated ? $translated : $tagline;
}
function prihod_theme_resources(): array {
    $resources = apply_filters( 'georg_kloster_prihod_resources', array() );
    return is_array( $resources ) ? $resources : array();
}
function prihod_theme_resource_id( string $key ): int {
    $resources = prihod_theme_resources();
    $id = absint( $resources[ $key ]['id'] ?? 0 );
    if ( ! $id ) { return 0; }
    if ( function_exists( 'pll_get_post' ) ) {
        $translated = pll_get_post( $id );
        return $translated ? (int) $translated : $id;
    }
    if ( has_filter( 'wpml_object_id' ) ) {
        return (int) apply_filters( 'wpml_object_id', $id, get_post_type( $id ) ?: 'page', true );
    }
    return $id;
}
function prihod_theme_url( string $key ): string {
    $id = prihod_theme_resource_id( $key );
    return $id && 'publish' === get_post_status( $id ) ? prihod_theme_preview_url( get_permalink( $id ) ) : '';
}
function prihod_theme_languages(): string {
    if ( function_exists( 'pll_the_languages' ) ) { return '<nav class="prihod-languages" aria-label="' . esc_attr__( 'Язык', 'georg-kloster-prihod' ) . '"><ul>' . pll_the_languages( array( 'echo' => 0, 'hide_if_empty' => 1 ) ) . '</ul></nav>'; }
    $urls = georg_kloster_prihod_options()['languages'];
    $wpml = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 1 ) );
    if ( is_array( $wpml ) ) { foreach ( $wpml as $code => $lang ) { if ( isset( $lang['url'] ) ) { $urls[ $code ] = $lang['url']; } } }
    $html = ''; $current = substr( determine_locale(), 0, 2 );
    foreach ( array( 'ru' => 'RU', 'de' => 'DE', 'pl' => 'PL', 'uk' => 'UK' ) as $code => $label ) {
        if ( empty( $urls[ $code ] ) ) { continue; }
        $html .= '<a hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '" href="' . esc_url( $urls[ $code ] ) . '"' . ( $code === $current ? ' aria-current="true"' : '' ) . '>' . $label . '</a>';
    }
    return $html ? '<nav class="prihod-languages" aria-label="' . esc_attr__( 'Язык', 'georg-kloster-prihod' ) . '">' . $html . '</nav>' : '';
}
function prihod_theme_brand(): string {
    $profile = prihod_theme_profile();
    $options = georg_kloster_prihod_options();
    $logo = '<a class="prihod-mark" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '" aria-label="' . esc_attr( $profile['name'] ) . '"><svg viewBox="0 0 64 76" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2"><path d="M32 2v14M27 7h10M32 14c-13 11-11 17 0 19 11-2 13-8 0-19ZM23 33v37h18V33M13 70V44h10M41 44h10v26M7 70h50M28 70V52h8v18M29 41h6"/></g></svg></a>';
    $logo_id = $options['logo_removed'] ? 0 : ( $options['logo'] ?: get_theme_mod( 'custom_logo' ) );
    if ( $logo_id ) { $logo = '<a href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'custom-logo', 'alt' => $profile['name'] ) ) . '</a>'; }
    $text = '<div><a class="prihod-brand-title" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html( ! empty( $profile['short_name'] ) ? $profile['short_name'] : $profile['name'] ) . '</a><p>' . esc_html( prihod_theme_tagline() ) . '</p></div>';
    $hide = $options['logo_hide_title'] && ( $logo_id || ! $options['logo_show'] );
    $visible_logo = $options['logo_show'] ? $logo : '';
    // Keep an accessible home link even when both display controls are disabled.
    if ( ! $visible_logo && $hide ) { $visible_logo = '<a class="prihod-brand-home" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'georg-kloster-prihod' ) . '</a>'; }
    $meta = $logo_id ? wp_get_attachment_metadata( $logo_id ) : array();
    $wide = $meta && ( $meta['width'] ?? 0 ) > 1.5 * ( $meta['height'] ?? 1 );
    $class = $options['logo_show'] && $logo_id ? ' prihod-brand-custom' . ( $wide ? ' prihod-brand-wide' : ' prihod-brand-square' ) : '';
    if ( $wide && $hide && $options['logo_show'] ) { $class .= ' prihod-brand-logo-only'; }
    return '<div class="prihod-brand' . $class . '" style="--prihod-logo-width:' . absint( $options['logo_width'] ) . 'px">' . $visible_logo . ( $hide ? '' : $text ) . '</div>';
}
function prihod_theme_block( string $name, array $attributes = array() ): string {
    if ( ! WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) { return ''; }
    return render_block( array( 'blockName' => $name, 'attrs' => $attributes, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
}
function prihod_theme_header(): string {
    $profile = prihod_theme_profile(); $contacts = '';
    if ( $profile['phone'] ) { $contacts .= '<a href="' . esc_url( 'tel:' . preg_replace( '/[^+0-9]/', '', $profile['phone'] ) ) . '">' . prihod_theme_icon( 'phone' ) . esc_html( $profile['phone'] ) . '</a>'; }
    if ( $profile['email'] ) { $contacts .= '<a href="' . esc_url( 'mailto:' . $profile['email'] ) . '">' . prihod_theme_icon( 'email' ) . esc_html( $profile['email'] ) . '</a>'; }
    if ( ! georg_kloster_prihod_options()['header_contacts'] ) { $contacts = ''; }
    $scheme = prihod_theme_scheme(); $options = georg_kloster_prihod_options();
    if ( 'classic' === $scheme && $options['header_motto'] ) { $contacts = '<span class="prihod-header-motto">' . esc_html( $options['header_motto'] ) . '</span>' . $contacts; }
    if ( 'editorial' === $scheme && $options['quote_text'] ) { $contacts = '<div class="prihod-header-quote">' . prihod_theme_verse() . '</div>' . $contacts; }
    $donation = prihod_theme_url( 'donations' );
    if ( $donation && in_array( $scheme, array( 'hospitality', 'editorial' ), true ) ) {
        $label = 'editorial' === $scheme ? __( 'Поддержать храм', 'georg-kloster-prihod' ) : ( 'monastery' === ( $profile['type'] ?? '' ) ? __( 'Помочь обители', 'georg-kloster-prihod' ) : __( 'Помочь приходу', 'georg-kloster-prihod' ) );
        $contacts .= '<a class="prihod-header-support prihod-button" href="' . esc_url( $donation ) . '">' . esc_html( $label ) . '</a>';
    }
    $navigation = array( 'overlayMenu' => 'mobile', 'layout' => array( 'type' => 'flex', 'justifyContent' => 'right' ) );
    $resources = prihod_theme_resources(); $id = absint( $resources['_navigation']['id'] ?? 0 );
    if ( $id && 'wp_navigation' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) { $navigation['ref'] = $id; }
    $classic_menu = has_nav_menu( 'primary' );
    $visual_menu = $classic_menu && 'visual' === $options['menu_style'];
    if ( $visual_menu ) {
        $menu = wp_nav_menu( array( 'theme_location' => 'primary', 'container' => 'nav', 'container_class' => 'prihod-visual-navigation', 'container_aria_label' => __( 'Разделы сайта', 'georg-kloster-prihod' ), 'menu_id' => 'prihod-visual-menu', 'menu_class' => 'prihod-visual-list', 'prihod_visual' => true, 'echo' => false ) );
        $navigation_html = '<details class="prihod-visual-menu" style="--prihod-menu-columns:' . absint( $options['menu_columns'] ) . '"><summary aria-label="' . esc_attr__( 'Разделы сайта', 'georg-kloster-prihod' ) . '"><span class="prihod-menu-icon" aria-hidden="true"></span><span>' . esc_html__( 'Разделы сайта', 'georg-kloster-prihod' ) . '</span><span class="prihod-menu-chevron" aria-hidden="true">⌄</span></summary><div class="prihod-visual-panel"><div class="prihod-visual-panel-head"><strong>' . esc_html__( 'Разделы сайта', 'georg-kloster-prihod' ) . '</strong><button type="button" class="prihod-visual-close" aria-label="' . esc_attr__( 'Закрыть меню', 'georg-kloster-prihod' ) . '">×</button></div>' . $menu . '</div></details>';
    } elseif ( $classic_menu ) {
        $navigation_html = wp_nav_menu( array( 'theme_location' => 'primary', 'container' => 'nav', 'container_class' => 'wp-block-navigation prihod-classic-navigation prihod-desktop-navigation', 'menu_id' => 'prihod-desktop-menu', 'menu_class' => 'wp-block-navigation__container', 'echo' => false ) );
        $mobile_navigation = wp_nav_menu( array( 'theme_location' => 'primary', 'container' => 'nav', 'container_class' => 'wp-block-navigation prihod-classic-navigation prihod-mobile-navigation', 'container_aria_label' => __( 'Меню', 'georg-kloster-prihod' ), 'menu_id' => 'prihod-mobile-menu', 'menu_class' => 'wp-block-navigation__container', 'echo' => false ) );
        $mobile_navigation = preg_replace( '/\s+id="menu-item-\d+"/', '', $mobile_navigation );
        $navigation_html .= '<details class="prihod-mobile-menu"><summary aria-label="' . esc_attr__( 'Меню', 'georg-kloster-prihod' ) . '"><span class="prihod-menu-icon" aria-hidden="true"></span><span class="prihod-menu-label">' . esc_html__( 'Меню', 'georg-kloster-prihod' ) . '</span></summary><div class="prihod-mobile-panel">' . $mobile_navigation . prihod_theme_languages() . '</div></details>';
    } else {
        $navigation_html = isset( $navigation['ref'] ) ? prihod_theme_block( 'core/navigation', $navigation ) : do_blocks( '<!-- wp:navigation {"overlayMenu":"mobile","layout":{"type":"flex","justifyContent":"right"}} --><!-- wp:page-list /--><!-- /wp:navigation -->' );
    }
    $search_form = get_search_form( array( 'echo' => false ) );
    if ( prihod_theme_preview_scheme() ) {
        $search_form = str_replace( '</form>', '<input type="hidden" name="prihod_preview" value="' . esc_attr( prihod_theme_preview_scheme() ) . '"></form>', $search_form );
    }
    $search_html = '<details class="prihod-header-search"><summary aria-label="' . esc_attr__( 'Поиск', 'georg-kloster-prihod' ) . '">' . prihod_theme_icon( 'search' ) . '</summary>' . $search_form . '</details>';
    $navigation_html = $visual_menu ? '<div class="prihod-menu-tools">' . $navigation_html . $search_html . '</div>' : $navigation_html . $search_html;
    return '<div class="prihod-header' . ( $classic_menu ? ' prihod-has-classic-menu' : '' ) . ( $visual_menu ? ' prihod-has-visual-menu' : '' ) . '" id="prihod-header"><div class="prihod-container prihod-header-top">' . prihod_theme_brand() . '<div class="prihod-header-right"><div class="prihod-contact-strip">' . $contacts . prihod_theme_languages() . '</div>' . $navigation_html . '</div></div></div>';
}
function prihod_theme_footer(): string {
    $policy = get_privacy_policy_url(); $links = '';
    $about_label = 'monastery' === ( prihod_theme_profile()['type'] ?? '' ) ? __( 'О монастыре', 'georg-kloster-prihod' ) : __( 'О приходе', 'georg-kloster-prihod' );
    foreach ( array( 'home' => __( 'Главная', 'georg-kloster-prihod' ), 'schedule' => __( 'Расписание', 'georg-kloster-prihod' ), 'about' => $about_label, 'first-visit' => '', 'news' => __( 'Новости', 'georg-kloster-prihod' ), 'sermons' => '', 'requests' => __( 'Вопрос священнику', 'georg-kloster-prihod' ), 'contacts' => __( 'Контакты', 'georg-kloster-prihod' ) ) as $key => $label ) { $url = prihod_theme_url( $key ); if ( $url ) { $links .= '<a href="' . esc_url( $url ) . '">' . esc_html( $label ?: get_the_title( prihod_theme_resource_id( $key ) ) ) . '</a>'; } }
    return '<div class="prihod-footer"><div class="prihod-container">' . prihod_theme_brand() . '<nav class="prihod-footer-links" aria-label="' . esc_attr__( 'Разделы сайта', 'georg-kloster-prihod' ) . '">' . $links . '</nav><div>' . ( $policy ? '<a href="' . esc_url( $policy ) . '">' . esc_html__( 'Политика конфиденциальности', 'georg-kloster-prihod' ) . '</a>' : '' ) . '<p>© ' . esc_html( wp_date( 'Y' ) ) . ' ' . esc_html( prihod_theme_profile()['name'] ) . '</p><p>' . nl2br( esc_html( georg_kloster_prihod_options()['footer_text'] ) ) . '</p></div><a class="prihod-top" href="#prihod-header" aria-label="' . esc_attr__( 'Наверх', 'georg-kloster-prihod' ) . '">↑</a></div></div>';
}
function prihod_theme_sidebar(): string {
    $html = '';
    $content = (string) get_post_field( 'post_content', get_the_ID() );
    $has_calendar = has_block( 'orthocal/calendar', $content ) || has_block( 'orthocal/today', $content ) || has_shortcode( $content, 'orthocal_calendar' ) || has_shortcode( $content, 'orthocal_today' );
    if ( ! $has_calendar && shortcode_exists( 'orthocal_today' ) ) {
        $calendar = prihod_theme_url( 'calendar' );
        $html .= '<section class="prihod-panel"><h2>' . esc_html__( 'Православный календарь', 'georg-kloster-prihod' ) . '</h2>' . do_shortcode( '[orthocal_today compact="1" sections="fasting,icons" icon_limit="1" images="1" icons="1" show_nav="0" show_picker="0" show_copy="0" show_font_size="0" show_section_titles="0" heading="0" image_size="90" open="page" day_page="' . prihod_theme_resource_id( 'calendar' ) . '"]' );
        if ( $calendar ) { $html .= '<a class="prihod-service-all" href="' . esc_url( $calendar ) . '">' . esc_html__( 'Все праздники дня', 'georg-kloster-prihod' ) . ' →</a>'; }
        $html .= '<nav class="prihod-sidebar-links">';
        foreach ( array( 'schedule' => __( 'Расписание богослужений', 'georg-kloster-prihod' ), 'prayer-notes' => __( 'Подать записку', 'georg-kloster-prihod' ), 'requests' => __( 'Вопрос священнику', 'georg-kloster-prihod' ) ) as $key => $label ) { $url = prihod_theme_url( $key ); if ( $url ) { $html .= '<a href="' . esc_url( $url ) . '">' . prihod_theme_icon( 'prayer-notes' === $key ? 'about' : $key ) . esc_html( $label ) . '</a>'; } }
        $html .= '</nav></section>';
    }
    if ( prihod_theme_request_page() ) { return $html . prihod_theme_verse(); }
    $next = prihod_theme_compact_services( 3 );
    if ( $next ) { $html .= '<section class="prihod-panel"><h2>' . esc_html__( 'Ближайшие службы', 'georg-kloster-prihod' ) . '</h2>' . $next . '<a class="prihod-service-all" href="' . esc_url( prihod_theme_url( 'schedule' ) ) . '">' . esc_html__( 'Все богослужения', 'georg-kloster-prihod' ) . ' →</a></section>'; }
    $contact = prihod_theme_url( 'contacts' );
    if ( $contact ) { $html .= '<section class="prihod-panel"><h2>' . esc_html__( 'Есть вопросы?', 'georg-kloster-prihod' ) . '</h2><a class="prihod-button is-outline" href="' . esc_url( $contact ) . '">' . esc_html__( 'Связаться с нами', 'georg-kloster-prihod' ) . ' →</a></section>'; }
    return $html . prihod_theme_verse();
}
function prihod_theme_heading(): string {
    if ( is_singular( 'post' ) ) {
        $section = prihod_theme_article_section();
        $html = '<div class="prihod-page-heading"><div class="prihod-container"><nav class="prihod-article-breadcrumb" aria-label="' . esc_attr__( 'Путь к статье', 'georg-kloster-prihod' ) . '"><a href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'georg-kloster-prihod' ) . '</a>';
        if ( ! empty( $section['url'] ) ) { $html .= '<span aria-hidden="true"> / </span><a href="' . esc_url( $section['url'] ) . '">' . esc_html( $section['name'] ) . '</a>'; }
        $html .= '<span aria-hidden="true"> / </span><span aria-current="page">' . esc_html( get_the_title() ) . '</span></nav>';
        if ( $section ) { $html .= '<p class="prihod-article-category">' . esc_html( $section['name'] ) . '</p>'; }
        return $html . '<h1>' . esc_html( get_the_title() ) . '</h1></div></div>';
    }
    $image = get_the_post_thumbnail_url( get_the_ID(), 'full' ) ?: wp_get_attachment_image_url( georg_kloster_prihod_options()['hero_image'], 'full' );
    if ( get_the_ID() === prihod_theme_resource_id( 'schedule' ) ) {
        $image = wp_get_attachment_image_url( georg_kloster_prihod_options()['hero_image'], 'full' ) ?: $image;
    }
    if ( prihod_theme_request_page() ) {
        $image = get_the_post_thumbnail_url( get_the_ID(), 'full' ) ?: wp_get_attachment_image_url( georg_kloster_prihod_options()['hero_image'], 'full' );
        $about = prihod_theme_url( 'about' );
        return '<div class="prihod-page-heading prihod-request-heading"><div class="prihod-container"><div class="prihod-request-heading-copy"><h1>' . esc_html( get_the_title() ) . '</h1><nav aria-label="' . esc_attr__( 'Путь к странице', 'georg-kloster-prihod' ) . '"><a href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'georg-kloster-prihod' ) . '</a>' . ( $about ? '<span aria-hidden="true"> → </span><a href="' . esc_url( $about ) . '">' . esc_html( get_the_title( prihod_theme_resource_id( 'about' ) ) ) . '</a>' : '' ) . '<span aria-hidden="true"> → </span><span aria-current="page">' . esc_html( get_the_title() ) . '</span></nav></div>' . ( $image ? '<img class="prihod-request-photo" src="' . esc_url( $image ) . '" alt="" width="320" height="180" decoding="async">' : '' ) . '</div></div>';
    }
    if ( prihod_theme_about_page() ) {
        $visit = prihod_theme_url( 'first-visit' ) ?: prihod_theme_url( 'contacts' );
        return '<div class="prihod-page-heading prihod-about-heading"' . ( $image ? ' style="--prihod-hero-image:url(' . esc_url( $image ) . ')"' : '' ) . '><div class="prihod-container"><a class="prihod-breadcrumb" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'georg-kloster-prihod' ) . '</a><h1>' . esc_html( get_the_title() ) . '</h1><div class="prihod-about-intro">' . prihod_theme_about_intro() . '</div>' . ( $visit ? '<a class="prihod-button" href="' . esc_url( $visit ) . '">' . esc_html__( 'Впервые в храме?', 'georg-kloster-prihod' ) . ' →</a>' : '' ) . '</div></div>';
    }
    return '<div class="prihod-page-heading"' . ( $image ? ' style="--prihod-hero-image:url(' . esc_url( $image ) . ')"' : '' ) . '><div class="prihod-container"><a class="prihod-breadcrumb" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'georg-kloster-prihod' ) . '</a><h1>' . esc_html( get_the_title() ) . '</h1></div></div>';
}
function prihod_theme_request_page(): bool {
    $post = get_post();
    return $post && $post->ID === prihod_theme_resource_id( 'requests' );
}
function prihod_theme_focused_banner(): string { return prihod_theme_request_page() ? prihod_theme_heading() : ''; }
function prihod_theme_focused_heading(): string { return prihod_theme_request_page() ? '' : prihod_theme_heading(); }
