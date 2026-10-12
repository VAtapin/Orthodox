<?php
defined( 'ABSPATH' ) || exit;

/** Merge an exact generated biography duplicate into the existing live directory. */
function prihod_theme_clergy_content( string $html ): string {
    $post = get_post(); $resources = get_option( 'prihod_resources', array() );
    if ( ! $post || ! is_singular( 'page' ) || $post->ID !== get_queried_object_id() || $post->ID !== absint( $resources['clergy']['id'] ?? 0 )
        || ! in_array( get_page_template_slug( $post->ID ), array( '', 'default' ), true ) || ! get_post_meta( $post->ID, '_prihod_theme_demo', true )
        || ! hash_equals( (string) get_post_meta( $post->ID, '_prihod_generated_hash', true ), hash( 'sha256', $post->post_title . '|' . $post->post_content ) ) ) { return $html; }
    $people = get_posts( array( 'post_type' => 'prihod_clergy', 'post_status' => 'publish', 'numberposts' => 2 ) );
    if ( 1 !== count( $people ) ) { return $html; } // Never associate one photograph with several people.
    $blocks = parse_blocks( $post->post_content ); $photo = null; $bio = null; $directory = null; $directories = 0;
    $text = static fn( $value ) => trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $value ) ) );
    foreach ( $blocks as $index => $block ) {
        if ( null === $photo && 'core/image' === $block['blockName'] && wp_attachment_is_image( absint( $block['attrs']['id'] ?? 0 ) ) ) { $photo = $index; }
        if ( 'prihod/clergy' === $block['blockName'] ) { $directory = $index; ++$directories; }
        if ( 'core/heading' === $block['blockName'] && $text( $block['innerHTML'] ) === $text( $people[0]->post_title )
            && 'core/paragraph' === ( $blocks[$index + 1]['blockName'] ?? '' ) && $text( $blocks[$index + 1]['innerHTML'] ) === $text( $people[0]->post_content ) ) { $bio = $index; }
    }
    if ( null === $photo || null === $bio || null === $directory || 1 !== $directories || $photo >= $bio ) { return $html; }
    $native = render_block( $blocks[$directory] );
    if ( ! str_contains( $native, esc_html( $people[0]->post_title ) ) ) { return $html; }
    $card = '<div class="prihod-clergy-card"><div class="prihod-clergy-photo">' . render_block( $blocks[$photo] ) . '</div><div class="prihod-clergy-biography">' . $native;
    $url = prihod_theme_url( 'requests' );
    if ( $url ) { $card .= '<a class="prihod-button is-outline" href="' . esc_url( $url ) . '">' . esc_html__( 'Вопрос священнику', 'prihod-theme' ) . ' →</a>'; }
    $card .= '</div></div>'; $result = '';
    foreach ( $blocks as $index => $block ) {
        if ( $index === $bio ) { $result .= $card; }
        if ( in_array( $index, array( $photo, $bio, $bio + 1, $directory ), true ) ) { continue; }
        $result .= render_block( $block );
    }
    return $result;
}
add_filter( 'render_block_core/post-content', 'prihod_theme_clergy_content' );
