<?php
/**
 * Plugin Name: Georg-Kloster Parish Social
 * Description: Contact forms, private correspondence and optional messaging channels.
 * Version: 0.1.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: georg-kloster-prihod-social
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'GKPS_VERSION', '0.1.1' );
define( 'GKPS_FILE', __FILE__ );

foreach ( array(
    'class-plugin', 'class-profile', 'class-modules', 'class-capabilities', 'class-admin',
    'class-lock', 'class-requests', 'class-request-form', 'class-request-admin',
    'class-request-mail-log', 'class-request-mail', 'class-request-media',
    'class-request-privacy', 'class-connector', 'class-telegram-requests',
    'class-telegram-request-settings', 'class-blocks',
) as $part ) { require_once __DIR__ . '/includes/' . $part . '.php'; }

register_activation_hook( __FILE__, array( 'GeorgKlosterPrihodSocial\\Capabilities', 'install' ) );
register_deactivation_hook( __FILE__, static function () {
    wp_clear_scheduled_hook( 'gkps_requests_retention' );
    wp_clear_scheduled_hook( 'gkps_telegram_register' );
} );
add_action( 'plugins_loaded', array( 'GeorgKlosterPrihodSocial\\Plugin', 'boot' ) );
