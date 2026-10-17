<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

final class Page_Links {
    public const OPTION = 'gkpc_pages';

    public static function labels(): array {
        return array(
            'schedule' => __( 'Service schedule', 'georg-kloster-prihod-core' ),
            'about' => __( 'About', 'georg-kloster-prihod-core' ),
            'news' => __( 'News', 'georg-kloster-prihod-core' ),
            'requests' => __( 'Contact requests', 'georg-kloster-prihod-core' ),
            'donations' => __( 'Donations', 'georg-kloster-prihod-core' ),
            'contacts' => __( 'Contacts', 'georg-kloster-prihod-core' ),
            'gallery' => __( 'Photo gallery', 'georg-kloster-prihod-core' ),
            'calendar' => __( 'Calendar', 'georg-kloster-prihod-core' ),
            'first-visit' => __( 'First visit', 'georg-kloster-prihod-core' ),
            'sermons' => __( 'Sermons', 'georg-kloster-prihod-core' ),
            'clergy' => __( 'Clergy page', 'georg-kloster-prihod-core' ),
        );
    }

    public static function get(): array {
        $saved = get_option( self::OPTION, array() );
        return is_array( $saved ) ? $saved : array();
    }

    public static function filter( $resources ): array {
        return array_replace( is_array( $resources ) ? $resources : array(), self::get() );
    }

    public static function sanitize( $input ): array {
        $result = array();
        if ( ! is_array( $input ) ) { return $result; }
        foreach ( self::labels() as $key => $label ) {
            $id = absint( $input[ $key ]['id'] ?? 0 );
            if ( $id && in_array( get_post_type( $id ), array( 'post', 'page' ), true ) && 'publish' === get_post_status( $id ) ) {
                $result[ $key ] = array( 'id' => $id );
            }
        }
        return $result;
    }

    public static function render(): void {
        $saved = self::get();
        $posts = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true ) );
        echo '<h2>' . esc_html__( 'Site sections', 'georg-kloster-prihod-core' ) . '</h2>';
        echo '<p>' . esc_html__( 'Choose the content shown in the theme navigation and home page. Each language uses its linked WordPress translation.', 'georg-kloster-prihod-core' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
        settings_fields( 'gkpc_pages' );
        echo '<table class="form-table"><tbody>';
        foreach ( self::labels() as $key => $label ) {
            echo '<tr><th><label for="gkpc-page-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><select id="gkpc-page-' . esc_attr( $key ) . '" name="gkpc_pages[' . esc_attr( $key ) . '][id]">';
            echo '<option value="0">' . esc_html__( 'Select content', 'georg-kloster-prihod-core' ) . '</option>';
            foreach ( $posts as $post ) {
                echo '<option value="' . (int) $post->ID . '"' . selected( (int) ( $saved[ $key ]['id'] ?? 0 ), $post->ID, false ) . '>' . esc_html( $post->post_title ) . ' (' . esc_html( $post->post_type ) . ')</option>';
            }
            echo '</select></td></tr>';
        }
        echo '</tbody></table>';
        submit_button( __( 'Save sections', 'georg-kloster-prihod-core' ) );
        echo '</form>';
    }
}
