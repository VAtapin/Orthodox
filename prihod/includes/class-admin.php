<?php
namespace Prihod;

defined( 'ABSPATH' ) || exit;

final class Admin {
    private Integration_Registry $integrations;

    public function __construct( Integration_Registry $integrations ) {
        $this->integrations = $integrations;
    }

    public function hooks(): void {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'settings' ) );
        add_action( 'admin_notices', array( Directory::class, 'notice' ) );
        add_action( 'admin_notices', array( Schedule_Admin::class, 'notice' ) );
        add_filter( 'option_page_capability_prihod_profile', function () { return 'manage_prihod'; } );
        add_filter( 'option_page_capability_prihod_modules', function () { return 'manage_prihod_settings'; } );
        add_filter( 'option_page_capability_prihod_privacy', function () { return 'manage_prihod_settings'; } );
    }

    public function menu(): void {
        // Settings-only staff still need the parent menu to reach their pages.
        $parent_capability = self::menu_capability();
        add_menu_page( __( 'Приход', 'prihod' ), __( 'Приход', 'prihod' ), $parent_capability, 'prihod', array( $this, 'overview' ), 'dashicons-admin-home', 26 );
        add_submenu_page( 'prihod', __( 'Обзор прихода', 'prihod' ), __( 'Обзор прихода', 'prihod' ), $parent_capability, 'prihod', array( $this, 'overview' ), 0 );
        add_submenu_page( 'prihod', __( 'Сведения о приходе', 'prihod' ), __( 'Сведения о приходе', 'prihod' ), 'manage_prihod', 'prihod-profile', array( $this, 'profile' ) );
        add_submenu_page( 'prihod', __( 'Подключения', 'prihod' ), __( 'Подключения', 'prihod' ), 'manage_prihod_settings', 'prihod-integrations', array( $this, 'integrations' ) );
        add_submenu_page( 'prihod', __( 'Настройки', 'prihod' ), __( 'Настройки', 'prihod' ), 'manage_prihod_settings', 'prihod-settings', array( $this, 'configuration' ) );
    }

    private function require_capability( string $capability ): void {
        if ( ! current_user_can( $capability ) ) {
            wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) );
        }
    }
    public static function menu_capability(): string {
        foreach ( array( 'manage_prihod', 'manage_prihod_schedule', 'view_prihod_requests', 'manage_prihod_settings' ) as $cap ) { if ( current_user_can( $cap ) ) { return $cap; } }
        return 'manage_prihod';
    }

    public function settings(): void {
        register_setting( 'prihod_modules', 'prihod_modules', array( 'type' => 'array', 'sanitize_callback' => array( Modules::class, 'sanitize' ), 'show_in_rest' => false ) );
        register_setting( 'prihod_privacy', 'prihod_retention_days', array( 'type' => 'integer', 'sanitize_callback' => fn( $days ) => is_scalar( $days ) ? max( 30, min( 3650, (int) $days ) ) : 365, 'show_in_rest' => false ) );
        register_setting( 'prihod_profile', Profile::OPTION, array(
            'type' => 'array', 'sanitize_callback' => array( Profile::class, 'sanitize' ),
            'default' => Profile::defaults(), 'show_in_rest' => false,
        ) );
    }

    public function overview(): void {
        $this->require_capability( self::menu_capability() );
        Dashboard::overview();
    }

    public function profile(): void {
        $this->require_capability( 'manage_prihod' );
        $profile = Profile::get();
        echo '<div class="wrap"><h1>' . esc_html__( 'Сведения о приходе', 'prihod' ) . '</h1>';
        settings_errors( Profile::OPTION );
        echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
        settings_fields( 'prihod_profile' );
        echo '<table class="form-table"><tbody><tr><th><label for="prihod-type">' . esc_html__( 'Тип организации', 'prihod' ) . '</label></th><td><select id="prihod-type" name="prihod_profile[type]">';
        foreach ( Profile::types() as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $profile['type'], $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></td></tr>';
        foreach ( Profile::fields() as $key => $label ) {
            echo '<tr><th><label for="prihod-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
            $name = 'prihod_profile[' . $key . ']';
            if ( in_array( $key, array( 'description', 'address' ), true ) ) {
                echo '<textarea class="large-text" rows="4" id="prihod-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $profile[ $key ] ) . '</textarea>';
            } elseif ( 'timezone' === $key ) {
                echo '<select id="prihod-timezone" name="' . esc_attr( $name ) . '">';
                foreach ( timezone_identifiers_list() as $timezone ) {
                    echo '<option value="' . esc_attr( $timezone ) . '"' . selected( $profile[ $key ], $timezone, false ) . '>' . esc_html( $timezone ) . '</option>';
                }
                echo '</select>';
            } else {
                $type = 'email' === $key ? 'email' : ( 'website' === $key ? 'url' : 'text' );
                echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="prihod-' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $profile[ $key ] ) . '">';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table><p>' . esc_html__( 'Язык и часовой пояс относятся к приходу; настройки самого WordPress не изменяются. Основной язык задаётся кодом, например ru_RU.', 'prihod' ) . '</p>';
        submit_button();
        echo '</form></div>';
    }

    public function integrations(): void {
        $this->require_capability( 'manage_prihod_settings' );
        echo '<div class="wrap"><h1>' . esc_html__( 'Подключения', 'prihod' ) . '</h1>';
        if ( ! $this->integrations->all() ) {
            echo '<p>' . esc_html__( 'Расширения пока не подключены. Календарь и «Записки» будут интегрированы после проверки их фактического кода.', 'prihod' ) . '</p>';
        }
        $labels = array(
            'ready' => __( 'Готово', 'prihod' ), 'needs_setup' => __( 'Требуется настройка', 'prihod' ),
            'unavailable' => __( 'Недоступно', 'prihod' ),
        );
        foreach ( $this->integrations->all() as $integration ) {
            $report = $this->integrations->report( $integration );
            echo '<section class="prihod-dash-card"><h2>' . esc_html( $report['name'] ) . '</h2><p>' . esc_html( $labels[ $report['status'] ] ) . '</p><p>' . esc_html( $report['message'] ) . '</p>';
            if ( ! empty( $report['settings_url'] ) ) { echo '<p><a href="' . esc_url( $report['settings_url'] ) . '">' . esc_html__( 'Настроить подключение', 'prihod' ) . '</a></p>'; }
            echo '</section>';
        }
        echo '</div>';
    }

    public function configuration(): void {
        $this->require_capability( 'manage_prihod_settings' );
        echo '<div class="wrap"><h1>' . esc_html__( 'Настройки', 'prihod' ) . '</h1><h2>' . esc_html__( 'Хранение данных', 'prihod' ) . '</h2>';
        settings_errors( 'prihod_modules' );
        echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
        settings_fields( 'prihod_modules' );
        foreach ( array( 'schedule' => __( 'Расписание включено', 'prihod' ), 'requests' => __( 'Обращения включены', 'prihod' ) ) as $module => $label ) { echo '<p><label><input type="checkbox" name="prihod_modules[' . esc_attr( $module ) . ']" value="1"' . checked( Modules::enabled( $module ), true, false ) . '> ' . esc_html( $label ) . '</label></p>'; }
        submit_button();
        echo '</form>';
        echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
        settings_fields( 'prihod_privacy' );
        echo '<p><label>' . esc_html__( 'Удалять закрытые обращения через дней после последнего изменения (30–3650)', 'prihod' ) . ' <input type="number" min="30" max="3650" name="prihod_retention_days" value="' . esc_attr( get_option( 'prihod_retention_days', 365 ) ) . '"></label></p>';
        submit_button(); echo '</form>';
        echo '<p>' . esc_html__( 'Деактивация и обычное удаление плагина сохраняют данные. Закрытые обращения очищаются по заданному сроку хранения.', 'prihod' ) . '</p>';
        echo '<h2>' . esc_html__( 'Права доступа', 'prihod' ) . '</h2><p>' . esc_html__( 'Права выдаются администратору при установке. Другим сотрудникам их можно назначить средствами управления ролями WordPress. Обычный редактор автоматически их не получает.', 'prihod' ) . '</p><ul>';
        foreach ( Capabilities::all() as $capability ) {
            echo '<li><code>' . esc_html( $capability ) . '</code></li>';
        }
        echo '</ul><p>' . esc_html__( 'Назначить права можно в разделе «Права сотрудников».', 'prihod' ) . '</p>';
        Calendar_Reminders::render();
        Calendar_Icons::render();
        echo '</div>';
    }
}
