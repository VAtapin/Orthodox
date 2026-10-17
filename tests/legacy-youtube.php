<?php
require __DIR__ . '/../scripts/legacy-youtube.php';

$source = '[fusion_builder_column][fusion_youtube autoplay="false" id="https://youtu.be/CQh-4SRK1h4" /][/fusion_builder_column]';
$converted = gkm_convert_legacy_youtube( $source );
if ( ! str_contains( $converted, '<!-- wp:embed ' ) || ! str_contains( $converted, 'https://www.youtube.com/watch?v=CQh-4SRK1h4' ) ) {
    throw new RuntimeException( 'Avada YouTube shortcodes must become standard WordPress blocks.' );
}
if ( ! str_contains( gkm_youtube_block( 'https://www.youtube.com/watch?v=6V-TNm-_w7o' ), '6V-TNm-_w7o' ) ) {
    throw new RuntimeException( 'YouTube watch URLs must be supported.' );
}
foreach ( array( 'https://example.org/watch?v=6V-TNm-_w7o', 'https://www.youtube.com/watch?v=invalid', 'javascript:alert(1)' ) as $url ) {
    if ( '' !== gkm_youtube_block( $url ) ) { throw new RuntimeException( 'Unsafe or invalid video URL was accepted.' ); }
}
echo "Legacy YouTube conversion passed.\n";
