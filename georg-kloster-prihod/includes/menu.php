<?php

defined( 'ABSPATH' ) || exit;

/** Optional image for a standard WordPress menu item. */
function prihod_theme_menu_image_id( WP_Post $item ): int {
    $chosen = absint( get_post_meta( $item->ID, '_prihod_menu_image', true ) );
    if ( $chosen && wp_attachment_is_image( $chosen ) ) { return $chosen; }
    if ( 'post_type' === $item->type && $item->object_id ) {
        return absint( get_post_thumbnail_id( $item->object_id ) );
    }
    return 0;
}

add_filter( 'nav_menu_item_title', function ( string $title, WP_Post $item, stdClass $args ): string {
    if ( empty( $args->prihod_visual ) ) { return $title; }
    $image_id = prihod_theme_menu_image_id( $item );
    $image = $image_id ? wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'prihod-menu-image', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(max-width: 600px) 100vw, 360px' ) ) : '';
    $description = trim( (string) $item->description );
    return ( $image ? '<span class="prihod-menu-picture">' . $image . '</span>' : '' )
        . '<span class="prihod-menu-copy"><span class="prihod-menu-title">' . esc_html( wp_strip_all_tags( $title ) ) . '</span>'
        . ( $description ? '<span class="prihod-menu-description">' . esc_html( $description ) . '</span>' : '' ) . '</span>';
}, 10, 3 );

add_action( 'wp_nav_menu_item_custom_fields', function ( int $item_id ): void {
    $image_id = absint( get_post_meta( $item_id, '_prihod_menu_image', true ) );
    $preview = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'class' => 'prihod-menu-admin-preview', 'alt' => '' ) ) : '';
    echo '<p class="description description-wide prihod-menu-image-field"><label for="edit-menu-item-prihod-image-' . $item_id . '">'
        . esc_html__( 'Изображение пункта меню', 'georg-kloster-prihod' ) . '</label><br>'
        . '<span class="prihod-menu-admin-image">' . $preview . '</span>'
        . '<input id="edit-menu-item-prihod-image-' . $item_id . '" type="hidden" name="prihod_menu_image[' . $item_id . ']" value="' . $image_id . '">'
        . '<button type="button" class="button prihod-menu-image-select">' . esc_html__( 'Выбрать изображение', 'georg-kloster-prihod' ) . '</button> '
        . '<button type="button" class="button-link prihod-menu-image-remove"' . ( $image_id ? '' : ' hidden' ) . '>' . esc_html__( 'Удалить', 'georg-kloster-prihod' ) . '</button>'
        . wp_nonce_field( 'prihod_menu_image_' . $item_id, 'prihod_menu_image_nonce[' . $item_id . ']', true, false )
        . '</p>';
} );

add_action( 'wp_update_nav_menu_item', function ( int $menu_id, int $item_id ): void {
    if ( ! current_user_can( 'edit_theme_options' ) || ! isset( $_POST['prihod_menu_image_nonce'][$item_id], $_POST['prihod_menu_image'][$item_id] ) ) { return; }
    $nonce = sanitize_text_field( wp_unslash( $_POST['prihod_menu_image_nonce'][$item_id] ) );
    if ( ! wp_verify_nonce( $nonce, 'prihod_menu_image_' . $item_id ) ) { return; }
    $image_id = absint( wp_unslash( $_POST['prihod_menu_image'][$item_id] ) );
    if ( $image_id && wp_attachment_is_image( $image_id ) ) {
        update_post_meta( $item_id, '_prihod_menu_image', $image_id );
    } else {
        delete_post_meta( $item_id, '_prihod_menu_image' );
    }
}, 10, 2 );

add_action( 'admin_enqueue_scripts', function ( string $hook ): void {
    if ( 'nav-menus.php' !== $hook ) { return; }
    wp_enqueue_media();
    wp_enqueue_script( 'prihod-menu-admin', get_template_directory_uri() . '/assets/menu-admin.js', array( 'media-views' ), wp_get_theme()->get( 'Version' ), true );
    wp_add_inline_script( 'prihod-menu-admin', 'window.prihodMenuMedia = ' . wp_json_encode( array( 'title' => __( 'Изображение пункта меню', 'georg-kloster-prihod' ), 'select' => __( 'Использовать изображение', 'georg-kloster-prihod' ) ) ) . ';', 'before' );
} );
