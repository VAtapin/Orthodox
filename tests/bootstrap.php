<?php
/** Isolated doubles for unit tests; these do not replace a WordPress integration run. */
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['options'] = array();
$GLOBALS['meta'] = array();
$GLOBALS['posts'] = array();
$GLOBALS['errors'] = array();
$GLOBALS['caps'] = array();
$GLOBALS['hooks'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['types'] = array();
$GLOBALS['roles'] = array();
function __( $text, $domain = '' ) { return $text; }
function wp_timezone_string() { return $GLOBALS['timezone'] ?? 'Europe/Berlin'; }
function get_locale() { return 'ru_RU'; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; }
function sanitize_text_field( $text ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( $text ) ) ); }
function sanitize_textarea_field( $text ) { return trim( strip_tags( $text ) ); }
function sanitize_email( $text ) { return filter_var( $text, FILTER_SANITIZE_EMAIL ); }
function is_email( $text ) { return filter_var( $text, FILTER_VALIDATE_EMAIL ); }
function esc_url_raw( $url, $protocols = null ) {
    return '' === $url || ! in_array( parse_url( $url, PHP_URL_SCHEME ), $protocols ?? array( 'http', 'https' ), true ) ? '' : $url;
}
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function add_settings_error( $option, $code, $message ) { $GLOBALS['errors'][] = $code; }
function get_role( $name ) { return $GLOBALS['roles'][ $name ] ?? null; }
function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
function get_post_type( $id ) { return get_post( $id )->post_type ?? false; }
function get_post_status( $id ) { return get_post( $id )->post_status ?? false; }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function current_user_can( $capability, ...$args ) { return $GLOBALS['caps'][ $capability ] ?? false; }
function wp_is_post_revision( $id ) { return $GLOBALS['revision'] ?? false; }
function wp_is_post_autosave( $id ) { return $GLOBALS['autosave'] ?? false; }
function wp_verify_nonce( $nonce, $action ) { return 'valid-' . $action === $nonce; }
function wp_unslash( $value ) { return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( $value ); }
function get_current_user_id() { return 7; }
function set_transient( $key, $value, $expiry ) { $GLOBALS['transients'][ $key ] = $value; }
function add_action( $name, $callback, ...$args ) { $GLOBALS['hooks'][ $name ][] = $callback; }
function add_filter( $name, $callback, ...$args ) { add_action( $name, $callback ); }
function register_post_type( $name, $args ) { $GLOBALS['types'][ $name ] = $args; }
function wp_die( $message, $title = '', $args = array() ) { throw new RuntimeException( 'denied', $args['response'] ?? 0 ); }
function esc_html__( $text, $domain ) { return $text; }
foreach ( array( 'class-capabilities', 'class-profile', 'class-directory', 'interface-integration', 'class-integration-registry', 'class-admin', 'class-plugin', 'class-modules', 'class-occurrences', 'class-schedule', 'class-schedule-admin' ) as $name ) {
    require dirname( __DIR__ ) . '/prihod/includes/' . $name . '.php';
}
