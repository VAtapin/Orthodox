<?php
defined( 'ABSPATH' ) || exit;

/** Enhance the existing Core block without changing its data or renderer. */
add_filter( 'render_block_prihod/contacts', function ( string $html ): string {
    if ( is_admin() ) { return $html; }
    $phone = prihod_theme_profile()['phone'];
    if ( ! $phone || str_contains( $html, 'href="tel:' ) ) { return $html; }
    $text = esc_html( $phone );
    return str_replace( $text . '</p>', '<a href="' . esc_url( 'tel:' . preg_replace( '/[^+0-9]/', '', $phone ) ) . '">' . $text . '</a></p>', $html );
} );

function prihod_theme_contacts_data(): ?array {
    $page = is_singular( 'page' ) ? get_post( get_queried_object_id() ) : null; $resources = get_option( 'prihod_resources', array() );
    if ( ! $page instanceof WP_Post || $page->ID !== absint( $resources['contacts']['id'] ?? 0 )
        || ! in_array( get_page_template_slug( $page->ID ), array( '', 'default', 'contacts.php' ), true ) || ! get_post_meta( $page->ID, '_prihod_theme_demo', true )
        || ! hash_equals( (string) get_post_meta( $page->ID, '_prihod_generated_hash', true ), hash( 'sha256', $page->post_title . '|' . $page->post_content ) ) ) { return null; }
    foreach ( array( 'page', 'page-' . $page->post_name, 'page-' . $page->ID, 'contacts' ) as $slug ) {
        $template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template' );
        if ( $template && ( 'custom' === $template->source || str_starts_with( $slug, 'page-' ) ) ) { return null; }
    }
    $intro = ''; $photo = ''; $contact = ''; $body = ''; $tail = ''; $count = 0;
    $blocks = parse_blocks( $page->post_content );
    foreach ( $blocks as $index => $block ) {
        if ( ! $intro && 'core/paragraph' === $block['blockName'] ) { $intro = render_block( $block ); continue; }
        if ( ! $photo && 'core/image' === $block['blockName'] ) { $photo = render_block( $block ); continue; }
        if ( 'prihod/contacts' === $block['blockName'] ) { $contact = render_block( $block ); ++$count; continue; }
        if ( $index === count( $blocks ) - 1 && 'core/paragraph' === $block['blockName'] && 'small' === ( $block['attrs']['fontSize'] ?? '' ) ) { $tail = render_block( $block ); continue; }
        $body .= render_block( $block );
    }
    return $intro && 1 === $count ? compact( 'page', 'intro', 'photo', 'contact', 'body', 'tail' ) : null;
}
add_filter( 'page_template_hierarchy', function ( array $templates ): array {
    if ( ! prihod_theme_contacts_data() ) { return $templates; }
    $index = array_search( 'page.php', $templates, true );
    if ( false !== $index ) { array_splice( $templates, $index, 0, array( 'contacts.php' ) ); }
    return $templates;
} );
function prihod_theme_contacts_content( string $html ): string {
    $data = prihod_theme_contacts_data(); $post = get_post();
    if ( ! $data || ! $post || $post->ID !== $data['page']->ID ) { return $html; }
    $actions = '';
    foreach ( array( 'requests' => __( 'Вопрос священнику', 'georg-kloster-prihod' ), 'first-visit' => __( 'Первое посещение', 'georg-kloster-prihod' ) ) as $key => $label ) {
        $url = prihod_theme_url( $key ); if ( $url ) { $actions .= '<a class="prihod-button is-outline" href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' →</a>'; }
    }
    return '<div class="prihod-contacts-content">' . $data['intro'] . '<section class="prihod-contacts-primary"><div><h2>' . esc_html__( 'Контакты', 'georg-kloster-prihod' ) . '</h2>' . $data['contact'] . '<div class="prihod-actions">' . $actions . '</div></div></section><section class="prihod-contacts-road"><h2>' . esc_html__( 'Как добраться', 'georg-kloster-prihod' ) . '</h2><div class="prihod-contacts-road-photo">' . $data['photo'] . '</div>' . $data['body'] . '</section>' . $data['tail'] . '</div>';
}
