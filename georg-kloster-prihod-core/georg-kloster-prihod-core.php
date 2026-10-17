<?php
/**
 * Plugin Name: Georg-Kloster Parish Core
 * Description: Parish profile, places and clergy for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: georg-kloster-prihod-core
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'GKPC_VERSION', '0.1.0' );
define( 'GKPC_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-profile.php';
require_once __DIR__ . '/includes/class-directory.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-blocks.php';

register_activation_hook( __FILE__, array( 'GeorgKlosterPrihodCore\\Admin', 'activate' ) );
add_action( 'init', array( 'GeorgKlosterPrihodCore\\Directory', 'register' ) );
add_action( 'init', array( 'GeorgKlosterPrihodCore\\Blocks', 'register' ) );
add_action( 'admin_menu', array( 'GeorgKlosterPrihodCore\\Admin', 'menu' ) );
add_action( 'admin_init', array( 'GeorgKlosterPrihodCore\\Admin', 'settings' ) );
add_action( 'add_meta_boxes', array( 'GeorgKlosterPrihodCore\\Directory', 'meta_boxes' ) );
add_action( 'save_post_gkpc_location', array( 'GeorgKlosterPrihodCore\\Directory', 'save' ) );
add_action( 'save_post_gkpc_clergy', array( 'GeorgKlosterPrihodCore\\Directory', 'save' ) );
