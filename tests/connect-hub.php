<?php
/** Synthetic data in a disposable WordPress database only. No live sends. */
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
use GeorgKlosterPrihodSocial\Forms;
use GeorgKlosterPrihodSocial\Admin;
use GeorgKlosterPrihodSocial\Request_Form;
use GeorgKlosterPrihodSocial\Requests;
use GeorgKlosterPrihodSocial\Connector;
use GeorgKlosterPrihodSocial\Publications;
use GeorgKlosterPrihodSocial\Hub_Settings;
use GeorgKlosterPrihodSocial\Social_Providers;

$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } };
$throws = static function ( callable $operation, string $message ) use ( $check ): void {
    $failed = false; try { $operation(); } catch ( Throwable $error ) { $failed = true; } $check( $failed, $message );
};
$admin_id = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID;
wp_set_current_user( $admin_id ); set_current_screen( 'dashboard' );
$check( '0.3.3' === GKPS_VERSION, 'Wrong Connect Hub version.' );
$check( ! is_plugin_active( 'georg-kloster-prihod-social/georg-kloster-prihod-social.php' ), 'Old development package remains active.' );
$check( 'Existing development form' === Admin::get()['title'], 'Development form settings were lost.' );
$check( '12345' === Connector::instance( 'telegram' )->settings()['target'], 'Channel settings were lost.' );
$old_messages = get_posts( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'numberposts' => -1 ) );
$check( 1 === count( $old_messages ) && 'Synthetic development content' === $old_messages[0]->post_content, 'Synthetic upgrade fixture was lost.' );
$mail = array();
add_filter( 'pre_wp_mail', static function ( $result, $args ) use ( &$mail ) { $mail[] = $args; return true; }, 10, 2 );
$http = array(); $mode = 'success'; $ig_status = 'IN_PROGRESS';
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$http, &$mode, &$ig_status ) {
    $http[] = array( 'url' => $url, 'args' => $args );
    $body = array(); $code = 200;
    if ( str_contains( $url, 'api.vk.com/method/wall.post' ) ) {
        if ( 'timeout' === $mode ) { return new WP_Error( 'timeout', 'Synthetic timeout.' ); }
        if ( 'vk_denied' === $mode ) { $body = array( 'error' => array( 'error_code' => 5 ) ); }
        elseif ( 'throttle' === $mode ) { $code = 429; }
        else { $body = array( 'response' => array( 'post_id' => 73 ) ); }
    } elseif ( str_contains( $url, 'api.ok.ru/fb.do' ) ) {
        if ( 'timeout' === $mode ) { return new WP_Error( 'timeout', 'Synthetic timeout.' ); }
        $body = '987654';
    } elseif ( str_contains( $url, '/media_publish' ) ) { $body = array( 'id' => '88001' ); }
    elseif ( str_contains( $url, '/media' ) ) { $body = array( 'id' => '77001' ); }
    elseif ( str_contains( $url, '/77001?' ) ) { $body = array( 'status_code' => $ig_status ); }
    elseif ( str_contains( $url, '/feed' ) ) { $body = array( 'id' => 'page_71' ); }
    elseif ( str_contains( $url, '/messages' ) ) { $body = array( 'messages' => array( array( 'id' => 'wa_71' ) ) ); }
    elseif ( str_contains( $url, 'api.telegram.org' ) ) { $body = array( 'ok' => true, 'result' => array( 'message_id' => 71 ) ); }
    else { return new WP_Error( 'fixture', 'External request blocked by disposable test.' ); }
    return array( 'headers' => array(), 'response' => array( 'code' => $code ), 'body' => wp_json_encode( $body ) );
}, 10, 3 );

