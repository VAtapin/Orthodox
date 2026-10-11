<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Maintenance {
    public static function hooks(): void {
        add_action( 'admin_menu', function () { add_submenu_page( 'prihod', __( 'Удаление данных', 'prihod' ), __( 'Удаление данных', 'prihod' ), 'manage_prihod_settings', 'prihod-data', array( Maintenance::class, 'render' ) ); } );
        add_action( 'admin_post_prihod_purge', array( self::class, 'submit' ) );
        add_action( 'prihod_purge_step', array( self::class, 'step' ) );
    }
    private static function authorize(): void {
        foreach ( array( 'manage_prihod_settings', 'manage_prihod', 'view_prihod_requests', 'delete_prihod_requests', 'delete_pages', 'delete_posts', 'manage_prihod_schedule' ) as $cap ) { if ( ! current_user_can( $cap ) ) { wp_die( esc_html__( 'Недостаточно прав полного удаления.', 'prihod' ), '', array( 'response' => 403 ) ); } }
    }
    public static function render(): void {
        self::authorize();
        echo '<div class="wrap"><h1>' . esc_html__( 'Полное удаление данных «Прихода»', 'prihod' ) . '</h1><p>' . esc_html__( 'Удаляются профиль, места, духовенство, расписание, обращения и переписка. Сначала сохраните резервную копию сайта. Обычное удаление плагина этих данных не удаляет.', 'prihod' ) . '</p>';
        if ( get_option( 'prihod_purge_job' ) ) { echo '<p>' . esc_html__( 'Удаление выполняется фоновыми пакетами через WP-Cron. Новые обращения и расписание временно отключены.', 'prihod' ) . '</p></div>'; return; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_purge">'; wp_nonce_field( 'prihod_purge' );
        echo '<p><label><input type="checkbox" name="owned_pages" value="1"> ' . esc_html__( 'Также удалить созданные мастером страницы (включая отредактированные), навигацию и меню. Использованные существующие страницы сохранятся.', 'prihod' ) . '</label></p><p><label>' . esc_html__( 'Для подтверждения введите УДАЛИТЬ', 'prihod' ) . ' <input name="confirmation" required autocomplete="off"></label></p><button class="button">' . esc_html__( 'Запустить необратимое удаление', 'prihod' ) . '</button></form></div>';
    }
    public static function start( bool $owned_pages ): void {
        self::authorize();
        if ( get_option( 'prihod_purge_job' ) ) { throw new \RuntimeException( __( 'Удаление уже запущено.', 'prihod' ) ); }
        update_option( 'prihod_purge_job', array( 'owned_pages' => $owned_pages, 'resources' => get_option( 'prihod_resources', array() ) ), false );
        $scheduled = wp_schedule_single_event( time() + 1, 'prihod_purge_step', array(), true );
        if ( is_wp_error( $scheduled ) || ! $scheduled ) { delete_option( 'prihod_purge_job' ); throw new \RuntimeException( __( 'Не удалось запланировать удаление.', 'prihod' ) ); }
    }
    public static function step(): void {
        $job = get_option( 'prihod_purge_job' ); if ( ! is_array( $job ) ) { return; }
        $token = Lock::acquire( 'prihod_purge_lock', 120 ); if ( ! $token ) { return; }
        try {
            $posts = get_posts( array( 'post_type' => array( 'prihod_request', 'prihod_message', 'prihod_service', 'prihod_location', 'prihod_clergy' ), 'post_status' => array( 'private', 'publish', 'draft', 'pending', 'future', 'trash' ), 'numberposts' => 100, 'orderby' => 'ID', 'order' => 'ASC' ) );
            foreach ( $posts as $post ) {
                if ( 'prihod_request' === $post->post_type && 'trash' !== $post->post_status ) { Requests::delete( $post->ID ); } else { wp_delete_post( $post->ID, true ); }
            }
            if ( count( $posts ) >= 100 ) { wp_schedule_single_event( time() + 10, 'prihod_purge_step' ); return; }
            if ( $job['owned_pages'] ) {
                foreach ( $job['resources'] as $key => $resource ) {
                    if ( empty( $resource['owned'] ) ) { continue; }
                    if ( '_menu' === $key ) { wp_delete_nav_menu( $resource['id'] ); }
                    elseif ( in_array( get_post_type( $resource['id'] ), array( 'page', 'wp_navigation' ), true ) ) { wp_delete_post( $resource['id'], true ); }
                }
            }
            foreach ( array( Profile::OPTION, 'prihod_modules', 'prihod_resources', 'prihod_retention_days', 'prihod_default_responsible' ) as $option ) { delete_option( $option ); }
            foreach ( get_users( array( 'fields' => 'ID' ) ) as $id ) { delete_user_meta( $id, 'prihod_wizard_' . get_current_blog_id() ); }
            delete_option( 'prihod_purge_job' ); update_option( 'prihod_last_purge', gmdate( DATE_ATOM ), false );
        } finally { Lock::release( 'prihod_purge_lock', $token ); }
    }
    public static function submit(): void {
        self::authorize(); check_admin_referer( 'prihod_purge' );
        if ( 'УДАЛИТЬ' !== ( $_POST['confirmation'] ?? '' ) ) { wp_die( esc_html__( 'Удаление не подтверждено.', 'prihod' ) ); }
        self::start( ! empty( $_POST['owned_pages'] ) ); wp_safe_redirect( admin_url( 'admin.php?page=prihod-data' ) ); exit;
    }
}
