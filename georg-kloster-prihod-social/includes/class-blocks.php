<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Blocks {
    public static function register(): void {
        wp_register_script( 'gkps-blocks', plugins_url( 'assets/blocks.js', GKPS_FILE ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ), GKPS_VERSION, true );
        wp_set_script_translations( 'gkps-blocks', 'georg-kloster-prihod-social', dirname( GKPS_FILE ) . '/languages' );
        register_block_type( 'gkps/contact-form', array( 'api_version' => 3, 'title' => __( 'Contact form', 'georg-kloster-prihod-social' ), 'category' => 'widgets', 'editor_script' => 'gkps-blocks', 'render_callback' => array( Request_Form::class, 'render' ) ) );
        add_shortcode( 'gkps_contact_form', array( Request_Form::class, 'render' ) );
    }
}
