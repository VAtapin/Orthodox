<?php
require_once '/wordpress/wp-load.php';

function gkpc_assert( bool $condition, string $message ): void {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}

gkpc_assert( class_exists( 'GeorgKlosterPrihodCore\\Profile' ), 'Core profile unavailable.' );
gkpc_assert( ! class_exists( 'Prihod\\Schedule' ) && ! class_exists( 'Prihod\\Requests' ), 'Core loaded a legacy module.' );
gkpc_assert( post_type_exists( 'gkpc_location' ) && post_type_exists( 'gkpc_clergy' ), 'Directory post types missing.' );
gkpc_assert( WP_Block_Type_Registry::get_instance()->is_registered( 'gkpc/profile' ), 'Profile block missing.' );
gkpc_assert( shortcode_exists( 'gkpc_contacts' ), 'Contact shortcode missing.' );
$role = get_role( 'administrator' );
gkpc_assert( $role && $role->has_cap( 'manage_gkpc_profile' ), 'Administrator capability missing.' );

update_option( 'gkpc_profile', GeorgKlosterPrihodCore\Profile::sanitize( array( 'type' => 'monastery', 'name' => 'Test Abbey', 'description' => 'Local test', 'email' => 'test@example.org' ) ) );
gkpc_assert( 'Test Abbey' === GeorgKlosterPrihodCore\Profile::get()['name'], 'Profile was not saved.' );
gkpc_assert( str_contains( do_shortcode( '[gkpc_profile]' ), 'Test Abbey' ), 'Profile shortcode failed.' );
gkpc_assert( str_contains( render_block( array( 'blockName' => 'gkpc/contacts', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) ), 'test@example.org' ), 'Contacts block failed.' );

$place = wp_insert_post( array( 'post_type' => 'gkpc_location', 'post_status' => 'publish', 'post_title' => 'Chapel' ), true );
gkpc_assert( ! is_wp_error( $place ), 'Could not save place.' );
gkpc_assert( str_contains( do_shortcode( '[gkpc_places]' ), 'Chapel' ), 'Places shortcode failed.' );

echo 'GKPC_RELEASE_OK ' . get_bloginfo( 'version' ) . ' PHP ' . PHP_VERSION;
