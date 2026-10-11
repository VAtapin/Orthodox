<?php
update_option( 'prihod_modules', array( 'schedule' => true, 'requests' => true ) );
$lock_token = Prihod\Lock::acquire( 'prihod_test_lock' );
prihod_assert( $lock_token && '' === Prihod\Lock::acquire( 'prihod_test_lock' ), 'Database lock rejects concurrent acquisition.' );
Prihod\Lock::release( 'prihod_test_lock', 'wrong-owner' );
prihod_assert( '' === Prihod\Lock::acquire( 'prihod_test_lock' ), 'Wrong owner cannot release a lock.' );
Prihod\Lock::release( 'prihod_test_lock', $lock_token );
Prihod\Requests::register();
$request = Prihod\Requests::create( array( 'name' => '<b>Visitor</b>', 'email' => 'visitor@example.org', 'subject' => 'Question', 'body' => 'Please answer.' ) );
prihod_assert( 'private' === get_post_status( $request ) && 'Visitor' === get_post_meta( $request, '_prihod_sender', true )['name'], 'Guest request persists privately with sanitized sender.' );
wp_set_current_user( $editor_id );
prihod_assert( ! current_user_can( 'read_post', $request ), 'Editor cannot read private requests.' );
try { Prihod\Requests::message( $request, 'Unauthorized', false ); prihod_assert( false, 'Editor reply should fail.' ); }
catch ( RuntimeException $error ) { prihod_assert( true, 'Reply requires view and reply capabilities.' ); }
wp_set_current_user( 1 );
$note = Prihod\Requests::message( $request, 'Internal note', true );
prihod_assert( ! wp_next_scheduled( 'prihod_request_mail', array( $request, $note ) ) && 'prihod_message' === get_post_type( $note ), 'Notes are private messages and never queued for email.' );
$reply = Prihod\Requests::message( $request, 'Answer', false );
prihod_assert( 'pending' === get_post_meta( $reply, '_prihod_delivery', true ) && wp_next_scheduled( 'prihod_request_mail', array( $request, $reply ) ), 'Email reply is queued asynchronously.' );
$mail_count = 0;
$mail_filter = function ( $return, $attributes ) use ( &$mail_count ) { ++$mail_count; prihod_assert( 'visitor@example.org' === $attributes['to'], 'Reply uses validated sender email.' ); return true; };
add_filter( 'pre_wp_mail', $mail_filter, 10, 2 );
Prihod\Requests::deliver( $request, $reply );
Prihod\Requests::deliver( $request, $reply );
remove_filter( 'pre_wp_mail', $mail_filter, 10 );
prihod_assert( 1 === $mail_count && 'sent' === get_post_meta( $reply, '_prihod_delivery', true ), 'Delivery does not resend a completed message.' );
$failed = Prihod\Requests::message( $request, 'Retry answer', false );
$mail_failure = fn() => false;
add_filter( 'pre_wp_mail', $mail_failure );
Prihod\Requests::deliver( $request, $failed );
remove_filter( 'pre_wp_mail', $mail_failure );
prihod_assert( 'failed' === get_post_meta( $failed, '_prihod_delivery', true ), 'Mail failure is visible in message history.' );
$export = Prihod\Request_Privacy::export( 'visitor@example.org' );
prihod_assert( 1 === count( $export['data'] ) && $export['done'], 'Privacy exporter includes the request and history.' );
prihod_assert( str_contains( Prihod\Request_Form::render(), 'name="consent"' ), 'Public form requires consent and uses registered handler.' );
for ( $attempt = 0; $attempt < 5; ++$attempt ) { Prihod\Request_Form::rate_limit( 'prihod_test_rate' ); }
try { Prihod\Request_Form::rate_limit( 'prihod_test_rate' ); prihod_assert( false, 'Sixth submission must be limited.' ); }
catch ( RuntimeException $error ) { prihod_assert( true, 'Rate limit rejects excess submissions.' ); }
update_option( 'prihod_modules', array( 'schedule' => true, 'requests' => false ) );
prihod_assert( '' === Prihod\Request_Form::render() && get_post( $request ), 'Disabled request module retains records.' );
$erase = Prihod\Request_Privacy::erase( 'visitor@example.org' );
prihod_assert( $erase['items_removed'] && ! get_post( $request ) && ! get_post( $note ) && ! get_post( $reply ), 'Privacy erasure removes request and private messages even when module is disabled.' );
prihod_assert( ! wp_next_scheduled( 'prihod_request_mail', array( $request, $failed ) ), 'Deletion cancels queued personal-data delivery.' );
update_option( 'prihod_modules', array( 'schedule' => true, 'requests' => true ) );
