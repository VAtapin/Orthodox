<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Admin_Navigation {
    public static function hooks(): void {
        add_action( 'in_admin_header', array( self::class, 'render' ) );
        add_action( 'admin_enqueue_scripts', function ( $hook ) {
            if ( self::active() ) { wp_enqueue_style( 'prihod-admin-navigation', plugins_url( 'assets/admin-navigation.css', PRIHOD_FILE ), array(), PRIHOD_VERSION ); }
            if ( self::core_screen() || 'index.php' === $hook ) { wp_enqueue_style( 'prihod-admin-ui', plugins_url( 'assets/admin-ui.css', PRIHOD_FILE ), array(), PRIHOD_VERSION . '.' . filemtime( dirname( PRIHOD_FILE ) . '/assets/admin-ui.css' ) ); }
        } );
        add_filter( 'admin_body_class', function ( $classes ) { return self::core_screen() ? $classes . ' prihod-own-admin' : $classes; } );
        add_filter( 'parent_file', function ( $parent ) { return self::active() && self::core_screen() ? 'prihod' : $parent; }, 50 );
        add_filter( 'submenu_file', function ( $file ) { return self::active() && self::core_screen() ? self::section() : $file; }, 50 );
    }
    private static function page(): string { return is_string( $_GET['page'] ?? null ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; }
    private static function core_screen(): bool {
        $screen = get_current_screen();
        return str_starts_with( self::page(), 'prihod' ) || in_array( $screen->post_type ?? '', array( 'prihod_location', 'prihod_clergy', 'prihod_service', 'prihod_request' ), true );
    }
    public static function active(): bool {
        if ( self::core_screen() ) { return true; }
        foreach ( array( 'orthocal', 'georg-kloster-orthodox-donate', 'kloster-prayer' ) as $prefix ) { if ( str_starts_with( self::page(), $prefix ) ) { return true; } }
        return false;
    }
    public static function symbol( string $type ): string {
        if ( ! in_array( $type, array( 'church', 'parish', 'monastery', 'chapel' ), true ) ) { $type = 'church'; }
        return '<svg class="prihod-church-symbol" viewBox="0 0 40 48" aria-hidden="true" focusable="false"><use href="' . esc_url( plugins_url( 'assets/parish-symbols.svg', PRIHOD_FILE ) . '#' . $type ) . '"></use></svg>';
    }
    public static function status_label( string $status ): string {
        $labels = array( 'publish' => __( 'Опубликована', 'prihod' ), 'draft' => __( 'Черновик', 'prihod' ), 'private' => __( 'Личная', 'prihod' ), 'pending' => __( 'Ожидает проверки', 'prihod' ), 'future' => __( 'Запланирована', 'prihod' ), 'trash' => __( 'В корзине', 'prihod' ) );
        return $labels[$status] ?? ( get_post_status_object( $status )->label ?? __( 'Не проверено', 'prihod' ) );
    }
    public static function section(): string {
        $page = self::page(); $type = get_current_screen()->post_type ?? '';
        if ( 'prihod-wizard' === $page ) { return 'prihod-wizard'; }
        if ( 'prihod-requests' === $page || 'prihod_request' === $type ) { return 'prihod-requests'; }
        if ( 'prihod_service' === $type ) { return 'edit.php?post_type=prihod_service'; }
        if ( 'prihod-info' === $page || 'prihod-profile' === $page || in_array( $type, array( 'prihod_location', 'prihod_clergy' ), true ) ) { return 'prihod-info'; }
        if ( 'prihod-plugins' === $page || ! self::core_screen() ) { return 'prihod-plugins'; }
        return 'prihod' === $page ? 'prihod' : 'prihod-settings-hub';
    }
    public static function render(): void {
        if ( ! self::active() ) { return; }
        $items = array();
        if ( current_user_can( Admin::menu_capability() ) ) { $items['prihod'] = __( 'Обзор прихода', 'prihod' ); }
        if ( current_user_can( 'manage_prihod' ) ) { $items['prihod-info'] = __( 'Информация', 'prihod' ); }
        if ( current_user_can( 'manage_prihod_schedule' ) ) { $items['edit.php?post_type=prihod_service'] = __( 'Расписание', 'prihod' ); }
        if ( current_user_can( 'view_prihod_requests' ) ) { $items['prihod-requests'] = __( 'Обращения', 'prihod' ); }
        if ( current_user_can( 'manage_prihod_settings' ) ) {
            $items['prihod-plugins'] = __( 'Наши плагины', 'prihod' ); $items['prihod-settings-hub'] = __( 'Настройки', 'prihod' );
            if ( current_user_can( 'edit_pages' ) ) { $items['prihod-wizard'] = __( 'Мастер настройки', 'prihod' ); }
        }
        if ( ! $items ) { return; }
        echo '<div class="prihod-admin-navigation">';
        if ( self::core_screen() ) {
            echo '<div class="prihod-nav-brand"><strong>' . self::symbol( 'church' ) . esc_html__( 'Приход', 'prihod' ) . '</strong>';
            $descriptions = array( 'edit.php?post_type=prihod_service' => __( 'Расписание богослужений', 'prihod' ), 'prihod' => __( 'Состояние сайта и ближайшие дела', 'prihod' ), 'prihod-info' => __( 'Сведения, духовенство и богослужения', 'prihod' ), 'prihod-requests' => __( 'Обращения и ответы посетителям', 'prihod' ), 'prihod-plugins' => __( 'Установленные компоненты вашего сайта', 'prihod' ), 'prihod-settings-hub' => __( 'Параметры прихода, оформления и подключений', 'prihod' ), 'prihod-wizard' => __( 'Подготовка сайта по шагам', 'prihod' ) );
            echo '<p>' . esc_html( $descriptions[self::section()] ?? '' ) . '</p></div>';
        } else {
            echo '<strong>' . esc_html__( 'Приход', 'prihod' ) . '</strong>';
        }
        echo '<nav aria-label="' . esc_attr__( 'Разделы прихода', 'prihod' ) . '">';
        foreach ( $items as $page => $label ) { echo '<a href="' . esc_url( admin_url( str_starts_with( $page, 'edit.php?' ) ? $page : 'admin.php?page=' . $page ) ) . '"' . ( self::section() === $page ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>'; }
        echo '</nav></div>';
    }
}
