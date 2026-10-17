<?php
/** Read-only integration check for the restored bilingual sections. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This check is restricted to new.georg-kloster.ru.' );
}

$assert = static function ( bool $condition, string $message ): void {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
};
$pages = get_option( 'gkpc_pages', array() );
$pilgrim = (int) ( $pages['first-visit']['id'] ?? 0 );
$sermons = (int) ( $pages['sermons']['id'] ?? 0 );
$assert( $pilgrim > 0 && $sermons > 0, 'Core page links are missing.' );
$assert( 'palomniku' === get_post_field( 'post_name', $pilgrim ) && has_block( 'core/query', get_post_field( 'post_content', $pilgrim ) ), 'Russian pilgrim page or its articles are missing.' );
$assert( 'propovedi-i-besedy' === get_post_field( 'post_name', $sermons ) && has_block( 'core/query', get_post_field( 'post_content', $sermons ) ), 'Russian sermons page or its articles are missing.' );
$de_pilgrim = (int) apply_filters( 'wpml_object_id', $pilgrim, 'page', false, 'de' );
$de_sermons = (int) apply_filters( 'wpml_object_id', $sermons, 'page', false, 'de' );
$assert( $de_pilgrim > 0 && 'fuer-pilger' === get_post_field( 'post_name', $de_pilgrim ), 'German pilgrim translation is missing.' );
$assert( $de_sermons > 0 && 'predigten-und-gespraeche' === get_post_field( 'post_name', $de_sermons ), 'German sermons translation is missing.' );
$assert( str_contains( get_post_field( 'post_content', $de_sermons ), 'keine deutschen Übersetzungen' ), 'German page must explain the missing article translations.' );
$assert( str_contains( get_post_field( 'post_content', $de_sermons ), 'href="/propovedi-i-besedy/?lang=ru"' ), 'German page must link to the Russian originals.' );

foreach ( array( 'ru' => array( 44, $pilgrim, $sermons, 718, 721, 724, 722 ), 'de' => array( 45, $de_pilgrim, $de_sermons, 726, 729, 732, 730 ) ) as $lang => $ids ) {
    do_action( 'wpml_switch_language', $lang );
    $items = wp_get_nav_menu_items( $ids[0], array( 'post_status' => 'publish' ) );
    $assert( is_array( $items ) && 10 === count( $items ), "{$lang} menu must have all ten links." );
    $by_page = array();
    foreach ( $items as $item ) { if ( 'post_type' === $item->type ) { $by_page[(int) $item->object_id] = $item; } }
    $assert( isset( $by_page[$ids[1]], $by_page[$ids[2]] ), "{$lang} menu is missing a restored page." );
    $topics = prihod_theme_home_topic_links();
    $assert( str_contains( $topics, get_permalink( $ids[1] ) ) && str_contains( $topics, get_permalink( $ids[2] ) ), "{$lang} homepage section links are missing." );
    $schedule_url = prihod_theme_url( 'schedule' );
    $features_with_panel = prihod_theme_home_features( 'classic', 'About', true, true );
    $features_without_panel = prihod_theme_home_features( 'classic', 'About', true, false );
    $assert( $schedule_url && ! str_contains( $features_with_panel, 'href="' . esc_url( $schedule_url ) . '"' ), "{$lang} schedule card duplicates the nearby service panel." );
    $assert( str_contains( $features_without_panel, 'href="' . esc_url( $schedule_url ) . '"' ), "{$lang} schedule card must remain when the service panel is absent." );
    $photo = get_post( $ids[4] ); $request = get_post( $ids[6] );
    $assert( (int) get_post_meta( $photo->ID, '_menu_item_menu_item_parent', true ) === $ids[3], "{$lang} photos submenu is missing." );
    $assert( (int) get_post_meta( $request->ID, '_menu_item_menu_item_parent', true ) === $ids[5], "{$lang} contact submenu is missing." );
}
do_action( 'wpml_switch_language', 'ru' );
WP_CLI::success( 'Bilingual section pages, article queries, WPML links and both menus verified.' );
