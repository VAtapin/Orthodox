<?php
/** Replace legacy builder remnants on the two imported news pages. Run with wp eval-file. */
defined( 'ABSPATH' ) || exit;

if ( 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This repair is limited to new.georg-kloster.ru.' );
}

function gk_news_query( int $category = 0 ): string {
    $query = array( 'query' => array( 'perPage' => 9, 'postType' => 'post', 'order' => 'desc', 'orderBy' => 'date', 'inherit' => false ) );
    if ( $category ) { $query['query']['taxQuery'] = array( 'category' => array( $category ) ); }
    return '<!-- wp:query ' . wp_json_encode( $query ) . ' --><div class="wp-block-query">'
    . '<!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->'
    . '<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->'
    . '<!-- wp:post-date /-->'
    . '<!-- wp:post-title {"isLink":true,"level":2} /-->'
    . '<!-- wp:post-excerpt {"excerptLength":24} /-->'
    . '<!-- /wp:post-template -->'
    . '<!-- wp:query-pagination --><div class="wp-block-query-pagination">'
    . '<!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /-->'
    . '</div><!-- /wp:query-pagination -->'
    . '</div><!-- /wp:query -->';
}

foreach ( array(
    17 => array( 'news', 'Новости и события нашей обители.', 2 ),
    62 => array( 'nachrichten', 'Nachrichten und Ereignisse aus unserem Kloster.', 3 ),
) as $id => $details ) {
    $page = get_post( $id );
    if ( ! $page || 'page' !== $page->post_type || $details[0] !== $page->post_name || 'publish' !== $page->post_status ) {
        throw new RuntimeException( 'Unexpected news page: ' . $id );
    }
    $intro = '<!-- wp:paragraph --><p>' . esc_html( $details[1] ) . '</p><!-- /wp:paragraph -->';
    $content = $intro . gk_news_query( $details[2] );
    if ( $page->post_content === $content ) {
        echo 'Already repaired: ' . $id . PHP_EOL;
        continue;
    }
    $first_repair = $intro . gk_news_query();
    $is_first_repair = $page->post_content === $first_repair;
    if ( ! $is_first_repair && ( ! str_contains( $page->post_content, 'newsletter-icon-1.png' ) || ! str_contains( $page->post_content, '[gkps_contact_form]' ) ) ) {
        throw new RuntimeException( 'News page was edited since import: ' . $id );
    }
    $result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ), true );
    if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
    update_post_meta( $id, '_wp_page_template', 'catalog.php' );
    echo 'Repaired news page: ' . $id . PHP_EOL;
}
