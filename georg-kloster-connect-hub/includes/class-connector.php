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
        if ( 'telegram' !== $id ) { add_action( 'gkps_' . $id . '_worker', array( $this, 'worker' ) ); }
        add_action( 'gkps_request_created', function ( $request ) {
            $settings = $this->settings();
            if ( empty( $settings['notify_requests'] ) || ! apply_filters( 'gkps_connector_request_notification', true, $this->id ) ) { return; }
            $telegram_target = '';
            if ( 'telegram' === $this->id ) {
                $form_id = absint( get_post_meta( (int) $request, '_gkch_form_id', true ) );
                $telegram_target = Forms::telegram_target( $form_id, '' );
            }
            $this->queue( __( 'New message. Open the private parish dashboard.', 'georg-kloster-connect-hub' ), admin_url( 'admin.php?page=gkps-requests&request_id=' . absint( $request ) ), 'request', (int) $request, array(), $telegram_target );
        } );
        add_action( 'gkps_request_deleted', function ( $request ) {
            foreach ( get_post_meta( $request, '_gkps_jobs_' . $this->id, true ) ?: array() as $id ) { delete_option( 'gkps_job_' . $this->id . '_' . $id ); wp_clear_scheduled_hook( 'gkps_' . $this->id . '_worker', array( $id ) ); }
        } );
        add_action( 'gkpsch_schedule_changed', function ( $service ) {
            if ( 'telegram' === $this->id && ! empty( $this->settings()['notify_schedule'] ) && 'publish' === get_post_status( $service ) ) {
                $url = home_url( '/' );
                $this->queue( __( 'Schedule updated:', 'georg-kloster-connect-hub' ) . ' ' . get_the_title( $service ), $url, 'schedule', (int) $service );
            }
        } );
    }
    public function __get( string $key ) { return in_array( $key, array( 'id', 'title' ), true ) ? $this->$key : null; }
    public function settings(): array { $saved = get_option( 'gkps_connect_' . $this->id, array() ); return array_merge( array( 'enabled' => false, 'token' => '', 'target' => '', 'api_version' => '', 'recipient' => '', 'application_key' => '', 'application_secret' => '', 'notify_requests' => false, 'notify_schedule' => false, 'telegram_broadcasts' => false, 'daily_digest_enabled' => false, 'daily_digest_time' => '08:00' ), is_array( $saved ) ? $saved : array() ); }
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
        if ( 'telegram' === $this->id ) { return (bool) preg_match( '/^[0-9]+:[A-Za-z0-9_-]{10,}$/D', $settings['token'] ); }
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
        echo '<p>' . esc_html__( 'A bot token is required. Each form uses only its own private Chat ID for contact messages; the old global Chat ID is not used. Public posts and schedule messages go to people who pressed Start in the bot.', 'georg-kloster-connect-hub' ) . '</p>';
        }
        echo '<div class="wrap"><h1>' . esc_html( $this->title ) . '</h1><p>' . esc_html( $this->health()['message'] ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkps_connect_' . esc_attr( $this->id ) . '">'; wp_nonce_field( 'gkps_connect_' . $this->id );
        echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked( $settings['enabled'], true, false ) . '> ' . esc_html__( 'Allow sending to the external service', 'georg-kloster-connect-hub' ) . '</label></p><p><label>' . esc_html__( 'Token (leave empty to keep the existing token)', 'georg-kloster-connect-hub' ) . ' <input type="password" name="token" autocomplete="new-password" value=""></label> <label><input type="checkbox" name="clear_token" value="1"> ' . esc_html__( 'Delete token', 'georg-kloster-connect-hub' ) . '</label></p>';
        $fields = 'telegram' === $this->id ? array() : array( 'target' => __( 'Facebook Page ID', 'georg-kloster-connect-hub' ) );
        if ( in_array( $this->id, array( 'instagram', 'vk', 'ok' ), true ) ) { $fields['target'] = 'vk' === $this->id ? __( 'VK owner ID', 'georg-kloster-connect-hub' ) : ( 'ok' === $this->id ? __( 'OK group ID', 'georg-kloster-connect-hub' ) : __( 'Instagram account ID', 'georg-kloster-connect-hub' ) ); }
        if ( 'telegram' !== $this->id && 'ok' !== $this->id ) { $fields['api_version'] = __( 'API version from your application', 'georg-kloster-connect-hub' ); }
        foreach ( $fields as $key => $label ) {
            echo '<p><label>' . esc_html( $label ) . '<br><input class="regular-text" name="' . esc_attr( $key ) . '" value="' . esc_attr( $settings[ $key ] ) . '"></label>';
            if ( 'api_version' === $key ) {
                echo '<br><span class="description">' . ( 'vk' === $this->id ? esc_html__( 'This is the VK API method version, not a token. Copy it from the method documentation, for example 5.199, without a v prefix.', 'georg-kloster-connect-hub' ) : esc_html__( 'This is the Meta Graph API version used in request URLs, not a key or plugin version. Copy a supported version from Graph API Explorer, for example vNN.N.', 'georg-kloster-connect-hub' ) ) . '</span>';
            }
            echo '</p>';
        }
        if ( 'telegram' === $this->id ) {
            foreach ( array( 'notify_requests' => __( 'Notify about new messages', 'georg-kloster-connect-hub' ), 'notify_schedule' => __( 'Notify subscribers about changes to the published schedule', 'georg-kloster-connect-hub' ) ) as $key => $label ) { echo '<p><label><input type="checkbox" name="' . esc_attr( $key ) . '" value="1"' . checked( $settings[ $key ], true, false ) . '> ' . esc_html( $label ) . '</label></p>'; }
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
        $guide_file = dirname( GKPS_FILE ) . '/docs/channel-guides.json';
        $guide_json = is_readable( $guide_file ) ? file_get_contents( $guide_file ) : false;
        $guide_data = is_string( $guide_json ) ? json_decode( $guide_json, true ) : array();
        $locale = substr( determine_locale(), 0, 2 );
        $guides = is_array( $guide_data[$locale] ?? null ) ? $guide_data[$locale] : ( is_array( $guide_data['ru'] ?? null ) ? $guide_data['ru'] : array() );
        if ( isset( $guides[$this->id] ) ) { echo '<details class="gkch-channel-guide"><summary>' . esc_html__( 'Detailed setup: where to click and find the required details', 'georg-kloster-connect-hub' ) . '</summary><div class="gkch-channel-guide__content">' . wp_kses_post( $guides[$this->id] ) . '</div></details>'; }
    }
    public function save(): void {
        $this->authorize(); check_admin_referer( 'gkps_connect_' . $this->id ); $settings = $this->settings();
        foreach ( array( 'token', 'target', 'api_version', 'application_key', 'application_secret' ) as $key ) {
            if ( ! isset( $_POST[ $key ] ) ) { continue; } if ( ! is_string( $_POST[ $key ] ) ) { wp_die( esc_html__( 'Invalid field.', 'georg-kloster-connect-hub' ) ); }
            $value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); if ( ! in_array( $key, array( 'token', 'application_secret' ), true ) || '' !== $value ) { $settings[ $key ] = $value; }
        }
        if ( ! empty( $_POST['clear_application_secret'] ) ) { $settings['application_secret'] = ''; }
        if ( ! empty( $_POST['clear_token'] ) ) { $settings['token'] = ''; }
        foreach ( array( 'enabled', 'notify_requests', 'notify_schedule', 'telegram_broadcasts', 'daily_digest_enabled' ) as $key ) { $settings[ $key ] = ! empty( $_POST[ $key ] ) && ( 'telegram' === $this->id || 'enabled' === $key ); }
        if ( 'telegram' === $this->id && isset( $_POST['daily_digest_time'] ) && is_string( $_POST['daily_digest_time'] ) ) {
            $time = sanitize_text_field( wp_unslash( $_POST['daily_digest_time'] ) );
            if ( preg_match( '/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time ) ) { $settings['daily_digest_time'] = $time; }
        }
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
        return array( 'token_missing' => __( 'Enter the bot token. The connection was not saved.', 'georg-kloster-connect-hub' ), 'token_invalid' => __( 'The bot token format is invalid. Copy the token from BotFather. The connection was not saved.', 'georg-kloster-connect-hub' ) );
    }
    public function telegram_errors( array $settings ): array {
        $errors = array();
        if ( empty( $settings['token'] ) ) { $errors[] = 'token_missing'; }
        elseif ( ! is_string( $settings['token'] ) || ! preg_match( '/^[0-9]+:[A-Za-z0-9_-]{10,}$/D', $settings['token'] ) ) { $errors[] = 'token_invalid'; }
        return $errors;
    }
    public function meta_box(): void {
        if ( ! $this->valid( $this->settings() ) ) { return; }
        if ( 'telegram' === $this->id && ! Telegram_Bot::broadcasting_enabled() ) { return; }
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
        if ( 'telegram' === $this->id && in_array( $kind, array( 'post', 'schedule', 'automation' ), true ) ) { return Telegram_Bot::queue( $kind, $source, $reply_context, $text, $url ); }
        if ( 'telegram' === $this->id && 'request' !== $kind && ! Telegram_Bot::broadcasting_enabled() ) { return ''; }
        if ( 'automation' === $kind && ! Publication_Rules::allows( array( 'kind' => $kind, 'source' => $source, 'reply_context' => $reply_context ) ) ) { return ''; }
        $image = 'automation' === $kind ? Publication_Rules::image( $source ) : Social_Providers::image( $source );
        if ( 'instagram' === $this->id && ! $image ) { return ''; }
        $settings = $this->settings(); $limit = max( 1, min( 3000, (int) apply_filters( 'gkps_connector_text_limit', 900, $this->id, $kind ) ) );
        $html = 'telegram' === $this->id && 'request' === $kind && ! empty( $reply_context['telegram_html'] ) && ! empty( $reply_context['request_id'] );
        $text = $html ? wp_kses( $text, array( 'b' => array() ) ) : wp_html_excerpt( wp_strip_all_tags( $text ), $limit, '…' ); $url = esc_url_raw( $url );
        $destination = 'telegram' === $this->id && 'request' === $kind ? $telegram_target : $settings['target'];
        if ( 'telegram' === $this->id && 'request' === $kind && ! preg_match( '/^[1-9][0-9]*$/D', $destination ) ) { return ''; }
        $resend_key = 'telegram' === $this->id && 'request' === $kind ? wp_generate_uuid4() : '';
        $id = hash( 'sha256', $this->id . $kind . $source . $text . $url . $destination . $settings['recipient'] . ( $reply_context['configuration'] ?? '' ) . $resend_key ); $key = 'gkps_job_' . $this->id . '_' . $id;
        $target = hash( 'sha256', $destination . '|' . $settings['recipient'] );
        if ( add_option( $key, array( 'text' => $text, 'url' => $url, 'kind' => $kind, 'source' => $source, 'target' => $target, 'telegram_target' => 'telegram' === $this->id && 'request' === $kind ? $destination : '', 'status' => 'pending', 'attempts' => 0, 'reply_context' => $reply_context, 'image_url' => 'instagram' === $this->id ? $image : '' ), '', false ) ) {
            if ( 'request' === $kind ) { $jobs = get_post_meta( $source, '_gkps_jobs_' . $this->id, true ) ?: array(); $jobs[] = $id; update_post_meta( $source, '_gkps_jobs_' . $this->id, $jobs ); }
            if ( 'telegram' === $this->id ) { $this->worker( $id ); }
            else {
                $scheduled = wp_schedule_single_event( time() + 1, 'gkps_' . $this->id . '_worker', array( $id ), true );
                if ( is_wp_error( $scheduled ) || ! $scheduled ) { delete_option( $key ); return ''; }
            }
        }
        return $id;
    }
    public function request( array $job ): array {
        $settings = $this->settings(); $html = 'telegram' === $this->id && ! empty( $job['reply_context']['telegram_html'] );
        $text = $job['text'];
        if ( ! ( 'telegram' === $this->id && 'request' === ( $job['kind'] ?? '' ) ) ) { $text .= ( $html ? "\n\n" : "\n" ) . ( $html ? esc_html( $job['url'] ) : $job['url'] ); }
        $headers = array( 'Content-Type' => 'application/json' );
        if ( 'telegram' === $this->id ) { $url = 'https://api.telegram.org/bot' . $settings['token'] . '/sendMessage'; $body = array( 'chat_id' => (string) ( $job['telegram_target'] ?? '' ), 'text' => $text, 'link_preview_options' => array( 'is_disabled' => true ) ); }
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
        // Older queued Telegram post jobs targeted one private Chat ID. Never replay
        // them after publications have moved to the subscriber broadcast queue.
        if ( 'telegram' === $this->id && is_array( $job ) && in_array( $job['kind'] ?? '', array( 'post', 'schedule', 'automation' ), true ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
        if ( ! is_array( $job ) || in_array( $job['status'], array( 'sent', 'cancelled', 'uncertain' ), true ) || $job['attempts'] >= ( 'instagram' === $this->id ? 16 : 3 ) || ! $this->valid( $this->settings() ) ) { return; }
        if ( ! apply_filters( 'gkps_connector_job_allowed', true, $this->id, $job ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
        $settings = $this->settings(); $destination = 'telegram' === $this->id && 'request' === ( $job['kind'] ?? '' ) ? ( $job['telegram_target'] ?? '' ) : $settings['target']; if ( ( 'telegram' === $this->id && 'request' === ( $job['kind'] ?? '' ) && ! preg_match( '/^[1-9][0-9]*$/D', $destination ) ) || ! hash_equals( $job['target'], hash( 'sha256', $destination . '|' . $settings['recipient'] ) ) ) { $job['status'] = 'cancelled'; update_option( $key, $job, false ); return; }
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
            if ( 'telegram' !== $this->id && ( $pending || ( ! $accepted && ! empty( $result['retry'] ) && $job['attempts'] < 3 ) ) ) { wp_schedule_single_event( time() + ( $pending ? 60 : 300 * $job['attempts'] ), 'gkps_' . $this->id . '_worker', array( $id ) ); }
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
        $destination = 'telegram' === $this->id && 'request' === ( $job['kind'] ?? '' ) ? ( $job['telegram_target'] ?? '' ) : $settings['target'];
        if ( ! is_array( $job ) || 'failed' !== $job['status'] || ! hash_equals( $job['target'], hash( 'sha256', $destination . '|' . $settings['recipient'] ) ) ) { wp_die( esc_html__( 'The job is unavailable or the recipient has changed. Create a new publication.', 'georg-kloster-connect-hub' ) ); }
        if ( ! empty( $job['container_terminal'] ) ) { unset( $job['container_id'], $job['container_terminal'] ); }
        $job['attempts'] = 0; $job['status'] = 'pending'; update_option( $key, $job, false );
        wp_clear_scheduled_hook( 'gkps_' . $this->id . '_worker', array( $id ) );
        if ( 'telegram' === $this->id ) { $this->worker( $id ); }
        else { wp_schedule_single_event( time() + 1, 'gkps_' . $this->id . '_worker', array( $id ) ); }
    }
}
