<?php
namespace GeorgKlosterPrihodSchedule;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    public const REST_NAMESPACE = 'gkpsch/v1';

    public static function boot(): void {
        load_plugin_textdomain( 'georg-kloster-prihod-schedule', false, dirname( plugin_basename( GKPSCH_FILE ) ) . '/languages' );
        Capabilities::maybe_upgrade();
        add_action( 'init', array( Directory::class, 'register' ) );
        Schedule::hooks();
        Schedule_Calendar::hooks();
        Calendar_Icons::hooks();
        Calendar_Reminders::hooks();
        add_action( 'init', array( Public_View::class, 'register' ) );
        add_action( 'admin_menu', array( Admin::class, 'menu' ), 5 );
        add_action( 'admin_init', array( Admin::class, 'settings' ) );
        add_action( 'admin_init', static function () { register_setting( Calendar_Provider::OPTION, Calendar_Provider::OPTION, array( 'type' => 'array', 'sanitize_callback' => array( Calendar_Provider::class, 'sanitize' ) ) ); } );
        add_filter( 'option_page_capability_' . Calendar_Provider::OPTION, static fn() => 'manage_gkpsch_settings' );
        add_action( 'add_meta_boxes', array( Directory::class, 'meta_boxes' ) );
        add_action( 'save_post_gkpsch_location', array( Directory::class, 'save' ) );
    }
}
