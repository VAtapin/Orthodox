<?php
namespace Prihod;

defined( 'ABSPATH' ) || exit;

final class Capabilities {
    public const VERSION = 1;

    public static function all(): array {
        return array(
            'manage_prihod',
            'manage_prihod_schedule',
            'view_prihod_requests',
            'reply_prihod_requests',
            'assign_prihod_requests',
            'delete_prihod_requests',
            'manage_prihod_settings',
        );
    }
    public static function labels(): array {
        return array( 'manage_prihod' => __( 'Сведения о приходе, места и духовенство', 'prihod' ), 'manage_prihod_schedule' => __( 'Управление расписанием', 'prihod' ),
            'view_prihod_requests' => __( 'Просмотр обращений', 'prihod' ), 'reply_prihod_requests' => __( 'Ответы и статусы обращений', 'prihod' ),
            'assign_prihod_requests' => __( 'Назначение ответственных', 'prihod' ), 'delete_prihod_requests' => __( 'Удаление обращений', 'prihod' ), 'manage_prihod_settings' => __( 'Настройки, мастер и подключения', 'prihod' ) );
    }

    public static function install(): void {
        // Initial grants only. Authorization elsewhere always checks capabilities.
        $role = get_role( 'administrator' );
        if ( ! $role ) {
            return;
        }
        foreach ( self::all() as $capability ) {
            $role->add_cap( $capability );
        }
        update_option( 'prihod_capabilities_version', self::VERSION, false );
    }

    public static function maybe_upgrade(): void {
        if ( (int) get_option( 'prihod_capabilities_version', 0 ) < self::VERSION ) {
            self::install();
        }
    }

    public static function directory(): array {
        $keys = array(
            'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts',
            'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts',
            'delete_published_posts', 'delete_others_posts', 'edit_private_posts',
            'edit_published_posts', 'create_posts', 'read',
        );
        return array_fill_keys( $keys, 'manage_prihod' );
    }
}
