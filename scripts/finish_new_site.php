<?php
/** Run once with WP-CLI on the new test site; never on the old production site. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'new.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
    throw new RuntimeException( 'This setup script is restricted to new.georg-kloster.ru.' );
}

function gkf_image( string $name ): int {
    global $wpdb;
    $id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id LIMIT 1", '%' . $wpdb->esc_like( $name ) . '%' ) );
    return $id && wp_attachment_is_image( (int) $id ) ? (int) $id : 0;
}

function gkf_lightbox( string $content ): string {
    $content = preg_replace_callback( '~<!-- wp:image (\{[^\n]*?\}) -->(.*?)<!-- /wp:image -->~s', static function ( array $parts ): string {
        $attrs = json_decode( $parts[1], true );
        if ( ! is_array( $attrs ) || 'media' !== ( $attrs['linkDestination'] ?? '' ) ) { return $parts[0]; }
        unset( $attrs['linkDestination'] );
        $attrs['lightbox'] = array( 'enabled' => true );
        $html = preg_replace( '~<a\b[^>]*>\s*(<img\b[^>]*>)\s*</a>~s', '$1', $parts[2], 1 );
        return '<!-- wp:image ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' -->' . $html . '<!-- /wp:image -->';
    }, $content );
    return preg_replace_callback( '~<!-- wp:gallery (\{[^\n]*?\}) -->~', static function ( array $parts ): string {
        $attrs = json_decode( $parts[1], true );
        if ( ! is_array( $attrs ) || 'media' !== ( $attrs['linkTo'] ?? '' ) ) { return $parts[0]; }
        unset( $attrs['linkTo'] );
        return $attrs ? '<!-- wp:gallery ' . wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' -->' : '<!-- wp:gallery -->';
    }, $content );
}

function gkf_home( int $id, string $heading, string $link_label, array $photos, int $gallery_id ): void {
    $post = get_post( $id );
    if ( ! $post || ! in_array( $post->post_title, array( 'Главная', 'Startseite' ), true ) ) { throw new RuntimeException( "Unexpected home page {$id}." ); }
    if ( ! str_contains( $post->post_content, 'wp:gallery' ) ) { throw new RuntimeException( "Home page {$id} has been edited; refusing to replace its content." ); }
    $items = '';
    foreach ( $photos as $image_id ) {
        $src = wp_get_attachment_image_url( $image_id, 'large' );
        $alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ?: get_the_title( $image_id );
        $items .= '<!-- wp:image {"id":' . $image_id . ',"sizeSlug":"large","lightbox":{"enabled":true}} --><figure class="wp-block-image size-large"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $image_id . '"/></figure><!-- /wp:image -->';
    }
    $url = get_permalink( $gallery_id );
    $content = '<!-- wp:group {"className":"prihod-home-gallery"} --><div class="wp-block-group prihod-home-gallery"><!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2><!-- /wp:heading --><!-- wp:gallery {"columns":5,"className":"prihod-photo-strip"} --><figure class="wp-block-gallery has-nested-images columns-5 is-cropped prihod-photo-strip">' . $items . '</figure><!-- /wp:gallery --><!-- wp:paragraph {"className":"prihod-gallery-more"} --><p class="prihod-gallery-more"><a href="' . esc_url( $url ) . '">' . esc_html( $link_label ) . ' →</a></p><!-- /wp:paragraph --></div><!-- /wp:group -->';
    wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $content ) ) );
}

$photos = array(
    gkf_image( '90206520.jpeg' ),
    gkf_image( '524331069_10240316547955133_7476596320833021101_n.jpg' ),
    gkf_image( 'troica-2026.jpeg' ),
    gkf_image( '47386829-6996-4c7f-b58f-b70a0662e445.jpg' ),
    gkf_image( 'WhatsApp-Bild-2025-09-16-um-19.07.50_8b455da6.jpg' ),
);
if ( in_array( 0, $photos, true ) ) { throw new RuntimeException( 'A reference photo is missing from imported media.' ); }

foreach ( array( 90, 716 ) as $id ) {
    $post = get_post( $id );
    if ( ! $post || ! str_contains( $post->post_content, 'wp:gallery' ) ) { throw new RuntimeException( "Gallery page {$id} is missing." ); }
    $updated = gkf_lightbox( $post->post_content );
    if ( $updated !== $post->post_content ) { wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $updated ) ) ); }
}

gkf_home( 16, 'Жизнь обители в фотографиях', 'Все фотографии', $photos, 90 );
gkf_home( 613, 'Klosterleben in Bildern', 'Alle Fotos', $photos, 716 );

$pages = get_option( 'gkpc_pages', array() );
foreach ( array( 'schedule' => $photos[2], 'news' => $photos[2], 'requests' => $photos[1], 'donations' => gkf_image( 'monastyr.jpg' ), 'about' => $photos[0] ) as $key => $image ) {
    $page = (int) ( $pages[$key]['id'] ?? 0 );
    if ( ! $page || ! $image ) { continue; }
    set_post_thumbnail( $page, $image );
    $de = (int) apply_filters( 'wpml_object_id', $page, 'page', false, 'de' );
    if ( $de && $de !== $page ) { set_post_thumbnail( $de, $image ); }
}
WP_CLI::success( 'Home galleries, native lightbox and feature card photos configured.' );
