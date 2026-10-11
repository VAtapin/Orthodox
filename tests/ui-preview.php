<?php
/** Plugin Name: Prihod disposable UI preview (tests only) */
defined( 'ABSPATH' ) || exit;
add_filter( 'pre_http_request', fn() => new WP_Error( 'offline_test', 'External HTTP disabled.' ), PHP_INT_MAX );
add_filter( 'pre_wp_mail', fn() => false, PHP_INT_MAX );
add_filter( 'locale', function ( $locale ) { $allowed = array( 'ru_RU', 'de_DE', 'pl_PL', 'uk' ); return isset( $_GET['ui_locale'] ) && in_array( $_GET['ui_locale'], $allowed, true ) ? $_GET['ui_locale'] : $locale; } );
add_filter( 'wp_theme_json_data_user', function ( $data ) {
    $name = $_GET['ui_scheme'] ?? '';
    if ( in_array( $name, array( 'hospitality', 'editorial', 'tradition', 'blue' ), true ) ) { $data->update_with( json_decode( file_get_contents( WP_CONTENT_DIR . '/themes/prihod-theme/styles/' . $name . '.json' ), true ) ); }
    return $data;
} );
