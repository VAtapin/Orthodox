<?php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../georg-kloster-connect-hub/includes/class-social-providers.php';
use GeorgKlosterPrihodSocial\Social_Providers;
function wp_safe_remote_request( $url, $args ) { $GLOBALS['vk_test_body'] = $args['body']; return array(); }
function is_wp_error( $response ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return 200; }
function wp_remote_retrieve_body( $response ) { return '{"response":{"post_id":12}}'; }
function __( $text, $domain ) { return $text; }
function get_the_title( $id ) { return 'A news title'; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
foreach ( array( 'club243131258', '243131258', '-243131258', '-club243131258', 'CLUB243131258' ) as $id ) {
    $settings = array( 'enabled' => true, 'token' => 'vk1.a.example', 'target' => $id, 'api_version' => '5.199' );
    if ( ! Social_Providers::valid( 'vk', $settings ) ) { throw new Exception( 'Valid ID rejected: ' . $id ); }
    $job = array( 'text' => 'text', 'url' => 'https://example.org', 'source' => 1, 'reply_context' => array() );
    Social_Providers::send( 'vk', $settings, $job );
    if ( '-243131258' !== $GLOBALS['vk_test_body']['owner_id'] || 1 !== $GLOBALS['vk_test_body']['from_group'] ) { throw new Exception( 'Incorrect VK request for: ' . $id ); }
    if ( 'https://example.org' !== $GLOBALS['vk_test_body']['attachments'] || 'text' !== $GLOBALS['vk_test_body']['message'] ) { throw new Exception( 'News link should be an attachment.' ); }
}
$settings = array( 'enabled' => true, 'token' => 'vk1.a.example', 'target' => 'club243131258', 'api_version' => '5.199' );
$job = array( 'kind' => 'post', 'text' => "A news title\nA news title The beginning.", 'url' => 'https://example.org', 'source' => 1, 'reply_context' => array(), 'vk_photo' => 'photo-123_456' );
Social_Providers::send( 'vk', $settings, $job );
if ( "A news title\n\nThe beginning." !== $GLOBALS['vk_test_body']['message'] ) { throw new Exception( 'Repeated title was not removed.' ); }
if ( 'photo-123_456,https://example.org' !== $GLOBALS['vk_test_body']['attachments'] ) { throw new Exception( 'Post must include its photo and link.' ); }
$job['url'] = '';
Social_Providers::send( 'vk', $settings, $job );
if ( 'photo-123_456' !== $GLOBALS['vk_test_body']['attachments'] ) { throw new Exception( 'Photo must remain attached without a link.' ); }
foreach ( array( 'club', 'club0', '-club', 'club12oops', '--123', 'https://vk.ru/club123' ) as $id ) {
    $settings = array( 'enabled' => true, 'token' => 'vk1.a.example', 'target' => $id, 'api_version' => '5.199' );
    if ( Social_Providers::valid( 'vk', $settings ) ) { throw new Exception( 'Invalid ID accepted: ' . $id ); }
}
echo "VK community ID checks passed.\n";
