<?php
/**
 * External WP-CLI migration, deliberately excluded from the theme ZIP.
 * wp eval-file migrate_home_blocks.php              creates a reviewable draft.
 * wp eval-file migrate_home_blocks.php apply        copies the reviewed blocks to the original page.
 * The page ID, URL, language metadata and all legacy options remain intact.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( "Run through WP-CLI.\n" ); }
if ( ! function_exists( 'prihod_theme_home_options' ) ) { WP_CLI::error( 'Activate Georg-Kloster Prihod first.' ); }
$source_id = absint( get_option( 'page_on_front' ) );
$source = get_post( $source_id );
if ( ! $source || 'page' !== $source->post_type || 'page' !== get_option( 'show_on_front' ) ) { WP_CLI::error( 'A static homepage is required.' ); }
if ( 'rollback' === ( $args[0] ?? '' ) ) {
    if ( ! metadata_exists( 'post', $source_id, '_prihod_original_home_content' ) ) { WP_CLI::error( 'No migration backup exists.' ); }
    $result = wp_update_post( wp_slash( array( 'ID' => $source_id, 'post_content' => get_post_meta( $source_id, '_prihod_original_home_content', true ) ) ), true );
    if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
    delete_post_meta( $source_id, '_prihod_blocks_source' );
    WP_CLI::success( 'Original homepage content restored. Restore the earlier theme to render its legacy sections.' );
    return;
}
if ( get_post_meta( $source_id, '_prihod_blocks_source', true ) ) { WP_CLI::success( 'The homepage already uses the migrated blocks.' ); return; }
$drafts = get_posts( array( 'post_type' => 'page', 'post_status' => array( 'draft', 'publish' ), 'meta_key' => '_prihod_blocks_source', 'meta_value' => $source_id, 'numberposts' => 1 ) );
if ( $drafts ) {
    $draft_id = $drafts[0]->ID;
} else {
    $block = static function ( string $name, string $html, array $attrs = array() ): string {
        return '<!-- wp:' . $name . ( $attrs ? ' ' . wp_json_encode( $attrs ) : '' ) . ' -->' . $html . '<!-- /wp:' . $name . ' -->';
    };
    $paragraph = static fn( $text ) => '' !== trim( (string) $text ) ? $block( 'paragraph', '<p>' . nl2br( esc_html( $text ) ) . '</p>' ) : '';
    $heading = static fn( $text, $level = 2 ) => $text ? $block( 'heading', '<h' . $level . ' class="wp-block-heading">' . esc_html( $text ) . '</h' . $level . '>', array( 'level' => $level ) ) : '';
    $button = static function ( $url, $label ) use ( $block ): string {
        if ( ! $url || ! $label ) { return ''; }
        return $block( 'buttons', '<div class="wp-block-buttons">' . $block( 'button', '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></div>' ) . '</div>' );
    };
    $image = static function ( $id ) use ( $block ): string {
        $url = wp_get_attachment_image_url( $id, 'large' );
        return $url ? $block( 'image', '<figure class="wp-block-image size-large"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( get_post_meta( $id, '_wp_attachment_image_alt', true ) ) . '" class="wp-image-' . absint( $id ) . '"/></figure>', array( 'id' => absint( $id ), 'sizeSlug' => 'large', 'linkDestination' => 'none' ) ) : '';
    };
    $home = prihod_theme_home_options(); $options = georg_kloster_prihod_options(); $profile = prihod_theme_profile();
    $content = '';
    foreach ( $home['order'] as $key ) {
        if ( empty( $home['sections'][$key]['enabled'] ) ) { continue; }
        $section = $paragraph( prihod_theme_home_eyebrow( $key ) ) . $heading( prihod_theme_home_title( $key ), 'hero' === $key ? 1 : 2 );
        switch ( $key ) {
            case 'hero':
                $section .= $paragraph( prihod_theme_home_text( $home['hero_description'], 'hero-description', $profile['description'] ) );
                foreach ( array( 'quote_text', 'hero_quote_text', 'hero_motto' ) as $field ) { $section .= $paragraph( $options[$field] ); }
                foreach ( array( 'quote_source', 'hero_quote_source' ) as $field ) { $section .= $paragraph( $options[$field] ); }
                $section .= $image( $options['hero_image'] ?: get_post_thumbnail_id( $source_id ) );
                $section .= $button( prihod_theme_home_url( 'schedule' ), prihod_theme_home_copy( 'hero_schedule_button' ) );
                $section .= $button( prihod_theme_home_url( 'classic' === prihod_theme_scheme() ? 'contacts' : 'about' ), prihod_theme_home_copy( 'hero_secondary_button' ) );
                break;
            case 'about':
                $section .= $paragraph( prihod_theme_home_text( $home['about_description'], 'about-description', $profile['description'] ) );
                $section .= $image( prihod_theme_resource_image( 'about', true ) ?: $options['hero_image'] );
                $section .= $button( prihod_theme_home_url( 'about' ), prihod_theme_home_copy( 'about_button' ) );
                break;
            case 'features':
                foreach ( $home['card_order'] as $card_key ) {
                    $card = $home['cards'][$card_key]; if ( ! $card['enabled'] ) { continue; }
                    $id = prihod_theme_home_resource_id( $card_key );
                    $section .= $heading( prihod_theme_home_text( $card['title'], 'card-' . $card_key . '-title', $id ? get_the_title( $id ) : '' ), 3 );
                    if ( $home['cards_show_images'] ) { $section .= $image( prihod_theme_resource_image( $card_key, true ) ); }
                    $section .= $paragraph( prihod_theme_home_text( $card['description'], 'card-' . $card_key . '-description' ) );
                    $section .= $button( prihod_theme_home_url( $card_key ), $card['button'] ?: ( $card['title'] ?: ( $id ? get_the_title( $id ) : '' ) ) );
                }
                break;
            case 'topics':
                foreach ( array( 'first-visit', 'sermons' ) as $topic ) { $id = prihod_theme_home_resource_id( $topic ); $section .= $button( prihod_theme_home_url( $topic ), $id ? get_the_title( $id ) : '' ); }
                break;
            case 'services':
                if ( WP_Block_Type_Registry::get_instance()->is_registered( 'gkpsch/next-services' ) ) {
                    $section .= '<!-- wp:gkpsch/next-services ' . wp_json_encode( array( 'limit' => $home['services_count'] ?: 3 ) ) . ' /-->';
                }
                $section .= $button( prihod_theme_home_url( 'schedule' ), prihod_theme_home_copy( 'services_button' ) );
                break;
            case 'news':
                $query = array( 'perPage' => $home['news_count'] ?: 3, 'pages' => 0, 'offset' => 0, 'postType' => 'post', 'order' => 'desc', 'orderBy' => 'date', 'author' => '', 'search' => '', 'exclude' => array(), 'sticky' => '', 'inherit' => false );
                if ( $home['news_category'] > 0 ) { $query['taxQuery'] = array( 'category' => array( $home['news_category'] ) ); }
                $template = $home['news_show_images'] ? '<!-- wp:post-featured-image {"isLink":true} /-->' : '';
                if ( $home['news_show_date'] ) { $template .= '<!-- wp:post-date /-->'; }
                $template .= '<!-- wp:post-title {"isLink":true,"level":3} /-->';
                if ( $home['news_show_excerpt'] ) { $template .= '<!-- wp:post-excerpt ' . wp_json_encode( array( 'excerptLength' => $home['news_excerpt_words'] ?: 20 ) ) . ' /-->'; }
                $section .= $block( 'query', '<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":' . ( $home['news_columns'] ?: 3 ) . '}} -->' . $template . '<!-- /wp:post-template --></div>', array( 'queryId' => 0, 'query' => $query ) );
                $section .= $button( prihod_theme_home_url( 'news' ), prihod_theme_home_copy( 'news_button' ) );
                break;
            case 'content': $section .= $source->post_content; break;
            case 'contacts':
                foreach ( array( 'address', 'phone', 'email', 'description' ) as $field ) {
                    if ( ! empty( $home['contact_items'][$field] ) ) { $section .= $paragraph( $profile[$field] ?? '' ); }
                }
                if ( ! empty( $home['contact_items']['image'] ) ) { $section .= $image( $options['hero_image'] ); }
                if ( ! empty( $home['contact_items']['map'] ) ) {
                    $map = prihod_theme_contact_map( $profile );
                    if ( $map ) { $section .= $block( 'html', $map ); }
                }
                $section .= $button( prihod_theme_home_url( 'contacts' ), prihod_theme_home_copy( 'contacts_button' ) );
                break;
        }
        if ( $section ) { $content .= $block( 'group', '<div class="wp-block-group">' . $section . '</div>', array( 'layout' => array( 'type' => 'constrained' ) ) ) . "\n"; }
    }
    $draft_id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => $source->post_title, 'post_content' => $content, 'post_author' => $source->post_author ) ), true );
    if ( is_wp_error( $draft_id ) ) { WP_CLI::error( $draft_id->get_error_message() ); }
    update_post_meta( $draft_id, '_prihod_blocks_source', $source_id );
    WP_CLI::log( 'Draft created: ' . $draft_id . '. Original page retained: ' . $source_id );
}
if ( 'apply' === ( $args[0] ?? '' ) ) {
    if ( ! metadata_exists( 'post', $source_id, '_prihod_original_home_content' ) ) {
        add_post_meta( $source_id, '_prihod_original_home_content', wp_slash( $source->post_content ), true );
    }
    wp_save_post_revision( $source_id );
    $result = wp_update_post( wp_slash( array( 'ID' => $source_id, 'post_content' => get_post_field( 'post_content', $draft_id ) ) ), true );
    if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
    update_post_meta( $source_id, '_prihod_blocks_source', $source_id );
    WP_CLI::success( 'Updated original homepage ' . $source_id . '. Original content is backed up in post metadata and revisions; legacy options are unchanged.' );
} else { WP_CLI::success( 'Review draft ' . $draft_id . ' in the page editor, then rerun with argument apply.' ); }
