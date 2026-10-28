<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Hub_Settings {
    public static function get(): array {
        $saved = get_option( 'gkch_settings', array() );
        return array_merge( array( 'enabled' => true, 'recaptcha_site_key' => '', 'recaptcha_threshold' => 0.5, 'recaptcha_secret_key' => '' ), is_array( $saved ) ? $saved : array(), array( 'recaptcha_secret_key' => (string) get_option( 'gkch_recaptcha_secret', '' ) ) );
    }
    public static function register(): void {
        register_setting( 'gkch_settings', 'gkch_settings', array( 'type' => 'array', 'sanitize_callback' => array( self::class, 'sanitize_general' ) ) );
        register_setting( 'gkch_settings', 'gkps_retention_days', array( 'type' => 'integer', 'sanitize_callback' => static fn( $value ) => max( 30, min( 3650, absint( $value ) ) ) ) );
        add_filter( 'option_page_capability_gkch_settings', static fn() => 'manage_gkps_settings' );
        $settings = self::get();
        if ( function_exists( 'wp_add_privacy_policy_content' ) && '' !== $settings['recaptcha_site_key'] && '' !== $settings['recaptcha_secret_key'] ) {
            $content = '<p>' . esc_html__( 'This site uses Google reCAPTCHA v3 to assess whether public form submissions are automated. Google receives browser interaction data and the reCAPTCHA response token; the server sends the token to Google for verification without forwarding the visitor IP address.', 'georg-kloster-connect-hub' ) . '</p><p><a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Privacy Policy', 'georg-kloster-connect-hub' ) . '</a> · <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Google Terms of Service', 'georg-kloster-connect-hub' ) . '</a></p>';
            wp_add_privacy_policy_content( __( 'Google reCAPTCHA', 'georg-kloster-connect-hub' ), $content );
        }
    }
    public static function sanitize_general( $input ): array {
        $current = self::get();
        $site_key = $current['recaptcha_site_key'];
        $threshold = $current['recaptcha_threshold'];
        if ( is_array( $input ) && isset( $input['recaptcha_site_key'] ) && is_string( $input['recaptcha_site_key'] ) ) { $site_key = sanitize_text_field( trim( $input['recaptcha_site_key'] ) ); }
        if ( is_array( $input ) && isset( $input['recaptcha_threshold'] ) && is_numeric( $input['recaptcha_threshold'] ) ) { $threshold = max( 0.1, min( 0.9, round( (float) $input['recaptcha_threshold'], 1 ) ) ); }
        return array( 'enabled' => is_array( $input ) && ! empty( $input['enabled'] ), 'recaptcha_site_key' => $site_key, 'recaptcha_threshold' => $threshold );
    }
    public static function save_general(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        check_admin_referer( 'gkch_settings_save' );
        $settings = self::get();
        $enabled = ! empty( $_POST['enabled'] );
        $retention = isset( $_POST['retention_days'] ) && is_scalar( $_POST['retention_days'] ) ? max( 30, min( 3650, absint( wp_unslash( $_POST['retention_days'] ) ) ) ) : 365;
        $raw_site_key = isset( $_POST['site_key'] ) && is_string( $_POST['site_key'] ) ? wp_unslash( $_POST['site_key'] ) : '';
        $site_key = sanitize_text_field( trim( $raw_site_key ) );
        $raw_threshold = isset( $_POST['threshold'] ) && is_scalar( $_POST['threshold'] ) ? wp_unslash( $_POST['threshold'] ) : '';
        $threshold = is_numeric( $raw_threshold ) ? max( 0.1, min( 0.9, round( (float) $raw_threshold, 1 ) ) ) : 0.5;
        $raw_secret = isset( $_POST['secret_key'] ) && is_string( $_POST['secret_key'] ) ? wp_unslash( $_POST['secret_key'] ) : '';
        $secret = sanitize_text_field( trim( $raw_secret ) );
        update_option( 'gkps_retention_days', $retention, false );
        update_option( 'gkch_settings', array( 'enabled' => $enabled, 'recaptcha_site_key' => $site_key, 'recaptcha_threshold' => $threshold ), false );
        if ( ! empty( $_POST['clear_secret'] ) ) { delete_option( 'gkch_recaptcha_secret' ); }
        elseif ( '' !== $secret ) { update_option( 'gkch_recaptcha_secret', $secret, false ); }
        wp_safe_redirect( admin_url( 'admin.php?page=gkch-settings&settings_saved=1' ) );
        exit;
    }
    public static function render(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- These GET parameters only select a settings tab and show a success notice.
        $network = isset( $_GET['network'] ) && is_string( $_GET['network'] ) ? sanitize_key( wp_unslash( $_GET['network'] ) ) : '';
        $networks = Publications::titles();
        if ( ! isset( $networks[$network] ) ) { $network = ''; }
        echo '<div class="wrap"><h1>' . esc_html__( 'Settings', 'georg-kloster-connect-hub' ) . '</h1><nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Settings sections', 'georg-kloster-connect-hub' ) . '">';
        echo '<a class="nav-tab' . ( '' === $network ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=gkch-settings' ) ) . '">' . esc_html__( 'General', 'georg-kloster-connect-hub' ) . '</a>';
        foreach ( $networks as $id => $title ) { echo '<a class="nav-tab' . ( $id === $network ? ' nav-tab-active' : '' ) . '" href="' . esc_url( Connector::instance( $id )->settings_url() ) . '">' . esc_html( $title ) . '</a>'; }
        echo '</nav>';
        if ( $network ) { echo '</div>'; Connector::instance( $network )->render(); return; }
        $settings = self::get();
        $has_site_key = '' !== $settings['recaptcha_site_key'];
        $has_secret_key = '' !== $settings['recaptcha_secret_key'];
        if ( isset( $_GET['settings_saved'] ) ) { echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'georg-kloster-connect-hub' ) . '</p></div>'; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_settings_save">';
        wp_nonce_field( 'gkch_settings_save' );
        echo '<h2>' . esc_html__( 'General', 'georg-kloster-connect-hub' ) . '</h2><p><label><input type="checkbox" name="enabled" value="1"' . checked( $settings['enabled'], true, false ) . '> ' . esc_html__( 'Accept incoming form messages', 'georg-kloster-connect-hub' ) . '</label></p><p><label>' . esc_html__( 'Keep closed messages for days', 'georg-kloster-connect-hub' ) . '<br><input type="number" name="retention_days" min="30" max="3650" value="' . esc_attr( get_option( 'gkps_retention_days', 365 ) ) . '"></label></p>';
        echo '<h2>' . esc_html__( 'Google reCAPTCHA v3 — spam protection', 'georg-kloster-connect-hub' ) . '</h2><p>' . esc_html__( 'Protect public forms from automated submissions. Enter the site key and secret key from your reCAPTCHA v3 registration. The secret is never displayed after saving.', 'georg-kloster-connect-hub' ) . '</p>';
        if ( $has_site_key && $has_secret_key ) { echo '<div class="notice notice-success inline"><p>' . esc_html__( 'reCAPTCHA is configured. Both keys are saved; the secret key is hidden.', 'georg-kloster-connect-hub' ) . '</p></div>'; }
        elseif ( ! $has_site_key && ! $has_secret_key ) { echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'reCAPTCHA is not configured. Enter both keys and save settings.', 'georg-kloster-connect-hub' ) . '</p></div>'; }
        else { echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'reCAPTCHA setup is incomplete. Both keys are required.', 'georg-kloster-connect-hub' ) . '</p></div>'; }
        echo '<p><label>' . esc_html__( 'reCAPTCHA site key', 'georg-kloster-connect-hub' ) . '<br><input class="regular-text" name="site_key" value="' . esc_attr( $settings['recaptcha_site_key'] ) . '" autocomplete="off"></label></p>';
        echo '<p><label>' . esc_html__( 'reCAPTCHA secret key (leave empty to keep the saved key)', 'georg-kloster-connect-hub' ) . '<br><input class="regular-text" type="password" name="secret_key" value="" autocomplete="new-password" placeholder="' . esc_attr( $has_secret_key ? __( 'Saved; enter a new key only to replace it', 'georg-kloster-connect-hub' ) : __( 'Not saved', 'georg-kloster-connect-hub' ) ) . '"></label> ';
        if ( $has_secret_key ) { echo '<label><input type="checkbox" name="clear_secret" value="1"> ' . esc_html__( 'Delete saved reCAPTCHA secret key', 'georg-kloster-connect-hub' ) . '</label>'; }
        echo '</p><p><label>' . esc_html__( 'Minimum reCAPTCHA score (0.1–0.9)', 'georg-kloster-connect-hub' ) . '<br><input type="number" name="threshold" min="0.1" max="0.9" step="0.1" value="' . esc_attr( $settings['recaptcha_threshold'] ) . '"></label></p>';
        submit_button();
        echo '</form><p>' . esc_html__( 'Each form has its own topics, text, recipient and enabled state. Channel credentials are configured in their respective sections.', 'georg-kloster-connect-hub' ) . '</p>';
        Blocks::help();
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        echo '</div>';
    }
}
