<?php
defined( 'ABSPATH' ) || exit;

/** Enhance existing WP Query blocks on the two registered catalog pages. */
function prihod_theme_catalog_page(): int {
    if ( ! is_singular( 'page' ) ) { return 0; }
    $id = get_queried_object_id();
    foreach ( array( 'news', 'sermons' ) as $key ) {
        $url = prihod_theme_url( $key );
        if ( $url && $id === url_to_postid( remove_query_arg( 'prihod_preview', $url ) ) && in_array( get_page_template_slug( $id ), array( '', 'default', 'catalog', 'catalog.php' ), true ) ) { return $id; }
    }
    return 0;
}
function prihod_theme_catalog_search(): string {
    $value = $_GET['prihod_catalog_search'] ?? '';
    return is_string( $value ) ? wp_html_excerpt( sanitize_text_field( wp_unslash( $value ) ), 200, '' ) : '';
}
function prihod_theme_catalog_terms( array $query ): array {
    unset( $query['prihodCatalog'] );
    $block = new WP_Block( array( 'blockName' => 'core/post-template', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ), array( 'query' => $query ) );
    $args = build_query_vars_from_query_block( $block, 1 );
    $args = array_merge( $args, array( 'post_status' => 'publish', 'posts_per_page' => -1, 'offset' => 0, 'fields' => 'ids', 'no_found_rows' => true, 'suppress_filters' => false ) );
    $posts = get_posts( $args );
    if ( ! $posts ) { return array(); }
    $terms = get_terms( array( 'taxonomy' => 'category', 'object_ids' => $posts, 'hide_empty' => true ) );
    return is_wp_error( $terms ) ? array() : $terms;
}
function prihod_theme_catalog_category( array $query ): int {
    $value = $_GET['prihod_catalog_category'] ?? '';
    $id = is_scalar( $value ) ? absint( $value ) : 0;
    return $id && in_array( $id, array_map( static fn( $term ) => $term->term_id, prihod_theme_catalog_terms( $query ) ), true ) ? $id : 0;
}
function prihod_theme_catalog_url( int $category = 0, string $search = '' ): string {
    $url = get_permalink( prihod_theme_catalog_page() ); $args = array();
    if ( $category ) { $args['prihod_catalog_category'] = $category; }
    if ( $search ) { $args['prihod_catalog_search'] = $search; }
    return prihod_theme_preview_url( add_query_arg( $args, $url ) );
}
function prihod_theme_catalog_controls( array $query ): string {
    $search = prihod_theme_catalog_search(); $category = prihod_theme_catalog_category( $query );
    $base = prihod_theme_catalog_url(); $hidden = array();
    parse_str( wp_parse_url( $base, PHP_URL_QUERY ) ?: '', $hidden );
    if ( $category ) { $hidden['prihod_catalog_category'] = $category; }
    $id = 'prihod-catalog-search-' . wp_unique_id();
    $html = '<div class="prihod-catalog-controls"><nav class="prihod-catalog-filters" aria-label="' . esc_attr__( 'Post categories', 'georg-kloster-prihod' ) . '"><a' . ( ! $category ? ' aria-current="true"' : '' ) . ' href="' . esc_url( prihod_theme_catalog_url( 0, $search ) ) . '">' . esc_html__( 'All', 'georg-kloster-prihod' ) . '</a>';
    foreach ( prihod_theme_catalog_terms( $query ) as $term ) { $html .= '<a' . ( $category === $term->term_id ? ' aria-current="true"' : '' ) . ' href="' . esc_url( prihod_theme_catalog_url( $term->term_id, $search ) ) . '">' . esc_html( $term->name ) . '</a>'; }
    $html .= '</nav><form method="get" action="' . esc_url( $base ) . '"><label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html__( 'Search posts', 'georg-kloster-prihod' ) . '</label><div><input id="' . esc_attr( $id ) . '" type="search" name="prihod_catalog_search" value="' . esc_attr( $search ) . '" maxlength="200" placeholder="' . esc_attr__( 'Search posts…', 'georg-kloster-prihod' ) . '"><button type="submit" aria-label="' . esc_attr__( 'Find', 'georg-kloster-prihod' ) . '">' . prihod_theme_icon( 'search' ) . '</button></div>';
    foreach ( $hidden as $name => $value ) { if ( is_scalar( $value ) ) { $html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">'; } }
    return $html . '</form><a class="prihod-catalog-reset" href="' . esc_url( $base ) . '">' . esc_html__( 'Reset filters', 'georg-kloster-prihod' ) . '</a></div>';
}
function prihod_theme_catalog_card_blocks( array $template ): array {
    $allowed = array( 'core/post-featured-image', 'core/post-date', 'core/post-title', 'core/post-excerpt', 'core/read-more' );
    $blocks = array();
    foreach ( $template['innerBlocks'] as $child ) {
        if ( ! in_array( $child['blockName'], $allowed, true ) ) { return $template; } // Preserve custom card compositions.
        $blocks[$child['blockName']] = $child;
    }
    if ( ! isset( $blocks['core/post-title'] ) || trim( $template['innerHTML'] ) ) { return $template; }
    $defaults = parse_blocks( '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /--><!-- wp:post-date /--><!-- wp:post-title {"isLink":true,"level":2} /--><!-- wp:post-excerpt {"excerptLength":24} /--><!-- wp:read-more {"content":' . wp_json_encode( __( 'Read more →', 'georg-kloster-prihod' ) ) . '} /-->' );
    $template['innerBlocks'] = array();
    foreach ( $defaults as $default ) {
        $child = $blocks[$default['blockName']] ?? $default;
        if ( 'core/post-excerpt' === $child['blockName'] && ! isset( $child['attrs']['excerptLength'] ) ) { $child['attrs']['excerptLength'] = 24; }
        $template['innerBlocks'][] = $child;
    }
    $template['innerContent'] = array_fill( 0, count( $template['innerBlocks'] ), null );
    $template['innerHTML'] = '';
    return $template;
}
function prihod_theme_catalog_mark_queries( array $blocks ): array {
    foreach ( $blocks as &$block ) {
        if ( 'core/query' === $block['blockName'] && empty( $block['attrs']['query']['inherit'] ) && 'post' === ( $block['attrs']['query']['postType'] ?? 'post' ) ) { $block['attrs']['query']['prihodCatalog'] = true; }
        $block['innerBlocks'] = prihod_theme_catalog_mark_queries( $block['innerBlocks'] );
    }
    unset( $block ); return $blocks;
}
add_filter( 'the_content', function ( string $content ): string {
    $post = get_post(); $page = prihod_theme_catalog_page();
    if ( ! $page || ! $post || $post->ID !== $page ) { return $content; }
    // Mark the page's own content, not unrelated header/footer Query blocks.
    return serialize_blocks( prihod_theme_catalog_mark_queries( parse_blocks( $content ) ) );
}, 8 );
add_filter( 'render_block_data', function ( array $block ): array {
    if ( 'core/query' !== $block['blockName'] || ! prihod_theme_catalog_page() || empty( $block['attrs']['query']['prihodCatalog'] ) ) { return $block; }
    $has_empty = false; $has_pagination = false;
    foreach ( $block['innerBlocks'] as &$child ) {
        if ( 'core/post-template' === $child['blockName'] ) { $child = prihod_theme_catalog_card_blocks( $child ); }
        if ( 'core/query-no-results' === $child['blockName'] ) { $has_empty = true; }
        if ( 'core/query-pagination' === $child['blockName'] ) {
            $has_pagination = true;
            if ( ! $child['innerBlocks'] ) {
                $pagination = parse_blocks( '<!-- wp:query-pagination --><div class="wp-block-query-pagination"><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--></div><!-- /wp:query-pagination -->' )[0];
                $pagination['attrs'] = array_merge( $pagination['attrs'], $child['attrs'] ); $child = $pagination;
            }
        }
    }
    unset( $child );
    if ( ! $has_pagination ) {
        $pagination = parse_blocks( '<!-- wp:query-pagination --><div class="wp-block-query-pagination"><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--></div><!-- /wp:query-pagination -->' )[0];
        $block['innerBlocks'][] = $pagination; array_splice( $block['innerContent'], -1, 0, array( null ) );
    }
    if ( ! $has_empty ) {
        $empty = parse_blocks( '<!-- wp:query-no-results --><div class="prihod-catalog-empty" role="status"><!-- wp:paragraph --><p>' . esc_html__( 'No content found. Change your search or reset the filters.', 'georg-kloster-prihod' ) . '</p><!-- /wp:paragraph --><!-- wp:paragraph --><p><a href="' . esc_url( prihod_theme_catalog_url() ) . '">' . esc_html__( 'Reset filters', 'georg-kloster-prihod' ) . '</a></p><!-- /wp:paragraph --></div><!-- /wp:query-no-results -->' )[0];
        $block['innerBlocks'][] = $empty; array_splice( $block['innerContent'], -1, 0, array( null ) );
    }
    return $block;
} );
add_filter( 'query_loop_block_query_vars', function ( array $args, WP_Block $block ): array {
    $query = $block->context['query'] ?? array();
    if ( empty( $query['prihodCatalog'] ) || ! prihod_theme_catalog_page() ) { return $args; }
    $args['post_status'] = 'publish';
    $search = prihod_theme_catalog_search();
    if ( $search ) { $args['s'] = trim( ( $args['s'] ?? '' ) . ' ' . $search ); }
    $category = prihod_theme_catalog_category( $query );
    if ( $category ) {
        $selected = array( 'taxonomy' => 'category', 'terms' => array( $category ), 'include_children' => false );
        $args['tax_query'] = ! empty( $args['tax_query'] ) ? array( 'relation' => 'AND', $args['tax_query'], $selected ) : array( $selected );
    }
    return $args;
}, 10, 2 );
add_filter( 'render_block_core/query', function ( string $html, array $block ): string {
    return prihod_theme_catalog_page() && ! empty( $block['attrs']['query']['prihodCatalog'] ) ? prihod_theme_catalog_controls( $block['attrs']['query'] ) . $html : $html;
}, 10, 2 );
add_filter( 'render_block_core/query-pagination', function ( string $html, array $parsed, WP_Block $block ): string {
    $query = $block->context['query'] ?? array();
    if ( empty( $query['prihodCatalog'] ) || ! prihod_theme_catalog_page() ) { return $html; }
    $base = prihod_theme_catalog_url( prihod_theme_catalog_category( $query ), prihod_theme_catalog_search() );
    $tags = new WP_HTML_Tag_Processor( $html );
    while ( $tags->next_tag( 'A' ) ) {
        $href = $tags->get_attribute( 'href' ); $args = array();
        if ( ! is_string( $href ) ) { continue; }
        parse_str( wp_parse_url( $href, PHP_URL_QUERY ) ?: '', $args );
        foreach ( $args as $name => $value ) {
            if ( preg_match( '/^query(?:-\d+)?-page$/', $name ) && is_scalar( $value ) ) { $tags->set_attribute( 'href', add_query_arg( $name, max( 1, absint( $value ) ), $base ) ); break; }
        }
    }
    return $tags->get_updated_html();
}, 10, 3 );
