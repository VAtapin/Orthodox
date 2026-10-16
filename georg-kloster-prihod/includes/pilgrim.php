<?php
defined( 'ABSPATH' ) || exit;

/** Presentation of the unchanged generated visitor page; never persist page data. */
function prihod_theme_pilgrim_data(): ?array {
    $page = is_singular( 'page' ) ? get_post( get_queried_object_id() ) : null; $resources = get_option( 'prihod_resources', array() );
    if ( ! is_singular( 'page' ) || ! $page instanceof WP_Post || $page->ID !== absint( $resources['first-visit']['id'] ?? 0 )
        || ! in_array( get_page_template_slug( $page->ID ), array( '', 'default', 'pilgrim.php' ), true ) || ! get_post_meta( $page->ID, '_prihod_theme_demo', true )
        || ! hash_equals( (string) get_post_meta( $page->ID, '_prihod_generated_hash', true ), hash( 'sha256', $page->post_title . '|' . $page->post_content ) ) ) { return null; }
    // Saved site-editor templates and page-specific files take precedence.
    foreach ( array( 'page', 'page-' . $page->post_name, 'page-' . $page->ID, 'pilgrim' ) as $slug ) {
        $template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template' );
        if ( $template && ( 'custom' === $template->source || str_starts_with( $slug, 'page-' ) ) ) { return null; }
    }
    $intro = ''; $photo = ''; $photo_id = 0; $sections = array(); $tail = ''; $extra = array();
    $blocks = parse_blocks( $page->post_content );
    foreach ( $blocks as $index => $block ) {
        if ( ! $intro && ! $sections && 'core/paragraph' === $block['blockName'] ) { $intro = render_block( $block ); continue; }
        if ( ! $photo && ! $sections && 'core/image' === $block['blockName'] ) { $photo = render_block( $block ); $photo_id = absint( $block['attrs']['id'] ?? 0 ); continue; }
        if ( 'core/heading' === $block['blockName'] ) { $sections[] = array( 'heading' => render_block( $block ), 'body' => '' ); continue; }
        if ( $index === count( $blocks ) - 1 && 'core/paragraph' === $block['blockName'] && 'small' === ( $block['attrs']['fontSize'] ?? '' ) ) { $tail = render_block( $block ); continue; }
        if ( $sections ) { $sections[count( $sections ) - 1]['body'] .= render_block( $block ); }
        else { $extra[] = render_block( $block ); }
    }
    return $intro && count( $sections ) >= 3 ? compact( 'page', 'intro', 'photo', 'photo_id', 'sections', 'tail', 'extra' ) : null;
}
add_filter( 'page_template_hierarchy', function ( array $templates ): array {
    if ( ! prihod_theme_pilgrim_data() ) { return $templates; }
    $index = array_search( 'page.php', $templates, true );
    if ( false !== $index ) { array_splice( $templates, $index, 0, array( 'pilgrim.php' ) ); }
    return $templates;
} );
function prihod_theme_pilgrim_heading(): string {
    $data = prihod_theme_pilgrim_data(); if ( ! $data ) { return prihod_theme_heading(); }
    $options = georg_kloster_prihod_options();
    $image = get_the_post_thumbnail_url( $data['page']->ID, 'full' ) ?: wp_get_attachment_image_url( $options['hero_image'], 'full' );
    return '<section class="prihod-pilgrim-heading"' . ( $image ? ' style="--prihod-hero-image:url(' . esc_url( $image ) . ')"' : '' ) . '><div class="prihod-container"><a class="prihod-breadcrumb" href="' . esc_url( prihod_theme_preview_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Главная', 'georg-kloster-prihod' ) . '</a><h1>' . esc_html( $data['page']->post_title ) . '</h1><div class="prihod-pilgrim-intro">' . $data['intro'] . '</div></div></section>';
}
function prihod_theme_pilgrim_content( string $html ): string {
    $data = prihod_theme_pilgrim_data(); $post = get_post();
    if ( ! $data || ! $post || $post->ID !== $data['page']->ID ) { return $html; }
    $sections = $data['sections']; $profile = prihod_theme_profile();
    $html = '<div class="prihod-pilgrim-content"><nav class="prihod-pilgrim-quick" aria-label="' . esc_attr__( 'Информация паломнику', 'georg-kloster-prihod' ) . '">';
    foreach ( array( array( 'road', 'contacts', __( 'Как добраться', 'georg-kloster-prihod' ) ), array( 'info', 'news', __( 'Первое посещение', 'georg-kloster-prihod' ) ), array( 'groups', 'group', __( 'Групповой визит', 'georg-kloster-prihod' ) ), array( 'groups', 'about', __( 'Пребывание в обители', 'georg-kloster-prihod' ) ) ) as $item ) {
        $html .= '<a href="#prihod-pilgrim-' . esc_attr( $item[0] ) . '">' . prihod_theme_icon( $item[1] ) . '<span>' . esc_html( $item[2] ) . '</span></a>';
    }
    $html .= '</nav>' . implode( '', $data['extra'] ) . '<div class="prihod-pilgrim-visit"><section id="prihod-pilgrim-info">' . $sections[0]['heading'] . $sections[0]['body'];
    $schedule = prihod_theme_url( 'schedule' );
    if ( $schedule ) { $html .= '<a class="prihod-pilgrim-schedule" href="' . esc_url( $schedule ) . '">' . esc_html__( 'Расписание богослужений', 'georg-kloster-prihod' ) . ' →</a>'; }
    $html .= '</section><section id="prihod-pilgrim-groups"><h2>' . esc_html__( 'Групповой визит и пребывание', 'georg-kloster-prihod' ) . '</h2><p>' . esc_html__( 'Возможность пребывания и условия группового визита согласуйте с обителью заранее.', 'georg-kloster-prihod' ) . '</p>' . $sections[2]['heading'] . $sections[2]['body'] . '<div class="prihod-actions">';
    if ( $profile['phone'] ) { $html .= '<a class="prihod-pilgrim-phone" href="' . esc_url( 'tel:' . preg_replace( '/[^+0-9]/', '', $profile['phone'] ) ) . '">' . prihod_theme_icon( 'phone' ) . esc_html__( 'Позвонить в обитель', 'georg-kloster-prihod' ) . '</a>'; }
    $contact = prihod_theme_url( 'requests' ) ?: prihod_theme_url( 'contacts' );
    if ( $contact ) { $html .= '<a class="prihod-button" href="' . esc_url( $contact ) . '">' . esc_html__( 'Согласовать приезд', 'georg-kloster-prihod' ) . ' →</a>'; }
    $html .= '</div></section></div><section id="prihod-pilgrim-road"><div class="prihod-pilgrim-road-copy">' . $sections[1]['heading'] . ( $data['photo'] ? '<div class="prihod-pilgrim-road-photo">' . $data['photo'] . '</div>' : '' ) . $sections[1]['body'] . '</div></section>';
    foreach ( array_slice( $sections, 3 ) as $section ) { $html .= '<section class="prihod-pilgrim-extra">' . $section['heading'] . $section['body'] . '</section>'; }
    return $html . $data['tail'] . '</div>';
}
