<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Dashboard {
    public static function hooks(): void {
        add_action( 'admin_menu', array( self::class, 'menu' ), 30 );
        add_action( 'admin_menu', array( self::class, 'organize_menu' ), PHP_INT_MAX );
        add_action( 'wp_dashboard_setup', array( self::class, 'widgets' ) );
        add_filter( 'parent_file', function ( $parent ) { return in_array( $parent, array( 'prihod-info', 'prihod-settings-hub' ), true ) ? 'prihod' : $parent; } );
        add_filter( 'submenu_file', function ( $file ) {
            global $plugin_page, $_parent_pages;
            $group = $_parent_pages[$plugin_page ?? ''] ?? '';
            return in_array( $group, array( 'prihod-info', 'prihod-settings-hub' ), true ) ? $group : $file;
        } );
        add_action( 'admin_enqueue_scripts', function ( $hook ) {
            $page = is_string( $_GET['page'] ?? null ) ? $_GET['page'] : '';
            if ( 'index.php' === $hook || str_starts_with( $page, 'prihod' ) ) { wp_enqueue_style( 'prihod-dashboard', plugins_url( 'assets/dashboard.css', PRIHOD_FILE ), array(), PRIHOD_VERSION ); }
        } );
    }
    public static function menu(): void {
        add_submenu_page( 'prihod', __( 'Информация', 'prihod' ), __( 'Информация', 'prihod' ), 'manage_prihod', 'prihod-info', array( self::class, 'information' ) );
        add_submenu_page( 'prihod', __( 'Наши плагины', 'prihod' ), __( 'Наши плагины', 'prihod' ), 'manage_prihod_settings', 'prihod-plugins', array( self::class, 'plugins' ) );
        add_submenu_page( 'prihod', __( 'Настройки', 'prihod' ), __( 'Настройки', 'prihod' ), 'manage_prihod_settings', 'prihod-settings-hub', array( self::class, 'settings' ) );
    }
    public static function organize_menu(): void {
        global $submenu;
        $hidden = array( 'prihod-profile', 'prihod-integrations', 'prihod-settings', 'prihod-check', 'prihod-access', 'prihod-data', 'prihod-theme', 'prihod-connect-telegram', 'prihod-connect-whatsapp', 'prihod-connect-facebook', 'edit.php?post_type=prihod_location', 'edit.php?post_type=prihod_clergy', 'edit.php?post_type=prihod_service' );
        foreach ( $hidden as $slug ) {
            $original_hook = get_plugin_page_hookname( $slug, 'prihod' );
            $item = remove_submenu_page( 'prihod', $slug );
            if ( ! $item || str_starts_with( $slug, 'edit.php?' ) ) { continue; }
            // A registered, non-top-level parent keeps grouped pages routable
            // without adding every form to the visible sidebar. WP retains its capability check.
            $group = 'prihod-profile' === $slug ? 'prihod-info' : 'prihod-settings-hub';
            add_submenu_page( $group, $item[3] ?? $item[0], $item[0], $item[1], $slug, function () use ( $original_hook, $item ) {
                self::require_capability( $item[1] );
                do_action( $original_hook );
            } );
        }
        $items = $submenu['prihod'] ?? array(); $ordered = array();
        foreach ( array( 'prihod', 'prihod-info', 'prihod-requests', 'prihod-plugins', 'prihod-settings-hub', 'prihod-wizard' ) as $slug ) {
            foreach ( $items as $key => $item ) { if ( $slug === $item[2] ) { $ordered[] = $item; unset( $items[$key] ); } }
        }
        $submenu['prihod'] = array_merge( $ordered, array_values( $items ) );
    }
    private static function require_capability( string $capability ): void {
        if ( ! current_user_can( $capability ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); }
    }
    private static function button( string $label, string $route, bool $primary = false ): void {
        echo '<a class="button' . ( $primary ? ' button-primary' : '' ) . '" href="' . esc_url( admin_url( $route ) ) . '">' . esc_html( $label ) . '</a> ';
    }
    public static function profile_card(): void {
        if ( ! current_user_can( 'manage_prihod' ) ) { return; }
        $profile = Profile::get();
        echo '<p><strong>' . esc_html( $profile['name'] ?: __( 'Название прихода ещё не заполнено.', 'prihod' ) ) . '</strong></p><p>' . esc_html( $profile['address'] ) . '</p><p>' . esc_html( $profile['timezone'] ) . '</p><div class="prihod-dash-actions">';
        if ( current_user_can( 'manage_prihod_settings' ) && current_user_can( 'edit_pages' ) ) { self::button( __( 'Мастер настройки', 'prihod' ), 'admin.php?page=prihod-wizard', true ); }
        self::button( __( 'Сведения о приходе', 'prihod' ), 'admin.php?page=prihod-profile' );
        echo '</div>';
    }
    public static function services_card(): void {
        if ( ! current_user_can( 'manage_prihod_schedule' ) ) { return; }
        echo Public_View::render( 'next-services', array( 'limit' => 3 ) );
        self::button( __( 'Расписание', 'prihod' ), 'edit.php?post_type=prihod_service' );
    }
    public static function requests_card(): void {
        if ( ! current_user_can( 'view_prihod_requests' ) ) { return; }
        foreach ( Requests::statuses() as $status => $label ) {
            $query = new \WP_Query( array( 'post_type' => 'prihod_request', 'post_status' => 'private', 'perm' => 'readable', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_prihod_request_status', 'meta_value' => $status ) );
            echo '<p>' . esc_html( $label ) . ': <strong>' . esc_html( (string) $query->found_posts ) . '</strong></p>';
        }
        self::button( __( 'Обращения', 'prihod' ), 'admin.php?page=prihod-requests', true );
    }
    public static function integrations_card(): void {
        if ( ! current_user_can( 'manage_prihod_settings' ) ) { return; }
        foreach ( Plugin::boot()->integrations()->all() as $integration ) {
            $report = Plugin::boot()->integrations()->report( $integration );
            echo '<p><strong>' . esc_html( $report['name'] ) . '</strong><br>' . esc_html( $report['message'] ) . '</p>';
        }
        self::button( __( 'Наши плагины', 'prihod' ), 'admin.php?page=prihod-plugins' );
    }
    public static function widgets(): void {
        foreach ( array( 'profile' => array( 'manage_prihod', __( 'Приход — обзор', 'prihod' ), 'profile_card' ), 'services' => array( 'manage_prihod_schedule', __( 'Ближайшие службы', 'prihod' ), 'services_card' ), 'requests' => array( 'view_prihod_requests', __( 'Обращения', 'prihod' ), 'requests_card' ), 'plugins' => array( 'manage_prihod_settings', __( 'Наши плагины', 'prihod' ), 'integrations_card' ) ) as $id => $definition ) {
            if ( current_user_can( $definition[0] ) ) { wp_add_dashboard_widget( 'prihod_dashboard_' . $id, $definition[1], array( self::class, $definition[2] ) ); }
        }
    }
    public static function overview(): void {
        echo '<div class="wrap prihod-dashboard"><h1>' . esc_html__( 'Приход — обзор', 'prihod' ) . '</h1><div class="prihod-dash-grid">';
        foreach ( array( 'manage_prihod' => array( __( 'Информация', 'prihod' ), 'profile_card' ), 'manage_prihod_schedule' => array( __( 'Ближайшие службы', 'prihod' ), 'services_card' ), 'view_prihod_requests' => array( __( 'Обращения', 'prihod' ), 'requests_card' ), 'manage_prihod_settings' => array( __( 'Наши плагины', 'prihod' ), 'integrations_card' ) ) as $capability => $card ) {
            if ( current_user_can( $capability ) ) { echo '<section class="prihod-dash-card"><h2>' . esc_html( $card[0] ) . '</h2>'; call_user_func( array( self::class, $card[1] ) ); echo '</section>'; }
        }
        echo '</div><p class="prihod-dash-actions">';
        if ( current_user_can( 'manage_prihod' ) ) { self::button( __( 'Информация', 'prihod' ), 'admin.php?page=prihod-info' ); }
        if ( current_user_can( 'manage_prihod_settings' ) ) { self::button( __( 'Настройки', 'prihod' ), 'admin.php?page=prihod-settings-hub' ); }
        echo '</p></div>';
    }
    public static function information(): void {
        self::require_capability( 'manage_prihod' );
        echo '<div class="wrap prihod-dashboard"><h1>' . esc_html__( 'Информация', 'prihod' ) . '</h1>'; self::profile_card();
        echo '<p class="prihod-dash-actions">';
        self::button( __( 'Места служения', 'prihod' ), 'edit.php?post_type=prihod_location' );
        self::button( __( 'Духовенство', 'prihod' ), 'edit.php?post_type=prihod_clergy' );
        if ( current_user_can( 'manage_prihod_schedule' ) ) { self::button( __( 'Расписание', 'prihod' ), 'edit.php?post_type=prihod_service' ); }
        echo '</p></div>';
    }
    public static function plugins(): void {
        self::require_capability( 'manage_prihod_settings' );
        echo '<div class="wrap prihod-dashboard"><h1>' . esc_html__( 'Наши плагины', 'prihod' ) . '</h1><div class="prihod-dash-grid">';
        $installed = get_plugins();
        echo '<section class="prihod-dash-card"><h2>' . esc_html__( 'Приход', 'prihod' ) . '</h2><p>Core · ' . esc_html( PRIHOD_VERSION ) . '</p>'; self::button( __( 'Настройки модулей', 'prihod' ), 'admin.php?page=prihod-settings' ); echo '</section>';
        $theme = wp_get_theme( 'prihod-theme' );
        if ( $theme->exists() ) { echo '<section class="prihod-dash-card"><h2>' . esc_html__( 'Оформление прихода', 'prihod' ) . '</h2><p>' . esc_html( $theme->get( 'Name' ) ) . ' · ' . esc_html( $theme->get( 'Version' ) ) . '</p>'; if ( current_user_can( 'manage_options' ) && function_exists( 'prihod_theme_settings_page' ) ) { self::button( __( 'Настройки', 'prihod' ), 'admin.php?page=prihod-theme' ); } echo '</section>'; }
        foreach ( Plugin::boot()->integrations()->all() as $integration ) {
            $report = Plugin::boot()->integrations()->report( $integration );
            $paths = array( 'calendar' => 'orthocal/orthocal.php', 'prayer-notes' => 'georg-kloster-orthodox-donate/georg-kloster-orthodox-donate.php', 'telegram' => 'prihod-telegram/prihod-telegram.php', 'whatsapp' => 'prihod-whatsapp/prihod-whatsapp.php', 'facebook' => 'prihod-social/prihod-social.php' );
            $path = $paths[$integration->id()] ?? '';
            if ( 'prayer-notes' === $integration->id() && ! isset( $installed[$path] ) ) { $path = 'kloster-prayer/kloster-prayer.php'; }
            echo '<section class="prihod-dash-card"><h2>' . esc_html( $report['name'] ) . '</h2>';
            if ( isset( $installed[$path] ) ) { echo '<p>' . esc_html( $installed[$path]['Name'] ) . ' · ' . esc_html( $installed[$path]['Version'] ) . '</p>'; }
            echo '<p>' . esc_html( $report['message'] ) . '</p>';
            if ( isset( $installed[$path] ) && is_plugin_active( $path ) && ! empty( $report['settings_url'] ) ) { echo '<a class="button" href="' . esc_url( $report['settings_url'] ) . '">' . esc_html__( 'Настройки', 'prihod' ) . '</a>'; }
            echo '</section>';
        }
        echo '</div></div>';
    }
    public static function settings(): void {
        self::require_capability( 'manage_prihod_settings' );
        echo '<div class="wrap prihod-dashboard"><h1>' . esc_html__( 'Настройки', 'prihod' ) . '</h1><p class="prihod-dash-actions">';
        foreach ( array( 'prihod-settings' => __( 'Настройки модулей', 'prihod' ), 'prihod-plugins' => __( 'Наши плагины', 'prihod' ), 'prihod-check' => __( 'Проверка сайта', 'prihod' ), 'prihod-access' => __( 'Права сотрудников', 'prihod' ), 'prihod-data' => __( 'Удаление данных', 'prihod' ) ) as $page => $title ) { self::button( $title, 'admin.php?page=' . $page ); }
        if ( current_user_can( 'manage_options' ) && function_exists( 'prihod_theme_settings_page' ) ) { self::button( __( 'Оформление прихода', 'prihod' ), 'admin.php?page=prihod-theme' ); }
        echo '</p><div class="prihod-dash-grid">';
        foreach ( Plugin::boot()->integrations()->all() as $integration ) {
            $report = Plugin::boot()->integrations()->report( $integration );
            if ( empty( $report['settings_url'] ) || 'unavailable' === $report['status'] ) { continue; }
            echo '<section class="prihod-dash-card"><h2>' . esc_html( $report['name'] ) . '</h2><a class="button" href="' . esc_url( $report['settings_url'] ) . '">' . esc_html__( 'Настройки', 'prihod' ) . '</a></section>';
        }
        echo '</div></div>';
    }
}
