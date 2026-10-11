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
        add_filter( 'option_page_capability_prihod_profile', function () { return 'manage_prihod'; } );
    }

    public function menu(): void {
        // Settings-only staff still need the parent menu to reach their pages.
        $parent_capability = current_user_can( 'manage_prihod' ) ? 'manage_prihod' : 'manage_prihod_settings';
        add_menu_page( __( 'Приход', 'prihod' ), __( 'Приход', 'prihod' ), $parent_capability, 'prihod', array( $this, 'overview' ), 'dashicons-admin-home', 26 );
        add_submenu_page( 'prihod', __( 'Обзор', 'prihod' ), __( 'Обзор', 'prihod' ), $parent_capability, 'prihod', array( $this, 'overview' ) );
        add_submenu_page( 'prihod', __( 'Сведения о приходе', 'prihod' ), __( 'Сведения о приходе', 'prihod' ), 'manage_prihod', 'prihod-profile', array( $this, 'profile' ) );
        add_submenu_page( 'prihod', __( 'Подключения', 'prihod' ), __( 'Подключения', 'prihod' ), 'manage_prihod_settings', 'prihod-integrations', array( $this, 'integrations' ) );
        add_submenu_page( 'prihod', __( 'Настройки', 'prihod' ), __( 'Настройки', 'prihod' ), 'manage_prihod_settings', 'prihod-settings', array( $this, 'configuration' ) );
    }

    private function require_capability( string $capability ): void {
        if ( ! current_user_can( $capability ) ) {
            wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) );
        }
    }

    public function settings(): void {
        register_setting( 'prihod_profile', Profile::OPTION, array(
            'type' => 'array', 'sanitize_callback' => array( Profile::class, 'sanitize' ),
            'default' => Profile::defaults(), 'show_in_rest' => false,
        ) );
    }

    public function overview(): void {
        if ( ! current_user_can( 'manage_prihod' ) && ! current_user_can( 'manage_prihod_settings' ) ) {
            $this->require_capability( 'manage_prihod' );
        }
        echo '<div class="wrap"><h1>' . esc_html__( 'Приход — обзор', 'prihod' ) . '</h1>';
        if ( current_user_can( 'manage_prihod' ) ) {
            $profile = Profile::get();
            echo '<p>' . esc_html( $profile['name'] ?: __( 'Название прихода ещё не заполнено.', 'prihod' ) ) . '</p>';
            echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=prihod-profile' ) ) . '">' . esc_html__( 'Заполнить сведения о приходе', 'prihod' ) . '</a></p>';
        }
        echo '<p>' . esc_html__( 'Доступны сведения о приходе, места служения и духовенство. Расписание, обращения, мастер и интеграции с внешними сервисами будут добавлены на следующих этапах.', 'prihod' ) . '</p>';
        echo '<p>' . esc_html__( 'Активация не создаёт страницы и не изменяет главную, меню или тему сайта.', 'prihod' ) . '</p></div>';
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
            echo '<h2>' . esc_html( $report['name'] ) . '</h2><p>' . esc_html( $labels[ $report['status'] ] ) . '</p><p>' . esc_html( $report['message'] ) . '</p>';
        }
        echo '</div>';
    }

    public function configuration(): void {
        $this->require_capability( 'manage_prihod_settings' );
        echo '<div class="wrap"><h1>' . esc_html__( 'Настройки', 'prihod' ) . '</h1><h2>' . esc_html__( 'Хранение данных', 'prihod' ) . '</h2>';
        echo '<p>' . esc_html__( 'Деактивация и обычное удаление плагина сохраняют сведения о приходе, места служения и духовенство. Автоматическое удаление данных отсутствует.', 'prihod' ) . '</p>';
        echo '<h2>' . esc_html__( 'Права доступа', 'prihod' ) . '</h2><p>' . esc_html__( 'Права выдаются администратору при установке. Другим сотрудникам их можно назначить средствами управления ролями WordPress. Обычный редактор автоматически их не получает.', 'prihod' ) . '</p><ul>';
        foreach ( Capabilities::all() as $capability ) {
            echo '<li><code>' . esc_html( $capability ) . '</code></li>';
        }
        echo '</ul><p>' . esc_html__( 'Права расписания и обращений зарезервированы для следующих этапов. Интерфейс назначения прав в самом плагине пока отсутствует.', 'prihod' ) . '</p></div>';
    }
}
