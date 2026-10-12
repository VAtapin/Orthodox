<?php
// Runs only in the disposable design fixture, using its real attachment records.
$photo_id = $first['images']['monastery'];
$photo = prihod_theme_photo( $photo_id, 'large', array( 'class' => 'owner-photo', 'style' => 'object-position:center 25%' ), 'Owner caption', '', 'test-gallery' );
$check( str_contains( $photo, 'wp-lightbox-container' ) && str_contains( $photo, 'lightbox-trigger' ), 'Dynamic theme photos use the native WordPress viewer.' );
$check( str_contains( $photo, 'data-prihod-photo-group="test-gallery"' ) && str_contains( $photo, 'Owner caption' ), 'Photo group and author caption survive rendering.' );
$check( str_contains( $photo, 'owner-photo' ) && str_contains( $photo, 'object-position:center 25%' ), 'Photo classes and author styles survive rendering.' );
$portrait = prihod_theme_photo( $first['images']['clergy'], 'medium_large', array( 'class' => 'is-portrait' ) );
$portrait_tags = new WP_HTML_Tag_Processor( $portrait ); $portrait_tags->next_tag( 'FIGURE' ); $portrait_style = $portrait_tags->get_attribute( 'style' );
$portrait_tags->next_tag( 'IMG' );
$check( str_contains( $portrait_style, '--prihod-portrait-background:url(' ) && str_contains( $portrait_style, $portrait_tags->get_attribute( 'src' ) ), 'Portrait background uses the exact real foreground photograph, never a substitute asset.' );
$check( 1 === substr_count( $portrait, '<img' ) && ! str_contains( $photo, '--prihod-portrait-background' ), 'Portrait decoration has no second content image and leaves unmarked photographs unchanged.' );
$linked = prihod_theme_photo( $photo_id, 'medium', array(), '', home_url( '/article/' ) );
$check( (bool) preg_match( '/<a\b[^>]*>\s*<img\b[^>]*>\s*<\/a>\s*<button\b/s', $linked ) && ! preg_match( '/<a\b[^>]*>[^<]*<button\b/s', $linked ), 'Article links and native zoom button are siblings, never nested interactive controls.' );
$tags = new WP_HTML_Tag_Processor( $linked ); $tags->next_tag( 'IMG' );
$check( null === $tags->get_attribute( 'data-wp-on--click' ), 'Linked photo keeps its original destination instead of opening on image click.' );
$custom = '<figure class="wp-block-post-featured-image owner-figure" style="margin-top:7px"><a href="' . esc_url( home_url( '/article/' ) ) . '" target="_blank" rel="noopener">' . wp_get_attachment_image( $photo_id, 'large', false, array( 'alt' => 'Owner alternate text' ) ) . '</a></figure>';
$converted = prihod_theme_photo_html( $custom, $photo_id );
$check( str_contains( $converted, 'owner-figure' ) && str_contains( $converted, 'margin-top:7px' ) && str_contains( $converted, 'target="_blank" rel="noopener"' ) && str_contains( $converted, 'Owner alternate text' ), 'Featured photo preserves figure styling, alternative text and existing link attributes.' );
$check( '' === prihod_theme_photo( 0 ) && $custom === prihod_theme_photo_html( $custom, 0 ), 'Missing attachments do not create an empty viewer.' );
$thumbnail_post = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_thumbnail_id' ) )[0];
$thumbnail_filter = static fn( $html ) => str_replace( '<img ', '<img data-owner-thumbnail="yes" ', $html );
add_filter( 'post_thumbnail_html', $thumbnail_filter );
$filtered_thumbnail = prihod_theme_post_photo( $thumbnail_post );
remove_filter( 'post_thumbnail_html', $thumbnail_filter );
$check( str_contains( $filtered_thumbnail, 'data-owner-thumbnail="yes"' ), 'Post photographs retain the existing WordPress thumbnail filter pipeline.' );
$content = '<figure class="wp-block-image">' . wp_get_attachment_image( $photo_id, 'large' ) . '</figure>';
$image_block = static fn( array $attrs ) => render_block( array( 'blockName' => 'core/image', 'attrs' => array_merge( array( 'id' => $photo_id ), $attrs ), 'innerBlocks' => array(), 'innerHTML' => $content, 'innerContent' => array( $content ) ) );
$check( str_contains( $image_block( array() ), 'wp-lightbox-container' ), 'Existing native Image blocks inherit the theme viewer default.' );
$check( ! str_contains( $image_block( array( 'lightbox' => array( 'enabled' => false ) ) ), 'wp-lightbox-container' ), 'Author explicit viewer opt-out is retained.' );
$check( ! str_contains( $image_block( array( 'linkDestination' => 'custom' ) ), 'wp-lightbox-container' ), 'Native author image links retain WordPress link behavior.' );
$group = wp_unique_id( 'photo-smoke-' );
$gallery = prihod_theme_photo_gallery( '<div class="owner-gallery">' . prihod_theme_photo( $photo_id, 'large', array(), '', '', $group ) . prihod_theme_photo( $first['images']['clergy'], 'large', array(), '', '', $group ) . '</div>', $group );
$check( str_contains( $gallery, 'owner-gallery' ) && 2 === substr_count( $gallery, '<img' ), 'Dynamic photo group preserves its owner layout and actual images.' );
if ( function_exists( 'block_core_gallery_render_context' ) ) {
    $check( str_contains( $gallery, 'data-wp-interactive="core/gallery"' ), 'Current WordPress registers native gallery navigation for dynamic theme photographs.' );
}
