<?php
// Isolated regression checks: php tests/theme-review-unit.php.
define( 'ABSPATH', __DIR__ );
function prihod_theme_preview_scheme() { return $GLOBALS['preview'] ?? 'blue'; }
function home_url( $path = '' ) { return 'https://example.org/site' . $path; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function url_to_postid( $url ) { return 'https://example.org/site/article/' === $url ? 1 : 0; }
function get_post( $id ) { return $id ? (object) array( 'ID' => $id ) : null; }
function is_post_publicly_viewable( $post ) { return true; }
function get_permalink( $post ) { return 'https://example.org/site/article/'; }
function add_query_arg( $key, $value, $url ) { return $url . '?' . $key . '=' . $value; }
require __DIR__ . '/../georg-kloster-prihod/includes/presentation.php';
$count = 0;
foreach ( array( 'https://example.org/site/', 'https://example.org/site/article/' ) as $url ) {
    if ( prihod_theme_preview_url( $url ) !== $url . '?prihod_preview=blue' ) { throw new RuntimeException( 'Public preview link failed.' ); }
    ++$count;
}
foreach ( array( 'https://example.org/wp-admin/', 'https://example.org/wp-login.php', 'https://example.org/wp-json/', 'https://example.org/site/?rest_route=/wp/v2/users', 'https://example.org/site/?action=logout', 'https://other.org/site/', 'http://example.org/site/', 'https://example.org:8443/site/', 'https://user@example.org/site/', '/site/article/', 'mailto:owner@example.org' ) as $url ) {
    if ( prihod_theme_preview_url( $url ) !== $url ) { throw new RuntimeException( 'Endpoint received preview: ' . $url ); }
    ++$count;
}
$GLOBALS['preview'] = '';
if ( prihod_theme_preview_url( 'https://example.org/site/' ) !== 'https://example.org/site/' ) { throw new RuntimeException( 'Inactive preview changed URL.' ); }
echo 'Passed ' . ( $count + 1 ) . " theme review assertions.\n";
