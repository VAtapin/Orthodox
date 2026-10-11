<?php
/**
 * Plugin Name: Приход
 * Description: Сведения о приходе, места служения и духовенство. Основа системы управления приходским сайтом.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: prihod
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'PRIHOD_VERSION', '0.1.0' );
define( 'PRIHOD_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-capabilities.php';
require_once __DIR__ . '/includes/class-profile.php';
require_once __DIR__ . '/includes/class-directory.php';
require_once __DIR__ . '/includes/interface-integration.php';
require_once __DIR__ . '/includes/class-integration-registry.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Prihod\\Capabilities', 'install' ) );
add_action( 'plugins_loaded', array( 'Prihod\\Plugin', 'boot' ) );
