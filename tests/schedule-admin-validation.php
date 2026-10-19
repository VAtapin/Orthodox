<?php
/** Run inside the disposable parish-merge fixture, never on an owner site. */
use GeorgKlosterPrihodSchedule\Schedule_Admin;

$validation_post = wp_insert_post( array( 'post_type' => 'gkpsch_service', 'post_status' => 'auto-draft', 'post_title' => 'Untitled service' ) );
$validation_input = array_merge( $fixture['rule'], array( 'date' => '2026-12-25', 'time' => '07:45', 'end' => '09:15', 'location_id' => '0' ) );
$die_message = '';
$die_args = array();
$die_handler = static function () use ( &$die_message, &$die_args ) {
    return static function ( $message, $title, $args ) use ( &$die_message, &$die_args ) {
        $die_message = $message; $die_args = $args;
        throw new RuntimeException( 'service_validation_stopped' );
    };
};
add_filter( 'wp_die_handler', $die_handler );
$rejected = static function ( int $id, array $input ) use ( $check ) {
    $_POST = array( 'gkpsch_service_nonce' => wp_create_nonce( 'gkpsch_service_' . $id ), 'gkpsch_service' => $input );
    $blocked = false;
    try { wp_update_post( array( 'ID' => $id, 'post_title' => 'Attempted title', 'post_content' => 'Attempted description', 'post_status' => 'publish' ) ); }
    catch ( RuntimeException $error ) { if ( 'service_validation_stopped' !== $error->getMessage() ) { throw $error; } $blocked = true; }
    $check( $blocked, 'Invalid service was accepted by the WordPress save pipeline.' );
};
$rejected( $validation_post, $validation_input );
$check( str_contains( $die_message, 'Выберите место служения перед сохранением.' ) && str_contains( $die_message, 'Богослужение не сохранено.' ), 'Missing-place error or unsaved warning is not explicit and Russian.' );
$check( 400 === $die_args['response'] && true === $die_args['back_link'], 'Server fallback lacks error status or a way back to the form.' );
$check( 'auto-draft' === get_post_status( $validation_post ) && 'Untitled service' === get_the_title( $validation_post ) && '' === get_post_field( 'post_content', $validation_post ), 'Invalid new service was partially published or changed.' );
$check( '' === get_post_meta( $validation_post, '_gkpsch_service', true ), 'Invalid new service stored a partial rule.' );
$check( '07:45' === $_POST['gkpsch_service']['time'] && '2026-12-25' === $_POST['gkpsch_service']['date'] && '09:15' === $_POST['gkpsch_service']['end'], 'Failed validation rewrote submitted date/time.' );
$_POST = array();
$original_post = get_post( $fixture['service'] );
$original_rule = get_post_meta( $fixture['service'], '_gkpsch_service', true );
$revisions = count( wp_get_post_revisions( $fixture['service'] ) );
$rejected( $fixture['service'], $validation_input );
$check( $original_post->post_title === get_the_title( $fixture['service'] ) && $original_post->post_content === get_post_field( 'post_content', $fixture['service'] ) && $original_post->post_status === get_post_status( $fixture['service'] ), 'Invalid update changed the existing service post.' );
$check( $original_rule === get_post_meta( $fixture['service'], '_gkpsch_service', true ) && $revisions === count( wp_get_post_revisions( $fixture['service'] ) ), 'Invalid update changed the existing rule or created a revision.' );
$rejected( $validation_post, array_merge( $validation_input, array( 'location_id' => '' ) ) );
$rejected( $validation_post, array_merge( $validation_input, array( 'location_id' => $bad_place ) ) );
$rejected( $validation_post, array_merge( $validation_input, array( 'location_id' => $fixture['place'], 'time' => '26:00' ) ) );
$check( str_contains( $die_message, 'Проверьте тип, даты и время службы.' ), 'Other validation errors bypass the pre-save guard.' );
remove_filter( 'wp_die_handler', $die_handler );
$validation_input['location_id'] = $fixture['place'];
$_POST = array( 'gkpsch_service_nonce' => wp_create_nonce( 'gkpsch_service_' . $validation_post ), 'gkpsch_service' => $validation_input );
$saved = wp_update_post( array( 'ID' => $validation_post, 'post_title' => 'Complete service', 'post_status' => 'publish' ), true );
$check( $validation_post === $saved && 'publish' === get_post_status( $validation_post ), 'Correcting the place did not allow normal publication.' );
$saved_rule = get_post_meta( $validation_post, '_gkpsch_service', true );
$check( '2026-12-25' === $saved_rule['date'] && '07:45' === $saved_rule['time'] && '09:15' === $saved_rule['end'] && $fixture['place'] === $saved_rule['location_id'], 'Valid save lost date/time/place.' );
$_POST = array( 'gkpsch_service_nonce' => 'invalid', 'gkpsch_service' => array( 'location_id' => '0' ) );
$data = array( 'post_type' => 'gkpsch_service' );
$check( $data === Schedule_Admin::before_save( $data, array( 'ID' => $validation_post ) ), 'Pre-save guard intercepted a request without a valid plugin nonce.' );
$_POST = array();
$check( $data === Schedule_Admin::before_save( $data, array( 'ID' => $validation_post ) ), 'Pre-save guard intercepted programmatic saves without editor fields.' );
ob_start(); Schedule_Admin::render( get_post( $validation_post ) ); $validation_editor = ob_get_clean();
$check( 1 === preg_match( '/<select name="gkpsch_service\[location_id\]" required aria-required="true"[^>]*><option value="">/', $validation_editor ), 'Main place selector is not marked required with an empty placeholder.' );
$check( str_contains( $validation_editor, 'data-required-message="Выберите место служения перед сохранением."' ), 'Browser validation message is not localized.' );
$check( false !== has_action( 'admin_notices', array( Schedule_Admin::class, 'notice' ) ), 'Fallback save errors are still never displayed.' );
wp_delete_post( $validation_post, true );
