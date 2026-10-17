<?php
/** Restore the omitted visitor and reading sections on the new test site only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This script is restricted to new.georg-kloster.ru.' );
}

function gk_sections_existing( int $id, string $type, string $title ): void {
    $post = get_post( $id );
    if ( ! $post || $type !== $post->post_type || $title !== $post->post_title || 'publish' !== $post->post_status ) {
        throw new RuntimeException( "Expected {$type} {$id}: {$title}" );
    }
}

function gk_sections_link( string $label, string $url ): string {
    return '<!-- wp:paragraph --><p><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p><!-- /wp:paragraph -->';
}

function gk_sections_query( array $categories ): string {
    $query = array( 'query' => array( 'perPage' => 9, 'postType' => 'post', 'order' => 'desc', 'orderBy' => 'date', 'inherit' => false, 'taxQuery' => array( 'category' => $categories ) ) );
    return '<!-- wp:query ' . wp_json_encode( $query ) . ' --><div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} --><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /--><!-- wp:post-date /--><!-- wp:post-title {"isLink":true,"level":2} /--><!-- wp:post-excerpt {"excerptLength":24} /--><!-- /wp:post-template --><!-- wp:query-pagination --><div class="wp-block-query-pagination"><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--></div><!-- /wp:query-pagination --></div><!-- /wp:query -->';
}

function gk_sections_page( string $slug, string $title, string $content, string $template ): int {
    $page = get_page_by_path( $slug );
    if ( $page ) {
        if ( $title !== $page->post_title || 'publish' !== $page->post_status || ! get_post_meta( $page->ID, '_gk_restored_section', true ) ) {
            throw new RuntimeException( "The page slug {$slug} is already in use." );
        }
        return (int) $page->ID;
    }
    $id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content, 'meta_input' => array( '_gk_restored_section' => 1, '_wp_page_template' => $template ) ) ), true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    return (int) $id;
}

function gk_sections_menu( string $lang, int $menu_id, array $old, int $pilgrim, int $sermons ): void {
    do_action( 'wpml_switch_language', $lang );
    $items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'publish' ) );
    if ( ! is_array( $items ) ) { throw new RuntimeException( "Menu {$menu_id} is missing." ); }
    $present = array_map( static fn( $item ) => (int) $item->ID, $items );
    foreach ( $old as $id ) { if ( ! in_array( $id, $present, true ) ) { throw new RuntimeException( "Menu {$menu_id} is no longer the expected menu." ); } }
    if ( ! in_array( count( $items ), array( 8, 9, 10 ), true ) ) { throw new RuntimeException( "Menu {$menu_id} has unexpected items; refusing to replace its structure." ); }
    foreach ( $items as $item ) {
        if ( ! in_array( (int) $item->ID, $old, true ) && ( 'post_type' !== $item->type || ! in_array( (int) $item->object_id, array( $pilgrim, $sermons ), true ) ) ) {
            throw new RuntimeException( "Menu {$menu_id} contains an unexpected custom item." );
        }
    }
    $new = array();
    foreach ( array( 'pilgrim' => $pilgrim, 'sermons' => $sermons ) as $key => $page_id ) {
        foreach ( $items as $item ) { if ( 'post_type' === $item->type && (int) $item->object_id === $page_id ) { $new[$key] = (int) $item->ID; break; } }
        if ( isset( $new[$key] ) ) { continue; }
        $id = wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => get_the_title( $page_id ), 'menu-item-object' => 'page', 'menu-item-object-id' => $page_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $new[$key] = (int) $id;
    }
    $labels = 'ru' === $lang
        ? array( 'schedule' => 'Расписание', 'donations' => 'Помочь' )
        : array( 'schedule' => 'Gottesdienste', 'donations' => 'Spenden' );
    $ordered = array( $old['home'], $old['about'], $old['photos'], $new['pilgrim'], $old['schedule'], $old['news'], $new['sermons'], $old['donations'], $old['contacts'], $old['requests'] );
    foreach ( $ordered as $position => $id ) {
        $update = array( 'ID' => $id, 'menu_order' => $position + 1 );
        if ( $id === $old['schedule'] ) { $update['post_title'] = $labels['schedule']; }
        if ( $id === $old['donations'] ) { $update['post_title'] = $labels['donations']; }
        $result = wp_update_post( wp_slash( $update ), true );
        if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
        $parent = $id === $old['photos'] ? $old['about'] : ( $id === $old['requests'] ? $old['contacts'] : 0 );
        update_post_meta( $id, '_menu_item_menu_item_parent', $parent );
    }
}

foreach ( array( 30 => array( 'post', 'Как доехать' ), 48 => array( 'post', 'Anfahrt' ), 34 => array( 'page', 'Расписание богослужений' ), 49 => array( 'page', 'Gottesdienstplan' ), 29 => array( 'page', 'Контакты' ), 71 => array( 'page', 'Kontakt' ) ) as $id => $expected ) {
    gk_sections_existing( $id, $expected[0], $expected[1] );
}
foreach ( array( 7 => 'Паломничество', 39 => 'Проповедь', 35 => 'Духовные мысли', 38 => 'Чтение Евангелия' ) as $id => $name ) {
    $term = get_term( $id, 'category' );
    if ( ! $term || is_wp_error( $term ) || $name !== $term->name ) { throw new RuntimeException( "Expected category {$id}: {$name}" ); }
}
do_action( 'wpml_switch_language', 'de' );
$wallfahrt = get_term( 21, 'category' );
if ( ! $wallfahrt || is_wp_error( $wallfahrt ) || 'Wallfahrt' !== $wallfahrt->name ) { throw new RuntimeException( 'Expected German Wallfahrt category.' ); }

do_action( 'wpml_switch_language', 'ru' );
$ru_pilgrim = '<!-- wp:paragraph --><p>Информация о поездке в обитель и материалы для паломников.</p><!-- /wp:paragraph -->'
    . gk_sections_link( 'Как доехать', get_permalink( 30 ) ) . gk_sections_link( 'Расписание богослужений', get_permalink( 34 ) ) . gk_sections_link( 'Связаться с обителью', get_permalink( 29 ) )
    . '<!-- wp:heading --><h2 class="wp-block-heading">Паломничество</h2><!-- /wp:heading -->' . gk_sections_query( array( 7 ) );
$ru_sermons = '<!-- wp:paragraph --><p>Проповеди, духовные мысли и чтения Евангелия из материалов монастыря.</p><!-- /wp:paragraph -->' . gk_sections_query( array( 39, 35, 38 ) );
$ru_pilgrim_id = gk_sections_page( 'palomniku', 'Паломнику', $ru_pilgrim, 'pilgrim.php' );
$ru_sermons_id = gk_sections_page( 'propovedi-i-besedy', 'Проповеди и беседы', $ru_sermons, 'catalog.php' );

do_action( 'wpml_switch_language', 'de' );
$de_pilgrim = '<!-- wp:paragraph --><p>Informationen für die Anreise und Beiträge für Pilger.</p><!-- /wp:paragraph -->'
    . gk_sections_link( 'Anfahrt', get_permalink( 48 ) ) . gk_sections_link( 'Gottesdienstplan', get_permalink( 49 ) ) . gk_sections_link( 'Kontakt', get_permalink( 71 ) )
    . '<!-- wp:heading --><h2 class="wp-block-heading">Wallfahrt</h2><!-- /wp:heading -->' . gk_sections_query( array( 21 ) );
$de_sermons = '<!-- wp:paragraph --><p>Für die vorhandenen Predigten und Gespräche liegen derzeit keine deutschen Übersetzungen vor. Die Originalbeiträge sind auf Russisch verfügbar.</p><!-- /wp:paragraph -->'
    . gk_sections_link( 'Predigten und Gespräche auf Russisch', '/propovedi-i-besedy/?lang=ru' );
$de_pilgrim_id = gk_sections_page( 'fuer-pilger', 'Für Pilger', $de_pilgrim, 'pilgrim.php' );
$de_sermons_id = gk_sections_page( 'predigten-und-gespraeche', 'Predigten und Gespräche', $de_sermons, 'catalog.php' );
$de_saved_content = get_post_field( 'post_content', $de_sermons_id );
$russian_target = 'href="/propovedi-i-besedy/?lang=ru"';
if ( ! str_contains( $de_saved_content, $russian_target ) ) {
    $updated_content = preg_replace( '~href="[^"]*">Predigten und Gespräche auf Russisch</a>~', $russian_target . '>Predigten und Gespräche auf Russisch</a>', $de_saved_content, 1, $replacements );
    if ( 1 !== $replacements ) { throw new RuntimeException( 'German source link is not in the expected content.' ); }
    // WPML rewrites this known cross-language URL on wp_update_post; preserve it in the generated page only.
    global $wpdb;
    if ( false === $wpdb->update( $wpdb->posts, array( 'post_content' => $updated_content ), array( 'ID' => $de_sermons_id ), array( '%s' ), array( '%d' ) ) ) {
        throw new RuntimeException( 'Could not save the Russian source link.' );
    }
    clean_post_cache( $de_sermons_id );
}

if ( has_filter( 'wpml_object_id' ) ) {
    foreach ( array( array( $ru_pilgrim_id, $de_pilgrim_id ), array( $ru_sermons_id, $de_sermons_id ) ) as $pair ) {
        $trid = apply_filters( 'wpml_element_trid', null, $pair[0], 'post_page' );
        if ( ! $trid ) { throw new RuntimeException( 'WPML did not register the Russian page.' ); }
        do_action( 'wpml_set_element_language_details', array( 'element_id' => $pair[1], 'element_type' => 'post_page', 'trid' => $trid, 'language_code' => 'de', 'source_language_code' => 'ru' ) );
    }
}

$pages = get_option( 'gkpc_pages', array() );
$pages['first-visit'] = array( 'id' => $ru_pilgrim_id );
$pages['sermons'] = array( 'id' => $ru_sermons_id );
update_option( 'gkpc_pages', $pages );

gk_sections_menu( 'ru', 44, array( 'home' => 717, 'about' => 718, 'schedule' => 719, 'news' => 720, 'photos' => 721, 'requests' => 722, 'donations' => 723, 'contacts' => 724 ), $ru_pilgrim_id, $ru_sermons_id );
gk_sections_menu( 'de', 45, array( 'home' => 725, 'about' => 726, 'schedule' => 727, 'news' => 728, 'photos' => 729, 'requests' => 730, 'donations' => 731, 'contacts' => 732 ), $de_pilgrim_id, $de_sermons_id );
do_action( 'wpml_switch_language', 'ru' );
WP_CLI::success( 'Restored visitor and sermons sections: ' . wp_json_encode( array( 'ru' => array( $ru_pilgrim_id, $ru_sermons_id ), 'de' => array( $de_pilgrim_id, $de_sermons_id ) ) ) );
