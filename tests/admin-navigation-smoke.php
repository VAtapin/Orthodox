<?php
defined( 'ABSPATH' ) || exit;
set_current_screen( 'dashboard' );
$_GET['page'] = 'prihod-connect-facebook';
ob_start(); Prihod\Admin_Navigation::render(); $navigation = ob_get_clean();
prihod_assert( str_contains( $navigation, 'page=prihod' ) && str_contains( $navigation, 'Обзор прихода' ) && str_contains( $navigation, 'aria-current="page"' ), 'Connector has shared navigation back to parish overview.' );
$_GET = array(); set_current_screen( 'prihod_service' );
prihod_assert( 'prihod-info' === Prihod\Admin_Navigation::section(), 'Service editor belongs to Information.' );
Prihod\Schedule_Admin::assets();
prihod_assert( wp_style_is( 'prihod-schedule-admin', 'enqueued' ), 'Service layout is loaded on its own editor.' );
ob_start(); Prihod\Schedule_Admin::render( (object) array( 'ID' => 0 ) ); $editor = ob_get_clean();
prihod_assert( str_contains( $editor, 'prihod-service-grid' ) && str_contains( $editor, 'data-prihod-repeat' ) && str_contains( $editor, '<details' ), 'Schedule form groups primary fields and optional details.' );
prihod_assert( str_contains( $editor, '<div id="prihod-exceptions"></div>' ), 'New service has no misleading empty exception.' );
set_current_screen( 'dashboard' );
prihod_assert( ! Prihod\Admin_Navigation::active(), 'Unrelated WordPress screens retain their own interface.' );