do_action( 'admin_init' );
foreach ( array( 'ru_RU' => array( 'Коммуникации', 'Формы', 'Сообщения', 'Публикации', 'Соцсети', 'Настройки' ), 'de_DE' => array( 'Kommunikation', 'Formulare', 'Nachrichten', 'Veröffentlichungen', 'Soziale Netzwerke', 'Einstellungen' ), 'en_US' => array( 'Connect Hub', 'Forms', 'Messages', 'Publications', 'Social networks', 'Settings' ) ) as $test_locale => $labels ) {
    $switched = switch_to_locale( $test_locale );
    global $menu, $submenu; $menu = array(); $submenu = array(); do_action( 'admin_menu' );
    $main = array_values( array_filter( $menu, static fn( $item ) => 'gkps' === $item[2] ) );
    $check( 1 === count( $main ) && $labels[0] === $main[0][0], 'Main menu locale: ' . $test_locale . ' ' . wp_json_encode( array( $switched, get_locale(), determine_locale(), $main ) ) );
    $check( array( 'gkps', 'gkps-requests', 'gkch-publications', 'gkch-settings' ) === array_column( $submenu['gkps'], 2 ), 'Four menu sections/order: ' . $test_locale );
    $check( array( $labels[1], $labels[2], $labels[3], $labels[5] ) === array_column( $submenu['gkps'], 0 ), 'Submenu locale: ' . $test_locale );
    if ( $switched ) { restore_previous_locale(); }
}

