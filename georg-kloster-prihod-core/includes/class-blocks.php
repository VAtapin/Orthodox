<?php
namespace GeorgKlosterPrihodCore;

defined( 'ABSPATH' ) || exit;

final class Blocks {
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
