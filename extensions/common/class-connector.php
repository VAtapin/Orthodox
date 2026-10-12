<?php
namespace PrihodConnect;
defined( 'ABSPATH' ) || exit;

/** Shared source copied into each independently installable connector package. */
final class Connector {
    private string $id;
    private string $title;
    private string $file;
    private static array $instances = array();
    public static function instance( string $id ): ?self { return self::$instances[$id] ?? null; }
    public static function boot( string $id, string $title, string $file ): self {
        if ( isset( self::$instances[ $id ] ) ) { return self::$instances[ $id ]; }
        return self::$instances[ $id ] = new self( $id, $title, $file );
    }
    private function __construct( string $id, string $title, string $file ) {
        $this->id = $id; $this->title = $title; $this->file = $file;
        add_action( 'admin_menu', array( $this, 'menu' ), 20 );
        add_action( 'admin_post_prihod_connect_' . $id, array( $this, 'save' ) );
        add_action( 'admin_post_prihod_retry_' . $id, array( $this, 'retry' ) );
        add_action( 'prihod_' . $id . '_worker', array( $this, 'worker' ) );
        add_action( 'add_meta_boxes_post', array( $this, 'meta_box' ) );
        add_action( 'save_post_post', array( $this, 'publish_post' ), 20 );
        add_action( 'prihod_request_created', function ( $request ) { if ( ! empty( $this->settings()['notify_requests'] ) && apply_filters( 'prihod_connector_request_notification', true, $this->id ) ) { $this->queue( __( 'Новое обращение. Откройте закрытый раздел прихода.', 'prihod-connect' ), admin_url( 'admin.php?page=prihod-requests&request_id=' . absint( $request ) ), 'request', (int) $request ); } } );
        add_action( 'prihod_request_deleted', function ( $request ) {
            foreach ( get_post_meta( $request, '_prihod_jobs_' . $this->id, true ) ?: array() as $id ) { delete_option( 'prihod_job_' . $this->id . '_' . $id ); wp_clear_scheduled_hook( 'prihod_' . $this->id . '_worker', array( $id ) ); }
        } );
        add_action( 'prihod_schedule_changed', function ( $service ) {
            if ( ! empty( $this->settings()['notify_schedule'] ) && 'publish' === get_post_status( $service ) ) {
                $resources = get_option( 'prihod_resources', array() ); $url = ! empty( $resources['schedule']['id'] ) ? get_permalink( $resources['schedule']['id'] ) : home_url( '/' );
                $this->queue( __( 'Изменено расписание:', 'prihod-connect' ) . ' ' . get_the_title( $service ), $url, 'schedule', (int) $service );
            }
        } );
        add_action( 'prihod_register_integrations', function ( $registry ) {
            $registry->register( new class( $this ) implements \Prihod\Integration {
                private Connector $connector;
                public function __construct( Connector $connector ) { $this->connector = $connector; }
                public function id(): string { return $this->connector->id; }
                public function describe(): array { return array( 'name' => $this->connector->title, 'capabilities' => array( 'notifications', 'publications' ), 'settings_url' => $this->connector->settings_url(), 'blocks' => array(), 'pages' => array(), 'external_services' => array( $this->connector->id ) ); }
                public function health(): array { return $this->connector->health(); }
            } );
        } );
    }
    public function __get( string $key ) { return in_array( $key, array( 'id', 'title' ), true ) ? $this->$key : null; }
    public function settings(): array { $saved = get_option( 'prihod_connect_' . $this->id, array() ); return array_merge( array( 'enabled' => false, 'token' => '', 'target' => '', 'api_version' => '', 'recipient' => '', 'template' => '', 'language' => 'ru', 'recipient_consent' => false, 'notify_requests' => false, 'notify_schedule' => false ), is_array( $saved ) ? $saved : array() ); }
    public function settings_url(): string { return admin_url( 'admin.php?page=prihod-connect-' . $this->id ); }
    private function authorize(): void { if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_prihod_settings' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod-connect' ), '', array( 'response' => 403 ) ); } }
    public function menu(): void {
        $capability = current_user_can( 'manage_options' ) ? 'manage_options' : 'manage_prihod_settings';
        if ( class_exists( '\Prihod\Plugin' ) ) { add_submenu_page( 'prihod', $this->title, $this->title, $capability, 'prihod-connect-' . $this->id, array( $this, 'render' ) ); }
        else { add_menu_page( $this->title, $this->title, $capability, 'prihod-connect-' . $this->id, array( $this, 'render' ), 'dashicons-share' ); }
    }
    public function health(): array {
        $ready = $this->valid( $this->settings() );
        $last = get_option( 'prihod_connect_last_' . $this->id, array() );
        return array( 'status' => $ready ? ( 'failed' === ( $last['status'] ?? '' ) ? 'needs_setup' : 'ready' ) : 'needs_setup',
            'message' => $ready ? ( 'sent' === ( $last['status'] ?? '' ) ? __( 'Последняя отправка принята сервисом.', 'prihod-connect' ) : __( 'Настройки заполнены; успешная живая отправка ещё не подтверждена.', 'prihod-connect' ) ) : __( 'Заполните подключение и явно включите отправки.', 'prihod-connect' ) );
    }
    public function valid( array $settings ): bool {
        if ( empty( $settings['enabled'] ) || ! is_string( $settings['token'] ) || ! is_string( $settings['target'] ) ) { return false; }
        if ( 'telegram' === $this->id ) { return (bool) preg_match( '/^[0-9]+:[A-Za-z0-9_-]{10,}$/D', $settings['token'] ) && (bool) preg_match( '/^(?:-?[0-9]+|@[A-Za-z0-9_]{5,})$/D', $settings['target'] ); }
        if ( ! preg_match( '/^[A-Za-z0-9._-]+$/D', $settings['token'] ) || ! preg_match( '/^[0-9]+$/D', $settings['target'] ) || ! preg_match( '/^v[0-9]+\.[0-9]+$/D', $settings['api_version'] ) ) { return false; }
        return 'whatsapp' !== $this->id || ( ! empty( $settings['recipient_consent'] ) && preg_match( '/^[1-9][0-9]{6,14}$/D', $settings['recipient'] ) && preg_match( '/^[a-z0-9_]+$/D', $settings['template'] ) && preg_match( '/^[a-z]{2,3}(?:_[A-Z]{2})?$/D', $settings['language'] ) );
    }
    public function render(): void {
        $this->authorize(); $settings = $this->settings();
        $error_key = 'prihod_connector_error_' . $this->id . '_' . get_current_user_id();
        foreach ( (array) get_transient( $error_key ) as $error ) { echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
        delete_transient( $error_key);
        if ( 'telegram' === $this->id ) {
            $error_codes = isset( $_GET['prihod_connect_error'] ) && is_string( $_GET['prihod_connect_error'] ) ? explode( ',', $_GET['prihod_connect_error'] ) : array();
            foreach ( self::telegram_error_messages() as $code => $message ) { if ( in_array( $code, $error_codes, true ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>'; } }
            echo '<p>' . esc_html__( 'Для отправки обязательны токен бота и Chat ID получателя. Для личного чата укажите числовой Chat ID; для канала — @имя_канала. Имя самого бота сюда не подходит.', 'prihod-connect' ) . '</p>';
        }
        echo '<div class="wrap"><h1>' . esc_html( $this->title ) . '</h1><p>' . esc_html( $this->health()['message'] ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_connect_' . esc_attr( $this->id ) . '">'; wp_nonce_field( 'prihod_connect_' . $this->id );
        echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked( $settings['enabled'], true, false ) . '> ' . esc_html__( 'Разрешить отправки во внешний сервис', 'prihod-connect' ) . '</label></p><p><label>' . esc_html__( 'Token (пусто — сохранить прежний)', 'prihod-connect' ) . ' <input type="password" name="token" autocomplete="new-password" value=""></label> <label><input type="checkbox" name="clear_token" value="1"> ' . esc_html__( 'Удалить token', 'prihod-connect' ) . '</label></p>';
        $fields = array( 'target' => 'telegram' === $this->id ? __( 'Chat ID / @канал', 'prihod-connect' ) : ( 'whatsapp' === $this->id ? __( 'Phone number ID', 'prihod-connect' ) : __( 'Facebook Page ID', 'prihod-connect' ) ) );
        if ( 'telegram' !== $this->id ) { $fields['api_version'] = __( 'Версия Graph API из настроек приложения', 'prihod-connect' ); }
        if ( 'whatsapp' === $this->id ) { $fields += array( 'recipient' => __( 'Получатель: международный номер без +', 'prihod-connect' ), 'template' => __( 'Одобренный шаблон с одним текстовым параметром в body', 'prihod-connect' ), 'language' => __( 'Язык шаблона', 'prihod-connect' ) ); }
        foreach ( $fields as $key => $label ) { echo '<p><label>' . esc_html( $label ) . '<br><input class="regular-text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $settings[ $key ] ) . '"></label></p>'; }
        if ( 'whatsapp' === $this->id ) { echo '<p><label><input type="checkbox" name="recipient_consent" value="1"' . checked( $settings['recipient_consent'], true, false ) . '> ' . esc_html__( 'Получатель согласился получать эти сообщения', 'prihod-connect' ) . '</label></p>'; }
        if ( 'facebook' !== $this->id ) {
            foreach ( array( 'notify_requests' => 'telegram' === $this->id ? __( 'Уведомлять о новых обращениях', 'prihod-connect' ) : __( 'Уведомлять о новых обращениях (без имени, email и текста)', 'prihod-connect' ), 'notify_schedule' => __( 'Уведомлять об изменениях опубликованного расписания', 'prihod-connect' ) ) as $key => $label ) { echo '<p><label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $settings[ $key ], true, false ) . '> ' . esc_html( $label ) . '</label></p>'; }
        }
        do_action( 'prihod_connector_settings_fields', $this->id, $settings );
        echo '<p>' . esc_html__( 'Публикации отправляются только по отдельному выбору в редакторе записи. Для WhatsApp получатель должен согласиться на сообщения; шаблон утверждается в Meta. Отправки выполняются через WP-Cron.', 'prihod-connect' ) . '</p>'; submit_button(); echo '</form>';
        if ( 'telegram' === $this->id ) { wp_enqueue_script( 'prihod-telegram-settings', plugins_url( 'assets/settings.js', $this->file ), array(), '0.1.3', true ); wp_localize_script( 'prihod-telegram-settings', 'prihodTelegramSettings', array( 'hasToken' => '' !== $settings['token'] ) ); }
        $last = get_option( 'prihod_connect_last_' . $this->id, array() );
        if ( 'failed' === ( $last['status'] ?? '' ) ) {
            echo '<p>' . esc_html__( 'Последняя отправка завершилась ошибкой. Код HTTP:', 'prihod-connect' ) . ' ' . esc_html( $last['http_code'] ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_retry_' . esc_attr( $this->id ) . '">'; wp_nonce_field( 'prihod_retry_' . $this->id );
            echo '<button class="button">' . esc_html__( 'Повторить последнюю отправку после исправления настроек', 'prihod-connect' ) . '</button></form>';
        }
        do_action( 'prihod_connector_settings_after', $this->id );
        echo '</div>';
    }
    public function save(): void {
        $this->authorize(); check_admin_referer( 'prihod_connect_' . $this->id ); $settings = $this->settings();
        foreach ( array( 'token', 'target', 'api_version', 'recipient', 'template', 'language' ) as $key ) {
            if ( ! isset( $_POST[ $key ] ) ) { continue; } if ( ! is_string( $_POST[ $key ] ) ) { wp_die( esc_html__( 'Некорректное поле.', 'prihod-connect' ) ); }
            $value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); if ( 'token' !== $key || '' !== $value ) { $settings[ $key ] = $value; }
        }
        if ( ! empty( $_POST['clear_token'] ) ) { $settings['token'] = ''; }
        foreach ( array( 'enabled', 'notify_requests', 'notify_schedule', 'recipient_consent' ) as $key ) { $settings[ $key ] = ! empty( $_POST[ $key ] ) && ( 'facebook' !== $this->id || 'enabled' === $key ); }
        if ( 'telegram' === $this->id && $settings['enabled'] ) {
            $errors = $this->telegram_errors( $settings );
            if ( $errors ) { wp_safe_redirect( add_query_arg( 'prihod_connect_error', implode( ',', $errors ), $this->settings_url() ) ); exit; }
        }
        if ( $settings['enabled'] && ! $this->valid( $settings ) ) { wp_die( esc_html__( 'Проверьте token, идентификаторы и настройки API. Подключение не сохранено.', 'prihod-connect' ) ); }
        $extra_errors = apply_filters( 'prihod_connector_settings_errors', array(), $this->id, $settings );
        if ( $extra_errors ) { set_transient( 'prihod_connector_error_' . $this->id . '_' . get_current_user_id(), $extra_errors, 120 ); wp_safe_redirect( $this->settings_url() ); exit; }
        update_option( 'prihod_connect_' . $this->id, $settings, false ); do_action( 'prihod_connector_saved', $this->id, $settings ); wp_safe_redirect( $this->settings_url() ); exit;
    }
    public static function telegram_error_messages(): array {
        return array( 'token_missing' => __( 'Введите токен бота. Подключение не сохранено.', 'prihod-connect' ), 'token_invalid' => __( 'Токен бота имеет неверный формат. Скопируйте токен из BotFather. Подключение не сохранено.', 'prihod-connect' ), 'target_missing' => __( 'Укажите Chat ID получателя или @имя_канала. Подключение не сохранено.', 'prihod-connect' ), 'target_invalid' => __( 'Chat ID должен быть числом (для группы может начинаться с минуса) или @именем канала. Подключение не сохранено.', 'prihod-connect' ) );
    }
    public function telegram_errors( array $settings ): array {
        $errors = array();
        if ( empty( $settings['token'] ) ) { $errors[] = 'token_missing'; }
        elseif ( ! is_string( $settings['token'] ) || ! preg_match( '/^[0-9]+:[A-Za-z0-9_-]{10,}$/D', $settings['token'] ) ) { $errors[] = 'token_invalid'; }
        if ( empty( $settings['target'] ) ) { $errors[] = 'target_missing'; }
        elseif ( ! is_string( $settings['target'] ) || ! preg_match( '/^(?:-?[0-9]+|@[A-Za-z0-9_]{5,})$/D', $settings['target'] ) ) { $errors[] = 'target_invalid'; }
        return $errors;
    }
    public function meta_box(): void {
        if ( ! $this->valid( $this->settings() ) ) { return; }
        add_meta_box( 'prihod-connect-' . $this->id, $this->title, function () {
            wp_nonce_field( 'prihod_publish_' . $this->id, 'prihod_publish_nonce_' . $this->id );
            echo '<label><input type="checkbox" name="prihod_publish[' . esc_attr( $this->id ) . ']" value="1"> ' . esc_html__( 'Отправить эту опубликованную запись в сервис', 'prihod-connect' ) . '</label>';
        }, 'post', 'side' );
    }
    public function publish_post( int $id ): void {
        $nonce = $_POST[ 'prihod_publish_nonce_' . $this->id ] ?? '';
        if ( ! current_user_can( 'edit_post', $id ) || ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'prihod_publish_' . $this->id ) || empty( $_POST['prihod_publish'][ $this->id ] ) || 'publish' !== get_post_status( $id ) || wp_is_post_autosave( $id ) || wp_is_post_revision( $id ) ) { return; }
        $post = get_post( $id ); $text = $post->post_title . "\n" . wp_html_excerpt( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 700, '…' );
        $this->queue( $text, get_permalink( $id ), 'post', $id );
    }
    public function queue( string $text, string $url, string $kind, int $source, array $reply_context = array() ): string {
        if ( ! $this->valid( $this->settings() ) ) { return ''; }
        if ( ! in_array( $kind, array( 'post', 'request', 'schedule' ), true ) || ( 'facebook' === $this->id && 'post' !== $kind ) ) { return ''; }
        $settings = $this->settings(); $limit = max( 1, min( 3000, (int) apply_filters( 'prihod_connector_text_limit', 900, $this->id, $kind ) ) );
        $html = 'telegram' === $this->id && 'request' === $kind && ! empty( $reply_context['telegram_html'] ) && ! empty( $reply_context['request_id'] );
        $text = $html ? wp_kses( $text, array( 'b' => array() ) ) : wp_html_excerpt( wp_strip_all_tags( $text ), $limit, '…' ); $url = esc_url_raw( $url );
        $id = hash( 'sha256', $this->id . $kind . $source . $text . $url . $settings['target'] . $settings['recipient'] . ( $reply_context['configuration'] ?? '' ) ); $key = 'prihod_job_' . $this->id . '_' . $id;
        $target = hash( 'sha256', $settings['target'] . '|' . $settings['recipient'] );
        if ( add_option( $key, array( 'text' => $text, 'url' => $url, 'kind' => $kind, 'source' => $source, 'target' => $target, 'status' => 'pending', 'attempts' => 0, 'reply_context' => $reply_context ), '', false ) ) {
            $scheduled = wp_schedule_single_event( time() + 1, 'prihod_' . $this->id . '_worker', array( $id ), true );
            if ( is_wp_error( $scheduled ) || ! $scheduled ) { delete_option( $key ); return ''; }
            if ( 'request' === $kind ) { $jobs = get_post_meta( $source, '_prihod_jobs_' . $this->id, true ) ?: array(); $jobs[] = $id; update_post_meta( $source, '_prihod_jobs_' . $this->id, $jobs ); }
        }
        return $id;
    }
    public function request( array $job ): array {
        $settings = $this->settings(); $html = 'telegram' === $this->id && ! empty( $job['reply_context']['telegram_html'] );
        $text = $job['text'] . ( $html ? "\n\n" : "\n" ) . ( $html ? esc_html( $job['url'] ) : $job['url'] );
        $headers = array( 'Content-Type' => 'application/json' );
        if ( 'telegram' === $this->id ) { $url = 'https://api.telegram.org/bot' . $settings['token'] . '/sendMessage'; $body = array( 'chat_id' => $settings['target'], 'text' => $text, 'link_preview_options' => array( 'is_disabled' => true ) ); }
        else {
            $headers['Authorization'] = 'Bearer ' . $settings['token'];
            $url = 'https://graph.facebook.com/' . $settings['api_version'] . '/' . $settings['target'] . ( 'whatsapp' === $this->id ? '/messages' : '/feed' );
            $body = 'whatsapp' === $this->id ? array( 'messaging_product' => 'whatsapp', 'to' => $settings['recipient'], 'type' => 'template', 'template' => array( 'name' => $settings['template'], 'language' => array( 'code' => $settings['language'] ), 'components' => array( array( 'type' => 'body', 'parameters' => array( array( 'type' => 'text', 'text' => wp_html_excerpt( $text, 1000, '…' ) ) ) ) ) ) ) : array( 'message' => $job['text'], 'link' => $job['url'] );
        }
        if ( $html ) { $body['parse_mode'] = 'HTML'; }
        return array( 'url' => $url, 'args' => array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'timeout' => 15, 'redirection' => 0 ) );
    }
    public function worker( string $id ): void {
        if ( ! preg_match( '/^[a-f0-9]{64}$/D', $id ) ) { return; }
        $key = 'prihod_job_' . $this->id . '_' . $id; $job = get_option( $key );
        if ( ! is_array( $job ) || in_array( $job['status'], array( 'sent', 'cancelled' ), true ) || $job['attempts'] >= 3 || ! $this->valid( $this->settings() ) ) { return; }
        if ( ! apply_filters( 'prihod_connector_job_allowed', true, $this->id, $job ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
        $settings = $this->settings(); if ( ! hash_equals( $job['target'], hash( 'sha256', $settings['target'] . '|' . $settings['recipient'] ) ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
        if ( ( 'request' === $job['kind'] && ( ! get_post( $job['source'] ) || get_option( 'prihod_purge_job' ) ) ) || ( 'request' !== $job['kind'] && 'publish' !== get_post_status( $job['source'] ) ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
        $lock = $key . '_lock'; $owner = Lock::acquire( $lock, 60 );
        if ( ! $owner ) { return; }
        try {
            $job = get_option( $key ); if ( 'sent' === $job['status'] ) { return; }
            $request = $this->request( $job );
            try { $response = wp_remote_post( $request['url'], $request['args'] ); } catch ( \Throwable $error ) { $response = new \WP_Error( 'prihod_transport', 'Transport failed.' ); }
            $code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response ); $body = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
            $accepted = $code >= 200 && $code < 300 && is_array( $body ) && ( 'telegram' === $this->id ? ! empty( $body['ok'] ) : ( 'whatsapp' === $this->id ? ! empty( $body['messages'][0]['id'] ) : ! empty( $body['id'] ) ) );
            ++$job['attempts']; $job['status'] = $accepted ? 'sent' : 'failed'; $job['http_code'] = $code;
            update_option( $key, $job, false ); update_option( 'prihod_connect_last_' . $this->id, array( 'id' => $id, 'status' => $job['status'], 'http_code' => $code, 'time' => time() ), false );
            if ( $accepted ) { do_action( 'prihod_connector_sent', $this->id, $id, $job, $body ); }
            if ( ! $accepted && $job['attempts'] < 3 && ( 0 === $code || 429 === $code || $code >= 500 ) ) { wp_schedule_single_event( time() + 300 * $job['attempts'], 'prihod_' . $this->id . '_worker', array( $id ) ); }
        } finally { Lock::release( $lock, $owner ); }
    }
    public function retry(): void {
        $this->authorize(); check_admin_referer( 'prihod_retry_' . $this->id );
        $this->retry_last();
        wp_safe_redirect( $this->settings_url() ); exit;
    }
    public function retry_last(): void {
        $this->authorize();
        $last = get_option( 'prihod_connect_last_' . $this->id, array() ); $id = $last['id'] ?? '';
        if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{64}$/D', $id ) || ! $this->valid( $this->settings() ) ) { wp_die( esc_html__( 'Сначала исправьте подключение.', 'prihod-connect' ) ); }
        $key = 'prihod_job_' . $this->id . '_' . $id; $job = get_option( $key ); $settings = $this->settings();
        if ( ! is_array( $job ) || 'failed' !== $job['status'] || ! hash_equals( $job['target'], hash( 'sha256', $settings['target'] . '|' . $settings['recipient'] ) ) ) { wp_die( esc_html__( 'Задача недоступна или получатель изменился. Создайте новую публикацию.', 'prihod-connect' ) ); }
        $job['attempts'] = 0; $job['status'] = 'pending'; update_option( $key, $job, false );
        wp_clear_scheduled_hook( 'prihod_' . $this->id . '_worker', array( $id ) ); wp_schedule_single_event( time() + 1, 'prihod_' . $this->id . '_worker', array( $id ) );
    }
}
