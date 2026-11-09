<?php
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
require __DIR__ . '/../georg-kloster-connect-hub/includes/class-social-providers.php';
require __DIR__ . '/../georg-kloster-connect-hub/includes/class-facebook-connect.php';
require __DIR__ . '/../georg-kloster-connect-hub/includes/class-connector.php';
use GeorgKlosterPrihodSocial\Facebook_Connect;
use GeorgKlosterPrihodSocial\Connector;
class Redirect_Finished extends Exception {}
function add_action( ...$args ) {}
function do_action( ...$args ) {}
function current_user_can( $capability ) { return true; }
function get_current_user_id() { return 1; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function set_transient( $key, $value, $expires ) { $GLOBALS['transients'][ $key ] = $value; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; }
function wp_safe_redirect( $url ) { throw new Redirect_Finished(); }
function sanitize_text_field( $text ) { return $text; }
function wp_unslash( $text ) { return $text; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function admin_url( $path ) { return 'https://site.example/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args ); }
function is_wp_error( $response ) { return false; }
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_body( $response ) { return json_encode( $response['body'] ); }
function wp_safe_remote_get( $url, $args ) {
    $GLOBALS['fb_calls'][] = array( $url, $args );
    $index = count( $GLOBALS['fb_calls'] );
    if ( $GLOBALS['fb_failure'] === $index ) { return array( 'status' => 400, 'body' => array( 'error' => array( 'code' => 190, 'error_subcode' => 463, 'message' => 'do not show secret-not-real' ) ) ); }
    $values = array(
        1 => array( 'access_token' => 'short-user-token' ),
        2 => array( 'access_token' => 'long-user-token', 'expires_in' => 5184000 ),
        3 => array( 'id' => $GLOBALS['fb_page'], 'name' => 'Page', 'access_token' => 'page-token' ),
        4 => array( 'data' => array( 'is_valid' => true, 'type' => $GLOBALS['fb_type'], 'app_id' => '123', 'expires_at' => $GLOBALS['fb_expiry'] ) ),
    );
    return array( 'status' => 200, 'body' => $values[ $index ] );
}
$input = array( 'app_id' => '123', 'app_secret' => 'secret-not-real', 'page_id' => '456' );
$GLOBALS['fb_page'] = '456'; $GLOBALS['fb_type'] = 'PAGE'; $GLOBALS['fb_expiry'] = 0; $GLOBALS['fb_failure'] = 0; $GLOBALS['fb_calls'] = array();
$result = Facebook_Connect::exchange( $input, 'one-time-code' );
if ( 'page-token' !== ( $result['token'] ?? '' ) || count( $GLOBALS['fb_calls'] ) !== 4 ) { throw new Exception( 'Long-lived Page token was not obtained.' ); }
if ( ! str_contains( $GLOBALS['fb_calls'][1][0], 'grant_type=fb_exchange_token' ) || 'Bearer long-user-token' !== $GLOBALS['fb_calls'][2][1]['headers']['Authorization'] ) { throw new Exception( 'Token exchange sequence is incorrect.' ); }
foreach ( array( 1 => 'exchange', 2 => 'exchange', 3 => 'page', 4 => 'lifetime' ) as $step => $error ) {
    $GLOBALS['fb_calls'] = array(); $GLOBALS['fb_failure'] = $step;
    if ( $error !== ( Facebook_Connect::exchange( $input, 'one-time-code' )['error'] ?? '' ) ) { throw new Exception( 'Incorrect failure handling at step ' . $step ); }
}
$GLOBALS['fb_failure'] = 0; $GLOBALS['fb_calls'] = array(); $GLOBALS['fb_expiry'] = time() + 7200;
if ( 'lifetime' !== ( Facebook_Connect::exchange( $input, 'one-time-code' )['error'] ?? '' ) ) { throw new Exception( 'Short-lived Page token accepted.' ); }
$GLOBALS['fb_calls'] = array(); $GLOBALS['fb_expiry'] = 0; $GLOBALS['fb_page'] = '999';
if ( 'page' !== ( Facebook_Connect::exchange( $input, 'one-time-code' )['error'] ?? '' ) ) { throw new Exception( 'Wrong Page accepted.' ); }
$GLOBALS['fb_page'] = '456'; $GLOBALS['fb_calls'] = array(); $GLOBALS['fb_failure'] = 3;
$GLOBALS['options']['gkps_connect_facebook'] = array( 'token' => 'existing-token', 'target' => '456', 'enabled' => true );
Connector::boot( 'facebook', 'Facebook', __FILE__ );
$input['state_hash'] = hash( 'sha256', 'state-value' );
$GLOBALS['transients']['gkch_fb_oauth_1'] = $input;
$_GET = array( 'state' => 'state-value', 'code' => 'one-time-code' );
try { Facebook_Connect::callback(); } catch ( Redirect_Finished $redirect ) {}
if ( 'existing-token' !== $GLOBALS['options']['gkps_connect_facebook']['token'] || 'page' !== $GLOBALS['transients']['gkch_fb_notice_1']['code'] || isset( $GLOBALS['transients']['gkch_fb_oauth_1'] ) ) { throw new Exception( 'Failed connection changed credentials or left state active.' ); }
if ( array( 'code' => '190', 'error_subcode' => '463' ) !== $GLOBALS['transients']['gkch_fb_notice_1']['meta'] || str_contains( json_encode( $GLOBALS['transients']['gkch_fb_notice_1'] ), 'secret-not-real' ) ) { throw new Exception( 'Meta error must include safe numeric codes without credentials.' ); }
$GLOBALS['fb_calls'] = array(); $GLOBALS['fb_failure'] = 0; $GLOBALS['transients']['gkch_fb_oauth_1'] = $input;
try { Facebook_Connect::callback(); } catch ( Redirect_Finished $redirect ) {}
if ( 'page-token' !== $GLOBALS['options']['gkps_connect_facebook']['token'] || 'connected' !== $GLOBALS['transients']['gkch_fb_notice_1']['code'] ) { throw new Exception( 'Successful connection was not saved.' ); }
$expected_proof = hash_hmac( 'sha256', 'page-token', 'secret-not-real' );
if ( $expected_proof !== $GLOBALS['options']['gkps_connect_facebook']['appsecret_proof'] ) { throw new Exception( 'Page token proof was not saved.' ); }
$request = Connector::instance( 'facebook' )->request( array( 'kind' => 'post', 'text' => 'News', 'url' => 'https://site.example/news' ) );
if ( $expected_proof !== ( json_decode( $request['args']['body'], true )['appsecret_proof'] ?? '' ) ) { throw new Exception( 'Page token proof was not sent with publication.' ); }
$GLOBALS['options']['gkps_connect_facebook']['token'] = 'existing-token';
$GLOBALS['fb_calls'] = array(); $GLOBALS['transients']['gkch_fb_oauth_1'] = $input; $_GET['state'] = 'wrong-state';
try { Facebook_Connect::callback(); } catch ( Redirect_Finished $redirect ) {}
if ( 'existing-token' !== $GLOBALS['options']['gkps_connect_facebook']['token'] || $GLOBALS['fb_calls'] || 'cancelled' !== $GLOBALS['transients']['gkch_fb_notice_1']['code'] ) { throw new Exception( 'Invalid OAuth state was not rejected.' ); }
echo "Facebook connection exchange checks passed.\n";
