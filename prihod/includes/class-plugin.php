<?php
namespace Prihod;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    public const REST_NAMESPACE = 'prihod/v1';
    private static ?self $instance = null;
    private Integration_Registry $integrations;

    private function __construct() {
        $this->integrations = new Integration_Registry();
        Capabilities::maybe_upgrade();
        add_action( 'init', function () {
            load_plugin_textdomain( 'prihod', false, dirname( plugin_basename( PRIHOD_FILE ) ) . '/languages' );
        }, 0 );
        add_action( 'init', array( Directory::class, 'register' ) );
        add_action( 'add_meta_boxes', array( Directory::class, 'meta_boxes' ) );
        add_action( 'save_post_prihod_location', array( Directory::class, 'save' ) );
        add_action( 'save_post_prihod_clergy', array( Directory::class, 'save' ) );
        ( new Admin( $this->integrations ) )->hooks();
        Dashboard::hooks();
        Admin_Navigation::hooks();
        Schedule::hooks();
        Schedule_Calendar::hooks();
        Calendar_Reminders::hooks();
        Requests::hooks();
        Wizard::hooks();
        Diagnostics::hooks();
        Access::hooks();
        Maintenance::hooks();
        Existing_Integration::hooks();
        add_action( 'init', array( Public_View::class, 'register' ) );
        add_action( 'init', function () {
            do_action( 'prihod_register_integrations', $this->integrations );
        }, 20 );
    }

    public static function boot(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function integrations(): Integration_Registry {
        return $this->integrations;
    }
}
