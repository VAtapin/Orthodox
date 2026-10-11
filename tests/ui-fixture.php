<?php
// Disposable browser fixture only. Never execute inside an existing WordPress.
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
add_filter( 'pre_http_request', fn() => new WP_Error( 'offline_test', 'External HTTP disabled.' ), PHP_INT_MAX );
add_filter( 'pre_wp_mail', fn() => false, PHP_INT_MAX );
activate_plugin( 'prihod-tests/ui-preview.php' );
activate_plugin( 'prihod/prihod.php' );
\Prihod\Plugin::boot();
foreach ( array( 'orthocal/orthocal.php', 'kloster-prayer/kloster-prayer.php', 'georg-kloster-orthodox-donate/georg-kloster-orthodox-donate.php' ) as $legacy ) { if ( file_exists( WP_PLUGIN_DIR . '/' . $legacy ) ) { $result = activate_plugin( $legacy ); if ( is_wp_error( $result ) ) { throw new RuntimeException( 'UI fixture cannot activate the supplied plugin: ' . $legacy ); } } }
if ( class_exists( '\KlosterPrayer\Public_Form' ) ) { \KlosterPrayer\Public_Form::init(); }
if ( class_exists( '\GeorgKlosterOrthodoxDonate\Public_Form' ) ) { \GeorgKlosterOrthodoxDonate\Public_Form::init(); }
switch_theme( 'prihod-theme' );
update_option( 'WPLANG', 'ru_RU' );
update_option( 'blogname', 'Приход — проверка интерфейса' );
update_option( 'blogdescription', 'Тестовое содержимое' );
update_option( 'prihod_profile', array( 'type' => 'parish', 'name' => 'Приход — проверка интерфейса', 'description' => 'Место встречи, молитвы и добрых дел.', 'short_name' => 'Приход', 'timezone' => 'Europe/Berlin', 'language' => 'ru_RU', 'address' => 'Тестовый адрес', 'phone' => '', 'email' => '', 'website' => '' ) );
\Prihod\Directory::register(); \Prihod\Schedule::register();
$place = wp_insert_post( array( 'post_type' => 'prihod_location', 'post_status' => 'publish', 'post_title' => 'Тестовый храм' ) );
$now = new DateTimeImmutable( 'now', new DateTimeZone( 'Europe/Berlin' ) );
wp_insert_post( array( 'post_type' => 'prihod_service', 'post_status' => 'publish', 'post_title' => 'Божественная литургия', 'meta_input' => array( '_prihod_service' => \Prihod\Schedule::validate( array( 'kind' => 'regular', 'date' => $now->format( 'Y-m-d' ), 'time' => '09:00', 'end' => '', 'weekdays' => array( 7 ), 'location_id' => $place, 'timezone' => 'Europe/Berlin', 'until' => '', 'exceptions' => array() ) ) ) ) );
$resources = array();
foreach ( \Prihod\Site_Plan::pages() as $key => $page ) {
    $content = 'home' === $key ? '' : $page['content'];
    $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $page['title'], 'post_content' => $content ) );
    $resources[ $key ] = array( 'id' => $id );
    if ( in_array( $key, array( 'schedule', 'news' ), true ) ) { update_post_meta( $id, '_wp_page_template', 'wide' ); }
    if ( 'requests' === $key ) { update_post_meta( $id, '_wp_page_template', 'focused' ); }
}
update_option( 'prihod_resources', $resources );
$links = '';
foreach ( array( 'home', 'schedule', 'about', 'news', 'requests', 'contacts' ) as $key ) { $links .= '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => get_the_title( $resources[ $key ]['id'] ), 'type' => 'page', 'id' => $resources[ $key ]['id'], 'url' => get_permalink( $resources[ $key ]['id'] ), 'kind' => 'post-type' ) ) . ' /-->'; }
$resources['_navigation'] = array( 'id' => wp_insert_post( wp_slash( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Test navigation', 'post_content' => $links ) ) ) );
update_option( 'prihod_resources', $resources );
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $resources['home']['id'] );
foreach ( array( 'Жизнь общины', 'Встреча прихожан', 'Добрые дела' ) as $title ) { wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => 'Тестовая публикация для проверки карточки, типографики и адаптивной сетки.' ) ); }
