<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

final class Blocks {
    public static function help(): void {
        $none = __( 'No parameters.', 'georg-kloster-prihod-core' );
        $place = __( 'location: service place ID; 0 (default) includes all published places.', 'georg-kloster-prihod-core' );
        $rows = array(
            array( '[gkpc_profile]', __( 'Parish name and description.', 'georg-kloster-prihod-core' ), $none, '[gkpc_profile]' ),
            array( '[gkpc_contacts]', __( 'Parish address, phone, email and website.', 'georg-kloster-prihod-core' ), $none, '[gkpc_contacts]' ),
            array( '[gkpc_clergy]', __( 'Published clergy profiles.', 'georg-kloster-prihod-core' ), $none, '[gkpc_clergy]' ),
            array( '[gkpc_places]', __( 'Published service places with logos and photos.', 'georg-kloster-prihod-core' ), $none, '[gkpc_places]' ),
            array( '[gkpsch_schedule]', __( 'Service schedule with week, month and list views.', 'georg-kloster-prihod-core' ), __( 'view: week (default), month or list.', 'georg-kloster-prihod-core' ) . ' ' . $place, '[gkpsch_schedule view="month" location="123"]' ),
            array( '[gkpsch_next_service]', __( 'The next upcoming service.', 'georg-kloster-prihod-core' ), $place, '[gkpsch_next_service location="123"]' ),
            array( '[gkpsch_next_services]', __( 'A list of upcoming services.', 'georg-kloster-prihod-core' ), $place . ' ' . __( 'limit: number of services, 1–50; default 5.', 'georg-kloster-prihod-core' ), '[gkpsch_next_services location="123" limit="5"]' ),
        );
        echo '<section id="gkpc-shortcodes"><h2>' . esc_html__( 'Shortcodes and blocks', 'georg-kloster-prihod-core' ) . '</h2><p>' . esc_html__( 'Insert a Shortcode block on a page and paste an example. Replace 123 with the ID shown in the service place edit URL (post=123). Omit location to include all places. The editor also provides equivalent Parish and Schedule blocks.', 'georg-kloster-prihod-core' ) . '</p><table class="widefat striped"><thead><tr>';
        foreach ( array( __( 'Shortcode', 'georg-kloster-prihod-core' ), __( 'Description', 'georg-kloster-prihod-core' ), __( 'Parameters', 'georg-kloster-prihod-core' ), __( 'Example', 'georg-kloster-prihod-core' ) ) as $label ) { echo '<th scope="col">' . esc_html( $label ) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) { echo '<tr><td><code>' . esc_html( $row[0] ) . '</code></td><td>' . esc_html( $row[1] ) . '</td><td>' . esc_html( $row[2] ) . '</td><td><code>' . esc_html( $row[3] ) . '</code></td></tr>'; }
        echo '</tbody></table></section>';
    }

    public static function register(): void {
        wp_register_style( 'gkpc-directory', plugins_url( 'assets/directory.css', GKPC_FILE ), array(), GKPC_VERSION );
        add_action( 'wp_enqueue_scripts', static function () { wp_enqueue_style( 'gkpc-directory' ); } );
        wp_register_script( 'gkpc-blocks', plugins_url( 'assets/blocks.js', GKPC_FILE ), array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-i18n' ), GKPC_VERSION, true );
        wp_set_script_translations( 'gkpc-blocks', 'georg-kloster-prihod-core', dirname( GKPC_FILE ) . '/languages' );
        foreach ( array( 'profile', 'contacts', 'clergy', 'places' ) as $name ) {
            register_block_type( 'gkpc/' . $name, array(
                'api_version' => 3,
                'title' => self::labels()[ $name ],
                'category' => 'widgets',
                'editor_script' => 'gkpc-blocks',
                'render_callback' => static function () use ( $name ) { return self::render( $name ); },
            ) );
            add_shortcode( 'gkpc_' . $name, static function () use ( $name ) { return self::render( $name ); } );
        }
    }

    private static function labels(): array {
        return array(
            'profile' => __( 'Parish profile', 'georg-kloster-prihod-core' ),
            'contacts' => __( 'Parish contacts', 'georg-kloster-prihod-core' ),
            'clergy' => __( 'Clergy', 'georg-kloster-prihod-core' ),
            'places' => __( 'Service places', 'georg-kloster-prihod-core' ),
        );
    }

    public static function render( string $name ): string {
        if ( 'profile' === $name ) {
            $profile = Profile::get();
            return '<section class="gkpc-profile"><h2>' . esc_html( $profile['name'] ) . '</h2>' . wpautop( esc_html( $profile['description'] ) ) . '</section>';
        }
        if ( 'contacts' === $name ) {
            $profile = Profile::get();
            $html = '';
            foreach ( array( 'address', 'phone', 'email', 'website' ) as $key ) {
                if ( ! $profile[ $key ] ) { continue; }
                $value = esc_html( $profile[ $key ] );
                if ( 'email' === $key ) { $value = '<a href="' . esc_url( 'mailto:' . $profile[ $key ] ) . '">' . $value . '</a>'; }
                if ( 'website' === $key ) { $value = '<a href="' . esc_url( $profile[ $key ] ) . '">' . $value . '</a>'; }
                $html .= '<p><strong>' . esc_html( Profile::fields()[ $key ] ) . ':</strong> ' . $value . '</p>';
            }
            return '<section class="gkpc-contacts">' . $html . '</section>';
        }
        $type = 'clergy' === $name ? 'gkpc_clergy' : Directory::location_types();
        $html = '';
        foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $post ) {
            $details = Directory::details( (int) $post->ID );
            $secondary = 'clergy' === $name ? ( $details['rank'] ?? '' ) : ( $details['address'] ?? '' );
            $html .= '<article><h3>' . esc_html( $post->post_title ) . '</h3>' . ( 'places' === $name ? Place_Media::render( $post->ID ) : '' ) . '<p>' . esc_html( $secondary ) . '</p>' . wpautop( wp_kses_post( $post->post_content ) ) . '</article>';
        }
        return '<section class="gkpc-' . esc_attr( $name ) . '">' . $html . '</section>';
    }
}
