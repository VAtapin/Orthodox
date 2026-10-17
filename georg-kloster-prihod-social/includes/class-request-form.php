<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

final class Request_Form {
    public static function topics(): array {
        $lines = preg_split( '/\r\n|\r|\n/', Admin::get()['topics'] );
        return array_slice( array_values( array_filter( array_map( 'trim', $lines ?: array() ), static fn( $topic ) => '' !== $topic ) ), 0, 20 );
    }
    /** Keep legacy free-text subjects; only the new explicit custom choice is normalized. */
    public static function submission_data( array $input ): array {
        if ( '__other' === ( $input['subject'] ?? null ) ) {
            if ( ! is_string( $input['subject_other'] ?? null ) || '' === trim( $input['subject_other'] ) ) { throw new \InvalidArgumentException( __( 'Введите свою тему вопроса.', 'georg-kloster-prihod-social' ) ); }
            $input['subject'] = $input['subject_other'];
        }
        return $input;
    }
    public static function render( $attributes = array() ): string {
        if ( ! Modules::enabled( 'requests' ) ) { return ''; }
        $settings = Admin::get();
        $attributes = shortcode_atts( array( 'title' => $settings['title'], 'intro' => $settings['intro'], 'submit' => $settings['submit'] ), is_array( $attributes ) ? $attributes : array(), 'gkps_contact_form' );
        foreach ( array( 'title', 'intro', 'submit' ) as $key ) {
            if ( is_string( $attributes[ $key ] ) ) { $settings[ $key ] = sanitize_text_field( $attributes[ $key ] ); }
        }
        $token = wp_generate_password( 32, false, false );
        $id = 'gkps-request-' . wp_unique_id();
        wp_enqueue_script( 'gkps-request-form', plugins_url( 'assets/request-form.js', GKPS_FILE ), array(), GKPS_VERSION, true );
        set_transient( 'gkps_form_' . hash( 'sha256', $token ), time(), HOUR_IN_SECONDS );
        $html = '<section class="gkps-block gkps-requests">' . ( $settings['title'] ? '<h2>' . esc_html( $settings['title'] ) . '</h2>' : '' ) . ( $settings['intro'] ? wpautop( esc_html( $settings['intro'] ) ) : '' );
        $notice = isset( $_GET['gkps_request'] ) && is_string( $_GET['gkps_request'] ) ? sanitize_key( $_GET['gkps_request'] ) : '';
        if ( 'sent' === $notice ) { $html .= '<p role="status">' . esc_html__( 'Обращение принято. Ответ придёт по email.', 'georg-kloster-prihod-social' ) . '</p>'; }
        if ( 'error' === $notice ) { $html .= '<p role="alert">' . esc_html__( 'Обращение не отправлено. Проверьте поля или повторите позже.', 'georg-kloster-prihod-social' ) . '</p>'; }
        $html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkps_request_submit"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">' . wp_nonce_field( 'gkps_request_submit', '_wpnonce', true, false );
        $html .= '<p id="' . esc_attr( $id . '-required' ) . '" class="gkps-required-help">' . esc_html__( 'Поля со звёздочкой обязательны для заполнения.', 'georg-kloster-prihod-social' ) . '</p>';
        foreach ( array( 'name' => array( __( 'Ваше имя', 'georg-kloster-prihod-social' ), __( 'Введите ваше имя', 'georg-kloster-prihod-social' ) ), 'email' => array( __( 'Ваш email', 'georg-kloster-prihod-social' ), 'name@example.org' ) ) as $key => $field ) { $html .= '<p><label>' . esc_html( $field[0] ) . ' <span class="gkps-required" aria-hidden="true">*</span><br><input required aria-describedby="' . esc_attr( $id . '-required' ) . '" placeholder="' . esc_attr( $field[1] ) . '" autocomplete="' . ( 'email' === $key ? 'email' : 'name' ) . '" maxlength="' . ( 'name' === $key ? '100' : '254' ) . '" type="' . ( 'email' === $key ? 'email' : 'text' ) . '" name="' . esc_attr( $key ) . '"></label></p>'; }
        $html .= '<p><label>' . esc_html__( 'Тема вопроса', 'georg-kloster-prihod-social' ) . ' <span class="gkps-required" aria-hidden="true">*</span><br><select name="subject" required data-request-topic aria-describedby="' . esc_attr( $id . '-required ' . $id . '-topic-help' ) . '" aria-controls="' . esc_attr( $id . '-custom' ) . '"><option value="">' . esc_html__( 'Выберите тему', 'georg-kloster-prihod-social' ) . '</option>';
        foreach ( self::topics() as $topic ) { $html .= '<option value="' . esc_attr( $topic ) . '">' . esc_html( $topic ) . '</option>'; }
        $html .= '<option value="__other">' . esc_html__( 'Другая тема', 'georg-kloster-prihod-social' ) . '</option></select></label></p><p id="' . esc_attr( $id . '-topic-help' ) . '" class="gkps-topic-help">' . esc_html__( 'Для другой темы укажите своё название в поле ниже.', 'georg-kloster-prihod-social' ) . '</p><p id="' . esc_attr( $id . '-custom' ) . '" data-request-custom><label>' . esc_html__( 'Своя тема вопроса', 'georg-kloster-prihod-social' ) . ' <span class="gkps-required" aria-hidden="true">*</span><br><input type="text" name="subject_other" maxlength="200" placeholder="' . esc_attr__( 'Введите свою тему вопроса', 'georg-kloster-prihod-social' ) . '"></label></p>';
        $html .= '<p><label>' . esc_html__( 'Текст обращения', 'georg-kloster-prihod-social' ) . ' <span class="gkps-required" aria-hidden="true">*</span><br><textarea name="body" required aria-describedby="' . esc_attr( $id . '-required' ) . '" placeholder="' . esc_attr__( 'Опишите ваш вопрос…', 'georg-kloster-prihod-social' ) . '" maxlength="10000" rows="6"></textarea></label></p><div aria-hidden="true" style="display:none"><label>' . esc_html__( 'Оставьте поле пустым', 'georg-kloster-prihod-social' ) . '<input name="website_check" tabindex="-1" autocomplete="off"></label></div>';
        $html .= '<p><label><input required type="checkbox" name="consent" value="1"> ' . esc_html__( 'Согласен на обработку данных для ответа на обращение.', 'georg-kloster-prihod-social' ) . '</label></p>';
        if ( get_privacy_policy_url() ) { $html .= '<p><a href="' . esc_url( get_privacy_policy_url() ) . '">' . esc_html__( 'Политика конфиденциальности', 'georg-kloster-prihod-social' ) . '</a></p>'; }
        return $html . '<p><button type="submit">' . esc_html( $settings['submit'] ) . '</button></p></form></section>';
    }
    public static function submit(): void {
        $redirect = wp_get_referer() ?: home_url( '/' );
        $redirect = wp_validate_redirect( $redirect, home_url( '/' ) );
        $status = 'error'; $lock = ''; $lock_token = '';
        try {
            $input = wp_unslash( $_POST );
            if ( ! is_string( $input['_wpnonce'] ?? null ) || ! wp_verify_nonce( $input['_wpnonce'], 'gkps_request_submit' ) || '1' !== ( $input['consent'] ?? '' ) || ! empty( $input['website_check'] ) || ! is_string( $input['token'] ?? null ) ) { throw new \RuntimeException( 'Invalid form.' ); }
            $hash = hash( 'sha256', $input['token'] );
            if ( ! get_transient( 'gkps_form_' . $hash ) ) { throw new \RuntimeException( 'Expired form.' ); }
            $lock = 'gkps_form_lock_' . $hash;
            $lock_token = Lock::acquire( $lock );
            if ( ! $lock_token ) { $lock = ''; throw new \RuntimeException( 'Duplicate submission.' ); }
            if ( ! get_transient( 'gkps_form_' . $hash ) ) { throw new \RuntimeException( 'Consumed form.' ); }
            $ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
            $key = 'gkps_rate_' . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
            self::rate_limit( $key );
            Requests::create( self::submission_data( $input ) );
            delete_transient( 'gkps_form_' . $hash );
            $status = 'sent';
        } catch ( \Throwable $error ) { $status = 'error'; }
        finally { if ( $lock ) { Lock::release( $lock, $lock_token ); } }
        wp_safe_redirect( add_query_arg( 'gkps_request', $status, $redirect ) );
        exit;
    }
    public static function rate_limit( string $key ): void {
        $lock = $key . '_lock';
        $token = Lock::acquire( $lock );
        if ( ! $token ) { throw new \RuntimeException( 'Rate limit busy.' ); }
        try {
            $bucket = get_transient( $key );
            if ( ! is_array( $bucket ) || $bucket['expires'] < time() ) { $bucket = array( 'count' => 0, 'expires' => time() + 15 * MINUTE_IN_SECONDS ); }
            if ( $bucket['count'] >= 5 ) { throw new \RuntimeException( 'Rate limit.' ); }
            ++$bucket['count'];
            set_transient( $key, $bucket, max( 1, $bucket['expires'] - time() ) );
        } finally { Lock::release( $lock, $token ); }
    }
}
