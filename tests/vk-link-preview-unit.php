<?php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../georg-kloster-connect-hub/includes/class-social-providers.php';
use GeorgKlosterPrihodSocial\Social_Providers;
function __( $text, $domain ) { return $text; }
function get_the_title( $id ) { return 'News'; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function is_wp_error( $value ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return 200; }
function wp_remote_retrieve_body( $response ) { return '{"response":{"post_id":789}}'; }
function wp_safe_remote_request( $url, $args ) {
    $GLOBALS['calls'][] = array( $url, $args );
    if ( 'https://api.vk.com/method/wall.post' !== $url ) { throw new Exception( 'Photo upload must not be called.' ); }
    return array();
}
$settings = array( 'token' => 'vk1.a.example', 'target' => 'club123', 'api_version' => '5.199' );
$job = array( 'kind' => 'post', 'source' => 1, 'text' => "News\nNews Text.", 'url' => 'https://example.org/news', 'reply_context' => array(), 'vk_photo' => 'photo-123_456' );
$GLOBALS['calls'] = array();
$result = Social_Providers::send( 'vk', $settings, $job );
if ( ! $result['accepted'] || count( $GLOBALS['calls'] ) !== 1 ) { throw new Exception( 'VK post should be sent without a photo-upload request.' ); }
$body = $GLOBALS['calls'][0][1]['body'];
if ( 'https://example.org/news' !== $body['attachments'] || "News\n\nText." !== $body['message'] || '-123' !== $body['owner_id'] || 1 !== $body['from_group'] ) { throw new Exception( 'VK post must contain text and a link attachment for the community.' ); }
echo "VK post and link preview request checks passed.\n";
