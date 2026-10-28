<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

/** Bounded private diagnostics, never message contents or addresses. */
final class Request_Mail_Log {
    const DAYS = 30;
    const LIMIT = 50;
    public static function begin( int $request, int $reply ): string {
        $post = get_post( $reply ); $parent = get_post( $request );
        if ( ! $post || ! $parent || 'gkps_message' !== $post->post_type || 'private' !== $post->post_status || $request !== (int) $post->post_parent || 'gkps_request' !== $parent->post_type || 'private' !== $parent->post_status ) { return ''; }
        $attempt = (int) get_post_meta( $reply, '_gkps_mail_attempt_sequence', true ) + 1;
        update_post_meta( $reply, '_gkps_mail_attempt_sequence', $attempt );
        $id = wp_generate_uuid4(); $rows = self::rows( $reply );
        $rows[] = array( 'id' => $id, 'request' => $request, 'reply' => $reply, 'attempt' => $attempt, 'started_gmt' => gmdate( DATE_ATOM ), 'status' => 'started', 'transport' => 'unknown', 'message_id' => '', 'stage_times' => array( 'started' => gmdate( DATE_ATOM ) ), 'stages' => array( 'started' ), 'error_code' => '' );
        self::store( $reply, $rows ); return $id;
    }
    public static function record( int $reply, string $id, string $stage, array $data = array() ): void {
        if ( ! $id ) { return; }
        if ( ! in_array( $stage, array( 'composed', 'mailer_ready', 'accepted', 'failed' ), true ) ) { return; }
        $rows = self::rows( $reply );
        foreach ( $rows as &$row ) {
            if ( $id !== $row['id'] ) { continue; }
            $row['stage_times'][$stage] = gmdate( DATE_ATOM ); $row['stages'][] = $stage; $row['stages'] = array_slice( array_unique( $row['stages'] ), 0, 6 );
            if ( isset( $data['method'] ) ) { $row['transport'] = in_array( $data['method'], array( 'mail', 'sendmail', 'qmail', 'smtp' ), true ) ? $data['method'] : 'unknown'; }
            if ( isset( $data['message_id'] ) && preg_match( '/^<gkps-[a-f0-9-]{36}@[a-z0-9.-]+>$/D', $data['message_id'] ) ) { $row['message_id'] = $data['message_id']; }
            foreach ( array( 'from_matches_profile', 'envelope_matches_profile', 'short_circuit', 'message_id_observed' ) as $key ) { if ( isset( $data[$key] ) ) { $row[$key] = (bool) $data[$key]; } }
            if ( isset( $data['exception_type'] ) && preg_match( '/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]{0,127}$/D', $data['exception_type'] ) ) { $row['exception_type'] = $data['exception_type']; }
            foreach ( array( 'exception_code', 'smtp_code' ) as $key ) { if ( isset( $data[$key] ) && is_int( $data[$key] ) ) { $row[$key] = $data[$key]; } }
            if ( isset( $data['error_code'] ) ) { $row['error_code'] = in_array( $data['error_code'], array( 'mail_not_accepted', 'mail_exception', 'wp_mail_failed', 'compose_exception', 'invalid_recipient' ), true ) ? $data['error_code'] : 'mail_error'; }
            if ( in_array( $stage, array( 'accepted', 'failed' ), true ) ) { $row['status'] = $stage; $row['finished_gmt'] = gmdate( DATE_ATOM ); }
        }
        unset( $row ); self::store( $reply, $rows );
    }
    public static function rows( int $reply ): array {
        $rows = get_post_meta( $reply, '_gkps_mail_log', true ); $cutoff = time() - self::DAYS * DAY_IN_SECONDS;
        return is_array( $rows ) ? array_values( array_filter( $rows, static fn( $row ) => is_array( $row ) && strtotime( $row['started_gmt'] ?? '' ) > $cutoff ) ) : array();
    }
    private static function store( int $reply, array $rows ): void {
        $rows = array_slice( $rows, -self::LIMIT );
        if ( ! $rows ) { delete_post_meta( $reply, '_gkps_mail_log' ); delete_post_meta( $reply, '_gkps_mail_log_expires' ); delete_post_meta( $reply, '_gkps_mail_transport' ); return; }
        update_post_meta( $reply, '_gkps_mail_log', $rows );
        update_post_meta( $reply, '_gkps_mail_log_expires', strtotime( $rows[0]['started_gmt'] ) + self::DAYS * DAY_IN_SECONDS );
    }
    public static function retention(): void {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Retention scans at most 100 private reply records per scheduled run.
        $posts = get_posts( array( 'post_type' => 'gkps_message', 'post_status' => 'private', 'numberposts' => 100, 'meta_key' => '_gkps_mail_log_expires', 'meta_value' => time(), 'meta_compare' => '<=', 'meta_type' => 'NUMERIC', 'orderby' => 'meta_value_num', 'order' => 'ASC' ) );
        foreach ( $posts as $post ) { self::store( $post->ID, self::rows( $post->ID ) ); }
    }
    public static function render( int $reply ): string {
        if ( ! current_user_can( 'view_gkps_requests' ) ) { return ''; }
        $post = get_post( $reply );
        if ( ! $post || 'gkps_message' !== $post->post_type || 'private' !== $post->post_status ) { return ''; }
        $rows = self::rows( $reply );
        $html = '<details class="gkps-mail-diagnostics"><summary>' . esc_html__( 'Email delivery attempts', 'georg-kloster-connect-hub' ) . '</summary><p>' . esc_html__( 'The technical log is kept for 30 days. Transport acceptance does not confirm inbox delivery.', 'georg-kloster-connect-hub' ) . '</p>';
        if ( ! $rows ) { $html .= '<p>' . esc_html__( 'There is no technical log for this reply. Past delivery attempts cannot be reconstructed retroactively.', 'georg-kloster-connect-hub' ) . '</p>'; }
        foreach ( $rows as $row ) {
            $label = array( 'started' => __( 'Attempt started, no result received', 'georg-kloster-connect-hub' ), 'accepted' => __( 'Passed to the mail system. Inbox delivery is not confirmed.', 'georg-kloster-connect-hub' ), 'failed' => __( 'Sending error', 'georg-kloster-connect-hub' ) )[$row['status']] ?? '';
            if ( ! empty( $row['short_circuit'] ) && 'accepted' === $row['status'] ) { $label = __( 'Accepted by WordPress; the transport result is unknown.', 'georg-kloster-connect-hub' ); }
            /* translators: 1: request ID, 2: reply ID, 3: delivery attempt number. */
            $html .= '<div style="overflow-wrap:anywhere;padding:12px 0;border-top:1px solid #dce3ec"><p><strong>' . esc_html( $label ) . '</strong></p><p>' . esc_html( sprintf( __( 'Message #%1$d · reply #%2$d · attempt %3$d', 'georg-kloster-connect-hub' ), $row['request'], $reply, $row['attempt'] ) ) . '</p><dl>';
            foreach ( array( 'started_gmt' => __( 'Started (UTC)', 'georg-kloster-connect-hub' ), 'finished_gmt' => __( 'Finished (UTC)', 'georg-kloster-connect-hub' ), 'transport' => __( 'Transport', 'georg-kloster-connect-hub' ), 'message_id' => ! empty( $row['message_id_observed'] ) ? 'Message-ID' : __( 'Assigned Message-ID', 'georg-kloster-connect-hub' ), 'id' => __( 'Attempt ID', 'georg-kloster-connect-hub' ), 'error_code' => __( 'Error code', 'georg-kloster-connect-hub' ), 'exception_type' => __( 'Exception type', 'georg-kloster-connect-hub' ), 'exception_code' => __( 'Exception code', 'georg-kloster-connect-hub' ), 'smtp_code' => __( 'SMTP code', 'georg-kloster-connect-hub' ) ) as $key => $title ) { if ( ! empty( $row[$key] ) ) { $html .= '<dt>' . esc_html( $title ) . '</dt><dd>' . esc_html( $row[$key] ) . '</dd>'; } }
            $html .= '</dl>' . ( ! empty( $row['short_circuit'] ) ? '<p>' . esc_html__( 'The email was handled by a WordPress interceptor; the actual transport was not recorded.', 'georg-kloster-connect-hub' ) . '</p>' : '' );
            $html .= '<p>' . esc_html__( 'Stages:', 'georg-kloster-connect-hub' ) . ' ' . esc_html( implode( ' → ', array_map( static fn( $stage ) => array( 'started' => __( 'Started', 'georg-kloster-connect-hub' ), 'composed' => __( 'Email prepared', 'georg-kloster-connect-hub' ), 'mailer_ready' => __( 'Transport prepared', 'georg-kloster-connect-hub' ), 'accepted' => __( 'Accepted by transport', 'georg-kloster-connect-hub' ), 'failed' => __( 'Sending error', 'georg-kloster-connect-hub' ) )[$stage] ?? $stage, $row['stages'] ) ) ) . '</p></div>';
        }
        return $html . '</details>';
    }
}
