<?php
/** Restore three YouTube videos lost when Avada shortcodes were stripped. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This repair is restricted to new.georg-kloster.ru.' );
}
require_once __DIR__ . '/legacy-youtube.php';

$videos = array(
    'евангелие-от-матфея-1-глава' => 'https://youtu.be/CQh-4SRK1h4',
    'евангелие-от-матфея-2-глава' => 'https://www.youtube.com/watch?v=eGjv8hGmwOw',
    'евангелие-от-матфея-3-глава' => 'https://www.youtube.com/watch?v=6V-TNm-_w7o',
);
$updates = array();
foreach ( $videos as $slug => $url ) {
    $post = get_page_by_path( $slug, OBJECT, 'post' );
    $block = gkm_youtube_block( $url );
    if ( ! $post || 'publish' !== $post->post_status || ! $block ) {
        throw new RuntimeException( "Cannot restore video for {$slug}." );
    }
    if ( trim( $post->post_content ) && $post->post_content !== $block ) {
        throw new RuntimeException( "Post {$slug} has new content; refusing to overwrite it." );
    }
    if ( $post->post_content !== $block ) { $updates[$post->ID] = $block; }
}
foreach ( $updates as $id => $block ) {
    $result = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $block ) ), true );
    if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Could not update post ' . $id . ': ' . $result->get_error_message() ); }
}
WP_CLI::success( count( $updates ) . ' Gospel videos restored as standard WordPress Embed blocks.' );
