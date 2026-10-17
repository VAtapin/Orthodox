<?php
/** Convert an Avada YouTube URL into an editable WordPress Embed block. */
function gkm_youtube_block( string $url ): string {
    $url = html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
    $path = (string) parse_url( $url, PHP_URL_PATH );
    $id = '';
    if ( 'youtu.be' === $host ) {
        $id = trim( $path, '/' );
    } elseif ( in_array( $host, array( 'youtube.com', 'www.youtube.com', 'm.youtube.com' ), true ) ) {
        if ( '/watch' === $path ) {
            parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
            $id = is_string( $query['v'] ?? null ) ? $query['v'] : '';
        } elseif ( str_starts_with( $path, '/embed/' ) ) {
            $id = substr( $path, 7 );
        }
    }
    if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/D', $id ) ) { return ''; }
    $canonical = 'https://www.youtube.com/watch?v=' . $id;
    return '<!-- wp:embed {"url":"' . $canonical . '","type":"video","providerNameSlug":"youtube","responsive":true} -->' . "\n"
        . '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">' . "\n"
        . $canonical . "\n" . '</div></figure>' . "\n" . '<!-- /wp:embed -->';
}

function gkm_convert_legacy_youtube( string $content ): string {
    return preg_replace_callback( '/\[fusion_youtube\b([^\]]*)\]/iu', static function ( array $match ): string {
        if ( ! preg_match( '/\bid\s*=\s*(["\'])(.*?)\1/iu', $match[1], $id ) ) { return ''; }
        return gkm_youtube_block( $id[2] );
    }, $content );
}
