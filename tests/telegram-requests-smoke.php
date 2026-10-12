<?php
// Real WordPress + connector + REST; all transports intercepted in a disposable DB.
$telegram = Prihod\Telegram_Requests::connector();
prihod_assert( $telegram && $telegram instanceof PrihodTelegram\Connector, 'New Telegram remains independent of older active shared connector classes.' );
$saved_telegram = get_option( 'prihod_connect_telegram' ); $saved_full = get_option( Prihod\Telegram_Requests::OPTION );
$saved_post = $_POST; $saved_get = $_GET; $saved_request = $_REQUEST; $saved_modules = get_option( 'prihod_modules' );
$fixture_config = array( 'enabled' => true, 'token' => '12345:TEST_ONLY_NOT_A_REAL_TOKEN', 'target' => '67890', 'notify_requests' => true );
update_option( 'prihod_connect_telegram', $fixture_config );
update_option( 'prihod_modules', array( 'schedule' => true, 'requests' => true ) );
update_option( Prihod\Telegram_Requests::OPTION, array( 'enabled' => true, 'staff' => 1 ) );
$api_calls = array(); $sent_texts = array(); $sent_id = 500; $foreign_webhook = false;
$telegram_transport = static function ( $pre, $args, $url ) use ( &$api_calls, &$sent_texts, &$sent_id, &$foreign_webhook ) {
    if ( ! str_starts_with( $url, 'https://api.telegram.org/bot12345:TEST_ONLY_NOT_A_REAL_TOKEN/' ) ) { return new WP_Error( 'fixture_offline', 'No external test transport.' ); }
    $method = basename( $url ); $api_calls[] = $method; $body = json_decode( $args['body'], true );
    if ( 'getWebhookInfo' === $method ) { $result = array( 'url' => $foreign_webhook ? 'https://example.invalid/other-bot' : '' ); }
    elseif ( 'getMe' === $method ) { $result = array( 'id' => 12345, 'is_bot' => true ); }
    elseif ( 'getChat' === $method ) { $result = array( 'id' => 67890, 'type' => 'private' ); }
    elseif ( 'setWebhook' === $method ) { prihod_assert( str_starts_with( $body['url'], 'https://' ) && strlen( $body['secret_token'] ) >= 32 && array( 'message' ) === $body['allowed_updates'] && false === $body['drop_pending_updates'], 'Webhook uses HTTPS and a header secret, preserves pending updates and accepts only messages.' ); $result = true; }
    elseif ( 'sendMessage' === $method ) { $plain = ( 'HTML' === ( $body['parse_mode'] ?? '' ) ) ? html_entity_decode( strip_tags( $body['text'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : $body['text']; $characters = preg_split( '//u', $plain, -1, PREG_SPLIT_NO_EMPTY ); $units = count( $characters ) + count( array_filter( $characters, static fn( $character ) => strlen( $character ) > 3 ) ); prihod_assert( $units <= 4096, 'Telegram message respects its parsed UTF-16 length limit, including emoji, headers and link.' ); if ( isset( $body['parse_mode'] ) ) { prihod_assert( 'HTML' === $body['parse_mode'] && str_contains( $body['text'], '<b>') && str_contains( $body['text'], "\n\n" ), 'Full Telegram request preserves paragraphs and uses explicit HTML formatting.' ); } $sent_texts[] = $body['text']; $result = array( 'message_id' => ++$sent_id, 'chat' => array( 'id' => 67890, 'type' => 'private' ), 'from' => array( 'id' => 12345, 'is_bot' => true ) ); }
    else { throw new RuntimeException( 'Unexpected Telegram method.' ); }
    return array( 'headers' => array(), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'ok' => true, 'result' => $result ) ), 'cookies' => array() );
};
$rest_https = static fn( $url ) => str_replace( 'http://', 'https://', $url );
$emails = array(); $mailers = array();
$capture_mail = static function ( $pre, $attributes ) use ( &$emails, &$mailers ) {
    $emails[] = $attributes;
    $mailer = (object) array( 'Sender' => 'previous@example.org', 'From' => Prihod\Profile::get()['email'], 'Mailer' => 'mail' );
    do_action( 'phpmailer_init', $mailer );
    prihod_assert( Prihod\Profile::get()['email'] === $mailer->Sender, 'This PHP-mail reply uses the configured parish email as its envelope sender.' );
    $mailers[] = $mailer; return true;
};
add_filter( 'pre_http_request', $telegram_transport, PHP_INT_MAX, 3 );
add_filter( 'pre_wp_mail', $capture_mail, PHP_INT_MAX, 2 ); add_filter( 'rest_url', $rest_https );
$created_requests = array();
try {
    prihod_assert( in_array( 'target_missing', $telegram->telegram_errors( array( 'token' => $fixture_config['token'], 'target' => '' ) ), true ), 'Missing Chat ID has a specific field error.' );
    $_GET['prihod_connect_error'] = 'target_missing'; ob_start(); $telegram->render(); $settings_html = ob_get_clean(); $_GET = $saved_get;
    prihod_assert( str_contains( $settings_html, 'Укажите Chat ID' ) && ! str_contains( $settings_html, $fixture_config['token'] ) && str_contains( $settings_html, 'telegram_reply_staff' ), 'Settings show field error, preserve secret and expose own reply configuration.' );
    $_POST = array( '_wpnonce' => wp_create_nonce( 'prihod_connect_telegram' ), 'enabled' => '1', 'notify_requests' => '1', 'target' => '' );
    $_REQUEST = $_POST;
    $redirect_capture = static function ( $url ) { if ( ! str_contains( $url, 'target_missing' ) || str_contains( $url, 'TEST_ONLY' ) ) { throw new RuntimeException( 'Unexpected validation redirect.' ); } throw new RuntimeException( 'FIELD_ERROR_REDIRECT' ); };
    add_filter( 'wp_redirect', $redirect_capture );
    try { $telegram->save(); prihod_assert( false, 'Missing recipient cannot save.' ); } catch ( RuntimeException $error ) { prihod_assert( 'FIELD_ERROR_REDIRECT' === $error->getMessage() && $fixture_config === get_option( 'prihod_connect_telegram' ), 'Real save handler redirects with a specific error and preserves old settings without leaking credentials.' ); }
    remove_filter( 'wp_redirect', $redirect_capture ); $_POST = $saved_post; $_REQUEST = $saved_request;
    $foreign_webhook = true; $before_registration = get_option( Prihod\Telegram_Requests::OPTION );
    prihod_assert( is_wp_error( Prihod\Telegram_Request_Settings::configure() ) && ! in_array( 'setWebhook', $api_calls, true ) && $before_registration === get_option( Prihod\Telegram_Requests::OPTION ), 'A webhook belonging to another application is never replaced.' );
    $foreign_webhook = false;
    prihod_assert( true === Prihod\Telegram_Request_Settings::configure() && Prihod\Telegram_Requests::ready(), 'Real API registration path uses the intercepted transport and enables configured private replies.' );
    $before_queue_api = count( $api_calls );
    prihod_assert( true === Prihod\Telegram_Request_Settings::queue_registration( 1 ) && count( $api_calls ) === $before_queue_api, 'Owner registration action queues network work instead of blocking settings save.' );
    $queued_o = Prihod\Telegram_Requests::options();
    Prihod\Telegram_Request_Settings::register_worker( 1, $queued_o['connection_job'], $queued_o['configuration'] );
    prihod_assert( Prihod\Telegram_Requests::ready() && empty( Prihod\Telegram_Requests::options()['connection_job'] ), 'Registered background worker completes the same verified setup path.' );
    $body = str_repeat( '🙂', 2200 ) . "\nFull visitor question & answer\n\nSecond paragraph\nTAIL_FIXTURE";
    $id = Prihod\Requests::create( array( 'name' => 'Local Visitor', 'email' => 'visitor@example.org', 'subject' => 'Local full request', 'body' => $body ) ); $created_requests[] = $id;
    $jobs = get_post_meta( $id, '_prihod_jobs_telegram', true );
    prihod_assert( count( $jobs ) > 1 && count( $jobs ) === count( array_unique( $jobs ) ), 'Full Unicode request is chunked without duplicating the ordinary generic notification.' );
    foreach ( $jobs as $job ) { $telegram->worker( $job ); }
    $full_text = implode( "\n", $sent_texts );
    prihod_assert( str_contains( $full_text, 'Local Visitor' ) && str_contains( $full_text, 'visitor@example.org' ) && str_contains( $full_text, 'Local full request' ) && str_contains( $full_text, 'TAIL_FIXTURE' ), 'Name, email, subject and complete question reach the selected fake transport, including the final chunk.' );
    prihod_assert( str_contains( $full_text, 'question &amp; answer') && str_contains( $full_text, "\n\nSecond paragraph" ), 'Visitor text is escaped for Telegram HTML without losing paragraph breaks.' );
    prihod_assert( count( $jobs ) === count( get_post_meta( $id, '_prihod_telegram_message' ) ), 'Accepted message IDs are mapped privately to their existing request.' );
    $make_reply = static function ( array $overrides = array(), string $secret = '' ) {
        $update = array_replace_recursive( array( 'update_id' => 700, 'message' => array( 'chat' => array( 'id' => 67890, 'type' => 'private' ), 'from' => array( 'id' => 67890, 'is_bot' => false ), 'text' => "Local answer\nSecond paragraph", 'reply_to_message' => array( 'message_id' => 501, 'from' => array( 'id' => 12345, 'is_bot' => true ) ) ) ), $overrides );
        $request = new WP_REST_Request( 'POST', '/prihod/v1/telegram-reply' );
        $request->set_header( 'Content-Type', 'application/json' ); $request->set_header( 'X-Telegram-Bot-Api-Secret-Token', $secret ?: Prihod\Telegram_Requests::options()['secret'] ); $request->set_body( wp_json_encode( $update ) ); return $request;
    };
    prihod_assert( is_wp_error( Prihod\Telegram_Requests::receive( $make_reply( array(), 'wrong-secret' ) ) ), 'A forged webhook cannot read or answer an appeal.' );
    foreach ( array( array( 'from' => array( 'id' => 99999 ) ), array( 'chat' => array( 'type' => 'group' ) ), array( 'reply_to_message' => array( 'message_id' => 99999 ) ), array( 'reply_to_message' => array( 'from' => array( 'id' => 77777 ) ) ), array( 'text' => '<b></b>' ) ) as $bad ) { Prihod\Telegram_Requests::receive( $make_reply( array( 'message' => $bad ) ) ); }
    prihod_assert( ! Prihod\Requests::messages( $id ) && ! $emails, 'Other senders, groups, unmapped messages, wrong bots and empty replies create no history or email.' );
    $original_user = get_current_user_id(); wp_set_current_user( 0 );
    $result = rest_get_server()->dispatch( $make_reply() );
    prihod_assert( 200 === $result->get_status() && 0 === get_current_user_id(), 'Actual REST callback accepts the authenticated update and restores the previous WordPress user.' );
    Prihod\Telegram_Requests::receive( $make_reply() ); $history = Prihod\Requests::messages( $id );
    prihod_assert( 1 === count( $history ) && 1 === (int) $history[0]->post_author && 'sent' === get_post_meta( $history[0]->ID, '_prihod_delivery', true ) && 1 === count( $emails ) && ! wp_next_scheduled( 'prihod_request_mail', array( $id, $history[0]->ID ) ), 'Telegram reply sends HTML email immediately; duplicate update creates neither another reply nor another mail.' );
    Prihod\Requests::deliver( $id, $history[0]->ID ); Prihod\Requests::deliver( $id, $history[0]->ID );
    prihod_assert( 1 === count( $emails ) && 'visitor@example.org' === $emails[0]['to'] && in_array( 'Content-Type: text/html; charset=UTF-8', $emails[0]['headers'], true ) && str_contains( $emails[0]['message'], '<table' ) && str_contains( $emails[0]['message'], 'Local answer') && str_contains( $emails[0]['message'], 'TAIL_FIXTURE' ), 'Existing mail worker sends one branded HTML reply with original question to the visitor, using only intercepted mail.' );
    prihod_assert( 'previous@example.org' === $mailers[0]->Sender && empty( $GLOBALS['wp_filter']['phpmailer_init']->callbacks ) && str_contains( implode( "\n", $emails[0]['headers'] ), '<' . Prihod\Profile::get()['email'] . '>' ), 'From uses the parish email; envelope and hook are restored before another plugin sends mail.' );
    wp_set_current_user( $original_user );
    $preview_question = clone get_post( $id ); $preview_question->post_title = 'Посещение монастыря'; $preview_question->post_content = 'Здравствуйте! Подскажите, пожалуйста, как подготовиться к посещению монастыря и когда можно приехать на богослужение?';
    $preview_reply = clone $history[0]; $preview_reply->post_content = "Здравствуйте! Будем рады видеть вас в нашей обители.\n\nПеред поездкой посмотрите расписание богослужений на сайте. Если планируете приехать группой, пожалуйста, заранее свяжитесь с нами.\n\nМира и Божией помощи!";
    file_put_contents( __DIR__ . '/.telegram-mail-preview.html', Prihod\Request_Mail::render( $preview_question, $preview_reply, array( 'name' => 'Мария' ) ) );
    $preview_reply->post_content = '<script>alert("fixture")</script> & plain text';
    prihod_assert( ! str_contains( Prihod\Request_Mail::render( $preview_question, $preview_reply, array( 'name' => '<img>' ) ), '<script>' ), 'Visitor email escapes stored reply and sender markup.' );
    $note = Prihod\Requests::message( $id, 'Internal fixture note', true ); Prihod\Requests::deliver( $id, $note );
    prihod_assert( 1 === count( $emails ) && ! str_contains( implode( "\n", $sent_texts ), 'Internal fixture note' ), 'Internal notes never reach Telegram or visitor mail.' );
    $preview_id = Prihod\Requests::create( array( 'name' => 'Мария', 'email' => 'visitor@example.org', 'subject' => 'О посещении храма', 'body' => "Здравствуйте! Можно ли приехать на воскресную литургию?\n\nБудем вдвоём. Подскажите, пожалуйста, как подготовиться к посещению." ) ); $created_requests[] = $preview_id;
    $preview_jobs = get_post_meta( $preview_id, '_prihod_jobs_telegram', true ); $preview_job = get_option( 'prihod_job_telegram_' . $preview_jobs[0] );
    file_put_contents( __DIR__ . '/.telegram-message-preview.html', '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="background:#dcead1;font:15px/1.5 Arial,sans-serif;padding:24px"><p>Образец форматирования · вымышленные данные</p><div style="max-width:520px;background:white;padding:20px;border-radius:14px;white-space:pre-wrap;overflow-wrap:anywhere">' . $preview_job['text'] . '</div></body></html>' );
    $draft = Prihod\Requests::create( array( 'name' => 'Fixture', 'email' => 'fixture@example.org', 'subject' => 'Changed bot', 'body' => 'No disclosure after bot changes.' ) ); $created_requests[] = $draft;
    $pending_jobs = get_post_meta( $draft, '_prihod_jobs_telegram', true ); $before_send = count( $sent_texts );
    update_option( 'prihod_connect_telegram', array_merge( $fixture_config, array( 'token' => '12346:TEST_ONLY_OTHER_BOT_TOKEN' ) ) );
    foreach ( $pending_jobs as $job ) { $telegram->worker( $job ); }
    prihod_assert( count( $sent_texts ) === $before_send && 'cancelled' === get_option( 'prihod_job_telegram_' . $pending_jobs[0] )['status'], 'Changing the bot invalidates queued full data before transport, even for the same target Chat ID.' );
    update_option( 'prihod_connect_telegram', $fixture_config );
    foreach ( $created_requests as $request_id ) { Prihod\Requests::delete( $request_id ); }
    prihod_assert( ! get_post( $id ) && ! get_option( 'prihod_job_telegram_' . $jobs[0] ), 'Deleting a request removes its mappings, reply history and Telegram job payloads.' );
} finally {
    foreach ( $created_requests as $request_id ) { if ( get_post( $request_id ) ) { Prihod\Requests::delete( $request_id ); } }
    remove_filter( 'pre_http_request', $telegram_transport, PHP_INT_MAX ); remove_filter( 'pre_wp_mail', $capture_mail, PHP_INT_MAX ); remove_filter( 'rest_url', $rest_https );
    update_option( 'prihod_connect_telegram', $saved_telegram ); update_option( Prihod\Telegram_Requests::OPTION, $saved_full ?: array() ); update_option( 'prihod_modules', $saved_modules );
    $_POST = $saved_post; $_GET = $saved_get; $_REQUEST = $saved_request; wp_set_current_user( 1 );
}
