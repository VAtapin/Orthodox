<?php
// Disposable WordPress fixtures only; no request submission or email delivery.
wp_set_current_user( 1 );
$saved_resources = get_option( 'prihod_resources', array() );
$saved_get = $_GET;
$published = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Overview published fixture' ) );
$draft = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Overview draft fixture' ) );
update_option( 'prihod_resources', array( 'home' => array( 'id' => $published ), 'about' => array( 'id' => $draft ) ) );
ob_start(); Prihod\Dashboard::overview(); $summary = ob_get_clean();
prihod_assert( str_contains( $summary, 'prihod-site-summary' ) && str_contains( $summary, 'prihod-page-states' ), 'Overview renders existing diagnostic and page states.' );
prihod_assert( str_contains( $summary, esc_url( get_permalink( $published ) ) ) && str_contains( $summary, esc_url( get_edit_post_link( $draft, 'raw' ) ) ), 'Published and draft resources link to their actual view and editor.' );
prihod_assert( str_contains( $summary, 'Не создана' ) && str_contains( $summary, 'Не проверено' ) && str_contains( $summary, 'prihod-check' ), 'Missing pages and unverified delivery are explicit, with diagnostic navigation.' );
update_option( 'prihod_resources', $saved_resources ); wp_delete_post( $published, true ); wp_delete_post( $draft, true );
$existing_requests = get_posts( array( 'post_type' => 'prihod_request', 'post_status' => 'private', 'numberposts' => -1, 'fields' => 'ids' ) );
foreach ( $existing_requests as $id ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) ); }
$fixture = 0;
try {
    $_GET = array(); ob_start(); Prihod\Request_Admin::render(); $empty = ob_get_clean();
    prihod_assert( str_contains( $empty, 'Обращений пока нет.' ) && str_contains( $empty, 'colspan="3"' ), 'Empty requests list has an explicit full-width state.' );
    prihod_assert( str_contains( $empty, 'for="prihod-status-filter"' ) && str_contains( $empty, 'id="prihod-status-filter"' ), 'Status selector has a programmatic label.' );
    $fixture = wp_insert_post( array( 'post_type' => 'prihod_request', 'post_status' => 'private', 'post_title' => 'Local private list fixture' ) );
    update_post_meta( $fixture, '_prihod_request_status', 'new' );
    $_GET = array( 'status' => 'closed' ); ob_start(); Prihod\Request_Admin::render(); $filtered = ob_get_clean();
    prihod_assert( str_contains( $filtered, 'По выбранному статусу обращений не найдено.' ) && str_contains( $filtered, 'Сбросить фильтр' ) && ! str_contains( $filtered, 'Local private list fixture' ), 'Empty valid filter offers reset and does not expose excluded records.' );
    $_GET = array( 'status' => 'new' ); ob_start(); Prihod\Request_Admin::render(); $listed = ob_get_clean();
    prihod_assert( str_contains( $listed, 'Local private list fixture' ) && str_contains( $listed, 'request_id=' . $fixture ) && ! str_contains( $listed, 'Обращений пока нет.' ), 'Matching private record opens its real request route.' );
    foreach ( array( 'unsupported', array( 'new' ) ) as $invalid ) {
        $_GET = array( 'status' => $invalid ); ob_start(); Prihod\Request_Admin::render(); $invalid_html = ob_get_clean();
        prihod_assert( str_contains( $invalid_html, 'Local private list fixture' ) && ! str_contains( $invalid_html, 'Сбросить фильтр' ), 'Invalid status is normalized to the unfiltered list.' );
    }
    $_GET = array( 'status' => 'new', 'paged' => 99 ); ob_start(); Prihod\Request_Admin::render(); $outside = ob_get_clean();
    prihod_assert( str_contains( $outside, 'На этой странице обращений нет.' ) && str_contains( $outside, 'Первая страница' ) && str_contains( $outside, 'status=new' ), 'Out-of-range page preserves the valid filter when returning to page one.' );
    wp_set_current_user( $dashboard_editor );
    $die_filter = static function () { return static function () { throw new RuntimeException( 'denied' ); }; };
    add_filter( 'wp_die_handler', $die_filter );
    foreach ( array( array( Prihod\Dashboard::class, 'overview' ), array( Prihod\Request_Admin::class, 'render' ) ) as $callback ) {
        ob_start();
        try { call_user_func( $callback ); prihod_assert( false, 'Unauthorized admin callback must deny access.' ); }
        catch ( RuntimeException $error ) { prihod_assert( 'denied' === $error->getMessage() && '' === ob_get_contents(), 'Unauthorized admin callback denies access before private output.' ); }
        finally { ob_end_clean(); }
    }
    remove_filter( 'wp_die_handler', $die_filter );
} finally {
    wp_set_current_user( 1 ); $_GET = $saved_get;
    if ( $fixture ) { wp_delete_post( $fixture, true ); }
    foreach ( $existing_requests as $id ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) ); }
}
