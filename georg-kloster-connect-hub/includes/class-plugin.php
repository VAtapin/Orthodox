<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    public const REST_NAMESPACE = 'gkps/v1';

    public static function boot(): void {
        Capabilities::maybe_upgrade();
        Forms::register();
        Requests::hooks();
        Telegram_Requests::hooks();
        Telegram_Bot::hooks();
        Outgoing_Log::schedule();
        add_action( 'wp_enqueue_scripts', array( Request_Form::class, 'enqueue_styles' ) );
        add_action( 'gkps_request_created', array( self::class, 'notify_by_email' ) );
        add_action( 'init', array( Blocks::class, 'register' ) );
        add_action( 'admin_menu', array( Admin::class, 'menu' ), 5 );
        add_action( 'admin_init', array( Admin::class, 'settings' ) );
        add_action( 'admin_init', array( Hub_Settings::class, 'register' ) );
        add_action( 'admin_post_gkch_settings_save', array( Hub_Settings::class, 'save_general' ) );
        add_action( 'admin_menu', array( Admin::class, 'order_menu' ), 99 );
        add_action( 'admin_post_gkch_form_save', array( Forms::class, 'save' ) );
        add_action( 'admin_post_gkch_publish', array( Publications::class, 'submit' ) );
        add_action( 'admin_post_gkch_publication_action', array( Publications::class, 'action' ) );
        foreach ( Publications::titles() as $id => $title ) { Connector::boot( $id, $title, GKPS_FILE ); }
        Publication_Rules::boot();
    }

    public static function notify_by_email( int $id ): void {
        $form = Forms::get( (int) get_post_meta( $id, '_gkch_form_id', true ) );
        $recipient = $form['recipient'] ?? '';
        if ( is_email( $recipient ) ) {
            $subject = __( 'New contact message', 'georg-kloster-connect-hub' );
            $content = admin_url( 'admin.php?page=gkps-requests&request_id=' . $id );
            try { $sent = wp_mail( $recipient, $subject, $content ); } catch ( \Throwable $error ) { $sent = false; }
            Outgoing_Log::record( array( 'channel' => 'email', 'recipient' => $recipient, 'subject' => $subject, 'content' => $content, 'kind' => 'request_notification', 'source_type' => 'request', 'source_id' => $id, 'status' => $sent ? 'accepted' : 'failed' ) );
        }
    }
}
