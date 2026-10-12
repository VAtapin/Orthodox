<?php
defined( 'ABSPATH' ) || exit;

/** Preserve literal shortcode examples in the calendar's JSON HTML attributes. */
function prihod_theme_calendar_markup( string $html, array $block ): string {
    $name = $block['blockName'] ?? '';
    if ( 0 !== strpos( $name, 'orthocal/' ) && ! in_array( $name, array( 'prihod-theme/sidebar', 'core/shortcode' ), true ) ) { return $html; }
    if ( false === strpos( $html, 'data-orthocal=' ) ) { return $html; }
    $tags = new WP_HTML_Tag_Processor( $html );
    while ( $tags->next_tag() ) {
        $config = $tags->get_attribute( 'data-orthocal' );
        if ( ! is_string( $config ) ) { continue; }
        json_decode( $config ); if ( JSON_ERROR_NONE !== json_last_error() ) { continue; }
        // Only JSON string tokens are escaped; structural arrays remain intact.
        $safe = preg_replace_callback( '/"(?:[^"\\\\]|\\\\.)*"/s', static fn( $match ) => str_replace( array( '[', ']' ), array( '\\u005b', '\\u005d' ), $match[0] ), $config );
        if ( is_string( $safe ) ) { $tags->set_attribute( 'data-orthocal', $safe ); }
    }
    return $tags->get_updated_html();
}
add_filter( 'render_block', 'prihod_theme_calendar_markup', 10, 2 );
