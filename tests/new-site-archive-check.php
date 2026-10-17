<?php
/** Read-only check of the native WordPress tag and category archives. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This check is restricted to new.georg-kloster.ru.' );
}

foreach ( array( 'post_tag' => 'видео', 'category' => 'news' ) as $taxonomy => $slug ) {
    $term = get_term_by( 'slug', $slug, $taxonomy );
    if ( ! $term ) { throw new RuntimeException( "Missing {$taxonomy} term: {$slug}." ); }
    $response = wp_remote_get( get_term_link( $term ), array( 'timeout' => 15 ) );
    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        throw new RuntimeException( "Cannot load {$taxonomy} archive." );
    }
    $html = wp_remote_retrieve_body( $response );
    $title = '<h1>' . esc_html( $term->name ) . '</h1>';
    if ( ! str_contains( $html, $title ) || str_contains( $html, '&lt;span&gt;' ) ) {
        throw new RuntimeException( "{$taxonomy} archive title contains escaped markup or the wrong term." );
    }
    if ( ! str_contains( $html, 'class="prihod-category-grid"' ) || ! preg_match( '/class="[^"]*prihod-category-card\b/', $html ) ) {
        throw new RuntimeException( "{$taxonomy} archive cards are missing." );
    }
}
WP_CLI::success( 'Tag and category archives show clean titles and themed cards.' );
