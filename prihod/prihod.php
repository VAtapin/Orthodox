<?php
/**
 * Plugin Name: Приход
 * Description: Сведения о приходе, расписание, обращения и безопасный мастер подготовки православного сайта.
 * Version: 1.0.0-rc.16
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: prihod
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'PRIHOD_VERSION', '1.0.0-rc.16' );
define( 'PRIHOD_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-capabilities.php';
require_once __DIR__ . '/includes/class-profile.php';
require_once __DIR__ . '/includes/class-directory.php';
require_once __DIR__ . '/includes/interface-integration.php';
require_once __DIR__ . '/includes/class-integration-registry.php';
require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-dashboard.php';
require_once __DIR__ . '/includes/class-admin-navigation.php';
require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-modules.php';
require_once __DIR__ . '/includes/class-lock.php';
require_once __DIR__ . '/includes/class-occurrences.php';
require_once __DIR__ . '/includes/class-schedule.php';
require_once __DIR__ . '/includes/class-schedule-admin.php';
require_once __DIR__ . '/includes/class-calendar-adapter.php';
require_once __DIR__ . '/includes/class-calendar-colors.php';
require_once __DIR__ . '/includes/class-calendar-icons.php';
require_once __DIR__ . '/includes/class-schedule-calendar.php';
require_once __DIR__ . '/includes/class-calendar-reminders.php';
require_once __DIR__ . '/includes/class-public-view.php';
require_once __DIR__ . '/includes/class-telegram-requests.php';
require_once __DIR__ . '/includes/class-telegram-request-settings.php';
require_once __DIR__ . '/includes/class-request-media.php';
require_once __DIR__ . '/includes/class-request-mail.php';
require_once __DIR__ . '/includes/class-requests.php';
require_once __DIR__ . '/includes/class-request-form.php';
require_once __DIR__ . '/includes/class-request-admin.php';
require_once __DIR__ . '/includes/class-request-privacy.php';
require_once __DIR__ . '/includes/class-site-plan.php';
require_once __DIR__ . '/includes/class-wizard-interview.php';
require_once __DIR__ . '/includes/class-wizard-view.php';
require_once __DIR__ . '/includes/class-wizard.php';
require_once __DIR__ . '/includes/class-wizard-result.php';
require_once __DIR__ . '/includes/class-diagnostics.php';
require_once __DIR__ . '/includes/class-access.php';
require_once __DIR__ . '/includes/class-maintenance.php';
require_once __DIR__ . '/includes/class-existing-integration.php';

register_activation_hook( __FILE__, array( 'Prihod\\Capabilities', 'install' ) );
add_action( 'plugins_loaded', array( 'Prihod\\Plugin', 'boot' ) );
register_deactivation_hook( __FILE__, function () { wp_clear_scheduled_hook( 'prihod_requests_retention' ); wp_clear_scheduled_hook( 'prihod_calendar_reminder' ); } );
