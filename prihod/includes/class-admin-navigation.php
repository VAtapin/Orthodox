<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Admin_Navigation {
    public static function hooks(): void {
        add_action( 'in_admin_header', array( self::class, 'render' ) );
        add_action( 'admin_enqueue_scripts', function () {
            if ( self::active() ) { wp_enqueue_style( 'prihod-admin-navigation', plugins_url( 'assets/admin-navigation.css', PRIHOD_FILE ), array(), PRIHOD_VERSION ); }
        } );
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
    public static function section(): string {
        $page = self::page(); $type = get_current_screen()->post_type ?? '';
        if ( 'prihod-wizard' === $page ) { return 'prihod-wizard'; }
        if ( 'prihod-requests' === $page || 'prihod_request' === $type ) { return 'prihod-requests'; }
        if ( 'prihod-info' === $page || 'prihod-profile' === $page || in_array( $type, array( 'prihod_location', 'prihod_clergy', 'prihod_service' ), true ) ) { return 'prihod-info'; }
        if ( 'prihod-plugins' === $page || ! self::core_screen() ) { return 'prihod-plugins'; }
        return 'prihod' === $page ? 'prihod' : 'prihod-settings-hub';
    }
    public static function render(): void {
        if ( ! self::active() ) { return; }
        $items = array();
        if ( current_user_can( Admin::menu_capability() ) ) { $items['prihod'] = __( 'Обзор прихода', 'prihod' ); }
        if ( current_user_can( 'manage_prihod' ) ) { $items['prihod-info'] = __( 'Информация', 'prihod' ); }
        if ( current_user_can( 'view_prihod_requests' ) ) { $items['prihod-requests'] = __( 'Обращения', 'prihod' ); }
        if ( current_user_can( 'manage_prihod_settings' ) ) {
            $items['prihod-plugins'] = __( 'Наши плагины', 'prihod' ); $items['prihod-settings-hub'] = __( 'Настройки', 'prihod' );
            if ( current_user_can( 'edit_pages' ) ) { $items['prihod-wizard'] = __( 'Мастер настройки', 'prihod' ); }
        }
        if ( ! $items ) { return; }
        echo '<div class="prihod-admin-navigation"><strong>' . esc_html__( 'Приход', 'prihod' ) . '</strong><nav aria-label="' . esc_attr__( 'Разделы прихода', 'prihod' ) . '">';
        foreach ( $items as $page => $label ) { echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '"' . ( self::section() === $page ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>'; }
        echo '</nav></div>';
    }
}
