<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

final class Directory {
    public static function register(): void {
        foreach ( array(
            'gkpc_location' => array( __( 'Places', 'georg-kloster-prihod-core' ), __( 'Place', 'georg-kloster-prihod-core' ) ),
            'gkpc_clergy' => array( __( 'Clergy', 'georg-kloster-prihod-core' ), __( 'Clergy member', 'georg-kloster-prihod-core' ) ),
        ) as $type => $labels ) {
            register_post_type( $type, array(
                'labels' => array( 'name' => $labels[0], 'singular_name' => $labels[1] ),
                'public' => false, 'show_ui' => true, 'show_in_menu' => 'gkpc',
                'show_in_rest' => true, 'supports' => array( 'title', 'editor', 'thumbnail', 'revisions' ),
                'capabilities' => array(
                    'edit_post' => 'manage_gkpc_profile', 'read_post' => 'manage_gkpc_profile', 'delete_post' => 'manage_gkpc_profile',
                    'edit_posts' => 'manage_gkpc_profile', 'edit_others_posts' => 'manage_gkpc_profile',
                    'publish_posts' => 'manage_gkpc_profile', 'read_private_posts' => 'manage_gkpc_profile',
                    'delete_posts' => 'manage_gkpc_profile', 'delete_private_posts' => 'manage_gkpc_profile',
                    'delete_published_posts' => 'manage_gkpc_profile', 'delete_others_posts' => 'manage_gkpc_profile',
                    'edit_private_posts' => 'manage_gkpc_profile', 'edit_published_posts' => 'manage_gkpc_profile',
                    'create_posts' => 'manage_gkpc_profile',
                ), 'map_meta_cap' => false,
            ) );
        }
    }

    public static function fields( string $type ): array {
        if ( 'gkpc_location' === $type ) {
            return array( 'address' => __( 'Address', 'georg-kloster-prihod-core' ), 'phone' => __( 'Phone', 'georg-kloster-prihod-core' ), 'email' => __( 'Email', 'georg-kloster-prihod-core' ) );
        }
        return 'gkpc_clergy' === $type ? array( 'rank' => __( 'Title', 'georg-kloster-prihod-core' ) ) : array();
    }

    public static function details( int $id ): array {
        $details = get_post_meta( $id, '_gkpc_details', true );
        return is_array( $details ) ? $details : array();
    }

    public static function meta_boxes(): void {
        foreach ( array( 'gkpc_location', 'gkpc_clergy' ) as $type ) {
            add_meta_box( 'gkpc_details', __( 'Details', 'georg-kloster-prihod-core' ), array( self::class, 'render' ), $type );
        }
    }

    public static function render( $post ): void {
        wp_nonce_field( 'gkpc_details_' . $post->ID, 'gkpc_details_nonce' );
        $details = self::details( (int) $post->ID );
        foreach ( self::fields( $post->post_type ) as $key => $label ) {
            echo '<p><label for="gkpc-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><br><input class="widefat" id="gkpc-' . esc_attr( $key ) . '" name="gkpc_details[' . esc_attr( $key ) . ']" value="' . esc_attr( $details[ $key ] ?? '' ) . '"></p>';
        }
    }

    public static function save( int $id ): void {
        if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! current_user_can( 'edit_post', $id ) ) { return; }
        $nonce = $_POST['gkpc_details_nonce'] ?? '';
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'gkpc_details_' . $id ) ) { return; }
        $post = get_post( $id );
        if ( ! $post ) { return; }
        $input = $_POST['gkpc_details'] ?? array();
        if ( ! is_array( $input ) ) { return; }
        $details = self::details( $id );
        foreach ( self::fields( $post->post_type ) as $key => $label ) {
            if ( ! isset( $input[ $key ] ) || ! is_string( $input[ $key ] ) ) { continue; }
            $value = trim( wp_unslash( $input[ $key ] ) );
            if ( 'email' === $key ) {
                if ( '' !== $value && ! is_email( $value ) ) { continue; }
                $value = sanitize_email( $value );
            } else { $value = sanitize_text_field( $value ); }
            $details[ $key ] = $value;
        }
        update_post_meta( $id, '_gkpc_details', $details );
    }
}
