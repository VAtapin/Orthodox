<?php
// Disposable WordPress only; native Query blocks, taxonomy, search and pagination.
$catalog_saved_query = $GLOBALS['wp_query']; $catalog_saved_post = $GLOBALS['post']; $catalog_saved_get = $_GET;
$catalog_page = $first['pages']['news']; $catalog_post = get_post( $catalog_page );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $catalog_page ) ); $GLOBALS['post'] = $catalog_post;
$catalog_content_before = $catalog_post->post_content;
$catalog_cards = static function ( $html ): array {
    $tags = new WP_HTML_Tag_Processor( $html ); $ids = array();
    while ( $tags->next_tag( 'LI' ) ) { if ( $tags->has_class( 'wp-block-post' ) && preg_match( '/\bpost-(\d+)\b/', $tags->get_attribute( 'class' ), $match ) ) { $ids[] = (int) $match[1]; } }
    return $ids;
};
$catalog_render = static fn() => apply_filters( 'the_content', get_post_field( 'post_content', $catalog_page ) );
$_GET = array( 'prihod_preview' => 'classic' );
$catalog_html = $catalog_render();
$check( ! str_contains( do_blocks( $catalog_post->post_content ), 'prihod-catalog-controls' ), 'Unmarked Query blocks outside the catalog page content retain their original composition.' );
$news_category = get_category_by_slug( 'prihod-news' ); $sermon_category = get_category_by_slug( 'prihod-sermons' );
$check( 3 === count( $catalog_cards( $catalog_html ) ) && str_contains( $catalog_html, 'prihod-catalog-controls' ) && str_contains( $catalog_html, $news_category->name ) && ! str_contains( $catalog_html, $sermon_category->name ), 'Catalog controls use only real categories from the published, scoped news pool.' );
$check( strpos( $catalog_html, 'wp-block-post-featured-image' ) < strpos( $catalog_html, 'wp-block-post-date' ) && strpos( $catalog_html, 'wp-block-post-date' ) < strpos( $catalog_html, 'wp-block-post-title' ) && strpos( $catalog_html, 'wp-block-post-title' ) < strpos( $catalog_html, 'wp-block-post-excerpt' ) && str_contains( $catalog_html, 'wp-block-read-more' ), 'Native cards expose photo, date, title, excerpt and reading action in reference order.' );
// The actual news headline, not a fabricated live article.
$news = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'category' => $news_category->term_id, 'numberposts' => 1 ) )[0];
$_GET['prihod_catalog_search'] = $news->post_title;
$catalog_html = $catalog_render();
$check( array( $news->ID ) === $catalog_cards( $catalog_html ), 'Search returns the actual matching news item within its existing catalog.' );
$_GET['prihod_catalog_search'] = 'prihod-no-such-material-12345';
$catalog_html = $catalog_render();
$check( ! $catalog_cards( $catalog_html ) && str_contains( $catalog_html, 'prihod-catalog-empty' ) && str_contains( $catalog_html, esc_url( prihod_theme_catalog_url() ) ), 'Empty searches show a native no-results state with a working reset target.' );
$_GET = array( 'prihod_preview' => 'classic', 'prihod_catalog_category' => $sermon_category->term_id );
$check( 3 === count( $catalog_cards( $catalog_render() ) ), 'An out-of-scope category cannot expand the news catalog into sermons.' );
$_GET['prihod_catalog_search'] = array( 'bad' ); $_GET['prihod_catalog_category'] = array( 'bad' );
$check( 3 === count( $catalog_cards( $catalog_render() ) ), 'Malformed search/category parameters are ignored without breaking the native query.' );

