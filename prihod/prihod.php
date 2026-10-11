<?php
/**
 * Plugin Name: Приход
 * Description: Сведения о приходе, места служения и духовенство. Основа системы управления приходским сайтом.
 * Version: 0.3.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: prihod
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'PRIHOD_VERSION', '0.3.0' );
define( 'PRIHOD_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-capabilities.php';
require_once __DIR__ . '/includes/class-profile.php';
require_once __DIR__ . '/includes/class-directory.php';
require_once __DIR__ . '/includes/interface-integration.php';
require_once __DIR__ . '/includes/class-integration-registry.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-modules.php';
require_once __DIR__ . '/includes/class-lock.php';
require_once __DIR__ . '/includes/class-occurrences.php';
require_once __DIR__ . '/includes/class-schedule.php';
require_once __DIR__ . '/includes/class-schedule-admin.php';
require_once __DIR__ . '/includes/class-public-view.php';
require_once __DIR__ . '/includes/class-requests.php';
require_once __DIR__ . '/includes/class-request-form.php';
require_once __DIR__ . '/includes/class-request-admin.php';
require_once __DIR__ . '/includes/class-request-privacy.php';

register_activation_hook( __FILE__, array( 'Prihod\\Capabilities', 'install' ) );
add_action( 'plugins_loaded', array( 'Prihod\\Plugin', 'boot' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'prihod_requests_retention' ); } );
