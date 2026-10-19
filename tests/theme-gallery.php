<?php
/** Native gallery regression fixture for disposable WordPress Playground only. */
if ( ! is_file( '/wordpress/wp-load.php' ) ) { throw new RuntimeException( 'Requires disposable WordPress Playground.' ); }
require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$images = '';
foreach ( range( 1, 5 ) as $index ) {
    $file = wp_upload_bits( 'gallery-test-' . $index . '.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP1cAAAAASUVORK5CYII=' ) );
    $id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_title' => 'Gallery test ' . $index ), $file['file'] );
    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file['file'] ) );
    $images .= '<!-- wp:image {"id":' . $id . ',"sizeSlug":"large","linkDestination":"none","lightbox":{"enabled":true}} --><figure class="wp-block-image size-large"><img src="' . esc_url( wp_get_attachment_url( $id ) ) . '" alt="Gallery test" class="wp-image-' . $id . '"/></figure><!-- /wp:image -->';
}
$content = '<!-- wp:group {"className":"prihod-home-gallery"} --><div class="wp-block-group prihod-home-gallery"><!-- wp:heading --><h2 class="wp-block-heading">Gallery test</h2><!-- /wp:heading --><!-- wp:gallery {"columns":5,"linkTo":"none","className":"prihod-photo-strip"} --><figure class="wp-block-gallery has-nested-images columns-5 is-cropped prihod-photo-strip">' . $images . '</figure><!-- /wp:gallery --><!-- wp:paragraph {"className":"prihod-gallery-more"} --><p class="prihod-gallery-more"><a href="/">All photographs</a></p><!-- /wp:paragraph --></div><!-- /wp:group -->';
$gallery_page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Gallery fixture', 'post_content' => $content ) );
$stored_content = get_post( $gallery_page )->post_content;
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $gallery_page );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $gallery_page ) ); $GLOBALS['wp_query']->the_post();
$checks = 0;
foreach ( array_keys( prihod_theme_schemes() ) as $scheme ) {
    $sections = array_fill_keys( array_keys( prihod_theme_home_sections() ), array( 'enabled' => false ) );
    $sections['content']['enabled'] = true;
    update_option( 'georg_kloster_prihod_options', array( 'scheme' => $scheme, 'home' => array( 'sections' => $sections ) ) );
    $html = prihod_theme_home();
    if ( ! str_contains( $html, 'prihod-home-gallery' ) || ! str_contains( $html, 'All photographs' ) || 5 !== substr_count( $html, 'alt="Gallery test"' ) ) { throw new RuntimeException( 'Native gallery content lost: ' . $scheme ); }
    ++$checks;
    if ( get_post( $gallery_page )->post_content !== $stored_content ) { throw new RuntimeException( 'Gallery content was rewritten.' ); }
    ++$checks;
}
echo 'THEME_GALLERY_OK ' . $checks;
