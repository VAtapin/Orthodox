<?php
defined( 'ABSPATH' ) || exit;

/** Render through the installed WordPress Image block, retaining its viewer. */
function prihod_theme_photo( int $id, string $size = 'large', array $attributes = array(), string $caption = '', string $href = '', string $group = '' ): string {
    if ( ! wp_attachment_is_image( $id ) ) { return ''; }
    $attributes['class'] = trim( ( $attributes['class'] ?? '' ) . ' wp-image-' . $id );
    $image = wp_get_attachment_image( $id, $size, false, $attributes );
    $portrait = in_array( 'is-portrait', preg_split( '/\s+/', $attributes['class'] ), true );
    $background = '';
    if ( $portrait ) { $tags = new WP_HTML_Tag_Processor( $image ); if ( $tags->next_tag( 'IMG' ) ) { $background = $tags->get_attribute( 'src' ); } }
    $style = $background ? ' style="' . esc_attr( '--prihod-portrait-background:url(' . wp_json_encode( esc_url_raw( $background ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ')' ) . '"' : '';
    $content = '<figure class="wp-block-image prihod-photo-item' . ( $portrait ? ' is-portrait' : '' ) . '"' . $style . '>' . $image . ( $caption ? '<figcaption>' . wp_kses_post( $caption ) . '</figcaption>' : '' ) . '</figure>';
    if ( $href ) { $content = str_replace( $image, '<a href="' . esc_url( $href ) . '">' . $image . '</a>', $content ); }
    return prihod_theme_photo_html( $content, $id, $group );
}

/** Keep WordPress's thumbnail filters and deliberate suppression of an image. */
function prihod_theme_post_photo( $post, string $size = 'medium_large', string $href = '' ): string {
    $image = get_the_post_thumbnail( $post, $size );
    if ( ! $image ) { return ''; }
    if ( $href && ! preg_match( '/<a\b/i', $image ) ) { $image = '<a href="' . esc_url( $href ) . '">' . $image . '</a>'; }
    return prihod_theme_photo_html( '<figure class="wp-block-image prihod-photo-item">' . $image . '</figure>', get_post_thumbnail_id( $post ) );
}

/** Preserve author markup and links while keeping the native zoom button outside links. */
function prihod_theme_photo_html( string $content, int $id, string $group = '' ): string {
    if ( ! $id || ! wp_attachment_is_image( $id ) || is_admin() ) { return $content; }
    $anchor = '';
    $content = preg_replace_callback( '/(<a\b[^>]*>)(\s*<img\b[^>]*>\s*)<\/a>/s', static function ( $match ) use ( &$anchor ) { $anchor = $match[1]; return $match[2]; }, $content, 1 );
    $tags = new WP_HTML_Tag_Processor( $content );
    if ( $tags->next_tag( 'IMG' ) ) { $tags->add_class( 'wp-image-' . $id ); }
    $content = $tags->get_updated_html();
    $parsed = array( 'blockName' => 'core/image', 'attrs' => array( 'id' => $id, 'lightbox' => array( 'enabled' => true ) ), 'innerBlocks' => array(), 'innerHTML' => $content, 'innerContent' => array( $content ) );
    $block = new WP_Block( $parsed, $group ? array( 'galleryId' => $group ) : array() );
    $html = $block->render();
    $tags = new WP_HTML_Tag_Processor( $html );
    if ( $tags->next_tag( 'FIGURE' ) ) {
        $tags->set_attribute( 'data-prihod-photo-id', (string) $id );
        if ( $group ) { $tags->set_attribute( 'data-prihod-photo-group', $group ); }
    }
    if ( $anchor && $tags->next_tag( 'IMG' ) ) { $tags->remove_attribute( 'data-wp-on--click' ); }
    $html = $tags->get_updated_html();
    // Keep the image's original destination; the native enlarge button is its sibling.
    if ( $anchor ) { $html = preg_replace_callback( '/<img\b[^>]*>/', static fn( $match ) => $anchor . $match[0] . '</a>', $html, 1 ); }
    return $html;
}

/** Let the installed Gallery block register native image order/navigation. */
function prihod_theme_photo_gallery( string $html, string $group ): string {
    if ( ! function_exists( 'block_core_gallery_render_context' ) || ! $html ) { return $html; }
    $parsed = array( 'blockName' => 'core/gallery', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => $html, 'innerContent' => array( $html ) );
    return block_core_gallery_render( array(), $html, new WP_Block( $parsed, array( 'galleryId' => $group ) ) );
}

add_filter( 'render_block_core/post-featured-image', static function ( string $html, array $parsed, WP_Block $block ): string {
    return prihod_theme_photo_html( $html, get_post_thumbnail_id( absint( $block->context['postId'] ?? get_the_ID() ) ) );
}, 20, 3 );

add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_script( 'prihod-theme-photos', get_template_directory_uri() . '/assets/photos.js', array(), '0.3.19', true );
    wp_enqueue_style( 'prihod-theme-photos', get_template_directory_uri() . '/assets/photos.css', array( 'prihod-theme-reference' ), '0.3.19.' . filemtime( get_template_directory() . '/assets/photos.css' ) );
    wp_localize_script( 'prihod-theme-photos', 'prihodPhotoLabels', array( 'previous' => __( 'Предыдущее фото', 'prihod-theme' ), 'next' => __( 'Следующее фото', 'prihod-theme' ) ) );
} );
