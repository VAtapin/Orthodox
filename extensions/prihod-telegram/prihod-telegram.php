<?php
/**
 * Plugin Name: Приход: Telegram
 * Description: Публикации WordPress и уведомления через официальный Telegram Bot API. Может работать без «Прихода».
 * Version: 0.1.2
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: prihod-telegram
 */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( '\PrihodTelegram\Lock' ) ) { require_once __DIR__ . '/includes/class-lock.php'; }
if ( ! class_exists( '\PrihodTelegram\Connector' ) ) { require_once __DIR__ . '/includes/class-connector.php'; }
add_action( 'init', function () { load_plugin_textdomain( 'prihod-telegram', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); \PrihodTelegram\Connector::boot( 'telegram', __( 'Приход: Telegram', 'prihod-telegram' ), __FILE__ ); }, 5 );
