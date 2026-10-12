<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Wizard {
    public static function hooks(): void {
        add_action( 'admin_menu', function () { add_submenu_page( 'prihod', __( 'Мастер настройки', 'prihod' ), __( 'Мастер настройки', 'prihod' ), 'manage_prihod_settings', 'prihod-wizard', array( Wizard::class, 'render' ) ); } );
        add_action( 'admin_post_prihod_wizard', array( self::class, 'submit' ) );
        add_action( 'admin_notices', array( self::class, 'invitation' ) );
        add_action( 'init', array( self::class, 'patterns' ) );
        add_action( 'admin_enqueue_scripts', function ( $hook ) {
            if ( 'prihod-wizard' === ( $_GET['page'] ?? '' ) ) { wp_enqueue_style( 'prihod-wizard', plugins_url( 'assets/wizard.css', PRIHOD_FILE ), array(), PRIHOD_VERSION ); }
        } );
    }
    public static function state(): array {
        return Wizard_Interview::resume( get_user_meta( get_current_user_id(), 'prihod_wizard_' . get_current_blog_id(), true ) ?: array( 'step' => 0, 'answers' => array( 'profile' => Profile::get(), 'modules' => array( 'schedule' => Modules::enabled( 'schedule' ), 'requests' => Modules::enabled( 'requests' ) ) ), 'revision' => 0 ) );
    }
    private static function save( array $state ): void { update_user_meta( get_current_user_id(), 'prihod_wizard_' . get_current_blog_id(), $state ); }
    public static function steps(): array {
        return array( __( 'Добро пожаловать', 'prihod' ), __( 'О вашем приходе', 'prihod' ), __( 'Возможности', 'prihod' ), __( 'Страницы сайта', 'prihod' ), __( 'Подтверждение', 'prihod' ), __( 'Готово', 'prihod' ) );
    }
    public static function invitation(): void {
        if ( 'prihod-wizard' === ( $_GET['page'] ?? '' ) || 5 === self::state()['stage'] ) { return; }
        if ( current_user_can( 'manage_prihod_settings' ) && ! get_user_meta( get_current_user_id(), 'prihod_wizard_dismissed_' . get_current_blog_id(), true ) ) {
            echo '<div class="notice notice-info"><p><a href="' . esc_url( admin_url( 'admin.php?page=prihod-wizard' ) ) . '">' . esc_html__( 'Начать настройку «Прихода»', 'prihod' ) . '</a></p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="prihod_wizard"><input type="hidden" name="operation" value="dismiss">'; wp_nonce_field( 'prihod_wizard' ); echo '<button class="button">' . esc_html__( 'Скрыть приглашение', 'prihod' ) . '</button></form></div>';
        }
    }
    public static function render(): void {
        if ( ! current_user_can( 'manage_prihod_settings' ) || ! current_user_can( 'manage_prihod' ) || ! current_user_can( 'edit_pages' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); }
        Wizard_View::render( self::state() );
    }
    public static function submit(): void {
        if ( ! current_user_can( 'manage_prihod_settings' ) ) { wp_die( esc_html__( 'Недостаточно прав.', 'prihod' ), '', array( 'response' => 403 ) ); }
        check_admin_referer( 'prihod_wizard' );
        $lock = 'prihod_wizard_lock_' . get_current_blog_id() . '_' . get_current_user_id();
        $token = Lock::acquire( $lock, 120 ); if ( ! $token ) { wp_die( esc_html__( 'Сохранение мастера уже выполняется.', 'prihod' ) ); }
        $state = self::state(); $operation = is_string( $_POST['operation'] ?? null ) ? sanitize_key( $_POST['operation'] ) : '';
        $input_checked = false;
        try {
            if ( 'dismiss' === $operation ) { update_user_meta( get_current_user_id(), 'prihod_wizard_dismissed_' . get_current_blog_id(), true ); }
            else {
                if ( ! is_scalar( $_POST['revision'] ?? null ) || (int) $_POST['revision'] !== $state['revision'] ) { throw new \RuntimeException( __( 'Эта вкладка устарела. Откройте мастер заново.', 'prihod' ) ); }
                if ( '2' !== ( $_POST['interface'] ?? '' ) ) { throw new \RuntimeException( __( 'Эта вкладка устарела. Откройте мастер заново.', 'prihod' ) ); }
                $input_checked = true;
                $state = Wizard_Interview::advance( $state, $operation, wp_unslash( $_POST ) );
                ++$state['revision']; $state['notice'] = __( 'Состояние мастера сохранено.', 'prihod' ); $state['notice_type'] = 'success'; self::save( $state );
            }
        } catch ( \Throwable $error ) {
            $state['notice'] = $error->getMessage(); $state['notice_type'] = 'error';
            if ( $input_checked && 1 === $state['stage'] && is_array( $_POST['profile'] ?? null ) ) { $state['profile_input'] = array_intersect_key( array_filter( wp_unslash( $_POST['profile'] ), 'is_string' ), Profile::defaults() ); }
            if ( 4 === $state['stage'] ) { unset( $state['plan'] ); $state['stage'] = 3; $state['step'] = 11; }
            self::save( $state );
        }
        Lock::release( $lock, $token );
        wp_safe_redirect( admin_url( 'admin.php?page=prihod-wizard' ) ); exit;
    }
    public static function patterns(): void {
        foreach ( array( 'news' => __( 'Новость прихода', 'prihod' ), 'announcement' => __( 'Объявление', 'prihod' ), 'sermon' => __( 'Проповедь', 'prihod' ) ) as $key => $title ) {
            register_block_pattern( 'prihod/' . $key, array( 'title' => $title, 'categories' => array( 'text' ), 'content' => '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $title ) . '</h2><!-- /wp:heading --><!-- wp:paragraph --><p>' . esc_html__( 'Добавьте текст публикации.', 'prihod' ) . '</p><!-- /wp:paragraph -->' ) );
        }
    }
}
