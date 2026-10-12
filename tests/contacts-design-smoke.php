<?php
// Disposable WordPress only; restores every touched owner value.
$contacts_query = $GLOBALS['wp_query']; $contacts_post = $GLOBALS['post']; $contacts_get = $_GET;
$contacts_template = $GLOBALS['_wp_current_template_id'] ?? ''; $contacts_template_content = $GLOBALS['_wp_current_template_content'] ?? '';
$contact_page = get_post( $first['pages']['contacts'] ); $contact_original = $contact_page->post_content; $contact_slug = get_page_template_slug( $contact_page->ID );
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $contact_page->ID ) ); $GLOBALS['post'] = $contact_page;
$_GET = array( 'prihod_preview' => 'classic' ); get_page_template(); $contact_template = get_block_template( get_stylesheet() . '//contacts', 'wp_template' );
$check( get_stylesheet() . '//contacts' === $GLOBALS['_wp_current_template_id'] && ! str_contains( $contact_template->content, 'sidebar' ), 'Generated contact page uses native wide hierarchy without saving its template.' );
$contact_html = do_blocks( $contact_template->content ); $contact_data = prihod_theme_contacts_data();
$check( strpos( $contact_html, $contact_data['intro'] ) < strpos( $contact_html, 'prihod-contacts-primary' ) && strpos( $contact_html, 'prihod-contacts-primary' ) < strpos( $contact_html, 'prihod-contacts-road' ), 'Introduction, genuine contacts and detailed road instructions retain their intended hierarchy.' );
$check( str_contains( $contact_html, 'href="tel:+491714340444"' ) && str_contains( $contact_html, 'href="mailto:info@georg-kloster.de"' ) && str_contains( $contact_html, 'href="https://georg-kloster.ru/' ), 'Native contacts retain actual address, clickable phone/email and website.' );
$check( str_contains( $contact_html, $contact_data['body'] ) && str_contains( $contact_html, $contact_data['photo'] ) && str_contains( $contact_html, $contact_data['tail'] ), 'Every original road paragraph, photograph/caption and source remains present.' );
$check( str_contains( $contact_html, prihod_theme_contact_map() ), 'Contact page reuses the existing map component and settings.' );
foreach ( array( 'classic', 'hospitality', 'editorial', 'tradition', 'blue' ) as $scheme ) {
    $_GET['prihod_preview'] = $scheme; $contact_html = do_blocks( $contact_template->content );
    $check( str_contains( $contact_html, esc_url( prihod_theme_url( 'requests' ) ) ) && str_contains( $contact_html, esc_url( prihod_theme_url( 'first-visit' ) ) ), 'Real contact navigation preserves preview: ' . $scheme );
}
wp_update_post( array( 'ID' => $contact_page->ID, 'post_content' => $contact_original . '<p>Owner contact text.</p>' ) ); $GLOBALS['post'] = get_post( $contact_page->ID );
$check( null === prihod_theme_contacts_data() && 'owner-content' === apply_filters( 'render_block_core/post-content', 'owner-content' ), 'Manual contact content is never rearranged.' );
wp_update_post( array( 'ID' => $contact_page->ID, 'post_content' => $contact_original ) ); $GLOBALS['post'] = get_post( $contact_page->ID ); update_post_meta( $contact_page->ID, '_wp_page_template', 'wide' );
$check( null === prihod_theme_contacts_data(), 'Explicit contact templates retain precedence.' ); update_post_meta( $contact_page->ID, '_wp_page_template', $contact_slug );
$contact_custom = wp_insert_post( array( 'post_type' => 'wp_template', 'post_status' => 'publish', 'post_name' => 'page-' . $contact_page->ID, 'post_title' => 'Owner contacts template', 'post_content' => '<!-- wp:post-content /-->' ) ); wp_set_object_terms( $contact_custom, get_stylesheet(), 'wp_theme' );
$check( null === prihod_theme_contacts_data(), 'Saved page-specific Site Editor contact template retains precedence.' ); wp_delete_post( $contact_custom, true );
$check( get_post_field( 'post_content', $contact_page->ID ) === $contact_original && get_page_template_slug( $contact_page->ID ) === $contact_slug, 'Contact fixture restores original content and template.' );
$GLOBALS['wp_query'] = $contacts_query; $GLOBALS['post'] = $contacts_post; $_GET = $contacts_get; $GLOBALS['_wp_current_template_id'] = $contacts_template; $GLOBALS['_wp_current_template_content'] = $contacts_template_content;
