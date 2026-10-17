<?php
/** Read-only check of the actual RU/DE news pages and every visible category filter. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This check is restricted to new.georg-kloster.ru.' );
}

$saved_query = $GLOBALS['wp_query'];
$saved_post = $GLOBALS['post'] ?? null;
$saved_get = $_GET;
$checked = 0;
foreach ( array( 'ru' => 17, 'de' => 62 ) as $language => $page_id ) {
    do_action( 'wpml_switch_language', $language );
    $GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page_id ) );
    $GLOBALS['post'] = get_post( $page_id );
    $blocks = parse_blocks( get_post_field( 'post_content', $page_id ) );
    $query = null;
    foreach ( $blocks as $block ) { if ( 'core/query' === $block['blockName'] ) { $query = $block['attrs']['query'] ?? null; break; } }
    if ( ! is_array( $query ) || ! empty( $query['taxQuery'] ) ) { throw new RuntimeException( "{$language} news base query still restricts categories." ); }
    $_GET = array();
    $terms = prihod_theme_catalog_terms( $query );
    if ( count( $terms ) < 2 ) { throw new RuntimeException( "{$language} news filters do not cover the published categories." ); }
    foreach ( $terms as $term ) {
        $_GET = array( 'prihod_catalog_category' => (string) $term->term_id );
        $html = apply_filters( 'the_content', get_post_field( 'post_content', $page_id ) );
        if ( str_contains( $html, 'prihod-catalog-empty' ) || ! preg_match( '/\bwp-block-post\s+post-\d+\b/', $html ) ) {
            throw new RuntimeException( "{$language} category {$term->term_id} has an empty result." );
        }
        ++$checked;
    }
}
$GLOBALS['wp_query'] = $saved_query;
$GLOBALS['post'] = $saved_post;
$_GET = $saved_get;
do_action( 'wpml_switch_language', 'ru' );
WP_CLI::success( "{$checked} RU/DE news category filters return published posts." );
