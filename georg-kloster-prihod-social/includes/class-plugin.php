<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Plugin {
    public const REST_NAMESPACE = 'gkps/v1';

    public static function boot(): void {
        load_plugin_textdomain( 'georg-kloster-prihod-social', false, dirname( plugin_basename( GKPS_FILE ) ) . '/languages' );
        Capabilities::maybe_upgrade();
        Requests::hooks();
        Telegram_Requests::hooks();
        add_action( 'gkps_request_created', array( self::class, 'notify_by_email' ) );
        add_action( 'init', array( Blocks::class, 'register' ) );
        add_action( 'admin_menu', array( Admin::class, 'menu' ), 5 );
        add_action( 'admin_init', array( Admin::class, 'settings' ) );
        foreach ( array(
            'telegram' => __( 'Telegram', 'georg-kloster-prihod-social' ),
            'whatsapp' => __( 'WhatsApp', 'georg-kloster-prihod-social' ),
            'facebook' => __( 'Facebook Page', 'georg-kloster-prihod-social' ),
        ) as $id => $title ) { Connector::boot( $id, $title, GKPS_FILE ); }
    }

    public static function notify_by_email( int $id ): void {
        $recipient = Admin::get()['recipient'];
        if ( is_email( $recipient ) ) {
            wp_mail( $recipient, __( 'New contact message', 'georg-kloster-prihod-social' ), admin_url( 'admin.php?page=gkps-requests&request_id=' . $id ) );
        }
    }
}
