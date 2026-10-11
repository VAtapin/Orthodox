<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Access {
    public static function hooks(): void {
        add_action( 'admin_menu', function () { if ( current_user_can( 'promote_users' ) && current_user_can( 'manage_prihod_settings' ) ) { add_submenu_page( 'prihod', __( 'Права сотрудников', 'prihod' ), __( 'Права сотрудников', 'prihod' ), 'promote_users', 'prihod-access', array( Access::class, 'render' ) ); } } );
        add_action( 'admin_post_prihod_access', array( self::class, 'save' ) );
    }
    public static function render(): void {
        self::authorize(); $id = isset( $_GET['user_id'] ) && is_scalar( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
        echo '<div class="wrap"><h1>' . esc_html__( 'Права сотрудников', 'prihod' ) . '</h1><form method="get"><input type="hidden" name="page" value="prihod-access">';
        wp_dropdown_users( array( 'name' => 'user_id', 'selected' => $id, 'show_option_none' => __( 'Выберите сотрудника', 'prihod' ) ) );
        echo '<button class="button">' . esc_html__( 'Выбрать', 'prihod' ) . '</button></form>';
        if ( $id && get_userdata( $id ) && $id !== get_current_user_id() && current_user_can( 'edit_user', $id ) ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_access"><input type="hidden" name="user_id" value="' . esc_attr( $id ) . '">'; wp_nonce_field( 'prihod_access_' . $id );
            foreach ( Capabilities::labels() as $cap => $label ) { echo '<p><label><input type="checkbox" name="caps[' . esc_attr( $cap ) . ']" value="1"' . checked( user_can( $id, $cap ), true, false ) . '> ' . esc_html( $label ) . '</label></p>'; }
            submit_button(); echo '</form>';
        }
        echo '<p>' . esc_html__( 'Изменяются только права «Прихода» выбранного сотрудника. Собственные права здесь не редактируются. Для ответа нужны права просмотра и ответа; для удаления — просмотра и удаления.', 'prihod' ) . '</p></div>';
    }
    public static function set( int $id, array $caps ): void {
        self::authorize();
        if ( $id === get_current_user_id() || ! current_user_can( 'edit_user', $id ) || ! get_userdata( $id ) ) { throw new \RuntimeException( __( 'Нельзя изменить права этого пользователя.', 'prihod' ) ); }
        $user = new \WP_User( $id ); foreach ( Capabilities::all() as $cap ) { $user->add_cap( $cap, ! empty( $caps[ $cap ] ) ); }
    }
    private static function authorize(): void { if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'manage_prihod_settings' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); } }
    public static function save(): void {
        self::authorize(); $id = is_scalar( $_POST['user_id'] ?? null ) ? absint( $_POST['user_id'] ) : 0; check_admin_referer( 'prihod_access_' . $id );
        self::set( $id, is_array( $_POST['caps'] ?? null ) ? $_POST['caps'] : array() );
        wp_safe_redirect( add_query_arg( array( 'page' => 'prihod-access', 'user_id' => $id ), admin_url( 'admin.php' ) ) ); exit;
    }
}
