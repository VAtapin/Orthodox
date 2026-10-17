<?php
/**
 * Plugin Name: Georg-Kloster Parish Schedule
 * Description: Service schedule, recurring events and Orthodox calendar hints.
 * Version: 0.1.7
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: georg-kloster-prihod-schedule
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'GKPSCH_VERSION', '0.1.7' );
define( 'GKPSCH_FILE', __FILE__ );

foreach ( array(
    'class-plugin', 'class-profile', 'class-directory', 'class-modules', 'class-capabilities',
    'class-admin', 'class-lock', 'class-occurrences', 'class-schedule', 'class-schedule-admin',
    'class-calendar-provider', 'class-calendar-adapter', 'class-calendar-colors', 'class-calendar-icons',
    'class-schedule-calendar', 'class-calendar-reminders', 'class-public-view',
) as $part ) { require_once __DIR__ . '/includes/' . $part . '.php'; }

register_activation_hook( __FILE__, array( 'GeorgKlosterPrihodSchedule\\Capabilities', 'install' ) );
register_deactivation_hook( __FILE__, static function () { wp_clear_scheduled_hook( 'gkpsch_calendar_reminder' ); } );
add_action( 'plugins_loaded', array( 'GeorgKlosterPrihodSchedule\\Plugin', 'boot' ) );
