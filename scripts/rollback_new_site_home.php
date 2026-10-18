<?php
/** Roll back the cancelled homepage/widget conversion on the test site only. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'Rollback is restricted to the test WordPress.' );
}
global $wpdb;
$private = '/var/www/vhosts/georg-kloster.ru/private/codex-migration-20261017/';
$read = static fn( $name ) => json_decode( file_get_contents( $private . $name . '.json' ), true, 512, JSON_THROW_ON_ERROR );
$before = array_column( $read( 'audit-restore-before' )['posts'], null, 'ID' );
$presentation = $read( 'restore-presentation' );
$plan = $read( 'restore-content-plan' );
$original = $read( 'restore-content-backup' );
$backup = array( 'posts' => array(), 'widgets' => get_option( 'widget_block' ), 'sidebars' => get_option( 'sidebars_widgets' ), 'options' => get_option( 'georg_kloster_prihod_options' ) );
$updates = array();
foreach ( $presentation['languages'] as $language => $saved ) {
    $id = (int) $saved['home_id'];
    $post = get_post( $id );
    if ( ! $post || ! isset( $before[$id] ) ) { throw new RuntimeException( 'Original homepage snapshot is missing.' ); }
    if ( $post->post_content === $before[$id]['post_content'] ) { continue; }
    if ( ! str_contains( $post->post_content, 'prihod-home-hero' ) ) { throw new RuntimeException( 'Unexpected current homepage structure.' ); }
    $backup['posts'][$id] = get_post( $id, ARRAY_A );
    $updates[$id] = $before[$id]['post_content'];
}
$widgets = (array) $backup['widgets']; $sidebars = (array) $backup['sidebars'];
foreach ( $plan['widgets'] as $area => $content ) {
    foreach ( $sidebars[$area] ?? array() as $widget ) {
        $id = (int) str_replace( 'block-', '', $widget );
        if ( ( $widgets[$id]['content'] ?? null ) !== $content ) { throw new RuntimeException( 'Widget content changed outside the cancelled conversion.' ); }
        unset( $widgets[$id] );
    }
    if ( array_key_exists( $area, (array) $original['sidebars'] ) ) { $sidebars[$area] = $original['sidebars'][$area]; }
    else { unset( $sidebars[$area] ); }
}
$options = (array) $backup['options'];
foreach ( $plan['options'] as $key => $value ) {
    if ( ! array_key_exists( $key, (array) $original['options'] ) && ( $options[$key] ?? null ) === $value ) { unset( $options[$key] ); }
}
if ( 'apply' !== getenv( 'GK_ROLLBACK_MODE' ) ) {
    WP_CLI::success( 'Prepared original homepages: ' . implode( ',', array_keys( $updates ) ) . '; four cancelled widget areas. No changes applied.' );
    return;
}
$backup_path = $private . 'home-theme-before-rollback.json';
if ( is_file( $backup_path ) ) { throw new RuntimeException( 'Rollback backup already exists; refusing a repeat.' ); }
if ( false === file_put_contents( $backup_path, wp_json_encode( $backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) { throw new RuntimeException( 'Cannot save rollback backup.' ); }
foreach ( $updates as $id => $content ) {
    // Avoid the WPML save hooks that changed term memberships during conversion.
    if ( false === $wpdb->update( $wpdb->posts, array( 'post_content' => $content, 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ), array( 'ID' => $id ) ) ) { throw new RuntimeException( 'Homepage restore failed.' ); }
    clean_post_cache( $id );
    if ( get_post( $id )->post_content !== $content ) { throw new RuntimeException( 'Homepage verification failed.' ); }
}
update_option( 'widget_block', $widgets );
update_option( 'sidebars_widgets', $sidebars );
update_option( 'georg_kloster_prihod_options', $options );
WP_CLI::success( 'Original homepage content restored; cancelled widget conversion removed. Articles and media were not changed.' );
