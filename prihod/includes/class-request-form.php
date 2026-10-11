<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Request_Form {
    public static function render(): string {
        if ( ! Modules::enabled( 'requests' ) ) { return ''; }
        $token = wp_generate_password( 32, false, false );
        set_transient( 'prihod_form_' . hash( 'sha256', $token ), time(), HOUR_IN_SECONDS );
        $html = '<section class="prihod-block prihod-requests">';
        $notice = isset( $_GET['prihod_request'] ) && is_string( $_GET['prihod_request'] ) ? sanitize_key( $_GET['prihod_request'] ) : '';
        if ( 'sent' === $notice ) { $html .= '<p role="status">' . esc_html__( 'Обращение принято. Ответ придёт по email.', 'prihod' ) . '</p>'; }
        if ( 'error' === $notice ) { $html .= '<p role="alert">' . esc_html__( 'Обращение не отправлено. Проверьте поля или повторите позже.', 'prihod' ) . '</p>'; }
        $html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_request_submit"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">' . wp_nonce_field( 'prihod_request_submit', '_wpnonce', true, false );
        foreach ( array( 'name' => __( 'Имя', 'prihod' ), 'email' => __( 'Email', 'prihod' ), 'subject' => __( 'Тема', 'prihod' ) ) as $key => $label ) { $html .= '<p><label>' . esc_html( $label ) . '<br><input required maxlength="' . ( 'name' === $key ? '100' : ( 'email' === $key ? '254' : '200' ) ) . '" type="' . ( 'email' === $key ? 'email' : 'text' ) . '" name="' . esc_attr( $key ) . '"></label></p>'; }
        $html .= '<p><label>' . esc_html__( 'Текст обращения', 'prihod' ) . '<br><textarea name="body" required maxlength="10000" rows="8"></textarea></label></p><div aria-hidden="true" style="display:none"><label>' . esc_html__( 'Оставьте поле пустым', 'prihod' ) . '<input name="website_check" tabindex="-1" autocomplete="off"></label></div>';
        $html .= '<p><label><input required type="checkbox" name="consent" value="1"> ' . esc_html__( 'Согласен на обработку данных для ответа на обращение.', 'prihod' ) . '</label></p>';
        if ( get_privacy_policy_url() ) { $html .= '<p><a href="' . esc_url( get_privacy_policy_url() ) . '">' . esc_html__( 'Политика конфиденциальности', 'prihod' ) . '</a></p>'; }
        return $html . '<p><button type="submit">' . esc_html__( 'Отправить обращение', 'prihod' ) . '</button></p></form></section>';
    }
    public static function submit(): void {
        $redirect = wp_get_referer() ?: home_url( '/' );
        $redirect = wp_validate_redirect( $redirect, home_url( '/' ) );
        $status = 'error'; $lock = ''; $lock_token = '';
        try {
            $input = wp_unslash( $_POST );
            if ( ! is_string( $input['_wpnonce'] ?? null ) || ! wp_verify_nonce( $input['_wpnonce'], 'prihod_request_submit' ) || '1' !== ( $input['consent'] ?? '' ) || ! empty( $input['website_check'] ) || ! is_string( $input['token'] ?? null ) ) { throw new \RuntimeException( 'Invalid form.' ); }
            $hash = hash( 'sha256', $input['token'] );
            if ( ! get_transient( 'prihod_form_' . $hash ) ) { throw new \RuntimeException( 'Expired form.' ); }
            $lock = 'prihod_form_lock_' . $hash;
            $lock_token = Lock::acquire( $lock );
            if ( ! $lock_token ) { $lock = ''; throw new \RuntimeException( 'Duplicate submission.' ); }
            if ( ! get_transient( 'prihod_form_' . $hash ) ) { throw new \RuntimeException( 'Consumed form.' ); }
            $ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
            $key = 'prihod_rate_' . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
            self::rate_limit( $key );
            Requests::create( $input );
            delete_transient( 'prihod_form_' . $hash );
            $status = 'sent';
        } catch ( \Throwable $error ) { $status = 'error'; }
        finally { if ( $lock ) { Lock::release( $lock, $lock_token ); } }
        wp_safe_redirect( add_query_arg( 'prihod_request', $status, $redirect ) );
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
