<?php
/**
 * Plugin Name: Приход: WhatsApp
 * Description: Публикации и уведомления через официальные шаблоны WhatsApp Cloud API. Может работать без «Прихода».
 * Version: 0.1.1
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: prihod-whatsapp
 */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( '\PrihodConnect\Lock' ) ) { require_once __DIR__ . '/includes/class-lock.php'; }
if ( ! class_exists( '\PrihodConnect\Connector' ) ) { require_once __DIR__ . '/includes/class-connector.php'; }
add_action( 'init', function () { \PrihodConnect\Connector::boot( 'whatsapp', __( 'Приход: WhatsApp', 'prihod-whatsapp' ), __FILE__ ); }, 5 );
