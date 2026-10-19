<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

/** Rule editor, separate from scheduling and the publication transport history. */
final class Publication_Rules_Admin {
    public static function assets(): void {
        if ( ! Publication_Rules::can_manage() || ! isset( $_GET['page'] ) || 'gkch-publications' !== $_GET['page'] ) { return; }
        wp_enqueue_media();
        wp_enqueue_script( 'gkch-publications', plugins_url( 'assets/publications.js', GKPS_FILE ), array( 'media-editor' ), GKPS_VERSION, true );
        wp_localize_script( 'gkch-publications', 'gkchPublicationsMedia', array( 'title' => __( 'Choose a JPEG image', 'georg-kloster-connect-hub' ), 'button' => __( 'Use this image', 'georg-kloster-connect-hub' ) ) );
    }
    public static function save(): void {
        check_admin_referer( 'gkch_rule_save' );
        try {
            $input = wp_unslash( $_POST );
            $id = isset( $input['rule_id'] ) && is_scalar( $input['rule_id'] ) ? absint( $input['rule_id'] ) : 0;
            Publication_Rules::store( $id, $input );
            $notice = __( 'Publication rule saved.', 'georg-kloster-connect-hub' );
        } catch ( \Throwable $error ) { $notice = $error->getMessage(); }
        self::redirect( $notice );
    }

