<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

/** Form definitions are separate from received private correspondence. */
final class Forms {
    public static function register(): void {
        register_post_type( 'gkch_form', array( 'public' => false, 'show_ui' => false, 'show_in_rest' => false, 'rewrite' => false, 'supports' => array(), 'map_meta_cap' => false, 'capabilities' => array( 'create_posts' => 'manage_gkps_settings', 'edit_posts' => 'manage_gkps_settings' ) ) );
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
                if ( '' !== $value && ! is_email( $value ) ) { throw new \InvalidArgumentException( __( 'Enter a valid notification email.', 'georg-kloster-connect-hub' ) ); }
                $value = sanitize_email( $value );
            } else { $value = in_array( $key, array( 'intro', 'topics' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ); }
            $values[$key] = $value;
        }
        return $values;
    }

    public static function store( int $id, array $input ): int {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { throw new \RuntimeException( __( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        if ( $id && ! self::get( $id ) ) { throw new \InvalidArgumentException( __( 'Form not found.', 'georg-kloster-connect-hub' ) ); }
        $name = isset( $input['name'] ) && is_string( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
        if ( '' === $name ) { throw new \InvalidArgumentException( __( 'Enter a form name.', 'georg-kloster-connect-hub' ) ); }
        $settings = self::sanitize( $input );
        $saved = wp_insert_post( wp_slash( array( 'ID' => $id, 'post_type' => 'gkch_form', 'post_status' => 'private', 'post_title' => $name ) ), true );
        if ( is_wp_error( $saved ) ) { throw new \RuntimeException( __( 'Form could not be saved.', 'georg-kloster-connect-hub' ) ); }
        update_post_meta( $saved, '_gkch_form', $settings );
        return (int) $saved;
    }

    public static function save(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        check_admin_referer( 'gkch_form_save' );
        $id = isset( $_POST['form_id'] ) && is_scalar( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
        try {
            $input = wp_unslash( $_POST );
            if ( isset( $_POST['default_form'] ) && '1' === $_POST['default_form'] ) { update_option( 'gkps_form', self::sanitize( $input ) ); }
            else { $id = self::store( $id, $input ); }
        } catch ( \Throwable $error ) {
            set_transient( 'gkch_form_error_' . get_current_user_id(), $error->getMessage(), 120 );
        }
        wp_safe_redirect( add_query_arg( array( 'page' => 'gkps', 'form_id' => $id ), admin_url( 'admin.php' ) ) ); exit;
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_gkps_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'georg-kloster-connect-hub' ) ); }
        echo '<div class="wrap"><h1>' . esc_html__( 'Forms', 'georg-kloster-connect-hub' ) . '</h1>';
        $error_key = 'gkch_form_error_' . get_current_user_id();
        if ( $error = get_transient( $error_key ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; delete_transient( $error_key ); }
        echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=gkps&new_form=1' ) ) . '">' . esc_html__( 'Add form', 'georg-kloster-connect-hub' ) . '</a></p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Form name', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Status', 'georg-kloster-connect-hub' ) . '</th><th>' . esc_html__( 'Shortcode', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody>';
        foreach ( self::all() as $form ) {
            echo '<tr><td><a href="' . esc_url( add_query_arg( array( 'page' => 'gkps', 'form_id' => $form['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $form['name'] ) . '</a></td><td>' . esc_html( $form['enabled'] ? __( 'Enabled', 'georg-kloster-connect-hub' ) : __( 'Disabled', 'georg-kloster-connect-hub' ) ) . '</td><td><code>[gkch_form id="' . esc_attr( $form['id'] ) . '"]</code></td></tr>';
        }
        echo '</tbody></table>';
        $new = ! empty( $_GET['new_form'] );
        $id = isset( $_GET['form_id'] ) && is_scalar( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
        if ( $new ) { $id = 0; }
        $form = $new ? array_merge( Admin::defaults(), array( 'id' => 0, 'name' => '' ) ) : self::get( $id );
        if ( ! $form ) { echo '<p>' . esc_html__( 'Form not found.', 'georg-kloster-connect-hub' ) . '</p></div>'; return; }
        echo '<h2>' . esc_html( $new ? __( 'Add form', 'georg-kloster-connect-hub' ) : $form['name'] ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_form_save"><input type="hidden" name="form_id" value="' . esc_attr( $id ) . '">';
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
        submit_button(); echo '</form></div>';
    }
}
