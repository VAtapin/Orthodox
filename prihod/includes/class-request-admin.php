<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Request_Admin {
    public static function menu(): void { add_submenu_page( 'prihod', __( 'Обращения', 'prihod' ), __( 'Обращения', 'prihod' ), 'view_prihod_requests', 'prihod-requests', array( self::class, 'render' ) ); }
    private static function authorize( string $cap = 'view_prihod_requests' ): void {
        if ( ! Modules::enabled( 'requests' ) || ! current_user_can( 'view_prihod_requests' ) || ! current_user_can( $cap ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); }
    }
    public static function render(): void {
        self::authorize();
        echo '<div class="wrap"><h1>' . esc_html__( 'Обращения к священнику', 'prihod' ) . '</h1>';
        $notice_key = 'prihod_request_notice_' . get_current_user_id();
        if ( $notice = get_transient( $notice_key ) ) { delete_transient( $notice_key ); echo '<p role="status">' . esc_html( $notice ) . '</p>'; }
        $id = isset( $_GET['request_id'] ) && is_scalar( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;
        if ( $id ) {
            try { Requests::assert_request( $id ); } catch ( \Throwable $error ) { echo '<p>' . esc_html__( 'Обращение не найдено.', 'prihod' ) . '</p></div>'; return; }
            $post = get_post( $id ); $sender = get_post_meta( $id, '_prihod_sender', true );
            echo '<h2>' . esc_html( $post->post_title ) . '</h2><p>' . esc_html( ( $sender['name'] ?? '' ) . ' — ' . ( $sender['email'] ?? '' ) ) . '</p><p>' . nl2br( esc_html( $post->post_content ) ) . '</p>';
            do_action( 'prihod_request_telegram_controls', $id );
            foreach ( Requests::messages( $id ) as $message ) {
                $internal = get_post_meta( $message->ID, '_prihod_internal', true );
                echo '<article><h3>' . esc_html( $internal ? __( 'Внутренняя заметка', 'prihod' ) : __( 'Ответ по email', 'prihod' ) ) . '</h3><p>' . esc_html( $message->post_date ) . '</p><p>' . nl2br( esc_html( $message->post_content ) ) . '</p>';
                echo Request_Media::admin_markup( $message->ID );
                if ( ! $internal ) { echo '<p>' . esc_html( self::delivery_label( get_post_meta( $message->ID, '_prihod_delivery', true ) ) ) . '</p>'; }
                if ( ! $internal && 'failed' === get_post_meta( $message->ID, '_prihod_delivery', true ) && current_user_can( 'reply_prihod_requests' ) ) {
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_request_update"><input type="hidden" name="request_id" value="' . esc_attr( $id ) . '"><input type="hidden" name="retry_message" value="' . esc_attr( $message->ID ) . '">'; wp_nonce_field( 'prihod_request_update_' . $id ); echo '<button class="button">' . esc_html__( 'Повторить этот ответ после исправления почты', 'prihod' ) . '</button></form>';
                }
                echo '</article>';
            }
            if ( current_user_can( 'reply_prihod_requests' ) || current_user_can( 'assign_prihod_requests' ) || current_user_can( 'delete_prihod_requests' ) ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_request_update"><input type="hidden" name="request_id" value="' . esc_attr( $id ) . '">';
                wp_nonce_field( 'prihod_request_update_' . $id );
                if ( current_user_can( 'reply_prihod_requests' ) ) {
                    echo '<p><label>' . esc_html__( 'Статус', 'prihod' ) . ' <select name="status">';
                    foreach ( Requests::statuses() as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( get_post_meta( $id, '_prihod_request_status', true ), $value, false ) . '>' . esc_html( $label ) . '</option>'; }
                    echo '</select></label></p><p><label>' . esc_html__( 'Ответ или заметка', 'prihod' ) . '<br><textarea class="large-text" rows="8" name="body" maxlength="20000"></textarea></label></p><label><input type="checkbox" name="internal" value="1"> ' . esc_html__( 'Внутренняя заметка (не отправлять посетителю)', 'prihod' ) . '</label>';
                }
                if ( current_user_can( 'assign_prihod_requests' ) ) {
                    echo '<p><label>' . esc_html__( 'Ответственный', 'prihod' ) . ' <select name="assigned"><option value="0">' . esc_html__( 'Не назначен', 'prihod' ) . '</option>';
                    foreach ( get_users( array( 'capability' => 'view_prihod_requests' ) ) as $user ) { if ( user_can( $user, 'reply_prihod_requests' ) ) { echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( (int) get_post_meta( $id, '_prihod_assigned', true ), $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>'; } }
                    echo '</select></label></p>';
                }
                submit_button( __( 'Сохранить и отправить ответ', 'prihod' ) );
                if ( current_user_can( 'delete_prihod_requests' ) ) { echo '<p><label><input type="checkbox" name="confirm_delete" value="1"> ' . esc_html__( 'Подтверждаю полное удаление обращения и переписки', 'prihod' ) . '</label> <button type="submit" name="delete" value="1" class="button">' . esc_html__( 'Удалить', 'prihod' ) . '</button></p>'; }
                echo '</form>';
            }
        } else {
            $page = max( 1, isset( $_GET['paged'] ) && is_scalar( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
            $filter = isset( $_GET['status'] ) && is_string( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
            if ( ! isset( Requests::statuses()[$filter] ) ) { $filter = ''; }
            echo '<form class="prihod-request-filters"><input type="hidden" name="page" value="prihod-requests"><label for="prihod-status-filter">' . esc_html__( 'Статус', 'prihod' ) . '</label><select id="prihod-status-filter" name="status"><option value="">' . esc_html__( 'Все статусы', 'prihod' ) . '</option>';
            foreach ( Requests::statuses() as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '"' . selected( $filter, $value, false ) . '>' . esc_html( $label ) . '</option>'; }
            echo '</select><button class="button button-primary">' . esc_html__( 'Показать', 'prihod' ) . '</button>';
            if ( $filter ) { echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=prihod-requests' ) ) . '">' . esc_html__( 'Сбросить фильтр', 'prihod' ) . '</a>'; }
            echo '</form><table class="widefat"><thead><tr><th>' . esc_html__( 'Тема', 'prihod' ) . '</th><th>' . esc_html__( 'Статус', 'prihod' ) . '</th><th>' . esc_html__( 'Ответственный', 'prihod' ) . '</th></tr></thead><tbody>';
            $args = array( 'post_type' => 'prihod_request', 'post_status' => 'private', 'posts_per_page' => 25, 'paged' => $page, 'orderby' => 'ID', 'order' => 'DESC' );
            if ( isset( Requests::statuses()[ $filter ] ) ) { $args['meta_key'] = '_prihod_request_status'; $args['meta_value'] = $filter; }
            $query = new \WP_Query( $args );
            if ( ! $query->posts ) {
                $has_previous = false;
                if ( $page > 1 ) { $first_args = $args; $first_args['paged'] = 1; $first_args['posts_per_page'] = 1; $first_args['fields'] = 'ids'; $first_args['no_found_rows'] = true; $has_previous = (bool) ( new \WP_Query( $first_args ) )->posts; }
                $message = $has_previous ? __( 'На этой странице обращений нет.', 'prihod' ) : ( $filter ? __( 'По выбранному статусу обращений не найдено.', 'prihod' ) : __( 'Обращений пока нет.', 'prihod' ) );
                echo '<tr><td colspan="3" class="prihod-request-empty"><p role="status">' . esc_html( $message ) . '</p>';
                if ( $has_previous ) { echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'prihod-requests', 'status' => $filter ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Первая страница', 'prihod' ) . '</a>'; }
                echo '</td></tr>';
            }
            foreach ( $query->posts as $post ) {
                $user = get_userdata( (int) get_post_meta( $post->ID, '_prihod_assigned', true ) );
                echo '<tr><td><a href="' . esc_url( add_query_arg( array( 'page' => 'prihod-requests', 'request_id' => $post->ID ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $post->post_title ) . '</a></td><td>' . esc_html( Requests::statuses()[ get_post_meta( $post->ID, '_prihod_request_status', true ) ] ?? '' ) . '</td><td>' . esc_html( $user ? $user->display_name : '' ) . '</td></tr>';
            }
            echo '</tbody></table>';
            for ( $index = 1; $index <= $query->max_num_pages; $index++ ) { echo '<a style="margin:1em" href="' . esc_url( add_query_arg( array( 'page' => 'prihod-requests', 'paged' => $index, 'status' => $filter ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $index ) . '</a>'; }
        }
        echo '</div>';
    }
    private static function delivery_label( string $status ): string {
        return array( 'pending' => __( 'Отправляется', 'prihod' ), 'sent' => __( 'Передано почтовой системе. Доставка в ящик не подтверждена.', 'prihod' ), 'failed' => __( 'Ошибка отправки', 'prihod' ) )[ $status ] ?? __( 'Не отправлено', 'prihod' );
    }
    public static function update(): void {
        self::authorize(); $id = absint( $_POST['request_id'] ?? 0 );
        check_admin_referer( 'prihod_request_update_' . $id );
        try {
            Requests::assert_request( $id );
            if ( ! empty( $_POST['delete'] ) ) {
                self::authorize( 'delete_prihod_requests' );
                if ( '1' !== ( $_POST['confirm_delete'] ?? '' ) ) { throw new \InvalidArgumentException( __( 'Подтвердите удаление.', 'prihod' ) ); }
                Requests::delete( $id ); $id = 0;
            } else {
                if ( isset( $_POST['retry_message'] ) ) { self::authorize( 'reply_prihod_requests' ); Requests::retry( $id, is_scalar( $_POST['retry_message'] ) ? absint( $_POST['retry_message'] ) : 0 ); }
                if ( isset( $_POST['assigned'] ) ) {
                    self::authorize( 'assign_prihod_requests' );
                    $assigned = is_scalar( $_POST['assigned'] ) ? absint( $_POST['assigned'] ) : -1;
                    if ( $assigned && ( ! user_can( $assigned, 'view_prihod_requests' ) || ! user_can( $assigned, 'reply_prihod_requests' ) ) ) { throw new \InvalidArgumentException( __( 'Ответственный не имеет необходимых прав.', 'prihod' ) ); }
                    update_post_meta( $id, '_prihod_assigned', $assigned );
                }
                if ( isset( $_POST['status'] ) ) {
                    self::authorize( 'reply_prihod_requests' );
                    if ( ! is_string( $_POST['status'] ) || ! isset( Requests::statuses()[ $_POST['status'] ] ) ) { throw new \InvalidArgumentException( __( 'Некорректный статус.', 'prihod' ) ); }
                    update_post_meta( $id, '_prihod_request_status', $_POST['status'] );
                }
                if ( ! empty( $_POST['body'] ) ) {
                    self::authorize( 'reply_prihod_requests' );
                    if ( ! is_string( $_POST['body'] ) ) { throw new \InvalidArgumentException( __( 'Некорректный ответ.', 'prihod' ) ); }
                    Requests::message( $id, wp_unslash( $_POST['body'] ), ! empty( $_POST['internal'] ) );
                }
                wp_update_post( array( 'ID' => $id ) );
            }
            $notice = __( 'Изменения сохранены.', 'prihod' );
        } catch ( \Throwable $error ) { $notice = __( 'Не удалось применить все изменения. Проверьте права и поля.', 'prihod' ); }
        set_transient( 'prihod_request_notice_' . get_current_user_id(), $notice, 60 );
        wp_safe_redirect( add_query_arg( array( 'page' => 'prihod-requests', 'request_id' => $id ), admin_url( 'admin.php' ) ) ); exit;
    }
}
