<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

/** Place images are independent from editor content and theme thumbnail support. */
final class Place_Media {
    public static function notice(): void {
        $key = 'gkpc_media_error_' . get_current_user_id();
        if ( $error = get_transient( $key ) ) { echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>'; delete_transient( $key ); }
    }
    public static function get( int $id ): array {
        $saved = get_post_meta( $id, '_gkpc_media', true );
        return is_array( $saved ) ? wp_parse_args( $saved, array( 'logo' => 0, 'photos' => array() ) ) : array( 'logo' => 0, 'photos' => get_post_thumbnail_id( $id ) ? array( get_post_thumbnail_id( $id ) ) : array() );
    }

    public static function store( int $id, array $input ): void {
        if ( ! in_array( get_post_type( $id ), Directory::location_types(), true ) || ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'upload_files' ) ) { throw new \RuntimeException( __( 'Access denied.', 'georg-kloster-prihod-core' ) ); }
        if ( ! isset( $input['logo'], $input['photos'] ) || ! is_scalar( $input['logo'] ) || ! is_array( $input['photos'] ) || count( $input['photos'] ) > 24 ) { throw new \InvalidArgumentException( __( 'Choose a logo and up to 24 photos from the media library.', 'georg-kloster-prihod-core' ) ); }
        $logo = absint( $input['logo'] ); $photos = array();
        foreach ( $input['photos'] as $image ) {
            if ( ! is_scalar( $image ) || ! ctype_digit( (string) $image ) ) { throw new \InvalidArgumentException( __( 'Select image attachments only.', 'georg-kloster-prihod-core' ) ); }
            if ( absint( $image ) ) { $photos[] = absint( $image ); }
        }
        foreach ( array_merge( $logo ? array( $logo ) : array(), $photos ) as $image ) {
            if ( 'attachment' !== get_post_type( $image ) || ! wp_attachment_is_image( $image ) || 'trash' === get_post_status( $image ) ) { throw new \InvalidArgumentException( __( 'Select image attachments only.', 'georg-kloster-prihod-core' ) ); }
        }
        update_post_meta( $id, '_gkpc_media', array( 'logo' => $logo, 'photos' => array_values( array_unique( $photos ) ) ) );
    }

    public static function assets(): void {
        $screen = get_current_screen();
        if ( ! $screen || ! in_array( $screen->post_type, Directory::location_types(), true ) || ! current_user_can( 'upload_files' ) ) { return; }
        wp_enqueue_media();
        wp_enqueue_script( 'gkpc-place-media', plugins_url( 'assets/place-media.js', GKPC_FILE ), array( 'media-views' ), GKPC_VERSION, true );
        wp_localize_script( 'gkpc-place-media', 'gkpcPlaceMedia', array( 'logo' => __( 'Choose a place logo', 'georg-kloster-prihod-core' ), 'photos' => __( 'Choose place photos', 'georg-kloster-prihod-core' ), 'use' => __( 'Use selected images', 'georg-kloster-prihod-core' ) ) );
    }

    public static function render_box( $post ): void {
        $media = self::get( $post->ID );
        wp_nonce_field( 'gkpc_media_' . $post->ID, 'gkpc_media_nonce' );
        echo '<p>' . esc_html__( 'These images are displayed separately from the description, in the Places block and the schedule for this place.', 'georg-kloster-prihod-core' ) . '</p>';
        foreach ( array( 'logo' => __( 'Logo', 'georg-kloster-prihod-core' ), 'photos' => __( 'Photos', 'georg-kloster-prihod-core' ) ) as $field => $label ) {
            $ids = 'logo' === $field ? ( $media['logo'] ? array( $media['logo'] ) : array() ) : $media['photos'];
            echo '<fieldset data-gkpc-media="' . esc_attr( $field ) . '"><legend><strong>' . esc_html( $label ) . '</strong></legend><input type="hidden" name="gkpc_media[' . esc_attr( $field ) . ']" value="' . esc_attr( implode( ',', $ids ) ) . '"><div class="gkpc-media-preview" aria-live="polite">';
            foreach ( $ids as $image ) { echo wp_get_attachment_image( $image, 'thumbnail', false, array( 'style' => 'width:100px;height:100px;object-fit:contain;margin:4px;' ) ); }
            echo '</div>';
            if ( current_user_can( 'upload_files' ) ) { echo '<p><button type="button" class="button" data-gkpc-media-choose>' . esc_html__( 'Choose images', 'georg-kloster-prihod-core' ) . '</button> <button type="button" class="button" data-gkpc-media-clear>' . esc_html__( 'Remove images', 'georg-kloster-prihod-core' ) . '</button></p>'; }
            echo '</fieldset>';
        }
    }

    public static function save( int $id ): void {
        if ( wp_is_post_autosave( $id ) || wp_is_post_revision( $id ) ) { return; }
        $nonce = $_POST['gkpc_media_nonce'] ?? '';
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'gkpc_media_' . $id ) || ! isset( $_POST['gkpc_media'] ) || ! is_array( $_POST['gkpc_media'] ) ) { return; }
        $input = wp_unslash( $_POST['gkpc_media'] );
        if ( ! isset( $input['logo'], $input['photos'] ) || ! is_string( $input['logo'] ) || ! is_string( $input['photos'] ) ) { return; }
        try { self::store( $id, array( 'logo' => $input['logo'], 'photos' => '' === $input['photos'] ? array() : explode( ',', $input['photos'] ) ) ); }
        catch ( \Throwable $error ) { set_transient( 'gkpc_media_error_' . get_current_user_id(), $error->getMessage(), 120 ); }
    }

    public static function render( int $id ): string {
        if ( 'publish' !== get_post_status( $id ) || ! in_array( get_post_type( $id ), Directory::location_types(), true ) ) { return ''; }
        $media = self::get( $id ); $html = '';
        if ( $media['logo'] && wp_attachment_is_image( $media['logo'] ) && 'trash' !== get_post_status( $media['logo'] ) ) { $html .= '<div class="gkpc-place-logo">' . wp_get_attachment_image( $media['logo'], 'medium' ) . '</div>'; }
        $photos = '';
        foreach ( $media['photos'] as $image ) {
            if ( wp_attachment_is_image( $image ) && 'trash' !== get_post_status( $image ) ) { $photos .= '<figure>' . wp_get_attachment_image( $image, 'large' ) . '</figure>'; }
        }
        if ( $photos ) { $html .= '<div class="gkpc-place-photos">' . $photos . '</div>'; }
        if ( $html ) { wp_enqueue_style( 'gkpc-directory' ); }
        return $html ? '<div class="gkpc-place-media">' . $html . '</div>' : '';
    }
}
