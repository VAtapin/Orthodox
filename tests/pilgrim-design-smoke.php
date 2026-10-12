<?php
// Only the disposable monastery fixture. No such mutations run on the live site.
$pilgrim_query_before = $GLOBALS['wp_query']; $pilgrim_post_before = $GLOBALS['post'];
$pilgrim_template_before = $GLOBALS['_wp_current_template_id'] ?? ''; $pilgrim_template_content_before = $GLOBALS['_wp_current_template_content'] ?? '';
$pilgrim_options_before = get_option( 'prihod_theme_options' ); $pilgrim_get_before = $_GET;
$pilgrim_page = get_post( $first['pages']['first-visit'] ); $pilgrim_content_before = $pilgrim_page->post_content;
$pilgrim_page_template_before = get_page_template_slug( $pilgrim_page->ID );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $pilgrim_page->ID ) ); $GLOBALS['post'] = $pilgrim_page;
if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'prihod-theme/pilgrim-heading' ) ) { prihod_theme_register_blocks(); }
$_GET = array( 'prihod_preview' => 'classic' ); get_page_template();
$pilgrim_template = get_block_template( get_stylesheet() . '//pilgrim', 'wp_template' );
$check( get_stylesheet() . '//pilgrim' === $GLOBALS['_wp_current_template_id'] && ! str_contains( $pilgrim_template->content, 'prihod-sidebar' ), 'WordPress resolves the wide generated pilgrim template without saving the page template or adding a common sidebar.' );
$pilgrim_render = static fn() => do_blocks( $pilgrim_template->content );
$pilgrim_html = $pilgrim_render(); $pilgrim_data = prihod_theme_pilgrim_data();
$check( str_contains( $pilgrim_html, 'prihod-pilgrim-heading' ) && str_contains( $pilgrim_html, wp_strip_all_tags( $pilgrim_data['intro'] ) ), 'Pilgrim heading retains the real title and full native introduction.' );
$check( strpos( $pilgrim_html, 'prihod-pilgrim-quick' ) < strpos( $pilgrim_html, 'prihod-pilgrim-photos' ) && strpos( $pilgrim_html, 'prihod-pilgrim-photos' ) < strpos( $pilgrim_html, 'prihod-pilgrim-visit' ) && strpos( $pilgrim_html, 'prihod-pilgrim-visit' ) < strpos( $pilgrim_html, 'id="prihod-pilgrim-road"' ), 'Reference 11 areas retain their order: quick links, four photographs, visitor/group information, road/map.' );
$check( 2 === substr_count( $pilgrim_html, 'href="#prihod-pilgrim-groups"' ) && str_contains( $pilgrim_html, 'href="#prihod-pilgrim-info"' ) && str_contains( $pilgrim_html, 'href="#prihod-pilgrim-road"' ), 'All four quick tiles target actual sections; unconfirmed accommodation goes to prior agreement.' );
$check( 4 === substr_count( prihod_theme_pilgrim_photos( $pilgrim_data ), '<figure>' ) && str_contains( $pilgrim_html, 'prihod-pilgrim-road-photo' ) && str_contains( $pilgrim_html, esc_html( get_the_title( $first['images']['road'] ) ) ), 'Four genuine gallery photographs and captions appear, with the original road photograph retained in the road area.' );
foreach ( $pilgrim_data['sections'] as $section ) { $check( str_contains( $pilgrim_html, $section['body'] ) && str_contains( $pilgrim_html, $section['heading'] ), 'Every original information section is retained verbatim.' ); }
$check( str_contains( $pilgrim_html, prihod_theme_contact_map() ) && str_contains( $pilgrim_html, esc_url( prihod_theme_options()['map_url'] ) ) && str_contains( $pilgrim_html, 'tel:+491714340444' ), 'Pilgrim view reuses the real configured map link and phone, not a fake booking service.' );
$map_options = prihod_theme_options(); $map_options['map_embed'] = 'https://www.openstreetmap.org/export/embed.html?bbox=13.6518627%2C53.0821374%2C13.6918627%2C53.1021374&layer=mapnik&marker=53.0921374%2C13.6718627'; update_option( 'prihod_theme_options', $map_options );
$check( str_contains( $pilgrim_render(), 'openstreetmap.org/export/embed.html' ) && str_contains( prihod_theme_contact_map(), esc_url( $map_options['map_embed'] ) ), 'The existing configured embed is reused with its exact genuine coordinates when available.' );
foreach ( array( 'classic', 'hospitality', 'editorial', 'tradition', 'blue' ) as $scheme ) {
    $_GET['prihod_preview'] = $scheme; $pilgrim_html = $pilgrim_render();
    $check( str_contains( $pilgrim_html, esc_url( prihod_theme_url( 'requests' ) ) ) && str_contains( $pilgrim_html, esc_url( prihod_theme_url( 'schedule' ) ) ) && str_contains( $pilgrim_html, esc_url( prihod_theme_url( 'photos' ) ) ), 'Real question, schedule and photograph navigation retain owner preview: ' . $scheme );
}
$options = prihod_theme_options(); $options['pilgrim_photos'] = $first['images']['kitchen'] . ',' . $first['images']['road']; $options['pilgrim_image'] = $first['images']['monastery']; update_option( 'prihod_theme_options', $options );
$owner_photos = prihod_theme_pilgrim_photos( $pilgrim_data );
$check( 2 === substr_count( $owner_photos, '<figure>' ) && str_contains( $owner_photos, esc_html( get_the_title( $first['images']['kitchen'] ) ) ) && str_contains( prihod_theme_pilgrim_heading(), wp_get_attachment_image_url( $first['images']['monastery'], 'full' ) ), 'Owner-selected photographs, genuine captions and title image override defaults.' );
$sanitized = prihod_theme_sanitize( array( 'pilgrim_photos' => $first['images']['kitchen'] . ',999999,' . $first['images']['kitchen'], 'pilgrim_image' => 999999 ) );
$check( (string) $first['images']['kitchen'] === $sanitized['pilgrim_photos'] && 0 === $sanitized['pilgrim_image'], 'New photo fields reject invalid media and duplicate IDs.' );
$sanitized = prihod_theme_sanitize( array( 'quote_source' => 'Partial form' ) );
$check( $options['pilgrim_photos'] === $sanitized['pilgrim_photos'] && $options['pilgrim_image'] === $sanitized['pilgrim_image'], 'Partial older forms preserve absent pilgrim photo settings.' );
$options['pilgrim_photos'] = ''; $options['gallery'] = ''; $options['map_url'] = ''; $options['map_embed'] = ''; update_option( 'prihod_theme_options', $options );
$check( '' === prihod_theme_pilgrim_photos( $pilgrim_data ) && '' === prihod_theme_contact_map() && str_contains( $pilgrim_render(), 'prihod-pilgrim-road-copy' ), 'Missing photographs/map produce no fake images or links and keep real road information.' );
update_option( 'prihod_theme_options', $pilgrim_options_before );
wp_update_post( array( 'ID' => $pilgrim_page->ID, 'post_content' => $pilgrim_content_before . '<!-- wp:paragraph --><p>Owner pilgrimage text.</p><!-- /wp:paragraph -->' ) ); $GLOBALS['post'] = get_post( $pilgrim_page->ID );
$check( null === prihod_theme_pilgrim_data() && 'owner-output' === prihod_theme_pilgrim_content( 'owner-output' ), 'Manual content edits disable automatic rearrangement.' );
get_page_template(); $check( get_stylesheet() . '//pilgrim' !== $GLOBALS['_wp_current_template_id'], 'A manually edited page keeps its original WordPress template hierarchy.' );
wp_update_post( array( 'ID' => $pilgrim_page->ID, 'post_content' => $pilgrim_content_before ) ); $GLOBALS['post'] = get_post( $pilgrim_page->ID );
update_post_meta( $pilgrim_page->ID, '_wp_page_template', 'wide' );
$check( null === prihod_theme_pilgrim_data(), 'An explicitly selected different template is preserved.' );
update_post_meta( $pilgrim_page->ID, '_wp_page_template', $pilgrim_page_template_before );
$custom_template = wp_insert_post( array( 'post_type' => 'wp_template', 'post_status' => 'publish', 'post_name' => 'page-' . $pilgrim_page->ID, 'post_title' => 'Owner page template', 'post_content' => '<!-- wp:post-content /-->' ) ); wp_set_object_terms( $custom_template, get_stylesheet(), 'wp_theme' );
$check( null === prihod_theme_pilgrim_data(), 'A saved page-specific Site Editor template retains precedence.' ); wp_delete_post( $custom_template, true );
$check( get_post_field( 'post_content', $pilgrim_page->ID ) === $pilgrim_content_before && get_page_template_slug( $pilgrim_page->ID ) === $pilgrim_page_template_before && get_option( 'prihod_theme_options' ) === $pilgrim_options_before, 'Fixture cleanup restores the original page, template and theme settings.' );
$GLOBALS['wp_query'] = $pilgrim_query_before; $GLOBALS['post'] = $pilgrim_post_before; $_GET = $pilgrim_get_before;
$GLOBALS['_wp_current_template_id'] = $pilgrim_template_before; $GLOBALS['_wp_current_template_content'] = $pilgrim_template_content_before;
