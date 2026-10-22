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
        add_action( 'template_redirect', function () {
            $post = get_post();
            if ( $post && ( has_block( 'gkps/contact-form', $post ) || has_block( 'gkch/form', $post ) || str_contains( $post->post_content, '[gkch_form' ) || str_contains( $post->post_content, '[gkps_contact_form' ) || has_block( 'gkps/requests', $post ) || str_contains( $post->post_content, '[gkps_requests' ) ) ) {
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
        return array( 'new' => __( 'New', 'georg-kloster-connect-hub' ), 'in_progress' => __( 'In progress', 'georg-kloster-connect-hub' ), 'waiting' => __( 'Awaiting reply', 'georg-kloster-connect-hub' ), 'answered' => __( 'Answered', 'georg-kloster-connect-hub' ), 'closed' => __( 'Closed', 'georg-kloster-connect-hub' ) );
    }
    public static function validate( array $input ): array {
        $result = array();
        foreach ( array( 'name' => 100, 'email' => 254, 'subject' => 200, 'body' => 10000 ) as $key => $length ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) || strlen( $input[ $key ] ) > $length ) { throw new \InvalidArgumentException( __( 'Check the required fields and text length.', 'georg-kloster-connect-hub' ) ); }
            $value = trim( $input[ $key ] );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( '' === $value || ( 'email' === $key && ! is_email( $value ) ) ) { throw new \InvalidArgumentException( __( 'Fill in all fields and enter a valid email address.', 'georg-kloster-connect-hub' ) ); }
            $result[ $key ] = 'email' === $key ? sanitize_email( $value ) : ( 'body' === $key ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( '' === $result[ $key ] ) { throw new \InvalidArgumentException( __( 'A field cannot contain markup only.', 'georg-kloster-connect-hub' ) ); }
        }
        return $result;
    }
    public static function create( array $input ): int {
        $form_id = isset( $input['form_id'] ) && is_scalar( $input['form_id'] ) ? absint( $input['form_id'] ) : 0;
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! Forms::active( $form_id ) || get_option( 'gkps_purge_job' ) ) { throw new \RuntimeException( __( 'Incoming messages are disabled.', 'georg-kloster-connect-hub' ) ); }
        $data = self::validate( $input );
        $id = wp_insert_post( array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'post_title' => $data['subject'], 'post_content' => $data['body'], 'post_author' => 0 ), true );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( is_wp_error( $id ) ) { throw new \RuntimeException( __( 'Your message could not be saved. Try again later.', 'georg-kloster-connect-hub' ) ); }
        update_post_meta( $id, '_gkps_sender', array( 'name' => $data['name'], 'email' => $data['email'] ) );
        update_post_meta( $id, '_gkch_form_id', $form_id );
        update_post_meta( $id, '_gkch_form_name', Forms::get( $form_id )['name'] );
        update_post_meta( $id, '_gkps_email', $data['email'] );
        update_post_meta( $id, '_gkps_request_status', 'new' );
        $responsible = (int) get_option( 'gkps_default_responsible', 0 );
        if ( $responsible && user_can( $responsible, 'view_gkps_requests' ) && user_can( $responsible, 'reply_gkps_requests' ) ) { update_post_meta( $id, '_gkps_assigned', $responsible ); }
        update_post_meta( $id, '_gkps_request_locale', determine_locale() );
        update_post_meta( $id, '_gkps_consent_at', gmdate( DATE_ATOM ) );
        try {
            do_action( 'gkps_request_created', $id );
        } catch ( \Throwable $error ) {
            // The request is already persisted; a failing notification must not tell the sender to submit it again.
            error_log( sprintf( 'Georg-Kloster Connect Hub: a post-create notification failed for request #%d (%s).', $id, get_class( $error ) ) );
        }
        return $id;
    }
    public static function assert_request( int $id ): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( 'gkps_request' !== get_post_type( $id ) || 'trash' === get_post_status( $id ) ) { throw new \InvalidArgumentException( __( 'Message not found.', 'georg-kloster-connect-hub' ) ); }
    }
    public static function message( int $id, string $body, bool $internal, string $source_id = '', array $media = array() ): int {
        self::assert_request( $id );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! current_user_can( 'view_gkps_requests' ) || ! current_user_can( 'reply_gkps_requests' ) ) { throw new \RuntimeException( __( 'Insufficient permissions to reply.', 'georg-kloster-connect-hub' ) ); }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( $source_id && ! preg_match( '/^[a-f0-9]{64}$/D', $source_id ) ) { throw new \InvalidArgumentException( __( 'The reply was not saved.', 'georg-kloster-connect-hub' ) ); }
        $media = Request_Media::validated( $media );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ( ! $media && '' === trim( sanitize_textarea_field( $body ) ) ) || strlen( $body ) > 20000 ) { throw new \InvalidArgumentException( __( 'Enter a reply of an allowed length.', 'georg-kloster-connect-hub' ) ); }
        $message = wp_insert_post( array( 'post_type' => 'gkps_message', 'post_parent' => $id, 'post_status' => 'private', 'post_content' => sanitize_textarea_field( $body ), 'post_author' => get_current_user_id(), 'meta_input' => $source_id ? array( '_gkps_reply_source' => $source_id ) : array() ), true );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( is_wp_error( $message ) ) { throw new \RuntimeException( __( 'The reply was not saved.', 'georg-kloster-connect-hub' ) ); }
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
            Outgoing_Log::record( array( 'channel' => 'email', 'recipient' => $sender['email'] ?? '', 'subject' => $mail['subject'] ?? ( __( 'Reply to message:', 'georg-kloster-connect-hub' ) . ' ' . $post->post_title ), 'content' => $mail['text'] ?? $message->post_content, 'kind' => 'request_reply', 'source_type' => 'request', 'source_id' => $id, 'status' => $success ? 'accepted' : 'failed', 'attempt' => $attempt, 'http_code' => (int) ( $transport['smtp_code'] ?? 0 ), 'remote_id' => (string) ( $transport['message_id'] ?? '' ), 'transport' => (string) ( $transport['method'] ?? '' ), 'error_code' => (string) ( $transport['error_code'] ?? '' ) ) );
            update_post_meta( $message_id, '_gkps_attempts', $attempt );
            update_post_meta( $message_id, '_gkps_delivery', $success ? 'sent' : 'failed' );
            if ( $success ) {
                if ( 'closed' !== get_post_meta( $id, '_gkps_request_status', true ) ) { update_post_meta( $id, '_gkps_request_status', 'answered' ); }
            } elseif ( $attempt < 3 ) { wp_schedule_single_event( time() + 300 * $attempt, 'gkps_request_mail', array( $id, $message_id ) ); }
        } finally { Lock::release( $lock, $token ); }
    }
    public static function retry( int $id, int $message_id ): void {
        self::assert_request( $id );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! current_user_can( 'view_gkps_requests' ) || ! current_user_can( 'reply_gkps_requests' ) ) { throw new \RuntimeException( __( 'Insufficient permissions.', 'georg-kloster-connect-hub' ) ); }
        $message = get_post( $message_id );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! $message || 'gkps_message' !== $message->post_type || (int) $message->post_parent !== $id || get_post_meta( $message_id, '_gkps_internal', true ) || 'failed' !== get_post_meta( $message_id, '_gkps_delivery', true ) ) { throw new \InvalidArgumentException( __( 'This reply cannot be resent.', 'georg-kloster-connect-hub' ) ); }
        wp_clear_scheduled_hook( 'gkps_request_mail', array( $id, $message_id ) ); update_post_meta( $message_id, '_gkps_attempts', 0 );
        update_post_meta( $message_id, '_gkps_delivery', 'pending' ); self::deliver( $id, $message_id );
    }
    public static function delete( int $id ): void {
        self::assert_request( $id );
        Outgoing_Log::delete_source( $id );
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
