<?php
/**
 * Plugin Name: Georg-Kloster Parish
 * Description: Parish profile, clergy, service places and service schedules for WordPress.
 * Version: 0.2.9
 * Author: Atapin Vladimir
 * Author URI: https://georg-kloster.ru/
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: georg-kloster-prihod-core
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'GKPC_VERSION', '0.2.9' );
define( 'GKPC_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-profile.php';
require_once __DIR__ . '/includes/class-page-links.php';
require_once __DIR__ . '/includes/class-directory.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-blocks.php';
require_once __DIR__ . '/includes/class-lock.php';
require_once __DIR__ . '/includes/class-demo-importer.php';
require_once __DIR__ . '/includes/class-demo-admin.php';
require_once __DIR__ . '/includes/class-schedule-module.php';
require_once __DIR__ . '/includes/class-place-media.php';

register_activation_hook( __FILE__, array( 'GeorgKlosterPrihodCore\\Schedule_Module', 'activate' ) );
register_deactivation_hook( __FILE__, static function () { wp_clear_scheduled_hook( 'gkpsch_calendar_reminder' ); } );
add_action( 'plugins_loaded', array( 'GeorgKlosterPrihodCore\\Schedule_Module', 'load' ), 0 );
add_action( 'admin_enqueue_scripts', array( 'GeorgKlosterPrihodCore\\Place_Media', 'assets' ) );
add_action( 'admin_notices', array( 'GeorgKlosterPrihodCore\\Place_Media', 'notice' ) );
add_action( 'pre_get_posts', array( 'GeorgKlosterPrihodCore\\Directory', 'unified_list' ) );
add_filter( 'wp_count_posts', array( 'GeorgKlosterPrihodCore\\Directory', 'counts' ), 10, 3 );
add_action( 'init', static function () {
    load_plugin_textdomain( 'georg-kloster-prihod-core', false, dirname( plugin_basename( GKPC_FILE ) ) . '/languages' );
}, 0 );
add_action( 'init', array( 'GeorgKlosterPrihodCore\\Directory', 'register' ) );
add_action( 'init', array( 'GeorgKlosterPrihodCore\\Directory', 'register_legacy' ), 11 );
add_action( 'init', array( 'GeorgKlosterPrihodCore\\Blocks', 'register' ) );
add_action( 'admin_menu', array( 'GeorgKlosterPrihodCore\\Admin', 'menu' ) );
add_action( 'admin_menu', array( 'GeorgKlosterPrihodCore\\Demo_Admin', 'menu' ), 20 );
add_action( 'admin_post_gkpc_demo_import', array( 'GeorgKlosterPrihodCore\\Demo_Admin', 'handle' ) );
add_action( 'georg_kloster_prihod_home_settings_after', array( 'GeorgKlosterPrihodCore\\Demo_Admin', 'link' ) );
add_action( 'admin_enqueue_scripts', array( 'GeorgKlosterPrihodCore\\Demo_Admin', 'assets' ) );
add_action( 'admin_init', array( 'GeorgKlosterPrihodCore\\Admin', 'settings' ) );
add_filter( 'georg_kloster_prihod_resources', array( 'GeorgKlosterPrihodCore\\Page_Links', 'filter' ) );
add_action( 'update_option_gkpc_profile', static function ( $old, $new ) { if ( is_array( $new ) ) { \GeorgKlosterPrihodCore\Profile::register_strings( $new ); } }, 10, 2 );
add_action( 'add_option_gkpc_profile', static function ( $name, $new ) { if ( is_array( $new ) ) { \GeorgKlosterPrihodCore\Profile::register_strings( $new ); } }, 10, 2 );
add_action( 'add_meta_boxes', array( 'GeorgKlosterPrihodCore\\Directory', 'meta_boxes' ) );
add_action( 'save_post_gkpc_location', array( 'GeorgKlosterPrihodCore\\Directory', 'save' ) );
add_action( 'save_post_gkpc_clergy', array( 'GeorgKlosterPrihodCore\\Directory', 'save' ) );
add_action( 'save_post_gkpsch_location', array( 'GeorgKlosterPrihodCore\\Directory', 'save' ) );
add_action( 'save_post_gkpc_location', array( 'GeorgKlosterPrihodCore\\Place_Media', 'save' ) );
add_action( 'save_post_gkpsch_location', array( 'GeorgKlosterPrihodCore\\Place_Media', 'save' ) );
