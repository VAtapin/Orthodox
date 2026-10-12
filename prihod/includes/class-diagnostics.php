<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Diagnostics {
    public static function hooks(): void {
        add_action( 'admin_menu', function () { add_submenu_page( 'prihod', __( 'Проверка сайта', 'prihod' ), __( 'Проверка сайта', 'prihod' ), 'manage_prihod_settings', 'prihod-check', array( Diagnostics::class, 'render' ) ); } );
        add_action( 'rest_api_init', function () {
            register_rest_route( Plugin::REST_NAMESPACE, '/status', array( 'methods' => 'GET', 'permission_callback' => fn() => current_user_can( 'manage_prihod_settings' ), 'callback' => fn() => rest_ensure_response( Diagnostics::report() ) ) );
        } );
    }
    public static function report(): array {
        $resources = get_option( 'prihod_resources', array() ); $pages = array();
        foreach ( $resources as $key => $resource ) { $pages[ $key ] = get_post_status( $resource['id'] ) ?: 'missing'; }
        return array( 'version' => PRIHOD_VERSION, 'profile_complete' => '' !== Profile::get()['name'], 'schedule_enabled' => Modules::enabled( 'schedule' ),
            'next_service_found' => (bool) Schedule::next(), 'requests_enabled' => Modules::enabled( 'requests' ), 'privacy_page' => (bool) get_privacy_policy_url(),
            'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON, 'retention_scheduled' => (bool) wp_next_scheduled( 'prihod_requests_retention' ),
            'email_delivery' => 'not_verified', 'pages' => $pages );
    }
    public static function render(): void {
        if ( ! current_user_can( 'manage_prihod_settings' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); }
        $report = self::report();
        echo '<div class="wrap"><h1>' . esc_html__( 'Проверка сайта', 'prihod' ) . '</h1><section class="prihod-dash-card"><ul>';
        foreach ( array( 'profile_complete' => __( 'Название прихода заполнено', 'prihod' ), 'next_service_found' => __( 'Ближайшая служба найдена', 'prihod' ), 'privacy_page' => __( 'Политика конфиденциальности задана', 'prihod' ), 'retention_scheduled' => __( 'Проверка сроков хранения запланирована', 'prihod' ) ) as $key => $label ) { echo '<li>' . esc_html( $label ) . ': ' . esc_html( $report[ $key ] ? __( 'Да', 'prihod' ) : __( 'Нет', 'prihod' ) ) . '</li>'; }
        echo '</ul><p>' . esc_html__( 'Доставка email не проверена. Для очереди ответов требуется работающий WP-Cron или серверный cron. Принятие письма транспортом не гарантирует доставку адресату.', 'prihod' ) . '</p>';
        $templates = Site_Plan::pages(); $resources = get_option( 'prihod_resources', array() );
        foreach ( $report['pages'] as $key => $status ) {
            $id = absint( $resources[$key]['id'] ?? 0 );
            $label = $templates[$key]['title'] ?? ( $id && current_user_can( 'read_post', $id ) ? get_the_title( $id ) : __( 'Материал', 'prihod' ) );
            echo '<p>' . esc_html( $label ) . ': ' . esc_html( 'missing' === $status ? __( 'Не создана', 'prihod' ) : Admin_Navigation::status_label( $status ) ) . '</p>';
        }
        echo '</section></div>';
    }
}
