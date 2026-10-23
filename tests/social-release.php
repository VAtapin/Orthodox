<?php
require_once '/wordpress/wp-load.php';

function gkps_assert( bool $condition, string $message ): void {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}

gkps_assert( class_exists( 'GeorgKlosterPrihodSocial\\Plugin' ), 'Social did not load.' );
gkps_assert( ! class_exists( 'GeorgKlosterPrihodCore\\Profile' ) && ! class_exists( 'Prihod\\Plugin' ), 'Social depends on another project plugin.' );
gkps_assert( post_type_exists( 'gkps_request' ) && post_type_exists( 'gkps_message' ), 'Private post types missing.' );
gkps_assert( WP_Block_Type_Registry::get_instance()->is_registered( 'gkps/contact-form' ), 'Contact block missing.' );
gkps_assert( '' === do_shortcode( '[gkps_contact_form]' ), 'Form should be disabled initially.' );

update_option( 'gkps_form', array( 'enabled' => true, 'title' => 'Ask a stylist', 'intro' => 'Describe your appointment.', 'submit' => 'Send request', 'topics' => "Haircut\nColour", 'recipient' => '' ) );
$html = do_shortcode( '[gkps_contact_form]' );
gkps_assert( str_contains( $html, 'Ask a stylist' ) && str_contains( $html, 'Haircut' ) && str_contains( $html, 'Send request' ), 'Configurable form was not rendered.' );
gkps_assert( str_contains( do_shortcode( '[gkps_contact_form title="Schreiben Sie uns" submit="Senden"]' ), 'Schreiben Sie uns' ), 'Per-page form title was not rendered.' );
gkps_assert( ! str_contains( $html, 'Обращения к священнику' ), 'The form is parish-specific.' );
GeorgKlosterPrihodSocial\Request_Form::enqueue_styles();
gkps_assert( wp_style_is( 'gkps-request-form', 'enqueued' ), 'Standalone form stylesheet missing.' );

$request = GeorgKlosterPrihodSocial\Requests::create( array( 'name' => 'Visitor', 'email' => 'visitor@example.org', 'subject' => 'Haircut', 'body' => 'Please contact me.' ) );
gkps_assert( $request > 0 && 'gkps_request' === get_post_type( $request ), 'Message was not stored.' );
gkps_assert( get_post_meta( $request, '_gkps_sender', true )['email'] === 'visitor@example.org', 'Sender was not stored.' );
gkps_assert( GeorgKlosterPrihodSocial\Connector::instance( 'telegram' ) !== null && null === GeorgKlosterPrihodSocial\Connector::instance( 'whatsapp' ) && GeorgKlosterPrihodSocial\Connector::instance( 'facebook' ) !== null, 'One channel is missing.' );
foreach ( array( 'telegram', 'facebook' ) as $id ) {
    gkps_assert( ! GeorgKlosterPrihodSocial\Connector::instance( $id )->settings()['enabled'], 'Channel enabled without consent: ' . $id );
}

echo 'GKPS_RELEASE_OK ' . get_bloginfo( 'version' ) . ' PHP ' . PHP_VERSION;
