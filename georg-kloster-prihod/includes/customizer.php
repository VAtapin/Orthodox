<?php
defined( 'ABSPATH' ) || exit;

/** Add native Customizer controls for the theme options; the existing settings page remains available. */
function prihod_theme_customize_register( WP_Customize_Manager $customize ): void {
	$options = georg_kloster_prihod_options();
	$options['scheme'] = $options['scheme'] ?: prihod_theme_scheme();
	$options['home'] = prihod_theme_home_options();
	$options['profile'] = prihod_theme_profile_sanitize( $options['profile'] );
	$customize->add_panel( 'prihod_theme_panel', array(
		'title'       => __( 'Parish theme settings', 'georg-kloster-prihod' ),
		'description' => __( 'These controls mirror the Parish Appearance settings page. Preview changes here and publish when ready.', 'georg-kloster-prihod' ),
		'priority'    => 35,
	) );

	$sections = array(
		'prihod_design' => __( 'Design and layout', 'georg-kloster-prihod' ),
		'prihod_branding' => __( 'Logo and images', 'georg-kloster-prihod' ),
		'prihod_profile' => __( 'Site details', 'georg-kloster-prihod' ),
		'prihod_header' => __( 'Header and footer', 'georg-kloster-prihod' ),
		'prihod_map' => __( 'Map', 'georg-kloster-prihod' ),
	);
	foreach ( $sections as $id => $title ) {
		$customize->add_section( $id, array( 'title' => $title, 'panel' => 'prihod_theme_panel' ) );
	}

	$add = static function ( string $key, string $label, string $section, string $control_type = 'text', array $args = array() ) use ( $customize, $options ): void {
		$id = 'georg_kloster_prihod_options[' . $key . ']';
		$value = $options;
		foreach ( explode( '][', trim( $key, '[]' ) ) as $part ) { $value = is_array( $value ) ? ( $value[$part] ?? null ) : null; }
		$setting = array(
			'type'              => 'option',
			'default'           => $value,
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $input ) use ( $control_type, $key ) {
				if ( 'checkbox' === $control_type ) { return (bool) $input; }
				if ( 'url' === $control_type ) { return esc_url_raw( is_scalar( $input ) ? (string) $input : '' ); }
				if ( 'number' === $control_type ) { return is_scalar( $input ) ? absint( $input ) : 0; }
				if ( 'decimal' === $control_type ) { $value = str_replace( ',', '.', is_scalar( $input ) ? (string) $input : '' ); $range = 'map_latitude' === $key ? array( -90, 90 ) : array( -180, 180 ); return is_numeric( $value ) && (float) $value >= $range[0] && (float) $value <= $range[1] ? (string) (float) $value : ''; }
				if ( 'textarea' === $control_type ) { return sanitize_textarea_field( is_scalar( $input ) ? (string) $input : '' ); }
				return sanitize_text_field( is_scalar( $input ) ? (string) $input : '' );
			},
		);
		$customize->add_setting( $id, $setting );
		$customize->add_control( $id, array_merge( array( 'label' => $label, 'section' => $section, 'type' => $control_type ), $args ) );
	};
	$add_media = static function ( string $key, string $label, string $section ) use ( $customize, $options ): void {
		$id = 'georg_kloster_prihod_options[' . $key . ']';
		$customize->add_setting( $id, array( 'type' => 'option', 'default' => absint( $options[$key] ?? 0 ), 'transport' => 'refresh', 'sanitize_callback' => static function ( $input ): int { $id = is_scalar( $input ) ? absint( $input ) : 0; return $id && wp_attachment_is_image( $id ) ? $id : 0; } ) );
		$customize->add_control( new WP_Customize_Media_Control( $customize, $id, array( 'label' => $label, 'section' => $section, 'mime_type' => 'image' ) ) );
	};
	$add_select = static function ( string $key, string $label, string $section, array $choices ) use ( $add ): void { $add( $key, $label, $section, 'select', array( 'choices' => $choices ) ); };

	$add_select( 'scheme', __( 'Visual style', 'georg-kloster-prihod' ), 'prihod_design', prihod_theme_schemes() );
	$add( 'width', __( 'Site width in px', 'georg-kloster-prihod' ), 'prihod_design', 'number', array( 'input_attrs' => array( 'min' => 960, 'max' => 1440 ) ) );
	$add( 'font_size', __( 'Base text size in px', 'georg-kloster-prihod' ), 'prihod_design', 'number', array( 'input_attrs' => array( 'min' => 14, 'max' => 20 ) ) );
	$add_select( 'font', __( 'Base font', 'georg-kloster-prihod' ), 'prihod_design', array( 'sans' => __( 'Sans serif', 'georg-kloster-prihod' ), 'serif' => __( 'Serif', 'georg-kloster-prihod' ) ) );

	$add_media( 'logo', __( 'Site logo', 'georg-kloster-prihod' ), 'prihod_branding' );
	$add( 'logo_removed', __( 'Remove selected logo', 'georg-kloster-prihod' ), 'prihod_branding', 'checkbox' );
	$add( 'logo_show', __( 'Show the theme logo', 'georg-kloster-prihod' ), 'prihod_branding', 'checkbox' );
	$add( 'logo_width', __( 'Logo width in px', 'georg-kloster-prihod' ), 'prihod_branding', 'number', array( 'input_attrs' => array( 'min' => 64, 'max' => 320 ) ) );
	$add( 'logo_hide_title', __( 'Hide the site title beside the logo', 'georg-kloster-prihod' ), 'prihod_branding', 'checkbox' );
	$add_media( 'hero_image', __( 'Homepage hero image', 'georg-kloster-prihod' ), 'prihod_branding' );

	foreach ( prihod_theme_profile_fields() as $key => $label ) { $add( 'profile][' . $key, $label, 'prihod_profile', in_array( $key, array( 'description', 'address' ), true ) ? 'textarea' : 'text' ); }
	$add_select( 'profile][type', __( 'Community type', 'georg-kloster-prihod' ), 'prihod_profile', array( '' => __( 'Automatic', 'georg-kloster-prihod' ), 'parish' => __( 'Parish', 'georg-kloster-prihod' ), 'monastery' => __( 'Monastery', 'georg-kloster-prihod' ) ) );
	$add_select( 'map_provider', __( 'Map provider', 'georg-kloster-prihod' ), 'prihod_map', array( 'openstreetmap' => __( 'OpenStreetMap', 'georg-kloster-prihod' ), 'google' => __( 'Google Maps', 'georg-kloster-prihod' ) ) );
	$add( 'map_latitude', __( 'Map latitude', 'georg-kloster-prihod' ), 'prihod_map', 'decimal', array( 'input_attrs' => array( 'step' => 'any', 'min' => -90, 'max' => 90 ) ) );
	$add( 'map_longitude', __( 'Map longitude', 'georg-kloster-prihod' ), 'prihod_map', 'decimal', array( 'input_attrs' => array( 'step' => 'any', 'min' => -180, 'max' => 180 ) ) );
	$add( 'google_maps_key', __( 'Google Maps API key', 'georg-kloster-prihod' ), 'prihod_map', 'password' );

	foreach ( array( 'sticky_header' => __( 'Keep the header visible while scrolling', 'georg-kloster-prihod' ), 'header_contacts' => __( 'Show contact details in the header', 'georg-kloster-prihod' ) ) as $key => $label ) { $add( $key, $label, 'prihod_header', 'checkbox' ); }
	$add_select( 'menu_style', __( 'Menu style', 'georg-kloster-prihod' ), 'prihod_header', array( 'visual' => __( 'Menu with cards and images', 'georg-kloster-prihod' ), 'classic' => __( 'Link row', 'georg-kloster-prihod' ) ) );
	$add_select( 'menu_columns', __( 'Cards per row', 'georg-kloster-prihod' ), 'prihod_header', array( '2' => '2', '3' => '3', '4' => '4' ) );
	foreach ( array( 'header_motto' => __( 'Classic header motto', 'georg-kloster-prihod' ), 'quote_text' => __( 'Quotation text', 'georg-kloster-prihod' ), 'quote_source' => __( 'Quotation source', 'georg-kloster-prihod' ), 'footer_text' => __( 'Additional footer text', 'georg-kloster-prihod' ) ) as $key => $label ) { $add( $key, $label, 'prihod_header', in_array( $key, array( 'quote_text', 'footer_text' ), true ) ? 'textarea' : 'text' ); }


}
add_action( 'customize_register', 'prihod_theme_customize_register' );

function prihod_theme_customize_categories(): array {
	$choices = array( 0 => __( 'Default', 'georg-kloster-prihod' ), -1 => __( 'All categories', 'georg-kloster-prihod' ) );
	$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { $choices[$term->term_id] = $term->name; } }
	return $choices;
}
