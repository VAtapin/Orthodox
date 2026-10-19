<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

final class Directory {
    public static function location_types(): array { return array( 'gkpc_location', 'gkpsch_location' ); }
    public static function places( array $args = array() ): array {
        return get_posts( array_merge( array( 'post_type' => self::location_types(), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ), $args ) );
    }
    public static function unified_list( $query ): void {
        if ( is_admin() && $query->is_main_query() && 'gkpc_location' === $query->get( 'post_type' ) ) { $query->set( 'post_type', self::location_types() ); }
    }
    public static function counts( $counts, string $type, string $permission ) {
        if ( 'gkpc_location' !== $type ) { return $counts; }
        $combined = clone $counts;
        foreach ( wp_count_posts( 'gkpsch_location', $permission ) as $status => $count ) { $combined->$status = ( $combined->$status ?? 0 ) + $count; }
        return $combined;
    }
    public static function register(): void {
        foreach ( array(
            'gkpc_location' => array( __( 'Service places', 'georg-kloster-prihod-core' ), __( 'Service place', 'georg-kloster-prihod-core' ) ),
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

    public static function register_legacy(): void {
        $args = get_post_type_object( 'gkpc_location' );
        // Existing places keep their IDs, metadata and every service reference.
        $caps = class_exists( '\GeorgKlosterPrihodSchedule\Capabilities' ) ? \GeorgKlosterPrihodSchedule\Capabilities::directory() : (array) $args->cap;
        register_post_type( 'gkpsch_location', array( 'labels' => (array) $args->labels, 'public' => false, 'show_ui' => true, 'show_in_menu' => false, 'show_in_rest' => true, 'supports' => array( 'title', 'editor', 'thumbnail', 'revisions' ), 'capabilities' => $caps, 'map_meta_cap' => false ) );
    }

    public static function fields( string $type ): array {
        if ( in_array( $type, self::location_types(), true ) ) {
            return array( 'address' => __( 'Address', 'georg-kloster-prihod-core' ), 'phone' => __( 'Phone', 'georg-kloster-prihod-core' ), 'email' => __( 'Email', 'georg-kloster-prihod-core' ), 'timezone' => __( 'Time zone (leave empty to use WordPress time zone)', 'georg-kloster-prihod-core' ) );
        }
        return 'gkpc_clergy' === $type ? array( 'rank' => __( 'Title', 'georg-kloster-prihod-core' ) ) : array();
    }

    public static function details( int $id ): array {
        $details = get_post_meta( $id, '_gkpc_details', true );
        $legacy = get_post_meta( $id, '_gkpsch_details', true );
        return array_merge( is_array( $legacy ) ? $legacy : array(), is_array( $details ) ? $details : array() );
    }

    public static function meta_boxes(): void {
        foreach ( array( 'gkpc_location', 'gkpsch_location', 'gkpc_clergy' ) as $type ) {
            add_meta_box( 'gkpc_details', __( 'Details', 'georg-kloster-prihod-core' ), array( self::class, 'render' ), $type );
            if ( in_array( $type, self::location_types(), true ) ) { add_meta_box( 'gkpc_place_media', __( 'Place logo and photos', 'georg-kloster-prihod-core' ), array( Place_Media::class, 'render_box' ), $type ); }
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
            if ( 'timezone' === $key && '' !== $value && ! in_array( $value, timezone_identifiers_list(), true ) ) { continue; }
            if ( 'email' === $key ) {
                if ( '' !== $value && ! is_email( $value ) ) { continue; }
                $value = sanitize_email( $value );
            } else { $value = sanitize_text_field( $value ); }
            $details[ $key ] = $value;
        }
        update_post_meta( $id, '_gkpc_details', wp_slash( $details ) );
        if ( in_array( $post->post_type, self::location_types(), true ) ) { do_action( 'wpml_register_single_string', 'georg-kloster-prihod-schedule', 'location-' . $id, get_the_title( $id ) ); }
    }
}
