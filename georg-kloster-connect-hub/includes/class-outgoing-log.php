<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

/** A short-lived, private audit trail for outgoing messages (not publications). */
final class Outgoing_Log {
    const OPTION = 'gkch_outgoing_log';
    const DAYS = 30;
    const LIMIT = 200;

    public static function schedule(): void {
        add_action( 'gkch_outgoing_log_retention', array( self::class, 'retention' ) );
        if ( ! wp_next_scheduled( 'gkch_outgoing_log_retention' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gkch_outgoing_log_retention' ); }
    }

    public static function retention(): void {
        $lock = Lock::acquire( 'gkch_outgoing_log_lock', 10 );
        if ( ! $lock ) { return; }
        try { self::store( self::rows() ); }
        finally { Lock::release( 'gkch_outgoing_log_lock', $lock ); }
    }

    public static function record( array $event ): void {
        $channel = $event['channel'] ?? '';
        if ( ! in_array( $channel, array( 'email', 'telegram' ), true ) ) { return; }
        $statuses = array( 'accepted', 'failed', 'processing', 'uncertain' );
        $entry = array(
            'id' => wp_generate_uuid4(), 'time_gmt' => gmdate( DATE_ATOM ), 'channel' => $channel,
            'recipient' => sanitize_text_field( (string) ( $event['recipient'] ?? '' ) ),
            'subject' => sanitize_text_field( (string) ( $event['subject'] ?? '' ) ),
            'content' => sanitize_textarea_field( (string) ( $event['content'] ?? '' ) ),
            'kind' => sanitize_key( (string) ( $event['kind'] ?? 'message' ) ),
            'source_id' => absint( $event['source_id'] ?? 0 ), 'source_type' => sanitize_key( (string) ( $event['source_type'] ?? '' ) ),
            'status' => in_array( $event['status'] ?? '', $statuses, true ) ? $event['status'] : 'failed',
            'attempt' => max( 1, absint( $event['attempt'] ?? 1 ) ),
            'http_code' => isset( $event['http_code'] ) ? absint( $event['http_code'] ) : 0,
            'remote_id' => sanitize_text_field( (string) ( $event['remote_id'] ?? '' ) ),
            'transport' => sanitize_key( (string) ( $event['transport'] ?? '' ) ),
            'error_code' => preg_match( '/^[a-z0-9_-]{1,64}$/D', (string) ( $event['error_code'] ?? '' ) ) ? (string) $event['error_code'] : '',
        );
        $lock = Lock::acquire( 'gkch_outgoing_log_lock', 10 );
        if ( ! $lock ) { return; }
        try { $rows = self::rows(); $rows[] = $entry; self::store( $rows ); }
        finally { Lock::release( 'gkch_outgoing_log_lock', $lock ); }
    }

    public static function rows( array $filters = array() ): array {
        $cutoff = time() - self::DAYS * DAY_IN_SECONDS;
        $rows = get_option( self::OPTION, array() );
        $rows = is_array( $rows ) ? array_values( array_filter( $rows, static fn( $row ) => is_array( $row ) && strtotime( $row['time_gmt'] ?? '' ) > $cutoff ) ) : array();
        if ( ! empty( $filters['channel'] ) ) { $rows = array_values( array_filter( $rows, static fn( $row ) => $filters['channel'] === ( $row['channel'] ?? '' ) ) ); }
        if ( ! empty( $filters['status'] ) ) { $rows = array_values( array_filter( $rows, static fn( $row ) => $filters['status'] === ( $row['status'] ?? '' ) ) ); }
        usort( $rows, static fn( $a, $b ) => strcmp( $b['time_gmt'], $a['time_gmt'] ) );
        return $rows;
    }

    private static function store( array $rows ): void {
        $cutoff = time() - self::DAYS * DAY_IN_SECONDS;
        $rows = array_values( array_filter( $rows, static fn( $row ) => strtotime( $row['time_gmt'] ?? '' ) > $cutoff ) );
        update_option( self::OPTION, array_slice( $rows, -self::LIMIT ), false );
    }

    public static function delete_source( int $id ): void {
        if ( $id < 1 ) { return; }
        $lock = Lock::acquire( 'gkch_outgoing_log_lock', 10 );
        if ( ! $lock ) { return; }
        try { self::store( array_values( array_filter( self::rows(), static fn( $row ) => ! ( 'request' === ( $row['source_type'] ?? '' ) && $id === (int) ( $row['source_id'] ?? 0 ) ) ) ) ); }
        finally { Lock::release( 'gkch_outgoing_log_lock', $lock ); }
    }

    public static function for_source( int $id ): array {
        return array_values( array_filter( self::rows(), static fn( $row ) => 'request' === ( $row['source_type'] ?? '' ) && $id === (int) ( $row['source_id'] ?? 0 ) ) );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
        $channel = isset( $_GET['channel'] ) && is_string( $_GET['channel'] ) && in_array( $_GET['channel'], array( 'email', 'telegram' ), true ) ? sanitize_key( $_GET['channel'] ) : '';
        $status = isset( $_GET['status'] ) && is_string( $_GET['status'] ) && in_array( $_GET['status'], array( 'accepted', 'failed', 'processing', 'uncertain' ), true ) ? sanitize_key( $_GET['status'] ) : '';
        echo '<div class="wrap"><h1>' . esc_html__( 'Message delivery log', 'georg-kloster-connect-hub' ) . '</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="gkch-log"><label>' . esc_html__( 'Channel', 'georg-kloster-connect-hub' ) . ' <select name="channel"><option value="">' . esc_html__( 'All channels', 'georg-kloster-connect-hub' ) . '</option>';
        foreach ( array( 'email' => __( 'Email', 'georg-kloster-connect-hub' ), 'telegram' => __( 'Telegram', 'georg-kloster-connect-hub' ) ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $channel, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></label> <label>' . esc_html__( 'Result', 'georg-kloster-connect-hub' ) . ' <select name="status"><option value="">' . esc_html__( 'All results', 'georg-kloster-connect-hub' ) . '</option>';
        foreach ( array( 'accepted' => __( 'Accepted by service', 'georg-kloster-connect-hub' ), 'failed' => __( 'Failed', 'georg-kloster-connect-hub' ), 'processing' => __( 'Processing', 'georg-kloster-connect-hub' ), 'uncertain' => __( 'Result uncertain', 'georg-kloster-connect-hub' ) ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></label> <button class="button button-primary">' . esc_html__( 'Filter', 'georg-kloster-connect-hub' ) . '</button></form><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Time (site timezone)', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Channel / recipient', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Message', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Result', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody>';
        $rows = self::rows( array( 'channel' => $channel, 'status' => $status ) );
        if ( ! $rows ) { echo '<tr><td colspan="4">' . esc_html__( 'No outgoing messages match these filters.', 'georg-kloster-connect-hub' ) . '</td></tr>'; }
        foreach ( $rows as $row ) {
            $time = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $row['time_gmt'] ) ), 'Y-m-d H:i:s' );
            $label = array( 'accepted' => __( 'Accepted by service; inbox delivery is not confirmed.', 'georg-kloster-connect-hub' ), 'failed' => __( 'Failed', 'georg-kloster-connect-hub' ), 'processing' => __( 'Processing', 'georg-kloster-connect-hub' ), 'uncertain' => __( 'Result uncertain; check the destination account.', 'georg-kloster-connect-hub' ) )[$row['status']] ?? $row['status'];
            echo '<tr><td>' . esc_html( $time ) . '</td><td><strong>' . esc_html( ucfirst( $row['channel'] ) ) . '</strong><br>' . esc_html( $row['recipient'] ) . '</td><td><strong>' . esc_html( $row['subject'] ?: ( __( 'Message', 'georg-kloster-connect-hub' ) . ' · ' . $row['kind'] ) ) . '</strong><br><details><summary>' . esc_html__( 'Show sent content', 'georg-kloster-connect-hub' ) . '</summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere;max-width:50em">' . esc_html( $row['content'] ) . '</pre></details>';
            if ( $row['source_id'] ) { echo '<small>' . esc_html( sprintf( __( 'Source message #%d', 'georg-kloster-connect-hub' ), $row['source_id'] ) ) . '</small>'; }
            echo '</td><td>' . esc_html( $label ) . '<br>' . esc_html( sprintf( __( 'Attempt #%d', 'georg-kloster-connect-hub' ), $row['attempt'] ) );
            if ( $row['http_code'] && 'email' === $row['channel'] ) { echo '<br>' . esc_html( sprintf( __( 'SMTP response: %d', 'georg-kloster-connect-hub' ), $row['http_code'] ) ); }
            elseif ( $row['http_code'] ) { echo '<br>' . esc_html( sprintf( __( 'HTTP response: %d', 'georg-kloster-connect-hub' ), $row['http_code'] ) ); }
            foreach ( array( 'remote_id' => __( 'Service message ID', 'georg-kloster-connect-hub' ), 'transport' => __( 'Mail transport', 'georg-kloster-connect-hub' ), 'error_code' => __( 'Error code', 'georg-kloster-connect-hub' ) ) as $key => $title ) { if ( $row[$key] ) { echo '<br>' . esc_html( $title . ': ' . $row[$key] ); } }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
