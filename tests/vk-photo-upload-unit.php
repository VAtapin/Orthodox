<?php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../georg-kloster-connect-hub/includes/class-social-providers.php';
use GeorgKlosterPrihodSocial\Social_Providers;
function __( $text, $domain ) { return $text; }
function get_post_thumbnail_id( $id ) { return 17; }
function get_attached_file( $id ) { return $GLOBALS['image_fixture']; }
function get_post_mime_type( $id ) { return 'image/jpeg'; }
function get_the_title( $id ) { return 'News'; }
function wp_strip_all_tags( $text ) { return strip_tags( $text ); }
function wp_http_validate_url( $url ) { return $url; }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function wp_generate_uuid4() { return 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'; }
function is_wp_error( $value ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return 200; }
function wp_remote_retrieve_body( $response ) { return json_encode( $response ); }
function sanitize_text_field( $value ) { return $value; }
function wp_safe_remote_request( $url, $args ) {
    $GLOBALS['calls'][] = array( $url, $args );
    if ( str_contains( $url, 'photos.getWallUploadServer' ) ) {
        return $GLOBALS['deny'] ? array( 'error' => array( 'error_code' => 27, 'error_msg' => 'Group authorization failed' ) ) : array( 'response' => array( 'upload_url' => 'https://upload.vk.com/image' ) );
    }
    if ( str_contains( $url, 'upload.vk.com' ) ) { return array( 'server' => 1, 'photo' => '[]', 'hash' => 'abc' ); }
    if ( str_contains( $url, 'photos.saveWallPhoto' ) ) { return array( 'response' => array( array( 'owner_id' => -123, 'id' => 456 ) ) ); }
    if ( str_contains( $url, 'wall.post' ) ) { return array( 'response' => array( 'post_id' => 789 ) ); }
    throw new Exception( 'Unexpected URL' );
}
$settings = array( 'token' => 'vk1.a.example', 'target' => 'club123', 'api_version' => '5.199' );
$GLOBALS['image_fixture'] = tempnam( sys_get_temp_dir(), 'gkch' );
file_put_contents( $GLOBALS['image_fixture'], 'image fixture' );
$job = array( 'kind' => 'post', 'source' => 1, 'text' => "News\nNews Text.", 'url' => 'https://example.org/news', 'reply_context' => array() );
$GLOBALS['calls'] = array(); $GLOBALS['deny'] = false;
$result = Social_Providers::send( 'vk', $settings, $job );
if ( ! $result['accepted'] || count( $GLOBALS['calls'] ) !== 4 ) { throw new Exception( 'Photo upload or publication failed: ' . json_encode( array( $result, $GLOBALS['calls'] ) ) ); }
if ( '123' !== $GLOBALS['calls'][0][1]['body']['group_id'] || '123' !== $GLOBALS['calls'][2][1]['body']['group_id'] ) { throw new Exception( 'Photo must be uploaded to the selected community.' ); }
$body = $GLOBALS['calls'][3][1]['body'];
if ( 'photo-123_456,https://example.org/news' !== $body['attachments'] || "News\n\nText." !== $body['message'] ) { throw new Exception( 'Photo, link and text must be published together.' ); }
if ( ! str_contains( $GLOBALS['calls'][1][1]['body'], 'name="photo"' ) ) { throw new Exception( 'Photo upload was not multipart.' ); }
$GLOBALS['calls'] = array(); Social_Providers::send( 'vk', $settings, $job );
if ( count( $GLOBALS['calls'] ) !== 1 ) { throw new Exception( 'Retry should reuse saved photo.' ); }
$GLOBALS['calls'] = array(); $GLOBALS['deny'] = true; unset( $job['vk_photo'] );
$result = Social_Providers::send( 'vk', $settings, $job );
if ( $result['accepted'] || count( $GLOBALS['calls'] ) !== 1 || '27' !== ( $result['api_error']['error_code'] ?? '' ) ) { throw new Exception( 'Photo failure must be logged and must not publish without the image.' ); }
unlink( $GLOBALS['image_fixture'] );
$GLOBALS['calls'] = array(); $GLOBALS['deny'] = false;
$result = Social_Providers::send( 'vk', $settings, $job );
if ( $result['accepted'] || count( $GLOBALS['calls'] ) !== 0 || empty( $result['api_error']['error_msg'] ) ) { throw new Exception( 'Missing image must fail before publication.' ); }
echo "VK photo upload checks passed.\n";
