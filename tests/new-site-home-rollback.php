<?php
/** Read-only verification of the explicitly requested homepage/theme rollback. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) { exit( 1 ); }
$private = '/var/www/vhosts/georg-kloster.ru/private/codex-migration-20261017/';
$before = array_column( json_decode( file_get_contents( $private . 'audit-restore-before.json' ), true, 512, JSON_THROW_ON_ERROR )['posts'], null, 'ID' );
$presentation = json_decode( file_get_contents( $private . 'restore-presentation.json' ), true, 512, JSON_THROW_ON_ERROR );
$plan = json_decode( file_get_contents( $private . 'restore-content-plan.json' ), true, 512, JSON_THROW_ON_ERROR );
$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } };
$check( wp_get_theme()->get( 'Version' ) === '0.4.25', 'Previous theme version is not installed.' );
$check( function_exists( 'prihod_theme_home_content' ), 'Original homepage renderer is missing.' );
$check( ! function_exists( 'prihod_theme_widget_area' ), 'Cancelled widget rendering remains active.' );
$homes = array();
foreach ( $presentation['languages'] as $language => $saved ) {
    $id = (int) $saved['home_id']; $homes[] = $id;
    $content = get_post( $id )->post_content;
    $check( $content === $before[$id]['post_content'], 'Homepage does not match its original snapshot: ' . $language );
    $check( ! str_contains( $content, 'prihod-home-hero' ) && ! str_contains( $content, 'prihod-restored-prose' ), 'Generated theme layout remains in page content.' );
}
foreach ( $plan['posts'] as $action ) {
    if ( in_array( (int) $action['id'], $homes, true ) ) { continue; }
    $check( get_post( $action['id'] )->post_content === $action['content'], 'Rollback unexpectedly changed another restored article: ' . $action['id'] );
}
$sidebars = get_option( 'sidebars_widgets', array() );
foreach ( array_keys( $plan['widgets'] ) as $area ) { $check( empty( $sidebars[$area] ), 'A cancelled widget region remains populated.' ); }
$check( is_file( $private . 'home-theme-before-rollback.json' ), 'Rollback backup is missing.' );
WP_CLI::success( $checks . ' rollback checks passed; original theme and both homepages restored, other article content preserved.' );
