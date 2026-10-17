<?php
namespace GeorgKlosterPrihodSchedule;

defined( 'ABSPATH' ) || exit;

final class Directory {
    public static function register(): void {
        register_post_type( 'gkpsch_location', array(
            'labels' => array( 'name' => __( 'Service places', 'georg-kloster-prihod-schedule' ), 'singular_name' => __( 'Service place', 'georg-kloster-prihod-schedule' ) ),
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'gkpsch', 'show_in_rest' => false,
            'supports' => array( 'title', 'editor' ), 'capabilities' => Capabilities::directory(), 'map_meta_cap' => false,
        ) );
    }
    public static function details( int $id ): array {
        $details = get_post_meta( $id, '_gkpsch_details', true );
        return is_array( $details ) ? $details : array();
    }
    public static function title( int $id ): string {
        $title = get_the_title( $id );
        $translated = apply_filters( 'wpml_translate_single_string', false, 'georg-kloster-prihod-schedule', 'location-' . $id );
        return is_string( $translated ) && '' !== $translated ? $translated : $title;
    }
    public static function public_title( int $id ): string {
        $places = get_posts( array( 'post_type' => 'gkpsch_location', 'post_status' => 'publish', 'numberposts' => 2, 'fields' => 'ids' ) );
        return count( $places ) > 1 ? self::title( $id ) : '';
    }
    public static function meta_boxes(): void {
        add_meta_box( 'gkpsch_place', __( 'Service place', 'georg-kloster-prihod-schedule' ), array( self::class, 'render' ), 'gkpsch_location' );
    }
    public static function render( $post ): void {
        wp_nonce_field( 'gkpsch_place_' . $post->ID, 'gkpsch_place_nonce' );
        $details = self::details( (int) $post->ID );
        echo '<p><label>' . esc_html__( 'Time zone (leave empty to use WordPress time zone)', 'georg-kloster-prihod-schedule' ) . '<br><input class="widefat" name="gkpsch_timezone" value="' . esc_attr( $details['timezone'] ?? '' ) . '"></label></p>';
    }
    public static function save( int $id ): void {
        if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) || ! current_user_can( 'manage_gkpsch_schedule' ) ) { return; }
        $nonce = $_POST['gkpsch_place_nonce'] ?? '';
        if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'gkpsch_place_' . $id ) ) { return; }
        $timezone = $_POST['gkpsch_timezone'] ?? '';
        if ( ! is_string( $timezone ) ) { return; }
        $timezone = trim( wp_unslash( $timezone ) );
        if ( '' !== $timezone && ! in_array( $timezone, timezone_identifiers_list(), true ) ) { return; }
        update_post_meta( $id, '_gkpsch_details', array( 'timezone' => $timezone ) );
        do_action( 'wpml_register_single_string', 'georg-kloster-prihod-schedule', 'location-' . $id, get_the_title( $id ) );
    }
}
