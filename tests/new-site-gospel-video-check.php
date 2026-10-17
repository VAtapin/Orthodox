<?php
/** Read-only verification after restoring the source site's Gospel videos. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This check is restricted to new.georg-kloster.ru.' );
}
foreach ( array(
    'евангелие-от-матфея-1-глава' => 'CQh-4SRK1h4',
    'евангелие-от-матфея-2-глава' => 'eGjv8hGmwOw',
    'евангелие-от-матфея-3-глава' => '6V-TNm-_w7o',
) as $slug => $id ) {
    $post = get_page_by_path( $slug, OBJECT, 'post' );
    if ( ! $post || ! has_block( 'core/embed', $post ) || ! str_contains( $post->post_content, $id ) ) {
        throw new RuntimeException( "Missing editable YouTube block in {$slug}." );
    }
    $response = wp_remote_get( get_permalink( $post ), array( 'timeout' => 20 ) );
    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        throw new RuntimeException( "Cannot load {$slug}." );
    }
    $html = wp_remote_retrieve_body( $response );
    if ( ! str_contains( $html, $id ) || ! str_contains( $html, 'wp-block-embed' ) ) {
        throw new RuntimeException( "Public video is missing from {$slug}." );
    }
}
WP_CLI::success( 'All three Gospel videos are present in editable blocks and public pages.' );
