<?php
namespace Prihod;
defined( 'ABSPATH' ) || exit;

final class Existing_Integration implements Integration {
    private string $id;
    public function __construct( string $id ) { $this->id = $id; }
    public function id(): string { return $this->id; }
    public function describe(): array {
        $calendar = 'calendar' === $this->id;
        return array( 'name' => $calendar ? __( 'Православный календарь', 'prihod' ) : __( 'Записки', 'prihod' ),
            'capabilities' => $calendar ? array( 'calendar', 'readings', 'liturgical_texts' ) : array( 'prayer_notes', 'donations', 'campaigns' ),
            'settings_url' => admin_url( $calendar ? 'admin.php?page=orthocal' : ( shortcode_exists( 'gkod_form' ) ? 'admin.php?page=georg-kloster-orthodox-donate-settings' : 'admin.php?page=kloster-prayer-settings' ) ),
            'blocks' => $calendar ? array( 'orthocal/calendar', 'orthocal/today', 'orthocal/readings' ) : array(),
            'pages' => $calendar ? array( 'calendar' ) : array( 'prayer-notes', 'donations' ), 'external_services' => array() );
    }
    public function health(): array {
        if ( 'calendar' === $this->id ) {
            if ( ! class_exists( '\Orthocal_Plugin' ) || ! shortcode_exists( 'orthocal_calendar' ) ) { return array( 'status' => 'unavailable', 'message' => __( 'Установите и активируйте существующий календарный плагин вручную.', 'prihod' ) ); }
            $public_api = defined( 'Orthocal_Config::DEFAULT_PUBLIC_API_ORIGIN' ) && is_callable( array( '\Orthocal_Config', 'public_api_origin' ) );
            $ready = $public_api || (bool) \Orthocal_Plugin::key();
            return array( 'status' => $ready ? 'ready' : 'needs_setup', 'message' => $ready ? __( 'Календарь активен. Доступность его сервера проверяется самим календарём.', 'prihod' ) : __( 'Календарь активен; настройте его подключение.', 'prihod' ) );
        }
        $ready = ( class_exists( '\KlosterPrayer\Public_Form' ) && shortcode_exists( 'kloster_form' ) ) || ( class_exists( '\GeorgKlosterOrthodoxDonate\Public_Form' ) && shortcode_exists( 'gkod_form' ) );
        return array( 'status' => $ready ? 'ready' : 'unavailable', 'message' => $ready ? __( 'Записки активны. Формы и платёжные подключения настраиваются в самом плагине.', 'prihod' ) : __( 'Установите «Записки» вручную. Версия 1.2.15 требует WordPress 7.1 и PHP 8.4.', 'prihod' ) );
    }
    public static function hooks(): void {
        add_action( 'init', array( self::class, 'register_form_aliases' ), 30 );
        add_action( 'prihod_register_integrations', function ( Integration_Registry $registry ) {
            $registry->register( new self( 'calendar' ) ); $registry->register( new self( 'prayer-notes' ) );
        } );
        add_filter( 'prihod_page_templates', function ( array $pages ) {
            if ( shortcode_exists( 'orthocal_calendar' ) ) { $pages['calendar'] = array( 'title' => __( 'Православный календарь', 'prihod' ), 'content' => '<!-- wp:orthocal/calendar /-->' ); }
            $form = shortcode_exists( 'gkod_form' ) ? 'gkod_form' : 'kloster_form';
            $donation = shortcode_exists( 'gkod_donation' ) ? 'gkod_donation' : 'kloster_donation';
            if ( shortcode_exists( $form ) ) { $pages['prayer-notes'] = array( 'title' => __( 'Записки', 'prihod' ), 'content' => '<!-- wp:shortcode -->[' . $form . ']<!-- /wp:shortcode -->' ); }
            if ( shortcode_exists( $donation ) ) { $pages['donations'] = array( 'title' => __( 'Пожертвования', 'prihod' ), 'content' => '<!-- wp:shortcode -->[' . $donation . ']<!-- /wp:shortcode -->' ); }
            return $pages;
        } );
    }
    public static function register_form_aliases(): void {
        global $shortcode_tags;
        foreach ( array( 'kloster_form' => 'gkod_form', 'kloster_donation' => 'gkod_donation' ) as $old => $current ) {
            if ( ! shortcode_exists( $old ) && shortcode_exists( $current ) ) { add_shortcode( $old, $shortcode_tags[$current] ); }
        }
    }
}
