<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

final class Requests {
    public static function hooks(): void {
        // Retention and previously queued delivery continue when the public module is disabled.
        add_action( 'init', array( self::class, 'register' ) );
        add_action( 'gkps_request_mail', array( self::class, 'deliver' ), 10, 2 );
        add_action( 'gkps_requests_retention', array( self::class, 'retention' ) );
        add_filter( 'wp_privacy_personal_data_exporters', array( Request_Privacy::class, 'exporters' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( Request_Privacy::class, 'erasers' ) );
        if ( ! wp_next_scheduled( 'gkps_requests_retention' ) ) { wp_schedule_event( time() + 3600, 'daily', 'gkps_requests_retention' ); }
        if ( ! Modules::enabled( 'requests' ) ) { return; }
        add_action( 'template_redirect', function () {
            $post = get_post();
            if ( $post && ( has_block( 'gkps/requests', $post ) || str_contains( $post->post_content, '[gkps_requests' ) ) ) {
                if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); } nocache_headers();
            }
        } );
        add_action( 'admin_menu', array( Request_Admin::class, 'menu' ) );
        add_action( 'admin_post_gkps_request_submit', array( Request_Form::class, 'submit' ) );
        add_action( 'admin_post_nopriv_gkps_request_submit', array( Request_Form::class, 'submit' ) );
        add_action( 'admin_post_gkps_reply_image', array( Request_Media::class, 'serve' ) );
        add_action( 'admin_post_gkps_request_update', array( Request_Admin::class, 'update' ) );
    }
    public static function register(): void {
        $capabilities = array_map( fn() => 'view_gkps_requests', Capabilities::directory() );
        $capabilities['edit_post'] = 'reply_gkps_requests';
        $capabilities['delete_post'] = 'delete_gkps_requests';
        $capabilities['create_posts'] = 'do_not_allow';
        foreach ( array( 'gkps_request', 'gkps_message' ) as $type ) { register_post_type( $type, array(
            'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'query_var' => false,
            'exclude_from_search' => true, 'supports' => array(), 'map_meta_cap' => false, 'delete_with_user' => false,
            'capabilities' => $capabilities,
        ) ); }
    }
    public static function statuses(): array {
        return array( 'new' => __( 'Новое', 'georg-kloster-prihod-social' ), 'in_progress' => __( 'В работе', 'georg-kloster-prihod-social' ), 'waiting' => __( 'Ожидает ответа', 'georg-kloster-prihod-social' ), 'answered' => __( 'Отвечено', 'georg-kloster-prihod-social' ), 'closed' => __( 'Закрыто', 'georg-kloster-prihod-social' ) );
    }
    public static function validate( array $input ): array {
        $result = array();
        foreach ( array( 'name' => 100, 'email' => 254, 'subject' => 200, 'body' => 10000 ) as $key => $length ) {
            if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) || strlen( $input[ $key ] ) > $length ) { throw new \InvalidArgumentException( __( 'Проверьте обязательные поля и длину текста.', 'georg-kloster-prihod-social' ) ); }
            $value = trim( $input[ $key ] );
            if ( '' === $value || ( 'email' === $key && ! is_email( $value ) ) ) { throw new \InvalidArgumentException( __( 'Заполните все поля и укажите корректный email.', 'georg-kloster-prihod-social' ) ); }
            $result[ $key ] = 'email' === $key ? sanitize_email( $value ) : ( 'body' === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
            if ( '' === $result[ $key ] ) { throw new \InvalidArgumentException( __( 'Поле не может состоять только из разметки.', 'georg-kloster-prihod-social' ) ); }
        }
        return $result;
    }
    public static function create( array $input ): int {
        if ( ! Modules::enabled( 'requests' ) ) { throw new \RuntimeException( __( 'Приём обращений отключён.', 'georg-kloster-prihod-social' ) ); }
        $data = self::validate( $input );
        $id = wp_insert_post( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'post_title' => $data['subject'], 'post_content' => $data['body'], 'post_author' => 0 ), true );
        if ( is_wp_error( $id ) ) { throw new \RuntimeException( __( 'Обращение не сохранено. Попробуйте позже.', 'georg-kloster-prihod-social' ) ); }
        update_post_meta( $id, '_gkps_sender', array( 'name' => $data['name'], 'email' => $data['email'] ) );
        update_post_meta( $id, '_gkps_email', $data['email'] );
        update_post_meta( $id, '_gkps_request_status', 'new' );
        $responsible = (int) get_option( 'gkps_default_responsible', 0 );
        if ( $responsible && user_can( $responsible, 'view_gkps_requests' ) && user_can( $responsible, 'reply_gkps_requests' ) ) { update_post_meta( $id, '_gkps_assigned', $responsible ); }
        update_post_meta( $id, '_gkps_request_locale', determine_locale() );
        update_post_meta( $id, '_gkps_consent_at', gmdate( DATE_ATOM ) );
        do_action( 'gkps_request_created', $id );
        return $id;
    }
    public static function assert_request( int $id ): void {
        if ( 'gkps_request' !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) { throw new \InvalidArgumentException( __( 'Обращение не найдено.', 'georg-kloster-prihod-social' ) ); }
    }
    public static function message( int $id, string $body, bool $internal, string $source_id = '', array $media = array() ): int {
        self::assert_request( $id );
        if ( ! current_user_can( 'view_gkps_requests' ) || ! current_user_can( 'reply_gkps_requests' ) ) { throw new \RuntimeException( __( 'Недостаточно прав для ответа.', 'georg-kloster-prihod-social' ) ); }
        if ( $source_id && ! preg_match( '/^[a-f0-9]{64}$/D', $source_id ) ) { throw new \InvalidArgumentException( __( 'Ответ не сохранён.', 'georg-kloster-prihod-social' ) ); }
        $media = Request_Media::validated( $media );
        if ( ( ! $media && '' === trim( sanitize_textarea_field( $body ) ) ) || strlen( $body ) > 20000 ) { throw new \InvalidArgumentException( __( 'Введите ответ допустимой длины.', 'georg-kloster-prihod-social' ) ); }
        $message = wp_insert_post( array( 'post_type' => 'gkps_message', 'post_parent' => $id, 'post_status' => 'private', 'post_content' => sanitize_textarea_field( $body ), 'post_author' => get_current_user_id(), 'meta_input' => $source_id ? array( '_gkps_reply_source' => $source_id ) : array() ), true );
        if ( is_wp_error( $message ) ) { throw new \RuntimeException( __( 'Ответ не сохранён.', 'georg-kloster-prihod-social' ) ); }
        if ( $media ) { update_post_meta( $message, '_gkps_reply_media', $media ); }
        update_post_meta( $message, '_gkps_internal', $internal );
        if ( ! $internal ) {
            update_post_meta( $message, '_gkps_delivery', 'pending' );
            self::deliver( $id, $message );
        }
        return $message;
    }
    public static function deliver( int $id, int $message_id ): void {
        if ( get_option( 'gkps_purge_job' ) ) { return; }
        $post = get_post( $id ); $message = get_post( $message_id );
        if ( ! $post || 'gkps_request' !== $post->post_type || ! $message || (int) $message->post_parent !== $id || 'gkps_message' !== $message->post_type || get_post_meta( $message_id, '_gkps_internal', true ) || 'sent' === get_post_meta( $message_id, '_gkps_delivery', true ) ) { return; }
        $lock = 'gkps_mail_lock_' . $message_id;
        $token = Lock::acquire( $lock, 300 );
        if ( ! $token ) { return; }
        try {
            $attempt = (int) get_post_meta( $message_id, '_gkps_attempts', true ) + 1;
            $journal = Request_Mail_Log::begin( $id, $message_id );
            $sender = get_post_meta( $id, '_gkps_sender', true ); $success = false; $transport = array();
            try {
                if ( is_array( $sender ) && is_email( $sender['email'] ?? '' ) ) {
                    $mail = Request_Mail::compose( $post, $message, $sender );
                    Request_Mail_Log::record( $message_id, $journal, 'composed' );
                    $success = Request_Mail::send( $sender['email'], $mail, $transport, array( 'reply' => $message_id, 'journal' => $journal ) );
                } else { $transport['error_code'] = 'invalid_recipient'; }
            } catch ( \Throwable $error ) { $transport['error_code'] = 'compose_exception'; }
            if ( $transport ) { update_post_meta( $message_id, '_gkps_mail_transport', $transport ); }
            Request_Mail_Log::record( $message_id, $journal, $success ? 'accepted' : 'failed', $transport );
            update_post_meta( $message_id, '_gkps_attempts', $attempt );
            update_post_meta( $message_id, '_gkps_delivery', $success ? 'sent' : 'failed' );
            if ( $success ) {
                if ( 'closed' !== get_post_meta( $id, '_gkps_request_status', true ) ) { update_post_meta( $id, '_gkps_request_status', 'answered' ); }
            } elseif ( $attempt < 3 ) { wp_schedule_single_event( time() + 300 * $attempt, 'gkps_request_mail', array( $id, $message_id ) ); }
        } finally { Lock::release( $lock, $token ); }
    }
    public static function retry( int $id, int $message_id ): void {
        self::assert_request( $id );
        if ( ! current_user_can( 'view_gkps_requests' ) || ! current_user_can( 'reply_gkps_requests' ) ) { throw new \RuntimeException( __( 'Недостаточно прав.', 'georg-kloster-prihod-social' ) ); }
        $message = get_post( $message_id );
        if ( ! $message || 'gkps_message' !== $message->post_type || (int) $message->post_parent !== $id || get_post_meta( $message_id, '_gkps_internal', true ) || 'failed' !== get_post_meta( $message_id, '_gkps_delivery', true ) ) { throw new \InvalidArgumentException( __( 'Ответ недоступен для повторной отправки.', 'georg-kloster-prihod-social' ) ); }
        wp_clear_scheduled_hook( 'gkps_request_mail', array( $id, $message_id ) ); update_post_meta( $message_id, '_gkps_attempts', 0 );
        update_post_meta( $message_id, '_gkps_delivery', 'pending' ); self::deliver( $id, $message_id );
    }
    public static function delete( int $id ): void {
        self::assert_request( $id );
        do_action( 'gkps_request_deleted', $id );
        foreach ( self::messages( $id ) as $message ) { wp_clear_scheduled_hook( 'gkps_request_mail', array( $id, $message->ID ) ); wp_delete_post( $message->ID, true ); }
        wp_delete_post( $id, true );
    }
    public static function messages( int $id ): array {
        return get_posts( array( 'post_type' => 'gkps_message', 'post_status' => 'private', 'post_parent' => $id, 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
    }
    public static function retention(): void {
        Request_Mail_Log::retention();
        $days = max( 30, min( 3650, (int) get_option( 'gkps_retention_days', 365 ) ) );
        $posts = get_posts( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'numberposts' => 100,
            'meta_key' => '_gkps_request_status', 'meta_value' => 'closed', 'date_query' => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ), 'column' => 'post_modified_gmt' ) ) ) );
        foreach ( $posts as $post ) { self::delete( $post->ID ); }
    }
}
