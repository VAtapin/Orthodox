<?php
foreach ( array( 'prihod-telegram', 'prihod-whatsapp', 'prihod-social' ) as $slug ) {
    $activation = activate_plugin( $slug . '/' . $slug . '.php' );
    prihod_assert( ! is_wp_error( $activation ), 'Independent connector package activates.' );
}
$connectors = array();
foreach ( array( 'telegram', 'whatsapp', 'facebook' ) as $provider ) { $connectors[ $provider ] = PrihodConnect\Connector::boot( $provider, $provider, '' ); }
$publication = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Public announcement', 'post_content' => 'Public content' ) );
$configs = array(
    'telegram' => array( 'enabled' => true, 'token' => '12345:TEST_ONLY_NOT_A_REAL_TOKEN', 'target' => '@test_channel' ),
    'whatsapp' => array( 'enabled' => true, 'token' => 'TEST_ONLY_NOT_A_REAL_TOKEN', 'target' => '123456', 'api_version' => 'v25.0', 'recipient' => '12345678901', 'template' => 'test_template', 'language' => 'ru', 'recipient_consent' => true ),
    'facebook' => array( 'enabled' => true, 'token' => 'TEST_ONLY_NOT_A_REAL_TOKEN', 'target' => '123456', 'api_version' => 'v25.0' ),
);
$http_calls = 0;
$transport = function ( $preempt, $args, $url ) use ( &$http_calls ) {
    if ( ! str_starts_with( $url, 'https://api.telegram.org/' ) && ! str_starts_with( $url, 'https://graph.facebook.com/' ) ) { return $preempt; }
    ++$http_calls; $body = json_decode( $args['body'], true );
    if ( str_contains( $url, '/sendMessage' ) ) { prihod_assert( '@test_channel' === $body['chat_id'] && isset( $body['text'] ), 'Telegram payload uses one configured connection.' ); $json = array( 'ok' => true, 'result' => array( 'message_id' => 1 ) ); }
    elseif ( str_ends_with( $url, '/messages' ) ) { prihod_assert( 'whatsapp' === $body['messaging_product'] && 'template' === $body['type'] && 'test_template' === $body['template']['name'], 'WhatsApp uses official approved-template payload.' ); $json = array( 'messages' => array( array( 'id' => 'test-message' ) ) ); }
    else { prihod_assert( isset( $body['message'], $body['link'] ), 'Facebook feed uses public text and link.' ); $json = array( 'id' => 'test-post' ); }
    return array( 'headers' => array(), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $json ), 'cookies' => array() );
};
add_filter( 'pre_http_request', $transport, 10, 3 );
foreach ( $connectors as $provider => $connector ) {
    update_option( 'prihod_connect_' . $provider, $configs[ $provider ], false );
    $job = $connector->queue( 'Public text', get_permalink( $publication ), 'post', $publication );
    prihod_assert( '' !== $job && $job === $connector->queue( 'Public text', get_permalink( $publication ), 'post', $publication ), 'Queue de-duplicates same provider publication.' );
    $connector->worker( $job ); $connector->worker( $job );
    prihod_assert( 'sent' === get_option( 'prihod_job_' . $provider . '_' . $job )['status'], 'Connector tracks API acceptance and avoids repeat send.' );
    wp_clear_scheduled_hook( 'prihod_' . $provider . '_worker', array( $job ) );
}
prihod_assert( 3 === $http_calls, 'One HTTP request per provider, no duplicate send.' );
remove_filter( 'pre_http_request', $transport, 10 );
$failure = fn() => new WP_Error( 'test_network_failure', 'TEST_SECRET_MUST_NOT_BE_LOGGED' );
add_filter( 'pre_http_request', $failure );
$failed_job = $connectors['telegram']->queue( 'Another publication', get_permalink( $publication ), 'post', $publication );
$connectors['telegram']->worker( $failed_job );
remove_filter( 'pre_http_request', $failure );
prihod_assert( 'failed' === get_option( 'prihod_job_telegram_' . $failed_job )['status'] && ! str_contains( wp_json_encode( get_option( 'prihod_connect_last_telegram' ) ), 'TEST_SECRET' ), 'Provider error is handled without storing secret exception content.' );
wp_clear_scheduled_hook( 'prihod_telegram_worker', array( $failed_job ) );
$connectors['telegram']->retry_last();
prihod_assert( 'pending' === get_option( 'prihod_job_telegram_' . $failed_job )['status'] && 0 === get_option( 'prihod_job_telegram_' . $failed_job )['attempts'], 'Explicit retry resets only the failed task after connection repair.' );
wp_clear_scheduled_hook( 'prihod_telegram_worker', array( $failed_job ) );
do_action( 'prihod_register_integrations', Prihod\Plugin::boot()->integrations() );
prihod_assert( count( Prihod\Plugin::boot()->integrations()->all() ) >= 5, 'Existing plugins and external connectors share the integration registry.' );
update_option( 'prihod_connect_telegram', array_merge( $configs['telegram'], array( 'notify_requests' => true ) ), false );
$private_request = Prihod\Requests::create( array( 'name' => 'PRIVATE NAME', 'email' => 'private@example.org', 'subject' => 'PRIVATE SUBJECT', 'body' => 'PRIVATE QUESTION' ) );
$notice_jobs = get_post_meta( $private_request, '_prihod_jobs_telegram', true );
prihod_assert( count( $notice_jobs ) === 1 && ! str_contains( wp_json_encode( get_option( 'prihod_job_telegram_' . $notice_jobs[0] ) ), 'PRIVATE' ), 'Notification excludes all private sender and question content.' );
Prihod\Requests::delete( $private_request );
prihod_assert( ! get_option( 'prihod_job_telegram_' . $notice_jobs[0] ), 'Privacy erasure removes queued external notification metadata.' );
foreach ( $configs as $provider => $config ) { $config['enabled'] = false; update_option( 'prihod_connect_' . $provider, $config, false ); }
if ( file_exists( WP_PLUGIN_DIR . '/orthocal/orthocal.php' ) ) {
    $activation = activate_plugin( 'orthocal/orthocal.php' );
    prihod_assert( ! is_wp_error( $activation ), 'Actual calendar source activates.' );
    Orthocal_Plugin::register();
    prihod_assert( shortcode_exists( 'orthocal_calendar' ) && isset( Prihod\Site_Plan::pages()['calendar'] ), 'Calendar adapter uses actual registered blocks and shortcodes.' );
    deactivate_plugins( 'orthocal/orthocal.php' );
}
if ( version_compare( get_bloginfo( 'version' ), '7.1', '>=' ) && version_compare( PHP_VERSION, '8.4', '>=' ) && file_exists( WP_PLUGIN_DIR . '/kloster-prayer/kloster-prayer.php' ) ) {
    $activation = activate_plugin( 'kloster-prayer/kloster-prayer.php' );
    prihod_assert( ! is_wp_error( $activation ), 'Actual Prayer Notes source activates on its supported platform.' );
    KlosterPrayer\Public_Form::init();
    prihod_assert( shortcode_exists( 'kloster_donation' ) && isset( Prihod\Site_Plan::pages()['prayer-notes'], Prihod\Site_Plan::pages()['donations'] ), 'Notes adapter reuses existing forms and standalone donations.' );
    deactivate_plugins( 'kloster-prayer/kloster-prayer.php' );
}
