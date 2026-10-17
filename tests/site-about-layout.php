<?php
/** Read-only integration check for the two edited monastery pages. Run with wp eval-file. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This check is limited to the test WordPress.' );
}

function gk_assert_core_blocks( array $blocks ): void {
    foreach ( $blocks as $block ) {
        if ( ! str_starts_with( (string) $block['blockName'], 'core/' ) ) {
            throw new RuntimeException( 'A non-core block was found.' );
        }
        gk_assert_core_blocks( $block['innerBlocks'] );
    }
}

foreach ( array(
    'ru' => array( 'page' => 23, 'history' => 31, 'heading' => 'Короткая хронология', 'schedule' => 'по субботам и воскресеньям в 10:00' ),
    'de' => array( 'page' => 55, 'history' => 53, 'heading' => 'Die wichtigsten Jahre', 'schedule' => 'samstags und sonntags um 10:00 Uhr' ),
) as $language => $expected ) {
    do_action( 'wpml_switch_language', $language );
    $page = get_post( $expected['page'] );
    if ( ! $page || 'publish' !== $page->post_status ) {
        throw new RuntimeException( 'The monastery page is missing: ' . $language );
    }
    $content = $page->post_content;
    $blocks = parse_blocks( $content );
    gk_assert_core_blocks( $blocks );
    if ( 1 !== count( $blocks ) || 'core/group' !== $blocks[0]['blockName'] || 'prihod-about-content' !== ( $blocks[0]['attrs']['className'] ?? '' ) ) {
        throw new RuntimeException( 'Unexpected page layout: ' . $language );
    }
    foreach ( array( 'prihod-about-lead', 'prihod-about-history', 'prihod-about-chronology', 'prihod-about-people', 'prihod-about-today' ) as $class ) {
        if ( ! str_contains( $content, '"className":"' . $class . '"' ) ) {
            throw new RuntimeException( 'Missing section ' . $class . ': ' . $language );
        }
    }
    if ( ! str_contains( $content, get_permalink( $expected['history'] ) ) || ! str_contains( $content, $expected['heading'] ) || ! str_contains( $content, $expected['schedule'] ) || ! str_contains( $content, '"lightbox":{"enabled":true}' ) ) {
        throw new RuntimeException( 'Missing article link, timeline, schedule or native image lightbox: ' . $language );
    }
    echo 'Monastery page OK: ' . $language . PHP_EOL;
}
