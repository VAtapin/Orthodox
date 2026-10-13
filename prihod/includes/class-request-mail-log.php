<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

/** Bounded private diagnostics, never message contents or addresses. */
final class Request_Mail_Log {
    const DAYS = 30;
    const LIMIT = 50;
    public static function begin( int $request, int $reply ): string {
        $post = get_post( $reply ); $parent = get_post( $request );
        if ( ! $post || ! $parent || 'prihod_message' !== $post->post_type || 'private' !== $post->post_status || $request !== (int) $post->post_parent || 'prihod_request' !== $parent->post_type || 'private' !== $parent->post_status ) { return ''; }
        $attempt = (int) get_post_meta( $reply, '_prihod_mail_attempt_sequence', true ) + 1;
        update_post_meta( $reply, '_prihod_mail_attempt_sequence', $attempt );
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
            if ( isset( $data['message_id'] ) && preg_match( '/^<prihod-[a-f0-9-]{36}@[a-z0-9.-]+>$/D', $data['message_id'] ) ) { $row['message_id'] = $data['message_id']; }
            foreach ( array( 'from_matches_profile', 'envelope_matches_profile', 'short_circuit', 'message_id_observed' ) as $key ) { if ( isset( $data[$key] ) ) { $row[$key] = (bool) $data[$key]; } }
            if ( isset( $data['exception_type'] ) && preg_match( '/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]{0,127}$/D', $data['exception_type'] ) ) { $row['exception_type'] = $data['exception_type']; }
            foreach ( array( 'exception_code', 'smtp_code' ) as $key ) { if ( isset( $data[$key] ) && is_int( $data[$key] ) ) { $row[$key] = $data[$key]; } }
            if ( isset( $data['error_code'] ) ) { $row['error_code'] = in_array( $data['error_code'], array( 'mail_not_accepted', 'mail_exception', 'wp_mail_failed', 'compose_exception', 'invalid_recipient' ), true ) ? $data['error_code'] : 'mail_error'; }
            if ( in_array( $stage, array( 'accepted', 'failed' ), true ) ) { $row['status'] = $stage; $row['finished_gmt'] = gmdate( DATE_ATOM ); }
        }
        unset( $row ); self::store( $reply, $rows );
    }
    public static function rows( int $reply ): array {
        $rows = get_post_meta( $reply, '_prihod_mail_log', true ); $cutoff = time() - self::DAYS * DAY_IN_SECONDS;
        return is_array( $rows ) ? array_values( array_filter( $rows, static fn( $row ) => is_array( $row ) && strtotime( $row['started_gmt'] ?? '' ) > $cutoff ) ) : array();
    }
    private static function store( int $reply, array $rows ): void {
        $rows = array_slice( $rows, -self::LIMIT );
        if ( ! $rows ) { delete_post_meta( $reply, '_prihod_mail_log' ); delete_post_meta( $reply, '_prihod_mail_log_expires' ); delete_post_meta( $reply, '_prihod_mail_transport' ); return; }
        update_post_meta( $reply, '_prihod_mail_log', $rows );
        update_post_meta( $reply, '_prihod_mail_log_expires', strtotime( $rows[0]['started_gmt'] ) + self::DAYS * DAY_IN_SECONDS );
    }
    public static function retention(): void {
        $posts = get_posts( array( 'post_type' => 'prihod_message', 'post_status' => 'private', 'numberposts' => 100, 'meta_key' => '_prihod_mail_log_expires', 'meta_value' => time(), 'meta_compare' => '<=', 'meta_type' => 'NUMERIC', 'orderby' => 'meta_value_num', 'order' => 'ASC' ) );
        foreach ( $posts as $post ) { self::store( $post->ID, self::rows( $post->ID ) ); }
    }
    public static function render( int $reply ): string {
        if ( ! current_user_can( 'view_prihod_requests' ) ) { return ''; }
        $post = get_post( $reply );
        if ( ! $post || 'prihod_message' !== $post->post_type || 'private' !== $post->post_status ) { return ''; }
        $rows = self::rows( $reply );
        $html = '<details class="prihod-mail-diagnostics"><summary>' . esc_html__( 'Попытки отправки письма', 'prihod' ) . '</summary><p>' . esc_html__( 'Технический журнал хранится 30 дней. Принятие письма транспортом не подтверждает доставку в ящик.', 'prihod' ) . '</p>';
        if ( ! $rows ) { $html .= '<p>' . esc_html__( 'Для этого ответа технический журнал отсутствует. Старые отправки не восстанавливаются задним числом.', 'prihod' ) . '</p>'; }
        foreach ( $rows as $row ) {
            $label = array( 'started' => __( 'Попытка начата, результат не получен', 'prihod' ), 'accepted' => __( 'Передано почтовой системе. Доставка в ящик не подтверждена.', 'prihod' ), 'failed' => __( 'Ошибка отправки', 'prihod' ) )[$row['status']] ?? '';
            if ( ! empty( $row['short_circuit'] ) && 'accepted' === $row['status'] ) { $label = __( 'Принято WordPress; результат транспорта неизвестен.', 'prihod' ); }
            $html .= '<div style="overflow-wrap:anywhere;padding:12px 0;border-top:1px solid #dce3ec"><p><strong>' . esc_html( $label ) . '</strong></p><p>' . esc_html( sprintf( __( 'Обращение #%1$d · ответ #%2$d · попытка %3$d', 'prihod' ), $row['request'], $reply, $row['attempt'] ) ) . '</p><dl>';
            foreach ( array( 'started_gmt' => __( 'Начало (UTC)', 'prihod' ), 'finished_gmt' => __( 'Окончание (UTC)', 'prihod' ), 'transport' => __( 'Транспорт', 'prihod' ), 'message_id' => ! empty( $row['message_id_observed'] ) ? 'Message-ID' : __( 'Назначенный Message-ID', 'prihod' ), 'id' => __( 'ID попытки', 'prihod' ), 'error_code' => __( 'Код ошибки', 'prihod' ), 'exception_type' => __( 'Тип исключения', 'prihod' ), 'exception_code' => __( 'Код исключения', 'prihod' ), 'smtp_code' => __( 'Код SMTP', 'prihod' ) ) as $key => $title ) { if ( ! empty( $row[$key] ) ) { $html .= '<dt>' . esc_html( $title ) . '</dt><dd>' . esc_html( $row[$key] ) . '</dd>'; } }
            $html .= '</dl>' . ( ! empty( $row['short_circuit'] ) ? '<p>' . esc_html__( 'Письмо обработано перехватчиком WordPress; фактический транспорт не зафиксирован.', 'prihod' ) . '</p>' : '' );
            $html .= '<p>' . esc_html__( 'Этапы:', 'prihod' ) . ' ' . esc_html( implode( ' → ', array_map( static fn( $stage ) => array( 'started' => __( 'Начало', 'prihod' ), 'composed' => __( 'Письмо подготовлено', 'prihod' ), 'mailer_ready' => __( 'Транспорт подготовлен', 'prihod' ), 'accepted' => __( 'Принято транспортом', 'prihod' ), 'failed' => __( 'Ошибка отправки', 'prihod' ) )[$stage] ?? $stage, $row['stages'] ) ) ) . '</p></div>';
        }
        return $html . '</details>';
    }
}