$milk = Forms::store( 0, array( 'name' => 'Milk supply', 'enabled' => true, 'title' => 'Order milk', 'intro' => 'Local dairy', 'submit' => 'Send milk order', 'topics' => "Milk\nCream", 'recipient' => 'milk@example.org' ) );
$coal = Forms::store( 0, array( 'name' => 'Coal supply', 'enabled' => true, 'title' => 'Order coal', 'submit' => 'Send coal order', 'topics' => 'Coal', 'recipient' => 'coal@example.org' ) );
$church = Forms::store( 0, array( 'name' => 'Church questions', 'enabled' => true, 'title' => 'Write a question', 'submit' => 'Send', 'topics' => 'Pilgrimage', 'recipient' => 'church@example.org' ) );
$check( 4 === count( Forms::all() ), 'Forms were not independently stored.' );
$check( 'milk@example.org' === Forms::get( $milk )['recipient'] && 'coal@example.org' === Forms::get( $coal )['recipient'], 'Form recipients were mixed.' );
$check( '' === Request_Form::render( array( 'id' => 0 ) ) && '' === Request_Form::render( array( 'id' => 99999999 ) ), 'Disabled or missing form rendered.' );
$milk_html = do_shortcode( '[gkch_form id="' . $milk . '"]' );
$coal_html = do_shortcode( '[gkch_form id="' . $coal . '"]' );
$check( str_contains( $milk_html, 'Order milk' ) && str_contains( $milk_html, 'Cream' ) && ! str_contains( $milk_html, 'Order coal' ) && ! str_contains( $milk_html, 'milk@example.org' ), 'Milk form isolation/public recipient disclosure.' );
$check( str_contains( $coal_html, 'Order coal' ) && ! str_contains( $coal_html, 'Cream' ), 'Coal form topics were mixed.' );
$check( str_contains( render_block( array( 'blockName' => 'gkch/form', 'attrs' => array( 'formId' => $church ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) ), 'Pilgrimage' ), 'Editor form selection did not reach the renderer.' );
$token = static function ( string $html, string $field ): string { preg_match( '/name="' . preg_quote( $field, '/' ) . '"[^>]*value="([^"]*)"/', $html, $match ); return html_entity_decode( $match[1] ?? '', ENT_QUOTES ); };
wp_set_current_user( 0 );
$milk_html = Request_Form::render( array( 'id' => $milk ) ); $coal_html = Request_Form::render( array( 'id' => $coal ) );
$input = array( '_wpnonce' => $token( $milk_html, '_wpnonce' ), 'token' => $token( $milk_html, 'token' ), 'form_id' => $milk, 'consent' => '1', 'name' => 'Synthetic visitor', 'email' => 'visitor@example.org', 'subject' => 'Milk', 'body' => 'Synthetic milk order.' );
$swapped = array_merge( $input, array( 'form_id' => $coal, '_wpnonce' => $token( $coal_html, '_wpnonce' ), 'subject' => 'Coal' ) );
$throws( static fn() => Request_Form::process_submission( $swapped, '192.0.2.1' ), 'A token was accepted for another form.' );
$request = Request_Form::process_submission( $input, '192.0.2.1' );
$check( (int) get_post_meta( $request, '_gkch_form_id', true ) === $milk && 'Milk supply' === get_post_meta( $request, '_gkch_form_name', true ), 'Source form was not recorded.' );
$check( 'milk@example.org' === $mail[0]['to'], 'Notification sent to another form recipient.' );
$throws( static fn() => Request_Form::process_submission( $input, '192.0.2.1' ), 'A consumed token was reused.' );
$throws( static fn() => Request_Form::process_submission( array_merge( $input, array( '_wpnonce' => 'bad' ) ), '192.0.2.2' ), 'Invalid CSRF accepted.' );
$throws( static fn() => Forms::store( 0, array( 'name' => 'Unauthorized' ) ), 'Anonymous user modified forms.' );
wp_set_current_user( $admin_id );
$throws( static fn() => Forms::store( $milk, array( 'name' => 'Bad email', 'recipient' => 'invalid' ) ), 'Invalid notification email saved.' );
$check( 'Milk supply' === Forms::get( $milk )['name'], 'Failed save changed an existing form.' );
update_option( 'gkch_settings', array( 'enabled' => false ) );
$check( '' === Request_Form::render( array( 'id' => $milk ) ), 'Global switch did not disable public forms.' );
ob_start(); GeorgKlosterPrihodSocial\Request_Admin::render(); $inbox = ob_get_clean();
$check( str_contains( $inbox, 'Milk' ), 'Messages disappeared when incoming forms were disabled.' );
update_option( 'gkch_settings', array( 'enabled' => true ) );
$post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Synthetic public post', 'post_content' => 'Synthetic public text.' ) );
$image = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => 'Synthetic image', 'guid' => 'https://93.184.216.34/fixture.jpg', 'post_status' => 'inherit' ), '', $post );
set_post_thumbnail( $post, $image );
update_post_meta( $post, '_thumbnail_id', $image );
$check( '' !== Social_Providers::image( $post ), 'JPEG fixture unavailable.' );
foreach ( Publications::titles() as $id => $title ) {
    $settings = array_merge( Connector::instance( $id )->settings(), array( 'enabled' => true, 'token' => 'development_token', 'target' => '12345', 'api_version' => 'v25.0', 'recipient' => '49123456789', 'recipient_consent' => true, 'template' => 'development_template', 'language' => 'ru', 'application_key' => 'public123', 'application_secret' => 'secret123' ) );
    if ( 'telegram' === $id ) { $settings['token'] = '12345:development_only_token'; }
    if ( 'vk' === $id ) { $settings['target'] = '-12345'; $settings['api_version'] = '5.199'; }
    update_option( 'gkps_connect_' . $id, $settings, false );
    $check( Connector::instance( $id )->valid( $settings ), 'Valid channel configuration rejected: ' . $id );
}
$before_http = count( $http );
$jobs = Publications::enqueue( $post, array_keys( Publications::titles() ) );
$check( 6 === count( $jobs ) && count( $http ) === $before_http, 'Publication sent synchronously or missing channels.' );
$check( $jobs === Publications::enqueue( $post, array_keys( Publications::titles() ) ), 'Repeated publication did not deduplicate.' );
foreach ( array( 'telegram', 'whatsapp', 'facebook', 'vk', 'ok' ) as $id ) {
    Connector::instance( $id )->worker( $jobs[$id] );
    $check( 'sent' === get_option( 'gkps_job_' . $id . '_' . $jobs[$id] )['status'], 'Channel did not accept a valid response: ' . $id );
    $calls = count( $http ); Connector::instance( $id )->worker( $jobs[$id] );
    $check( $calls === count( $http ), 'Sent publication was delivered twice: ' . $id );
}
$vk_call = array_values( array_filter( $http, static fn( $r ) => str_contains( $r['url'], 'api.vk.com' ) ) )[0];
$check( '-12345' === $vk_call['args']['body']['owner_id'] && 1 === $vk_call['args']['body']['from_group'] && 32 === strlen( $vk_call['args']['body']['guid'] ), 'VK request contract.' );
$ok_call = array_values( array_filter( $http, static fn( $r ) => str_contains( $r['url'], 'api.ok.ru' ) ) )[0];
$ok_body = $ok_call['args']['body'];
$signature = 'application_key=public123attachment=' . $ok_body['attachment'] . 'format=jsongid=12345method=mediatopic.posttype=GROUP_THEME' . md5( 'development_tokensecret123' );
$check( md5( $signature ) === $ok_body['sig'] && 'development_token' === $ok_body['access_token'], 'OK signature differs from official parameter ordering.' );
Connector::instance( 'instagram' )->worker( $jobs['instagram'] );
$ig_key = 'gkps_job_instagram_' . $jobs['instagram'];
$check( 'processing' === get_option( $ig_key )['status'] && '77001' === get_option( $ig_key )['container_id'], 'Instagram container not persisted.' );
Connector::instance( 'instagram' )->worker( $jobs['instagram'] );
$check( 'processing' === get_option( $ig_key )['status'], 'Instagram processing was not rescheduled.' );
$ig_status = 'FINISHED'; Connector::instance( 'instagram' )->worker( $jobs['instagram'] );
$check( 'sent' === get_option( $ig_key )['status'] && '88001' === get_option( $ig_key )['remote_id'], 'Instagram container not published.' );
$ig_creates = array_filter( $http, static fn( $r ) => str_ends_with( $r['url'], '/media' ) );
$check( 1 === count( $ig_creates ), 'Instagram created multiple containers for the same publication.' );
$mode = 'vk_denied';
$failed_job = Connector::instance( 'vk' )->queue( 'Synthetic failure', get_permalink( $post ), 'post', $post );
Connector::instance( 'vk' )->worker( $failed_job );
$check( 'failed' === get_option( 'gkps_job_vk_' . $failed_job )['status'], 'VK API error in HTTP 200 was accepted.' );
$mode = 'timeout';
$uncertain = Connector::instance( 'ok' )->queue( 'Synthetic uncertain result', get_permalink( $post ), 'post', $post );
wp_clear_scheduled_hook( 'gkps_ok_worker', array( $uncertain ) ); Connector::instance( 'ok' )->worker( $uncertain );
$check( 'uncertain' === get_option( 'gkps_job_ok_' . $uncertain )['status'] && ! wp_next_scheduled( 'gkps_ok_worker', array( $uncertain ) ), 'OK ambiguous timeout automatically retried.' );
$throws( static fn() => Publications::change_job( 'ok', $uncertain, 'retry' ), 'Uncertain post retried without verification.' );
Publications::change_job( 'ok', $uncertain, 'retry', true );
$check( 'pending' === get_option( 'gkps_job_ok_' . $uncertain )['status'], 'Verified retry not queued.' );
Publications::change_job( 'ok', $uncertain, 'cancel' );
$check( 'cancelled' === get_option( 'gkps_job_ok_' . $uncertain )['status'], 'Publication not cancelled.' );
$check( '' === Connector::instance( 'vk' )->queue( 'Private message', '', 'request', $request ), 'Private messages could be sent to a public social network.' );
$draft = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Synthetic draft' ) );
$throws( static fn() => Publications::enqueue( $draft, array( 'vk' ) ), 'Draft publication accepted.' );
foreach ( array_keys( Publications::titles() ) as $network ) {
    $_GET['network'] = $network; ob_start(); Hub_Settings::render(); $screen = ob_get_clean();
    $check( str_contains( $screen, Publications::titles()[$network] ) && ! str_contains( $screen, 'development_token' ) && ! str_contains( $screen, 'secret123' ), 'Network screen leaked secrets or omitted provider.' );
    $check( str_contains( Connector::instance( $network )->settings_url(), 'page=gkch-settings' ) && str_contains( $screen, 'name="action" value="gkps_connect_' . $network . '"' ), 'Network moved without its save handler.' );
    $check( 7 === preg_match_all( '/<a class="nav-tab(?: nav-tab-active)?"/', $screen ), 'Settings must show General and all six channel tabs.' );
    if ( 'telegram' === $network ) { $check( str_contains( $screen, 'name="telegram_full_requests"' ) && str_contains( $screen, 'value="gkps_telegram_receive"' ), 'Telegram reply controls lost in the Settings tab.' ); }
    if ( 'whatsapp' === $network ) { $check( str_contains( $screen, 'name="template"' ) && str_contains( $screen, 'name="recipient_consent"' ), 'WhatsApp template/consent controls lost in the Settings tab.' ); }
}
// Intercept redirects before exit so the real save/legacy handlers can be exercised.
$redirect = static function ( callable $action ): string {
    $url = '';
    $capture = static function ( $target ) use ( &$url ) { $url = $target; throw new RuntimeException( 'fixture_redirect' ); };
    add_filter( 'wp_redirect', $capture );
    try { $action(); } catch ( RuntimeException $error ) { if ( 'fixture_redirect' !== $error->getMessage() ) { throw $error; } }
    finally { remove_filter( 'wp_redirect', $capture ); }
    return $url;
};
foreach ( array( 'telegram', 'whatsapp' ) as $provider ) {
    $connector = Connector::instance( $provider ); $saved = $connector->settings();
    $_POST = array_merge( $saved, array( 'token' => '', 'application_secret' => '' ) );
    $_REQUEST = array( '_wpnonce' => wp_create_nonce( 'gkps_connect_' . $provider ) );
    $check( $connector->settings_url() === $redirect( array( $connector, 'save' ) ) && $saved === $connector->settings(), 'Saving a moved channel lost credentials/settings or returned to the old page.' );
    $_GET = array( 'gkps_connect_error' => 'token_missing,unknown' );
    $target = $redirect( array( $connector, 'legacy_settings' ) );
    $check( $target === ( 'telegram' === $provider ? add_query_arg( 'gkps_connect_error', 'token_missing', $connector->settings_url() ) : $connector->settings_url() ), 'Old channel URL does not redirect to the Settings tab with safe error codes.' );
    $check( (bool) has_action( 'load-admin_page_gkps-connect-' . $provider, array( $connector, 'legacy_settings' ) ), 'Old page is not registered as a hidden redirect.' );
}
foreach ( array( 'en_US' => 'Shortcodes and blocks', 'ru_RU' => 'Шорткоды и блоки', 'de_DE' => 'Shortcodes und Blöcke' ) as $help_locale => $heading ) {
    switch_to_locale( $help_locale );
    $_GET = array();
    ob_start(); Hub_Settings::render(); $help = ob_get_clean();
    $check( str_contains( $help, $heading ) && str_contains( $help, '[gkch_form id=' ) && str_contains( $help, '[gkps_contact_form id=' ), 'Localized form shortcode help is missing: ' . $help_locale );
    restore_previous_locale();
}
$_REQUEST = array( '_wpnonce' => wp_create_nonce( 'gkps_telegram_receive' ) );
$check( Connector::instance( 'telegram' )->settings_url() === $redirect( array( GeorgKlosterPrihodSocial\Telegram_Request_Settings::class, 'connect' ) ), 'Telegram receive/reply setup returns to the old page.' );
$_POST = $_REQUEST = array();
$_GET = array();
GeorgKlosterPrihodSocial\Blocks::register();
$editor = wp_scripts()->get_data( 'gkps-blocks', 'data' );
$check( str_contains( $editor, 'Milk supply' ) && str_contains( $editor, 'Coal supply' ) && ! str_contains( $editor, 'milk@example.org' ), 'Editor form selector leaks settings or omits forms.' );
$check( 6 <= count( Publications::history() ), 'Publication history missing.' );
require __DIR__ . '/connect-hub-automation.php';
file_put_contents( __DIR__ . '/.connect-hub-result.json', wp_json_encode( array( 'checks' => $checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'CONNECT_HUB_OK' ) ) );
echo 'CONNECT_HUB_OK ' . $checks;
