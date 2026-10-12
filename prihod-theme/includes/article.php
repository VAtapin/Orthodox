<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_article_section(): array {
    $categories = get_the_category();
    if ( ! $categories ) { return array(); }
    $category = $categories[0]; $url = get_category_link( $category );
    $resources = get_option( 'prihod_resources', array() );
    foreach ( array( 'news', 'sermons' ) as $key ) {
        if ( 'prihod-' . $key !== $category->slug ) { continue; }
        $id = absint( $resources[$key]['id'] ?? 0 );
        if ( $id && 'publish' === get_post_status( $id ) ) { $url = get_permalink( $id ); }
    }
    return array( 'name' => $category->name, 'url' => is_wp_error( $url ) ? '' : prihod_theme_preview_url( $url ) );
}
function prihod_theme_article_meta(): string {
    if ( ! is_singular( 'post' ) ) { return ''; }
    return '<div class="prihod-article-meta"><time datetime="' . esc_attr( get_the_date( DATE_ATOM ) ) . '">' . esc_html( get_the_date() ) . '</time><div class="prihod-share"><button type="button" aria-expanded="false" data-share-url="' . esc_url( get_permalink() ) . '" data-share-title="' . esc_attr( get_the_title() ) . '" data-copied="' . esc_attr__( 'Ссылка скопирована.', 'prihod-theme' ) . '" data-manual="' . esc_attr__( 'Скопируйте выделенную ссылку.', 'prihod-theme' ) . '">' . prihod_theme_icon( 'share' ) . esc_html__( 'Поделиться', 'prihod-theme' ) . '</button><div class="prihod-share-actions" hidden><button type="button" data-share-native>' . esc_html__( 'Через приложения', 'prihod-theme' ) . '</button><button type="button" data-share-copy>' . esc_html__( 'Скопировать ссылку', 'prihod-theme' ) . '</button></div><input hidden readonly aria-label="' . esc_attr__( 'Ссылка на статью', 'prihod-theme' ) . '" value="' . esc_url( get_permalink() ) . '"><span role="status" aria-live="polite"></span><noscript><a href="' . esc_url( get_permalink() ) . '">' . esc_html__( 'Ссылка на статью', 'prihod-theme' ) . '</a></noscript></div></div>';
}
function prihod_theme_article_return(): string {
    if ( ! is_singular( 'post' ) ) { return ''; }
    $section = prihod_theme_article_section();
    return empty( $section['url'] ) ? '' : '<p class="prihod-article-return"><a href="' . esc_url( $section['url'] ) . '">← ' . esc_html( $section['name'] ) . '</a></p>';
}
function prihod_theme_related_posts(): string {
    if ( ! is_singular( 'post' ) ) { return ''; }
    $posts = get_posts( array( 'post_status' => 'publish', 'numberposts' => 3, 'post__not_in' => array( get_the_ID() ), 'category__in' => wp_get_post_categories( get_the_ID() ), 'suppress_filters' => false ) );
    if ( ! $posts ) { return ''; }
    $html = '<section class="prihod-related">' . prihod_theme_section_title( __( 'Читайте также', 'prihod-theme' ) ) . '<div class="prihod-news-grid">';
    foreach ( $posts as $post ) { $html .= '<article class="prihod-news-card"><a href="' . esc_url( prihod_theme_preview_url( get_permalink( $post ) ) ) . '">' . get_the_post_thumbnail( $post, 'medium_large' ) . '<div><time datetime="' . esc_attr( get_the_date( 'Y-m-d', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time><h3>' . esc_html( $post->post_title ) . '</h3><p>' . esc_html( wp_trim_words( get_the_excerpt( $post ), 22 ) ) . '</p><span class="prihod-read-more">' . esc_html__( 'Читать дальше', 'prihod-theme' ) . ' →</span></div></a></article>'; }
    return $html . '</div></section>';
}
function prihod_theme_about_content( string $html ): string {
    $resources = get_option( 'prihod_resources', array() ); $post = get_post();
    if ( ! $post || 'about' !== get_page_template_slug( $post->ID ) || $post->ID !== absint( $resources['about']['id'] ?? 0 ) || ! get_post_meta( $post->ID, '_prihod_theme_demo', true ) || ! hash_equals( (string) get_post_meta( $post->ID, '_prihod_generated_hash', true ), hash( 'sha256', $post->post_title . '|' . $post->post_content ) ) ) { return $html; }
    $result = '';
    foreach ( parse_blocks( $post->post_content ) as $block ) {
        if ( 'core/image' === $block['blockName'] ) { continue; } // Used by the photographic page heading.
        if ( 'core/paragraph' === $block['blockName'] && preg_match_all( '/\b(?:19|20)\d{2}\b/u', wp_strip_all_tags( $block['innerHTML'] ) ) >= 2 ) {
            $sentences = preg_split( '/(?<=[.;!?])\s+/u', wp_strip_all_tags( $block['innerHTML'] ) );
            $result .= '<ol class="prihod-history-timeline">';
            foreach ( $sentences as $sentence ) { if ( ! trim( $sentence ) ) { continue; } preg_match( '/\b(?:19|20)\d{2}\b/u', $sentence, $year ); $result .= '<li><strong>' . esc_html( $year[0] ?? '' ) . '</strong><p>' . esc_html( $sentence ) . '</p></li>'; }
            $result .= '</ol>';
        } else { $result .= render_block( $block ); }
    }
    if ( class_exists( '\\Prihod\\Directory' ) && get_posts( array( 'post_type' => 'prihod_clergy', 'post_status' => 'publish', 'numberposts' => 1 ) ) ) { $result .= '<section class="prihod-about-clergy"><h2>' . esc_html__( 'Духовенство', 'prihod-theme' ) . '</h2>' . wp_get_attachment_image( prihod_theme_resource_image( 'clergy' ), 'medium' ) . prihod_theme_block( 'prihod/clergy' ) . '</section>'; }
    return $result;
}
add_filter( 'render_block_core/post-content', 'prihod_theme_about_content' );
