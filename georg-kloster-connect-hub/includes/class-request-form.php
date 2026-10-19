<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

final class Request_Form {
    public static function enqueue_styles(): void {
        if ( Modules::enabled( 'requests' ) ) {
            wp_enqueue_style( 'gkps-request-form', plugins_url( 'assets/request-form.css', GKPS_FILE ), array(), GKPS_VERSION );
        }
    }
    public static function topics( int $form_id = 0 ): array {
        $lines = preg_split( '/\r\n|\r|\n/', Forms::get( $form_id )['topics'] ?? '' );
        return array_slice( array_values( array_filter( array_map( 'trim', $lines ?: array() ), static fn( $topic ) => '' !== $topic ) ), 0, 20 );
    }
    /** Keep legacy free-text subjects; only the new explicit custom choice is normalized. */
    public static function submission_data( array $input ): array {
        if ( '__other' === ( $input['subject'] ?? null ) ) {
            if ( ! is_string( $input['subject_other'] ?? null ) || '' === trim( $input['subject_other'] ) ) { throw new \InvalidArgumentException( __( 'Enter your own subject.', 'georg-kloster-connect-hub' ) ); }
            $input['subject'] = $input['subject_other'];
        }
        return $input;
    }
    public static function render( $attributes = array() ): string {
        $attributes = is_array( $attributes ) ? $attributes : array();
        $form_id = $attributes['formId'] ?? $attributes['id'] ?? 0;
        if ( ! is_scalar( $form_id ) || ! preg_match( '/^\d+$/D', (string) $form_id ) ) { return ''; }
        $form_id = (int) $form_id;
        if ( ! Forms::active( $form_id ) || get_option( 'gkps_purge_job' ) ) { return ''; }
        $settings = Forms::get( $form_id );
        $attributes = shortcode_atts( array( 'title' => $settings['title'], 'intro' => $settings['intro'], 'submit' => $settings['submit'] ), is_array( $attributes ) ? $attributes : array(), 'gkps_contact_form' );
        foreach ( array( 'title', 'intro', 'submit' ) as $key ) {
            if ( is_string( $attributes[ $key ] ) ) { $settings[ $key ] = sanitize_text_field( $attributes[ $key ] ); }
        }
        $token = wp_generate_password( 32, false, false );
        $id = 'gkps-request-' . wp_unique_id();
        wp_enqueue_script( 'gkps-request-form', plugins_url( 'assets/request-form.js', GKPS_FILE ), array(), GKPS_VERSION, true );
        set_transient( 'gkps_form_' . hash( 'sha256', $token ), array( 'form_id' => $form_id ), HOUR_IN_SECONDS );
        $html = '<section class="gkps-block gkps-requests">' . ( $settings['title'] ? '<h2>' . esc_html( $settings['title'] ) . '</h2>' : '' ) . ( $settings['intro'] ? wpautop( esc_html( $settings['intro'] ) ) : '' );
        $notice = isset( $_GET['gkps_request'] ) && is_string( $_GET['gkps_request'] ) ? sanitize_key( $_GET['gkps_request'] ) : '';
        $notice_form = isset( $_GET['gkch_form'] ) && is_scalar( $_GET['gkch_form'] ) ? (int) $_GET['gkch_form'] : 0;
        if ( $notice_form !== $form_id ) { $notice = ''; }
        if ( 'sent' === $notice ) { $html .= '<p role="status">' . esc_html__( 'Your message has been received. A reply will be sent by email.', 'georg-kloster-connect-hub' ) . '</p>'; }
        if ( 'error' === $notice ) { $html .= '<p role="alert">' . esc_html__( 'Your message was not sent. Check the fields or try again later.', 'georg-kloster-connect-hub' ) . '</p>'; }
        $html .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkps_request_submit"><input type="hidden" name="token" value="' . esc_attr( $token ) . '">' . '<input type="hidden" name="form_id" value="' . esc_attr( $form_id ) . '">' . wp_nonce_field( 'gkps_request_submit_' . $form_id, '_wpnonce', true, false );
        $html .= '<p id="' . esc_attr( $id . '-required' ) . '" class="gkps-required-help">' . esc_html__( 'Fields marked with an asterisk are required.', 'georg-kloster-connect-hub' ) . '</p>';
        foreach ( array( 'name' => array( __( 'Your name', 'georg-kloster-connect-hub' ), __( 'Enter your name', 'georg-kloster-connect-hub' ) ), 'email' => array( __( 'Your email', 'georg-kloster-connect-hub' ), 'name@example.org' ) ) as $key => $field ) { $html .= '<p><label>' . esc_html( $field[0] ) . ' <span class="gkps-required" aria-hidden="true">*</span><br><input required aria-describedby="' . esc_attr( $id . '-required' ) . '" placeholder="' . esc_attr( $field[1] ) . '" autocomplete="' . ( 'email' === $key ? 'email' : 'name' ) . '" maxlength="' . ( 'name' === $key ? '100' : '254' ) . '" type="' . ( 'email' === $key ? 'email' : 'text' ) . '" name="' . esc_attr( $key ) . '"></label></p>'; }
        $html .= '<p><label>' . esc_html__( 'Question subject', 'georg-kloster-connect-hub' ) . ' <span class="gkps-required" aria-hidden="true">*</span><br><select name="subject" required data-request-topic aria-describedby="' . esc_attr( $id . '-required ' . $id . '-topic-help' ) . '" aria-controls="' . esc_attr( $id . '-custom' ) . '"><option value="">' . esc_html__( 'Select a subject', 'georg-kloster-connect-hub' ) . '</option>';
        foreach ( self::topics( $form_id ) as $topic ) { $html .= '<option value="' . esc_attr( $topic ) . '">' . esc_html( $topic ) . '</option>'; }
        $html .= '<option value="__other">' . esc_html__( 'Other subject', 'georg-kloster-connect-hub' ) . '</option></select></label></p><p id="' . esc_attr( $id . '-topic-help' ) . '" class="gkps-topic-help">' . esc_html__( 'For another subject, enter your own title in the field below.', 'georg-kloster-connect-hub' ) . '</p><p id="' . esc_attr( $id . '-custom' ) . '" data-request-custom><label>' . esc_html__( 'Custom question subject', 'georg-kloster-connect-hub' ) . ' <span class="gkps-required" aria-hidden="true">*</span><br><input type="text" name="subject_other" maxlength="200" placeholder="' . esc_attr__( 'Enter your question subject', 'georg-kloster-connect-hub' ) . '"></label></p>';
        $html .= '<p><label>' . esc_html__( 'Message text', 'georg-kloster-connect-hub' ) . ' <span class="gkps-required" aria-hidden="true">*</span><br><textarea name="body" required aria-describedby="' . esc_attr( $id . '-required' ) . '" placeholder="' . esc_attr__( 'Describe your question…', 'georg-kloster-connect-hub' ) . '" maxlength="10000" rows="6"></textarea></label></p><div aria-hidden="true" style="display:none"><label>' . esc_html__( 'Leave this field empty', 'georg-kloster-connect-hub' ) . '<input name="website_check" tabindex="-1" autocomplete="off"></label></div>';
        $html .= '<p><label><input required type="checkbox" name="consent" value="1"> ' . esc_html__( 'I consent to processing my data to receive a reply to this message.', 'georg-kloster-connect-hub' ) . '</label></p>';
        if ( get_privacy_policy_url() ) { $html .= '<p><a href="' . esc_url( get_privacy_policy_url() ) . '">' . esc_html__( 'Privacy policy', 'georg-kloster-connect-hub' ) . '</a></p>'; }
        return $html . '<p><button type="submit">' . esc_html( $settings['submit'] ) . '</button></p></form></section>';
    }
    public static function process_submission( array $input, string $ip ): int {
        $form_id = $input['form_id'] ?? 0;
        if ( ! is_scalar( $form_id ) || ! preg_match( '/^\d+$/D', (string) $form_id ) ) { throw new \RuntimeException( 'Invalid form.' ); }
        $form_id = (int) $form_id;
        $nonce = $input['_wpnonce'] ?? null;
        if ( ! is_string( $nonce ) || ( ! wp_verify_nonce( $nonce, 'gkps_request_submit_' . $form_id ) && ( 0 !== $form_id || ! wp_verify_nonce( $nonce, 'gkps_request_submit' ) ) ) || '1' !== ( $input['consent'] ?? '' ) || ! empty( $input['website_check'] ) || ! is_string( $input['token'] ?? null ) ) { throw new \RuntimeException( 'Invalid form.' ); }
        $hash = hash( 'sha256', $input['token'] );
        $key = 'gkps_form_' . $hash;
        $lock = 'gkps_form_lock_' . $hash;
        $owner = Lock::acquire( $lock );
        if ( ! $owner ) { throw new \RuntimeException( 'Duplicate submission.' ); }
        try {
            $token = get_transient( $key );
            if ( ! $token || ( is_array( $token ) ? ( $token['form_id'] ?? -1 ) !== $form_id : 0 !== $form_id ) ) { throw new \RuntimeException( 'Expired or mismatched form.' ); }
            if ( ! Forms::active( $form_id ) ) { throw new \RuntimeException( 'Disabled form.' ); }
            self::rate_limit( 'gkps_rate_' . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) );
            if ( $form_id && '__other' !== ( $input['subject'] ?? '' ) && self::topics( $form_id ) && ! in_array( $input['subject'] ?? '', self::topics( $form_id ), true ) ) { throw new \RuntimeException( 'Invalid topic.' ); }
            $input['form_id'] = $form_id;
            $id = Requests::create( self::submission_data( $input ) );
            delete_transient( $key );
            return $id;
        } finally { Lock::release( $lock, $owner ); }
    }
    public static function submit(): void {
        $redirect = wp_validate_redirect( wp_get_referer() ?: home_url( '/' ), home_url( '/' ) );
        try {
            self::process_submission( wp_unslash( $_POST ), isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : 'unknown' );
            $status = 'sent';
        } catch ( \Throwable $error ) { $status = 'error'; }
        wp_safe_redirect( add_query_arg( array( 'gkps_request' => $status, 'gkch_form' => isset( $_POST['form_id'] ) && is_scalar( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0 ), $redirect ) ); exit;
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
