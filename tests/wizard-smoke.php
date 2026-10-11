<?php
$existing = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Existing contact', 'post_content' => 'Hand edited content' ) );
$answers = array( 'profile' => Prihod\Profile::get(), 'modules' => array( 'schedule' => true, 'requests' => true ), 'publications' => true,
    'pages' => array( 'home' => 1, 'contacts' => 1, 'schedule' => 1 ), 'reuse' => array( 'contacts' => $existing ),
    'locations' => array( array( 'name' => 'New church', 'address' => 'Street' ) ), 'clergy' => array( array( 'name' => 'Father test', 'rank' => 'Priest' ) ), 'service' => array( 'title' => 'First service', 'date' => '2026-12-01', 'time' => '09:00', 'location_id' => '0' ) );
$pages_before = (int) wp_count_posts( 'page' )->draft;
$plan = Prihod\Site_Plan::plan( $answers );
prihod_assert( $pages_before === (int) wp_count_posts( 'page' )->draft, 'Plan preview does not create pages.' );
prihod_assert( 'reuse' === $plan['pages']['contacts']['action'], 'Plan explicitly reuses existing page.' );
wp_update_post( array( 'ID' => $existing, 'post_content' => 'Modified after preview' ) );
try { Prihod\Site_Plan::apply( $plan ); prihod_assert( false, 'Stale plan must fail.' ); }
catch ( RuntimeException $error ) { prihod_assert( true, 'Changes after preview require another confirmation.' ); }
$resources = Prihod\Site_Plan::apply( Prihod\Site_Plan::plan( $answers ) );
prihod_assert( 'draft' === get_post_status( $resources['home']['id'] ) && 'Modified after preview' === get_post( $existing )->post_content, 'Apply creates drafts and preserves existing user content.' );
prihod_assert( 'draft' === get_post_status( $resources['_locations_0']['id'] ) && get_post_meta( $resources['_clergy_0']['id'], '_prihod_details', true )['location_ids'] === array( $resources['_locations_0']['id'] ), 'Interview data creates location and clergy drafts after confirmation.' );
prihod_assert( 'draft' === get_post_status( $resources['_service_0']['id'] ), 'First service is a draft until separate publication.' );
$home_id = $resources['home']['id'];
$again = Prihod\Site_Plan::apply( Prihod\Site_Plan::plan( $answers ) );
prihod_assert( $home_id === $again['home']['id'] && $resources['_locations_0']['id'] === $again['_locations_0']['id'], 'Repeated apply does not duplicate generated resources.' );
wp_update_post( array( 'ID' => $again['schedule']['id'], 'post_content' => 'Manual change' ) );
try { Prihod\Site_Plan::publish( array( 'schedule' ), false, '', false ); prihod_assert( false, 'Manual page must not auto-publish.' ); }
catch ( RuntimeException $error ) { prihod_assert( 'draft' === get_post_status( $again['schedule']['id'] ), 'Manually changed draft is preserved for individual review.' ); }
Prihod\Site_Plan::publish( array( 'home', 'contacts', '_locations_0', '_clergy_0' ), true, '', true );
prihod_assert( 'publish' === get_post_status( $home_id ) && (int) get_option( 'page_on_front' ) === $home_id, 'Separate publication can explicitly assign the front page.' );
prihod_assert( ! empty( get_option( 'prihod_resources' )['_navigation']['id'] ), 'Block navigation is created separately without rewriting theme templates.' );
register_nav_menu( 'prihod-test', 'Test menu' );
Prihod\Site_Plan::publish( array( 'home', 'contacts' ), false, 'prihod-test', true );
$menu_id = get_option( 'prihod_resources' )['_menu']['id'];
Prihod\Site_Plan::publish( array( 'home', 'contacts' ), false, 'prihod-test', true );
prihod_assert( 2 === count( wp_get_nav_menu_items( $menu_id ) ), 'Repeated menu publication does not duplicate links.' );
wp_set_current_user( $editor_id );
try { Prihod\Site_Plan::apply( $plan ); prihod_assert( false, 'Editor cannot apply parish plan.' ); }
catch ( RuntimeException $error ) { prihod_assert( true, 'Plan apply requires parish and page capabilities.' ); }
wp_set_current_user( 1 );
Prihod\Access::set( $staff_id, array( 'view_prihod_requests' => 1, 'reply_prihod_requests' => 1 ) );
prihod_assert( user_can( $staff_id, 'view_prihod_requests' ) && user_can( $staff_id, 'reply_prihod_requests' ) && ! user_can( $staff_id, 'manage_options' ), 'Staff rights can be granted without administrator permissions.' );
