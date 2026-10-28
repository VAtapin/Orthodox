<?php
namespace GeorgKlosterPrihodSocial;
defined( 'ABSPATH' ) || exit;

final class Request_Admin {
    public static function menu(): void { add_submenu_page( 'gkps', __( 'Messages', 'georg-kloster-connect-hub' ), __( 'Messages', 'georg-kloster-connect-hub' ), 'view_gkps_requests', 'gkps-requests', array( self::class, 'render' ) ); }
    private static function authorize( string $cap = 'view_gkps_requests' ): void {
        if ( ! current_user_can( 'view_gkps_requests' ) || ! current_user_can( $cap ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'georg-kloster-connect-hub' ), '', array( 'response' => 403 ) ); }
    }
    public static function render(): void {
        self::authorize();
        echo '<div class="wrap"><h1>' . esc_html__( 'Messages', 'georg-kloster-connect-hub' ) . '</h1>';
        $notice_key = 'gkps_request_notice_' . get_current_user_id();
        if ( $notice = get_transient( $notice_key ) ) { delete_transient( $notice_key ); echo '<p role="status">' . esc_html( $notice ) . '</p>'; }
        $id = isset( $_GET['request_id'] ) && is_scalar( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;
        if ( $id ) {
            try { Requests::assert_request( $id ); } catch ( \Throwable $error ) { echo '<p>' . esc_html__( 'Message not found.', 'georg-kloster-connect-hub' ) . '</p></div>'; return; }
            echo '<p><strong>' . esc_html__( 'Form', 'georg-kloster-connect-hub' ) . ':</strong> ' . esc_html( get_post_meta( $id, '_gkch_form_name', true ) ?: __( 'Default form', 'georg-kloster-connect-hub' ) ) . '</p>';
            $post = get_post( $id ); $sender = get_post_meta( $id, '_gkps_sender', true );
            echo '<h2>' . esc_html( $post->post_title ) . '</h2><p>' . esc_html( ( $sender['name'] ?? '' ) . ' — ' . ( $sender['email'] ?? '' ) ) . '</p><p>' . nl2br( esc_html( $post->post_content ) ) . '</p>';
            do_action( 'gkps_request_telegram_controls', $id );
            foreach ( Requests::messages( $id ) as $message ) {
                $internal = get_post_meta( $message->ID, '_gkps_internal', true );
                echo '<article><h3>' . esc_html( $internal ? __( 'Internal note', 'georg-kloster-connect-hub' ) : __( 'Email reply', 'georg-kloster-connect-hub' ) ) . '</h3><p>' . esc_html( $message->post_date ) . '</p><p>' . nl2br( esc_html( $message->post_content ) ) . '</p>';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both helpers return fixed markup and escape all dynamic values; the image endpoint serves validated private media.
                echo Request_Media::admin_markup( $message->ID );
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The status label and diagnostics helper escape all dynamic content before returning markup.
                if ( ! $internal ) { echo '<p>' . esc_html( ! empty( get_post_meta( $message->ID, '_gkps_mail_transport', true )['short_circuit'] ) && 'sent' === get_post_meta( $message->ID, '_gkps_delivery', true ) ? __( 'Accepted by WordPress; the transport result is unknown.', 'georg-kloster-connect-hub' ) : self::delivery_label( get_post_meta( $message->ID, '_gkps_delivery', true ) ) ) . '</p>' . Request_Mail_Log::render( $message->ID ); }
                if ( ! $internal && 'failed' === get_post_meta( $message->ID, '_gkps_delivery', true ) && current_user_can( 'reply_gkps_requests' ) ) {
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkps_request_update"><input type="hidden" name="request_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="retry_message" value="' . esc_attr( $message->ID ) . '">'; wp_nonce_field( 'gkps_request_update_' . $id ); echo '<button class="button">' . esc_html__( 'Retry this reply after correcting the mail configuration', 'georg-kloster-connect-hub' ) . '</button></form>';
                }
                echo '</article>';
            }
            if ( current_user_can( 'reply_gkps_requests' ) || current_user_can( 'assign_gkps_requests' ) || current_user_can( 'delete_gkps_requests' ) ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="gkps_request_update"><input type="hidden" name="request_id" value="' . esc_attr( $id ) . '">';
                wp_nonce_field( 'gkps_request_update_' . $id );
                if ( current_user_can( 'reply_gkps_requests' ) ) {
                    echo '<p><label>' . esc_html__( 'Message status', 'georg-kloster-connect-hub' ) . ' <select name="status">';
                    foreach ( Requests::statuses() as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( get_post_meta( $id, '_gkps_request_status', true ), $value, false ) . '>' . esc_html( $label ) . '</option>'; }
                    echo '</select></label></p><p><label>' . esc_html__( 'Reply or note', 'georg-kloster-connect-hub' ) . '<br><textarea class="large-text" rows="8" name="body" maxlength="20000"></textarea></label></p><label><input type="checkbox" name="internal" value="1"> ' . esc_html__( 'Internal note (do not send to the visitor)', 'georg-kloster-connect-hub' ) . '</label>';
                }
                if ( current_user_can( 'assign_gkps_requests' ) ) {
                    echo '<p><label>' . esc_html__( 'Assigned staff member', 'georg-kloster-connect-hub' ) . ' <select name="assigned"><option value="0">' . esc_html__( 'Unassigned', 'georg-kloster-connect-hub' ) . '</option>';
                    foreach ( get_users( array( 'capability' => 'view_gkps_requests' ) ) as $user ) { if ( user_can( $user, 'reply_gkps_requests' ) ) { echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( (int) get_post_meta( $id, '_gkps_assigned', true ), $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>'; } }
                    echo '</select></label></p>';
                }
                submit_button( __( 'Save and send reply', 'georg-kloster-connect-hub' ) );
                if ( current_user_can( 'delete_gkps_requests' ) ) { echo '<p><label><input type="checkbox" name="confirm_delete" value="1"> ' . esc_html__( 'I confirm permanent deletion of the message and conversation', 'georg-kloster-connect-hub' ) . '</label> <button type="submit" name="delete" value="1" class="button">' . esc_html__( 'Delete', 'georg-kloster-connect-hub' ) . '</button></p>'; }
                echo '</form>';
            }
        } else {
            $page = max( 1, isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
            $filter = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
            if ( ! isset( Requests::statuses()[$filter] ) ) { $filter = ''; }
            $form_filter = isset( $_GET['form_id'] ) && is_scalar( $_GET['form_id'] ) && preg_match( '/^\d+$/D', (string) $_GET['form_id'] ) ? (int) $_GET['form_id'] : null;
            $can_delete = current_user_can( 'delete_gkps_requests' );

            echo '<form method="get" class="gkps-request-filters"><input type="hidden" name="page" value="gkps-requests"><label for="gkps-status-filter">' . esc_html__( 'Message status', 'georg-kloster-connect-hub' ) . '</label><select id="gkps-status-filter" name="status"><option value="">' . esc_html__( 'All statuses', 'georg-kloster-connect-hub' ) . '</option>';
            foreach ( Requests::statuses() as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $filter, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
            echo '</select><label for="gkps-form-filter">' . esc_html__( 'Form', 'georg-kloster-connect-hub' ) . '</label><select id="gkps-form-filter" name="form_id"><option value="">' . esc_html__( 'All forms', 'georg-kloster-connect-hub' ) . '</option>';
            foreach ( Forms::all() as $form ) { echo '<option value="' . esc_attr( $form['id'] ) . '"' . selected( null !== $form_filter ? (string) $form_filter : '', (string) $form['id'], false ) . '>' . esc_html( $form['name'] ) . '</option>'; }
            echo '</select><button class="button button-primary">' . esc_html__( 'Show', 'georg-kloster-connect-hub' ) . '</button>';
            if ( $filter || null !== $form_filter ) { echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=gkps-requests' ) ) . '">' . esc_html__( 'Reset filter', 'georg-kloster-connect-hub' ) . '</a>'; }
            echo '</form>';

            $args = array( 'post_type' => 'gkps_request', 'post_status' => 'private', 'posts_per_page' => 25, 'paged' => $page, 'orderby' => 'ID', 'order' => 'DESC' );
            if ( isset( Requests::statuses()[$filter] ) ) { $args['meta_key'] = '_gkps_request_status'; $args['meta_value'] = $filter; }
            if ( null !== $form_filter ) {
                $args['meta_query'] = 0 === $form_filter ? array( 'relation' => 'OR', array( 'key' => '_gkch_form_id', 'compare' => 'NOT EXISTS' ), array( 'key' => '_gkch_form_id', 'value' => 0 ) ) : array( array( 'key' => '_gkch_form_id', 'value' => $form_filter ) );
            }
            $query = new \WP_Query( $args );
            if ( $can_delete ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gkps-request-list"><input type="hidden" name="action" value="gkps_request_bulk_delete"><input type="hidden" name="status" value="' . esc_attr( $filter ) . '"><input type="hidden" name="form_id" value="' . esc_attr( null !== $form_filter ? (string) $form_filter : '' ) . '">';
                wp_nonce_field( 'gkps_request_bulk_delete' );
                /* translators: %d: number of messages matching the current filters. */
                echo '<div class="tablenav top"><div class="alignleft actions bulkactions"><label class="screen-reader-text" for="gkps-bulk-action">' . esc_html__( 'Bulk actions', 'georg-kloster-connect-hub' ) . '</label><select id="gkps-bulk-action" name="bulk_action"><option value="">' . esc_html__( 'Bulk actions', 'georg-kloster-connect-hub' ) . '</option><option value="delete">' . esc_html__( 'Delete selected', 'georg-kloster-connect-hub' ) . '</option></select><button type="submit" class="button action">' . esc_html__( 'Apply', 'georg-kloster-connect-hub' ) . '</button><label><input type="checkbox" name="confirm_delete" value="1"> ' . esc_html__( 'Confirm permanent deletion', 'georg-kloster-connect-hub' ) . '</label></div><div class="tablenav-pages"><span class="displaying-num">' . esc_html( sprintf( __( 'Total messages: %d', 'georg-kloster-connect-hub' ), (int) $query->found_posts ) ) . '</span></div></div>';
            } else {
                /* translators: %d: number of messages matching the current filters. */
                echo '<div class="tablenav top"><div class="tablenav-pages"><span class="displaying-num">' . esc_html( sprintf( __( 'Total messages: %d', 'georg-kloster-connect-hub' ), (int) $query->found_posts ) ) . '</span></div></div>';
            }

            echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
            if ( $can_delete ) { echo '<td class="manage-column column-cb check-column"><input type="checkbox" aria-label="' . esc_attr__( 'Select all messages', 'georg-kloster-connect-hub' ) . '"></td>'; }
            foreach ( array( esc_html__( 'Message subject', 'georg-kloster-connect-hub' ), esc_html__( 'Sender', 'georg-kloster-connect-hub' ), esc_html__( 'Form', 'georg-kloster-connect-hub' ), esc_html__( 'Date received', 'georg-kloster-connect-hub' ), esc_html__( 'Message status', 'georg-kloster-connect-hub' ), esc_html__( 'Assigned staff member', 'georg-kloster-connect-hub' ) ) as $heading ) { echo '<th scope="col" class="manage-column">' . $heading . '</th>'; }
            echo '</tr></thead><tbody>';
            if ( ! $query->posts ) {
                $has_previous = false;
                if ( $page > 1 ) { $first_args = $args; $first_args['paged'] = 1; $first_args['posts_per_page'] = 1; $first_args['fields'] = 'ids'; $first_args['no_found_rows'] = true; $has_previous = (bool) ( new \WP_Query( $first_args ) )->posts; }
                $message = $has_previous ? __( 'There are no messages on this page.', 'georg-kloster-connect-hub' ) : ( $filter || null !== $form_filter ? __( 'No messages match the selected filters.', 'georg-kloster-connect-hub' ) : __( 'No messages yet.', 'georg-kloster-connect-hub' ) );
                echo '<tr><td colspan="' . esc_attr( $can_delete ? '7' : '6' ) . '" class="gkps-request-empty"><p role="status">' . esc_html( $message ) . '</p>';
                if ( $has_previous ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'gkps-requests', 'status' => $filter, 'form_id' => null !== $form_filter ? $form_filter : '' ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'First page', 'georg-kloster-connect-hub' ) . '</a>'; }
                echo '</td></tr>';
            }
            foreach ( $query->posts as $post ) {
                $user = get_userdata( (int) get_post_meta( $post->ID, '_gkps_assigned', true ) );
                $sender = get_post_meta( $post->ID, '_gkps_sender', true );
                $status = Requests::statuses()[ get_post_meta( $post->ID, '_gkps_request_status', true ) ] ?? '';
                $form_name = get_post_meta( $post->ID, '_gkch_form_name', true ) ?: __( 'Default form', 'georg-kloster-connect-hub' );
                $date = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post->post_date );
                echo '<tr>';
                if ( $can_delete ) {
                    /* translators: %s: subject of the message being selected. */
                    echo '<th scope="row" class="check-column"><input type="checkbox" name="request_ids[]" value="' . esc_attr( $post->ID ) . '" aria-label="' . esc_attr( sprintf( __( 'Select message: %s', 'georg-kloster-connect-hub' ), $post->post_title ) ) . '"></th>';
                }
                echo '<td class="column-primary"><strong><a class="row-title" href="' . esc_url( add_query_arg( array( 'page' => 'gkps-requests', 'request_id' => $post->ID ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $post->post_title ) . '</a></strong><button type="button" class="toggle-row"><span class="screen-reader-text">' . esc_html__( 'Show more details', 'georg-kloster-connect-hub' ) . '</span></button></td>';
                echo '<td data-colname="' . esc_attr__( 'Sender', 'georg-kloster-connect-hub' ) . '">' . esc_html( is_array( $sender ) ? ( $sender['name'] ?? '' ) : '' ) . ( is_array( $sender ) && ! empty( $sender['email'] ) ? '<br><a href="mailto:' . esc_attr( $sender['email'] ) . '">' . esc_html( $sender['email'] ) . '</a>' : '' ) . '</td>';
                echo '<td data-colname="' . esc_attr__( 'Form', 'georg-kloster-connect-hub' ) . '">' . esc_html( $form_name ) . '</td>';
                echo '<td data-colname="' . esc_attr__( 'Date received', 'georg-kloster-connect-hub' ) . '">' . esc_html( $date ) . '</td>';
                echo '<td data-colname="' . esc_attr__( 'Message status', 'georg-kloster-connect-hub' ) . '">' . esc_html( $status ) . '</td>';
                echo '<td data-colname="' . esc_attr__( 'Assigned staff member', 'georg-kloster-connect-hub' ) . '">' . esc_html( $user ? $user->display_name : '—' ) . '</td></tr>';
            }
            echo '</tbody></table>';
            if ( $can_delete ) { echo '<div class="tablenav bottom"><div class="alignleft actions"><button type="submit" name="bulk_action" value="delete" class="button action">' . esc_html__( 'Delete selected', 'georg-kloster-connect-hub' ) . '</button></div><div class="tablenav-pages">'; }
            else { echo '<div class="tablenav bottom"><div class="tablenav-pages">'; }
            if ( $query->max_num_pages > 1 ) {
                $base_url = add_query_arg( array( 'page' => 'gkps-requests', 'status' => $filter, 'form_id' => null !== $form_filter ? $form_filter : '' ), admin_url( 'admin.php' ) );
                echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', $base_url ), 'format' => '', 'current' => $page, 'total' => (int) $query->max_num_pages ) ) );
            }
            echo '</div></div>';
            if ( $can_delete ) { echo '</form>'; }
        }
        echo '</div>';
    }
    private static function delivery_label( string $status ): string {
        return array( 'pending' => __( 'Sending', 'georg-kloster-connect-hub' ), 'sent' => __( 'Passed to the mail system. Inbox delivery is not confirmed.', 'georg-kloster-connect-hub' ), 'failed' => __( 'Sending error', 'georg-kloster-connect-hub' ) )[ $status ] ?? __( 'Not sent', 'georg-kloster-connect-hub' );
    }
    public static function bulk_delete(): void {
        self::authorize( 'delete_gkps_requests' );
        check_admin_referer( 'gkps_request_bulk_delete' );
        $status = isset( $_POST['status'] ) && is_string( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
        if ( ! isset( Requests::statuses()[$status] ) ) { $status = ''; }
        $form_id = isset( $_POST['form_id'] ) && is_scalar( $_POST['form_id'] ) && preg_match( '/^\d+$/D', (string) $_POST['form_id'] ) ? (int) $_POST['form_id'] : null;
        $redirect = add_query_arg( array( 'page' => 'gkps-requests', 'status' => $status, 'form_id' => null !== $form_id ? $form_id : '' ), admin_url( 'admin.php' ) );
        $deleted = 0;
        try {
            if ( 'delete' !== ( $_POST['bulk_action'] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Choose an action for the selected messages.', 'georg-kloster-connect-hub' ) ); }
            if ( '1' !== ( $_POST['confirm_delete'] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Confirm permanent deletion.', 'georg-kloster-connect-hub' ) ); }
            $ids = isset( $_POST['request_ids'] ) && is_array( $_POST['request_ids'] ) ? array_slice( array_unique( array_filter( array_map( static fn( $id ) => is_scalar( $id ) ? absint( $id ) : 0, wp_unslash( $_POST['request_ids'] ) ) ) ), 0, 100 ) : array();
            if ( ! $ids ) { throw new \InvalidArgumentException( __( 'Select at least one message to delete.', 'georg-kloster-connect-hub' ) ); }
            foreach ( $ids as $id ) { Requests::assert_request( $id ); }
            foreach ( $ids as $id ) { Requests::delete( $id ); ++$deleted; }
            /* translators: %d: number of messages deleted. */
            $notice = sprintf( __( 'Deleted messages: %d.', 'georg-kloster-connect-hub' ), $deleted );
        } catch ( \Throwable $error ) {
            $notice = $error instanceof \InvalidArgumentException ? $error->getMessage() : __( 'Messages could not be deleted. Please try again.', 'georg-kloster-connect-hub' );
        }
        set_transient( 'gkps_request_notice_' . get_current_user_id(), $notice, 60 );
        wp_safe_redirect( $redirect );
        exit;
    }

    public static function update(): void {
        self::authorize(); $id = absint( $_POST['request_id'] ?? 0 );
        check_admin_referer( 'gkps_request_update_' . $id );
        try {
            Requests::assert_request( $id );
            if ( ! empty( $_POST['delete'] ) ) {
                self::authorize( 'delete_gkps_requests' );
                if ( '1' !== ( $_POST['confirm_delete'] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Confirm deletion.', 'georg-kloster-connect-hub' ) ); }
                Requests::delete( $id ); $id = 0;
            } else {
                if ( isset( $_POST['retry_message'] ) ) { self::authorize( 'reply_gkps_requests' ); Requests::retry( $id, is_scalar( $_POST['retry_message'] ) ? absint( $_POST['retry_message'] ) : 0 ); }
                if ( isset( $_POST['assigned'] ) ) {
                    self::authorize( 'assign_gkps_requests' );
                    $assigned = is_scalar( $_POST['assigned'] ) ? absint( $_POST['assigned'] ) : -1;
                    if ( $assigned && ( ! user_can( $assigned, 'view_gkps_requests' ) || ! user_can( $assigned, 'reply_gkps_requests' ) ) ) { throw new \InvalidArgumentException( __( 'The assigned staff member does not have the required permissions.', 'georg-kloster-connect-hub' ) ); }
                    update_post_meta( $id, '_gkps_assigned', $assigned );
                }
                if ( isset( $_POST['status'] ) ) {
                    self::authorize( 'reply_gkps_requests' );
                    if ( ! is_string( $_POST['status'] ) || ! isset( Requests::statuses()[ $_POST['status'] ] ) ) { throw new \InvalidArgumentException( __( 'Invalid status.', 'georg-kloster-connect-hub' ) ); }
                    update_post_meta( $id, '_gkps_request_status', $_POST['status'] );
                }
                if ( ! empty( $_POST['body'] ) ) {
                    self::authorize( 'reply_gkps_requests' );
                    if ( ! is_string( $_POST['body'] ) ) { throw new \InvalidArgumentException( __( 'Invalid reply.', 'georg-kloster-connect-hub' ) ); }
                    Requests::message( $id, wp_unslash( $_POST['body'] ), ! empty( $_POST['internal'] ) );
                }
                wp_update_post( array( 'ID' => $id ) );
            }
            $notice = __( 'Changes saved.', 'georg-kloster-connect-hub' );
        } catch ( \Throwable $error ) { $notice = __( 'Some changes could not be applied. Check permissions and fields.', 'georg-kloster-connect-hub' ); }
        set_transient( 'gkps_request_notice_' . get_current_user_id(), $notice, 60 );
        wp_safe_redirect( add_query_arg( array( 'page' => 'gkps-requests', 'request_id' => $id ), admin_url( 'admin.php' ) ) ); exit;
    }
}
