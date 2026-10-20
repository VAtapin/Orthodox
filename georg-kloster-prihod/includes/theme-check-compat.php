<?php
/**
 * WordPress.org Theme Check compatibility enhancements.
 *
 * @package Georg-Kloster-Prihod
 */
defined( 'ABSPATH' ) || exit;

/**
 * Register lightweight, reusable styles and a starter pattern for core blocks.
 */
function prihod_theme_register_block_styles(): void {
	register_block_style(
		'core/paragraph',
		array(
			'name'         => 'prihod-lead',
			'label'        => __( 'Parish lead paragraph', 'georg-kloster-prihod' ),
			'inline_style' => '.is-style-prihod-lead{font-size:1.2em;line-height:1.7;color:var(--wp--preset--color--muted,#687080)}',
		)
	);
	register_block_style(
		'core/quote',
		array(
			'name'         => 'prihod-scripture',
			'label'        => __( 'Scripture quotation', 'georg-kloster-prihod' ),
			'inline_style' => '.is-style-prihod-scripture{border-inline-start:3px solid var(--wp--preset--color--gold,#b9863d);padding-inline-start:1.25rem;font-family:var(--wp--preset--font-family--serif,Georgia,serif)}',
		)
	);
}
add_action( 'init', 'prihod_theme_register_block_styles' );

/**
 * Register an optional simple welcome pattern for parish pages.
 */
function prihod_theme_register_block_patterns(): void {
	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category(
			'georg-kloster-prihod',
			array( 'label' => __( 'Parish theme', 'georg-kloster-prihod' ) )
		);
	}
	if ( function_exists( 'register_block_pattern' ) ) {
		register_block_pattern(
			'georg-kloster-prihod/welcome',
			array(
				'title'       => __( 'Parish welcome', 'georg-kloster-prihod' ),
				'description' => __( 'A simple welcome section with a heading, introduction and link.', 'georg-kloster-prihod' ),
				'categories'  => array( 'georg-kloster-prihod', 'text' ),
				'content'     => '<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group"><!-- wp:heading {"level":2} --><h2 class="wp-block-heading">' . esc_html__( 'Welcome to our parish', 'georg-kloster-prihod' ) . '</h2><!-- /wp:heading --><!-- wp:paragraph {"className":"is-style-prihod-lead"} --><p class="is-style-prihod-lead">' . esc_html__( 'Our church is a place for prayer, fellowship and support for everyone.', 'georg-kloster-prihod' ) . '</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>' . esc_html__( 'Learn more about our community, services and parish life.', 'georg-kloster-prihod' ) . '</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
			)
		);
	}
}
add_action( 'init', 'prihod_theme_register_block_patterns', 20 );
