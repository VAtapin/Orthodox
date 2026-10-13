<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Telegram_Request_Settings {
    public static function hooks(): void {
        add_action( 'prihod_connector_settings_fields', array( self::class, 'fields' ), 10, 2 );
        add_action( 'prihod_connector_settings_after', array( self::class, 'after' ) );
        add_filter( 'prihod_connector_settings_errors', array( self::class, 'errors' ), 10, 3 );
        add_action( 'prihod_connector_saved', array( self::class, 'saved' ), 10, 2 );
        add_action( 'admin_post_prihod_telegram_receive', array( self::class, 'connect' ) );
        add_action( 'prihod_telegram_register', array( self::class, 'register_worker' ), 10, 4 );
        add_action( 'admin_post_prihod_telegram_request', array( self::class, 'resend' ) );
        add_action( 'prihod_request_telegram_controls', array( self::class, 'request_button' ) );
    }
    private static function input(): array {
        return array( 'enabled' => ! empty( $_POST['telegram_full_requests'] ), 'staff' => is_scalar( $_POST['telegram_reply_staff'] ?? null ) ? absint( $_POST['telegram_reply_staff'] ) : 0 );
    }
    public static function errors( array $errors, string $provider, array $settings ): array {
        if ( 'telegram' !== $provider ) { return $errors; }
        $o = self::input();
        if ( $o['enabled'] ) {
            if ( ! preg_match( '/^[1-9][0-9]*$/D', $settings['target'] ) ) { $errors[] = __( 'Для ответа на обращения нужен числовой Chat ID вашего личного чата с ботом.', 'prihod' ); }
            if ( ! $settings['enabled'] || ! $settings['notify_requests'] ) { $errors[] = __( 'Включите отправки и уведомления о новых обращениях.', 'prihod' ); }
            if ( ! user_can( $o['staff'], 'view_prihod_requests' ) || ! user_can( $o['staff'], 'reply_prihod_requests' ) ) { $errors[] = __( 'Выберите сотрудника с правами просмотра и ответа на обращения.', 'prihod' ); }
        }
        return $errors;
    }
    public static function saved( string $provider, array $settings ): void {
        if ( 'telegram' !== $provider ) { return; }
        $o = array_merge( Telegram_Requests::options(), self::input() );
        if ( ! $o['enabled'] || ! hash_equals( $o['configuration'], Telegram_Requests::configuration( $settings, (int) $o['staff'] ) ) ) { $o['registered'] = false; unset( $o['connection_job'] ); wp_clear_scheduled_hook( 'prihod_telegram_register' ); }
        update_option( Telegram_Requests::OPTION, $o, false );
    }
    public static function fields( string $provider, array $settings ): void {
        if ( 'telegram' !== $provider ) { return; }
        $o = Telegram_Requests::options();
        echo '<fieldset><legend><h2>' . esc_html__( 'Обращения и ответы в Telegram', 'prihod' ) . '</h2></legend><p><label><input type="checkbox" name="telegram_full_requests" value="1"' . checked( $o['enabled'], true, false ) . '> ' . esc_html__( 'Передавать полное обращение и принимать ответы в личном чате', 'prihod' ) . '</label></p><p>' . esc_html__( 'В Telegram будут переданы имя, email, тема и полный текст обращения. Ответьте кнопкой «Ответить» на сообщение бота: сайт сохранит ответ и отправит посетителю оформленное письмо. Внутренние заметки не передаются.', 'prihod' ) . '</p><p><label>' . esc_html__( 'От имени какого сотрудника сохранять ответы', 'prihod' ) . ' <select name="telegram_reply_staff"><option value="0">' . esc_html__( 'Выберите сотрудника', 'prihod' ) . '</option>';
        foreach ( get_users( array( 'capability' => 'view_prihod_requests' ) ) as $user ) {
            if ( user_can( $user, 'reply_prihod_requests' ) ) { echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( $o['staff'], $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>'; }
        }
        echo '<p>' . esc_html__( 'Можно ответить несколько раз на сообщение бота: каждый ответ сохраняется отдельно и сразу передаётся PHP-почте. Фото с подписью и статический стикер вставляются в письмо. Ответы посетителя по email пока автоматически не принимаются.', 'prihod' ) . '</p>';
        echo '</select></label></p><p>' . esc_html__( 'Сначала сохраните настройки, затем подключите приём ответов. Каналы и группы сохраняют обычные уведомления без персональных данных.', 'prihod' ) . '</p></fieldset>';
    }
    public static function after( string $provider ): void {
        if ( 'telegram' !== $provider ) { return; }
        $key = 'prihod_telegram_notice_' . get_current_user_id(); $notice = get_transient( $key );
        if ( $notice ) { delete_transient( $key ); echo '<p role="status">' . esc_html( $notice ) . '</p>'; }
        if ( ! empty( Telegram_Requests::options()['connection_job'] ) ) { echo '<p role="status">' . esc_html__( 'Проверка подключения ещё в очереди. Обновите страницу позже.', 'prihod' ) . '</p>'; }
        echo '<p>' . esc_html( Telegram_Requests::ready() ? __( 'Приём ответов настроен. Новые обращения поступают полностью.', 'prihod' ) : __( 'Приём ответов пока не подключён; обычные уведомления сохраняют прежний режим.', 'prihod' ) ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_telegram_receive">'; wp_nonce_field( 'prihod_telegram_receive' );
        submit_button( __( 'Подключить / проверить приём ответов', 'prihod' ), 'secondary' ); echo '</form>';
    }
    public static function api( string $method, array $body = array(), array $snapshot = array() ) {
        $connector = Telegram_Requests::connector();
        $settings = $snapshot ?: ( $connector ? $connector->settings() : array() );
        if ( ! $connector || ! in_array( $method, array( 'getMe', 'getChat', 'getWebhookInfo', 'setWebhook', 'getFile' ), true ) || ! $connector->valid( $settings ) ) { return new \WP_Error( 'telegram_settings', __( 'Сначала сохраните подключение.', 'prihod' ) ); }
        $response = wp_remote_post( 'https://api.telegram.org/bot' . $settings['token'] . '/' . $method, array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ), 'timeout' => 15, 'redirection' => 0 ) );
        $data = is_wp_error( $response ) ? null : json_decode( wp_remote_retrieve_body( $response ), true );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) || empty( $data['ok'] ) ) { return new \WP_Error( 'telegram_api', __( 'Telegram не подтвердил подключение. Проверьте токен, Chat ID и доступность сервиса.', 'prihod' ) ); }
        return $data['result'] ?? null;
    }
    public static function configure() {
        $connector = Telegram_Requests::connector(); $o = Telegram_Requests::options();
        if ( ! $o['enabled'] || ! $connector || ! user_can( $o['staff'], 'view_prihod_requests' ) || ! user_can( $o['staff'], 'reply_prihod_requests' ) || ! $connector->settings()['notify_requests'] || ! preg_match( '/^[1-9][0-9]*$/D', $connector->settings()['target'] ) ) { return new \WP_Error( 'telegram_settings', __( 'Сохраните полные обращения, личный Chat ID и сотрудника с правами ответа.', 'prihod' ) ); }
        $url = rest_url( Plugin::REST_NAMESPACE . '/telegram-reply' );
        if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) { return new \WP_Error( 'telegram_https', __( 'Для приёма ответов Telegram нужен адрес сайта с HTTPS.', 'prihod' ) ); }
        $settings = $connector->settings(); $fingerprint = Telegram_Requests::configuration( $settings, (int) $o['staff'] );
        $info = self::api( 'getWebhookInfo', array(), $settings ); if ( is_wp_error( $info ) ) { return $info; }
        if ( ! is_array( $info ) || ( ! empty( $info['url'] ) && $info['url'] !== $url ) ) { return new \WP_Error( 'telegram_foreign_webhook', __( 'Этот бот уже принимает сообщения в другом приложении. Его подключение не изменено.', 'prihod' ) ); }
        $me = self::api( 'getMe', array(), $settings ); if ( is_wp_error( $me ) ) { return $me; }
        $chat = self::api( 'getChat', array( 'chat_id' => $settings['target'] ), $settings ); if ( is_wp_error( $chat ) ) { return $chat; }
        if ( ! is_array( $me ) || empty( $me['is_bot'] ) || ! is_int( $me['id'] ?? null ) || ! is_array( $chat ) || 'private' !== ( $chat['type'] ?? '' ) || (string) ( $chat['id'] ?? '' ) !== $settings['target'] ) { return new \WP_Error( 'telegram_chat', __( 'Telegram не подтвердил личный чат с этим ботом.', 'prihod' ) ); }
        if ( ! hash_equals( $fingerprint, Telegram_Requests::configuration( $connector->settings(), (int) Telegram_Requests::options()['staff'] ) ) || ! Telegram_Requests::options()['enabled'] ) { return new \WP_Error( 'telegram_changed', __( 'Настройки изменились во время проверки. Подключите приём ответов заново.', 'prihod' ) ); }
        $o['secret'] = $o['secret'] ?: wp_generate_password( 48, false, false );
        $set = self::api( 'setWebhook', array( 'url' => $url, 'secret_token' => $o['secret'], 'allowed_updates' => array( 'message' ), 'drop_pending_updates' => false ), $settings );
        if ( is_wp_error( $set ) ) { return $set; }
        if ( true !== $set ) { return new \WP_Error( 'telegram_registration', __( 'Telegram не подтвердил приём ответов.', 'prihod' ) ); }
        if ( ! hash_equals( $fingerprint, Telegram_Requests::configuration( $connector->settings(), (int) Telegram_Requests::options()['staff'] ) ) || ! Telegram_Requests::options()['enabled'] ) { return new \WP_Error( 'telegram_changed', __( 'Настройки изменились во время проверки. Подключите приём ответов заново.', 'prihod' ) ); }
        $o['registered'] = true; $o['bot'] = (string) $me['id']; $o['configuration'] = $fingerprint;
        update_option( Telegram_Requests::OPTION, $o, false ); return true;
    }
    public static function connect(): void {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_prihod_settings' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'prihod_telegram_receive' ); $result = self::queue_registration( get_current_user_id() );
        set_transient( 'prihod_telegram_notice_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : __( 'Проверка подключения поставлена в очередь. Обновите эту страницу через некоторое время.', 'prihod' ), 120 );
        wp_safe_redirect( admin_url( 'admin.php?page=prihod-connect-telegram' ) ); exit;
    }
    public static function queue_registration( int $user ) {
        $connector = Telegram_Requests::connector(); $o = Telegram_Requests::options();
        if ( ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'manage_prihod_settings' ) ) { return new \WP_Error( 'telegram_permission', __( 'Недостаточно прав.', 'prihod' ) ); }
        if ( ! $connector || ! $o['enabled'] || ! $connector->valid( $connector->settings() ) || ! user_can( $o['staff'], 'view_prihod_requests' ) || ! user_can( $o['staff'], 'reply_prihod_requests' ) ) { return new \WP_Error( 'telegram_settings', __( 'Сохраните полные обращения, личный Chat ID и сотрудника с правами ответа.', 'prihod' ) ); }
        $generation = wp_generate_password( 24, false, false );
        wp_clear_scheduled_hook( 'prihod_telegram_register' );
        $queued = wp_schedule_single_event( time() + 1, 'prihod_telegram_register', array( $user, $generation, Telegram_Requests::configuration( $connector->settings(), (int) $o['staff'] ), 0 ), true );
        if ( is_wp_error( $queued ) || ! $queued ) { return new \WP_Error( 'telegram_queue', __( 'Проверку не удалось поставить в очередь. Попробуйте позже.', 'prihod' ) ); }
        $o['connection_job'] = $generation; update_option( Telegram_Requests::OPTION, $o, false ); return true;
    }
    public static function register_worker( int $user, string $generation, string $fingerprint, int $attempt = 0 ): void {
        $connector = Telegram_Requests::connector(); $o = Telegram_Requests::options();
        if ( $generation !== ( $o['connection_job'] ?? '' ) ) { return; }
        if ( ! $connector || ! $o['enabled'] || ! hash_equals( $fingerprint, Telegram_Requests::configuration( $connector->settings(), (int) $o['staff'] ) ) || ( ! user_can( $user, 'manage_options' ) && ! user_can( $user, 'manage_prihod_settings' ) ) ) { self::finish_registration( $user, $generation, __( 'Настройки изменились во время проверки. Подключите приём ответов заново.', 'prihod' ) ); return; }
        $lock = 'prihod_telegram_registration'; $owner = Lock::acquire( $lock, 120 );
        if ( ! $owner ) {
            $retry = $attempt < 3 && wp_schedule_single_event( time() + 30, 'prihod_telegram_register', array( $user, $generation, $fingerprint, $attempt + 1 ) );
            if ( ! $retry ) { self::finish_registration( $user, $generation, __( 'Проверку не удалось поставить в очередь. Попробуйте позже.', 'prihod' ) ); }
            return;
        }
        $previous = get_current_user_id();
        try {
            wp_set_current_user( $user ); $result = self::configure();
            self::finish_registration( $user, $generation, is_wp_error( $result ) ? $result->get_error_message() : __( 'Приём ответов подключён. Обращения отправляются полностью.', 'prihod' ) );
        } finally { wp_set_current_user( $previous ); Lock::release( $lock, $owner ); }
    }
    private static function finish_registration( int $user, string $generation, string $notice ): void {
        $current = Telegram_Requests::options();
        if ( $generation !== ( $current['connection_job'] ?? '' ) ) { return; }
        unset( $current['connection_job'] ); update_option( Telegram_Requests::OPTION, $current, false );
        set_transient( 'prihod_telegram_notice_' . $user, $notice, 300 );
    }
    public static function request_button( int $id ): void {
        if ( ! Telegram_Requests::ready() || ! current_user_can( 'reply_prihod_requests' ) ) { return; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_telegram_request"><input type="hidden" name="request_id" value="' . esc_attr( $id ) . '">'; wp_nonce_field( 'prihod_telegram_request_' . $id );
        submit_button( __( 'Отправить полное обращение в Telegram', 'prihod' ), 'secondary' ); echo '</form>';
    }
    public static function resend(): void {
        $id = is_scalar( $_POST['request_id'] ?? null ) ? absint( $_POST['request_id'] ) : 0;
        if ( ! current_user_can( 'view_prihod_requests' ) || ! current_user_can( 'reply_prihod_requests' ) || ! Telegram_Requests::ready() ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'prihod_telegram_request_' . $id ); Requests::assert_request( $id ); Telegram_Requests::notify( $id );
        wp_safe_redirect( admin_url( 'admin.php?page=prihod-requests&request_id=' . $id ) ); exit;
    }
}
