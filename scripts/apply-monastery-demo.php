<?php
/** Owner-authorized, repeatable content import. Run with wp eval-file --user=<administrator>. */
defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_pages' ) || ! current_user_can( 'manage_prihod' ) ) { throw new RuntimeException( 'Administrator required.' ); }
if ( 'prihod.georg-kloster.ru' !== wp_parse_url( home_url(), PHP_URL_HOST ) && ! defined( 'PRIHOD_DEMO_TEST' ) ) { throw new RuntimeException( 'This content pack belongs to prihod.georg-kloster.ru.' ); }
$pack = json_decode( file_get_contents( __DIR__ . '/monastery-content.json' ), true, 512, JSON_THROW_ON_ERROR );
$resources = get_option( 'prihod_resources', array() );
$original_titles = array();
foreach ( $pack['pages'] as $key => $entry ) { $p = get_post( $resources[$key]['id'] ?? 0 ); if ( $p ) { $original_titles[$p->ID] = $p->post_title; } }
$report = array( 'pages' => array(), 'preserved' => array(), 'images' => array(), 'posts' => array() );
$hash = static fn( $post ) => hash( 'sha256', $post->post_title . '|' . $post->post_content );
$paragraph = static fn( $text ) => '<!-- wp:paragraph --><p>' . wp_kses_post( $text ) . '</p><!-- /wp:paragraph -->';
$heading = static fn( $text ) => '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $text ) . '</h2><!-- /wp:heading -->';
$source = static fn( $url ) => '<!-- wp:paragraph {"fontSize":"small"} --><p class="has-small-font-size"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Материал на сайте монастыря →</a></p><!-- /wp:paragraph -->';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$media = array();
foreach ( $pack['images'] as $key => $image ) {
    if ( 'https' !== wp_parse_url( $image['url'], PHP_URL_SCHEME ) || 'georg-kloster.ru' !== wp_parse_url( $image['url'], PHP_URL_HOST ) ) { throw new RuntimeException( 'Unexpected image source.' ); }
    $found = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'meta_key' => '_prihod_demo_source', 'meta_value' => $image['url'] ) );
    $id = $found ? $found[0]->ID : media_sideload_image( $image['url'], 0, $image['caption'], 'id' );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    update_post_meta( $id, '_prihod_demo_source', $image['url'] );
    update_post_meta( $id, '_wp_attachment_image_alt', $image['caption'] );
    $media[$key] = (int) $id;
    $report['images'][$key] = $id;
}
$image_block = static function ( $key ) use ( $media, $pack ): string {
    $id = $media[$key];
    return '<!-- wp:image ' . wp_json_encode( array( 'id' => $id, 'sizeSlug' => 'large', 'linkDestination' => 'none' ) ) . ' --><figure class="wp-block-image size-large"><img src="' . esc_url( wp_get_attachment_image_url( $id, 'large' ) ) . '" alt="' . esc_attr( $pack['images'][$key]['caption'] ) . '" class="wp-image-' . $id . '"><figcaption class="wp-element-caption">' . esc_html( $pack['images'][$key]['caption'] ) . '</figcaption></figure><!-- /wp:image -->';
};
// Fill a fresh demo profile. Existing owner-entered values take precedence.
$profile = Prihod\Profile::get();
foreach ( $pack['profile'] as $key => $value ) { if ( empty( $profile[$key] ) || ( 'type' === $key && empty( $profile['name'] ) ) ) { $profile[$key] = $value; } }
update_option( Prihod\Profile::OPTION, Prihod\Profile::sanitize( $profile ), false );
$options = prihod_theme_options();
foreach ( array( 'hero_image' => 'monastery', 'about_image' => 'trinity' ) as $key => $image ) { if ( ! $options[$key] ) { $options[$key] = $media[$image]; } }
if ( ! $options['gallery'] ) { $options['gallery'] = implode( ',', array_values( array_intersect_key( $media, array_flip( $pack['gallery'] ) ) ) ); }
if ( ! $options['map_url'] ) { $options['map_url'] = 'https://www.openstreetmap.org/search?query=Götschendorf%2033%2017268%20Milmersdorf'; }
update_option( 'prihod_theme_options', prihod_theme_sanitize( $options ), false );
$terms = array();
foreach ( array( 'news' => 'Новости обители', 'sermons' => 'Проповеди и беседы' ) as $key => $label ) {
    $term = term_exists( 'prihod-' . $key, 'category' );
    if ( ! $term ) { $term = wp_insert_term( $label, 'category', array( 'slug' => 'prihod-' . $key ) ); }
    if ( is_wp_error( $term ) ) { throw new RuntimeException( $term->get_error_message() ); }
    $terms[$key] = (int) $term['term_id'];
}
foreach ( $pack['posts'] as $entry ) {
    $found = get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'draft', 'trash' ), 'numberposts' => 1, 'meta_key' => '_prihod_demo_source', 'meta_value' => $entry['source'] ) );
    if ( $found ) { $report['posts'][] = $found[0]->ID; continue; }
    $content = $paragraph( $entry['intro'] );
    foreach ( $entry['sections'] as $section ) { $content .= $heading( $section[0] ) . $paragraph( $section[1] ); }
    $content .= $source( $entry['source'] );
    $id = wp_insert_post( wp_slash( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $entry['title'], 'post_content' => $content, 'post_excerpt' => $entry['intro'], 'post_date' => $entry['date'], 'post_category' => array( $terms[$entry['category']] ), 'meta_input' => array( '_prihod_demo_source' => $entry['source'] ) ) ), true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    set_post_thumbnail( $id, $media[$entry['image']] );
    $report['posts'][] = $id;
}
foreach ( array( 'location' => array( 'prihod_location', 'Храм святого великомученика Георгия', 'monastery' ), 'clergy' => array( 'prihod_clergy', 'Игумен Даниил (Ирбитс)', 'clergy' ) ) as $key => $entry ) {
    $found = get_posts( array( 'post_type' => $entry[0], 'post_status' => array( 'publish', 'draft', 'trash' ), 'numberposts' => 1, 'meta_key' => '_prihod_monastery_record', 'meta_value' => $key ) );
    if ( $found ) { $records[$key] = $found[0]->ID; continue; }
    $details = 'location' === $key ? array( 'address' => $pack['profile']['address'], 'phone' => $pack['profile']['phone'], 'timezone' => 'Europe/Berlin' ) : array( 'rank' => 'Игумен, наместник монастыря', 'location_ids' => array( $records['location'] ) );
    $id = wp_insert_post( wp_slash( array( 'post_type' => $entry[0], 'post_status' => 'publish', 'post_title' => $entry[1], 'post_content' => $paragraph( 'location' === $key ? $pack['profile']['description'] : $pack['pages']['clergy']['sections'][0][1] ), 'meta_input' => array( '_prihod_details' => $details, '_prihod_monastery_record' => $key ) ) ), true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
    set_post_thumbnail( $id, $media[$entry[2]] ); $records[$key] = $id;
}
foreach ( array( '2026-10-17' => '12:00', '2026-10-18' => '12:00', '2026-10-24' => '12:00', '2026-10-25' => '13:00', '2026-10-31' => '13:00' ) as $date => $time ) {
    $found = get_posts( array( 'post_type' => 'prihod_service', 'post_status' => array( 'publish', 'draft', 'trash' ), 'numberposts' => 1, 'meta_key' => '_prihod_monastery_record', 'meta_value' => $date ) );
    if ( $found ) { continue; }
    $rule = Prihod\Schedule::validate( array( 'kind' => 'single', 'date' => $date, 'time' => $time, 'location_id' => $records['location'], 'timezone' => 'Europe/Berlin' ) );
    $id = wp_insert_post( wp_slash( array( 'post_type' => 'prihod_service', 'post_status' => 'publish', 'post_title' => 'Божественная литургия', 'post_content' => $paragraph( 'По опубликованному расписанию монастыря на октябрь 2026 года. Перед поездкой уточняйте актуальное время.' ) . $source( 'https://georg-kloster.ru/raspisanie-bogosluzheniy/' ), 'meta_input' => array( '_prihod_service' => $rule, '_prihod_monastery_record' => $date ) ) ), true );
    if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
}
foreach ( $pack['pages'] as $key => $entry ) {
    $post = get_post( $resources[$key]['id'] ?? 0 );
    if ( ! $post || ! get_post_meta( $post->ID, '_prihod_theme_demo', true ) || 'trash' === $post->post_status || ! hash_equals( (string) get_post_meta( $post->ID, '_prihod_generated_hash', true ), $hash( $post ) ) ) { $report['preserved'][] = $key; continue; }
    if ( get_post_meta( $post->ID, '_prihod_monastery_demo', true ) ) { $report['pages'][$key] = $post->ID; continue; }
    $content = $paragraph( $entry['intro'] );
    if ( ! empty( $entry['image'] ) ) { $content .= $image_block( $entry['image'] ); }
    foreach ( $entry['sections'] as $section ) { $content .= $heading( $section[0] ) . $paragraph( $section[1] ); }
    if ( in_array( $key, array( 'news', 'sermons' ), true ) ) {
        $content .= '<!-- wp:query ' . wp_json_encode( array( 'query' => array( 'perPage' => 12, 'postType' => 'post', 'inherit' => false, 'taxQuery' => array( 'category' => array( $terms[$key] ) ) ) ) ) . ' --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-featured-image {"isLink":true} /--><!-- wp:post-title {"isLink":true} /--><!-- wp:post-date /--><!-- wp:post-excerpt /--><!-- /wp:post-template --><!-- wp:query-pagination /--></div><!-- /wp:query -->';
    } elseif ( 'photos' === $key ) {
        $content .= '<!-- wp:gallery {"columns":3} --><figure class="wp-block-gallery has-nested-images columns-3 is-cropped">';
        foreach ( $pack['gallery'] as $photo ) { $content .= $image_block( $photo ); }
        $content .= '</figure><!-- /wp:gallery -->';
    } elseif ( in_array( $key, array( 'clergy', 'schedule', 'requests', 'contacts', 'calendar', 'prayer-notes', 'donations' ), true ) ) {
        // Keep the installed providers' actual block/shortcode configuration.
        foreach ( parse_blocks( $post->post_content ) as $block ) { if ( ! in_array( $block['blockName'], array( null, 'core/paragraph' ), true ) ) { $content .= serialize_block( $block ); } }
    }
    $content .= $source( $entry['source'] );
    $result = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_title' => $entry['title'], 'post_content' => $content, 'post_status' => 'publish' ) ), true );
    if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
    update_post_meta( $post->ID, '_prihod_generated_hash', $hash( get_post( $post->ID ) ) );
    update_post_meta( $post->ID, '_prihod_monastery_demo', 1 );
    update_post_meta( $post->ID, '_wp_page_template', $entry['template'] );
    $report['pages'][$key] = $post->ID;
}
// A compact menu replaces only the untouched generated menu.
$nav = get_post( $resources['_navigation']['id'] ?? 0 );
$untouched = $nav && hash_equals( (string) get_post_meta( $nav->ID, '_prihod_generated_hash', true ), $hash( $nav ) );
if ( $nav && ! get_post_meta( $nav->ID, '_prihod_generated_hash', true ) && ! empty( $resources['_navigation']['owned'] ) ) {
    $blocks = array_values( array_filter( parse_blocks( $nav->post_content ), static fn( $b ) => null !== $b['blockName'] ) );
    $untouched = count( $blocks ) === count( $original_titles ); $seen = array();
    foreach ( $blocks as $block ) { $id = (int) ( $block['attrs']['id'] ?? 0 ); if ( 'core/navigation-link' !== $block['blockName'] || isset( $seen[$id] ) || ( $original_titles[$id] ?? null ) !== ( $block['attrs']['label'] ?? '' ) ) { $untouched = false; } $seen[$id] = true; }
}
if ( $untouched ) {
    $link = static function ( $key ) use ( $resources, $pack ): string {
        $id = $resources[$key]['id'];
        return '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $pack['pages'][$key]['title'], 'type' => 'page', 'id' => $id, 'url' => get_permalink( $id ), 'kind' => 'post-type' ), JSON_UNESCAPED_UNICODE ) . ' /-->';
    };
    $content = $link( 'home' );
    foreach ( $pack['menu'] as $label => $keys ) {
        $content .= '<!-- wp:navigation-submenu ' . wp_json_encode( array( 'label' => $label ), JSON_UNESCAPED_UNICODE ) . ' -->';
        foreach ( $keys as $key ) { $content .= $link( $key ); }
        $content .= '<!-- /wp:navigation-submenu -->';
    }
    $content .= $link( 'contacts' );
    wp_update_post( wp_slash( array( 'ID' => $nav->ID, 'post_content' => $content ) ) );
    update_post_meta( $nav->ID, '_prihod_generated_hash', $hash( get_post( $nav->ID ) ) );
}
echo wp_json_encode( $report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . "\n";
