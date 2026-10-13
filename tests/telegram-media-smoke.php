<?php
// Runs inside the disposable authenticated Telegram scenario; no real transport.
$reply_count = count( Prihod\Requests::messages( $id ) ); $mail_count = count( $emails );
$second = $make_reply( array( 'update_id' => 701, 'message' => array( 'text' => 'Another answer after answered status' ) ) );
Prihod\Telegram_Requests::receive( $second ); Prihod\Telegram_Requests::receive( $second );
prihod_assert( $reply_count + 1 === count( Prihod\Requests::messages( $id ) ) && $mail_count + 1 === count( $emails ), 'Another reply to an answered request appends its own history/email; duplicate webhook adds neither.' );
$download_calls = 0; $unsafe_path = false; $download_error = false; $sticker_mode = false;
$png = file_get_contents( __DIR__ . '/fixtures/brand-square.png' );
$media_transport = static function ( $pre, $args, $url ) use ( &$download_calls, &$unsafe_path, &$download_error, &$sticker_mode, $png ) {
    $response = static fn( $body ) => array( 'headers' => array(), 'response' => array( 'code' => 200 ), 'body' => $body, 'cookies' => array() );
    if ( str_ends_with( $url, '/getFile' ) ) { return $response( wp_json_encode( array( 'ok' => true, 'result' => array( 'file_path' => $unsafe_path ? '../private.png' : ( $sticker_mode ? 'stickers/fixture.webp' : 'photos/fixture.png' ), 'file_size' => strlen( $png ) ) ) ) ); }
    if ( str_starts_with( $url, 'https://api.telegram.org/file/bot12345:TEST_ONLY_NOT_A_REAL_TOKEN/' ) ) {
        ++$download_calls;
        prihod_assert( 0 === $args['redirection'] && Prihod\Request_Media::MAX_BYTES + 1 === $args['limit_response_size'], 'Private image download has no redirects and enforces a byte limit.' );
        if ( $download_error ) { return new WP_Error( 'offline', 'Fixture download failure.' ); }
        if ( $sticker_mode ) { $image = imagecreatefromstring( $png ); ob_start(); imagewebp( $image ); $bytes = ob_get_clean(); unset( $image ); return $response( $bytes ); }
        return $response( $png );
    }
    return $pre;
};
add_filter( 'pre_http_request', $media_transport, PHP_INT_MAX, 3 );
try {
    $photo = $make_reply( array( 'update_id' => 702, 'message' => array( 'text' => null, 'caption' => "Photo caption\nSecond line", 'photo' => array( array( 'file_id' => 'fixture_photo', 'width' => 512, 'height' => 512, 'file_size' => strlen( $png ) ) ) ) ) );
    $attachment_count = wp_count_posts( 'attachment' )->inherit; $before = count( $emails );
    Prihod\Telegram_Requests::receive( $photo ); Prihod\Telegram_Requests::receive( $photo );
    $latest = array_slice( Prihod\Requests::messages( $id ), -1 )[0];
    $photo_reply_id = $latest->ID;
    $media = get_post_meta( $latest->ID, '_prihod_reply_media', true );
    prihod_assert( $before + 1 === count( $emails ) && 1 === $download_calls && 'private' === $latest->post_status && 'image/png' === $media['mime'] && $png === base64_decode( $media['data'] ), 'Photo and caption create one private history record/email; webhook redelivery performs no download or send.' );
    $mime = end( $mime_emails ); $html = end( $emails )['message'];
    prihod_assert( str_contains( $html, 'cid:' . Prihod\Request_Media::cid( $latest->ID ) ) && str_contains( $html, 'Photo caption' ) && str_contains( $mime, 'Content-ID: <' . Prihod\Request_Media::cid( $latest->ID ) . '>' ) && str_contains( $mime, 'multipart/alternative' ), 'Email embeds real image bytes by CID alongside caption and a readable plain-text alternative.' );
    prihod_assert( $attachment_count === wp_count_posts( 'attachment' )->inherit && ! str_contains( $html, 'api.telegram.org' ) && ! str_contains( $html, 'TEST_ONLY' ), 'Private reply images never become public media attachments, bot URLs or tokens.' );
    $last_mailer = end( $mailers );
    prihod_assert( array() === $last_mailer->getAttachments() && 'Previous unrelated text' === $last_mailer->AltBody, 'Inline images and alternative body do not leak into another plugin mail.' );
    $admin = Prihod\Request_Media::admin_markup( $latest->ID );
    prihod_assert( str_contains( $admin, 'action=prihod_reply_image' ) && str_contains( $admin, '_wpnonce' ) && ! str_contains( $admin, 'data:image' ), 'Admin image uses a protected endpoint rather than public uploads or inline private bytes.' );
    $current = get_current_user_id(); wp_set_current_user( 1 ); $nonce = wp_create_nonce( 'prihod_reply_image_' . $latest->ID );
    prihod_assert( Prihod\Request_Media::can_view( $latest->ID, $nonce ) && ! Prihod\Request_Media::can_view( $latest->ID, 'forged' ) && ! Prihod\Request_Media::can_view( $latest->ID, array() ), 'Staff image access requires a valid message-specific nonce.' );
    wp_set_current_user( 0 );
    prihod_assert( ! Prihod\Request_Media::can_view( $latest->ID, $nonce ) && ! has_action( 'admin_post_nopriv_prihod_reply_image' ), 'Anonymous visitors cannot access private reply images even with the staff URL.' );
    wp_set_current_user( $editor_id );
    prihod_assert( ! Prihod\Request_Media::can_view( $latest->ID, $nonce ), 'Ordinary editors cannot view correspondence images.' );
    wp_set_current_user( $current );
    $export = Prihod\Request_Privacy::export( 'visitor@example.org' );
    prihod_assert( in_array( 'data:image/png;base64,' . $media['data'], array_merge( ...array_map( static fn( $group ) => array_column( $group['data'], 'value' ), $export['data'] ) ), true ), 'The standard personal-data export includes private reply image bytes.' );
    $view_reply = clone $latest; $view_reply->post_content = "Здравствуйте! Будем рады видеть вас в нашей обители.\n\nК ответу прикладываю изображение. Перед поездкой посмотрите расписание богослужений.";
    $view = Prihod\Request_Mail::render( $preview_question, $view_reply, array( 'name' => 'Мария' ) );
    file_put_contents( __DIR__ . '/.telegram-media-mail-preview.html', str_replace( 'cid:' . Prihod\Request_Media::cid( $latest->ID ), 'data:image/png;base64,' . $media['data'], $view ) );
    $sticker_mode = true; $before = count( $emails );
    $sticker = $make_reply( array( 'update_id' => 703, 'message' => array( 'text' => null, 'sticker' => array( 'file_id' => 'fixture_sticker', 'is_animated' => false, 'is_video' => false ) ) ) );
    Prihod\Telegram_Requests::receive( $sticker );
    $latest = array_slice( Prihod\Requests::messages( $id ), -1 )[0];
    prihod_assert( $before + 1 === count( $emails ) && 'image/png' === get_post_meta( $latest->ID, '_prihod_reply_media', true )['mime'], 'A real static WebP sticker is converted to PNG and sent even without text.' );
    $before = count( $emails ); $downloads_before = $download_calls;
    $animated = $make_reply( array( 'update_id' => 704, 'message' => array( 'text' => null, 'sticker' => array( 'file_id' => 'fixture_animation', 'is_animated' => true, 'is_video' => false ) ) ) );
    Prihod\Telegram_Requests::receive( $animated ); Prihod\Telegram_Requests::receive( $animated );
    $latest = array_slice( Prihod\Requests::messages( $id ), -1 )[0];
    prihod_assert( $before === count( $emails ) && $downloads_before === $download_calls && get_post_meta( $latest->ID, '_prihod_internal', true ), 'Unsupported animated sticker creates a deduplicated visible internal explanation, without pretending to email it.' );
    $sticker_mode = false; $unsafe_path = true;
    Prihod\Telegram_Requests::receive( $make_reply( array( 'update_id' => 705, 'message' => array( 'text' => null, 'photo' => array( array( 'file_id' => 'fixture_path', 'width' => 512, 'height' => 512 ) ) ) ) ) );
    prihod_assert( $downloads_before === $download_calls && $before === count( $emails ), 'Path traversal and unsafe file URLs are rejected before network download.' );
    $unsafe_path = false; $download_error = true;
    $transient = $make_reply( array( 'update_id' => 706, 'message' => array( 'text' => null, 'photo' => array( array( 'file_id' => 'fixture_retry', 'width' => 512, 'height' => 512 ) ) ) ) );
    $history_before = count( Prihod\Requests::messages( $id ) );
    prihod_assert( is_wp_error( Prihod\Telegram_Requests::receive( $transient ) ) && $history_before === count( Prihod\Requests::messages( $id ) ), 'Temporary download failure leaves no false reply receipt and asks Telegram to retry.' );
    $download_error = false; Prihod\Telegram_Requests::receive( $transient );
    prihod_assert( $before + 1 === count( $emails ) && $history_before + 1 === count( Prihod\Requests::messages( $id ) ), 'Retry after download recovery creates exactly one actual image reply.' );
} finally { remove_filter( 'pre_http_request', $media_transport, PHP_INT_MAX ); }
