<?php
/** Read-only public-content inventory for migration audits, run via WP-CLI. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }
global $wpdb;
$host = wp_parse_url( home_url(), PHP_URL_HOST );
if ( ! in_array( $host, array( 'georg-kloster.ru', 'georg-kloster.de', 'new.georg-kloster.ru' ), true ) ) {
    WP_CLI::error( 'This inventory is restricted to the monastery sites.' );
}
$output = getenv( 'GK_AUDIT_OUTPUT' );
$private = '/var/www/vhosts/georg-kloster.ru/private/codex-migration-20261017/';
if ( ! $output || dirname( $output ) . '/' !== $private || ! preg_match( '/^audit-[a-z-]+\.json$/D', basename( $output ) ) ) {
    WP_CLI::error( 'Use an audit JSON filename in the private migration directory.' );
}
$data = array( 'home' => home_url(), 'generated_utc' => gmdate( 'c' ), 'posts' => array(), 'terms' => array(), 'menus' => array(), 'shared_blocks' => array(), 'components' => array(), 'other_public_types' => array() );
$rows = $wpdb->get_results( "SELECT ID,post_type,post_title,post_name,post_content,post_excerpt,post_parent FROM {$wpdb->posts} WHERE post_status='publish' AND post_password='' AND post_type IN ('post','page') ORDER BY ID", ARRAY_A );
foreach ( $rows as $row ) {
    $id = (int) $row['ID'];
    $lang = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $id, 'element_type' => 'post_' . $row['post_type'] ) );
    do_action( 'wpml_switch_language', $lang ?: 'ru' );
    $row['url'] = get_permalink( $id );
    $row['lang'] = $lang;
    $row['source_id'] = (int) get_post_meta( $id, '_gkm_source_id', true );
    $row['thumbnail'] = get_the_post_thumbnail_url( $id, 'full' ) ?: '';
    $row['terms'] = array();
    foreach ( array( 'category', 'post_tag' ) as $tax ) {
        $terms = wp_get_object_terms( $id, $tax );
        $row['terms'][$tax] = is_wp_error( $terms ) ? array() : array_map( static function( $t ) { return array( 'slug' => $t->slug, 'name' => $t->name ); }, $terms );
    }
    $data['posts'][] = $row;
    preg_match_all( '/\[fusion_global\s+id="(\d+)"/i', $row['post_content'], $shared );
    foreach ( array_unique( $shared[1] ) as $shared_id ) {
        $block = get_post( (int) $shared_id );
        if ( $block && 'publish' === $block->post_status && '' === $block->post_password ) {
            $data['shared_blocks'][$shared_id] = array( 'title' => $block->post_title, 'content' => $block->post_content );
        }
    }
}
do_action( 'wpml_switch_language', 'all' );
$terms = get_terms( array( 'taxonomy' => array( 'category', 'post_tag' ), 'hide_empty' => false, 'suppress_filter' => true ) );
if ( is_wp_error( $terms ) ) { WP_CLI::error( $terms->get_error_message() ); }
foreach ( $terms as $term ) {
    $lang = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $term->term_taxonomy_id, 'element_type' => 'tax_' . $term->taxonomy ) );
    do_action( 'wpml_switch_language', $lang ?: 'ru' );
    $link = get_term_link( $term );
    $data['terms'][] = array( 'id' => $term->term_id, 'taxonomy' => $term->taxonomy, 'slug' => $term->slug, 'name' => $term->name, 'description' => $term->description, 'count' => $term->count, 'lang' => $lang, 'url' => is_wp_error( $link ) ? '' : $link );
}
do_action( 'wpml_switch_language', 'all' );
foreach ( wp_get_nav_menus() as $menu ) {
    $lang = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $menu->term_taxonomy_id, 'element_type' => 'tax_nav_menu' ) );
    do_action( 'wpml_switch_language', $lang ?: 'ru' );
    // Bypass WPML query filtering so hidden menu items are still inventoried.
    $ids = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->term_relationships} r ON r.object_id=p.ID WHERE r.term_taxonomy_id=%d AND p.post_type='nav_menu_item' AND p.post_status='publish' ORDER BY p.menu_order,p.ID", $menu->term_taxonomy_id ) );
    $items = array_map( static function( $id ) { return wp_setup_nav_menu_item( get_post( $id ) ); }, $ids );
    $data['menus'][] = array( 'name' => $menu->name, 'items' => array_map( static function( $i ) { return array( 'title' => $i->title, 'url' => $i->url, 'parent' => (int) $i->menu_item_parent, 'object' => $i->object, 'object_id' => (int) $i->object_id ); }, $items ?: array() ) );
}
foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) {
    if ( in_array( $type, array( 'post','page','attachment' ), true ) ) { continue; }
    $data['other_public_types'][$type] = (int) ( wp_count_posts( $type )->publish ?? 0 );
}
$components = $wpdb->get_results( "SELECT ID,post_type,post_title,post_content FROM {$wpdb->posts} WHERE post_status='publish' AND post_password='' AND post_type IN ('popup','calendar') ORDER BY ID", ARRAY_A );
foreach ( $components as $component ) {
    $component['source_id'] = (int) get_post_meta( (int) $component['ID'], '_gkm_source_id', true );
    $data['components'][] = $component;
}
// Status only: imported popups may deliberately be drafts. Do not export their content.
$data['imported_popup_statuses'] = $wpdb->get_results( "SELECT p.ID,p.post_status,m.meta_value AS source_id FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_gkm_source_id' WHERE p.post_type='popup' ORDER BY p.ID", ARRAY_A );
if ( false === file_put_contents( $output, wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) ) ) { WP_CLI::error( 'Could not write private inventory.' ); }
WP_CLI::success( count( $data['posts'] ) . ' public pages/posts, ' . count( $data['terms'] ) . ' terms inventoried. No WordPress data changed.' );
