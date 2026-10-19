<?php
/** Bundled service-schedule module. Public identifiers remain compatible. */

defined( 'ABSPATH' ) || exit;

define( 'GKPSCH_VERSION', GKPC_VERSION );
define( 'GKPSCH_FILE', __FILE__ );

foreach ( array(
    'class-plugin', 'class-profile', 'class-directory', 'class-modules', 'class-capabilities',
    'class-admin', 'class-lock', 'class-occurrences', 'class-schedule', 'class-schedule-admin',
    'class-calendar-provider', 'class-calendar-adapter', 'class-calendar-colors', 'class-calendar-icons',
    'class-schedule-calendar', 'class-calendar-reminders', 'class-public-view',
) as $part ) { require_once __DIR__ . '/includes/' . $part . '.php'; }

add_action( 'init', array( 'GeorgKlosterPrihodSchedule\\Plugin', 'boot' ), 0 );
