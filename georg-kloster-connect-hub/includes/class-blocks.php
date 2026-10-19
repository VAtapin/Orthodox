<?php
namespace GeorgKlosterPrihodSocial;

defined( 'ABSPATH' ) || exit;

final class Blocks {
    public static function help(): void {
        echo '<section id="gkch-shortcodes"><h2>' . esc_html__( 'Shortcodes and blocks', 'georg-kloster-connect-hub' ) . '</h2><p>' . esc_html__( 'Add a Form block and select a form, or insert a Shortcode block with one of these examples. Copy the form ID from the Forms table. A form must be enabled, and incoming messages must be enabled in Settings.', 'georg-kloster-connect-hub' ) . '</p><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Shortcode', 'georg-kloster-connect-hub' ) . '</th><th scope="col">' . esc_html__( 'Description', 'georg-kloster-connect-hub' ) . '</th></tr></thead><tbody><tr><td><code>[gkch_form id="123"]</code></td><td>' . esc_html__( 'Show the selected form. Replace 123 with its ID; id="0" or an omitted id uses the default form.', 'georg-kloster-connect-hub' ) . '</td></tr><tr><td><code>[gkps_contact_form id="123"]</code></td><td>' . esc_html__( 'Compatible alias for the same form shortcode. Existing pages can keep using it.', 'georg-kloster-connect-hub' ) . '</td></tr></tbody></table><p>' . esc_html__( 'Optional title, intro and submit parameters override only the displayed heading, introduction and button label. They do not change saved form settings, topics or recipients.', 'georg-kloster-connect-hub' ) . '</p><p><code>[gkch_form id="123" title="Contact us" intro="Send a message" submit="Send"]</code></p></section>';
    }

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
