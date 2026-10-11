<?php
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
if ( is_plugin_active( 'prihod/prihod.php' ) ) { throw new RuntimeException( 'Standalone check needs inactive core.' ); }
switch_theme( 'prihod-theme' );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Ordinary page', 'post_content' => '<!-- wp:paragraph --><p>Standalone content</p><!-- /wp:paragraph -->' ) );
$GLOBALS['post'] = get_post( $page );
$html = do_blocks( get_block_template( 'prihod-theme//page', 'wp_template' )->content );
if ( ! str_contains( $html, 'Standalone content' ) ) { throw new RuntimeException( 'Theme must work without core.' ); }
file_put_contents( __DIR__ . '/.theme-result.json', wp_json_encode( array( 'status' => 'THEME_ALONE_OK', 'wordpress' => get_bloginfo( 'version' ) ) ) );
