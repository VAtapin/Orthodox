<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Publications {
    public static function titles(): array {
        return array( 'telegram' => __( 'Telegram', 'georg-kloster-connect-hub' ), 'facebook' => __( 'Facebook', 'georg-kloster-connect-hub' ), 'instagram' => __( 'Instagram', 'georg-kloster-connect-hub' ), 'vk' => __( 'VK', 'georg-kloster-connect-hub' ), 'ok' => __( 'OK', 'georg-kloster-connect-hub' ) );
    }
    public static function enqueue( int $post_id, array $channels ): array {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! current_user_can( 'publish_posts' ) || ! current_user_can( 'edit_post', $post_id ) ) { throw new \RuntimeException( __( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        $post = get_post( $post_id );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) { throw new \InvalidArgumentException( __( 'Select a published WordPress post.', 'georg-kloster-connect-hub' ) ); }
        $jobs = array();
        $text = $post->post_title . "\n" . wp_html_excerpt( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 700, '…' );
        foreach ( array_keys( self::titles() ) as $id ) {
            if ( ! in_array( $id, $channels, true ) ) { continue; }
            $connector = Connector::instance( $id );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( ! $connector || ! $connector->valid( $connector->settings() ) ) { throw new \InvalidArgumentException( __( 'Configure and enable each selected channel first.', 'georg-kloster-connect-hub' ) ); }
            if ( 'telegram' === $id && ! Telegram_Bot::broadcasting_enabled() ) { throw new \InvalidArgumentException( __( 'Enable Telegram subscriber broadcasts before selecting Telegram.', 'georg-kloster-connect-hub' ) ); }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( 'instagram' === $id && ! Social_Providers::image( $post_id ) ) { throw new \InvalidArgumentException( __( 'Instagram requires a public JPEG featured image.', 'georg-kloster-connect-hub' ) ); }
        }
        foreach ( array_keys( self::titles() ) as $id ) {
            if ( in_array( $id, $channels, true ) ) { $jobs[$id] = Connector::instance( $id )->queue( $text, get_permalink( $post_id ), 'post', $post_id ); }
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! $jobs || in_array( '', $jobs, true ) ) { throw new \RuntimeException( __( 'Choose a channel. If scheduling failed, check WordPress Cron.', 'georg-kloster-connect-hub' ) ); }
        return $jobs;
    }
    public static function submit(): void {
        check_admin_referer( 'gkch_publish' );
        $post_id = isset( $_POST['post_id'] ) && is_scalar( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $channels = isset( $_POST['channels'] ) && is_array( $_POST['channels'] ) ? array_values( array_filter( wp_unslash( $_POST['channels'] ), 'is_string' ) ) : array();
        try { self::enqueue( $post_id, $channels ); $notice = __( 'Publication queued. Delivery status appears below.', 'georg-kloster-connect-hub' ); }
        catch ( \Throwable $error ) { $notice = $error->getMessage(); }
        set_transient( 'gkch_publication_notice_' . get_current_user_id(), $notice, 120 );
        wp_safe_redirect( admin_url( 'admin.php?page=gkch-publications' ) ); exit;
    }
    public static function history(): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 200", $wpdb->esc_like( 'gkps_job_' ) . '%' ), ARRAY_A );
        $jobs = array();
        foreach ( $rows as $row ) {
            $job = maybe_unserialize( $row['option_value'] );
            if ( ! is_array( $job ) || ! in_array( $job['kind'] ?? '', array( 'post', 'automation' ), true ) ) { continue; }
            foreach ( self::titles() as $channel => $title ) {
                if ( preg_match( '/^gkps_job_' . preg_quote( $channel, '/' ) . '_([a-f0-9]{64})$/D', $row['option_name'], $match ) ) { $jobs[] = array_merge( $job, array( 'channel' => $title, 'provider' => $channel, 'id' => $match[1] ) ); break; }
            }
        }
        return $jobs;
    }

    public static function change_job( string $provider, string $id, string $action, bool $confirmed = false ): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! isset( self::titles()[$provider] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $id ) || ! in_array( $action, array( 'retry', 'cancel' ), true ) ) { throw new \InvalidArgumentException( __( 'Invalid publication action.', 'georg-kloster-connect-hub' ) ); }
        $key = 'gkps_job_' . $provider . '_' . $id;
        $owner = Lock::acquire( $key . '_lock', 60 );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! $owner ) { throw new \RuntimeException( __( 'Publication is busy. Try again shortly.', 'georg-kloster-connect-hub' ) ); }
        try {
            $job = get_option( $key );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( ! is_array( $job ) || ! self::can_edit_job( $job ) ) { throw new \RuntimeException( __( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            if ( ! in_array( $job['status'], array( 'failed', 'pending', 'processing', 'uncertain' ), true ) || ( 'retry' === $action && ! in_array( $job['status'], array( 'failed', 'uncertain' ), true ) ) ) { throw new \RuntimeException( __( 'This publication cannot be changed.', 'georg-kloster-connect-hub' ) ); }
            if ( 'retry' === $action ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( 'uncertain' === $job['status'] && ! $confirmed ) { throw new \RuntimeException( __( 'Check the remote account and confirm that no post was published before retrying.', 'georg-kloster-connect-hub' ) ); }
                $settings = Connector::instance( $provider )->settings();
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( ! Connector::instance( $provider )->valid( $settings ) || ! hash_equals( $job['target'], hash( 'sha256', $settings['target'] . '|' . $settings['recipient'] ) ) || ! Publication_Rules::allows( $job ) || ( 'post' === $job['kind'] && 'publish' !== get_post_status( $job['source'] ) ) ) { throw new \RuntimeException( __( 'Check the original channel, source post and active rule before retrying.', 'georg-kloster-connect-hub' ) ); }
                if ( ! empty( $job['container_terminal'] ) ) { unset( $job['container_id'], $job['container_terminal'] ); }
                $job['attempts'] = 0; $job['status'] = 'pending'; $job['error'] = '';
                wp_clear_scheduled_hook( 'gkps_' . $provider . '_worker', array( $id ) );
                $scheduled = wp_schedule_single_event( time() + 1, 'gkps_' . $provider . '_worker', array( $id ), true );
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( is_wp_error( $scheduled ) || ! $scheduled ) { throw new \RuntimeException( __( 'Could not schedule publication.', 'georg-kloster-connect-hub' ) ); }
            } else { $job['status'] = 'cancelled'; wp_clear_scheduled_hook( 'gkps_' . $provider . '_worker', array( $id ) ); }
            update_option( $key, $job, false );
        } finally { Lock::release( $key . '_lock', $owner ); }
    }

    public static function action(): void {
        check_admin_referer( 'gkch_publication_action' );
        try {
            $input = wp_unslash( $_POST );
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
            foreach ( array( 'provider', 'job', 'job_action' ) as $field ) { if ( ! isset( $input[$field] ) || ! is_string( $input[$field] ) ) { throw new \InvalidArgumentException( __( 'Invalid publication action.', 'georg-kloster-connect-hub' ) ); } }
            self::change_job( $input['provider'], $input['job'], $input['job_action'], ! empty( $input['confirmed'] ) );
            $notice = __( 'Publication updated.', 'georg-kloster-connect-hub' );
        } catch ( \Throwable $error ) { $notice = $error->getMessage(); }
        set_transient( 'gkch_publication_notice_' . get_current_user_id(), $notice, 120 );
        wp_safe_redirect( admin_url( 'admin.php?page=gkch-publications' ) ); exit;
    }
    public static function render(): void {
        if ( ! current_user_can( 'publish_posts' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        echo '<div class="wrap"><h1>' . esc_html__( 'Publications', 'georg-kloster-connect-hub' ) . '</h1>';
        $key = 'gkch_publication_notice_' . get_current_user_id();
        if ( $notice = get_transient( $key ) ) { echo '<p role="status">' . esc_html( $notice ) . '</p>'; delete_transient( $key ); }
        Publication_Rules_Admin::render();
        echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=gkch-telegram' ) ) . '">' . esc_html__( 'Telegram bot subscribers and delivery status', 'georg-kloster-connect-hub' ) . '</a></p>';
        $statuses = array( 'pending' => __( 'Queued', 'georg-kloster-connect-hub' ), 'processing' => __( 'Processing', 'georg-kloster-connect-hub' ), 'sent' => __( 'Sent', 'georg-kloster-connect-hub' ), 'failed' => __( 'Failed', 'georg-kloster-connect-hub' ), 'cancelled' => __( 'Cancelled', 'georg-kloster-connect-hub' ), 'uncertain' => __( 'Check the remote account before retrying', 'georg-kloster-connect-hub' ) );
        echo '<h2>' . esc_html__( 'Publication history', 'georg-kloster-connect-hub' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Publication or reminder', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Channel', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Status', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody>';
        foreach ( self::history() as $job ) {
            if ( ! self::can_edit_job( $job ) ) { continue; }
            $title = 'automation' === $job['kind'] ? ( $job['reply_context']['rule_name'] ?? $job['text'] ) : get_the_title( $job['source'] );
            echo '<tr><td>' . esc_html( $title ) . '</td><td>' . esc_html( $job['channel'] ) . '</td><td>' . esc_html( $statuses[$job['status']] ?? __( 'Unknown', 'georg-kloster-connect-hub' ) ) . ( ! empty( $job['error'] ) ? '<br>' . esc_html( $job['error'] ) : '' );
            if ( in_array( $job['status'], array( 'pending', 'processing', 'failed', 'uncertain' ), true ) ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_publication_action"><input type="hidden" name="provider" value="' . esc_attr( $job['provider'] ) . '"><input type="hidden" name="job" value="' . esc_attr( $job['id'] ) . '">';
                wp_nonce_field( 'gkch_publication_action' );
                if ( 'uncertain' === $job['status'] ) { echo '<p><label><input type="checkbox" name="confirmed" value="1"> ' . esc_html__( 'I checked the account: no post was published', 'georg-kloster-connect-hub' ) . '</label></p>'; }
                if ( in_array( $job['status'], array( 'failed', 'uncertain' ), true ) ) { echo '<button class="button" name="job_action" value="retry">' . esc_html__( 'Retry', 'georg-kloster-connect-hub' ) . '</button> '; }
                echo '<button class="button" name="job_action" value="cancel">' . esc_html__( 'Cancel', 'georg-kloster-connect-hub' ) . '</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function can_edit_job( array $job ): bool {
        return current_user_can( 'publish_posts' ) && ( 'automation' === ( $job['kind'] ?? '' ) ? Publication_Rules::can_manage() : 'post' === ( $job['kind'] ?? '' ) && current_user_can( 'edit_post', $job['source'] ) );
    }
}