$fixture_term = wp_insert_term( 'Catalog fixture category', 'category' )['term_id'];
$private_term = wp_insert_term( 'Private-only fixture category', 'category' )['term_id']; $fixture_ids = array();
for ( $i = 1; $i <= 15; ++$i ) { $fixture_ids[] = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Catalog paging fixture ' . $i, 'post_content' => 'Disposable native publication.', 'post_date' => sprintf( '2025-01-%02d 12:00:00', $i ), 'post_category' => array( $news_category->term_id, $fixture_term ) ) ); }
$fixture_ids[] = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'private', 'post_title' => 'Catalog paging fixture private', 'post_category' => array( $news_category->term_id, $private_term ) ) );
$fixture_ids[] = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Catalog paging fixture draft', 'post_category' => array( $news_category->term_id, $private_term ) ) );
$_GET = array( 'prihod_preview' => 'classic', 'prihod_catalog_search' => 'Catalog paging fixture', 'prihod_catalog_category' => $fixture_term );
$catalog_html = $catalog_render(); $page_one_ids = $catalog_cards( $catalog_html );
$check( 12 === count( $page_one_ids ) && ! str_contains( $catalog_html, 'Private-only fixture category' ), 'Search/category pagination exposes twelve published results and no private-only facets.' );
$links = new WP_HTML_Tag_Processor( $catalog_html ); $next_url = '';
while ( $links->next_tag( 'A' ) ) { if ( $links->has_class( 'wp-block-query-pagination-next' ) ) { $next_url = $links->get_attribute( 'href' ); break; } }
$next_args = array(); parse_str( wp_parse_url( $next_url, PHP_URL_QUERY ) ?: '', $next_args );
$page_key = '';
foreach ( $next_args as $key => $value ) { if ( preg_match( '/^query(?:-\d+)?-page$/', $key ) ) { $page_key = $key; } }
$check( $page_key && 'Catalog paging fixture' === ( $next_args['prihod_catalog_search'] ?? '' ) && (int) ( $next_args['prihod_catalog_category'] ?? 0 ) === $fixture_term && 'classic' === ( $next_args['prihod_preview'] ?? '' ), 'Native pagination preserves search, category and owner preview: ' . wp_json_encode( array( 'url' => $next_url, 'args' => $next_args, 'key' => $page_key ) ) );
$_GET[$page_key] = 2; $page_two_html = $catalog_render(); $page_two_ids = $catalog_cards( $page_two_html );
$check( 3 === count( $page_two_ids ) && ! array_intersect( $page_one_ids, $page_two_ids ), 'Second native query page returns the remaining three results without duplication.' );
foreach ( array_merge( $page_one_ids, $page_two_ids ) as $id ) { $check( 'publish' === get_post_status( $id ) && has_category( $news_category->term_id, $id ) && has_category( $fixture_term, $id ), 'Paged result belongs to the permitted published news/category intersection.' ); }
$controls = new WP_HTML_Tag_Processor( $page_two_html ); $has_page_hidden = false; $has_category_hidden = false; $has_page_id_hidden = false;
while ( $controls->next_tag( 'INPUT' ) ) { $name = $controls->get_attribute( 'name' ); $has_page_hidden = $has_page_hidden || $name === $page_key; $has_category_hidden = $has_category_hidden || 'prihod_catalog_category' === $name; $has_page_id_hidden = $has_page_id_hidden || 'page_id' === $name; }
$check( ! $has_page_hidden && $has_category_hidden, 'Submitting a new search resets pagination and retains its selected category.' );
$catalog_saved_permalink = get_option( 'permalink_structure' );
$GLOBALS['wp_rewrite']->set_permalink_structure( '' );
$plain_html = $catalog_render(); $plain_tags = new WP_HTML_Tag_Processor( $plain_html ); $plain_page_id = '';
while ( $plain_tags->next_tag( 'INPUT' ) ) { if ( 'page_id' === $plain_tags->get_attribute( 'name' ) ) { $plain_page_id = $plain_tags->get_attribute( 'value' ); } }
$check( (int) $plain_page_id === $catalog_page && 3 === count( $catalog_cards( $plain_html ) ), 'Plain permalinks retain their page_id through the GET search form and native second page.' );
$GLOBALS['wp_rewrite']->set_permalink_structure( $catalog_saved_permalink );
$_GET[$page_key] = 99;
$check( ! $catalog_cards( $catalog_render() ) && str_contains( $catalog_render(), 'prihod-catalog-empty' ), 'Out-of-range query pages show a useful empty state.' );
$_GET = array( 'prihod_preview' => 'classic' );
wp_update_post( array( 'ID' => $catalog_page, 'post_content' => '<!-- wp:paragraph --><p>Owner catalog introduction preserved.</p><!-- /wp:paragraph -->' . $catalog_content_before ) );
$check( str_contains( $catalog_render(), 'Owner catalog introduction preserved.' ), 'Owner-written catalog text remains visible without being overwritten.' );
wp_update_post( array( 'ID' => $catalog_page, 'post_content' => $catalog_content_before ) );
foreach ( $fixture_ids as $id ) { wp_delete_post( $id, true ); }
wp_delete_term( $fixture_term, 'category' ); wp_delete_term( $private_term, 'category' );
$check( get_post_field( 'post_content', $catalog_page ) === $catalog_content_before && 3 === count( $catalog_cards( $catalog_render() ) ), 'Catalog fixture cleanup restores all original news records and page text.' );
$GLOBALS['wp_query'] = $catalog_saved_query; $GLOBALS['post'] = $catalog_saved_post; $_GET = $catalog_saved_get;
