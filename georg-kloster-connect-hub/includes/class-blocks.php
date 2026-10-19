<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Blocks {
    public static function register(): void {
        wp_register_script( 'gkps-blocks', plugins_url( 'assets/blocks.js', GKPS_FILE ), array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ), GKPS_VERSION, true );
        wp_set_script_translations( 'gkps-blocks', 'georg-kloster-connect-hub', dirname( GKPS_FILE ) . '/languages' );
        $forms = array();
        if ( current_user_can( 'edit_posts' ) || current_user_can( 'manage_gkps_settings' ) ) {
            foreach ( Forms::all() as $form ) { $forms[] = array( 'label' => $form['name'], 'value' => $form['id'] ); }
        }
        wp_localize_script( 'gkps-blocks', 'gkchForms', $forms );
        foreach ( array( 'gkch/form', 'gkps/contact-form' ) as $name ) {
            register_block_type( $name, array( 'api_version' => 3, 'title' => __( 'Form', 'georg-kloster-connect-hub' ), 'category' => 'widgets', 'editor_script' => 'gkps-blocks', 'attributes' => array( 'formId' => array( 'type' => 'integer', 'default' => 0 ) ), 'render_callback' => array( Request_Form::class, 'render' ) ) );
        }
        add_shortcode( 'gkps_contact_form', array( Request_Form::class, 'render' ) );
        add_shortcode( 'gkch_form', array( Request_Form::class, 'render' ) );
    }
}
