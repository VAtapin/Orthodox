<?php
defined( 'ABSPATH' ) || exit;

function prihod_theme_category_image_position_value( $value ): string {
    return is_string( $value ) && in_array( $value, array( 'center', 'top', 'bottom' ), true ) ? $value : 'center';
}

function prihod_theme_category_image_position(): string {
    $term = get_queried_object();
    if ( ! $term instanceof WP_Term || 'category' !== $term->taxonomy ) { return 'center'; }
    return prihod_theme_category_image_position_value( (string) get_term_meta( $term->term_id, '_prihod_category_card_image_position', true ) );
}

function prihod_theme_category_image_position_field( ?WP_Term $term = null ): void {
    $position = $term ? prihod_theme_category_image_position_value( (string) get_term_meta( $term->term_id, '_prihod_category_card_image_position', true ) ) : 'center';
    wp_nonce_field( 'prihod_category_card_image_position', 'prihod_category_card_image_position_nonce' );
    echo '<select id="prihod-category-card-image-position" name="prihod_category_card_image_position">';
    foreach ( array( 'center' => __( 'Center', 'georg-kloster-prihod' ), 'top' => __( 'Top', 'georg-kloster-prihod' ), 'bottom' => __( 'Bottom', 'georg-kloster-prihod' ) ) as $value => $label ) {
        echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $position, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select><p class="description">' . esc_html__( 'Choose which part of featured images appears in this category’s archive cards. Other categories keep their own setting.', 'georg-kloster-prihod' ) . '</p>';
}

add_action( 'category_add_form_fields', function (): void {
    echo '<div class="form-field term-prihod-category-card-image-position">';
    echo '<label for="prihod-category-card-image-position">' . esc_html__( 'Archive card image position', 'georg-kloster-prihod' ) . '</label>';
    prihod_theme_category_image_position_field();
    echo '</div>';
} );

add_action( 'category_edit_form_fields', function ( WP_Term $term ): void {
    echo '<tr class="form-field term-prihod-category-card-image-position"><th scope="row"><label for="prihod-category-card-image-position">' . esc_html__( 'Archive card image position', 'georg-kloster-prihod' ) . '</label></th><td>';
    prihod_theme_category_image_position_field( $term );
    echo '</td></tr>';
} );

function prihod_theme_save_category_image_position( int $term_id ): void {
    if ( ! isset( $_POST['prihod_category_card_image_position_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['prihod_category_card_image_position_nonce'] ) ), 'prihod_category_card_image_position' ) || ! current_user_can( 'manage_categories' ) ) { return; }
    $position = isset( $_POST['prihod_category_card_image_position'] ) ? prihod_theme_category_image_position_value( sanitize_key( wp_unslash( $_POST['prihod_category_card_image_position'] ) ) ) : 'center';
    if ( 'center' === $position ) {
        delete_term_meta( $term_id, '_prihod_category_card_image_position' );
    } else {
        update_term_meta( $term_id, '_prihod_category_card_image_position', $position );
    }
}
add_action( 'created_category', 'prihod_theme_save_category_image_position' );
add_action( 'edited_category', 'prihod_theme_save_category_image_position' );
