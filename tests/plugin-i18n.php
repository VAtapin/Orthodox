<?php
/** Disposable WordPress only: automatic PHP/JS locale loading and real UI output. */
require_once '/wordpress/wp-includes/plugin.php';
add_action( 'doing_it_wrong_run', static function ( $function, $message ) {
    if ( '_load_textdomain_just_in_time' === $function ) { throw new RuntimeException( $message ); }
}, 10, 2 );
set_error_handler( static function ( $severity, $message ) {
    if ( str_contains( $message, '_load_textdomain_just_in_time' ) ) { throw new RuntimeException( $message ); }
    return false;
} );
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void {
    ++$checks;
    if ( ! $ok ) { throw new RuntimeException( $message ); }
};
wp_set_current_user( get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0]->ID );
set_current_screen( 'dashboard' );
$check( 'ru_RU' === determine_locale(), 'Russian locale was not selected: ' . get_locale() . '/' . determine_locale() . '/' . get_option( 'WPLANG' ) . ' plugins=' . wp_json_encode( get_option( 'active_plugins' ) ) );
foreach ( array(
    'georg-kloster-prihod-core' => array( 'Parish', 'Приход' ),
    'georg-kloster-connect-hub' => array( 'Connect Hub', 'Коммуникации' ),
    'georg-kloster-prihod-schedule' => array( 'Services', 'Богослужения' ),
) as $domain => $pair ) {
    $check( $pair[1] === translate( $pair[0], $domain ), 'Bundled catalog did not load automatically: ' . $domain );
}
foreach ( array( GKPC_FILE => 'Georg-Kloster — Приход', GKPS_FILE => 'Georg-Kloster — Коммуникации' ) as $file => $title ) {
    $data = get_plugin_data( $file );
    $check( $title === $data['Name'], 'Untranslated plugin name.' );
    $check( (bool) preg_match( '/[А-Яа-яЁё]/u', $data['Description'] ), 'Untranslated plugin description.' );
}
global $menu, $submenu;
$menu = array(); $submenu = array();
do_action( 'admin_init' );
do_action( 'admin_menu' );
foreach ( array( 'gkpc' => 'Приход', 'gkps' => 'Коммуникации' ) as $slug => $title ) {
    $found = array_filter( $menu, static fn( $item ) => $slug === $item[2] && $title === $item[0] );
    $check( 1 === count( $found ), 'Untranslated admin menu: ' . $slug );
}
$check( 'Места служения' === get_post_type_object( 'gkpc_location' )->labels->name, 'Core directory labels.' );
$check( 'Духовенство' === get_post_type_object( 'gkpc_clergy' )->labels->name, 'Clergy directory labels.' );
$check( 'Места служения' === get_post_type_object( 'gkpsch_location' )->labels->name, 'Schedule directory labels.' );
foreach ( array(
    'gkpc/profile' => 'Сведения о приходе', 'gkpc/contacts' => 'Контакты прихода',
    'gkpc/clergy' => 'Духовенство', 'gkpc/places' => 'Места служения',
    'gkps/contact-form' => 'Форма', 'gkpsch/schedule' => 'Расписание богослужений',
    'gkpsch/next-service' => 'Ближайшее богослужение', 'gkpsch/next-services' => 'Ближайшие богослужения',
) as $name => $title ) {
    $check( $title === WP_Block_Type_Registry::get_instance()->get_registered( $name )->title, 'Untranslated block: ' . $name );
}
update_option( 'gkpc_profile', array( 'name' => 'Owner supplied name', 'description' => '<own content>' ) );
update_option( 'gkps_form', array( 'enabled' => true, 'intro' => 'Owner supplied introduction' ) );
$saved = array( get_option( 'gkpc_profile' ), get_option( 'gkps_form' ) );
$content = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) );
$screens = array(
    array( array( 'GeorgKlosterPrihodCore\Admin', 'page' ), array( 'Сведения о приходе', 'Тип общины', 'Разделы сайта', 'Owner supplied name' ) ),
    array( array( 'GeorgKlosterPrihodSocial\Admin', 'render' ), array( 'Формы', 'Заголовок формы', 'Почта для уведомлений' ) ),
    array( array( 'GeorgKlosterPrihodSocial\Request_Admin', 'render' ), array( 'Сообщения' ) ),
    array( array( 'GeorgKlosterPrihodSchedule\Admin', 'render' ), array( 'Расписание богослужений', 'Управление богослужениями', 'Управление местами служения' ) ),
    array( array( 'GeorgKlosterPrihodSchedule\Admin', 'settings_page' ), array( 'Настройки расписания' ) ),
);
foreach ( array( 'telegram', 'whatsapp', 'facebook' ) as $provider ) {
    $labels = array( 'Токен', 'Разрешить отправки' );
    if ( 'whatsapp' === $provider ) { $labels[] = 'Идентификатор номера телефона'; }
    if ( 'facebook' === $provider ) { $labels[] = 'Facebook'; $labels[] = 'Идентификатор страницы Facebook'; }
    $screens[] = array( array( GeorgKlosterPrihodSocial\Connector::instance( $provider ), 'render' ), $labels );
}
foreach ( $screens as $screen ) {
    ob_start(); call_user_func( $screen[0] ); $html = ob_get_clean();
    foreach ( $screen[1] as $label ) { $check( str_contains( $html, $label ), 'Missing screen translation: ' . $label ); }
    foreach ( array( '>Parish<', '>Contacts<', '>Services<', '>Manage services<', '>Manage places<', '>Contact form<' ) as $english ) {
        $check( ! str_contains( $html, $english ), 'English UI remains: ' . $english );
    }
}
$form = do_shortcode( '[gkps_contact_form]' );
$check( str_contains( $form, 'Напишите нам' ) && str_contains( $form, 'Отправить обращение' ) && str_contains( $form, 'Owner supplied introduction' ), 'Russian form defaults or owner text missing.' );
foreach ( array( 'gkpc-blocks' => 'Сведения о приходе', 'gkps-blocks' => 'Форма', 'gkpsch-blocks' => 'Период' ) as $handle => $label ) {
    $script = wp_scripts()->registered[$handle];
    $check( str_contains( $script->translations_path, '/languages' ), 'Script translation path missing.' );
    $translations = wp_scripts()->print_translations( $handle, false );
    $check( is_string( $translations ) && str_contains( $translations, $label ), 'WordPress did not resolve editor JSON: ' . $handle );
}
$check( $saved === array( get_option( 'gkpc_profile' ), get_option( 'gkps_form' ) ), 'Rendering changed owner settings.' );
$check( $content == get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) ), 'Rendering changed page/post content.' );
$check( switch_to_locale( 'en_US' ), 'Cannot switch to English.' );
$check( 'Name' === GeorgKlosterPrihodCore\Profile::fields()['name'], 'Core English fallback failed.' );
$check( 'Contact us' === GeorgKlosterPrihodSocial\Admin::defaults()['title'], 'Social English fallback failed.' );
$check( 'Service schedule' === GeorgKlosterPrihodSchedule\Public_View::blocks()['schedule'], 'Schedule English fallback failed.' );
foreach ( $screens as $screen ) {
    ob_start(); call_user_func( $screen[0] ); $english_screen = ob_get_clean();
    // Saved form titles/button labels are authored settings, even when they
    // were first saved under Russian. Locale changes must not translate them.
    $english_ui = wp_strip_all_tags( preg_replace( '/<textarea\b[^>]*>.*?<\/textarea>/s', '', $english_screen ) );
    preg_match_all( '/[А-Яа-яЁё][^<>]*/u', $english_ui, $unexpected );
    $check( ! $unexpected[0], 'Russian source text leaked into English ' . wp_json_encode( $screen[0] ) . ': ' . implode( ' | ', $unexpected[0] ) );
}
$check( 'Email reply' === translate( 'Email reply', 'georg-kloster-connect-hub' ) && 'Week' === translate( 'Week', 'georg-kloster-prihod-schedule' ), 'English source fallback missing.' );
foreach ( array( 'GeorgKlosterPrihodCore\\Blocks', 'GeorgKlosterPrihodSocial\\Blocks' ) as $help_class ) {
    ob_start(); $help_class::help(); $help = ob_get_clean();
    $check( str_contains( $help, 'Shortcodes and blocks' ) && ! preg_match( '/[А-Яа-яЁё]/u', $help ), 'English shortcode help is missing or contains Russian source text.' );
    $tags = str_contains( $help_class, 'Core' ) ? array( 'gkpc_profile', 'gkpc_contacts', 'gkpc_clergy', 'gkpc_places', 'gkpsch_schedule', 'gkpsch_next_service', 'gkpsch_next_services' ) : array( 'gkch_form', 'gkps_contact_form' );
    foreach ( $tags as $tag ) { $check( shortcode_exists( $tag ) && str_contains( $help, '[' . $tag ), 'Shortcode documentation differs from registration: ' . $tag ); }
}
restore_previous_locale();
$check( $saved === array( get_option( 'gkpc_profile' ), get_option( 'gkps_form' ) ), 'Changing the interface language rewrote authored settings.' );
$check( 'Название' === GeorgKlosterPrihodCore\Profile::fields()['name'], 'Russian locale was not restored.' );
foreach ( array( array( 'GeorgKlosterPrihodCore\\Admin', 'page' ), array( 'GeorgKlosterPrihodSchedule\\Admin', 'settings_page' ), array( 'GeorgKlosterPrihodSocial\\Forms', 'render' ), array( 'GeorgKlosterPrihodSocial\\Hub_Settings', 'render' ) ) as $help_screen ) {
    ob_start(); call_user_func( $help_screen ); $help = ob_get_clean();
    $check( str_contains( $help, 'Шорткоды и блоки' ), 'Shortcode help is missing from the Russian settings/forms screen.' );
}
restore_error_handler();
file_put_contents( __DIR__ . '/.plugin-i18n-result.json', wp_json_encode( array( 'checks' => $checks, 'wordpress' => get_bloginfo( 'version' ), 'php' => PHP_VERSION, 'result' => 'PLUGIN_I18N_OK' ) ) );
echo 'PLUGIN_I18N_OK ' . $checks . ' WP ' . get_bloginfo( 'version' ) . ' PHP ' . PHP_VERSION;
