<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

/** Form definitions are separate from received private correspondence. */
final class Forms {
    private const EDIT_PAGE = 'gkps-form-edit';

    public static function register(): void {
        register_post_type( 'gkch_form', array( 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'supports' => array(), 'map_meta_cap' => false, 'capabilities' => array( 'create_posts' => 'manage_gkps_settings', 'edit_posts' => 'manage_gkps_settings' ) ) );
        add_action( 'admin_menu', array( self::class, 'editor_page' ), 20 );
    }

    public static function get( int $id = 0 ): ?array {
        if ( 0 === $id ) { return array_merge( Admin::get(), array( 'id' => 0, 'name' => __( 'Default form', 'georg-kloster-connect-hub' ) ) ); }
        $post = get_post( $id );
        if ( ! $post || 'gkch_form' !== $post->post_type || 'private' !== $post->post_status ) { return null; }
        $settings = get_post_meta( $id, '_gkch_form', true );
        return array_merge( Admin::defaults(), is_array( $settings ) ? $settings : array(), array( 'id' => $id, 'name' => $post->post_title ) );
    }

    public static function all(): array {
        $forms = array( self::get() );
        foreach ( get_posts( array( 'post_type' => 'gkch_form', 'post_status' => 'private', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) { $forms[] = self::get( (int) $post->ID ); }
        return $forms;
    }

    public static function active( int $id ): bool {
        $form = self::get( $id );
        return Hub_Settings::get()['enabled'] && $form && ! empty( $form['enabled'] );
    }

    public static function sanitize( array $input ): array {
        $values = array( 'enabled' => ! empty( $input['enabled'] ) );
        foreach ( array( 'title', 'intro', 'submit', 'topics', 'recipient' ) as $key ) {
            $value = isset( $input[$key] ) && is_string( $input[$key] ) ? trim( $input[$key] ) : '';
            if ( 'recipient' === $key ) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
                if ( '' !== $value && ! is_email( $value ) ) { throw new \InvalidArgumentException( __( 'Enter a valid notification email.', 'georg-kloster-connect-hub' ) ); }
                $value = sanitize_email( $value );
            } else { $value = in_array( $key, array( 'intro', 'topics' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ); }
            $values[$key] = $value;
        }
        $chat_id = isset( $input['telegram_chat_id'] ) && is_string( $input['telegram_chat_id'] ) ? trim( $input['telegram_chat_id'] ) : '';
        if ( '' !== $chat_id && ! preg_match( '/^[1-9][0-9]*$/D', $chat_id ) ) { throw new \InvalidArgumentException( __( 'Enter a positive numeric Telegram Chat ID for this form.', 'georg-kloster-connect-hub' ) ); }
        $staff = isset( $input['telegram_staff'] ) && is_scalar( $input['telegram_staff'] ) ? absint( $input['telegram_staff'] ) : 0;
        if ( $staff && ( ! user_can( $staff, 'view_gkps_requests' ) || ! user_can( $staff, 'reply_gkps_requests' ) ) ) { throw new \InvalidArgumentException( __( 'Choose a staff member who can view and reply to messages.', 'georg-kloster-connect-hub' ) ); }
        $values['telegram_chat_id'] = $chat_id;
        $values['telegram_staff'] = $staff;
        return $values;
    }

    public static function telegram_target( int $form_id, string $fallback ): string {
        $form = self::get( $form_id );
        $target = is_array( $form ) ? (string) ( $form['telegram_chat_id'] ?? '' ) : '';
        return '' !== $target ? $target : $fallback;
    }

    public static function telegram_staff( int $form_id, int $fallback ): int {
        $form = self::get( $form_id );
        $staff = is_array( $form ) ? absint( $form['telegram_staff'] ?? 0 ) : 0;
        return $staff ?: $fallback;
    }

    public static function store( int $id, array $input ): int {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { throw new \RuntimeException( __( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( $id && ! self::get( $id ) ) { throw new \InvalidArgumentException( __( 'Form not found.', 'georg-kloster-connect-hub' ) ); }
        $name = isset( $input['name'] ) && is_string( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( '' === $name ) { throw new \InvalidArgumentException( __( 'Enter a form name.', 'georg-kloster-connect-hub' ) ); }
        $settings = self::sanitize( $input );
        $saved = wp_insert_post( wp_slash( array( 'ID' => $id, 'post_type' => 'gkch_form', 'post_status' => 'private', 'post_title' => $name ) ), true );
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is data and is escaped by the UI before display.
        if ( is_wp_error( $saved ) ) { throw new \RuntimeException( __( 'Form could not be saved.', 'georg-kloster-connect-hub' ) ); }
        update_post_meta( $saved, '_gkch_form', $settings );
        return (int) $saved;
    }

    public static function save(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        check_admin_referer( 'gkch_form_save' );
        $id = isset( $_POST['form_id'] ) && is_scalar( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        $adding = isset( $_POST['form_action'] ) && 'add' === sanitize_key( wp_unslash( $_POST['form_action'] ) );
        $saved = false;
        try {
            $input = wp_unslash( $_POST );
            if ( isset( $_POST['default_form'] ) && '1' === $_POST['default_form'] ) { update_option( 'gkps_form', self::sanitize( $input ) ); }
            else { $id = self::store( $id, $input ); }
            $saved = true;
        } catch ( \Throwable $error ) {
            set_transient( 'gkch_form_error_' . get_current_user_id(), $error->getMessage(), 120 );
        }
        $args = array( 'page' => self::EDIT_PAGE );
        if ( $adding && ! $saved ) { $args['action'] = 'add'; }
        else { $args['form_id'] = $id; }
        if ( $saved ) { $args['updated'] = '1'; }
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Forms', 'georg-kloster-connect-hub' ) . '</h1> <a class="page-title-action" href="' . esc_url( self::edit_url( 0, true ) ) . '">' . esc_html__( 'Add form', 'georg-kloster-connect-hub' ) . '</a><hr class="wp-header-end">';
        $error_key = 'gkch_form_error_' . get_current_user_id();
        if ( $error = get_transient( $error_key ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; delete_transient( $error_key ); }
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th scope="col">' . esc_html__( 'Form name', 'georg-kloster-connect-hub' ) . '</th><th scope="col">' . esc_html__( 'Status', 'georg-kloster-connect-hub' ) . '</th><th scope="col">' . esc_html__( 'Shortcode', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody>';
        $forms = self::all();
        foreach ( $forms as $form ) {
            $edit = self::edit_url( (int) $form['id'] );
            echo '<tr><td><strong><a href="' . esc_url( $edit ) . '">' . esc_html( $form['name'] ) . '</a></strong><div class="row-actions"><span class="edit"><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit', 'georg-kloster-connect-hub' ) . '</a></span></div></td><td>' . esc_html( $form['enabled'] ? __( 'Enabled', 'georg-kloster-connect-hub' ) : __( 'Disabled', 'georg-kloster-connect-hub' ) ) . '</td><td><code>[gkch_form id="' . esc_attr( $form['id'] ) . '"]</code></td></tr>';
        }
        if ( ! $forms ) { echo '<tr class="no-items"><td colspan="3">' . esc_html__( 'No forms found.', 'georg-kloster-connect-hub' ) . '</td></tr>'; }
        echo '</tbody></table>';
        Blocks::help();
        echo '</div>';
    }

    public static function edit_link( int $id ): string { return self::edit_url( $id ); }

    private static function edit_url( int $id, bool $new = false ): string {
        $args = array( 'page' => self::EDIT_PAGE );
        if ( $new ) { $args['action'] = 'add'; } else { $args['form_id'] = $id; }
        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    public static function editor_page(): void {
        add_submenu_page( null, __( 'Edit form', 'georg-kloster-connect-hub' ), __( 'Edit form', 'georg-kloster-connect-hub' ), 'manage_gkps_settings', self::EDIT_PAGE, array( self::class, 'render_editor' ) );
    }

    public static function render_editor(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        $new = 'add' === ( isset( $_GET['action'] ) && is_scalar( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '' );
        $id = isset( $_GET['form_id'] ) && is_scalar( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        $form = $new ? array_merge( Admin::defaults(), array( 'id' => 0, 'name' => '' ) ) : self::get( $id );
        echo '<div class="wrap"><h1>' . esc_html( $new ? __( 'Add form', 'georg-kloster-connect-hub' ) : __( 'Edit form', 'georg-kloster-connect-hub' ) ) . '</h1><hr class="wp-header-end">';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=gkps' ) ) . '">← ' . esc_html__( 'Back to forms', 'georg-kloster-connect-hub' ) . '</a></p>';
        $error_key = 'gkch_form_error_' . get_current_user_id();
        if ( $error = get_transient( $error_key ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; delete_transient( $error_key ); }
        if ( ! empty( $_GET['updated'] ) ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Form saved.', 'georg-kloster-connect-hub' ) . '</p></div>'; }
        if ( ! $form ) { echo '<p>' . esc_html__( 'Form not found.', 'georg-kloster-connect-hub' ) . '</p></div>'; return; }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_form_save"><input type="hidden" name="form_id" value="' . esc_attr( $id ) . '">';
        if ( $new ) { echo '<input type="hidden" name="form_action" value="add">'; }
        wp_nonce_field( 'gkch_form_save' );
        if ( ! $new && 0 === $id ) { echo '<input type="hidden" name="default_form" value="1">'; }
        else { echo '<p><label>' . esc_html__( 'Form name', 'georg-kloster-connect-hub' ) . '<br><input class="regular-text" name="name" required value="' . esc_attr( $form['name'] ) . '"></label></p>'; }
        echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked( $form['enabled'], true, false ) . '> ' . esc_html__( 'Accept messages', 'georg-kloster-connect-hub' ) . '</label></p>';
        foreach ( array( 'title' => __( 'Form title', 'georg-kloster-connect-hub' ), 'intro' => __( 'Introduction', 'georg-kloster-connect-hub' ), 'submit' => __( 'Button label', 'georg-kloster-connect-hub' ), 'topics' => __( 'Suggested topics (one per line)', 'georg-kloster-connect-hub' ), 'recipient' => __( 'Notification email', 'georg-kloster-connect-hub' ) ) as $key => $label ) {
            echo '<p><label>' . esc_html( $label ) . '<br>';
            if ( in_array( $key, array( 'intro', 'topics' ), true ) ) { echo '<textarea class="large-text" rows="4" name="' . esc_attr( $key ) . '">' . esc_textarea( $form[$key] ) . '</textarea>'; }
            else { echo '<input class="regular-text" type="' . ( 'recipient' === $key ? 'email' : 'text' ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $form[$key] ) . '">'; }
            echo '</label></p>';
        }
        echo '<hr><h3>' . esc_html__( 'Telegram chat and responsible person for this form', 'georg-kloster-connect-hub' ) . '</h3><p><label>' . esc_html__( 'Private numeric Telegram Chat ID (leave empty to disable Telegram notifications for this form)', 'georg-kloster-connect-hub' ) . '<br><input class="regular-text" inputmode="numeric" pattern="[0-9]+" name="telegram_chat_id" value="' . esc_attr( $form['telegram_chat_id'] ?? '' ) . '"></label></p>';
        echo '<p>' . esc_html__( 'To find this form’s Chat ID, the responsible person opens your bot and presses Start, then opens', 'georg-kloster-connect-hub' ) . ' <a href="https://t.me/userinfobot" target="_blank" rel="noopener noreferrer">@userinfobot</a> ' . esc_html__( 'and copies their numeric ID. They must start your bot first or it cannot message them. Enter a separate private Chat ID for each form. Groups and public channels do not support private replies. Find the bot token and connection instructions under', 'georg-kloster-connect-hub' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=gkch-settings&network=telegram' ) ) . '">Connect Hub → Settings → Telegram</a>; ' . esc_html__( 'create a bot with', 'georg-kloster-connect-hub' ) . ' <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a>.</p>';
        echo '<p><label>' . esc_html__( 'Save Telegram replies on behalf of this staff member (empty uses the shared staff setting)', 'georg-kloster-connect-hub' ) . ' <select name="telegram_staff"><option value="0">' . esc_html__( 'Shared staff member from Telegram settings', 'georg-kloster-connect-hub' ) . '</option>';
        foreach ( get_users( array( 'capability' => 'view_gkps_requests' ) ) as $user ) {
            if ( user_can( $user, 'reply_gkps_requests' ) ) { echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( (int) ( $form['telegram_staff'] ?? 0 ), $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>'; }
        }
        echo '</select></label></p><p>' . esc_html__( 'Each form sends contact messages to its own chat; replies are recorded under the selected staff member. The bot token and webhook are shared. Enable Telegram in Settings, and enable complete-message replies there if staff need to answer in Telegram.', 'georg-kloster-connect-hub' ) . '</p>';
        submit_button(); echo '</form>';
        echo '</div>';
    }
}
