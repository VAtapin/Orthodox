<?php
defined( 'ABSPATH' ) || exit;

/** Whether the current post asks its featured image to float beside the text. */
function prihod_theme_wrap_featured_image( ?int $post_id = null ): bool {
    $post_id = $post_id ?: get_the_ID();
    return $post_id > 0 && '1' === (string) get_post_meta( $post_id, '_prihod_wrap_featured_image', true );
}

/** The icon archive has its own image crop and card treatment. */
function prihod_theme_is_icons_category(): bool {
    $term = get_queried_object();
    if ( ! $term instanceof WP_Term || 'category' !== $term->taxonomy ) { return false; }
    $slug = rawurldecode( (string) $term->slug );
    return in_array( $slug, array( 'иконы-храма', 'ikony-hrama' ), true );
}

add_action( 'add_meta_boxes_post', function (): void {
    add_meta_box( 'prihod-featured-image-layout', __( 'Featured image layout', 'georg-kloster-prihod' ), 'prihod_theme_featured_image_layout_box', 'post', 'side' );
} );

function prihod_theme_featured_image_layout_box( WP_Post $post ): void {
    wp_nonce_field( 'prihod_featured_image_layout_' . $post->ID, 'prihod_featured_image_layout_nonce' );
    ?>
    <label><input type="checkbox" name="prihod_wrap_featured_image" value="1" <?php checked( prihod_theme_wrap_featured_image( $post->ID ) ); ?>> <?php esc_html_e( 'Wrap text around the featured image', 'georg-kloster-prihod' ); ?></label>
    <p class="description"><?php esc_html_e( 'Place the featured image beside the text and let text flow around it.', 'georg-kloster-prihod' ); ?></p>
    <?php
}

add_action( 'save_post_post', function ( int $post_id ): void {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) { return; }
    if ( ! isset( $_POST['prihod_featured_image_layout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['prihod_featured_image_layout_nonce'] ) ), 'prihod_featured_image_layout_' . $post_id ) ) { return; }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
    if ( isset( $_POST['prihod_wrap_featured_image'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['prihod_wrap_featured_image'] ) ) ) {
        update_post_meta( $post_id, '_prihod_wrap_featured_image', '1' );
    } else {
        delete_post_meta( $post_id, '_prihod_wrap_featured_image' );
    }
} );
