<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

/** Connect a Facebook Page through the administrator's own Meta application. */
final class Facebook_Connect {
    public static function boot(): void {
        add_action( 'gkps_connector_settings_after', array( self::class, 'render' ) );
        add_action( 'admin_post_gkch_facebook_connect', array( self::class, 'start' ) );
        add_action( 'admin_post_gkch_facebook_callback', array( self::class, 'callback' ) );
    }

    private static function authorize(): void {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
    }

    private static function callback_url(): string { return admin_url( 'admin-post.php?action=gkch_facebook_callback' ); }
    private static function transient_key(): string { return 'gkch_fb_oauth_' . get_current_user_id(); }
    private static function notice_key(): string { return 'gkch_fb_notice_' . get_current_user_id(); }

    private static function finish( string $code, array $meta = array() ): void {
        set_transient( self::notice_key(), array( 'code' => $code, 'meta' => $meta ), 120 );
        wp_safe_redirect( Connector::instance( 'facebook' )->settings_url() ); exit;
    }

    public static function render( string $id ): void {
        if ( 'facebook' !== $id ) { return; }
        self::authorize();
        $notice_data = get_transient( self::notice_key() ); delete_transient( self::notice_key() );
        $notice = is_array( $notice_data ) ? ( $notice_data['code'] ?? '' ) : '';
        $messages = array(
            'connected' => __( 'Facebook Page connected. Its Page token was saved.', 'georg-kloster-connect-hub' ),
            'invalid' => __( 'Check the Meta App ID, App Secret and Facebook Page ID, then try again.', 'georg-kloster-connect-hub' ),
            'cancelled' => __( 'Facebook authorization was cancelled or expired. Start the connection again.', 'georg-kloster-connect-hub' ),
            'exchange' => __( 'Meta could not complete authorization. Check the app settings and try again.', 'georg-kloster-connect-hub' ),
            'page' => __( 'Meta did not return a token for this Page. Check its ID and the permissions granted to the app.', 'georg-kloster-connect-hub' ),
            'lifetime' => __( 'Meta did not confirm a long-lived Page token. Check the app and Page permissions, then connect again.', 'georg-kloster-connect-hub' ),
        );
        if ( isset( $messages[ $notice ] ) ) {
            $meta = is_array( $notice_data['meta'] ?? null ) ? $notice_data['meta'] : array();
            $codes = array_filter( array( $meta['code'] ?? '', $meta['error_subcode'] ?? '' ), static function ( $value ) { return is_string( $value ) && preg_match( '/^[0-9]{1,10}$/D', $value ); } );
            echo '<div class="notice ' . ( 'connected' === $notice ? 'notice-success' : 'notice-error' ) . '"><p>' . esc_html( $messages[ $notice ] ) . ( $codes ? ' (Meta: ' . esc_html( implode( ' / ', $codes ) ) . ')' : '' ) . '</p></div>';
        }
        $settings = Connector::instance( 'facebook' )->settings();
        echo '<hr><h2>' . esc_html__( 'Connect Facebook Page', 'georg-kloster-connect-hub' ) . '</h2>';
        if ( 'https' !== wp_parse_url( self::callback_url(), PHP_URL_SCHEME ) ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'Facebook connection requires HTTPS for the WordPress admin.', 'georg-kloster-connect-hub' ) . '</p></div>'; return; }
        echo '<p>' . esc_html__( 'Sign in to Facebook to obtain a long-lived Page token. Meta may require app review and can revoke access later.', 'georg-kloster-connect-hub' ) . '</p>';
        echo '<p>' . esc_html__( 'In Facebook Login settings, add this exact OAuth redirect URL:', 'georg-kloster-connect-hub' ) . ' <code>' . esc_html( self::callback_url() ) . '</code></p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_facebook_connect">';
        wp_nonce_field( 'gkch_facebook_connect' );
        echo '<p><label>' . esc_html__( 'Meta App ID', 'georg-kloster-connect-hub' ) . '<br><input class="regular-text" name="app_id" required inputmode="numeric"></label></p>';
        echo '<p><label>' . esc_html__( 'Meta App Secret', 'georg-kloster-connect-hub' ) . '<br><input class="regular-text" type="password" name="app_secret" required autocomplete="new-password"></label></p>';
        echo '<p><label>' . esc_html__( 'Facebook Page ID', 'georg-kloster-connect-hub' ) . '<br><input class="regular-text" name="page_id" required inputmode="numeric" value="' . esc_attr( $settings['target'] ) . '"></label></p>';
        submit_button( __( 'Connect with Facebook', 'georg-kloster-connect-hub' ) ); echo '</form>';
    }

    public static function start(): void {
        self::authorize(); check_admin_referer( 'gkch_facebook_connect' );
        if ( 'https' !== wp_parse_url( self::callback_url(), PHP_URL_SCHEME ) ) { self::finish( 'invalid' ); }
        $input = array();
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each scalar is unslashed, sanitized and validated below.
        foreach ( array( 'app_id', 'app_secret', 'page_id' ) as $field ) { $input[ $field ] = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : ''; }
        if ( ! preg_match( '/^[1-9][0-9]*$/D', $input['app_id'] ) || ! preg_match( '/^[1-9][0-9]*$/D', $input['page_id'] ) || ! preg_match( '/^[A-Za-z0-9_-]{16,128}$/D', $input['app_secret'] ) ) { self::finish( 'invalid' ); }
        $state = wp_generate_password( 48, false, false );
        $input['state_hash'] = hash( 'sha256', $state );
        set_transient( self::transient_key(), $input, 10 * MINUTE_IN_SECONDS );
        $url = add_query_arg( array( 'client_id' => $input['app_id'], 'redirect_uri' => self::callback_url(), 'state' => $state, 'response_type' => 'code', 'scope' => 'pages_show_list,pages_read_engagement,pages_manage_posts' ), 'https://www.facebook.com/' . Social_Providers::META_GRAPH_API_VERSION . '/dialog/oauth' );
        $allow = static function ( array $hosts ): array { $hosts[] = 'www.facebook.com'; return $hosts; };
        add_filter( 'allowed_redirect_hosts', $allow ); wp_safe_redirect( $url ); remove_filter( 'allowed_redirect_hosts', $allow ); exit;
    }

    /** Fetch a Meta response without exposing tokens or request URLs to the UI. */
    private static function get( string $url, array $headers = array() ): array {
        try { $response = wp_safe_remote_get( $url, array( 'timeout' => 20, 'redirection' => 0, 'headers' => $headers, 'limit_response_size' => 65536 ) ); }
        catch ( \Throwable $error ) { return array(); }
        if ( is_wp_error( $response ) ) { return array(); }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( 200 === (int) wp_remote_retrieve_response_code( $response ) && is_array( $body ) && empty( $body['error'] ) ) { return $body; }
        $error = is_array( $body['error'] ?? null ) ? $body['error'] : array(); $safe = array();
        foreach ( array( 'code', 'error_subcode' ) as $key ) { if ( isset( $error[ $key ] ) && is_scalar( $error[ $key ] ) && preg_match( '/^[0-9]{1,10}$/D', (string) $error[ $key ] ) ) { $safe[ $key ] = (string) $error[ $key ]; } }
        return array( '_meta_error' => $safe );
    }

    /** Exchange a one-time authorization code, then obtain the selected Page token. */
    public static function exchange( array $input, string $code ): array {
        $base = 'https://graph.facebook.com/' . Social_Providers::META_GRAPH_API_VERSION;
        $response = self::get( add_query_arg( array( 'client_id' => $input['app_id'], 'client_secret' => $input['app_secret'], 'redirect_uri' => self::callback_url(), 'code' => $code ), $base . '/oauth/access_token' ) );
        $short = $response['access_token'] ?? '';
        if ( ! is_string( $short ) || '' === $short ) { return array( 'error' => 'exchange', 'meta' => $response['_meta_error'] ?? array() ); }
        $response = self::get( add_query_arg( array( 'grant_type' => 'fb_exchange_token', 'client_id' => $input['app_id'], 'client_secret' => $input['app_secret'], 'fb_exchange_token' => $short ), $base . '/oauth/access_token' ) );
        $long = $response['access_token'] ?? '';
        if ( ! is_string( $long ) || '' === $long || ! isset( $response['expires_in'] ) || (int) $response['expires_in'] < 30 * DAY_IN_SECONDS ) { return array( 'error' => 'exchange', 'meta' => $response['_meta_error'] ?? array() ); }
        $url = add_query_arg( array( 'fields' => 'name,access_token', 'appsecret_proof' => hash_hmac( 'sha256', $long, $input['app_secret'] ) ), $base . '/' . $input['page_id'] );
        $page = self::get( $url, array( 'Authorization' => 'Bearer ' . $long ) );
        $token = $page['access_token'] ?? '';
        if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9._-]+$/D', $token ) || (string) ( $page['id'] ?? '' ) !== $input['page_id'] ) { return array( 'error' => 'page', 'meta' => $page['_meta_error'] ?? array() ); }
        $debug_url = add_query_arg( array( 'input_token' => $token ), $base . '/debug_token' );
        $debug = self::get( $debug_url, array( 'Authorization' => 'Bearer ' . $input['app_id'] . '|' . $input['app_secret'] ) );
        $data = $debug['data'] ?? array();
        if ( ! is_array( $data ) || empty( $data['is_valid'] ) || 'PAGE' !== ( $data['type'] ?? '' ) || (string) ( $data['app_id'] ?? '' ) !== $input['app_id'] || ! isset( $data['expires_at'] ) || ! is_numeric( $data['expires_at'] ) || ( 0 !== (int) $data['expires_at'] && (int) $data['expires_at'] < time() + 30 * DAY_IN_SECONDS ) ) { return array( 'error' => 'lifetime', 'meta' => $debug['_meta_error'] ?? array() ); }
        return array( 'token' => $token, 'proof' => hash_hmac( 'sha256', $token, $input['app_secret'] ) );
    }

    public static function callback(): void {
        self::authorize();
        $input = get_transient( self::transient_key() ); delete_transient( self::transient_key() );
        // The state is a one-time, user-bound random token checked against the server-side transient.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Meta redirects with OAuth state instead of a WordPress nonce; the state is checked below.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The scalar state is unslashed, sanitized and verified below.
        $state = isset( $_GET['state'] ) && is_string( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The scalar authorization code is unslashed and sanitized before being sent to Meta.
        $code = isset( $_GET['code'] ) && is_string( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( ! is_array( $input ) || '' === $state || '' === $code || ! isset( $input['state_hash'] ) || ! hash_equals( $input['state_hash'], hash( 'sha256', $state ) ) ) { self::finish( 'cancelled' ); }
        $result = self::exchange( $input, $code );
        if ( empty( $result['token'] ) ) { self::finish( $result['error'] ?? 'exchange', $result['meta'] ?? array() ); }
        $settings = Connector::instance( 'facebook' )->settings();
        $settings['target'] = $input['page_id']; $settings['token'] = $result['token']; $settings['appsecret_proof'] = $result['proof']; $settings['enabled'] = true;
        update_option( 'gkps_connect_facebook', $settings, false );
        do_action( 'gkps_connector_saved', 'facebook', $settings );
        self::finish( 'connected' );
    }
}
