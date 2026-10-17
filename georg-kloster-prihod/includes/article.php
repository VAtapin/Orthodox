<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_article_section(): array {
    $categories = get_the_category();
    if ( ! $categories ) { return array(); }
    $category = $categories[0]; $url = get_category_link( $category );
    foreach ( array( 'news', 'sermons' ) as $key ) {
        if ( 'prihod-' . $key !== $category->slug ) { continue; }
        $id = prihod_theme_resource_id( $key );
        if ( $id && 'publish' === get_post_status( $id ) ) { $url = get_permalink( $id ); }
    }
    return array( 'name' => $category->name, 'url' => is_wp_error( $url ) ? '' : prihod_theme_preview_url( $url ) );
}
function prihod_theme_article_meta(): string {
    if ( ! is_singular( 'post' ) ) { return ''; }
    return '<div class="prihod-article-meta"><time datetime="' . esc_attr( get_the_date( DATE_ATOM ) ) . '">' . esc_html( get_the_date() ) . '</time></div>';
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
    $html = '<section class="prihod-related">' . prihod_theme_section_title( __( 'Читайте также', 'georg-kloster-prihod' ) ) . '<div class="prihod-news-grid">';
    foreach ( $posts as $post ) { $html .= '<article class="prihod-news-card">' . prihod_theme_post_photo( $post, 'medium_large', prihod_theme_preview_url( get_permalink( $post ) ) ) . '<a class="prihod-news-copy" href="' . esc_url( prihod_theme_preview_url( get_permalink( $post ) ) ) . '"><div><time datetime="' . esc_attr( get_the_date( 'Y-m-d', $post ) ) . '">' . esc_html( get_the_date( '', $post ) ) . '</time><h3>' . esc_html( $post->post_title ) . '</h3><p>' . esc_html( wp_trim_words( get_the_excerpt( $post ), 22 ) ) . '</p><span class="prihod-read-more">' . esc_html__( 'Читать дальше', 'georg-kloster-prihod' ) . ' →</span></div></a></article>'; }
    return $html . '</div></section>';
}
