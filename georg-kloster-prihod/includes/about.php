<?php
defined( 'ABSPATH' ) || exit;

/** Presentation is opt-in for the unchanged generated about page. Never save content. */
function prihod_theme_about_page(): ?WP_Post {
    $resources = get_option( 'prihod_resources', array() ); $post = get_post();
    $headings = $post ? array_filter( parse_blocks( $post->post_content ), static fn( $block ) => 'core/heading' === $block['blockName'] ) : array();
    return count( $headings ) >= 2 && $post && in_array( get_page_template_slug( $post->ID ), array( '', 'default', 'about', 'about.php' ), true ) && $post->ID === absint( $resources['about']['id'] ?? 0 ) && get_post_meta( $post->ID, '_prihod_theme_demo', true ) && hash_equals( (string) get_post_meta( $post->ID, '_prihod_generated_hash', true ), hash( 'sha256', $post->post_title . '|' . $post->post_content ) ) ? $post : null;
}
function prihod_theme_about_intro(): string {
    $post = prihod_theme_about_page();
    if ( ! $post ) { return ''; }
    foreach ( parse_blocks( $post->post_content ) as $block ) { if ( 'core/paragraph' === $block['blockName'] ) { return render_block( $block ); } }
    return '';
}
function prihod_theme_about_directions(): string {
    $resources = get_option( 'prihod_resources', array() ); $html = '';
    foreach ( array( 'first-visit' => 'about', 'news' => 'news', 'donations' => 'support' ) as $key => $icon ) {
        $url = prihod_theme_url( $key ); $page = get_post( absint( $resources[$key]['id'] ?? 0 ) );
        if ( ! $url || ! $page ) { continue; }
        $intro = '';
        foreach ( parse_blocks( $page->post_content ) as $block ) { if ( 'core/paragraph' === $block['blockName'] ) { $intro = wp_strip_all_tags( $block['innerHTML'] ); break; } }
        $html .= '<article>' . prihod_theme_icon( $icon ) . '<div><h3>' . esc_html( $page->post_title ) . '</h3><p>' . esc_html( wp_trim_words( $intro, 12 ) ) . '</p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Узнать подробнее', 'georg-kloster-prihod' ) . ' →</a></div></article>';
    }
    return $html ? '<section id="prihod-about-activities" class="prihod-about-directions"><h2>' . esc_html__( 'Направления деятельности', 'georg-kloster-prihod' ) . '</h2><div>' . $html . '</div></section>' : '';
}
function prihod_theme_about_navigation(): string {
    if ( ! prihod_theme_about_page() ) { return ''; }
    $items = array( 'history' => __( 'История', 'georg-kloster-prihod' ), 'life' => __( 'Жизнь общины', 'georg-kloster-prihod' ) );
    if ( get_posts( array( 'post_type' => 'prihod_clergy', 'post_status' => 'publish', 'numberposts' => 1 ) ) ) { $items['clergy'] = __( 'Духовенство', 'georg-kloster-prihod' ); }
    if ( prihod_theme_about_directions() ) { $items['activities'] = __( 'Направления деятельности', 'georg-kloster-prihod' ); }
    $html = '<nav class="prihod-about-nav" aria-label="' . esc_attr__( 'Разделы страницы', 'georg-kloster-prihod' ) . '"><div class="prihod-container">';
    foreach ( $items as $id => $label ) { $html .= '<a href="#prihod-about-' . $id . '">' . esc_html( $label ) . '</a>'; }
    return $html . '</div></nav>';
}
function prihod_theme_about_invitation(): string {
    if ( ! prihod_theme_about_page() ) { return ''; }
    $links = '';
    foreach ( array( 'contacts' => __( 'Как присоединиться', 'georg-kloster-prihod' ), 'schedule' => __( 'Расписание', 'georg-kloster-prihod' ) ) as $key => $label ) { $url = prihod_theme_url( $key ); if ( $url ) { $links .= '<a class="prihod-button' . ( 'schedule' === $key ? ' is-outline' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' →</a>'; } }
    return $links ? '<section class="prihod-about-invitation"><div class="prihod-container">' . prihod_theme_icon( 'about' ) . '<h2>' . esc_html__( 'Рады встрече с вами', 'georg-kloster-prihod' ) . '</h2><div class="prihod-actions">' . $links . '</div></div></section>' : '';
}
function prihod_theme_about_content( string $html ): string {
    $post = prihod_theme_about_page(); if ( ! $post ) { return $html; }
    $result = ''; $intro = false; $section = 0;
    foreach ( parse_blocks( $post->post_content ) as $block ) {
        if ( ! $intro && 'core/paragraph' === $block['blockName'] ) { $intro = true; continue; } // Rendered intact in the hero.
        if ( 'core/heading' === $block['blockName'] && $section < 2 ) {
            if ( $section ) { $result .= '</section>'; }
            $result .= '<section id="prihod-about-' . ( $section ? 'life' : 'history' ) . '" class="prihod-about-story">'; ++$section;
        }
        if ( 'core/paragraph' === $block['blockName'] && preg_match_all( '/\b(?:19|20)\d{2}\b/u', wp_strip_all_tags( $block['innerHTML'] ) ) >= 2 ) {
            $sentences = preg_split( '/(?<=[.;!?])\s+/u', wp_strip_all_tags( $block['innerHTML'] ) );
            $result .= '<ol class="prihod-history-timeline">';
            foreach ( $sentences as $sentence ) { if ( ! trim( $sentence ) ) { continue; } preg_match( '/\b(?:19|20)\d{2}\b/u', $sentence, $year ); $result .= '<li><strong>' . esc_html( $year[0] ?? '' ) . '</strong><p>' . esc_html( $sentence ) . '</p></li>'; }
            $result .= '</ol>';
        } else { $result .= render_block( $block ); }
    }
    if ( $section ) { $result .= '</section>'; }
    if ( class_exists( '\Prihod\Directory' ) && get_posts( array( 'post_type' => 'prihod_clergy', 'post_status' => 'publish', 'numberposts' => 1 ) ) ) { $result .= '<section id="prihod-about-clergy" class="prihod-about-clergy"><h2>' . esc_html__( 'Духовенство', 'georg-kloster-prihod' ) . '</h2>' . prihod_theme_photo( prihod_theme_resource_image( 'clergy' ), 'medium' ) . prihod_theme_block( 'prihod/clergy' ) . '</section>'; }
    return $result . prihod_theme_about_directions();
}
add_filter( 'render_block_core/post-content', 'prihod_theme_about_content' );
