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
Prihod\Requests::retry( $request, $failed );
prihod_assert( 'pending' === get_post_meta( $failed, '_prihod_delivery', true ), 'Staff can explicitly retry an existing failed answer without creating a second message.' );
$export = Prihod\Request_Privacy::export( 'visitor@example.org' );
prihod_assert( 1 === count( $export['data'] ) && $export['done'], 'Privacy exporter includes the request and history.' );
prihod_assert( str_contains( Prihod\Request_Form::render(), 'name="consent"' ), 'Public form requires consent and uses registered handler.' );
$form_html = Prihod\Request_Form::render();
prihod_assert( str_contains( $form_html, '<select name="subject" required' ) && str_contains( $form_html, 'value="__other"' ) && str_contains( $form_html, 'placeholder="name@example.org"' ) && str_contains( $form_html, 'prihod-required-help' ), 'Native request form exposes topic choices, hints and required-field guidance.' );
$preset = Prihod\Request_Form::topics()[0];
prihod_assert( $preset === Prihod\Request_Form::submission_data( array( 'subject' => $preset, 'subject_other' => 'Ignore stale custom text' ) )['subject'], 'Preset selection cannot be overwritten by stale custom text.' );
prihod_assert( 'Legacy subject' === Prihod\Request_Form::submission_data( array( 'subject' => 'Legacy subject' ) )['subject'], 'Legacy free-text submissions remain compatible.' );
foreach ( array( '', array( 'invalid' ) ) as $bad_subject ) {
    try { Prihod\Request_Form::submission_data( array( 'subject' => '__other', 'subject_other' => $bad_subject ) ); prihod_assert( false, 'Invalid custom subject must fail.' ); }
    catch ( InvalidArgumentException $error ) { prihod_assert( true, 'Empty or malformed custom subject is rejected.' ); }
}
// Exercise the registered submit handler in this disposable database; intercept only its redirect.
$saved_post_input = $_POST; $submission_mail_count = 0;
$no_test_mail = function () use ( &$submission_mail_count ) { ++$submission_mail_count; return true; };
add_filter( 'pre_wp_mail', $no_test_mail );
foreach ( array( $preset, '__other' ) as $topic ) {
    $form_tags = new WP_HTML_Tag_Processor( Prihod\Request_Form::render() ); $form_token = '';
    while ( $form_tags->next_tag( 'INPUT' ) ) { if ( 'token' === $form_tags->get_attribute( 'name' ) ) { $form_token = $form_tags->get_attribute( 'value' ); break; } }
    $_POST = array( '_wpnonce' => wp_create_nonce( 'prihod_request_submit' ), 'token' => $form_token, 'consent' => '1', 'name' => 'Local fixture', 'email' => 'fixture@example.org', 'subject' => $topic, 'subject_other' => 'My own fixture subject', 'body' => 'Local handler verification only.' );
    $redirect_url = '';
    $capture_redirect = function ( $url ) use ( &$redirect_url ) { $redirect_url = $url; throw new RuntimeException( 'PRIHOD_TEST_REDIRECT' ); };
    add_filter( 'wp_redirect', $capture_redirect );
    try { Prihod\Request_Form::submit(); } catch ( RuntimeException $error ) { if ( 'PRIHOD_TEST_REDIRECT' !== $error->getMessage() ) { throw $error; } }
    remove_filter( 'wp_redirect', $capture_redirect );
    $submitted = get_posts( array( 'post_type' => 'prihod_request', 'post_status' => 'private', 'numberposts' => 1, 'orderby' => 'ID', 'order' => 'DESC' ) )[0];
    prihod_assert( str_contains( $redirect_url, 'prihod_request=sent' ) && $submitted->post_title === ( '__other' === $topic ? 'My own fixture subject' : $preset ), 'The existing handler stores the selected or custom subject privately.' );
    prihod_assert( ! get_transient( 'prihod_form_' . hash( 'sha256', $form_token ) ), 'Successful topic submission consumes the existing one-use token.' );
    Prihod\Requests::delete( $submitted->ID );
}
remove_filter( 'pre_wp_mail', $no_test_mail ); $_POST = $saved_post_input;
prihod_assert( 0 === $submission_mail_count, 'Creating the two local topic requests sends no email.' );
for ( $attempt = 0; $attempt < 5; ++$attempt ) { Prihod\Request_Form::rate_limit( 'prihod_test_rate' ); }
try { Prihod\Request_Form::rate_limit( 'prihod_test_rate' ); prihod_assert( false, 'Sixth submission must be limited.' ); }
catch ( RuntimeException $error ) { prihod_assert( true, 'Rate limit rejects excess submissions.' ); }
update_option( 'prihod_modules', array( 'schedule' => true, 'requests' => false ) );
prihod_assert( '' === Prihod\Request_Form::render() && get_post( $request ), 'Disabled request module retains records.' );
$erase = Prihod\Request_Privacy::erase( 'visitor@example.org' );
prihod_assert( $erase['items_removed'] && ! get_post( $request ) && ! get_post( $note ) && ! get_post( $reply ), 'Privacy erasure removes request and private messages even when module is disabled.' );
prihod_assert( ! wp_next_scheduled( 'prihod_request_mail', array( $request, $failed ) ), 'Deletion cancels queued personal-data delivery.' );
update_option( 'prihod_modules', array( 'schedule' => true, 'requests' => true ) );