    public static function delete(): void {
        check_admin_referer( 'gkch_rule_delete' );
        try {
            $id = isset( $_POST['rule_id'] ) && is_scalar( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
            Publication_Rules::remove( $id ); $notice = __( 'Publication rule removed. History was retained.', 'georg-kloster-connect-hub' );
        } catch ( \Throwable $error ) { $notice = $error->getMessage(); }
        self::redirect( $notice );
    }

    private static function redirect( string $notice ): void {
        set_transient( 'gkch_publication_notice_' . get_current_user_id(), $notice, 120 );
        wp_safe_redirect( admin_url( 'admin.php?page=gkch-publications' ) ); exit;
    }

    private static function types(): array { return array( 'new_posts' => __( 'New WordPress posts', 'georg-kloster-connect-hub' ), 'message' => __( 'Scheduled messages and reminders', 'georg-kloster-connect-hub' ) ); }
    private static function frequencies(): array { return array( 'once' => __( 'Once', 'georg-kloster-connect-hub' ), 'daily' => __( 'Every day', 'georg-kloster-connect-hub' ), 'weekly' => __( 'Every week', 'georg-kloster-connect-hub' ) ); }

    public static function render(): void {
        echo '<p>' . esc_html__( 'Set what to send, when and to which channels. Channel connections alone do not enable automatic publications.', 'georg-kloster-connect-hub' ) . '</p>';
        if ( ! Publication_Rules::can_manage() ) { echo '<p>' . esc_html__( 'Ask a communication settings administrator to manage automatic publication rules.', 'georg-kloster-connect-hub' ) . '</p>'; return; }
        echo '<h2>' . esc_html__( 'Automatic publication rules', 'georg-kloster-connect-hub' ) . '</h2><table class="widefat striped"><thead><tr>';
        foreach ( array( __( 'Rule', 'georg-kloster-connect-hub' ), __( 'What to send', 'georg-kloster-connect-hub' ), __( 'Channels', 'georg-kloster-connect-hub' ), __( 'Schedule and status', 'georg-kloster-connect-hub' ) ) as $heading ) { echo '<th scope="col">' . esc_html( $heading ) . '</th>'; }
        echo '</tr></thead><tbody>';
        $rules = Publication_Rules::all();
        foreach ( $rules as $rule ) {
            $url = add_query_arg( array( 'page' => 'gkch-publications', 'rule' => $rule['id'] ), admin_url( 'admin.php' ) );
            echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $rule['name'] ) . '</a></td><td>' . esc_html( self::types()[$rule['type']] ) . '</td><td>' . esc_html( implode( ', ', array_intersect_key( Publications::titles(), array_flip( $rule['channels'] ) ) ) ) . '</td><td>';
            echo esc_html( $rule['enabled'] ? __( 'Enabled', 'georg-kloster-connect-hub' ) : __( 'Paused', 'georg-kloster-connect-hub' ) );
            if ( 'message' === $rule['type'] ) {
                echo '<br>' . esc_html( self::frequencies()[$rule['frequency']] . ' · ' . $rule['time'] );
                echo '<br>' . esc_html( $rule['next'] ? __( 'Next:', 'georg-kloster-connect-hub' ) . ' ' . wp_date( 'Y-m-d H:i', $rule['next'] ) : __( 'No scheduled delivery', 'georg-kloster-connect-hub' ) );
            }
            if ( $error = get_post_meta( $rule['id'], '_gkch_rule_error', true ) ) { echo '<p role="status">' . esc_html( $error ) . '</p>'; }
            echo '</td></tr>';
        }
        if ( ! $rules ) { echo '<tr><td colspan="4">' . esc_html__( 'No rules yet. Add a rule below; automatic sending is off by default.', 'georg-kloster-connect-hub' ) . '</td></tr>'; }
        echo '</tbody></table><h2>' . esc_html__( 'Add or edit a rule', 'georg-kloster-connect-hub' ) . '</h2>';
        $id = isset( $_GET['rule'] ) && is_scalar( $_GET['rule'] ) ? absint( $_GET['rule'] ) : 0;
        $rule = $id ? Publication_Rules::get( $id ) : Publication_Rules::defaults();
        if ( ! $rule ) { echo '<p>' . esc_html__( 'Invalid publication rule.', 'georg-kloster-connect-hub' ) . '</p>'; return; }
        if ( ! $id && isset( $_GET['rule_type'] ) && 'message' === $_GET['rule_type'] ) { $rule['type'] = 'message'; }
        echo '<p>';
        foreach ( self::types() as $type => $label ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'gkch-publications', 'rule_type' => $type ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a> '; }
        echo '</p><h3>' . esc_html( self::types()[$rule['type']] ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_rule_save"><input type="hidden" name="rule_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="type" value="' . esc_attr( $rule['type'] ) . '">';
        wp_nonce_field( 'gkch_rule_save' );
        echo '<p><label>' . esc_html__( 'Rule name', 'georg-kloster-connect-hub' ) . '<br><input class="regular-text" name="name" maxlength="150" required value="' . esc_attr( $rule['name'] ) . '"></label></p>';
        echo '<p><label><input type="checkbox" name="enabled" value="1"' . checked( $rule['enabled'], true, false ) . '> ' . esc_html__( 'Enable automatic sending for this rule', 'georg-kloster-connect-hub' ) . '</label></p><fieldset><legend>' . esc_html__( 'Send to channels', 'georg-kloster-connect-hub' ) . '</legend>';
        foreach ( Publications::titles() as $channel => $title ) {
            $connector = Connector::instance( $channel ); $ready = $connector && $connector->valid( $connector->settings() );
            echo '<p><label><input type="checkbox" name="channels[]" value="' . esc_attr( $channel ) . '"' . checked( in_array( $channel, $rule['channels'], true ), true, false ) . '> ' . esc_html( $title ) . '</label> ';
            if ( ! $ready ) { echo '<a href="' . esc_url( $connector->settings_url() ) . '">' . esc_html__( 'Configure channel', 'georg-kloster-connect-hub' ) . '</a>'; }
            echo '</p>';
        }
        echo '</fieldset>';
        if ( 'new_posts' === $rule['type'] ) {
            echo '<p>' . esc_html__( 'Send the title, excerpt and link when a post is first published, including scheduled WordPress posts. Existing posts and later edits are not sent.', 'georg-kloster-connect-hub' ) . '</p><p><label for="gkch-rule-category">' . esc_html__( 'Category', 'georg-kloster-connect-hub' ) . '</label><br>';
            wp_dropdown_categories( array( 'name' => 'category', 'id' => 'gkch-rule-category', 'show_option_all' => __( 'All categories', 'georg-kloster-connect-hub' ), 'hide_empty' => false, 'selected' => $rule['category'], 'hierarchical' => true ) );
            echo '</p><p>' . esc_html__( 'For Instagram, each new post must have a public HTTPS JPEG featured image.', 'georg-kloster-connect-hub' ) . '</p>';
        } else {
            echo '<p><label>' . esc_html__( 'Message or reminder text', 'georg-kloster-connect-hub' ) . '<br><textarea class="large-text" name="text" rows="4" maxlength="900" required>' . esc_textarea( $rule['text'] ) . '</textarea></label></p>';
            echo '<p><label>' . esc_html__( 'Link (optional)', 'georg-kloster-connect-hub' ) . '<br><input class="large-text" type="url" name="url" value="' . esc_attr( $rule['url'] ) . '"></label></p>';
            echo '<p>' . esc_html__( 'Image (required for Instagram; public HTTPS JPEG)', 'georg-kloster-connect-hub' ) . '<br><input type="hidden" name="image" id="gkch-rule-image" value="' . esc_attr( $rule['image'] ) . '"><button type="button" class="button" id="gkch-rule-choose-image">' . esc_html__( 'Choose a JPEG image', 'georg-kloster-connect-hub' ) . '</button> <button type="button" class="button" id="gkch-rule-clear-image">' . esc_html__( 'Remove image', 'georg-kloster-connect-hub' ) . '</button> <span id="gkch-rule-image-name" aria-live="polite">' . esc_html( $rule['image'] ? get_the_title( $rule['image'] ) : '' ) . '</span></p><p><label>' . esc_html__( 'Repeat', 'georg-kloster-connect-hub' ) . ' <select name="frequency" id="gkch-rule-frequency">';
            foreach ( self::frequencies() as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $rule['frequency'], $value, false ) . '>' . esc_html( $label ) . '</option>'; }
            echo '</select></label></p><p><label>' . esc_html__( 'Time', 'georg-kloster-connect-hub' ) . ' <input type="time" name="time" required value="' . esc_attr( $rule['time'] ) . '"></label></p><p data-gkch-frequency="once"><label>' . esc_html__( 'Date (for a one-time reminder)', 'georg-kloster-connect-hub' ) . ' <input type="date" name="date" value="' . esc_attr( $rule['date'] ) . '"></label></p><p data-gkch-frequency="weekly"><label>' . esc_html__( 'Day (for weekly messages)', 'georg-kloster-connect-hub' ) . ' <select name="weekday">';
            global $wp_locale;
            for ( $day = 0; $day < 7; ++$day ) { echo '<option value="' . esc_attr( $day ) . '"' . selected( $rule['weekday'], $day, false ) . '>' . esc_html( $wp_locale->get_weekday( $day ) ) . '</option>'; }
            echo '</select></label></p>';
        }
        submit_button( __( 'Save rule', 'georg-kloster-connect-hub' ) ); echo '</form>';
        if ( $id ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkch_rule_delete"><input type="hidden" name="rule_id" value="' . esc_attr( $id ) . '">'; wp_nonce_field( 'gkch_rule_delete' );
            submit_button( __( 'Remove rule', 'georg-kloster-connect-hub' ), 'delete' ); echo '</form>';
        }
        echo '<p>' . esc_html__( 'Timezone:', 'georg-kloster-connect-hub' ) . ' ' . esc_html( wp_timezone_string() ) . '. ' . esc_html__( 'WordPress Cron runs on site visits or the server scheduler. Late runs send once, without repeating every missed interval. Editing or pausing a rule cancels its pending deliveries.', 'georg-kloster-connect-hub' ) . '</p>';
    }
}
