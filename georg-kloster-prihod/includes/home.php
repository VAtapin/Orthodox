<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_home_features( string $scheme, string $about_label, bool $monastery, bool $has_schedule = false ): string {
    $home = prihod_theme_home_options(); $order = $home['card_order'];
    $photo_cards = $home['cards_show_images'];
    $html = '<nav class="prihod-feature-grid' . ( $has_schedule ? ' has-schedule-panel' : '' ) . '" aria-label="' . esc_attr__( 'Разделы сайта', 'georg-kloster-prihod' ) . '">'; $has_cards = false;
    foreach ( $order as $key ) {
        if ( ! $home['cards'][$key]['enabled'] || $has_schedule && $home['hide_schedule_card'] && 'schedule' === $key ) { continue; }
        $url = prihod_theme_home_url( $key ); if ( ! $url ) { continue; } $has_cards = true;
        $card = array( get_the_title( prihod_theme_home_resource_id( $key ) ), '', '' ); $photo = prihod_theme_resource_image( $key, true );
        foreach ( array( 'title', 'description', 'button' ) as $index => $field ) { $card[$index] = prihod_theme_home_text( $home['cards'][$key][$field], 'card-' . $key . '-' . $field, $card[$index] ); }
        $support = ( 'editorial' === $scheme && 'donations' === $key || 'tradition' === $scheme && in_array( $key, array( 'requests', 'donations' ), true ) ) && ! $photo;
        $html .= '<div class="prihod-feature' . ( $support ? ( 'editorial' === $scheme ? ' prihod-feature-support' : ' prihod-feature-symbol' ) : ( $photo_cards ? ' has-photo' : '' ) ) . '">' . ( $support ? '<a class="prihod-feature-support-symbol" href="' . esc_url( $url ) . '">' . prihod_theme_icon( 'requests' === $key ? 'requests' : 'support' ) . '</a>' : ( $photo_cards && $photo ? prihod_theme_photo( $photo, 'medium_large', array( 'class' => 'prihod-feature-photo' . ( 'requests' === $key ? ' is-portrait' : '' ), 'style' => 'object-position:center 25%' ), '', $url ) : '' ) ) . '<a class="prihod-feature-copy" href="' . esc_url( $url ) . '">' . prihod_theme_icon( $key ) . '<div><h2>' . esc_html( $card[0] ) . '</h2>' . ( $card[1] ? '<p>' . esc_html( $card[1] ) . '</p>' : '' ) . ( $card[2] ? '<span class="prihod-feature-link">' . esc_html( $card[2] ) . ' →</span>' : '' ) . '</div></a></div>';
    }
    $title = prihod_theme_home_title( 'features' );
    return $has_cards ? prihod_theme_section_title( $title, '', '', prihod_theme_home_eyebrow( 'features' ) ) . $html . '</nav>' : '';
}
function prihod_theme_home_topic_links(): string {
    $links = '';
    foreach ( array( 'first-visit', 'sermons' ) as $key ) {
        $id = prihod_theme_home_resource_id( $key );
        if ( ! $id || 'publish' !== get_post_status( $id ) ) { continue; }
        $links .= '<a href="' . esc_url( prihod_theme_preview_url( get_permalink( $id ) ) ) . '"><span>' . esc_html( get_the_title( $id ) ) . '</span><span aria-hidden="true">→</span></a>';
    }
    $title = prihod_theme_home_title( 'topics' );
    return $links ? prihod_theme_section_title( $title, '', '', prihod_theme_home_eyebrow( 'topics' ) ) . '<nav class="prihod-home-topic-links" aria-label="' . esc_attr__( 'Разделы сайта', 'georg-kloster-prihod' ) . '">' . $links . '</nav>' : '';
}
function prihod_theme_home_news( string $scheme, bool $monastery ): string {
    $home = prihod_theme_home_options();
    $category = get_term_by( 'slug', 'prihod-news', 'category' ) ?: get_term_by( 'slug', 'news', 'category' ); $count = $home['news_count'] ?: ( 'hospitality' === $scheme ? 4 : 3 );
    $category_id = $home['news_category'] > 0 ? $home['news_category'] : ( 0 === $home['news_category'] && $category ? (int) $category->term_id : 0 );
    if ( $category_id && function_exists( 'pll_get_term' ) ) { $category_id = pll_get_term( $category_id ) ?: $category_id; }
    if ( $category_id ) { $category_id = (int) apply_filters( 'wpml_object_id', $category_id, 'category', true ); }
    $posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $count, 'category' => $category_id, 'suppress_filters' => false ) );
    if ( 0 === $home['news_category'] && count( $posts ) < $count ) {
        $sermons = get_term_by( 'slug', 'prihod-sermons', 'category' );
        if ( $sermons ) { $posts = array_merge( $posts, get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $count - count( $posts ), 'category' => $sermons->term_id, 'post__not_in' => wp_list_pluck( $posts, 'ID' ), 'suppress_filters' => false ) ) ); }
    }
    $title = prihod_theme_home_title( 'news' );
    $html = '<section class="prihod-news-section">' . prihod_theme_section_title( $title, prihod_theme_home_url( 'news' ), prihod_theme_home_copy( 'news_button' ), prihod_theme_home_eyebrow( 'news' ) ) . '<div class="prihod-news-grid"' . ( $home['news_columns'] ? ' data-home-columns="' . $home['news_columns'] . '" style="--prihod-home-columns:' . $home['news_columns'] . '"' : '' ) . '>';
    foreach ( $posts as $post ) {
        $date = $home['news_show_date'] ? '<time datetime="' . esc_attr( get_the_date( 'Y-m-d', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time>' : '';
        $excerpt = $home['news_show_excerpt'] ? '<p>' . esc_html( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ?: $post->post_content ) ), $home['news_excerpt_words'] ?: ( 'tradition' === $scheme ? 9 : ( 'hospitality' === $scheme ? 12 : 19 ) ) ) ) . '</p>' : '';
        $html .= '<article class="prihod-news-card' . ( $home['news_show_images'] ? '' : ' prihod-home-no-photo' ) . '">' . ( $home['news_show_images'] ? prihod_theme_post_photo( $post, 'medium_large', prihod_theme_preview_url( get_permalink( $post ) ) ) : '' ) . '<a class="prihod-news-copy" href="' . esc_url( prihod_theme_preview_url( get_permalink( $post ) ) ) . '"><div>' . $date . '<h3>' . esc_html( $post->post_title ) . '</h3>' . $excerpt . ( prihod_theme_home_copy( 'news_read_button' ) ? '<span>' . esc_html( prihod_theme_home_copy( 'news_read_button' ) ) . ' →</span>' : '' ) . '</div></a></article>';
    }
    return $html . '</div>' . ( ! $posts ? '<p>' . esc_html__( 'Публикаций пока нет.', 'georg-kloster-prihod' ) . '</p>' : '' ) . '</section>';
}
function prihod_theme_home(): string {
    $profile = prihod_theme_profile(); $options = georg_kloster_prihod_options(); $scheme = prihod_theme_scheme(); $home = prihod_theme_home_options();
    $monastery = 'monastery' === ( $profile['type'] ?? '' );
    $about_label = prihod_theme_home_title( 'about' );
    $schedule_class = prihod_theme_schedule_class();
    $has_schedule = $home['sections']['services']['enabled'] && $schedule_class && $schedule_class::next( 0, 1 );
    $visible = array_values( array_filter( $home['order'], static fn( $key ) => $home['sections'][$key]['enabled'] ) );
    $hero_index = array_search( 'hero', $visible, true );
    $embedded = $has_schedule && false !== $hero_index && 'services' === ( $visible[$hero_index + 1] ?? '' ) && in_array( $scheme, array( 'hospitality', 'blue', 'editorial' ), true );
    $html = ''; $container_open = false;
    foreach ( $visible as $key ) {
        if ( 'hero' === $key ) {
            if ( $container_open ) { $html .= '</div>'; $container_open = false; }
            $html .= prihod_theme_home_hero( $scheme, $profile, $options, $monastery, $about_label, $embedded );
            continue;
        }
        if ( ! $container_open ) { $html .= '<div class="prihod-container">'; $container_open = true; }
        switch ( $key ) {
            case 'services':
                if ( $has_schedule && ! $embedded ) { $html .= prihod_theme_home_service_panel( $scheme, prihod_theme_home_url( 'schedule' ) ); }
                break;
            case 'features': $html .= prihod_theme_home_features( $scheme, $about_label, $monastery, (bool) $has_schedule ); break;
            case 'topics': $html .= prihod_theme_home_topic_links(); break;
            case 'about': $html .= prihod_theme_home_about( $scheme, $profile, $options, $monastery, $about_label ); break;
            case 'news':
                $news = prihod_theme_home_news( $scheme, $monastery );
                $html .= 'editorial' === $scheme ? '<div class="prihod-editorial-news">' . $news . prihod_theme_verse() . '</div>' : ( 'tradition' === $scheme ? '<div class="prihod-tradition-stories">' . $news . '</div>' : $news );
                break;
            case 'content':
                $content = prihod_theme_home_content(); $title = prihod_theme_home_title( 'content' );
                $html .= $content ? prihod_theme_section_title( $title, '', '', prihod_theme_home_eyebrow( 'content' ) ) . $content : '';
                break;
            case 'contacts': $html .= prihod_theme_contact_panel( prihod_theme_home_title( 'contacts' ) ); break;
        }
    }
    return $html . ( $container_open ? '</div>' : '' );
}
function prihod_theme_home_hero( string $scheme, array $profile, array $options, bool $monastery, string $about_label, bool $embedded ): string {
    $home = prihod_theme_home_options();
    $hero = wp_get_attachment_image_url( $options['hero_image'], 'full' ) ?: get_the_post_thumbnail_url( get_the_ID(), 'full' );
    $schedule = prihod_theme_home_url( 'schedule' ); $about = prihod_theme_home_url( 'about' ); $contact = prihod_theme_home_url( 'contacts' );
    $title = prihod_theme_home_title( 'hero' );
    $eyebrow = prihod_theme_home_eyebrow( 'hero' );
    $html = '<section class="prihod-home-hero"' . ( $hero ? ' style="--prihod-hero-image:url(' . esc_url( $hero ) . ')"' : '' ) . '><div class="prihod-container prihod-hero-grid"><div class="prihod-hero-copy">';
    if ( $eyebrow ) { $html .= '<p class="prihod-eyebrow">' . esc_html( $eyebrow ) . '</p>'; }
    if ( $title ) { $html .= '<h1>' . esc_html( $title ) . '</h1>'; }
    if ( 'classic' === $scheme ) { $html .= prihod_theme_verse(); }
    $description = prihod_theme_home_text( $home['hero_description'], 'hero-description', $profile['description'] );
    if ( $description ) { $html .= '<p class="prihod-hero-description">' . nl2br( esc_html( $description ) ) . '</p>'; }
    if ( 'hospitality' === $scheme ) { $html .= prihod_theme_verse(); }
    if ( 'tradition' !== $scheme ) {
        $html .= '<div class="prihod-actions">';
        $actions = array(); $actions[$schedule] = prihod_theme_home_copy( 'hero_schedule_button' );
        $actions['classic' === $scheme ? $contact : $about] = prihod_theme_home_copy( 'hero_secondary_button' );
        foreach ( $actions as $url => $label ) { if ( $url && $label ) { $html .= '<a class="prihod-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' →</a>'; } }
        $html .= '</div>';
    }
    if ( 'blue' === $scheme ) { $html .= prihod_theme_verse( 'hero_quote_text', 'hero_quote_source' ); }
    $html .= '</div>';
    if ( in_array( $scheme, array( 'classic', 'tradition' ), true ) && $options['hero_motto'] ) { $html .= '<aside class="' . ( 'tradition' === $scheme ? 'prihod-tradition-motto' : 'prihod-classic-motto' ) . '">' . esc_html( $options['hero_motto'] ) . '</aside>'; }
    if ( $embedded ) { $html .= prihod_theme_home_service_panel( $scheme, $schedule ); }
    return $html . '</div></section>';
}
function prihod_theme_home_about( string $scheme, array $profile, array $options, bool $monastery, string $about_label ): string {
    $home = prihod_theme_home_options(); $about = prihod_theme_home_url( 'about' );
    $description = prihod_theme_home_text( $home['about_description'], 'about-description', $profile['description'] );
    $button = prihod_theme_home_copy( 'about_button' );
    return '<section class="prihod-about-section"><div class="prihod-about-copy">' . prihod_theme_section_title( prihod_theme_home_title( 'about' ), '', '', prihod_theme_home_eyebrow( 'about' ) ) . ( $description ? '<p>' . nl2br( esc_html( $description ) ) . '</p>' : '' ) . ( $about && $button ? '<a class="prihod-button is-outline" href="' . esc_url( $about ) . '">' . esc_html( $button ) . ' →</a>' : '' ) . '</div><div class="prihod-about-photo">' . prihod_theme_photo( prihod_theme_resource_image( 'about', true ) ?: $options['hero_image'], 'large' ) . '</div>' . ( 'blue' === $scheme ? '' : prihod_theme_verse() ) . '</section>';
}
function prihod_theme_home_service_panel( string $scheme, string $schedule ): string {
    $title = prihod_theme_home_title( 'services' ); $button = prihod_theme_home_copy( 'services_button' ); $eyebrow = prihod_theme_home_eyebrow( 'services' );
    $limit = prihod_theme_home_options()['services_count'] ?: ( 'editorial' === $scheme ? 3 : 1 );
    $html = '<section class="prihod-hero-service prihod-panel">' . ( $eyebrow ? '<p class="prihod-section-eyebrow">' . esc_html( $eyebrow ) . '</p>' : '' ) . ( $title ? '<h2>' . prihod_theme_icon( 'schedule' ) . esc_html( $title ) . '</h2>' : '' ) . '<div class="prihod-compact-services">' . prihod_theme_compact_services( $limit ) . '</div>';
    if ( $schedule && $button ) { $html .= '<a class="prihod-service-all" href="' . esc_url( $schedule ) . '">' . esc_html( $button ) . ' →</a>'; }
    if ( 'blue' === $scheme ) { $html .= prihod_theme_verse(); }
    return $html . '</section>';
}

/** Display the owner's page content and any plugin blocks without a collapsed panel. */
function prihod_theme_home_content(): string {
    $post = get_post( get_queried_object_id() ?: get_option( 'page_on_front' ) );
    if ( ! $post || ! trim( $post->post_content ) ) { return ''; }
    return '<section class="prihod-home-content wp-block-post-content">' . apply_filters( 'the_content', $post->post_content ) . '</section>';
}

function prihod_theme_resource_image( string $key, bool $home = false ): int {
    $id = $home ? prihod_theme_home_resource_id( $key ) : prihod_theme_resource_id( $key );
    $featured = get_post_thumbnail_id( $id ); if ( $featured ) { return $featured; }
    $post = get_post( $id ); if ( ! $post ) { return 0; }
    foreach ( parse_blocks( $post->post_content ) as $block ) { if ( 'core/image' === $block['blockName'] && wp_attachment_is_image( absint( $block['attrs']['id'] ?? 0 ) ) ) { return absint( $block['attrs']['id'] ); } }
    return 0;
}
