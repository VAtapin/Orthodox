<?php
/**
 * Plugin Name: Georg-Kloster Connect Hub
 * Plugin URI: https://github.com/VAtapin/Orthodox/tree/main/georg-kloster-connect-hub
 * Description: Independent forms, messages and publications across communication channels.
 * Version: 0.4.9
 * Author: Atapin Vladimir
 * Author URI: https://atapin.de/
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: georg-kloster-connect-hub
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// Keep the internal identifiers: existing messages, capabilities, cron jobs and
// Telegram callbacks must survive a change of the public package name.
$gkch_load = static function (): bool {
    if ( class_exists( 'GeorgKlosterPrihodSocial\\Plugin', false ) ) { return false; }
    define( 'GKPS_VERSION', '0.4.9' );
    define( 'GKPS_FILE', __FILE__ );
    foreach ( array(
    'class-plugin', 'class-profile', 'class-modules', 'class-capabilities', 'class-admin',
    'class-lock', 'class-requests', 'class-request-form', 'class-request-admin',
    'class-request-mail-log', 'class-outgoing-log', 'class-request-mail', 'class-request-media',
    'class-request-privacy', 'class-connector', 'class-telegram-requests',
    'class-telegram-request-settings', 'class-telegram-bot', 'class-blocks', 'class-forms',
    'class-hub-settings', 'class-publications', 'class-social-providers',
    'class-publication-rules', 'class-publication-rules-admin',
    ) as $part ) { require_once __DIR__ . '/includes/' . $part . '.php'; }
    return true;
};

register_activation_hook( __FILE__, static function ( $network_wide ) use ( $gkch_load ) {
    load_plugin_textdomain( 'georg-kloster-connect-hub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    if ( is_plugin_active_for_network( 'georg-kloster-prihod-social/georg-kloster-prihod-social.php' ) && ! $network_wide ) {
        wp_die( esc_html__( 'Activate Connect Hub network-wide to replace a network-active Parish Social.', 'georg-kloster-connect-hub' ) );
    }
    // Activation runs with the old plugin already loaded. Do not redeclare it.
    $gkch_load();
    \GeorgKlosterPrihodSocial\Capabilities::install();
    deactivate_plugins( 'georg-kloster-prihod-social/georg-kloster-prihod-social.php', true );
} );
register_deactivation_hook( __FILE__, static function () {
    wp_clear_scheduled_hook( 'gkps_requests_retention' );
    wp_clear_scheduled_hook( 'gkch_outgoing_log_retention' );
    wp_clear_scheduled_hook( 'gkps_telegram_register' );
    if ( class_exists( 'GeorgKlosterPrihodSocial\\Telegram_Bot' ) ) { \GeorgKlosterPrihodSocial\Telegram_Bot::deactivate(); }
    if ( class_exists( 'GeorgKlosterPrihodSocial\\Publication_Rules' ) ) { \GeorgKlosterPrihodSocial\Publication_Rules::deactivate(); }
} );
add_action( 'plugins_loaded', static function () use ( $gkch_load ) {
    if ( $gkch_load() ) {
        add_action( 'init', array( 'GeorgKlosterPrihodSocial\\Plugin', 'boot' ), 0 );
    } else {
        add_action( 'init', static function () { load_plugin_textdomain( 'georg-kloster-connect-hub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); }, 0 );
        add_action( 'admin_notices', static function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Deactivate Parish Social to use Connect Hub. Your saved data will be retained.', 'georg-kloster-connect-hub' ) . '</p></div>';
        } );
    }
}, 0 );
