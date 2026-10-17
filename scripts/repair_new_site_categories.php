<?php
/** Remove the destination default category accidentally added during migration. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This repair is limited to the test WordPress.' );
}

global $wpdb;
$default = (int) get_option( 'default_category' );
$slug = $wpdb->get_var( $wpdb->prepare( "SELECT slug FROM {$wpdb->terms} WHERE term_id=%d", $default ) );
if ( 'uncategorized' !== $slug ) {
    throw new RuntimeException( 'Unexpected default category.' );
}
$ids = $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id=p.ID WHERE p.post_type='post' AND m.meta_key='_gkm_source_id' ORDER BY p.ID" );
$affected = array();
$only_default = array();
foreach ( $ids as $id ) {
    $id = (int) $id;
    $categories = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT tt.term_id FROM {$wpdb->term_relationships} r INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=r.term_taxonomy_id WHERE r.object_id=%d AND tt.taxonomy='category' ORDER BY tt.term_id",
        $id
    ) ) );
    if ( ! in_array( $default, $categories, true ) ) { continue; }
    if ( count( $categories ) < 2 ) { $only_default[] = $id; continue; }
    $affected[ $id ] = $categories;
}

echo 'Imported posts: ' . count( $ids ) . ', extra default category: ' . count( $affected ) . ', only default: ' . count( $only_default ) . PHP_EOL;
if ( $only_default ) { echo 'Posts requiring source-category investigation: ' . implode( ',', $only_default ) . PHP_EOL; }
if ( '1' !== getenv( 'GK_REPAIR_APPLY' ) ) { return; }

if ( $affected ) {
    $backup = '/var/www/vhosts/georg-kloster.ru/private/codex-migration-20261017/categories-before-repair.json';
    if ( file_exists( $backup ) || false === file_put_contents( $backup, wp_json_encode( $affected, JSON_PRETTY_PRINT ), LOCK_EX ) ) {
        throw new RuntimeException( 'Cannot create a fresh category recovery file.' );
    }
    foreach ( $affected as $id => $_categories ) {
        $result = wp_remove_object_terms( $id, $default, 'category' );
        if ( is_wp_error( $result ) ) { throw new RuntimeException( 'Could not repair post ' . $id . ': ' . $result->get_error_message() ); }
    }
    echo 'Removed extra category from ' . count( $affected ) . ' posts. Recovery file: ' . $backup . PHP_EOL;
}
