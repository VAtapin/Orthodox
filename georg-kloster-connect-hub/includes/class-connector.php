<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

/** Optional outbound channels sharing one queue and retry implementation. */
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
        add_action( 'admin_post_gkps_connect_' . $id, array( $this, 'save' ) );
        add_action( 'admin_post_gkps_retry_' . $id, array( $this, 'retry' ) );
        add_action( 'gkps_' . $id . '_worker', array( $this, 'worker' ) );
        add_action( 'gkps_request_created', function ( $request ) {
            $settings = $this->settings();
            if ( empty( $settings['notify_requests'] ) || ! apply_filters( 'gkps_connector_request_notification', true, $this->id ) ) { return; }
            $telegram_target = '';
            if ( 'telegram' === $this->id ) {
                $form_id = absint( get_post_meta( (int) $request, '_gkch_form_id', true ) );
                $telegram_target = Forms::telegram_target( $form_id, $settings['target'] );
            }
            $this->queue( __( 'New message. Open the private parish dashboard.', 'georg-kloster-connect-hub' ), admin_url( 'admin.php?page=gkps-requests&request_id=' . absint( $request ) ), 'request', (int) $request, array(), $telegram_target );
        } );
        add_action( 'gkps_request_deleted', function ( $request ) {
            foreach ( get_post_meta( $request, '_gkps_jobs_' . $this->id, true ) ?: array() as $id ) { delete_option( 'gkps_job_' . $this->id . '_' . $id ); wp_clear_scheduled_hook( 'gkps_' . $this->id . '_worker', array( $id ) ); }
        } );
        add_action( 'gkpsch_schedule_changed', function ( $service ) {
            if ( ! empty( $this->settings()['notify_schedule'] ) && 'publish' === get_post_status( $service ) ) {
                $url = home_url( '/' );
                $this->queue( __( 'Schedule updated:', 'georg-kloster-connect-hub' ) . ' ' . get_the_title( $service ), $url, 'schedule', (int) $service );
            }
        } );
    }
    public function __get( string $key ) { return in_array( $key, array( 'id', 'title' ), true ) ? $this->$key : null; }
    public function settings(): array { $saved = get_option( 'gkps_connect_' . $this->id, array() ); return array_merge( array( 'enabled' => false, 'token' => '', 'target' => '', 'api_version' => '', 'recipient' => '', 'application_key' => '', 'application_secret' => '', 'notify_requests' => false, 'notify_schedule' => false ), is_array( $saved ) ? $saved : array() ); }
    public function settings_url(): string { return add_query_arg( array( 'page' => 'gkch-settings', 'network' => $this->id ), admin_url( 'admin.php' ) ); }
    private function authorize(): void { if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); } }
    public function menu(): void {
        if ( 'telegram' !== $this->id ) { return; }
        $capability = current_user_can( 'manage_options' ) ? 'manage_options' : 'manage_gkps_settings';
        $hook = add_submenu_page( null, $this->title, $this->title, $capability, 'gkps-connect-' . $this->id, array( $this, 'render' ) );
        add_action( 'load-' . $hook, array( $this, 'legacy_settings' ) );
    }
    public function legacy_settings(): void {
        $this->authorize();
        $url = $this->settings_url();
        if ( 'telegram' === $this->id && isset( $_GET['gkps_connect_error'] ) && is_string( $_GET['gkps_connect_error'] ) ) {
            $codes = array_intersect( explode( ',', $_GET['gkps_connect_error'] ), array_keys( self::telegram_error_messages() ) );
            if ( $codes ) { $url = add_query_arg( 'gkps_connect_error', implode( ',', $codes ), $url ); }
        }
        wp_safe_redirect( $url ); exit;
    }
    public function health(): array {
        $ready = $this->valid( $this->settings() );
        $last = get_option( 'gkps_connect_last_' . $this->id, array() );
        return array( 'status' => $ready ? ( 'failed' === ( $last['status'] ?? '' ) ? 'needs_setup' : 'ready' ) : 'needs_setup',
            'message' => $ready ? ( 'sent' === ( $last['status'] ?? '' ) ? __( 'The last delivery was accepted by the service.', 'georg-kloster-connect-hub' ) : __( 'Settings are complete; a successful live delivery has not been confirmed yet.', 'georg-kloster-connect-hub' ) ) : __( 'Configure the connection and explicitly enable sending.', 'georg-kloster-connect-hub' ) );
    }
    public function valid( array $settings ): bool {
        if ( Social_Providers::supports( $this->id ) ) { return Social_Providers::valid( $this->id, $settings ); }
        if ( empty( $settings['enabled'] ) || ! is_string( $settings['token'] ) || ! is_string( $settings['target'] ) ) { return false; }
        if ( 'telegram' === $this->id ) { return (bool) preg_match( '/^[0-9]+:[A-Za-z0-9_-]{10,}$/D', $settings['token'] ) && (bool) preg_match( '/^(?:-?[0-9]+|@[A-Za-z0-9_]{5,})$/D', $settings['target'] ); }
        if ( ! preg_match( '/^[A-Za-z0-9._-]+$/D', $settings['token'] ) || ! preg_match( '/^[0-9]+$/D', $settings['target'] ) || ! preg_match( '/^v[0-9]+\.[0-9]+$/D', $settings['api_version'] ) ) { return false; }
        return true;
    }
    public function render(): void {
        $this->authorize(); $settings = $this->settings();
        $error_key = 'gkps_connector_error_' . $this->id . '_' . get_current_user_id();
        foreach ( (array) get_transient( $error_key ) as $error ) { echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; }
        delete_transient( $error_key);
        if ( 'telegram' === $this->id ) {
            $error_codes = isset( $_GET['gkps_connect_error'] ) && is_string( $_GET['gkps_connect_error'] ) ? explode( ',', $_GET['gkps_connect_error'] ) : array();
            foreach ( self::telegram_error_messages() as $code => $message ) { if ( in_array( $code, $error_codes, true ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>'; } }
            echo '<p>' . esc_html__( 'A bot token and recipient Chat ID are required. For a private chat, enter its numeric Chat ID; for a channel, enter @channel_name. Do not enter the bot\'s own username here.', 'georg-kloster-connect-hub' ) . '</p>';
        }
        echo '<div class="wrap"><h1>' . esc_html( $this->title ) . '</h1><p>' . esc_html( $this->health()['message'] ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkps_connect_' . esc_attr( $this->id ) . '">'; wp_nonce_field( 'gkps_connect_' . $this->id );
        echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked( $settings['enabled'], true, false ) . '> ' . esc_html__( 'Allow sending to the external service', 'georg-kloster-connect-hub' ) . '</label></p><p><label>' . esc_html__( 'Token (leave empty to keep the existing token)', 'georg-kloster-connect-hub' ) . ' <input type="password" name="token" autocomplete="new-password" value=""></label> <label><input type="checkbox" name="clear_token" value="1"> ' . esc_html__( 'Delete token', 'georg-kloster-connect-hub' ) . '</label></p>';
        $fields = array( 'target' => 'telegram' === $this->id ? __( 'Chat ID / @channel', 'georg-kloster-connect-hub' ) : __( 'Facebook Page ID', 'georg-kloster-connect-hub' ) );
        if ( in_array( $this->id, array( 'instagram', 'vk', 'ok' ), true ) ) { $fields['target'] = 'vk' === $this->id ? __( 'VK owner ID', 'georg-kloster-connect-hub' ) : ( 'ok' === $this->id ? __( 'OK group ID', 'georg-kloster-connect-hub' ) : __( 'Instagram account ID', 'georg-kloster-connect-hub' ) ); }
        if ( 'telegram' !== $this->id && 'ok' !== $this->id ) { $fields['api_version'] = __( 'API version from your application', 'georg-kloster-connect-hub' ); }
        foreach ( $fields as $key => $label ) {
            echo '<p><label>' . esc_html( $label ) . '<br><input class="regular-text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $settings[ $key ] ) . '"></label>';
            if ( 'api_version' === $key ) {
                echo '<br><span class="description">' . ( 'vk' === $this->id ? 'Это версия методов VK API, а не токен. Скопируйте версию из документации метода; формат — например, <code>5.199</code>, без буквы v.' : 'Это версия Graph API Meta в URL запросов, а не ключ и не версия плагина. Скопируйте значение из списка версий в Graph API Explorer, например в формате <code>vNN.N</code>.' ) . '</span>';
            }
            echo '</p>';
        }
        if ( 'telegram' === $this->id ) {
            foreach ( array( 'notify_requests' => __( 'Notify about new messages', 'georg-kloster-connect-hub' ), 'notify_schedule' => __( 'Notify about changes to published schedules', 'georg-kloster-connect-hub' ) ) as $key => $label ) { echo '<p><label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $settings[ $key ], true, false ) . '> ' . esc_html( $label ) . '</label></p>'; }
        }
        if ( Social_Providers::supports( $this->id ) ) { echo '<p>' . esc_html( Social_Providers::help( $this->id ) ) . '</p>'; }
        if ( 'ok' === $this->id ) {
            echo '<p><label>' . esc_html__( 'Application public key', 'georg-kloster-connect-hub' ) . '<br><input name="application_key" value="' . esc_attr( $settings['application_key'] ) . '"></label></p><p><label>' . esc_html__( 'Application secret (leave empty to keep)', 'georg-kloster-connect-hub' ) . '<br><input type="password" name="application_secret" autocomplete="new-password" value=""></label><label><input type="checkbox" name="clear_application_secret" value="1"> ' . esc_html__( 'Delete application secret', 'georg-kloster-connect-hub' ) . '</label></p>';
        }
        $this->documentation();
        do_action( 'gkps_connector_settings_fields', $this->id, $settings );
        submit_button(); echo '</form>';
        if ( 'telegram' === $this->id ) { wp_enqueue_script( 'gkps-telegram-settings', plugins_url( 'assets/settings.js', $this->file ), array(), '0.1.3', true ); wp_localize_script( 'gkps-telegram-settings', 'prihodTelegramSettings', array( 'hasToken' => '' !== $settings['token'] ) ); }
        $last = get_option( 'gkps_connect_last_' . $this->id, array() );
        if ( 'failed' === ( $last['status'] ?? '' ) ) {
            echo '<p>' . esc_html__( 'The last delivery failed. HTTP code:', 'georg-kloster-connect-hub' ) . ' ' . esc_html( $last['http_code'] ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkps_retry_' . esc_attr( $this->id ) . '">'; wp_nonce_field( 'gkps_retry_' . $this->id );
            echo '<button class="button">' . esc_html__( 'Retry the last delivery after correcting the settings', 'georg-kloster-connect-hub' ) . '</button></form>';
        }
        do_action( 'gkps_connector_settings_after', $this->id );
        echo '</div>';
    }
    private function documentation(): void {
        $guides = array(
            'telegram' => '<h3>Telegram: подключение и отдельный чат для каждого отдела</h3><ol><li>Откройте <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a>, отправьте <code>/newbot</code>, задайте имя и username бота. Скопируйте выданный токен в поле «Токен» выше. Токен — пароль бота: не публикуйте его и не отправляйте в переписке.</li><li>Для общего чата откройте созданного бота и нажмите Start. Чтобы узнать свой числовой ID, откройте <a href="https://t.me/userinfobot" target="_blank" rel="noopener noreferrer">@userinfobot</a>. В поле «Chat ID / @channel» укажите свой числовой ID. Это общий адрес по умолчанию для форм без отдельного адреса.</li><li>Затем откройте Connect Hub → Forms, выберите нужный отдел и в его блоке Telegram укажите личный числовой Chat ID ответственного. Каждый сотрудник должен открыть именно вашего бота и нажать Start до первой отправки. Выберите соответствующего сотрудника WordPress, от имени которого сохранять ответы.</li><li>Включите отправку и уведомления о новых обращениях. Если сотрудники должны видеть полный текст и отвечать прямо в Telegram, включите «Send complete messages and receive replies in a private chat», сохраните настройки и нажмите «Connect / check incoming replies».</li><li>Отправьте тестовое обращение отдельно через форму каждого отдела и проверьте, что оно пришло в нужный личный чат. Telegram принимает ответы только как Reply на сообщение бота с обращением. Общий токен один; маршруты, ответственные и ответы разделены по формам.</li></ol><p>Для ответов нужен личный положительный числовой ID. Имя бота, username сотрудника, ID группы и <code>@channel</code> не подходят для личной переписки. Подробности: <a href="https://core.telegram.org/bots/features#botfather" target="_blank" rel="noopener noreferrer">создание бота</a> · <a href="https://core.telegram.org/bots/api" target="_blank" rel="noopener noreferrer">Telegram Bot API</a>.</p>',
            'facebook' => '<h3>Facebook: Page token, ID страницы и версия Graph API</h3><ol><li>Откройте <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">Meta for Developers → My Apps</a> и создайте приложение. Добавьте Facebook Login/Pages API и привяжите страницу Facebook, на которой у вас есть право публиковать.</li><li>В <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener noreferrer">Graph API Explorer</a> выберите своё приложение, запросите Page Access Token для нужной страницы и разрешения, необходимые для публикации: <code>pages_manage_posts</code> и <code>pages_read_engagement</code>. Для работы приложения вне ролей разработчика/тестировщика Meta может потребовать App Review и расширенный доступ.</li><li>Скопируйте Page Access Token в поле «Токен». Укажите числовой Page ID в поле «Facebook Page ID». Не используйте пароль Facebook или App Secret вместо Page Access Token.</li><li>Поле «API version» — это версия адреса Graph API, а не ключ и не версия плагина. В Graph API Explorer выберите поддерживаемую версию в выпадающем списке рядом с запросом и перепишите её точно, с префиксом <code>v</code> (например, формат <code>vNN.N</code>).</li><li>Сохраните настройки, включите канал и сначала проверьте публикацию на тестовой записи. Этот канал публикует выбранные записи WordPress на страницу; уведомления о формах отправляются через Telegram и email.</li></ol><p>Ссылки: <a href="https://developers.facebook.com/docs/pages-api/getting-started/" target="_blank" rel="noopener noreferrer">начало работы с Pages API</a> · <a href="https://developers.facebook.com/docs/pages-api/posts/" target="_blank" rel="noopener noreferrer">публикация постов</a> · <a href="https://developers.facebook.com/docs/graph-api/overview/versioning/" target="_blank" rel="noopener noreferrer">версии Graph API</a>.</p>',
            'instagram' => '<h3>Instagram: профессиональный аккаунт, токен, ID и версия API</h3><ol><li>Переведите Instagram в профессиональный аккаунт Business или Creator и свяжите его со страницей Facebook, администратором которой вы являетесь.</li><li>В <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">Meta for Developers</a> создайте/выберите приложение и настройте Instagram API with Facebook Login. В Graph API Explorer получите токен пользователя с разрешениями <code>instagram_basic</code> и <code>instagram_content_publish</code> (для поиска связанной страницы также может потребоваться <code>pages_show_list</code>). При использовании вне ролей приложения может потребоваться App Review.</li><li>Вставьте токен в поле «Токен». В поле «Instagram account ID» укажите ID именно профессионального аккаунта Instagram, связанного со страницей, а не username и не Page ID. Его можно найти через связанный Page node и поле <code>instagram_business_account.id</code> в Graph API Explorer.</li><li>Поле «API version» — версия Graph API, используемая в запросах к Meta. Выберите доступную поддерживаемую версию в Graph API Explorer и скопируйте строку целиком, например в формате <code>vNN.N</code>. Это не секретный ключ.</li><li>Публикация требует JPEG-запись WordPress с публичным HTTPS URL: Meta должна иметь возможность скачать изображение. Сохраните настройки и проверьте на тестовой публикации.</li></ol><p>Ссылки: <a href="https://developers.facebook.com/docs/instagram-platform/" target="_blank" rel="noopener noreferrer">Instagram Platform</a> · <a href="https://developers.facebook.com/docs/instagram-platform/content-publishing/" target="_blank" rel="noopener noreferrer">правила публикации контента</a> · <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener noreferrer">Graph API Explorer</a>.</p>',
            'vk' => '<h3>ВКонтакте: токен, ID владельца и версия API</h3><ol><li>Откройте <a href="https://dev.vk.com/ru" target="_blank" rel="noopener noreferrer">VK для разработчиков</a>, создайте приложение и получите пользовательский access token с правом публикации на стене (<code>wall</code>). Не вставляйте пароль аккаунта.</li><li>Укажите access token в поле «Токен». Для публикации в сообщество укажите его числовой ID со знаком минус (например, <code>-123456</code>); положительный ID означает публикацию на личной странице. У пользователя токена должны быть права публикации в этой цели.</li><li>«API version» — версия методов VK API, а не токен. Найдите версию в документации/примере метода <code>wall.post</code> в кабинете разработчика и введите только цифры в формате <code>NN.NNN</code> (например, <code>5.199</code>), без буквы <code>v</code>.</li><li>Сохраните настройки и сначала проверьте публикацию тестовой записи. Токен даёт право публиковать от имени выбранного пользователя/сообщества, поэтому храните его как пароль.</li></ol><p>Ссылки: <a href="https://dev.vk.com/ru" target="_blank" rel="noopener noreferrer">портал VK для разработчиков</a> · <a href="https://dev.vk.com/ru/method/wall.post" target="_blank" rel="noopener noreferrer">метод wall.post</a> · <a href="https://dev.vk.com/ru/api/access-token/getting-started" target="_blank" rel="noopener noreferrer">руководство по access token</a>.</p>',
            'ok' => '<h3>Одноклассники: приложение, группа и ключи</h3><ol><li>Откройте <a href="https://apiok.ru/dev/" target="_blank" rel="noopener noreferrer">портал разработки OK</a>. Для уже существующего приложения найдите в его настройках ID приложения, публичный ключ и секретный ключ; access token создаётся там же. Официальная документация сообщает, что создание новых игр и пользовательских приложений через интерфейс OK сейчас прекращено; если приложения ещё нет, следуйте текущим указаниям портала/поддержки OK.</li><li>В поле «Token» вставьте access token; в поле «OK group ID» укажите числовой ID группы. В полях ниже укажите публичный ключ и секрет приложения. Секрет не публикуйте и не пересылайте.</li><li>Для публикации в группу приложению требуется разрешение <code>GROUP_CONTENT</code>. Если права приложения менялись, создайте новый access token после их выдачи.</li><li>Сохраните и проверьте на тестовой публикации. Для OK отдельное поле версии API не используется.</li></ol><p>Ссылки: <a href="https://apiok.ru/dev/app/create" target="_blank" rel="noopener noreferrer">создание приложения и получение ключей (актуальное уведомление OK)</a> · <a href="https://apiok.ru/dev/methods/rest/mediatopic/mediatopic.post" target="_blank" rel="noopener noreferrer">метод публикации в группу</a> · <a href="https://apiok.ru/ext/oauth/permissions" target="_blank" rel="noopener noreferrer">права приложения</a>.</p>',
        );
        if ( isset( $guides[$this->id] ) ) { echo '<details class="gkch-channel-guide"><summary>Подробная инструкция: куда нажать и где взять данные</summary><div class="gkch-channel-guide__content">' . wp_kses_post( $guides[$this->id] ) . '</div></details>'; }
    }
    public function save(): void {
        $this->authorize(); check_admin_referer( 'gkps_connect_' . $this->id ); $settings = $this->settings();
        foreach ( array( 'token', 'target', 'api_version', 'application_key', 'application_secret' ) as $key ) {
            if ( ! isset( $_POST[ $key ] ) ) { continue; } if ( ! is_string( $_POST[ $key ] ) ) { wp_die( esc_html__( 'Invalid field.', 'georg-kloster-connect-hub' ) ); }
            $value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); if ( ! in_array( $key, array( 'token', 'application_secret' ), true ) || '' !== $value ) { $settings[ $key ] = $value; }
        }
        if ( ! empty( $_POST['clear_application_secret'] ) ) { $settings['application_secret'] = ''; }
        if ( ! empty( $_POST['clear_token'] ) ) { $settings['token'] = ''; }
        foreach ( array( 'enabled', 'notify_requests', 'notify_schedule' ) as $key ) { $settings[ $key ] = ! empty( $_POST[ $key ] ) && ( 'telegram' === $this->id || 'enabled' === $key ); }
        if ( 'telegram' === $this->id && $settings['enabled'] ) {
            $errors = $this->telegram_errors( $settings );
            if ( $errors ) { wp_safe_redirect( add_query_arg( 'gkps_connect_error', implode( ',', $errors ), $this->settings_url() ) ); exit; }
        }
        if ( $settings['enabled'] && ! $this->valid( $settings ) ) { wp_die( esc_html__( 'Check the token, identifiers and API settings. The connection was not saved.', 'georg-kloster-connect-hub' ) ); }
        $extra_errors = apply_filters( 'gkps_connector_settings_errors', array(), $this->id, $settings );
        if ( $extra_errors ) { set_transient( 'gkps_connector_error_' . $this->id . '_' . get_current_user_id(), $extra_errors, 120 ); wp_safe_redirect( $this->settings_url() ); exit; }
        update_option( 'gkps_connect_' . $this->id, $settings, false ); do_action( 'gkps_connector_saved', $this->id, $settings ); wp_safe_redirect( $this->settings_url() ); exit;
    }
    public static function telegram_error_messages(): array {
        return array( 'token_missing' => __( 'Enter the bot token. The connection was not saved.', 'georg-kloster-connect-hub' ), 'token_invalid' => __( 'The bot token format is invalid. Copy the token from BotFather. The connection was not saved.', 'georg-kloster-connect-hub' ), 'target_missing' => __( 'Enter the recipient Chat ID or @channel_name. The connection was not saved.', 'georg-kloster-connect-hub' ), 'target_invalid' => __( 'Chat ID must be a number (groups may use a leading minus sign) or @channel_name. The connection was not saved.', 'georg-kloster-connect-hub' ) );
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
        add_meta_box( 'gkps-connect-' . $this->id, $this->title, function () {
            wp_nonce_field( 'gkps_publish_' . $this->id, 'gkps_publish_nonce_' . $this->id );
            echo '<label><input type="checkbox" name="gkps_publish[' . esc_attr( $this->id ) . ']" value="1"> ' . esc_html__( 'Send this published post to the service', 'georg-kloster-connect-hub' ) . '</label>';
        }, 'post', 'side' );
    }
    public function publish_post( int $id ): void {
        $nonce = $_POST[ 'gkps_publish_nonce_' . $this->id ] ?? '';
        if ( ! current_user_can( 'edit_post', $id ) || ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'gkps_publish_' . $this->id ) || empty( $_POST['gkps_publish'][ $this->id ] ) || 'publish' !== get_post_status( $id ) || wp_is_post_autosave( $id ) || wp_is_post_revision( $id ) ) { return; }
        $post = get_post( $id ); $text = $post->post_title . "\n" . wp_html_excerpt( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 700, '…' );
        $this->queue( $text, get_permalink( $id ), 'post', $id );
    }
    public function queue( string $text, string $url, string $kind, int $source, array $reply_context = array(), string $telegram_target = '' ): string {
        if ( ! $this->valid( $this->settings() ) ) { return ''; }
        if ( ! in_array( $kind, array( 'post', 'request', 'schedule', 'automation' ), true ) || ( 'telegram' !== $this->id && ! in_array( $kind, array( 'post', 'automation' ), true ) ) ) { return ''; }
        if ( 'automation' === $kind && ! Publication_Rules::allows( array( 'kind' => $kind, 'source' => $source, 'reply_context' => $reply_context ) ) ) { return ''; }
        $image = 'automation' === $kind ? Publication_Rules::image( $source ) : Social_Providers::image( $source );
        if ( 'instagram' === $this->id && ! $image ) { return ''; }
        $settings = $this->settings(); $limit = max( 1, min( 3000, (int) apply_filters( 'gkps_connector_text_limit', 900, $this->id, $kind ) ) );
        $html = 'telegram' === $this->id && 'request' === $kind && ! empty( $reply_context['telegram_html'] ) && ! empty( $reply_context['request_id'] );
        $text = $html ? wp_kses( $text, array( 'b' => array() ) ) : wp_html_excerpt( wp_strip_all_tags( $text ), $limit, '…' ); $url = esc_url_raw( $url );
        $destination = 'telegram' === $this->id && 'request' === $kind ? ( '' !== $telegram_target ? $telegram_target : $settings['target'] ) : $settings['target'];
        $id = hash( 'sha256', $this->id . $kind . $source . $text . $url . $destination . $settings['recipient'] . ( $reply_context['configuration'] ?? '' ) ); $key = 'gkps_job_' . $this->id . '_' . $id;
        $target = hash( 'sha256', $destination . '|' . $settings['recipient'] );
        if ( add_option( $key, array( 'text' => $text, 'url' => $url, 'kind' => $kind, 'source' => $source, 'target' => $target, 'telegram_target' => 'telegram' === $this->id && 'request' === $kind ? $destination : '', 'status' => 'pending', 'attempts' => 0, 'reply_context' => $reply_context, 'image_url' => 'instagram' === $this->id ? $image : '' ), '', false ) ) {
            $scheduled = wp_schedule_single_event( time() + 1, 'gkps_' . $this->id . '_worker', array( $id ), true );
            if ( is_wp_error( $scheduled ) || ! $scheduled ) { delete_option( $key ); return ''; }
            if ( 'request' === $kind ) { $jobs = get_post_meta( $source, '_gkps_jobs_' . $this->id, true ) ?: array(); $jobs[] = $id; update_post_meta( $source, '_gkps_jobs_' . $this->id, $jobs ); }
        }
        return $id;
    }
    public function request( array $job ): array {
        $settings = $this->settings(); $html = 'telegram' === $this->id && ! empty( $job['reply_context']['telegram_html'] );
        $text = $job['text'];
        if ( ! ( 'telegram' === $this->id && 'request' === ( $job['kind'] ?? '' ) ) ) { $text .= ( $html ? "\n\n" : "\n" ) . ( $html ? esc_html( $job['url'] ) : $job['url'] ); }
        $headers = array( 'Content-Type' => 'application/json' );
        if ( 'telegram' === $this->id ) { $url = 'https://api.telegram.org/bot' . $settings['token'] . '/sendMessage'; $body = array( 'chat_id' => ! empty( $job['telegram_target'] ) ? $job['telegram_target'] : $settings['target'], 'text' => $text, 'link_preview_options' => array( 'is_disabled' => true ) ); }
        else {
            $headers['Authorization'] = 'Bearer ' . $settings['token'];
            $url = 'https://graph.facebook.com/' . $settings['api_version'] . '/' . $settings['target'] . '/feed';
            $body = array( 'message' => $job['text'], 'link' => $job['url'] );
        }
        if ( $html ) { $body['parse_mode'] = 'HTML'; }
        if ( 'facebook' === $this->id && '' === $job['url'] ) { unset( $body['link'] ); }
        return array( 'url' => $url, 'args' => array( 'headers' => $headers, 'body' => wp_json_encode( $body ), 'timeout' => 15, 'redirection' => 0 ) );
    }
    public function worker( string $id ): void {
        if ( ! preg_match( '/^[a-f0-9]{64}$/D', $id ) ) { return; }
        $key = 'gkps_job_' . $this->id . '_' . $id; $job = get_option( $key );
        if ( ! is_array( $job ) || in_array( $job['status'], array( 'sent', 'cancelled', 'uncertain' ), true ) || $job['attempts'] >= ( 'instagram' === $this->id ? 16 : 3 ) || ! $this->valid( $this->settings() ) ) { return; }
        if ( ! apply_filters( 'gkps_connector_job_allowed', true, $this->id, $job ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
        $settings = $this->settings(); $destination = 'telegram' === $this->id && 'request' === ( $job['kind'] ?? '' ) ? ( $job['telegram_target'] ?? $settings['target'] ) : $settings['target']; if ( ! hash_equals( $job['target'], hash( 'sha256', $destination . '|' . $settings['recipient'] ) ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
        if ( ! Publication_Rules::allows( $job ) || ( 'request' === $job['kind'] && ( ! get_post( $job['source'] ) || get_option( 'gkps_purge_job' ) ) ) || ( ! in_array( $job['kind'], array( 'request', 'automation' ), true ) && 'publish' !== get_post_status( $job['source'] ) ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
        $lock = $key . '_lock'; $owner = Lock::acquire( $lock, 60 );
        if ( ! $owner ) { return; }
        try {
            $job = get_option( $key );
            if ( ! is_array( $job ) || in_array( $job['status'], array( 'sent', 'cancelled', 'uncertain' ), true ) || $job['attempts'] >= ( 'instagram' === $this->id ? 16 : 3 ) ) { return; }
            if ( ! Publication_Rules::allows( $job ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
            if ( Social_Providers::supports( $this->id ) ) {
                try { $result = Social_Providers::send( $this->id, $settings, $job ); }
                catch ( \Throwable $error ) { $result = array( 'http_code' => 0, 'accepted' => false, 'pending' => false, 'retry' => false, 'response' => array(), 'uncertain' => 'ok' === $this->id ); }
                $code = $result['http_code']; $body = $result['response']; $accepted = $result['accepted'];
            } else {
                $request = $this->request( $job );
                try { $response = wp_remote_post( $request['url'], $request['args'] ); } catch ( \Throwable $error ) { $response = new \WP_Error( 'gkps_transport', 'Transport failed.' ); }
                $code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response ); $body = is_wp_error( $response ) ? array() : json_decode( wp_remote_retrieve_body( $response ), true );
                $accepted = $code >= 200 && $code < 300 && is_array( $body ) && empty( $body['error'] ) && ( 'telegram' === $this->id ? true === ( $body['ok'] ?? null ) : ! empty( $body['id'] ) );
                $result = array( 'pending' => false, 'retry' => 0 === $code || 429 === $code || $code >= 500 );
            }
            ++$job['attempts'];
            $pending = ! empty( $result['pending'] ) && $job['attempts'] < 16;
            $job['status'] = $accepted ? 'sent' : ( ! empty( $result['uncertain'] ) ? 'uncertain' : ( $pending ? 'processing' : 'failed' ) );
            $job['http_code'] = $code; $job['error'] = $result['error'] ?? '';
            if ( $accepted && ! empty( $body['id'] ) ) { $job['remote_id'] = sanitize_text_field( (string) $body['id'] ); }
            if ( in_array( $job['kind'] ?? '', array( 'request', 'schedule' ), true ) && 'telegram' === $this->id ) {
                $payload = isset( $request['args']['body'] ) ? json_decode( $request['args']['body'], true ) : array();
                $content = $payload['text'] ?? $job['text'];
                if ( is_array( $content ) ) { $content = wp_json_encode( $content ); }
                $remote_id = $body['result']['message_id'] ?? '';
                $template = '';
                if ( 'schedule' === $job['kind'] ) { $template .= ( $template ? ' · ' : '' ) . get_the_title( (int) $job['source'] ); }
                $api_code = 'telegram' === $this->id ? ( $body['error_code'] ?? '' ) : ( $body['error']['code'] ?? '' );
                $error_code = is_scalar( $api_code ) && preg_match( '/^\d{1,8}$/D', (string) $api_code ) ? 'api_' . $api_code : (string) ( $result['error_code'] ?? '' );
                $is_request = 'request' === $job['kind'];
                Outgoing_Log::record( array( 'channel' => $this->id, 'recipient' => $destination, 'subject' => $template, 'content' => (string) $content, 'kind' => $is_request ? 'request_notification' : 'schedule_notification', 'source_type' => $job['kind'], 'source_id' => (int) $job['source'], 'status' => 'sent' === $job['status'] ? 'accepted' : $job['status'], 'attempt' => (int) $job['attempts'], 'http_code' => $code, 'remote_id' => (string) $remote_id, 'error_code' => $error_code ) );
            }
            update_option( $key, $job, false ); update_option( 'gkps_connect_last_' . $this->id, array( 'id' => $id, 'status' => $job['status'], 'http_code' => $code, 'time' => time() ), false );
            if ( $accepted ) { do_action( 'gkps_connector_sent', $this->id, $id, $job, $body ); }
            if ( $pending || ( ! $accepted && ! empty( $result['retry'] ) && $job['attempts'] < 3 ) ) { wp_schedule_single_event( time() + ( $pending ? 60 : 300 * $job['attempts'] ), 'gkps_' . $this->id . '_worker', array( $id ) ); }
        } finally { Lock::release( $lock, $owner ); }
    }
    public function retry(): void {
        $this->authorize(); check_admin_referer( 'gkps_retry_' . $this->id );
        $this->retry_last();
        wp_safe_redirect( $this->settings_url() ); exit;
    }
    public function retry_last(): void {
        $this->authorize();
        $last = get_option( 'gkps_connect_last_' . $this->id, array() ); $id = $last['id'] ?? '';
        if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{64}$/D', $id ) || ! $this->valid( $this->settings() ) ) { wp_die( esc_html__( 'Correct the connection first.', 'georg-kloster-connect-hub' ) ); }
        $key = 'gkps_job_' . $this->id . '_' . $id; $job = get_option( $key ); $settings = $this->settings();
        $destination = 'telegram' === $this->id && 'request' === ( $job['kind'] ?? '' ) ? ( $job['telegram_target'] ?? $settings['target'] ) : $settings['target'];
        if ( ! is_array( $job ) || 'failed' !== $job['status'] || ! hash_equals( $job['target'], hash( 'sha256', $destination . '|' . $settings['recipient'] ) ) ) { wp_die( esc_html__( 'The job is unavailable or the recipient has changed. Create a new publication.', 'georg-kloster-connect-hub' ) ); }
        if ( ! empty( $job['container_terminal'] ) ) { unset( $job['container_id'], $job['container_terminal'] ); }
        $job['attempts'] = 0; $job['status'] = 'pending'; update_option( $key, $job, false );
        wp_clear_scheduled_hook( 'gkps_' . $this->id . '_worker', array( $id ) ); wp_schedule_single_event( time() + 1, 'gkps_' . $this->id . '_worker', array( $id ) );
    }
}
