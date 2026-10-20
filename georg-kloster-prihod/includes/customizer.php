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
		'prihod_home' => __( 'Homepage sections and content', 'georg-kloster-prihod' ),
		'prihod_languages' => __( 'Page language versions', 'georg-kloster-prihod' ),
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
			'sanitize_callback' => static function ( $input ) use ( $control_type ) {
				if ( 'checkbox' === $control_type ) { return (bool) $input; }
				if ( 'url' === $control_type ) { return esc_url_raw( is_scalar( $input ) ? (string) $input : '' ); }
				if ( 'number' === $control_type ) { return is_scalar( $input ) ? absint( $input ) : 0; }
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

	foreach ( array( 'sticky_header' => __( 'Keep the header visible while scrolling', 'georg-kloster-prihod' ), 'header_contacts' => __( 'Show contact details in the header', 'georg-kloster-prihod' ) ) as $key => $label ) { $add( $key, $label, 'prihod_header', 'checkbox' ); }
	$add_select( 'menu_style', __( 'Menu style', 'georg-kloster-prihod' ), 'prihod_header', array( 'visual' => __( 'Menu with cards and images', 'georg-kloster-prihod' ), 'classic' => __( 'Link row', 'georg-kloster-prihod' ) ) );
	$add_select( 'menu_columns', __( 'Cards per row', 'georg-kloster-prihod' ), 'prihod_header', array( '2' => '2', '3' => '3', '4' => '4' ) );
	foreach ( array( 'header_motto' => __( 'Classic header motto', 'georg-kloster-prihod' ), 'hero_motto' => __( 'Classic hero motto', 'georg-kloster-prihod' ), 'quote_text' => __( 'Quotation text', 'georg-kloster-prihod' ), 'quote_source' => __( 'Quotation source', 'georg-kloster-prihod' ), 'hero_quote_text' => __( 'Quotation below the buttons in the blue style', 'georg-kloster-prihod' ), 'hero_quote_source' => __( 'Blue style quotation source', 'georg-kloster-prihod' ), 'footer_text' => __( 'Additional footer text', 'georg-kloster-prihod' ) ) as $key => $label ) { $add( $key, $label, 'prihod_header', 'textarea' ); }

	$home = prihod_theme_home_options();
	foreach ( prihod_theme_home_sections() as $key => $label ) {
		$add( 'home][sections][' . $key . '][enabled', sprintf( __( 'Show section: %s', 'georg-kloster-prihod' ), $label ), 'prihod_home', 'checkbox' );
		$add( 'home][sections][' . $key . '][title', sprintf( __( 'Section heading: %s', 'georg-kloster-prihod' ), $label ), 'prihod_home' );
		$add( 'home][sections][' . $key . '][eyebrow', sprintf( __( 'Section link or subtitle: %s', 'georg-kloster-prihod' ), $label ), 'prihod_home' );
	}
	$section_choices = array();
	foreach ( prihod_theme_home_sections() as $key => $label ) { $section_choices[$key] = $label; }
	foreach ( $home['order'] as $index => $key ) { $add( 'home][order][' . $index, sprintf( __( 'Section position %d', 'georg-kloster-prihod' ), $index + 1 ), 'prihod_home', 'select', array( 'choices' => $section_choices ) ); }
	$text_labels = array( 'hero_description' => __( 'Description', 'georg-kloster-prihod' ), 'about_description' => __( 'Description', 'georg-kloster-prihod' ), 'hero_schedule_button' => __( 'Schedule button label', 'georg-kloster-prihod' ), 'hero_secondary_button' => __( 'Secondary button label', 'georg-kloster-prihod' ), 'about_button' => __( 'Link label', 'georg-kloster-prihod' ), 'news_button' => __( 'Link label', 'georg-kloster-prihod' ), 'news_read_button' => __( 'Post link label', 'georg-kloster-prihod' ), 'services_button' => __( 'Link label', 'georg-kloster-prihod' ), 'contacts_button' => __( 'Link label', 'georg-kloster-prihod' ) );
	foreach ( prihod_theme_home_text_fields() as $key ) { $add( 'home][' . $key, $text_labels[$key] ?? __( 'Description', 'georg-kloster-prihod' ), 'prihod_home', 'textarea' ); }
	foreach ( array( 'services_count' => array( __( 'Number of services', 'georg-kloster-prihod' ), 6 ), 'news_count' => array( __( 'Number of posts', 'georg-kloster-prihod' ), 12 ), 'news_excerpt_words' => array( __( 'Excerpt length in words', 'georg-kloster-prihod' ), 80 ), 'news_columns' => array( __( 'Desktop columns', 'georg-kloster-prihod' ), 4 ) ) as $key => $field ) { $add( 'home][' . $key, $field[0], 'prihod_home', 'number', array( 'input_attrs' => array( 'min' => 0, 'max' => $field[1] ) ) ); }
	$add_select( 'home][news_category', __( 'Post category', 'georg-kloster-prihod' ), 'prihod_home', prihod_theme_customize_categories() );
	foreach ( array( 'news_show_date' => __( 'Show date', 'georg-kloster-prihod' ), 'news_show_excerpt' => __( 'Show excerpt', 'georg-kloster-prihod' ), 'news_show_images' => __( 'Show images', 'georg-kloster-prihod' ), 'cards_show_images' => __( 'Show card images', 'georg-kloster-prihod' ), 'hide_schedule_card' => __( 'Hide the schedule card when upcoming services are displayed', 'georg-kloster-prihod' ) ) as $key => $label ) { $add( 'home][' . $key, $label, 'prihod_home', 'checkbox' ); }
	foreach ( prihod_theme_home_pages() as $key => $label ) {
		$choices = array( 0 => __( 'Automatic', 'georg-kloster-prihod' ) );
		foreach ( get_pages( array( 'post_status' => 'publish', 'sort_column' => 'post_title' ) ) as $page ) { $choices[$page->ID] = $page->post_title; }
		$add_select( 'home][pages][' . $key, sprintf( __( 'Link to page: %s', 'georg-kloster-prihod' ), $label ), 'prihod_home', $choices );
	}
	foreach ( $home['card_order'] as $index => $key ) {
		$card_choices = array();
		foreach ( prihod_theme_home_pages() as $card_key => $label ) { if ( isset( $home['cards'][$card_key] ) ) { $card_choices[$card_key] = $label; } }
		$add( 'home][card_order][' . $index, sprintf( __( 'Card position %d', 'georg-kloster-prihod' ), $index + 1 ), 'prihod_home', 'select', array( 'choices' => $card_choices ) );
	}
	foreach ( $home['cards'] as $key => $card ) {
		$label = prihod_theme_home_pages()[$key] ?? $key;
		$add( 'home][cards][' . $key . '][enabled', sprintf( __( 'Show card: %s', 'georg-kloster-prihod' ), $label ), 'prihod_home', 'checkbox' );
		foreach ( array( 'title' => __( 'Card title', 'georg-kloster-prihod' ), 'description' => __( 'Card description', 'georg-kloster-prihod' ), 'button' => __( 'Link label', 'georg-kloster-prihod' ) ) as $field => $title ) { $add( 'home][cards][' . $key . '][' . $field, sprintf( __( '%1$s: %2$s', 'georg-kloster-prihod' ), $title, $label ), 'prihod_home', 'text' ); }
	}
	$add( 'languages][ru', __( 'Russian', 'georg-kloster-prihod' ), 'prihod_languages', 'url' );
	$add( 'languages][de', __( 'German', 'georg-kloster-prihod' ), 'prihod_languages', 'url' );
	$add( 'languages][pl', __( 'Polish', 'georg-kloster-prihod' ), 'prihod_languages', 'url' );
	$add( 'languages][uk', __( 'Ukrainian', 'georg-kloster-prihod' ), 'prihod_languages', 'url' );

}
add_action( 'customize_register', 'prihod_theme_customize_register' );

function prihod_theme_customize_categories(): array {
	$choices = array( 0 => __( 'Default', 'georg-kloster-prihod' ), -1 => __( 'All categories', 'georg-kloster-prihod' ) );
	$terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
	if ( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { $choices[$term->term_id] = $term->name; } }
	return $choices;
}
